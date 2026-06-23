<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ubicacion;
use App\Models\Alerta;
use App\Models\TiempoDeteccion;
use Carbon\Carbon;

class DetectionTimeController extends Controller
{
    /**
     * Mostrar el análisis de tiempo de detección
     */
    public function index(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', 'all');
        $mode = $request->query('mode', 'manual');

        // 🌳 CARGAR PLANTAS POR GRUPO
        $plantasGC = \App\Models\Planta::where('grupo_experimental', 'control')
            ->orderBy('numero_planta')->get();
        $plantasGE = \App\Models\Planta::where('grupo_experimental', 'experimental')
            ->orderBy('numero_planta')->get();

        $ubicacionSeleccionada = $location_id ? Ubicacion::find($location_id) : null;

        // 🛡️ CORRECCIÓN DE LÓGICA INVERSA
        if ($mode === 'iot' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control') {
            $firstGE = $plantasGE->first();
            $location_id = $firstGE && $firstGE->ubicaciones->isNotEmpty() ? $firstGE->ubicaciones->first()->id : null;
            $ubicacionSeleccionada = $location_id ? Ubicacion::find($location_id) : null;
        }

        if ($mode === 'manual' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'experimental') {
            $firstGC = $plantasGC->first();
            $location_id = $firstGC && $firstGC->ubicaciones->isNotEmpty() ? $firstGC->ubicaciones->first()->id : null;
            $ubicacionSeleccionada = $location_id ? Ubicacion::find($location_id) : null;
        }

        // ✅ SELECCIONAR AUTOMÁTICAMENTE LA PRIMERA PLANTA SI NO HAY NINGUNA
        if (!$ubicacionSeleccionada) {
            if ($mode === 'iot') {
                $firstGE = $plantasGE->first();
                if ($firstGE && $firstGE->ubicaciones->isNotEmpty()) {
                    $location_id = $firstGE->ubicaciones->first()->id;
                    $ubicacionSeleccionada = Ubicacion::find($location_id);
                }
            } else {
                $firstGC = $plantasGC->first();
                if ($firstGC && $firstGC->ubicaciones->isNotEmpty()) {
                    $location_id = $firstGC->ubicaciones->first()->id;
                    $ubicacionSeleccionada = Ubicacion::find($location_id);
                }
            }
        }

        $isCtrl = $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control';

        // Sincronizar alertas (solo si hay ubicación seleccionada)
        if ($ubicacionSeleccionada) {
            $allAlertas = Alerta::with('ubicacion.planta')
                ->where('ubicacion_id', $ubicacionSeleccionada->id)
                ->whereNotNull('tiempo_alerta')
                ->whereNotNull('tiempo_riesgo')
                ->get();
            $this->saveDetectionTimeRecords($allAlertas);
        }

        // ✅ CONSULTAR REGISTROS - SIEMPRE FILTRAR POR UBICACIÓN
        $recordsQuery = TiempoDeteccion::with('ubicacion.planta')
            ->when($location_id, fn($q) => $q->where('ubicacion_id', $location_id))
            ->orderByDesc('fecha');

        switch ($filter) {
            case '24h': $recordsQuery->where('fecha', '>=', Carbon::today()); break;
            case '7d':  $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(30)); break;
        }

        $totalAlertasCount = (clone $recordsQuery)->sum('cantidad_eventos');
        $detectionRecords = $recordsQuery->paginate(15)->withQueryString();

        // Gráficos - SIEMPRE FILTRAR POR UBICACIÓN
        $chartQuery = TiempoDeteccion::query()
            ->when($location_id, fn($q) => $q->where('ubicacion_id', $location_id))
            ->orderBy('fecha');
        
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

