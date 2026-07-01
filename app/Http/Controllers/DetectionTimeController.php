<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ubicacion;
use App\Models\Alerta;
use App\Models\TiempoDeteccion;
use App\Models\Planta;
use App\Models\ConsolidacionDiaria;
use Carbon\Carbon;

class DetectionTimeController extends Controller
{
    public function index(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', 'all');
        $mode = $request->query('mode', 'manual');

        $isAllPlants = ($location_id === 'all');

        $plantasGC = Planta::where('grupo_experimental', 'control')
            ->with('ubicaciones')->orderBy('numero_planta')->get();
        $plantasGE = Planta::where('grupo_experimental', 'experimental')
            ->with('ubicaciones')->orderBy('numero_planta')->get();

        if ($isAllPlants) {
            $ubicacionSeleccionada = null;
            $isCtrl = ($mode === 'manual');
        } else {
            $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;

            if ($mode === 'iot' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control') {
                $firstGE = $plantasGE->first();
                $location_id = $firstGE && $firstGE->ubicaciones->isNotEmpty() ? $firstGE->ubicaciones->first()->id : null;
                $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;
            }

            if ($mode === 'manual' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'experimental') {
                $firstGC = $plantasGC->first();
                $location_id = $firstGC && $firstGC->ubicaciones->isNotEmpty() ? $firstGC->ubicaciones->first()->id : null;
                $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;
            }

            if (!$ubicacionSeleccionada) {
                if ($mode === 'iot') {
                    $firstGE = $plantasGE->first();
                    if ($firstGE && $firstGE->ubicaciones->isNotEmpty()) {
                        $location_id = $firstGE->ubicaciones->first()->id;
                        $ubicacionSeleccionada = Ubicacion::with('planta')->find($location_id);
                    }
                } else {
                    $firstGC = $plantasGC->first();
                    if ($firstGC && $firstGC->ubicaciones->isNotEmpty()) {
                        $location_id = $firstGC->ubicaciones->first()->id;
                        $ubicacionSeleccionada = Ubicacion::with('planta')->find($location_id);
                    }
                }
            }

            $isCtrl = $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control';
        }

        // Consultar registros de tiempo de detección
        $recordsQuery = TiempoDeteccion::with('ubicacion.planta');

        if ($isAllPlants) {
            if ($mode === 'manual') {
                $ubicacionIds = $plantasGC->pluck('ubicaciones')->flatten()->pluck('id');
                $recordsQuery->whereIn('ubicacion_id', $ubicacionIds);
            } else {
                $ubicacionIds = $plantasGE->pluck('ubicaciones')->flatten()->pluck('id');
                $recordsQuery->whereIn('ubicacion_id', $ubicacionIds);
            }
        } elseif ($location_id) {
            $recordsQuery->where('ubicacion_id', $location_id);
        }

        $recordsQuery->orderByDesc('fecha');

        switch ($filter) {
            case '24h': $recordsQuery->where('fecha', '>=', Carbon::today()); break;
            case '7d':  $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(30)); break;
        }

        $totalAlertasCount = (clone $recordsQuery)->sum('cantidad_eventos');
        $detectionRecords = $recordsQuery->paginate(15)->withQueryString();

        // 🆕 Cargar alertas evaluadas para el modal de detalle (SOLO IoT)
        $alertasPorDia = [];
        
        if ($mode === 'iot') {
            foreach ($detectionRecords as $record) {
                $fecha = $record->fecha->format('Y-m-d');
                $ubicacionId = $record->ubicacion_id;
                
                $alertas = Alerta::where('ubicacion_id', $ubicacionId)
                    ->whereDate('tiempo_alerta', $fecha)
                    ->with('evaluacion')
                    ->orderBy('tiempo_alerta', 'asc')
                    ->get();
                
                $alertasFormateadas = $alertas->map(function($alerta) {
                    $esVP = $alerta->evaluacion && $alerta->evaluacion->etiqueta === 'VP';

                    // ✅ CORRECCIÓN: calcular diferencia igual que alertas.blade.php
                    // Prioridad: 1) tar del Job (VP real), 2) fecha_resolucion - tiempo_alerta, 3) null
                    $diferencia = null;
                    $tieneTiempo = false;

                    if ($alerta->tar && $alerta->tar > 0 && $alerta->tar != 300) {
                        // TAR calculado por CalcularTiempoDeteccion Job (solo VP)
                        $diferencia  = (int) $alerta->tar;
                        $tieneTiempo = true;
                    } elseif ($alerta->fecha_resolucion && $alerta->tiempo_alerta
                              && $alerta->fecha_resolucion->gt($alerta->tiempo_alerta)) {
                        // Fallback: duración real desde detección hasta resolución
                        $diferencia  = $alerta->fecha_resolucion->diffInSeconds($alerta->tiempo_alerta);
                        $tieneTiempo = true;
                    }

                    return [
                        'id'                    => $alerta->id,
                        'tiempo_alerta'         => $alerta->tiempo_alerta ? $alerta->tiempo_alerta->format('H:i:s') : 'N/A',
                        'tiempo_riesgo'         => $alerta->fecha_resolucion ? $alerta->fecha_resolucion->format('H:i:s') : 'N/A',
                        'etiqueta'              => $alerta->evaluacion ? $alerta->evaluacion->etiqueta : 'Sin evaluar',
                        'es_vp'                 => $esVP,
                        'tiene_tiempo'          => $tieneTiempo,
                        'diferencia_segundos'   => $diferencia,
                        'diferencia_formateada' => $diferencia !== null ? $diferencia . 's (~' . round($diferencia / 60, 1) . ' min)' : 'N/A',
                    ];
                });
                
                $alertasPorDia[$record->id] = $alertasFormateadas;
            }
        }

