<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SensorController extends Controller
{
    /**
     * Recibir datos del ESP32
     * POST: sensor_id, humedad, temperatura, ce
     */
    public function store(Request $request)
    {
        try {
            Log::info('🟢 [ESP32 DATA RECEIVED]', [
                'method' => $request->method(),
                'content_type' => $request->header('content-type'),
                'raw_data' => $request->all(),
                'ip' => $request->ip()
            ]);

            // Obtener datos SIN validación estricta (ser tolerante)
            $sensor_id = $request->input('sensor_id', 'ESP32_UNKNOWN');
            $humedad = (float) filter_var($request->input('humedad', 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $temperatura = (float) filter_var($request->input('temperatura', 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $ce = (float) filter_var($request->input('ce', 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            Log::info('✅ [DATA PARSED]', compact('sensor_id', 'humedad', 'temperatura', 'ce'));

            // Obtener o crear lote
            try {
                $lote = Lote::where('nombre', 'Sensor_' . $sensor_id)->first();
                
                if (!$lote) {
                    $lote = Lote::create([
                        'nombre' => 'Sensor_' . $sensor_id,
                        'variedad' => 'Automático',
                        'user_id' => 1
                    ]);
                    Log::info('🆕 [NEW LOTE CREATED]', ['lote_id' => $lote->id, 'sensor_id' => $sensor_id]);
                }
            } catch (\Exception $e) {
                Log::error('❌ [LOTE ERROR]', ['error' => $e->getMessage()]);
                return response('LOTE_ERROR', 500)->header('Content-Type', 'text/plain');
            }

            // Guardar lectura
            try {
                $reading = EcReading::create([
                    'sensor_id' => $sensor_id,
                    'lote_id' => $lote->id,
                    'humidity' => $humedad,
                    'temperature' => $temperatura,
                    'value' => $ce,
                ]);

                Log::info('✅ [READING SAVED]', [
                    'reading_id' => $reading->id,
                    'sensor_id' => $sensor_id,
                    'humedad' => $humedad,
                    'temperatura' => $temperatura,
                    'ce' => $ce
                ]);

                // Responder OK
                return response('OK', 200)
                    ->header('Content-Type', 'text/plain');

            } catch (\Exception $e) {
                Log::error('❌ [SAVE FAILED]', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return response('SAVE_ERROR', 500)->header('Content-Type', 'text/plain');
            }

        } catch (\Exception $e) {
            Log::error('❌ [GENERAL ERROR]', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('ERROR', 500)->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Obtener últimos datos para gráficos (AJAX)
     */
    public function getData(Request $request)
    {
        try {
            $limit = min($request->input('limit', 30), 100);
            $sensor_id = $request->input('sensor_id');

            $query = Reading::orderBy('recorded_at', 'desc')->limit($limit);
            
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
                'labels' => $readings->pluck('recorded_at')->map(fn($date) => $date->format('H:i:s')),
                'sensor_id' => $readings->pluck('sensor_id'),
                'humedad' => $readings->pluck('humidity')->map(fn($v) => (float)$v),
                'temperatura' => $readings->pluck('temperature')->map(fn($v) => (float)$v),
                'ce' => $readings->pluck('conductivity')->map(fn($v) => (float)$v),
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
            
            $query = Reading::orderBy('recorded_at', 'desc');
            
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

