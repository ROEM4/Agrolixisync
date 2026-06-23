<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alerta;
use App\Models\AnalisisLixiviacion;
use App\Models\Lectura;
use App\Models\Sensor;
use App\Models\Ubicacion;
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

    // ILx thresholds — alineados con alertas.blade.php (modal de configuración)
    private const ILX_ALTA_MIN  = 1.00;  // ILx > 1.0          → Lixiviación Alta
    private const ILX_MEDIA_MIN = 0.60;  // 0.6 <= ILx <= 1.0  → Lixiviación Media
    private const ILX_BAJA_MAX  = 0.40;  // ILx < 0.4          → Lixiviación Baja

    public function __construct(AnalisisService $analisisService)
    {
        $this->analisisService = $analisisService;
    }

    public function analyze(Sensor $sensor_sup, Sensor $sensor_prof): void
    {
        try {
            $r_sup  = Lectura::where('sensor_id', $sensor_sup->id)->orderByDesc('id')->first();
            $r_prof = Lectura::where('sensor_id', $sensor_prof->id)->orderByDesc('id')->first();

            if (!$r_sup || !$r_prof) return;

            // Rechazar si los timestamps difieren más de 5 minutos
            if (abs($r_sup->fecha_registro->diffInSeconds($r_prof->fecha_registro)) > 300) {
                Log::warning('LixiviationService: readings not synchronized', [
                    'sup'  => $r_sup->fecha_registro,
                    'prof' => $r_prof->fecha_registro,
                ]);
                return;
            }

            $ce_s = (float) $r_sup->conductividad;
            $ce_p = (float) $r_prof->conductividad;

            // ── INDICADOR PRINCIPAL: ILx ──────────────────────────────────
            $ilx   = $ce_s > 0 ? round($ce_p / $ce_s, 4) : 0.0;

            // ── INDICADOR SECUNDARIO: ΔCE (complemento visual) ───────────
            $delta = round($ce_s - $ce_p, 4);

            [$ilx_estado, $detected, $risk, $config_key] = $this->classifyByILx($ilx);

            $location = $sensor_sup->ubicacion;
            $now      = now();

            // Lectura superficial anterior (para ΔCE temporal en alertas)
            $prev_r_sup  = Lectura::where('sensor_id', $sensor_sup->id)
                ->where('id', '<', $r_sup->id)->orderByDesc('id')->first();
            $ce_anterior = $prev_r_sup ? (float) $prev_r_sup->conductividad : $ce_s;
            $delta_ce    = round($ce_s - $ce_anterior, 4);

            $analysis = AnalisisLixiviacion::create([
                'planta_id'                => $location->planta_id,
                'ubicacion_id'             => $location->id,
                'grupo_experimental'       => $location->grupo_experimental,
                'sensor_superficial_id'    => $sensor_sup->id,
                'sensor_profundo_id'       => $sensor_prof->id,
                'lectura_superficial_id'   => $r_sup->id,
                'lectura_profundo_id'      => $r_prof->id,
                'conductividad_superficial' => $ce_s,
                'conductividad_profundo'    => $ce_p,
                'delta_conductividad'       => $delta,      // ΔCE — complementario
                'ilx'                      => $ilx,        // ILx — PRINCIPAL
                'ilx_estado'               => $ilx_estado, // Estado agronómico
                'umbral_usado'             => self::ILX_ALTA_MIN,
                'lixiviacion_detectada'     => $detected,
                'nivel_riesgo'               => $risk,
                'porcentaje_riesgo'          => $this->calcRiskPct($ilx),
                'fecha_analisis'              => $now,
                'fecha_deteccion'        => $now,
                'fecha_generacion_alerta'       => $detected ? $now : null,
                'tipo_evento'               => 'LIXIVIATION',
            ]);

            $shouldAlert = $detected;

            // Respetar configuración guardada en alertas.blade.php para esta planta
            if ($shouldAlert && $config_key !== null) {
                $settings = is_array($location->configuracion_alertas ?? null)
                    ? $location->configuracion_alertas
                    : [];
                // Si el usuario configuró al menos una clave, respetarla;
                // si no hay ninguna configuración guardada, se notifica por defecto.
                if (!empty($settings)) {
                    $shouldAlert = $settings[$config_key] ?? false;
                }
            }

            if ($shouldAlert) {
                $existing = Alerta::where('ubicacion_id', $location->id)->where('estado', 'ABIERTA')->first();

                if (!$existing) {
                    $newAlert = Alerta::create([
                        'planta_id'     => $location->planta_id,
                        'ubicacion_id'   => $location->id,
                        'analisis_lixiviacion_id'   => $analysis->id,
                        'tipo'          => 'lixiviacion',
                        'descripcion'   => sprintf(
                            'ILx=%.4f (%s) | ΔCE=%.4f dS/m',
                            $ilx, $ilx_estado, $delta
                        ),
                        'severidad'      => $risk,
                        'nivel'         => $risk,
                        'estado'        => 'ABIERTA',
                        'ce_actual'     => $ce_s,
                        'ce_anterior'   => $ce_anterior,
                        'delta_ce'      => $delta_ce,
                        'tiempo_riesgo' => $r_sup->fecha_registro,
                        'tiempo_alerta' => $now,
                    ]);

                    Log::warning('ALERTA REGISTRADA: ' . $risk, [
                        'location'  => $location->nombre,
                        'ilx'       => $ilx,
                        'ilx_estado'=> $ilx_estado,
                    ]);

                    // ✅ Notificar Telegram con la nueva alerta
                    try {
                        $newAlert->load('ubicacion.planta');
                        $alertService = resolve(\App\Services\AlertService::class);
                        $alertService->dispatch($newAlert, false);
                    } catch (\Exception $e) {
                        Log::error('Error notificando Telegram (nueva alerta): ' . $e->getMessage());
                    }

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
    /**
     * Clasificación según umbrales de alertas.blade.php:
     *   ILx > 1.0           → lixiviacion_alta  (ALTO)
     *   0.6 <= ILx <= 1.0   → lixiviacion_media (MEDIO)
     *   ILx < 0.4           → lixiviacion_baja  (BAJO)
     *   0.4 <= ILx < 0.6    → zona normal, sin alerta
     *
     * @return array{string, bool, string, string}  [estado, detectada, risk, config_key]
     */
    private function classifyByILx(float $ilx): array
    {
        // ILx > 1.0 → Lixiviación Alta
        if ($ilx > self::ILX_ALTA_MIN) {
            return ['LIXIVIACIÓN ALTA',  true,  'ALTO',  'lixiviacion_alta'];
        }
        // 0.6 <= ILx <= 1.0 → Lixiviación Media
        if ($ilx >= self::ILX_MEDIA_MIN) {
            return ['LIXIVIACIÓN MEDIA', true,  'MEDIO', 'lixiviacion_media'];
        }
        // 0.4 <= ILx < 0.6 → zona sin alerta
        if ($ilx >= self::ILX_BAJA_MAX) {
            return ['EQUILIBRIO',        false, 'BAJO',  null];
        }
        // ILx < 0.4 → Lixiviación Baja (acumulación)
        return ['LIXIVIACIÓN BAJA',      true,  'BAJO',  'lixiviacion_baja'];
    }

    /**
     * Calculates risk percentage.
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

    private function updateExistingAlert(Alerta $existing, string $newRisk, float $ce_s, float $ilx, string $ilx_estado): void
    {
        $riskWeights   = ['BAJO' => 0, 'MEDIO' => 1, 'ALTO' => 2, 'CRÍTICO' => 3];
        $oldRisk       = strtoupper($existing->nivel ?? 'BAJO');
        $riskIncreased = ($riskWeights[$newRisk] ?? 0) > ($riskWeights[$oldRisk] ?? 0);
        $riskPersists  = ($riskWeights[$newRisk] ?? 0) >= 1;

        $updates = [];
        if ($ce_s > $existing->ce_actual) {
            $updates['ce_actual'] = $ce_s;
        }
        if ($riskIncreased) {
            $updates['nivel']    = $newRisk;
            $updates['severidad'] = $newRisk;
        }
        if (!empty($updates)) {
            $existing->update($updates);
        }

        // Re-notificar si el riesgo aumentó o llevan ≥ 20 min sin notificar
        $lastNotify       = $existing->updated_at;
        $minutesSinceLast = $lastNotify ? $lastNotify->diffInMinutes(now()) : 999;

        if ($riskIncreased || ($riskPersists && $minutesSinceLast >= 20)) {
            try {
                $telegram = resolve(\App\Services\TelegramService::class);
                $success  = $telegram->sendAlert($existing, true);

                if ($success) {
                    $existing->touch(); // Touch to update updated_at timestamp to avoid spam
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
