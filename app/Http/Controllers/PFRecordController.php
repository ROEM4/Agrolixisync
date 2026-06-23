<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegistroPorcentajePerdida;
use App\Models\Ubicacion;
use App\Models\AnalisisLixiviacion;
use App\Models\Lectura;
use Illuminate\Support\Facades\Validator;

class PFRecordController extends Controller
{
    public function index(Request $request)
    {
        $ubicacion_id = $request->query('location_id');
        $locations = Ubicacion::with('planta')->orderBy('nombre')->get();

        $q = RegistroPorcentajePerdida::query()->orderByDesc('fecha_registro');
        if ($ubicacion_id) {
            $q->where('ubicacion_id', $ubicacion_id);
        }
        $records = $q->limit(30)->get();

        // Obtener lecturas para pre-llenado (Superficial 20cm y Profunda 60cm)
        $ce_sup = null;
        $ce_prof = null;
        
        if ($ubicacion_id) {
            // Buscamos el último análisis realizado para esta ubicación
            $latestAnalysis = AnalisisLixiviacion::where('ubicacion_id', $ubicacion_id)
                ->orderByDesc('fecha_analisis')
                ->first();

            if ($latestAnalysis) {
                $ce_sup = $latestAnalysis->conductividad_superficial;
                $ce_prof = $latestAnalysis->conductividad_profundo;
            } else {
                // Fallback a lecturas directas de sensores si no hay análisis
                $readings = Lectura::whereHas('sensor', function($q) use ($ubicacion_id) {
                    $q->where('ubicacion_id', $ubicacion_id);
                })->orderByDesc('fecha_registro')->limit(10)->get();
                
                $r_sup = $readings->filter(fn($r) => $r->sensor->profundidad == 20)->first();
                $r_prof = $readings->filter(fn($r) => $r->sensor->profundidad == 60)->first();
                
                $ce_sup = $r_sup ? $r_sup->conductividad : null;
                $ce_prof = $r_prof ? $r_prof->conductividad : null;
            }
        }

        return view('dashboard.pf_ficha', [
            'locations' => $locations,
            'location' => $ubicacion_id ? Ubicacion::find($ubicacion_id) : null,
            'records' => $records,
            'location_id' => $ubicacion_id,
            'ce_sup' => $ce_sup,
            'ce_prof' => $ce_prof
        ]);
    }

    public function export(Request $request)
    {
        $ubicacion_id = $request->query('location_id');
        $query = RegistroPorcentajePerdida::with('ubicacion.planta')->orderByDesc('fecha_registro');
        if ($ubicacion_id) {
            $query->where('ubicacion_id', $ubicacion_id);
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
                $groupCode = ($r->grupo_experimental === 'control') ? 0 : 1;

                fputcsv($file, [
                    $r->id,
                    $r->fecha_registro->format('Y-m-d H:i:s'),
                    ($r->ubicacion->nombre ?? 'N/A'),
                    $r->grupo_experimental,
                    $groupCode,
                    number_format($r->ce_superficial, 3, '.', ''),
                    number_format($r->ce_profunda, 3, '.', ''),
                    number_format($r->ce_medida, 4, '.', ''), // ILx Experimental
                    number_format($r->ce_referencia, 4, '.', ''), // ILx Control/Ref
                    number_format($r->porcentaje_pf, 2, '.', '')
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
            'location_id' => 'nullable|exists:ubicaciones,id',
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
            $count = RegistroPorcentajePerdida::where('ubicacion_id', $data['location_id'])->count();
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
            $location = Ubicacion::find($data['location_id']);
        }

        RegistroPorcentajePerdida::create([
            'ubicacion_id' => $data['location_id'] ?? null,
            'grupo_experimental' => $location ? $location->grupo_experimental : 'experimental',
            'fecha_registro' => $data['recorded_at'],
            'ce_superficial' => $data['ce_superficial'] ?? null,
            'ce_profunda' => $data['ce_profunda'] ?? null,
            'ce_referencia' => $data['ce_reference'],
            'ce_medida' => $data['ce_measured'],
            'subparcela' => $data['subparcela'] ?? null,
            'porcentaje_pf' => $pf,
        ]);

        return redirect()->route('pf.ficha.index', ['location_id' => $data['location_id'] ?? null])->with('success', 'Registro PF guardado');
    }
}
