<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alert;
use App\Models\Analysis;
use App\Models\Reading;
use App\Models\Sensor;
use Illuminate\Support\Facades\Log;

/**
 * LixiviationService — v3 ILx
 *
 * Responsabilidad: detectar el estado de lixiviación a partir de un par de
 * sensores (superficial + profundo), persistir el análisis y generar alertas.
 *
 * ── Indicador PRINCIPAL ───────────────────────────────────────────────────
 *   ILx = CE_p / CE_s   (0 cuando CE_s == 0)
 *
 *   ILx > 1.2           → LIXIVIACIÓN ALTA  (riesgo 2)
 *   1.05 < ILx ≤ 1.2    → LIXIVIACIÓN       (riesgo 1)
 *   0.9 ≤ ILx ≤ 1.05    → EQUILIBRIO        (riesgo 0)
 *   0.7 ≤ ILx < 0.9     → RETENCIÓN         (riesgo 1)
 *   ILx < 0.7            → ACUMULACIÓN       (riesgo 2)
 *
 * ── Indicador SECUNDARIO ─────────────────────────────────────────────────
 *   ΔCE = CE_s - CE_p   (solo visual / tendencia, no determina estado)
 */
class LixiviationService
{
    private AnalisisService $analisisService;

    // ILx thresholds (único criterio de decisión)
    private const ILX_LIX_ALTA  = 1.20;
    private const ILX_LIX       = 1.05;
    private const ILX_EQUIL_LOW = 0.90;
    private const ILX_RET_LOW   = 0.70;

    public function __construct(AnalisisService $analisisService)
    {
        $this->analisisService = $analisisService;
    }

