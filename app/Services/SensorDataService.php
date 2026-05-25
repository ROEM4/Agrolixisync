<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\SensorType;
use Carbon\Carbon;

class SensorDataService
{
    /**
     * Crear un nuevo tipo de sensor
     */
    public function createSensorType(array $data): SensorType
    {
        return SensorType::create($data);
    }

    /**
     * Crear una nueva ubicación
     */
    public function createLocation(array $data): Location
    {
        return Location::create($data);
    }

    /**
     * Crear un nuevo sensor
     */
    public function createSensor(array $data): Sensor
    {
        // Validar que no exista otro sensor con el mismo código
        if (Sensor::where('code', $data['code'])->exists()) {
            throw new \InvalidArgumentException(
                "Ya existe un sensor con el código: {$data['code']}"
            );
        }

        return Sensor::create($data);
    }

    /**
     * Crear lectura de sensor
     */
    public function recordReading(Sensor $sensor, array $data): Reading
    {
        $data['sensor_id'] = $sensor->id;

        // Si no viene recorded_at, usar ahora
        if (!isset($data['recorded_at'])) {
            $data['recorded_at'] = now();
        }

        $reading = Reading::create($data);

        // Actualizar last_reading_at del sensor
        $sensor->update(['last_reading_at' => $data['recorded_at']]);

        return $reading;
    }

    /**
     * Obtener estadísticas de un sensor
     */
    public function getSensorStatistics(Sensor $sensor, $days = 7): array
    {
        $startDate = now()->subDays($days);

        $readings = $sensor->readings()
            ->where('recorded_at', '>=', $startDate)
            ->get();

        if ($readings->isEmpty()) {
            return [
                'total_readings' => 0,
                'avg_temperature' => null,
                'avg_humidity' => null,
                'avg_conductivity' => null,
                'min_temperature' => null,
                'max_temperature' => null,
                'min_conductivity' => null,
                'max_conductivity' => null,
            ];
        }

        return [
            'total_readings' => $readings->count(),
            'avg_temperature' => $readings->avg('temperature'),
            'avg_humidity' => $readings->avg('humidity'),
            'avg_conductivity' => $readings->avg('conductivity'),
            'avg_soil_moisture' => $readings->avg('soil_moisture'),
            'min_temperature' => $readings->min('temperature'),
            'max_temperature' => $readings->max('temperature'),
            'min_humidity' => $readings->min('humidity'),
            'max_humidity' => $readings->max('humidity'),
            'min_conductivity' => $readings->min('conductivity'),
            'max_conductivity' => $readings->max('conductivity'),
            'last_reading' => $readings->sortByDesc('recorded_at')->first(),
        ];
    }

    /**
     * Obtener comparativa de dos sensores en un rango de tiempo
     */
    public function compareSensors(
        Sensor $sensor1,
        Sensor $sensor2,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $readings1 = $sensor1->readings()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->get();

        $readings2 = $sensor2->readings()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->get();

        return [
            'sensor1' => [
                'name' => $sensor1->name,
                'code' => $sensor1->code,
                'avg_temperature' => $readings1->avg('temperature'),
                'avg_humidity' => $readings1->avg('humidity'),
                'avg_conductivity' => $readings1->avg('conductivity'),
                'total_readings' => $readings1->count(),
            ],
            'sensor2' => [
                'name' => $sensor2->name,
                'code' => $sensor2->code,
                'avg_temperature' => $readings2->avg('temperature'),
                'avg_humidity' => $readings2->avg('humidity'),
                'avg_conductivity' => $readings2->avg('conductivity'),
                'total_readings' => $readings2->count(),
            ],
            'delta_conductivity_avg' => ($readings2->avg('conductivity') ?? 0) - ($readings1->avg('conductivity') ?? 0),
        ];
    }

    /**
     * Obtener hilo temporal de lecturas para una ubicación
     */
    public function getLocationTimeline(Location $location, $days = 7, $limit = 100): array
    {
        $startDate = now()->subDays($days);

        $sensors = $location->sensors()->get();
        $timeline = [];

        foreach ($sensors as $sensor) {
            $readings = $sensor->readings()
                ->where('recorded_at', '>=', $startDate)
                ->latest('recorded_at')
                ->limit($limit)
                ->get();

            $timeline[$sensor->code] = $readings->map(fn($r) => [
                'timestamp' => $r->recorded_at,
                'temperature' => $r->temperature,
                'humidity' => $r->humidity,
                'conductivity' => $r->conductivity,
            ])->toArray();
        }

        return $timeline;
    }

    /**
     * Verificar salud de los sensores
     */
    public function getSensorHealth(Location $location): array
    {
        $sensors = $location->sensors()->get();
        $health = [];

        foreach ($sensors as $sensor) {
            $lastReading = $sensor->lastReading;
            $isHealthy = true;
            $statusMessage = 'OK';

            if (!$lastReading) {
                $isHealthy = false;
                $statusMessage = 'Sin lecturas';
            } elseif ($lastReading->recorded_at->diffInMinutes(now()) > 60) {
                $isHealthy = false;
                $statusMessage = 'Sin datos recientes';
            }

            $health[] = [
                'sensor_id' => $sensor->id,
                'code' => $sensor->code,
                'name' => $sensor->name,
                'is_healthy' => $isHealthy,
                'status' => $statusMessage,
                'last_reading_at' => $lastReading?->recorded_at,
                'minutes_since_last' => $lastReading?->recorded_at->diffInMinutes(now()),
            ];
        }

        return $health;
    }

    /**
     * Obtener todos los sensores de un lote
     */
    public function getLoteSensors($lote)
    {
        $sensors = [];

        $locations = $lote->locations;

        foreach ($locations as $location) {
            $sensors = array_merge($sensors, $location->sensors->toArray());
        }

        return $sensors;
    }

    /**
     * Actualizar estado de sensor
     */
    public function updateSensorStatus(Sensor $sensor, string $status): void
    {
        $sensor->update(['status' => $status]);
    }

    /**
     * Desactivar sensor
     */
    public function deactivateSensor(Sensor $sensor): void
    {
        $sensor->update(['is_active' => false, 'status' => 'inactive']);
    }

    /**
     * Activar sensor
     */
    public function activateSensor(Sensor $sensor): void
    {
        $sensor->update(['is_active' => true, 'status' => 'active']);
    }

    /**
     * Limpiar lecturas antiguas (útil para mantenimiento)
     */
    public function deleteOldReadings($days = 365): int
    {
        $cutoffDate = now()->subDays($days);

        return Reading::where('recorded_at', '<', $cutoffDate)->delete();
    }
}
