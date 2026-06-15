<?php

namespace App\Modules\SensorRealtime;

/**
 * SensorPayloadDTO
 *
 * Representa el payload normalizado del ESP32 después de pasar por
 * la capa de normalización. Todos los formatos legacy (v1/v2/v3) se
 * convierten a este DTO antes de cualquier lógica de negocio.
 *
 * Contrato v3 del firmware:
 * { device, ts, ce_s, ce_p, hum_s?, hum_p?, temp_s?, temp_p? }
 */
final class SensorPayloadDTO
{
    public function __construct(
        public readonly string  $device,
        public readonly string  $ts,
        public readonly float   $ce_s,
        public readonly float   $ce_p,
        public readonly ?float  $hum_s    = null,
        public readonly ?float  $hum_p    = null,
        public readonly ?float  $temp_s   = null,
        public readonly ?float  $temp_p   = null,
        public readonly int     $riesgo   = 0,
        public readonly string  $estado   = 'EQUILIBRIO',
    ) {}

    /**
     * Construye el DTO desde el array validado del Request.
     */
    public static function fromValidated(array $v): self
    {
        return new self(
            device: $v['device'],
            ts:     $v['ts'],
            ce_s:   (float) $v['ce_s'],
            ce_p:   (float) $v['ce_p'],
            hum_s:  isset($v['hum_s'])  ? (float) $v['hum_s']  : null,
            hum_p:  isset($v['hum_p'])  ? (float) $v['hum_p']  : null,
            temp_s: isset($v['temp_s']) ? (float) $v['temp_s'] : null,
            temp_p: isset($v['temp_p']) ? (float) $v['temp_p'] : null,
            riesgo: (int) ($v['riesgo'] ?? 0),
            estado: $v['estado'] ?? 'EQUILIBRIO',
        );
    }
}