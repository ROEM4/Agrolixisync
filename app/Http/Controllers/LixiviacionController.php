<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Reading;
use App\Models\PFRecord;
use App\Models\Analysis;
use App\Models\Alert;
use App\Models\Observacion;
use App\Models\DetectionTimeRecord;
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

        // Preparar series para gráficos a partir de Analysis (agrupado por fecha)
        $chartQuery = \App\Models\Analysis::query()->orderBy('analyzed_at');
        if ($location_id) {
            $chartQuery->where('location_id', $location_id);
        }
        switch ($filter) {
            case '24h': $chartQuery->where('analyzed_at', '>=', Carbon::now()->subHours(24)); break;
            case '7d':  $chartQuery->where('analyzed_at', '>=', Carbon::now()->subDays(7)); break;
            case '14d': $chartQuery->where('analyzed_at', '>=', Carbon::now()->subDays(14)); break;
            case '30d': $chartQuery->where('analyzed_at', '>=', Carbon::now()->subDays(30)); break;
            case 'all': break;
        }

        $chartRows = $chartQuery->get()->groupBy(function($r){ return $r->analyzed_at->format('Y-m-d'); });
        $dates = [];
        $avgCeSup = [];
        $avgIlx = [];
        $counts = [];
        foreach ($chartRows as $date => $rows) {
            $dates[] = Carbon::parse($date)->format('d/m/Y');
            $avgCeSup[] = round($rows->avg('conductivity_superficial'), 3);
            $avgIlx[] = round($rows->avg('ilx'), 4);
            $counts[] = $rows->count();
        }

        return view('dashboard.lixiviacion', compact(
            'locations', 
            'analysisRecords', 
            'location_id', 
            'filter', 
            'latestAnalysis',
            'latestReading',
            'records',
            'selectedLocation'
        ))->with([
            'datesJson' => json_encode($dates),
            'ceSupJson' => json_encode($avgCeSup),
            'ilxJson' => json_encode($avgIlx),
            'countsJson' => json_encode($counts),
        ]);
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

    public function storeManual(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'conductivity_superficial' => 'required|numeric|min:0',
            'conductivity_profundo' => 'required|numeric|min:0',
        ]);

        $location = Location::findOrFail($request->location_id);
        $ce_s = (float) $request->conductivity_superficial;
        $ce_p = (float) $request->conductivity_profundo;
        
        // Calcular ILx
        $ilx = $ce_s > 0 ? round($ce_p / $ce_s, 4) : 0.0;
        $delta = round($ce_s - $ce_p, 4);

        // Clasificar ILx
        $ilx_estado = 'EQUILIBRIO';
        $detected = false;
        $risk = 'BAJO';

        if ($ilx > 1.20) {
            $ilx_estado = 'LIXIVIACIÓN ALTA';
            $detected = true;
            $risk = 'ALTO';
        } elseif ($ilx > 1.05) {
            $ilx_estado = 'LIXIVIACIÓN';
            $detected = true;
            $risk = 'MEDIO';
        } elseif ($ilx >= 0.90) {
            $ilx_estado = 'EQUILIBRIO';
            $detected = false;
            $risk = 'BAJO';
        } elseif ($ilx >= 0.70) {
            $ilx_estado = 'RETENCIÓN';
            $detected = false;
            $risk = 'BAJO';
        } else {
            $ilx_estado = 'ACUMULACIÓN';
            $detected = true;
            $risk = 'MEDIO';
        }

        $now = now();

        $analysis = Analysis::create([
            'lote_id'                  => $location->lote_id,
            'location_id'              => $location->id,
            'experimental_group'       => $location->experimental_group,
            'conductivity_superficial' => $ce_s,
            'conductivity_profundo'    => $ce_p,
            'delta_conductivity'       => $delta,
            'ilx'                      => $ilx,
            'ilx_estado'               => $ilx_estado,
            'lixiviation_detected'     => $detected,
            'risk_level'               => $risk,
            'threshold_used'           => 1.20,
            'analyzed_at'              => $now,
            'event_detected_at'        => $now,
            'alert_generated_at'       => $detected ? $now : null,
            'event_type'               => 'LIXIVIATION',
        ]);

        if ($detected || $risk === 'MEDIO') {
            $alert = Alert::create([
                'lote_id'       => $location->lote_id,
                'location_id'   => $location->id,
                'analysis_id'   => $analysis->id,
                'type'          => 'lixiviacion',
                'description'   => sprintf('Manual ILx=%.4f (%s) | ΔCE=%.4f dS/m', $ilx, $ilx_estado, $delta),
                'severity'      => $risk,
                'level'         => $risk,
                'status'        => 'RESOLVED',
                'is_resolved'   => true,
                'resolved_at'   => $now,
                'ce_actual'     => $ce_s,
                'ce_anterior'   => $ce_s,
                'delta_ce'      => 0.0,
                'tiempo_riesgo' => $now,
                'tiempo_alerta' => $now,
            ]);

            // Guardar/actualizar registro de tiempo de detección (TPD)
            try {
                $diffSec = 0;
                if ($alert->tiempo_alerta && $alert->tiempo_riesgo) {
                    $diffSec = $alert->tiempo_riesgo->diffInSeconds($alert->tiempo_alerta);
                }
                DetectionTimeRecord::updateOrCreate(
                    ['fecha' => $alert->tiempo_alerta->format('Y-m-d'), 'location_id' => $location->id],
                    [
                        'lote_id' => $location->lote_id,
                        'tiempo_promedio_segundos' => $diffSec,
                        'cantidad_eventos' => 1,
                        'suma_tiempos_segundos' => $diffSec,
                        'tipo_entrada' => 'manual',
                        'subparcela' => null,
                    ]
                );
            } catch (\Exception $e) {
                // No bloquear el flujo si falla el guardado del TPD
            }

            $observacion = new Observacion([
                'location_id'        => $location->id,
                'experimental_group' => $location->experimental_group,
                'alert_id'           => $alert->id,
                'ce_real'            => $ce_s,
                'diagnostico'        => 'LIXIVIACION',
                'resultado'          => 'VP',
            ]);
            $observacion->save();
        }

        return redirect()->route('lixiviacion', ['location_id' => $location->id])
            ->with('success', 'Registro manual de lixiviación guardado correctamente.');
    }
}
