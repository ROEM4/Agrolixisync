<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Alerta;
use App\Models\TiempoDeteccion;
use Carbon\Carbon;

class CalcularTiempoDeteccion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alerta;

    public function __construct(Alerta $alerta)
    {
        $this->alerta = $alerta;
    }

    public function handle()
    {
        $alerta = $this->alerta->fresh(['evaluacion']);

        // Solo procesar si es VP
        if (!$alerta->evaluacion || $alerta->evaluacion->etiqueta !== 'VP') {
            return;
        }

        if (!$alerta->tiempo_alerta) {
            return;
        }

        $fecha = $alerta->tiempo_alerta->format('Y-m-d');
        $ubicacionId = $alerta->ubicacion_id;
        $plantaId = $alerta->planta_id;

        // ✅ CORRECCIÓN: Calcular TAR correctamente
        // TAR = tiempo desde la alerta hasta que se resolvió
        if ($alerta->fecha_resolucion && $alerta->fecha_resolucion->gt($alerta->tiempo_alerta)) {
            // Si tiene fecha_resolución, usar esa
            $diferencia = $alerta->fecha_resolucion->diffInSeconds($alerta->tiempo_alerta);
        } elseif ($alerta->tiempo_riesgo && $alerta->tiempo_riesgo->ne($alerta->tiempo_alerta)) {
            // Si no tiene fecha_resolución, usar tiempo_riesgo si es distinto
            $diferencia = abs($alerta->tiempo_riesgo->diffInSeconds($alerta->tiempo_alerta));
        } else {
            // Sin tiempo medible (tar=0), igual registrar el evento como VP pero con tiempo=0
            $diferencia = 0;
        }

        // ✅ Actualizar el campo tar en la alerta con el valor real
        $alerta->update(['tar' => $diferencia]);

        $tiempoDeteccion = TiempoDeteccion::firstOrCreate(
            ['fecha' => $fecha, 'ubicacion_id' => $ubicacionId],
            [
                'planta_id' => $plantaId,
                'tiempo_promedio_segundos' => 0,
                'cantidad_eventos' => 0,
                'suma_tiempos_segundos' => 0,
                'tipo_entrada' => 'automatico',
                'subparcela' => $alerta->subparcela,
            ]
        );

        $tiempoDeteccion->cantidad_eventos += 1;
        $tiempoDeteccion->suma_tiempos_segundos += $diferencia;
        $tiempoDeteccion->tiempo_promedio_segundos = round(
            $tiempoDeteccion->suma_tiempos_segundos / $tiempoDeteccion->cantidad_eventos,
            2
        );
        $tiempoDeteccion->save();
    }
}