    public function analyze(Sensor $sensor_sup, Sensor $sensor_prof): void
    {
        try {
            $r_sup  = Reading::where('sensor_id', $sensor_sup->id)->orderByDesc('id')->first();
            $r_prof = Reading::where('sensor_id', $sensor_prof->id)->orderByDesc('id')->first();

            if (!$r_sup || !$r_prof) return;

            // Rechazar si los timestamps difieren más de 5 minutos
            if (abs($r_sup->recorded_at->diffInSeconds($r_prof->recorded_at)) > 300) {
                Log::warning('LixiviationService: readings not synchronized', [
                    'sup'  => $r_sup->recorded_at,
                    'prof' => $r_prof->recorded_at,
                ]);
                return;
            }

            $ce_s = (float) $r_sup->conductivity;
            $ce_p = (float) $r_prof->conductivity;

            // ── INDICADOR PRINCIPAL: ILx ──────────────────────────────────
            $ilx   = $ce_s > 0 ? round($ce_p / $ce_s, 4) : 0.0;

            // ── INDICADOR SECUNDARIO: ΔCE (complemento visual) ───────────
            $delta = round($ce_s - $ce_p, 4);

            [$ilx_estado, $detected, $risk] = $this->classifyByILx($ilx);

            $location = $sensor_sup->location;
            $now      = now();

            // Lectura superficial anterior (para ΔCE temporal en alertas)
            $prev_r_sup  = Reading::where('sensor_id', $sensor_sup->id)
                ->where('id', '<', $r_sup->id)->orderByDesc('id')->first();
            $ce_anterior = $prev_r_sup ? (float) $prev_r_sup->conductivity : $ce_s;
            $delta_ce    = round($ce_s - $ce_anterior, 4);

            $analysis = Analysis::create([
                'lote_id'                  => $location->lote_id,
                'location_id'              => $location->id,
                'experimental_group'       => $location->experimental_group,
                'sensor_superficial_id'    => $sensor_sup->id,
                'sensor_profundo_id'       => $sensor_prof->id,
                'reading_superficial_id'   => $r_sup->id,
                'reading_profundo_id'      => $r_prof->id,
                'conductivity_superficial' => $ce_s,
                'conductivity_profundo'    => $ce_p,
                'delta_conductivity'       => $delta,      // ΔCE — complementario
                'ilx'                      => $ilx,        // ILx — PRINCIPAL
                'ilx_estado'               => $ilx_estado, // Estado agronómico
                'threshold_used'           => self::ILX_LIX_ALTA, // umbral de referencia
                'lixiviation_detected'     => $detected,
                'risk_level'               => $risk,
                'risk_percentage'          => $this->calcRiskPct($ilx),
                'analyzed_at'              => $now,
                'event_detected_at'        => $now,
                'alert_generated_at'       => $detected ? $now : null,
                'event_type'               => 'LIXIVIATION',
            ]);

            $shouldAlert = $detected || $risk === 'MEDIO';

            // Validar preferencias de alerta de la ubicación
            if ($shouldAlert) {
                $settings = $location->alert_settings ?? [
                    'lixiviacion_alta' => true,
                    'lixiviacion' => true,
                    'acumulacion' => true,
                ];

                $settingKey = strtolower(str_replace([' ', 'Ó'], ['_', 'o'], $ilx_estado));
                if (!($settings[$settingKey] ?? true)) {
                    $shouldAlert = false;
                    Log::info("Alerta omitida por configuración del usuario: {$ilx_estado}", ['location' => $location->name]);
                }
            }

            if ($shouldAlert) {
                $existing = Alert::where('location_id', $location->id)->where('status', 'OPEN')->first();

                if (!$existing) {
                    Alert::create([
                        'lote_id'       => $location->lote_id,
                        'location_id'   => $location->id,
                        'analysis_id'   => $analysis->id,
                        'type'          => 'lixiviacion',
                        'description'   => sprintf(
                            'ILx=%.4f (%s) | ΔCE=%.4f dS/m',
                            $ilx, $ilx_estado, $delta
                        ),
                        'severity'      => $risk,
                        'level'         => $risk,
                        'status'        => 'OPEN',
                        'ce_actual'     => $ce_s,
                        'ce_anterior'   => $ce_anterior,
                        'delta_ce'      => $delta_ce,
                        'tiempo_riesgo' => $r_sup->recorded_at,
                        'tiempo_alerta' => $now,
                    ]);

                    Log::warning('ALERTA REGISTRADA: ' . $risk, [
                        'location'  => $location->name,
                        'ilx'       => $ilx,
                        'ilx_estado'=> $ilx_estado,
                        'ce_s'      => $ce_s,
                        'ce_p'      => $ce_p,
                    ]);
                } else {
                    $this->updateExistingAlert($existing, $risk, $ce_s, $ilx, $ilx_estado);
                }
            } else {
                $this->analisisService->evaluateOpenAlerts($location->id, $ce_s, $analysis);
            }

        } catch (\Exception $e) {
            Log::error('LixiviationService error', ['error' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CLASIFICACIÓN POR ILx — ÚNICO CRITERIO DE DECISIÓN
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Clasifica el estado agronómico basándose exclusivamente en ILx.
     *
     * @return array{string, bool, string}  [ilx_estado, lixiviation_detected, risk_level]
     */
    private function classifyByILx(float $ilx): array
    {
        if ($ilx > self::ILX_LIX_ALTA) {
            return ['LIXIVIACIÓN ALTA', true,  'ALTO'];
        }
        if ($ilx > self::ILX_LIX) {
            return ['LIXIVIACIÓN',      true,  'MEDIO'];
        }
        if ($ilx >= self::ILX_EQUIL_LOW) {
            return ['EQUILIBRIO',       false, 'BAJO'];
        }
        if ($ilx >= self::ILX_RET_LOW) {
            return ['RETENCIÓN',        false, 'BAJO'];
        }
        // ILx < 0.7 → ACUMULACIÓN (sales acumuladas, también requiere atención)
        return ['ACUMULACIÓN',          true,  'MEDIO'];
    }

    /**
     * Calcula un porcentaje de riesgo proporcional a la desviación del ILx respecto al equilibrio.
     * Zona de equilibrio: 0.9 – 1.05. La desviación máxima esperada es ±0.5.
     */
    private function calcRiskPct(float $ilx): float
    {
        // Centro del equilibrio
        $center  = 0.975;
        $maxDev  = 0.5;
        $deviation = abs($ilx - $center);
        return round(min(100.0, ($deviation / $maxDev) * 100), 2);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTUALIZACIÓN DE ALERTA EXISTENTE
    // ═══════════════════════════════════════════════════════════════════════

    private function updateExistingAlert(Alert $existing, string $newRisk, float $ce_s, float $ilx, string $ilx_estado): void
    {
        $riskWeights   = ['BAJO' => 0, 'MEDIO' => 1, 'ALTO' => 2, 'CRÍTICO' => 3];
        $oldRisk       = strtoupper($existing->level ?? 'BAJO');
        $riskIncreased = ($riskWeights[$newRisk] ?? 0) > ($riskWeights[$oldRisk] ?? 0);
        $riskPersists  = ($riskWeights[$newRisk] ?? 0) >= 1;

        $updates = [];
        if ($ce_s > $existing->ce_actual) {
            $updates['ce_actual'] = $ce_s;
        }
        if ($riskIncreased) {
            $updates['level']    = $newRisk;
            $updates['severity'] = $newRisk;
        }
        if (!empty($updates)) {
            $existing->update($updates);
        }

        // Re-notificar si el riesgo aumentó o llevan ≥ 20 min sin notificar
        $lastNotify       = $existing->notified_at ? \Carbon\Carbon::parse($existing->notified_at) : null;
        $minutesSinceLast = $lastNotify ? $lastNotify->diffInMinutes(now()) : 999;

        if ($riskIncreased || ($riskPersists && $minutesSinceLast >= 20)) {
            try {
                $telegram = resolve(\App\Services\TelegramService::class);
                $success  = $telegram->sendAlert($existing, true);

                if ($success) {
                    $existing->updateQuietly(['notified_at' => now()]);
                    Log::info('Actualización Telegram enviada', [
                        'alert_id'  => $existing->id,
                        'ilx'       => $ilx,
                        'ilx_estado'=> $ilx_estado,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error en re-notificación Telegram: ' . $e->getMessage());
            }
        }
    }
}
