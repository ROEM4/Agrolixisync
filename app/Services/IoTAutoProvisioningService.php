<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\Location;
use App\Models\Lote;
use Illuminate\Support\Facades\Log;

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * IoT Auto-Provisioning Service
 * 
 * Maneja el onboarding automático de nuevos dispositivos IoT (ESP32, etc.)
 * SIN requerir pre-registro manual en el sistema.
 * 
 * FLUJO:
 * 1. ESP32 envía datos con code="ESP32-LOTE-01"
 * 2. Servicio busca o crea Location para ese code
 * 3. Para cada sensor (superficial, profundo):
 *    - Busca o crea registro en tabla sensors
 * 4. Guarda readings asociadas
 * ════════════════════════════════════════════════════════════════════════════════
 */
class IoTAutoProvisioningService
{
    /**
     * Resolver o crear sensores para un dispositivo IoT
     * 
     * @param string $device_code  Código del dispositivo (ej: "ESP32-LOTE-01")
     * @return array  ['superficial' => Sensor, 'profundo' => Sensor]
     */
    public function resolveSensors(string $device_code): array
    {
        Log::info('🔧 IoT Auto-provisioning: Resolving sensors for device', [
            'device_code' => $device_code,
        ]);

        // STEP 1: Resolver Location
        $location = $this->resolveLocation($device_code);

        // STEP 2: Resolver Sensores
        $sensor_sup = $this->resolveSensor(
            code: $device_code . '-SUP',
            depth: 20,
            location: $location
        );

        $sensor_prof = $this->resolveSensor(
            code: $device_code . '-PROF',
            depth: 60,
            location: $location
        );

        Log::info('✅ Sensors resolved successfully', [
            'device_code' => $device_code,
            'location_id' => $location->id,
            'sensor_sup_id' => $sensor_sup->id,
            'sensor_prof_id' => $sensor_prof->id,
        ]);

        return [
            'superficial' => $sensor_sup,
            'profundo' => $sensor_prof,
        ];
    }

    /**
     * Resolver o crear una Location para un dispositivo
     * 
     * Estrategia:
     * 1. Buscar si existe location con name = device_code
     * 2. Si no, buscar lote con name = device_code (extraer parte del código)
     * 3. Si no, crear location + lote default
     * 
     * @param string $device_code
     * @return Location
     */
    private function resolveLocation(string $device_code): Location
    {
        // INTENTO 1: Buscar location por código
        $location = Location::where('name', $device_code)
            ->first();

        if ($location) {
            Log::info('📍 Location found by device code', [
                'device_code' => $device_code,
                'location_id' => $location->id,
            ]);
            return $location;
        }

        // INTENTO 2: Extraer lote del código y buscar
        // Ej: "ESP32-LOTE-01" → buscar lote con "LOTE-01" o similar
        $lote_hint = $this->extractLoteHint($device_code);
        $lote = Lote::whereRaw("name LIKE ?", ["%$lote_hint%"])
            ->first();

        if ($lote) {
            // Usar la primera location del lote, o crear una
            $location = $lote->locations()->first();
            if ($location) {
                Log::info('📍 Location found from lote', [
                    'device_code' => $device_code,
                    'lote_id' => $lote->id,
                    'location_id' => $location->id,
                ]);
                return $location;
            }
        }

        // INTENTO 3: Crear Location + Lote default
        Log::info('🆕 Creating default location for device', [
            'device_code' => $device_code,
        ]);

        // Crear lote si no existe
        if (!$lote) {
            $lote = Lote::firstOrCreate(
                ['name' => "Auto - $device_code"],
                [
                    'crop_type' => 'palta',
                    'user_id' => 1, // Admin por defecto
                ]
            );
            Log::info('✨ Default lote created', [
                'lote_id' => $lote->id,
                'name' => $lote->name,
            ]);
        }

        // Crear location
        $location = Location::create([
            'lote_id' => $lote->id,
            'name' => $device_code,
            'latitude' => -25.2637,  // Default Paraguay
            'longitude' => -57.5759,
            'is_active' => true,
        ]);

        Log::info('✨ Default location created', [
            'location_id' => $location->id,
            'lote_id' => $lote->id,
        ]);

        return $location;
    }

    /**
     * Resolver o crear un Sensor específico
     * 
     * @param string $code
     * @param float $depth (20 = superficial, 60 = profundo)
     * @param Location $location
     * @param string $sensor_type_name
     * @return Sensor
     */
    private function resolveSensor(
        string $code,
        float $depth,
        Location $location
    ): Sensor {
        $sensor = Sensor::where('location_id', $location->id)
            ->where('depth', $depth)
            ->first();

        if ($sensor) {
            return $sensor;
        }

        Log::info('🆕 Creating new sensor', ['code' => $code, 'depth' => $depth]);

        return Sensor::create([
            'code'        => $code,
            'name'        => $this->generateSensorName($code, $depth),
            'location_id' => $location->id,
            'depth'       => $depth,
            'group_type'  => 'EXPERIMENTAL',
            'is_active'   => true,
            'status'      => 'active',
            'notes'       => 'Auto-provisioned on ' . now()->toDateTimeString(),
        ]);
    }

    /**
     * Generar nombre descriptivo para el sensor
     */
    private function generateSensorName(string $code, float $depth): string
    {
        $depth_label = $depth == 20 ? 'Superficial (20cm)' : "Profundo ({$depth}cm)";
        return "{$code} - {$depth_label}";
    }

    /**
     * Extraer hint de lote del código del dispositivo
     * Ej: "ESP32-LOTE-01" → "LOTE-01"
     */
    private function extractLoteHint(string $device_code): string
    {
        // Intentar extraer entre guiones
        if (preg_match('/LOTE[_-]?(\d+)/i', $device_code, $matches)) {
            return $matches[0];
        }

        // Si no encuentra LOTE, devolver el código completo
        return $device_code;
    }

    /**
     * Validar que los datos del sensor estén en rango válido
     */
    public function validateSensorData(array $data): array
    {
        $errors = [];

        $ranges = [
            'humidity' => ['min' => 0, 'max' => 100],
            'temperature' => ['min' => -50, 'max' => 80],
            'conductivity' => ['min' => 50, 'max' => 5000],
        ];

        foreach (['superficial', 'profundo'] as $level) {
            if (!isset($data[$level])) continue;

            foreach ($ranges as $param => $range) {
                $value = $data[$level][$param] ?? null;
                if ($value !== null && ($value < $range['min'] || $value > $range['max'])) {
                    $errors[] = "{$level}.{$param} out of range ({$range['min']}-{$range['max']})";
                }
            }
        }

        return $errors;
    }
}
