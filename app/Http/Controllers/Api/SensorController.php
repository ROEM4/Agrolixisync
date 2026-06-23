<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lectura;
use App\Models\Planta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensorController extends Controller
{
    /**
     * Obtener últimos datos para gráficos (AJAX)
     */
    public function getData(Request $request)
    {
        try {
            $limit = min($request->input('limit', 30), 100);
            $sensor_id = $request->input('sensor_id');

            $query = Lectura::orderBy('fecha_registro', 'desc')->limit($limit);
            
            if ($sensor_id) {
                $query->where('sensor_id', $sensor_id);
            }

            $readings = $query->get()->reverse()->values();

            if ($readings->isEmpty()) {
                return response()->json([
                    'labels' => [],
                    'sensor_id' => [],
                    'humedad' => [],
                    'temperatura' => [],
                    'ce' => [],
                ]);
            }

            return response()->json([
                'labels' => $readings->pluck('fecha_registro')->map(fn($date) => $date->format('H:i:s')),
                'sensor_id' => $readings->pluck('sensor_id'),
                'humedad' => $readings->pluck('humedad')->map(fn($v) => (float)$v),
                'temperatura' => $readings->pluck('temperatura')->map(fn($v) => (float)$v),
                'ce' => $readings->pluck('conductividad')->map(fn($v) => (float)$v),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener datos de gráficos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener datos'], 500);
        }
    }

    /**
     * Obtener última lectura
     */
    public function getLatest(Request $request)
    {
        try {
            $sensor_id = $request->input('sensor_id');
            
            $query = Lectura::orderBy('fecha_registro', 'desc');
            
            if ($sensor_id) {
                $query->where('sensor_id', $sensor_id);
            }

            $latest = $query->first();

            if (!$latest) {
                return response()->json(['message' => 'No hay datos'], 404);
            }

            return response()->json($latest);
        } catch (\Exception $e) {
            Log::error('Error al obtener última lectura: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener datos'], 500);
        }
    }
}
