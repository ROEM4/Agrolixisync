<?php

namespace App\Events;

use App\Models\Reading;
use App\Models\Location;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ══════════════════════════════════════════════════════════════
 * EVENT: ReadingCreated
 * 
 * Se dispara cuando se reciben 2 nuevas lecturas (SUP + PROF)
 * 
 * BROADCAST A:
 *   - Canal: locations.{location_id}
 *   - Audiencia: Todos los usuarios viend o ese lote
 * 
 * PAYLOAD:
 *   {
 *     "superficial": { reading },
 *     "profundo": { reading },
 *     "location": { location },
 *     "timestamp": "ISO8601"
 *   }
 * ══════════════════════════════════════════════════════════════
 */
class ReadingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Reading $readingSuperficial,
        public Reading $readingProfundo,
        public Location $location
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
            'superficial' => [
                'id' => $this->readingSuperficial->id,
                'sensor_id' => $this->readingSuperficial->sensor_id,
                'conductivity' => (float)$this->readingSuperficial->conductivity,
                'humidity' => (float)$this->readingSuperficial->humidity,
                'temperature' => (float)$this->readingSuperficial->temperature,
                'recorded_at' => $this->readingSuperficial->recorded_at->toIso8601String(),
                'depth' => 0,
            ],
            'profundo' => [
                'id' => $this->readingProfundo->id,
                'sensor_id' => $this->readingProfundo->sensor_id,
                'conductivity' => (float)$this->readingProfundo->conductivity,
                'humidity' => (float)$this->readingProfundo->humidity,
                'temperature' => (float)$this->readingProfundo->temperature,
                'recorded_at' => $this->readingProfundo->recorded_at->toIso8601String(),
                'depth' => 20,
            ],
            'location' => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'reading-created';
    }
}
