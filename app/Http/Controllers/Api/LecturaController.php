<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lectura;
use App\Models\AnalisisLixiviacion;
use App\Models\Alerta;
use App\Models\Ubicacion;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LecturaController extends Controller
{
    /**
     * GET /api/readings/latest
     * Usado por realtime.blade.php
     */
    public function latest(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        
        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id requerido'
            ], 400);
        }

        $ubicacion = Ubicacion::with('sensores')->find($locationId);
        
        if (!$ubicacion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ubicación no encontrada'
            ], 404);
        }

        $readings = [];
        $analysis = null;

        foreach ($ubicacion->sensores as $sensor) {
            $ultima = Lectura::where('sensor_id', $sensor->id)
                ->orderByDesc('fecha_registro')
                ->first();

            if ($ultima) {
                $readings[] = [
                    'id' => $ultima->id,
                    'sensor' => [
                        'id' => $sensor->id,
                        'code' => $sensor->codigo,
                        'depth' => (int) $sensor->profundidad
                    ],
                    'conductivity' => (float) $ultima->conductividad,
                    'conductivity_raw' => (float) $ultima->conductividad,
                    'humidity' => $ultima->humedad !== null ? (float) $ultima->humedad : null,
                    'temperature' => $ultima->temperatura !== null ? (float) $ultima->temperatura : null,
                    'recorded_at' => $ultima->fecha_registro->toIso8601String()
                ];
            }
        }

        // Calcular ILx si hay ambos sensores
        $sup = collect($readings)->firstWhere('sensor.depth', 20);
        $prof = collect($readings)->firstWhere('sensor.depth', 60);

        if ($sup && $prof && $sup['conductivity'] > 0) {
            $ilx = $prof['conductivity'] / $sup['conductivity'];
            $analysis = [
                'ilx' => round($ilx, 4),
                'ilx_estado' => $this->classifyILx($ilx)
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'readings' => $readings,
                'analysis' => $analysis
            ]
        ]);
    }

    /**
     * GET /api/readings/history
     * Usado por historico.blade.php
     */
    public function history(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $limit = (int) $request->query('limit', 100);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id requerido'
            ], 400);
        }

        if ($locationId === 'all') {
            return $this->historyAllPlants($limit);
        }

        $sensores = Sensor::where('ubicacion_id', $locationId)->get();
        $supSensor = $sensores->firstWhere('profundidad', 20);
        $profSensor = $sensores->firstWhere('profundidad', 60);

        if (!$supSensor || !$profSensor) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $lecturasSup = Lectura::where('sensor_id', $supSensor->id)
            ->orderByDesc('fecha_registro')
            ->limit($limit)
            ->get();

        $lecturasProf = Lectura::where('sensor_id', $profSensor->id)
            ->orderByDesc('fecha_registro')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($lecturasSup as $sup) {
            $prof = $lecturasProf->first(function($p) use ($sup) {
                return abs($p->fecha_registro->diffInSeconds($sup->fecha_registro)) < 300;
            });

            $data[] = [
                'sup' => [
                    'id' => $sup->id,
                    'sensor' => [
                        'id' => $supSensor->id,
                        'code' => $supSensor->codigo,
                        'depth' => 20
                    ],
                    'conductivity' => (float) $sup->conductividad,
                    'conductivity_raw' => (float) $sup->conductividad,
                    'humidity' => $sup->humedad !== null ? (float) $sup->humedad : null,
                    'temperature' => $sup->temperatura !== null ? (float) $sup->temperatura : null,
                    'recorded_at' => $sup->fecha_registro->toIso8601String()
                ],
                'prof' => $prof ? [
                    'id' => $prof->id,
                    'sensor' => [
                        'id' => $profSensor->id,
                        'code' => $profSensor->codigo,
                        'depth' => 60
                    ],
                    'conductivity' => (float) $prof->conductividad,
                    'conductivity_raw' => (float) $prof->conductividad,
                    'humidity' => $prof->humedad !== null ? (float) $prof->humedad : null,
                    'temperature' => $prof->temperatura !== null ? (float) $prof->temperatura : null,
                    'recorded_at' => $prof->fecha_registro->toIso8601String()
                ] : null,
                'recorded_at' => $sup->fecha_registro->toIso8601String()
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'count' => count($data)
        ]);
    }

    private function historyAllPlants($limit): JsonResponse
    {
        $ubicaciones = Ubicacion::where('grupo_experimental', 'experimental')
            ->with('sensores')
            ->get();

        $data = [];
        
        foreach ($ubicaciones as $ubicacion) {
            $supSensor = $ubicacion->sensores->firstWhere('profundidad', 20);
            $profSensor = $ubicacion->sensores->firstWhere('profundidad', 60);

            if (!$supSensor || !$profSensor) continue;

            $lecturasSup = Lectura::where('sensor_id', $supSensor->id)
                ->orderByDesc('fecha_registro')
                ->limit($limit)
                ->get();

            $lecturasProf = Lectura::where('sensor_id', $profSensor->id)
                ->orderByDesc('fecha_registro')
                ->limit($limit)
                ->get();

            foreach ($lecturasSup as $sup) {
                $prof = $lecturasProf->first(function($p) use ($sup) {
                    return abs($p->fecha_registro->diffInSeconds($sup->fecha_registro)) < 300;
                });

                $data[] = [
                    'sup' => [
                        'id' => $sup->id,
                        'sensor' => [
                            'id' => $supSensor->id,
                            'code' => $supSensor->codigo,
                            'depth' => 20,
                            'location' => $ubicacion->nombre
                        ],
                        'conductivity' => (float) $sup->conductividad,
                        'conductivity_raw' => (float) $sup->conductividad,
                        'humidity' => $sup->humedad !== null ? (float) $sup->humedad : null,
                        'temperature' => $sup->temperatura !== null ? (float) $sup->temperatura : null,
                        'recorded_at' => $sup->fecha_registro->toIso8601String()
                    ],
                    'prof' => $prof ? [
                        'id' => $prof->id,
                        'sensor' => [
                            'id' => $profSensor->id,
                            'code' => $profSensor->codigo,
                            'depth' => 60,
                            'location' => $ubicacion->nombre
                        ],
                        'conductivity' => (float) $prof->conductividad,
                        'conductivity_raw' => (float) $prof->conductividad,
                        'humidity' => $prof->humedad !== null ? (float) $prof->humedad : null,
                        'temperature' => $prof->temperatura !== null ? (float) $prof->temperatura : null,
                        'recorded_at' => $prof->fecha_registro->toIso8601String()
                    ] : null,
                    'recorded_at' => $sup->fecha_registro->toIso8601String()
                ];
            }
        }

        usort($data, function($a, $b) {
            return strtotime($b['recorded_at']) - strtotime($a['recorded_at']);
        });

        return response()->json([
            'status' => 'success',
            'data' => array_slice($data, 0, $limit),
            'count' => count($data)
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $days = (int) $request->query('days', 30);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id requerido'
            ], 400);
        }

        if ($locationId === 'all') {
            return $this->dailyAllPlants();
        }

        $ubicacion = Ubicacion::with('sensores')->find($locationId);
        
        if (!$ubicacion) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $supSensor = $ubicacion->sensores->firstWhere('profundidad', 20);
        $profSensor = $ubicacion->sensores->firstWhere('profundidad', 60);

        if (!$supSensor || !$profSensor) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $supData = Lectura::where('sensor_id', $supSensor->id)
            ->selectRaw('DATE(fecha_registro) as date, 
                        AVG(conductividad) as avg_ce,
                        AVG(humedad) as avg_hum,
                        AVG(temperatura) as avg_temp,
                        COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $profData = Lectura::where('sensor_id', $profSensor->id)
            ->selectRaw('DATE(fecha_registro) as date, 
                        AVG(conductividad) as avg_ce,
                        AVG(humedad) as avg_hum,
                        AVG(temperatura) as avg_temp,
                        COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $daily = [];
        foreach ($supData as $sup) {
            $prof = $profData->firstWhere('date', $sup->date);
            
            $ceSup = (float) $sup->avg_ce;
            $ceProf = $prof ? (float) $prof->avg_ce : null;
            $ilx = ($ceSup > 0 && $ceProf !== null) ? round($ceProf / $ceSup, 4) : null;

            $daily[] = [
                'day' => Carbon::parse($sup->date)->format('d/m'),
                'date' => $sup->date,
                'ce_sup_avg' => $ceSup,
                'ce_prof_avg' => $ceProf,
                'hum_sup_avg' => $sup->avg_hum ? round((float) $sup->avg_hum, 1) : null,
                'hum_prof_avg' => $prof && $prof->avg_hum ? round((float) $prof->avg_hum, 1) : null,
                'temp_sup_avg' => $sup->avg_temp ? round((float) $sup->avg_temp, 1) : null,
                'temp_prof_avg' => $prof && $prof->avg_temp ? round((float) $prof->avg_temp, 1) : null,
                'ilx_avg' => $ilx,
                'n' => (int) $sup->count + ($prof ? (int) $prof->count : 0)
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $daily
        ]);
    }

    private function dailyAllPlants(): JsonResponse
    {
        $ubicaciones = Ubicacion::where('grupo_experimental', 'experimental')
            ->with('sensores')
            ->get();

        $sensorIds = [];
        foreach ($ubicaciones as $ubicacion) {
            $supSensor = $ubicacion->sensores->firstWhere('profundidad', 20);
            $profSensor = $ubicacion->sensores->firstWhere('profundidad', 60);
            
            if ($supSensor) $sensorIds[] = $supSensor->id;
            if ($profSensor) $sensorIds[] = $profSensor->id;
        }

        if (empty($sensorIds)) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $supData = Lectura::whereIn('sensor_id', $sensorIds)
            ->join('sensores', 'lecturas.sensor_id', '=', 'sensores.id')
            ->where('sensores.profundidad', 20)
            ->selectRaw('DATE(lecturas.fecha_registro) as date, 
                        AVG(lecturas.conductividad) as avg_ce,
                        AVG(lecturas.humedad) as avg_hum,
                        AVG(lecturas.temperatura) as avg_temp,
                        COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $profData = Lectura::whereIn('sensor_id', $sensorIds)
            ->join('sensores', 'lecturas.sensor_id', '=', 'sensores.id')
            ->where('sensores.profundidad', 60)
            ->selectRaw('DATE(lecturas.fecha_registro) as date, 
                        AVG(lecturas.conductividad) as avg_ce,
                        AVG(lecturas.humedad) as avg_hum,
                        AVG(lecturas.temperatura) as avg_temp,
                        COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $daily = [];
        foreach ($supData as $sup) {
            $prof = $profData->firstWhere('date', $sup->date);
            
            $ceSup = (float) $sup->avg_ce;
            $ceProf = $prof ? (float) $prof->avg_ce : null;
            $ilx = ($ceSup > 0 && $ceProf !== null) ? round($ceProf / $ceSup, 4) : null;

            $daily[] = [
                'day' => Carbon::parse($sup->date)->format('d/m'),
                'date' => $sup->date,
                'ce_sup_avg' => $ceSup,
                'ce_prof_avg' => $ceProf,
                'hum_sup_avg' => $sup->avg_hum ? round((float) $sup->avg_hum, 1) : null,
                'hum_prof_avg' => $prof && $prof->avg_hum ? round((float) $prof->avg_hum, 1) : null,
                'temp_sup_avg' => $sup->avg_temp ? round((float) $sup->avg_temp, 1) : null,
                'temp_prof_avg' => $prof && $prof->avg_temp ? round((float) $prof->avg_temp, 1) : null,
                'ilx_avg' => $ilx,
                'n' => (int) $sup->count + ($prof ? (int) $prof->count : 0)
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $daily
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $days = (int) $request->query('days', 30);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id requerido'
            ], 400);
        }

        if ($locationId === 'all') {
            return $this->analyticsAllPlants();
        }

        return response()->json([
            'status' => 'success',
            'data' => []
        ]);
    }

    private function analyticsAllPlants(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => []
        ]);
    }

    private function classifyILx($ilx): string
    {
        if ($ilx > 1.0) return 'LIXIVIACIÓN ALTA';
        if ($ilx >= 0.6) return 'LIXIVIACIÓN MEDIA';
        if ($ilx >= 0.4) return 'EQUILIBRIO';
        return 'LIXIVIACIÓN BAJA';
    }

    public function recordReading(Request $request)
    {
        $validated = $request->validate([
            'device_code' => 'required|string|exists:ubicaciones,codigo_dispositivo',
            'ce_sup' => 'required|numeric',
            'ce_prof' => 'required|numeric',
            'hum_sup' => 'nullable|numeric',
            'hum_prof' => 'nullable|numeric',
            'temp_sup' => 'nullable|numeric',
            'temp_prof' => 'nullable|numeric',
            'timestamp' => 'nullable|date',
        ]);

        $ubicacion = Ubicacion::where('codigo_dispositivo', $validated['device_code'])->first();

        if (!$ubicacion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dispositivo no encontrado'
            ], 404);
        }

        $timestamp = $validated['timestamp'] ? Carbon::parse($validated['timestamp']) : now();

        $sensores = $ubicacion->sensores;
        $supSensor = $sensores->firstWhere('profundidad', 20);
        $profSensor = $sensores->firstWhere('profundidad', 60);

        $lecturaSup = null;
        $lecturaProf = null;

        if ($supSensor) {
            $lecturaSup = Lectura::create([
                'sensor_id' => $supSensor->id,
                'conductividad' => $validated['ce_sup'],
                'humedad' => $validated['hum_sup'] ?? null,
                'temperatura' => $validated['temp_sup'] ?? null,
                'fecha_registro' => $timestamp,
            ]);
        }

        if ($profSensor) {
            $lecturaProf = Lectura::create([
                'sensor_id' => $profSensor->id,
                'conductividad' => $validated['ce_prof'],
                'humedad' => $validated['hum_prof'] ?? null,
                'temperatura' => $validated['temp_prof'] ?? null,
                'fecha_registro' => $timestamp,
            ]);
        }

        if ($supSensor) $supSensor->update(['ultima_lectura' => now()]);
        if ($profSensor) $profSensor->update(['ultima_lectura' => now()]);

        if ($lecturaSup && $lecturaProf) {
            $this->analizarLixiviacion($ubicacion, $lecturaSup, $lecturaProf, $timestamp);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lecturas registradas correctamente',
            'lectura_superficial_id' => $lecturaSup?->id,
            'lectura_profunda_id' => $lecturaProf?->id,
        ]);
    }

    private function analizarLixiviacion($ubicacion, $lecturaSup, $lecturaProf, $timestamp)
    {
        $ceSup = $lecturaSup->conductividad;
        $ceProf = $lecturaProf->conductividad;

        if ($ceSup == 0) return;

        $ilx = $ceProf / $ceSup;
        $deltaCe = $ceProf - $ceSup;

        if ($ilx > 1.0) {
            $estadoIlx = 'LIXIVIACIÓN ALTA';
            $nivelRiesgo = 'ALTO';
            $lixiviacionDetectada = true;
        } elseif ($ilx >= 0.6) {
            $estadoIlx = 'LIXIVIACIÓN MEDIA';
            $nivelRiesgo = 'MEDIO';
            $lixiviacionDetectada = true;
        } elseif ($ilx >= 0.4) {
            $estadoIlx = 'EQUILIBRIO';
            $nivelRiesgo = 'BAJO';
            $lixiviacionDetectada = false;
        } else {
            $estadoIlx = 'LIXIVIACIÓN BAJA';
            $nivelRiesgo = 'BAJO';
            $lixiviacionDetectada = true;
        }

        $analisis = AnalisisLixiviacion::create([
            'planta_id' => $ubicacion->planta_id,
            'ubicacion_id' => $ubicacion->id,
            'grupo_experimental' => $ubicacion->grupo_experimental,
            'sensor_superficial_id' => $ubicacion->sensores->firstWhere('profundidad', 20)?->id,
            'sensor_profundo_id' => $ubicacion->sensores->firstWhere('profundidad', 60)?->id,
            'lectura_superficial_id' => $lecturaSup->id,
            'lectura_profundo_id' => $lecturaProf->id,
            'conductividad_superficial' => $ceSup,
            'conductividad_profundo' => $ceProf,
            'delta_conductividad' => $deltaCe,
            'ilx' => $ilx,
            'ilx_estado' => $estadoIlx,
            'umbral_usado' => 1.20,
            'lixiviacion_detectada' => $lixiviacionDetectada,
            'nivel_riesgo' => strtolower($nivelRiesgo),
            'porcentaje_riesgo' => $lixiviacionDetectada ? min(100, ($ilx - 1) * 200) : 0,
            'fecha_analisis' => $timestamp,
        ]);

        if ($lixiviacionDetectada && $ubicacion->grupo_experimental === 'experimental') {
            $this->generarAlerta($ubicacion, $analisis, $timestamp);
        }

        return $analisis;
    }

    /**
     * ✅ CORREGIDO: Generar alerta sin bloqueo
     */
    private function generarAlerta($ubicacion, $analisis, $timestamp)
    {
        // ✅ Verificar si ya existe una alerta ABIERTA para esta ubicación
        $existing = Alerta::where('ubicacion_id', $ubicacion->id)
            ->where('estado', 'ABIERTA')
            ->first();

        // ✅ Si ya existe una alerta abierta, NO crear otra (evitar spam)
        if ($existing) {
            return;
        }

        // ✅ Calcular tiempos correctamente
        $tiempoAlerta = $timestamp;
        $tiempoRiesgo = $timestamp->copy()->addMinutes(5);
        
        // ✅ Calcular TAR (Tiempo de Alerta de Riesgo) en segundos
        $tarSeconds = $tiempoRiesgo->diffInSeconds($tiempoAlerta);

        $newAlert = Alerta::create([
            'analisis_lixiviacion_id' => $analisis->id,
            'planta_id'    => $ubicacion->planta_id,
            'ubicacion_id' => $ubicacion->id,
            'subparcela'   => 'A',
            'tipo'         => 'lixiviacion',
            'severidad'    => strtoupper($analisis->nivel_riesgo),
            'estado'       => 'ABIERTA',
            'nivel'        => strtoupper($analisis->nivel_riesgo),
            'descripcion'  => "ILx={$analisis->ilx} ({$analisis->ilx_estado}) | ΔCE={$analisis->delta_conductividad} dS/m",
            'ce_actual'    => $analisis->conductividad_profundo,
            'ce_anterior'  => $analisis->conductividad_superficial,
            'delta_ce'     => $analisis->delta_conductividad,
            'tiempo_alerta' => $tiempoAlerta,
            'tiempo_riesgo' => $tiempoRiesgo,
            'tar'          => $tarSeconds,
            'resuelta'     => false,
        ]);

        try {
            $newAlert->load('ubicacion.planta');
            resolve(\App\Services\AlertService::class)->dispatch($newAlert, false);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error Telegram generarAlerta: ' . $e->getMessage());
        }
    }
}