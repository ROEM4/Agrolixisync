<?php

namespace App\Events;

use App\Models\Location;
use App\Models\Analysis;
use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ══════════════════════════════════════════════════════════════
 * EVENT: LixiviationDetected
 * 
 * Se dispara cuando se detecta LIXIVIACIÓN
 * (Delta CE > umbral O Ratio > 1.2)
 * 
 * BROADCAST A:
 *   - Canal: locations.{location_id}
 *   - Audiencia: SOLO USUARIOS AUTENTICADOS (PrivateChannel)
 * 
 * PAYLOAD:
 *   {
 *     "location": { location },
 *     "analysis": { analysis con delta, ratio, risk },
 *     "alert": { alerta creada },
 *     "severity": "ALTO/MEDIO/BAJO",
 *     "timestamp": "ISO8601"
 *   }
 * 
 * NOTA: Los dashboards escuchando este canal recibirán
 *       actualización instantánea + pueden reproducir sonido
 * ══════════════════════════════════════════════════════════════
 */
class LixiviationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Location $location,
        public Analysis $analysis,
        public Alert $alert
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('locations.' . $this->location->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'LIXIVIATION_ALERT',
            'location' => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'coordinates' => [
                    'lat' => $this->location->latitude,
                    'lon' => $this->location->longitude,
                ],
            ],
            'analysis' => [
                'id' => $this->analysis->id,
                'ce_superficial' => (float)$this->analysis->conductivity_superficial,
                'ce_profundo' => (float)$this->analysis->conductivity_profundo,
                'delta_conductivity' => (float)$this->analysis->delta_conductivity,
                'threshold_used' => (float)$this->analysis->threshold_used,
                'ratio_ce' => ($this->analysis->conductivity_superficial > 0) ?
                    (float)($this->analysis->conductivity_profundo / $this->analysis->conductivity_superficial) :
                    0,
                'risk_level' => $this->analysis->risk_level,
                'risk_percentage' => (float)$this->analysis->risk_percentage,
                'analyzed_at' => $this->analysis->analyzed_at->toIso8601String(),
            ],
            'alert' => [
                'id' => $this->alert->id,
                'type' => $this->alert->type,
                'description' => $this->alert->description,
                'severity' => $this->alert->severity,
                'status' => $this->alert->status,
            ],
            'severity' => $this->analysis->risk_level,
            'icon' => $this->getAlertIcon(),
            'color' => $this->getAlertColor(),
            'sound' => $this->shouldPlaySound(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the event broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'lixiviation-detected';
    }

    /**
     * Get icon based on risk level
     */
    private function getAlertIcon(): string
    {
        return match ($this->analysis->risk_level) {
            'ALTO' => '🔴',
            'MEDIO' => '🟡',
            'BAJO' => '🟢',
            default => '⚪',
        };
    }

    /**
     * Get color based on risk level
     */
    private function getAlertColor(): string
    {
        return match ($this->analysis->risk_level) {
            'ALTO' => '#dc3545',      // Red
            'MEDIO' => '#ffc107',     // Yellow
            'BAJO' => '#28a745',      // Green
            default => '#6c757d',     // Gray
        };
    }

    /**
     * Should play sound alert
     */
    private function shouldPlaySound(): bool
    {
        // Solo reproducir sonido para ALTO riesgo
        return $this->analysis->risk_level === 'ALTO';
    }
}
