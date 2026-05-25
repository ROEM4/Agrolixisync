<?php

namespace App\Modules\SensorRealtime;

use Illuminate\Http\Request;

/**
 * NormalizerService
 *
 * Responsabilidad única: convertir cualquier formato de payload del ESP32
 * al contrato v3 canónico antes de la validación de Laravel.
 *
 * Formatos soportados (zero-downtime, todos coexisten):
 *   v1 legacy  → {parcela, ce_s, ce_p, dce, estado, riesgo}
 *   v2 nuevo   → {codigo, fecha, superficial.ce_dS_m, profundo.ce_dS_m}
 *   v2 estándar→ {code, timestamp, sensors.superficial.conductivity}
 *   v3 actual  → {device, ts, ce_s, ce_p, hum_s, hum_p, temp_s, temp_p}
 */
class NormalizerService
{
    public function normalize(Request $request): Request
    {
        $b = $request->all();

        // device
        if (empty($b['device'])) {
            $b['device'] = $b['code']
                ?? $b['codigo']
                ?? (isset($b['parcela']) ? 'ESP32-' . strtoupper(trim($b['parcela'])) : null);
        }

        // ts
        if (empty($b['ts'])) {
            $b['ts'] = $b['timestamp'] ?? $b['fecha'] ?? now()->toIso8601String();
        }

        // ce_s
        if (!isset($b['ce_s'])) {
            $b['ce_s'] = $b['sensors']['superficial']['conductivity']
                ?? $b['superficial']['ce_dS_m']
                ?? null;
        }

        // ce_p
        if (!isset($b['ce_p'])) {
            $b['ce_p'] = $b['sensors']['profundo']['conductivity']
                ?? $b['profundo']['ce_dS_m']
                ?? null;
        }

        // hum_s / hum_p
        if (!isset($b['hum_s'])) {
            $b['hum_s'] = $b['sensors']['superficial']['humidity']
                ?? $b['superficial']['humedad']
                ?? null;
        }
        if (!isset($b['hum_p'])) {
            $b['hum_p'] = $b['sensors']['profundo']['humidity']
                ?? $b['profundo']['humedad']
                ?? null;
        }

        // temp_s / temp_p
        if (!isset($b['temp_s'])) {
            $b['temp_s'] = $b['sensors']['superficial']['temperature']
                ?? $b['superficial']['temperatura']
                ?? null;
        }
        if (!isset($b['temp_p'])) {
            $b['temp_p'] = $b['sensors']['profundo']['temperature']
                ?? $b['profundo']['temperatura']
                ?? null;
        }

        $request->replace($b);
        return $request;
    }

    public function rules(): array
    {
        return [
            'device' => 'required|string|max:64',
            'ts'     => 'required|date',
            'ce_s'   => 'required|numeric|min:0|max:100',
            'ce_p'   => 'required|numeric|min:0|max:100',
            'hum_s'  => 'nullable|numeric|between:0,100',
            'hum_p'  => 'nullable|numeric|between:0,100',
            'temp_s' => 'nullable|numeric|between:-50,80',
            'temp_p' => 'nullable|numeric|between:-50,80',
            'riesgo' => 'nullable|integer|between:0,2',
            'estado' => 'nullable|string|max:50',
        ];
    }
}
