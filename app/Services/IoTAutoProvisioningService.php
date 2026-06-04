<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\Location;
use App\Models\Lote;
use Illuminate\Support\Facades\Log;

class IoTAutoProvisioningService
{
    public function resolveSensors(string $device_code): array
    {
        Log::info('IoT provisioning', ['device_code' => $device_code]);

        // 🔥 NORMALIZACIÓN DEL DEVICE CODE
        $device_code = $this->cleanDevice($device_code);

        // 1. Resolver Location
        $location = $this->resolveLocation($device_code);

        // 2. Sensores
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

        return [
            'superficial' => $sensor_sup,
            'profundo' => $sensor_prof,
        ];
    }

    /**
     * LOCATION CLEAN (SIN BASURA DE AUTO-ESP32)
     */
    private function resolveLocation(string $device_code): Location
    {
        $device_code = $this->cleanDevice($device_code);

        // Buscar por device limpio
        $location = Location::where('device_code', $device_code)->first();

        if ($location) {
            return $location;
        }

        // Crear LOTE base (limpio)
        $lote = Lote::firstOrCreate(
            ['name' => $this->generateLoteName($device_code)],
            [
                'crop_type' => 'palta',
                'user_id' => 1,
            ]
        );

        // Crear LOCATION limpia (ESTO ES LO IMPORTANTE)
        $location = Location::create([
            'lote_id' => $lote->id,

            // 🔥 NOMBRE BONITO PARA UI (SELECT)
            'name' => $this->generateLocationName($device_code),

            // 🔥 GUARDAR DEVICE REAL SEPARADO (RECOMENDADO)
            'device_code' => $device_code,

            'latitude' => -25.2637,
            'longitude' => -57.5759,
            'is_active' => true,
        ]);

        Log::info('Location created clean', [
            'location_id' => $location->id,
            'name' => $location->name,
        ]);

        return $location;
    }

    /**
     * SENSOR CREATION
     */
    private function resolveSensor(string $code, float $depth, Location $location): Sensor
    {
        $sensor = Sensor::where('location_id', $location->id)
            ->where('depth', $depth)
            ->first();

        if ($sensor) {
            return $sensor;
        }

        return Sensor::create([
            'code' => $code,
            'name' => $this->generateSensorName($location->name, $depth),
            'location_id' => $location->id,
            'depth' => $depth,
            'group_type' => 'EXPERIMENTAL',
            'is_active' => true,
            'status' => 'active',
            'notes' => 'Auto-provisioned',
        ]);
    }

    /**
     * NOMBRE SENSOR LIMPIO
     */
    private function generateSensorName(string $location_name, float $depth): string
    {
        return $depth == 20
            ? "{$location_name} - Superficial (20cm)"
            : "{$location_name} - Profundo (60cm)";
    }

    /**
     * NOMBRE LOTE LIMPIO
     */
    private function generateLoteName(string $device_code): string
    {
        $clean = $this->cleanDevice($device_code);
        return $clean;
    }

    /**
     * NOMBRE LOCATION BONITO PARA UI (SELECT)
     */
    private function generateLocationName(string $device_code): string
    {
        $clean = $this->cleanDevice($device_code);

        return match (true) {
            str_contains($clean, 'PALTO') => 'Planta de Palto - GE',
            default => $clean,
        };
    }

    /**
     * LIMPIEZA ROBUSTA DE DEVICE
     */
    private function cleanDevice(string $device): string
    {
        $device = preg_replace('/^Auto-[^-]+--/i', '', $device);
        $device = str_replace(['Auto-', 'ESP32-'], '', $device);
        $device = preg_replace('/-+/', '-', $device);

        return trim($device, '- ');
    }
}