        return view('dashboard.detection_time', [
            'plantasGC' => $plantasGC,
            'plantasGE' => $plantasGE,
            'ubicaciones' => Ubicacion::with('planta')->orderBy('nombre')->get(),
            'location_id' => $location_id,
            'ubicacion' => $ubicacionSeleccionada,
            'ubicacionSeleccionada' => $ubicacionSeleccionada,
            'isCtrl' => $isCtrl,
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
        ]);
    }

    /**
     * Calcular el tiempo promedio de detección agrupado por día
     */
    private function calculateDetectionTimeByDay($alertas)
    {
        $groupedByDate = [];
        foreach ($alertas as $alerta) {
            $date = $alerta->tiempo_alerta->format('Y-m-d');
            if (!isset($groupedByDate[$date])) {
                $groupedByDate[$date] = [
                    'alertas' => [],
                    'suma_tiempos' => 0,
                    'cantidad' => 0,
                ];
            }
            $diferencia = abs($alerta->tiempo_riesgo->diffInSeconds($alerta->tiempo_alerta));
            $groupedByDate[$date]['alertas'][] = [
                'id' => $alerta->id,
                'ubicacion_nombre' => $alerta->ubicacion->nombre ?? 'N/A',
                'planta_nombre' => $alerta->ubicacion->planta->nombre ?? 'N/A',
                'tiempo_alerta' => $alerta->tiempo_alerta,
                'tiempo_riesgo' => $alerta->tiempo_riesgo,
                'diferencia' => $diferencia,
            ];
            $groupedByDate[$date]['suma_tiempos'] += $diferencia;
            $groupedByDate[$date]['cantidad'] += 1;
        }

        $result = [];
        $dayNumber = 1;
        foreach ($groupedByDate as $fecha => $data) {
            $tiempoPromedio = $data['cantidad'] > 0
                ? round($data['suma_tiempos'] / $data['cantidad'], 2)
                : 0;
            $primerRegistro = $data['alertas'][0];
            $ultimoRegistro = end($data['alertas']);
            $result[] = [
                'numero' => $dayNumber,
                'fecha' => $fecha,
                'tiempo_inicial' => $primerRegistro['tiempo_alerta']->format('Y-m-d H:i:s'),
                'tiempo_final' => $ultimoRegistro['tiempo_riesgo']->format('Y-m-d H:i:s'),
                'subparcela' => $primerRegistro['ubicacion_nombre'],
                'planta' => $primerRegistro['planta_nombre'],
                'tiempo_promedio' => $tiempoPromedio,
                'cantidad_eventos' => $data['cantidad'],
                'detalles' => $data['alertas'],
            ];
            $dayNumber++;
        }
        return $result;
    }

    /**
     * Exportar datos de tiempo de detección a CSV
     */
    public function export(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', 'all');

        $recordsQuery = TiempoDeteccion::with('ubicacion.planta')
            ->where('ubicacion_id', $location_id)
            ->orderByDesc('fecha');

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
                'Número (Día)',
                'Fecha',
                'Tiempo Inicial (Ti)',
                'Tiempo Final (Tf)',
                'Subparcela',
                'Planta',
                'Tiempo Promedio (segundos)',
                'Cantidad de Eventos',
            ], ';');
            foreach ($detectionData as $day) {
                fputcsv($file, [
                    $day->id,
                    $day->fecha->format('Y-m-d'),
                    $day->fecha->copy()->setHour(8)->format('Y-m-d H:i:s'),
                    $day->fecha->copy()->setHour(8)->addSeconds((int)$day->tiempo_promedio_segundos)->format('Y-m-d H:i:s'),
                    $day->subparcela ?? 'N/A',
                    $day->ubicacion->planta->nombre ?? 'N/A',
                    number_format((float)$day->tiempo_promedio_segundos, 2, '.', ''),
                    $day->cantidad_eventos,
                ], ';');
            }
            fclose($file);
        }, 200, $headers);
    }

    /**
     * Guardar registros de TPD en la tabla tiempos_deteccion
     */
    private function saveDetectionTimeRecords($alertas)
    {
        if ($alertas->isEmpty()) {
            return;
        }

        $groupedByDateAndLocation = [];
        foreach ($alertas as $alerta) {
            $date = $alerta->tiempo_alerta->format('Y-m-d');
            $ubicacionId = $alerta->ubicacion_id;
            $key = "$date|$ubicacionId";
            if (!isset($groupedByDateAndLocation[$key])) {
                $groupedByDateAndLocation[$key] = [
                    'fecha' => $date,
                    'ubicacion_id' => $ubicacionId,
                    'planta_id' => $alerta->planta_id,
                    'ubicacion' => $alerta->ubicacion,
                    'alertas' => [],
                    'subparcela' => $alerta->subparcela,
                ];
            } else {
                if (empty($groupedByDateAndLocation[$key]['subparcela']) && !empty($alerta->subparcela)) {
                    $groupedByDateAndLocation[$key]['subparcela'] = $alerta->subparcela;
                }
            }
            $diferencia = abs($alerta->tiempo_riesgo->diffInSeconds($alerta->tiempo_alerta));
            $groupedByDateAndLocation[$key]['alertas'][] = $diferencia;
        }

        foreach ($groupedByDateAndLocation as $record) {
            $fecha = Carbon::parse($record['fecha']);
            $tiempoPromedio = count($record['alertas']) > 0
                ? round(array_sum($record['alertas']) / count($record['alertas']), 2)
                : 0;
            $tipoEntrada = $this->determinaTipoEntrada($record['ubicacion']);

            TiempoDeteccion::updateOrCreate(
                [
                    'fecha' => $fecha,
                    'ubicacion_id' => $record['ubicacion_id'],
                ],
                [
                    'planta_id' => $record['planta_id'],
                    'tiempo_promedio_segundos' => round($tiempoPromedio, 2),
                    'cantidad_eventos' => count($record['alertas']),
                    'suma_tiempos_segundos' => (int) array_sum($record['alertas']),
                    'tipo_entrada' => $tipoEntrada,
                    'subparcela' => $record['subparcela'],
                ]
            );
        }
    }

    /**
     * Determina si la entrada es manual o automática
     */
    private function determinaTipoEntrada($ubicacion)
    {
        if (!$ubicacion) {
            return 'automatico';
        }
        $ubicacionNombre = strtolower($ubicacion->nombre);

        if ($ubicacion->grupo_experimental === 'control' ||
            strpos($ubicacionNombre, 'control') !== false ||
            strpos($ubicacionNombre, 'manual') !== false) {
            return 'manual';
        }

        if (strpos($ubicacionNombre, 'esp32') !== false ||
            strpos($ubicacionNombre, 'automático') !== false ||
            strpos($ubicacionNombre, 'iot') !== false ||
            strpos($ubicacionNombre, 'experimental') !== false) {
            return 'automatico';
        }

        return 'automatico';
    }

    /**
     * Almacena un registro de tiempo ingresado manualmente
     */
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

        // ✅ Crear o actualizar registro en tiempos_deteccion
        TiempoDeteccion::updateOrCreate(
            [
                'fecha' => $fecha,
                'ubicacion_id' => $ubicacion->id,
            ],
            [
                'planta_id' => $ubicacion->planta_id,
                'tiempo_promedio_segundos' => $tarSeconds,
                'cantidad_eventos' => $cantidadEventos,
                'suma_tiempos_segundos' => $tarSeconds * $cantidadEventos,
                'tipo_entrada' => 'manual',
                'subparcela' => 'Manual', // Valor por defecto
            ]
        );

        return redirect()->route('detection_time', [
            'location_id' => $ubicacion->id,
            'mode' => 'manual',
            'filter' => 'all'
        ])->with('success', '✅ Registro manual guardado correctamente. TAR: ' . $tarSeconds . 's, Eventos: ' . $cantidadEventos);
    }

    /**
     * Actualiza un registro de tiempo de detección manual
     */
    public function updateManual(Request $request, $recordId)
    {
        $request->validate([
            'subparcela' => ['required', 'string', 'regex:/^[Ss]\d+$/'],
            'fecha' => 'required|date',
            'hora_alerta' => 'required',
            'hora_evento' => 'required',
        ]);

        $record = TiempoDeteccion::findOrFail($recordId);

        $fecha = $request->fecha;
        $ti = Carbon::parse($fecha . ' ' . $request->hora_alerta);
        $tf = Carbon::parse($fecha . ' ' . $request->hora_evento);
        $tarSeconds = $tf->diffInSeconds($ti);

        $record->update([
            'fecha' => $fecha,
            'subparcela' => strtoupper($request->subparcela),
            'tiempo_promedio_segundos' => $tarSeconds,
            'suma_tiempos_segundos' => $tarSeconds,
            'cantidad_eventos' => 1,
        ]);

        return redirect()->route('detection_time', ['location_id' => $record->ubicacion_id])
            ->with('success', '✅ Registro actualizado correctamente.');
    }
}