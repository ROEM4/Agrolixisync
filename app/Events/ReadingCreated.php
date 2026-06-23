<?php

namespace App\Events;

use App\Models\Lectura;
use App\Models\Ubicacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ══════════════════════════════════════════════════════════════
 * EVENT: LecturaCreada
 *
 * Se dispara cuando se reciben 2 nuevas lecturas (SUP + PROF)
 *
 * BROADCAST A:
 *   - Canal: ubicaciones.{ubicacion_id}
 *   - Audiencia: Todos los usuarios viendo esa ubicación
 *
 * PAYLOAD:
 *   {
 *     "superficial": { lectura },
 *     "profundo":    { lectura },
 *     "ubicacion":   { ubicacion },
 *     "timestamp":   "ISO8601"
 *   }
 * ══════════════════════════════════════════════════════════════
 */
class ReadingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Lectura   $lecturaSuperficial,
        public Lectura   $lecturaProfundo,
        public Ubicacion $ubicacion
    ) {}

    /**
     * Canal de broadcast.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ubicaciones.' . $this->ubicacion->id),
        ];
    }

    /**
     * Datos que se envían al canal.
     */
    public function broadcastWith(): array
    {
        return [
            'superficial' => [
                'id'           => $this->lecturaSuperficial->id,
                'sensor_id'    => $this->lecturaSuperficial->sensor_id,
                'conductividad'=> (float) $this->lecturaSuperficial->conductividad,
                'humedad'      => (float) $this->lecturaSuperficial->humedad,
                'temperatura'  => (float) $this->lecturaSuperficial->temperatura,
                'fecha_registro'=> $this->lecturaSuperficial->fecha_registro?->toIso8601String(),
                'profundidad'  => 20,
            ],
            'profundo' => [
                'id'           => $this->lecturaProfundo->id,
                'sensor_id'    => $this->lecturaProfundo->sensor_id,
                'conductividad'=> (float) $this->lecturaProfundo->conductividad,
                'humedad'      => (float) $this->lecturaProfundo->humedad,
                'temperatura'  => (float) $this->lecturaProfundo->temperatura,
                'fecha_registro'=> $this->lecturaProfundo->fecha_registro?->toIso8601String(),
                'profundidad'  => 60,
            ],
            'ubicacion' => [
                'id'     => $this->ubicacion->id,
                'nombre' => $this->ubicacion->nombre,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Nombre del evento broadcast.
     */
    public function broadcastAs(): string
    {
        return 'lectura-creada';
    }
}
