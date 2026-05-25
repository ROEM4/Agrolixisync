<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PFRecord;
use App\Models\Location;
use Illuminate\Support\Facades\Validator;

class PFRecordController extends Controller
{
    public function index(Request $request)
    {
        $location_id = $request->query('location_id');
        $locations = Location::with('lote')->orderBy('name')->get();

        $q = PFRecord::query()->orderByDesc('recorded_at');
        if ($location_id) {
            $q->where('location_id', $location_id);
        }
        $records = $q->limit(30)->get();

        // Obtener lecturas para pre-llenado (Superficial 20cm y Profunda 60cm)
        $ce_sup = null;
        $ce_prof = null;
        
        if ($location_id) {
            // Buscamos el último análisis realizado para esta ubicación
            // Esto sirve tanto para el grupo Experimental (sensores) como Control (manual/simulado)
            $latestAnalysis = \App\Models\Analysis::where('location_id', $location_id)
                ->orderByDesc('analyzed_at')
                ->first();

            if ($latestAnalysis) {
                $ce_sup = $latestAnalysis->conductivity_superficial;
                $ce_prof = $latestAnalysis->conductivity_profundo;
            } else {
                // Fallback a lecturas directas de sensores si no hay análisis
                $readings = \App\Models\Reading::whereHas('sensor', function($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                })->orderByDesc('recorded_at')->limit(10)->get();
                
                $r_sup = $readings->filter(fn($r) => $r->sensor->depth == 20)->first();
                $r_prof = $readings->filter(fn($r) => $r->sensor->depth == 60)->first();
                
                $ce_sup = $r_sup ? $r_sup->conductivity : null;
                $ce_prof = $r_prof ? $r_prof->conductivity : null;
            }
        }

        return view('dashboard.pf_ficha', [
            'locations' => $locations,
            'location' => $location_id ? Location::find($location_id) : null,
            'records' => $records,
            'location_id' => $location_id,
            'ce_sup' => $ce_sup,
            'ce_prof' => $ce_prof
        ]);
    }

    public function export(Request $request)
    {
        $location_id = $request->query('location_id');
        $query = PFRecord::with('location.lote')->orderByDesc('recorded_at');
        if ($location_id) {
            $query->where('location_id', $location_id);
        }
        $data = $query->get();

        $filename = 'registros_pf_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            // Headers optimizados para Jamovi (Sin espacios, nombres cortos)
            fputcsv($file, [
                'ID', 
                'Fecha', 
                'Ubicacion', 
                'Grupo', 
                'GroupCode', 
                'CE_Sup', 
                'CE_Prof', 
                'ILx_Exp', 
                'ILx_Ref', 
                'PF_Porcentaje'
            ]);

            foreach ($data as $r) {
                // GroupCode: Control = 0, Experimental = 1 (Para ANOVA/T-Test en Jamovi)
                $groupCode = ($r->experimental_group === 'control') ? 0 : 1;

                fputcsv($file, [
                    $r->id,
                    $r->recorded_at->format('Y-m-d H:i:s'),
                    ($r->location->name ?? 'N/A'),
                    $r->experimental_group,
                    $groupCode,
                    number_format($r->ce_superficial, 3, '.', ''),
                    number_format($r->ce_profunda, 3, '.', ''),
                    number_format($r->ce_measured, 4, '.', ''), // ILx Experimental
                    number_format($r->ce_reference, 4, '.', ''), // ILx Control/Ref
                    number_format($r->pf_percentage, 2, '.', '')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'location_id' => 'nullable|exists:locations,id',
            'recorded_at' => 'required|date',
            'ce_reference' => 'required|numeric', // ILx Control
            'ce_measured' => 'required|numeric',  // ILx Experimental
            'ce_superficial' => 'nullable|numeric',
            'ce_profunda' => 'nullable|numeric',
            'subparcela' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Enforce max 30 records per location
        if ($data['location_id']) {
            $count = PFRecord::where('location_id', $data['location_id'])->count();
            if ($count >= 30) {
                return back()->withErrors(['limit' => 'Ya existen 30 registros para esta ubicación.'])->withInput();
            }
        }

        $pf = null;
        $ilx_control = floatval($data['ce_reference']);
        $ilx_exp = floatval($data['ce_measured']);
        
        if ($ilx_control != 0) {
            $pf = (($ilx_control - $ilx_exp) / $ilx_control) * 100.0;
        }

        $location = null;
        if (isset($data['location_id'])) {
            $location = Location::find($data['location_id']);
        }

        PFRecord::create([
            'location_id' => $data['location_id'] ?? null,
            'experimental_group' => $location ? $location->experimental_group : 'experimental',
            'recorded_at' => $data['recorded_at'],
            'ce_superficial' => $data['ce_superficial'] ?? null,
            'ce_profunda' => $data['ce_profunda'] ?? null,
            'ce_reference' => $data['ce_reference'],
            'ce_measured' => $data['ce_measured'],
            'subparcela' => $data['subparcela'] ?? null,
            'pf_percentage' => $pf,
        ]);

        return redirect()->route('pf.ficha.index', ['location_id' => $data['location_id'] ?? null])->with('success', 'Registro PF guardado');
    }
}
