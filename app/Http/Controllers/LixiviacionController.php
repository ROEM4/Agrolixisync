<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Reading;
use App\Models\PFRecord;
use App\Models\Analysis;
use Carbon\Carbon;

class LixiviacionController extends Controller
{
    public function index(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', '30d');
        $locations = Location::with('lote')->orderBy('name')->get();

        $query = Analysis::with('location.lote')->orderByDesc('analyzed_at');

        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        // Aplicar filtros de tiempo
        switch ($filter) {
            case '24h':
                $query->where('analyzed_at', '>=', Carbon::now()->subHours(24));
                break;
            case '7d':
                $query->where('analyzed_at', '>=', Carbon::now()->subDays(7));
                break;
            case '14d':
                $query->where('analyzed_at', '>=', Carbon::now()->subDays(14));
                break;
            case '30d':
                $query->where('analyzed_at', '>=', Carbon::now()->subDays(30));
                break;
            case 'all':
                // No filter applied
                break;
        }

        $analysisRecords = $query->paginate(20)->withQueryString();

        // Obtener datos actuales para los cards (del último análisis)
        $latestAnalysis = null;
        if ($location_id) {
            $latestAnalysis = Analysis::where('location_id', $location_id)->orderByDesc('analyzed_at')->first();
        }

        // Obtener datos para la ficha (pre-llenado)
        $latestReading = null;
        if ($location_id) {
            // Buscamos la última lectura de conductividad para esta ubicación a través de sus sensores
            $latestReading = Reading::whereHas('sensor', function($q) use ($location_id) {
                    $q->where('location_id', $location_id)
                      ->where('depth', 20);
                })
                ->orderByDesc('recorded_at')
                ->first();
            
            if (!$latestReading) {
                $latestReading = Reading::whereHas('sensor', function($q) use ($location_id) {
                        $q->where('location_id', $location_id);
                    })
                    ->orderByDesc('recorded_at')
                    ->first();
            }
        }

        // Registros de la ficha PF
        $pfRecords = PFRecord::query()->orderByDesc('recorded_at');
        if ($location_id) {
            $pfRecords->where('location_id', $location_id);
        }
        $records = $pfRecords->limit(10)->get();

        $selectedLocation = $location_id ? Location::find($location_id) : null;

        return view('dashboard.lixiviacion', compact(
            'locations', 
            'analysisRecords', 
            'location_id', 
            'filter', 
            'latestAnalysis',
            'latestReading',
            'records',
            'selectedLocation'
        ));
    }

    public function export(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', '24h');
        
        $query = Analysis::with('location.lote')->orderByDesc('analyzed_at');
        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        switch ($filter) {
            case '24h': $query->where('analyzed_at', '>=', Carbon::now()->subHours(24)); break;
            case '7d': $query->where('analyzed_at', '>=', Carbon::now()->subDays(7)); break;
            case '14d': $query->where('analyzed_at', '>=', Carbon::now()->subDays(14)); break;
            case '30d': $query->where('analyzed_at', '>=', Carbon::now()->subDays(30)); break;
        }

        $data = $query->get();

        $filename = 'indice_lixiviacion_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Fecha', 'Lote', 'Ubicación', 'CE Superficial', 'CE Profunda', 'IL Ratio', 'Estado']);

            foreach ($data as $r) {
                fputcsv($file, [
                    $r->id,
                    $r->analyzed_at->format('Y-m-d H:i:s'),
                    $r->location->lote->name ?? '',
                    $r->location->name ?? '',
                    $r->conductivity_superficial,
                    $r->conductivity_profundo,
                    $r->ilx,
                    $r->ilx_estado
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