        // Agregar Ti y Tf para cada registro
        $detectionRecords->getCollection()->transform(function($record) use ($mode) {
            if ($mode === 'iot') {
                $primeraAlerta = Alerta::whereDate('tiempo_alerta', $record->fecha)
                    ->where('ubicacion_id', $record->ubicacion_id)
                    ->orderBy('tiempo_alerta', 'asc')
                    ->first();
                
                $ultimaAlerta = Alerta::whereDate('tiempo_alerta', $record->fecha)
                    ->where('ubicacion_id', $record->ubicacion_id)
                    ->orderBy('tiempo_alerta', 'desc')
                    ->first();
                
                $record->tiempo_inicial = $primeraAlerta ? $primeraAlerta->tiempo_alerta : $record->fecha->copy()->setHour(8);
                $record->tiempo_final = $ultimaAlerta ? $ultimaAlerta->tiempo_riesgo : $record->fecha->copy()->setHour(8)->addSeconds($record->tiempo_promedio_segundos);
            } else {
                $record->tiempo_inicial = $record->fecha->copy()->setHour(8);
                $record->tiempo_final = $record->fecha->copy()->setHour(8)->addSeconds($record->tiempo_promedio_segundos);
            }
            
            return $record;
        });

        // Gráficos
        $chartQuery = TiempoDeteccion::query();

        if ($isAllPlants) {
            if ($mode === 'manual') {
                $ubicacionIds = $plantasGC->pluck('ubicaciones')->flatten()->pluck('id');
                $chartQuery->whereIn('ubicacion_id', $ubicacionIds);
            } else {
                $ubicacionIds = $plantasGE->pluck('ubicaciones')->flatten()->pluck('id');
                $chartQuery->whereIn('ubicacion_id', $ubicacionIds);
            }
        } elseif ($location_id) {
            $chartQuery->where('ubicacion_id', $location_id);
        }

        $chartQuery->orderBy('fecha');

