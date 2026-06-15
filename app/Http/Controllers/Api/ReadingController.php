<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Reading;
use App\Models\Sensor;
use App\Modules\AnalyticsEngine\LixiviationService;
//use App\Modules\DeviceManager\DeviceManagerService;
use App\Modules\SensorRealtime\IngestionService;
use App\Modules\SensorRealtime\NormalizerService;
use App\Modules\SensorRealtime\SensorPayloadDTO;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ReadingController — Thin controller
 *
 * Solo orquesta módulos. Sin lógica de negocio.
 * Toda la lógica vive en:
 *   - SensorRealtime\NormalizerService   (normalización de formatos)
 *   - SensorRealtime\IngestionService    (persistencia)
 *   - AnalyticsEngine\LixiviationService (detección)
 *   - DeviceManager\DeviceManagerService (heartbeat)
 */
class ReadingController extends Controller
{
    public function __construct(
        private readonly NormalizerService  $normalizer,
        private readonly IngestionService   $ingestion,
        private readonly LixiviationService $lixiviation,
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/sensor/data
    // Contrato v3: {device, ts, ce_s, ce_p, hum_s?, hum_p?, temp_s?, temp_p?}
    // Formatos legacy v1/v2 se normalizan automáticamente.
    // ══════════════════════════════════════════════════════════════════════════
    public function recordReading(Request $request): JsonResponse
    {
        try {
            $request   = $this->normalizer->normalize($request);
            $validated = $request->validate($this->normalizer->rules());
            $validated['ts'] = Carbon::parse($validated['ts'])->utc()->toIso8601String();

            $dto = SensorPayloadDTO::fromValidated($validated);

            // 🔥 LIMPIEZA DEL DEVICE CODE (IMPORTANTE)
            $device = $dto->device;
            $device = preg_replace('/^Auto-[^-]+--/', '', $device);
            $device = str_replace('--', '-', $device);

            $result = $this->ingestion->ingest(
                new SensorPayloadDTO(
                    device: $device,
                    ts: $dto->ts,
                    ce_s: $dto->ce_s,
                    ce_p: $dto->ce_p,
                    hum_s: $dto->hum_s,
                    hum_p: $dto->hum_p,
                    temp_s: $dto->temp_s,
                    temp_p: $dto->temp_p,
                )
            );

            if ($result['status'] === 'success') {

                $sensors = app(\App\Services\IoTAutoProvisioningService::class)
                    ->resolveSensors($device);

                $this->lixiviation->analyze(
                    $sensors['superficial'],
                    $sensors['profundo']
                );

               // $this->deviceManager->heartbeat($device, $result['location_id']);
            }

            Log::info('POST /api/sensor/data', [
                'device' => $device,
                'status' => $result['status']
            ]);

            return response()->json($result, 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'ack' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('POST /api/sensor/data error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'ack' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/readings/latest
    // ══════════════════════════════════════════════════════════════════════════
    public function getLatest(Request $request): JsonResponse
    {
        $location_id = (int) $request->query('location_id');
        if (!$location_id) {
            return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
        }

        $sensors = Sensor::where('location_id', $location_id)->pluck('id');
        if ($sensors->isEmpty()) {
            return response()->json(['status' => 'success', 'data' => ['readings' => [], 'analysis' => null], 'count' => 0]);
        }

        $result = [];
        foreach ($sensors as $sid) {
            $r = Reading::with('sensor')->where('sensor_id', $sid)->orderByDesc('id')->first();
            if ($r) $result[] = $this->formatReading($r);
        }

        $analysis = Analysis::where('location_id', $location_id)->orderByDesc('analyzed_at')->first();

        return response()->json([
            'status'    => 'success',
            'data'      => ['readings' => $result, 'analysis' => $analysis ? $this->formatAnalysis($analysis) : null],
            'count'     => count($result),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/readings/history
    // ══════════════════════════════════════════════════════════════════════════
    public function getHistory(Request $request): JsonResponse
    {
        $location_id = (int) $request->query('location_id');
        $limit       = max(1, min(500, (int) $request->query('limit', 60)));

        if (!$location_id) {
            return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
        }

        $sensors     = Sensor::where('location_id', $location_id)->get()->keyBy(fn($s) => (int) $s->depth);
        $sensor_sup  = $sensors->get(20);
        $sensor_prof = $sensors->get(60);

        if ($sensors->isEmpty()) {
            return response()->json(['status' => 'success', 'data' => [], 'count' => 0]);
        }

        $timestamps = Reading::whereIn('sensor_id', $sensors->pluck('id')->values())
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->pluck('recorded_at')
            ->unique()
            ->values();

        if ($timestamps->isEmpty()) {
            return response()->json(['status' => 'success', 'data' => [], 'count' => 0]);
        }

        $pairs = [];
        foreach ($timestamps as $ts) {
            $sup_r  = $sensor_sup  ? Reading::with('sensor')->where('sensor_id', $sensor_sup->id)->where('recorded_at', $ts)->first()  : null;
            $prof_r = $sensor_prof ? Reading::with('sensor')->where('sensor_id', $sensor_prof->id)->where('recorded_at', $ts)->first() : null;
            if (!$sup_r && !$prof_r) continue;
            $pairs[] = [
                'recorded_at' => ($sup_r ?? $prof_r)->recorded_at->toIso8601String(),
                'sup'         => $sup_r  ? $this->formatReading($sup_r)  : null,
                'prof'        => $prof_r ? $this->formatReading($prof_r) : null,
            ];
        }

        return response()->json(['status' => 'success', 'data' => array_reverse($pairs), 'count' => count($pairs)]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/analysis/latest
    // ══════════════════════════════════════════════════════════════════════════
    public function getLatestAnalysis(Request $request): JsonResponse
    {
        $location_id = (int) $request->query('location_id');
        $analysis = Analysis::when($location_id, fn($q) => $q->where('location_id', $location_id))
            ->orderByDesc('analyzed_at')
            ->first();

        return response()->json(['status' => 'success', 'data' => $analysis ? $this->formatAnalysis($analysis) : null]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/dashboard/data
    // ══════════════════════════════════════════════════════════════════════════
    public function getDashboardData(Request $request): JsonResponse
    {
        $location_id = (int) $request->query('location_id');
        $since       = now()->subHours(max(1, min(168, (int) $request->query('hours', 24))));

        $readings = Reading::with('sensor.location')
            ->when($location_id, fn($q) => $q->whereHas('sensor', fn($s) => $s->where('location_id', $location_id)))
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn($r) => (int) $r->sensor->depth === 20 ? '20cm' : '60cm');

        $analysis = Analysis::when($location_id, fn($q) => $q->where('location_id', $location_id))
            ->orderByDesc('analyzed_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'data'   => ['readings' => $readings, 'analysis' => $analysis, 'last_updated' => now()->toIso8601String()],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SERIALIZACIÓN — sin cambios para mantener compatibilidad con el dashboard
    // ══════════════════════════════════════════════════════════════════════════
    private function formatReading(Reading $reading): array
    {
        $raw = $reading->conductivity;
        $str = $raw !== null ? rtrim(rtrim(number_format((float) $raw, 6, '.', ''), '0'), '.') : null;

        return [
            'id'               => $reading->id,
            'sensor_id'        => $reading->sensor_id,
            'conductivity'     => $str !== null ? (float) $str : null,
            'conductivity_raw' => $str,
            'humidity'         => $reading->humidity    !== null ? round((float) $reading->humidity, 2)    : null,
            'temperature'      => $reading->temperature !== null ? round((float) $reading->temperature, 2) : null,
            'recorded_at'      => $reading->recorded_at->toIso8601String(),
            'sensor' => [
                'id'          => $reading->sensor->id,
                'code'        => $reading->sensor->code,
                'depth'       => (float) $reading->sensor->depth,
                'location_id' => $reading->sensor->location_id,
            ],
        ];
    }

    private function formatAnalysis(Analysis $analysis): array
    {
        $ce_s  = (float) $analysis->conductivity_superficial;
        $ce_p  = (float) $analysis->conductivity_profundo;

        // ILx: tomamos el valor persistido; si no existe, recalculamos en tiempo real
        $ilx   = $analysis->ilx !== null
            ? (float) $analysis->ilx
            : ($ce_s > 0 ? round($ce_p / $ce_s, 4) : 0.0);

        // ΔCE: dato complementario
        $delta = (float) $analysis->delta_conductivity;

        return [
            'id'                       => $analysis->id,
            // ── INDICADOR PRINCIPAL ───────────────────────────────────
            'ilx'                      => $ilx,
            'ilx_estado'               => $analysis->ilx_estado ?? $this->mapILxEstado($ilx),
            // ── INDICADOR SECUNDARIO (complementario) ─────────────────
            'delta_conductivity'       => $delta,
            // ── DATOS RAW ────────────────────────────────────────────
            'conductivity_superficial' => $ce_s,
            'conductivity_profundo'    => $ce_p,
            'threshold_used'           => (float) $analysis->threshold_used,
            'lixiviation_detected'     => (bool) $analysis->lixiviation_detected,
            'risk_level'               => $analysis->risk_level,
            // 'state' queda para compatibilidad con el frontend legacy
            'state'                    => $analysis->ilx_estado ?? $this->mapILxEstado($ilx),
            'analyzed_at'              => $analysis->analyzed_at->toIso8601String(),
        ];
    }

    /**
     * Clasifica el estado agronómico basado en ILx (criterio principal v3).
     * Usado como fallback cuando ilx_estado aún no está persistido.
     */
    private function mapILxEstado(float $ilx): string
    {
        if ($ilx > 1.20) return 'LIXIVIACIÓN ALTA';
        if ($ilx > 1.05) return 'LIXIVIACIÓN';
        if ($ilx >= 0.90) return 'EQUILIBRIO';
        if ($ilx >= 0.70) return 'RETENCIÓN';
        return 'ACUMULACIÓN';
    }

    /** @deprecated Conservado solo para retrocompatibilidad interna */
    private function mapAnalysisState(Analysis $analysis): string
    {
        $ce_s = (float) $analysis->conductivity_superficial;
        $ce_p = (float) $analysis->conductivity_profundo;
        $ilx  = $ce_s > 0 ? $ce_p / $ce_s : 0.0;
        return $this->mapILxEstado($ilx);
    }
}
