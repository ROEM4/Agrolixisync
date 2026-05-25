<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alert;
use App\Models\Observacion;
use App\Models\Analysis;
use Illuminate\Support\Facades\Log;

class AnalisisService
{
    /**
     * Evalúa las alertas abiertas cuando los valores regresan a la normalidad.
     * Calcula la persistencia del evento y clasifica como VP o FP.
     */
    public function evaluateOpenAlerts(int $location_id, float $current_ce_s, Analysis $current_analysis): void
    {
        $open_alerts = Alert::where('location_id', $location_id)
            ->where('status', 'OPEN')
            ->get();

        foreach ($open_alerts as $alert) {
            $alert->resolve('Valores regresaron a la normalidad.');
            $alert->status = 'CLOSED';
            $alert->save();
            
            // Si no hay tiempo_riesgo, asume el tiempo en que se detectó la alerta
            $tiempo_inicio = $alert->tiempo_riesgo ?? clone $alert->created_at;
            
            // El fin del evento es el momento del análisis actual
            $tiempo_fin = $current_analysis->event_detected_at ?? now();
            
            // Calcular duración en minutos
            $duration_minutes = $tiempo_fin->diffInMinutes($tiempo_inicio);
            
            // Persistencia mínima para ser un Verdadero Positivo (VP)
            // Se puede configurar en agrolixisync.php, por defecto 10 minutos
            $persistence_threshold = config('agrolixisync.persistence_minutes', 10);
            
            $resultado = $duration_minutes >= $persistence_threshold ? 'VP' : 'FP';
            
            $location = $alert->location;

            Observacion::create([
                'location_id'        => $location_id,
                'experimental_group' => $location ? $location->experimental_group : 'experimental',
                'alert_id'           => $alert->id,
                'ce_real'            => $current_ce_s,
                'diagnostico'        => $alert->type ?? 'LIXIVIACION',
                'resultado'          => $resultado,
            ]);

            Log::info("Observación creada automáticamente ({$resultado})", [
                'alert_id' => $alert->id,
                'duration' => $duration_minutes,
                'threshold' => $persistence_threshold
            ]);
        }
    }

    /**
     * Calcula las estadísticas del PDS.
     */
    public function getPdsStats(int $location_id = null): array
    {
        $q = Observacion::query();
        if ($location_id) {
            $q->where('location_id', $location_id);
        }
        
        $vp = (clone $q)->where('resultado', 'VP')->count();
        $fp = (clone $q)->where('resultado', 'FP')->count();
        $fn = (clone $q)->where('resultado', 'FN')->count();
        $total = $vp + $fp + $fn;
        
        $pds = $total > 0 ? ($vp / $total) * 100 : 0;
        
        // Tiempo promedio de respuesta (TAR)
        $avg_time = 0;
        $observations = (clone $q)->with('alert')->get();
        if ($observations->count() > 0) {
            $total_minutes = $observations->sum(function($obs) {
                if ($obs->alert && $obs->alert->resolved_at) {
                    return $obs->alert->resolved_at->diffInMinutes($obs->alert->created_at);
                }
                return 0;
            });
            $avg_time = $total_minutes / $observations->count();
        }

        return [
            'vp' => $vp,
            'fp' => $fp,
            'fn' => $fn,
            'total' => $total,
            'pds_percentage' => round($pds, 2),
            'vp_percentage' => $total > 0 ? round(($vp / $total) * 100, 2) : 0,
            'fp_percentage' => $total > 0 ? round(($fp / $total) * 100, 2) : 0,
            'avg_response_time' => round($avg_time, 2)
        ];
    }

    /**
     * Compara Grupo Control vs Experimental para tesis
     */
    public function getComparisonStats(): array
    {
        $stats = [
            'control' => [
                'count' => \App\Models\PFRecord::where('experimental_group', 'control')->count(),
                'avg_ilx' => \App\Models\PFRecord::where('experimental_group', 'control')->avg('ce_measured'),
                'avg_pf' => \App\Models\PFRecord::where('experimental_group', 'control')->avg('pf_percentage'),
            ],
            'experimental' => [
                'count' => \App\Models\PFRecord::where('experimental_group', 'experimental')->count(),
                'avg_ilx' => \App\Models\PFRecord::where('experimental_group', 'experimental')->avg('ce_measured'),
                'avg_pf' => \App\Models\PFRecord::where('experimental_group', 'experimental')->avg('pf_percentage'),
            ]
        ];

        // Calcular Eficiencia del sistema
        // Eficiencia = ((PF_control - PF_exp) / PF_control) * 100
        $efficiency = 0;
        if ($stats['control']['avg_pf'] > 0) {
            $efficiency = (($stats['control']['avg_pf'] - $stats['experimental']['avg_pf']) / $stats['control']['avg_pf']) * 100;
        }
        $stats['efficiency'] = round($efficiency, 2);

        return $stats;
    }
}
