<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Alert;
use App\Models\DetectionTimeRecord;
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
        $mode = $request->query('mode', 'manual'); // 🆕 'iot' o 'manual'
        
        // ═══════════════════════════════════════════════════════════════
        // 🌳 CARGAR PLANTAS POR GRUPO
        // ═══════════════════════════════════════════════════════════════
        $lotesGC = \App\Models\Lote::where('experimental_group', 'control')
            ->orderBy('plant_number')->get();
        $lotesGE = \App\Models\Lote::where('experimental_group', 'experimental')
            ->orderBy('plant_number')->get();
        
        $selectedLocation = $location_id ? Location::find($location_id) : null;
        
        // 🛡️ CORRECCIÓN DE LÓGICA INVERSA (igual que lixiviación)
        if ($mode === 'iot' && $selectedLocation && $selectedLocation->experimental_group === 'control') {
            $firstGE = $lotesGE->first();
            $location_id = $firstGE && $firstGE->locations->isNotEmpty() ? $firstGE->locations->first()->id : null;
            $selectedLocation = $location_id ? Location::find($location_id) : null;
        }
        
        if ($mode === 'manual' && $selectedLocation && $selectedLocation->experimental_group === 'experimental') {
            $firstGC = $lotesGC->first();
            $location_id = $firstGC && $firstGC->locations->isNotEmpty() ? $firstGC->locations->first()->id : null;
            $selectedLocation = $location_id ? Location::find($location_id) : null;
        }
        
        if (!$selectedLocation) {
            if ($mode === 'iot') {
                $firstGE = $lotesGE->first();
                if ($firstGE && $firstGE->locations->isNotEmpty()) {
                    $location_id = $firstGE->locations->first()->id;
                    $selectedLocation = Location::find($location_id);
                }
            } else {
                $firstGC = $lotesGC->first();
                if ($firstGC && $firstGC->locations->isNotEmpty()) {
                    $location_id = $firstGC->locations->first()->id;
                    $selectedLocation = Location::find($location_id);
                }
            }
        }
        
        $isCtrl = $selectedLocation && $selectedLocation->experimental_group === 'control';
        
        // Sincronizar alertas (igual que tenías)
        $allAlerts = Alert::with('location.lote')
            ->whereNotNull('tiempo_alerta')
            ->whereNotNull('tiempo_riesgo')
            ->get();
        $this->saveDetectionTimeRecords($allAlerts);
        
        // Consultar registros
        $recordsQuery = DetectionTimeRecord::with('location.lote')
            ->where('location_id', $location_id)
            ->orderByDesc('fecha');
        
        switch ($filter) {
            case '24h': $recordsQuery->where('fecha', '>=', Carbon::today()); break;
            case '7d':  $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $recordsQuery->where('fecha', '>=', Carbon::today()->subDays(30)); break;
        }
        
        $totalAlertsCount = (clone $recordsQuery)->sum('cantidad_eventos');
        $detectionRecords = $recordsQuery->paginate(15)->withQueryString();
        
        // Fallback en memoria
        if ($detectionRecords->total() === 0 && $allAlerts->isNotEmpty()) {
            $filteredAlerts = $allAlerts;
            if ($location_id) {
                $filteredAlerts = $filteredAlerts->where('location_id', $location_id);
            }
            $since = null;
            switch ($filter) {
                case '24h': $since = Carbon::today(); break;
                case '7d':  $since = Carbon::today()->subDays(7); break;
                case '14d': $since = Carbon::today()->subDays(14); break;
                case '30d': $since = Carbon::today()->subDays(30); break;
            }
            if ($since) {
                $filteredAlerts = $filteredAlerts->filter(fn($a) => $a->tiempo_alerta && $a->tiempo_alerta->greaterThanOrEqualTo($since));
            }
            $computed = $this->calculateDetectionTimeByDay($filteredAlerts);
            if (!empty($computed)) {
                $page = (int) request()->query('page', 1);
                $perPage = 20;
                $offset = ($page - 1) * $perPage;
                $detectionRecords = new \Illuminate\Pagination\LengthAwarePaginator(
                    array_slice($computed, $offset, $perPage),
                    count($computed), $perPage, $page,
                    ['path' => route('detection_time'), 'query' => request()->query()]
                );
                $totalAlertsCount = array_sum(array_column($computed, 'cantidad_eventos'));
            }
        }
        
        // Gráficos
        $chartQuery = DetectionTimeRecord::query()->orderBy('fecha');
        if ($location_id) $chartQuery->where('location_id', $location_id);
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
            'lotesGC' => $lotesGC,
            'lotesGE' => $lotesGE,
            'locations' => Location::with('lote')->orderBy('name')->get(),
            'location_id' => $location_id,
            'location' => $selectedLocation,
            'selectedLocation' => $selectedLocation,
            'isCtrl' => $isCtrl,
            'mode' => $mode,
            'detectionRecords' => $detectionRecords,
            'filter' => $filter,
            'total_alerts' => $totalAlertsCount,
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
     * Fórmula: Para cada día, suma(Tf - Ti) / cantidad de registros del día
     * Donde: Ti = tiempo_alerta, Tf = tiempo_riesgo
     *
     * @param $alerts Colección de alertas
     * @return array Datos agrupados por día con tiempo promedio
     */
    private function calculateDetectionTimeByDay($alerts)
    {
        $groupedByDate = [];

        foreach ($alerts as $alert) {
            // Agrupar por fecha (sin hora)
            $date = $alert->tiempo_alerta->format('Y-m-d');

            if (!isset($groupedByDate[$date])) {
                $groupedByDate[$date] = [
                    'alerts' => [],
                    'suma_tiempos' => 0,
                    'cantidad' => 0,
                ];
            }

            // Calcular diferencia en segundos (Tf - Ti), siempre positivo
            $diferencia = abs($alert->tiempo_riesgo->diffInSeconds($alert->tiempo_alerta));

            $groupedByDate[$date]['alerts'][] = [
                'id' => $alert->id,
                'location_name' => $alert->location->name ?? 'N/A',
                'lote_name' => $alert->location->lote->name ?? 'N/A',
                'tiempo_alerta' => $alert->tiempo_alerta,
                'tiempo_riesgo' => $alert->tiempo_riesgo,
                'diferencia' => $diferencia,
            ];

            $groupedByDate[$date]['suma_tiempos'] += $diferencia;
            $groupedByDate[$date]['cantidad'] += 1;
        }

        // Transformar a formato para la vista
        $result = [];
        $dayNumber = 1;

        foreach ($groupedByDate as $fecha => $data) {
            $tiempoPromedio = $data['cantidad'] > 0 
                ? round($data['suma_tiempos'] / $data['cantidad'], 2)
                : 0;

            // Obtener el primer y último tiempo del día para la visualización
            $primerRegistro = $data['alerts'][0];
            $ultimoRegistro = end($data['alerts']);

            $result[] = [
                'numero' => $dayNumber,
                'fecha' => $fecha,
                'tiempo_inicial' => $primerRegistro['tiempo_alerta']->format('Y-m-d H:i:s'),
                'tiempo_final' => $ultimoRegistro['tiempo_riesgo']->format('Y-m-d H:i:s'),
                'subparcela' => $primerRegistro['location_name'],
                'lote' => $primerRegistro['lote_name'],
                'tiempo_promedio' => $tiempoPromedio,
                'cantidad_eventos' => $data['cantidad'],
                'detalles' => $data['alerts'],
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
        if (!$location_id || !in_array($location_id, [3, 4])) {
            $location_id = 3;
        }
        
        $recordsQuery = DetectionTimeRecord::with('location.lote')
            ->where('location_id', $location_id)
            ->orderByDesc('fecha');

        $detectionData = $recordsQuery->get();

        $filename = 'tiempo_deteccion_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($detectionData) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8 en Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados
            fputcsv($file, [
                'Número (Día)',
                'Fecha',
                'Tiempo Inicial (Ti)',
                'Tiempo Final (Tf)',
                'Subparcela',
                'Lote',
                'Tiempo Promedio (segundos)',
                'Cantidad de Eventos',
            ], ';');

            // Datos
            foreach ($detectionData as $day) {
                fputcsv($file, [
                    $day->numero,
                    $day->fecha->format('Y-m-d'),
                    $day->tiempo_inicial ? Carbon::parse($day->tiempo_inicial)->format('Y-m-d H:i:s') : '',
                    $day->tiempo_final ? Carbon::parse($day->tiempo_final)->format('Y-m-d H:i:s') : '',
                    $day->subparcela,
                    $day->location->lote->name ?? 'N/A',
                    number_format((float)$day->tiempo_promedio_segundos, 2, '.', ''),
                    $day->cantidad_eventos,
                ], ';');
            }

            fclose($file);
        }, 200, $headers);
    }

    /**
     * Guardar registros de TPD en la tabla detection_time_records
     * Agrupa por fecha y ubicación, y determina si es manual o automático
     *
     * @param $alerts Colección de alertas
     */
    private function saveDetectionTimeRecords($alerts)
    {
        if ($alerts->isEmpty()) {
            return;
        }

        // Agrupar por fecha y ubicación
        $groupedByDateAndLocation = [];

        foreach ($alerts as $alert) {
            $date = $alert->tiempo_alerta->format('Y-m-d');
            $locationId = $alert->location_id;
            $key = "$date|$locationId";

            if (!isset($groupedByDateAndLocation[$key])) {
                $groupedByDateAndLocation[$key] = [
                    'fecha' => $date,
                    'location_id' => $locationId,
                    'lote_id' => $alert->lote_id,
                    'location' => $alert->location,
                    'alerts' => [],
                    'subparcela' => $alert->subparcela,
                ];
            } else {
                if (empty($groupedByDateAndLocation[$key]['subparcela']) && !empty($alert->subparcela)) {
                    $groupedByDateAndLocation[$key]['subparcela'] = $alert->subparcela;
                }
            }

            $diferencia = abs($alert->tiempo_riesgo->diffInSeconds($alert->tiempo_alerta));
            $groupedByDateAndLocation[$key]['alerts'][] = $diferencia;
        }

        // Guardar registros
        foreach ($groupedByDateAndLocation as $record) {
            $fecha = Carbon::parse($record['fecha']);
            $tiempoPromedio = count($record['alerts']) > 0 
                ? round(array_sum($record['alerts']) / count($record['alerts']), 2)
                : 0;

            // Determinar si es manual o automático basándose en el nombre de la ubicación
            $tipoEntrada = $this->determinaTipoEntrada($record['location']);

            // Usar updateOrCreate para evitar duplicados
            DetectionTimeRecord::updateOrCreate(
                [
                    'fecha' => $fecha,
                    'location_id' => $record['location_id'],
                ],
                [
                    'lote_id' => $record['lote_id'],
                    'tiempo_promedio_segundos' => round($tiempoPromedio, 2),
                    'cantidad_eventos' => count($record['alerts']),
                    'suma_tiempos_segundos' => (int)array_sum($record['alerts']),
                    'tipo_entrada' => $tipoEntrada,
                    'subparcela' => $record['subparcela'],
                ]
            );
        }
    }

    /**
     * Determina si la entrada es manual o automática basándose en el nombre de la ubicación
     *
     * @param $location Objeto Location
     * @return string 'manual' o 'automatico'
     */
    private function determinaTipoEntrada($location)
    {
        if (!$location) {
            return 'automatico';
        }

        // Patrones conocidos para identificar tipo de entrada
        $locationName = strtolower($location->name);
        
        // Si contiene "control" o "manual" es manual, o si es experimental_group === 'control'
        if ($location->experimental_group === 'control' || 
            strpos($locationName, 'control') !== false || 
            strpos($locationName, 'manual') !== false ||
            strpos($locationName, 'parcela de control') !== false) {
            return 'manual';
        }
        
        // Si contiene "esp32", "automático", "iot" es automático
        if (strpos($locationName, 'esp32') !== false || 
            strpos($locationName, 'automático') !== false ||
            strpos($locationName, 'automatico') !== false ||
            strpos($locationName, 'iot') !== false ||
            strpos($locationName, 'experimental') !== false) {
            return 'automatico';
        }

        // Por defecto, automático
        return 'automatico';
    }

    /**
     * Almacena un registro de tiempo ingresado manualmente para subparcelas de control
     */
    public function storeManual(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'fecha' => 'required|date',
            'hora_alerta' => 'required',
            'hora_evento' => 'required',
            'subparcela' => ['required', 'string', 'regex:/^[Ss]\d+$/'],
        ]);

        $location = Location::findOrFail($request->location_id);

        // Validar que sea subparcela de control
        if ($location->experimental_group !== 'control') {
            return back()->withErrors(['location_id' => 'La subparcela seleccionada debe ser una parcela de control.'])->withInput();
        }

        // Parsear fecha y horas
        $fecha = $request->fecha;
        $ti = Carbon::parse($fecha . ' ' . $request->hora_alerta);
        $tf = Carbon::parse($fecha . ' ' . $request->hora_evento);

        // Calcular diff en segundos
        $tarSeconds = $tf->diffInSeconds($ti);
        $subparcelaVal = strtoupper($request->subparcela);

        // Crear registro en la tabla analysis (Lixiviación) para mantener sincronización
        $analysis = \App\Models\Analysis::create([
            'lote_id'                  => $location->lote_id,
            'location_id'              => $location->id,
            'experimental_group'       => $location->experimental_group,
            'conductivity_superficial' => 0.0,
            'conductivity_profundo'    => 0.0,
            'delta_conductivity'       => 0.0,
            'ilx'                      => 1.1000,
            'ilx_estado'               => 'LIXIVIACIÓN',
            'lixiviation_detected'     => true,
            'risk_level'               => 'MEDIO',
            'threshold_used'           => 1.20,
            'analyzed_at'              => $ti,
            'event_detected_at'        => $ti,
            'alert_generated_at'       => $ti,
            'event_type'               => 'LIXIVIATION',
            'is_validated'             => true,
            'validated_at'             => $tf,
            'notes'                    => 'Registro manual desde Tiempo de Detección',
        ]);

        // Crear alerta manual
        $alert = Alert::create([
            'location_id'   => $location->id,
            'lote_id'       => $location->lote_id,
            'analysis_id'   => $analysis->id,
            'type'          => 'lixiviacion',
            'severity'      => 'MEDIO',
            'level'         => 'medio',
            'status'        => 'RESOLVED',
            'is_resolved'   => true,
            'resolved_at'   => $tf,
            'description'   => 'Registro manual de lixiviación (Control)',
            'tiempo_alerta' => $ti,
            'tiempo_riesgo' => $tf,
            'tar'           => $tarSeconds,
            'subparcela'    => $subparcelaVal,
        ]);

        // Crear registro de observación (Análisis Académico) para mantener sincronización
        $observacion = new \App\Models\Observacion([
            'location_id'        => $location->id,
            'experimental_group' => $location->experimental_group,
            'alert_id'           => $alert->id,
            'ce_real'            => 0.0,
            'diagnostico'        => 'LIXIVIACION',
            'resultado'          => 'VP',
        ]);
        $observacion->created_at = $ti;
        $observacion->updated_at = $ti;
        $observacion->save();

        return redirect()->route('detection_time', ['location_id' => $location->id])
            ->with('success', 'Registro de tiempo manual guardado correctamente.');
    }
    // 🆕 AGREGAR ESTE MÉTODO AQUÍ (después de storeManual)
        /**
         * Actualiza un registro de tiempo de detección manual
         */
        public function updateManual(Request $request, $recordId)
        {
            $request->validate([
                'subparcela'  => ['required', 'string', 'regex:/^[Ss]\d+$/'],
                'fecha'       => 'required|date',
                'hora_alerta' => 'required',
                'hora_evento' => 'required',
            ]);
            
            $record = DetectionTimeRecord::findOrFail($recordId);
            
            $fecha = $request->fecha;
            $ti = Carbon::parse($fecha . ' ' . $request->hora_alerta);
            $tf = Carbon::parse($fecha . ' ' . $request->hora_evento);
            $tarSeconds = $tf->diffInSeconds($ti);
            
            $record->update([
                'fecha'                    => $fecha,
                'subparcela'               => strtoupper($request->subparcela),
                'tiempo_promedio_segundos' => $tarSeconds,
                'suma_tiempos_segundos'    => $tarSeconds,
                'cantidad_eventos'         => 1,
            ]);
            
            return redirect()->route('detection_time', ['location_id' => $record->location_id])
                ->with('success', '✅ Registro actualizado correctamente.');
        }


}