        switch ($filter) {
            case '24h': $chartQuery->where('fecha', '>=', Carbon::today()); break;
            case '7d':  $chartQuery->where('fecha', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $chartQuery->where('fecha', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $chartQuery->where('fecha', '>=', Carbon::today()->subDays(30)); break;
        }

        $chartRows = $chartQuery->get();

        $dates = $chartRows->map(fn($r) => $r->fecha->format('d/m/Y'))->toArray();
        $avgTimes = $chartRows->map(fn($r) => (float) $r->tiempo_promedio_segundos)->toArray();
        $events = $chartRows->map(fn($r) => (int) $r->cantidad_eventos)->toArray();
        $manualCount = $chartRows->where('tipo_entrada', 'manual')->count();
        $automaticCount = $chartRows->where('tipo_entrada', 'automatico')->count();

        // Datos de precisión
        $precisionData = [];

        if ($isAllPlants) {
            $plantaIds = $plantasGE->pluck('id');
            $consolidaciones = ConsolidacionDiaria::whereIn('planta_id', $plantaIds)->get();
        } elseif ($ubicacionSeleccionada) {
            $consolidaciones = ConsolidacionDiaria::where('planta_id', $ubicacionSeleccionada->planta_id)->get();
        } else {
            $consolidaciones = collect();
        }

        foreach ($consolidaciones as $cons) {
            $dateKey = Carbon::parse($cons->fecha_consolidacion)->format('Y-m-d');
            
            if (!isset($precisionData[$dateKey])) {
                $precisionData[$dateKey] = [
                    'vp' => 0, 'fp' => 0, 'fn' => 0,
                    'n_precision' => 0, 'pds_percentage' => 0,
                ];
            }
            
            $precisionData[$dateKey]['vp'] += $cons->vp ?? 0;
            $precisionData[$dateKey]['fp'] += $cons->fp ?? 0;
            $precisionData[$dateKey]['fn'] += $cons->fn ?? 0;
            $precisionData[$dateKey]['n_precision'] = $precisionData[$dateKey]['vp'] + $precisionData[$dateKey]['fp'] + $precisionData[$dateKey]['fn'];
            
            $nTotal = $precisionData[$dateKey]['n_precision'];
            $precisionData[$dateKey]['pds_percentage'] = $nTotal > 0 
                ? round(($precisionData[$dateKey]['vp'] / $nTotal) * 100, 1) 
                : 0;
        }

        return view('dashboard.detection_time', [
            'plantasGC' => $plantasGC,
            'plantasGE' => $plantasGE,
            'ubicaciones' => Ubicacion::with('planta')->orderBy('nombre')->get(),
            'location_id' => $location_id,
            'ubicacion' => $ubicacionSeleccionada,
            'ubicacionSeleccionada' => $ubicacionSeleccionada,
            'isCtrl' => $isCtrl,
            'isAllPlants' => $isAllPlants,
            'mode' => $mode,
            'detectionRecords' => $detectionRecords,
            'filter' => $filter,
            'total_alerts' => $totalAlertasCount,
            'unique_days' => $detectionRecords->total(),
            'datesJson' => json_encode($dates),
            'avgTimesJson' => json_encode($avgTimes),
            'eventsJson' => json_encode($events),
            'manualCount' => $manualCount,
            'automaticCount' => $automaticCount,
            'precisionData' => $precisionData,
            'alertasPorDia' => $alertasPorDia,
        ]);
    }

    public function export(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', 'all');
        $mode = $request->query('mode', 'manual');
        $isAllPlants = ($location_id === 'all');

        $plantasGC = Planta::where('grupo_experimental', 'control')->with('ubicaciones')->orderBy('numero_planta')->get();
        $plantasGE = Planta::where('grupo_experimental', 'experimental')->with('ubicaciones')->orderBy('numero_planta')->get();

        $recordsQuery = TiempoDeteccion::with('ubicacion.planta');

        if ($isAllPlants) {
            if ($mode === 'manual') {
                $ubicacionIds = $plantasGC->pluck('ubicaciones')->flatten()->pluck('id');
                $recordsQuery->whereIn('ubicacion_id', $ubicacionIds);
            } else {
                $ubicacionIds = $plantasGE->pluck('ubicaciones')->flatten()->pluck('id');
                $recordsQuery->whereIn('ubicacion_id', $ubicacionIds);
            }
        } elseif ($location_id) {
            $recordsQuery->where('ubicacion_id', $location_id);
        }

        $recordsQuery->orderByDesc('fecha');

        switch ($filter) {
            case '24h': $recordsQuery->where('fecha', '>=', Carbon::today()); break;
            case '7d':  $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(30)); break;
        }

        $detectionData = $recordsQuery->get();
        $filename = 'tiempo_deteccion_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($detectionData) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, [
                'Número (Día)', 'Fecha', 'Tiempo Inicial (Ti)', 'Tiempo Final (Tf)',
                'Subparcela', 'Planta', 'Tiempo Promedio (segundos)', 'Cantidad de Eventos',
            ], ';');
            foreach ($detectionData as $day) {
                fputcsv($file, [
                    $day->id, $day->fecha->format('Y-m-d'),
                    $day->fecha->copy()->setHour(8)->format('Y-m-d H:i:s'),
                    $day->fecha->copy()->setHour(8)->addSeconds((int)$day->tiempo_promedio_segundos)->format('Y-m-d H:i:s'),
                    $day->subparcela ?? 'N/A', $day->ubicacion->planta->nombre ?? 'N/A',
                    number_format((float)$day->tiempo_promedio_segundos, 2, '.', ''),
                    $day->cantidad_eventos,
                ], ';');
            }
            fclose($file);
        }, 200, $headers);
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'ubicacion_id' => 'required|exists:ubicaciones,id',
            'fecha' => 'required|date',
            'hora_alerta' => 'required',
            'hora_evento' => 'required',
            'cantidad_eventos' => 'required|integer|min:1',
        ]);

        $ubicacion = Ubicacion::findOrFail($request->ubicacion_id);

        if ($ubicacion->grupo_experimental !== 'control') {
            return back()->withErrors(['ubicacion_id' => 'La ubicación seleccionada debe ser del grupo control.'])->withInput();
        }

        $fecha = $request->fecha;
        $ti = Carbon::parse($fecha . ' ' . $request->hora_alerta);
        $tf = Carbon::parse($fecha . ' ' . $request->hora_evento);
        $tarSeconds = abs($tf->diffInSeconds($ti));
        $cantidadEventos = (int) $request->cantidad_eventos;

        TiempoDeteccion::updateOrCreate(
            ['fecha' => $fecha, 'ubicacion_id' => $ubicacion->id],
            [
                'planta_id' => $ubicacion->planta_id,
                'tiempo_promedio_segundos' => $tarSeconds,
                'cantidad_eventos' => $cantidadEventos,
                'suma_tiempos_segundos' => $tarSeconds * $cantidadEventos,
                'tipo_entrada' => 'manual',
                'subparcela' => 'Manual',
            ]
        );

        return redirect()->route('detection_time', [
            'location_id' => $ubicacion->id, 'mode' => 'manual', 'filter' => 'all'
        ])->with('success', '✅ Registro manual guardado correctamente. TAR: ' . $tarSeconds . 's, Eventos: ' . $cantidadEventos);
    }
}