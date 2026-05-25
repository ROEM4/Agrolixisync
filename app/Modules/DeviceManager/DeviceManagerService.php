<?php

namespace App\Modules\DeviceManager;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * DeviceManagerService
 *
 * Responsabilidad: gestionar el estado de los dispositivos ESP32.
 * Registra heartbeats en device_logs y detecta dispositivos offline.
 *
 * Se llama desde IngestionService en cada recepción exitosa de datos.
 * No bloquea la respuesta al ESP32 (se puede despachar como job si escala).
 */
class DeviceManagerService
{
    /**
     * Registra un heartbeat cuando el ESP32 envía datos correctamente.
     */
    public function heartbeat(string $device_code, int $location_id, array $meta = []): void
    {
        DB::table('device_logs')->insert([
            'device_code'      => $device_code,
            'location_id'      => $location_id,
            'event_type'       => 'HEARTBEAT',
            'message'          => 'Data received OK',
            'queue_size'       => $meta['queue_size']   ?? null,
            'sent_ok_count'    => $meta['sent_ok']      ?? null,
            'sent_fail_count'  => $meta['sent_fail']    ?? null,
            'logged_at'        => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Registra un evento genérico (BOOT, WIFI_LOST, SENSOR_ERROR, etc.)
     */
    public function log(string $device_code, string $event_type, string $message, ?int $location_id = null): void
    {
        DB::table('device_logs')->insert([
            'device_code' => $device_code,
            'location_id' => $location_id,
            'event_type'  => $event_type,
            'message'     => $message,
            'logged_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Devuelve el estado actual de todos los dispositivos de una location.
     * Un dispositivo se considera OFFLINE si no envió datos en los últimos 15 min.
     */
    public function getDeviceStatus(int $location_id): array
    {
        $devices = DB::table('device_logs')
            ->where('location_id', $location_id)
            ->where('event_type', 'HEARTBEAT')
            ->select('device_code', DB::raw('MAX(logged_at) as last_seen'))
            ->groupBy('device_code')
            ->get();

        return $devices->map(function ($d) {
            $last   = Carbon::parse($d->last_seen);
            $minAgo = $last->diffInMinutes(now());
            return [
                'device_code' => $d->device_code,
                'last_seen'   => $last->toIso8601String(),
                'minutes_ago' => $minAgo,
                'status'      => $minAgo <= 15 ? 'ONLINE' : ($minAgo <= 60 ? 'STALE' : 'OFFLINE'),
            ];
        })->values()->toArray();
    }

    /**
     * Últimos N eventos de un dispositivo (para diagnóstico).
     */
    public function getRecentLogs(string $device_code, int $limit = 20): array
    {
        return DB::table('device_logs')
            ->where('device_code', $device_code)
            ->orderByDesc('logged_at')
            ->limit($limit)
            ->get(['event_type', 'message', 'queue_size', 'logged_at'])
            ->toArray();
    }
}
