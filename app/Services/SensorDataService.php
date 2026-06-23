<?php

namespace App\Services;

use App\Models\Ubicacion;
use App\Models\Lectura;
use App\Models\Sensor;
use App\Models\Planta;
use Carbon\Carbon;

class SensorDataService
{
    /**
     * Crear una nueva ubicación
     */
    public function createLocation(array $data): Ubicacion
    {
        return Ubicacion::create($data);
    }

    /**
     * Crear un nuevo sensor
     */
    public function createSensor(array $data): Sensor
    {
        // Validar que no exista otro sensor con el mismo código
        if (Sensor::where('codigo', $data['codigo'])->exists()) {
            throw new \InvalidArgumentException(
                "Ya existe un sensor con el código: {$data['codigo']}"
            );
        }

        return Sensor::create($data);
    }

    /**
     * Crear lectura de sensor
     */
    public function recordReading(Sensor $sensor, array $data): Lectura
    {
        $data['sensor_id'] = $sensor->id;

        // Si no viene fecha_registro, usar ahora
        if (!isset($data['fecha_registro'])) {
            $data['fecha_registro'] = now();
        }

        $reading = Lectura::create($data);

        // Actualizar ultima_lectura del sensor
        $sensor->update(['ultima_lectura' => $data['fecha_registro']]);

        return $reading;
    }

    /**
     * Obtener estadísticas de un sensor
     */
    public function getSensorStatistics(Sensor $sensor, $days = 7): array
    {
        $startDate = now()->subDays($days);

        $readings = $sensor->lecturas()
            ->where('fecha_registro', '>=', $startDate)
            ->get();

        if ($readings->isEmpty()) {
            return [
                'total_lecturas'    => 0,
                'avg_temperatura'   => null,
                'avg_humedad'       => null,
                'avg_conductividad' => null,
                'min_temperatura'   => null,
                'max_temperatura'   => null,
                'min_conductividad' => null,
                'max_conductividad' => null,
            ];
        }

        return [
            'total_lecturas'    => $readings->count(),
            'avg_temperatura'   => $readings->avg('temperatura'),
            'avg_humedad'       => $readings->avg('humedad'),
            'avg_conductividad' => $readings->avg('conductividad'),
            'min_temperatura'   => $readings->min('temperatura'),
            'max_temperatura'   => $readings->max('temperatura'),
            'min_humedad'       => $readings->min('humedad'),
            'max_humedad'       => $readings->max('humedad'),
            'min_conductividad' => $readings->min('conductividad'),
            'max_conductividad' => $readings->max('conductividad'),
            'ultima_lectura'    => $readings->sortByDesc('fecha_registro')->first(),
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
        $readings1 = $sensor1->lecturas()
            ->whereBetween('fecha_registro', [$startDate, $endDate])
            ->get();

        $readings2 = $sensor2->lecturas()
            ->whereBetween('fecha_registro', [$startDate, $endDate])
            ->get();

        return [
            'sensor1' => [
                'nombre'            => $sensor1->nombre,
                'codigo'            => $sensor1->codigo,
                'avg_temperatura'   => $readings1->avg('temperatura'),
                'avg_humedad'       => $readings1->avg('humedad'),
                'avg_conductividad' => $readings1->avg('conductividad'),
                'total_lecturas'    => $readings1->count(),
            ],
            'sensor2' => [
                'nombre'            => $sensor2->nombre,
                'codigo'            => $sensor2->codigo,
                'avg_temperatura'   => $readings2->avg('temperatura'),
                'avg_humedad'       => $readings2->avg('humedad'),
                'avg_conductividad' => $readings2->avg('conductividad'),
                'total_lecturas'    => $readings2->count(),
            ],
            'delta_conductividad_avg' => ($readings2->avg('conductividad') ?? 0) - ($readings1->avg('conductividad') ?? 0),
        ];
    }

    /**
     * Obtener hilo temporal de lecturas para una ubicación
     */
    public function getLocationTimeline(Ubicacion $location, $days = 7, $limit = 100): array
    {
        $startDate = now()->subDays($days);

        $sensors = $location->sensores()->get();
        $timeline = [];

        foreach ($sensors as $sensor) {
            $readings = $sensor->lecturas()
                ->where('fecha_registro', '>=', $startDate)
                ->latest('fecha_registro')
                ->limit($limit)
                ->get();

            $timeline[$sensor->codigo] = $readings->map(fn($r) => [
                'timestamp'     => $r->fecha_registro,
                'temperatura'   => $r->temperatura,
                'humedad'       => $r->humedad,
                'conductividad' => $r->conductividad,
            ])->toArray();
        }

        return $timeline;
    }

    /**
     * Verificar salud de los sensores
     */
    public function getSensorHealth(Ubicacion $location): array
    {
        $sensors = $location->sensores()->get();
        $health = [];

        foreach ($sensors as $sensor) {
            $lastReading = $sensor->ultimaLectura;
            $isHealthy = true;
            $statusMessage = 'OK';

            if (!$lastReading) {
                $isHealthy = false;
                $statusMessage = 'Sin lecturas';
            } elseif ($lastReading->fecha_registro->diffInMinutes(now()) > 60) {
                $isHealthy = false;
                $statusMessage = 'Sin datos recientes';
            }

            $health[] = [
                'sensor_id' => $sensor->id,
                'code' => $sensor->codigo,
                'name' => $sensor->nombre,
                'is_healthy' => $isHealthy,
                'status' => $statusMessage,
                'last_reading_at' => $lastReading?->fecha_registro,
                'minutes_since_last' => $lastReading?->fecha_registro ? $lastReading->fecha_registro->diffInMinutes(now()) : null,
            ];
        }

        return $health;
    }

    /**
     * Obtener todos los sensores de una planta
     */
    public function getPlantaSensors(Planta $planta)
    {
        $sensors = [];

        $locations = $planta->ubicaciones;

        foreach ($locations as $location) {
            $sensors = array_merge($sensors, $location->sensores->toArray());
        }

        return $sensors;
    }

    /**
     * Actualizar estado de sensor
     */
    public function updateSensorStatus(Sensor $sensor, string $status): void
    {
        $sensor->update(['estado' => $status]);
    }

    /**
     * Desactivar sensor
     */
    public function deactivateSensor(Sensor $sensor): void
    {
        $sensor->update(['activo' => false, 'estado' => 'inactivo']);
    }

    /**
     * Activar sensor
     */
    public function activateSensor(Sensor $sensor): void
    {
        $sensor->update(['activo' => true, 'estado' => 'activo']);
    }

    /**
     * Limpiar lecturas antiguas (útil para mantenimiento)
     */
    public function deleteOldReadings($days = 365): int
    {
        $cutoffDate = now()->subDays($days);

        return Lectura::where('fecha_registro', '<', $cutoffDate)->delete();
    }
}
