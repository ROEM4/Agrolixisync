<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\Ubicacion;
use App\Models\Planta;
use Illuminate\Support\Facades\Log;

class IoTAutoProvisioningService
{
    public function resolveSensors(string $device_code): array
    {
        Log::info('IoT provisioning', ['device_code' => $device_code]);

        // 🔥 NORMALIZACIÓN DEL DEVICE CODE
        $device_code = $this->cleanDevice($device_code);

        // 1. Resolver Ubicacion
        $location = $this->resolveLocation($device_code);

        // 2. Sensores
        $sensor_sup = $this->resolveSensor(
            code: $device_code . '-SUP',
            depth: 20.0,
            location: $location
        );

        $sensor_prof = $this->resolveSensor(
            code: $device_code . '-PROF',
            depth: 60.0,
            location: $location
        );

        return [
            'superficial' => $sensor_sup,
            'profundo' => $sensor_prof,
        ];
    }

    /**
     * UBICACIÓN — solo busca por codigo_dispositivo, no crea
     */
    private function resolveLocation(string $device_code): Ubicacion
    {
        $device_code = $this->cleanDevice($device_code);

        $location = Ubicacion::where('codigo_dispositivo', $device_code)->first();

        if (!$location) {
            throw new \RuntimeException(
                "Device code '{$device_code}' no está registrado en ninguna planta. "
                . "Regístralo primero en el módulo Plantas (Grupo Experimental)."
            );
        }

        return $location;
    }

    /**
     * SENSOR CREATION
     */
    private function resolveSensor(string $code, float $depth, Ubicacion $location): Sensor
    {
        $sensor = Sensor::where('ubicacion_id', $location->id)
            ->where('profundidad', $depth)
            ->first();

        if ($sensor) {
            return $sensor;
        }

        return Sensor::create([
            'codigo' => $code,
            'nombre' => $this->generateSensorName($location->nombre, $depth),
            'ubicacion_id' => $location->id,
            'profundidad' => $depth,
            'tipo_grupo' => 'EXPERIMENTAL',
            'activo' => true,
            'estado' => 'activo',
            'notas' => 'Auto-provisioned',
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
     * NOMBRE PLANTA LIMPIO
     */
    private function generatePlantaNombre(string $device_code): string
    {
        $clean = $this->cleanDevice($device_code);
        return $clean;
    }

    /**
     * NOMBRE UBICACIÓN BONITO PARA UI (SELECT)
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