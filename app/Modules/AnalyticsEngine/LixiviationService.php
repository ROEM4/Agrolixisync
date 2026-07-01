<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alerta;
use App\Models\AnalisisLixiviacion;
use App\Models\Lectura;
use App\Models\Sensor;
use App\Models\Ubicacion;
use Illuminate\Support\Facades\Log;

class LixiviationService
{
    private AnalisisService $analisisService;

    private const ILX_ALTA_MIN  = 1.00;
    private const ILX_MEDIA_MIN = 0.60;
    private const ILX_BAJA_MAX  = 0.40;

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

            if (abs($r_sup->fecha_registro->diffInSeconds($r_prof->fecha_registro)) > 300) {
                Log::warning('LixiviationService: readings not synchronized', [
                    'sup'  => $r_sup->fecha_registro,
                    'prof' => $r_prof->fecha_registro,
                ]);
                return;
            }

            $ce_s = (float) $r_sup->conductividad;
            $ce_p = (float) $r_prof->conductividad;

            $ilx   = $ce_s > 0 ? round($ce_p / $ce_s, 4) : 0.0;
            $delta = round($ce_s - $ce_p, 4);

            [$ilx_estado, $detected, $risk, $config_key] = $this->classifyByILx($ilx);

            $location = $sensor_sup->ubicacion;
            $now      = now();

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
                'delta_conductividad'       => $delta,
                'ilx'                      => $ilx,
                'ilx_estado'               => $ilx_estado,
                'umbral_usado'             => self::ILX_ALTA_MIN,
                'lixiviacion_detectada'     => $detected,
                'nivel_riesgo'               => $risk,
                'porcentaje_riesgo'          => $this->calcRiskPct($ilx),
                'fecha_analisis'              => $now,
                'fecha_deteccion'        => $now,
                'fecha_generacion_alerta'       => $detected ? $now : null,
                'tipo_evento'               => 'LIXIVIATION',
            ]);

            $shouldAlert = (bool)$detected;

            // ✅ Normalizar claves de configuración (quitar espacios)
            if ($shouldAlert) {
                $settings = $location->configuracion_alertas;
                if ($settings && (is_array($settings) || is_object($settings))) {
                    $normalizedSettings = [];
                    foreach ((array)$settings as $key => $value) {
                        $normalizedSettings[trim($key)] = $value;
                    }
                    if ($config_key !== null) {
                        $shouldAlert = isset($normalizedSettings[$config_key]) ? (bool)$normalizedSettings[$config_key] : true;
                    }
                }
            }

            if ($shouldAlert) {
                $existing = Alerta::where('ubicacion_id', $location->id)
                    ->where('estado', 'ABIERTA')
                    ->first();

                // ✅ CORRECCIÓN CRÍTICA: Si la alerta existente tiene más de 30 minutos,
                // cerrarla y crear una nueva para garantizar alertas continuas.
                // Esto evita que una alerta "atascada" bloquee todas las futuras.
                if ($existing && $existing->created_at->diffInMinutes(now()) >= 30) {
                    Log::info('LixiviationService: Cerrando alerta antigua para crear nueva', [
                        'alert_id'   => $existing->id,
                        'age_min'    => $existing->created_at->diffInMinutes(now()),
                        'location'   => $location->nombre,
                    ]);
                    $existing->update([
                        'estado'           => 'RESUELTA',
                        'resuelta'         => true,
                        'fecha_resolucion' => now(),
                        'notas_resolucion' => 'Auto-cerrada por persistencia (nueva detección >= 30 min)',
                    ]);
                    $existing = null; // Forzar creación de nueva alerta
                }

                if (!$existing) {
                    // Verificar si el día ya fue consolidado para esta planta
                    $diaCerrado = \App\Models\ConsolidacionDiaria::where('fecha_consolidacion', $now->toDateString())
                        ->where('planta_id', $location->planta_id)
                        ->exists();

                    $newAlert = Alerta::create([
                        'planta_id'               => $location->planta_id,
                        'ubicacion_id'            => $location->id,
                        'analisis_lixiviacion_id' => $analysis->id,
                        'tipo'                    => 'lixiviacion',
                        'descripcion'             => sprintf(
                            'ILx=%.4f (%s) | ΔCE=%.4f dS/m',
                            $ilx, $ilx_estado, $delta
                        ),
                        'severidad'               => $risk,
                        'nivel'                   => $risk,
                        'estado'                  => $diaCerrado ? 'RESUELTA' : 'ABIERTA',
                        'ce_actual'               => $ce_s,
                        'ce_anterior'             => $ce_anterior,
                        'delta_ce'                => $delta_ce,
                        'tiempo_riesgo'           => $r_sup->fecha_registro,
                        'tiempo_alerta'           => $now,
                        'tar'                     => null, // Se calcula realmente al evaluar como VP
                        'resuelta'                => $diaCerrado,
                        'fecha_resolucion'        => $diaCerrado ? $now : null,
                        'notas_resolucion'        => $diaCerrado ? 'Auto-resuelta al nacer (Día ya consolidado)' : null,
                    ]);

                    Log::warning('✅ NUEVA ALERTA REGISTRADA: ' . $risk, [
                        'location'   => $location->nombre,
                        'ilx'        => $ilx,
                        'ilx_estado' => $ilx_estado,
                    ]);

                    // Enviar Telegram con alerta nueva
                    try {
                        $newAlert->load('ubicacion.planta');
                        $alertService = resolve(\App\Services\AlertService::class);
                        $alertService->dispatch($newAlert, false);
                    } catch (\Exception $e) {
                        Log::error('Error notificando Telegram (nueva alerta): ' . $e->getMessage());
                    }

                } else {
                    // ✅ CORRECCIÓN: Actualizar alerta existente y re-notificar si es necesario
                    $this->updateExistingAlert($existing, $risk, $ce_s, $ilx, $ilx_estado);
                }
            } else {
                $this->analisisService->evaluateOpenAlerts($location->id, $ce_s, $analysis);
            }

        } catch (\Exception $e) {
            Log::error('LixiviationService error', ['error' => $e->getMessage()]);
        }
    }

    private function updateExistingAlert(Alerta $existing, string $newRisk, float $ce_s, float $ilx, string $ilx_estado): void
    {
        $riskWeights   = ['ALTO' => 3, 'MEDIO' => 2, 'BAJO' => 1];
        $oldRisk       = strtoupper($existing->nivel ?? 'BAJO');
        $riskIncreased = ($riskWeights[$newRisk] ?? 0) > ($riskWeights[$oldRisk] ?? 0);
        $riskPersists  = ($riskWeights[$newRisk] ?? 0) >= 1;

        $updates = [];
        if ($ce_s > (float)$existing->ce_actual) {
            $updates['ce_actual'] = $ce_s;
        }
        if ($riskIncreased) {
            $updates['nivel']     = $newRisk;
            $updates['severidad'] = $newRisk;
        }
        if (!empty($updates)) {
            // ✅ CORRECCIÓN: Guardar timestamp de última notificación en notas_resolucion
            // para no confundir updated_at con el tiempo real de la última notificación
            $existing->update($updates);
        }

        // ✅ CORRECCIÓN: Usar tiempo_alerta (cuándo se creó la alerta) para el cooldown
        // en lugar de updated_at, que cambia con cada update de datos.
        $lastTelegramNotify = $existing->tiempo_alerta;
        $minutesSinceLast   = $lastTelegramNotify ? $lastTelegramNotify->diffInMinutes(now()) : 999;

        // Re-notificar si: el riesgo aumentó O han pasado 20+ minutos desde la última notificación
        if ($riskIncreased || ($riskPersists && $minutesSinceLast >= 20)) {
            try {
                $existing->load('ubicacion.planta');
                $telegram = resolve(\App\Services\TelegramService::class);
                $success  = $telegram->sendAlert($existing, true);

                if ($success) {
                    // ✅ Actualizar tiempo_alerta para reiniciar el contador del cooldown
                    $existing->update(['tiempo_alerta' => now()]);
                    Log::info('✅ Re-notificación Telegram enviada', [
                        'alert_id'   => $existing->id,
                        'ilx'        => $ilx,
                        'ilx_estado' => $ilx_estado,
                        'risk'       => $newRisk,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error en re-notificación Telegram: ' . $e->getMessage());
            }
        } else {
            Log::debug('LixiviationService: Re-notificación omitida (cooldown activo)', [
                'alert_id'          => $existing->id,
                'minutes_since_last' => $minutesSinceLast,
                'risk_increased'    => $riskIncreased,
            ]);
        }
    }

    private function classifyByILx(float $ilx): array
    {
        if ($ilx > 1.0) {
            return ['LIXIVIACIÓN ALTA', true, 'ALTO', 'lixiviacion_alta'];
        } elseif ($ilx >= 0.6) {
            return ['LIXIVIACIÓN MEDIA', true, 'MEDIO', 'lixiviacion_media'];
        } elseif ($ilx >= 0.4) {
            return ['EQUILIBRIO', false, 'BAJO', 'lixiviacion_baja'];
        } else {
            return ['LIXIVIACIÓN BAJA', true, 'BAJO', 'lixiviacion_baja'];
        }
    }

    private function calcRiskPct(float $ilx): float
    {
        if ($ilx > 1.0) {
            return min(100, ($ilx - 1) * 200);
        } elseif ($ilx >= 0.6) {
            return min(100, ($ilx - 0.6) * 250);
        }
        return 0;
    }
}