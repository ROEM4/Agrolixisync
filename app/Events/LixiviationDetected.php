<?php

namespace App\Events;

use App\Models\Ubicacion;
use App\Models\AnalisisLixiviacion;
use App\Models\Alerta;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ══════════════════════════════════════════════════════════════
 * EVENT: LixiviationDetected
 *
 * Se dispara cuando se detecta LIXIVIACIÓN
 * (ILx > 1.20  O  ILx < 0.70)
 *
 * BROADCAST A:
 *   - Canal: ubicaciones.{ubicacion_id}
 *   - Audiencia: Usuarios autenticados viendo esa ubicación
 *
 * PAYLOAD:
 *   {
 *     "ubicacion":  { ubicacion },
 *     "analisis":   { analisis con ilx, delta, riesgo },
 *     "alerta":     { alerta creada },
 *     "severidad":  "ALTO/MEDIO/BAJO",
 *     "timestamp":  "ISO8601"
 *   }
 * ══════════════════════════════════════════════════════════════
 */
class LixiviationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ubicacion           $ubicacion,
        public AnalisisLixiviacion $analisis,
        public Alerta              $alerta
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
        $ceS = (float) $this->analisis->conductividad_superficial;
        $ceP = (float) $this->analisis->conductividad_profundo;

        return [
            'event_type' => 'LIXIVIATION_ALERT',
            'ubicacion'  => [
                'id'     => $this->ubicacion->id,
                'nombre' => $this->ubicacion->nombre,
            ],
            'analisis' => [
                'id'                     => $this->analisis->id,
                'conductividad_superficial' => $ceS,
                'conductividad_profundo'    => $ceP,
                'delta_conductividad'       => (float) $this->analisis->delta_conductividad,
                'ilx'                       => (float) $this->analisis->ilx,
                'ilx_estado'                => $this->analisis->ilx_estado,
                'nivel_riesgo'              => $this->analisis->nivel_riesgo,
                'porcentaje_riesgo'         => (float) $this->analisis->porcentaje_riesgo,
                'fecha_analisis'            => $this->analisis->fecha_analisis?->toIso8601String(),
            ],
            'alerta' => [
                'id'        => $this->alerta->id,
                'tipo'      => $this->alerta->tipo,
                'descripcion'=> $this->alerta->descripcion,
                'severidad' => $this->alerta->severidad,
                'estado'    => $this->alerta->estado,
            ],
            'severidad' => $this->analisis->nivel_riesgo,
            'icono'     => $this->getAlertIcon(),
            'color'     => $this->getAlertColor(),
            'sonido'    => $this->shouldPlaySound(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Nombre del evento broadcast.
     */
    public function broadcastAs(): string
    {
        return 'lixiviacion-detectada';
    }

    private function getAlertIcon(): string
    {
        return match ($this->analisis->nivel_riesgo) {
            'ALTO'   => '🔴',
            'MEDIO'  => '🟡',
            default  => '🟢',
        };
    }

    private function getAlertColor(): string
    {
        return match ($this->analisis->nivel_riesgo) {
            'ALTO'  => '#dc3545',
            'MEDIO' => '#ffc107',
            default => '#28a745',
        };
    }

    private function shouldPlaySound(): bool
    {
        return $this->analisis->nivel_riesgo === 'ALTO';
    }
}
