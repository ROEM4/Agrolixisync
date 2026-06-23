<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alerta;
use App\Models\ObservacionCampo;
use App\Models\AnalisisLixiviacion;
use App\Models\RegistroPorcentajePerdida;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalisisService
{
    /**
     * ============================================================
     * 1. EVALUACIÓN DE ALERTAS (VP / FP)
     * ============================================================
     */
    public function evaluateOpenAlerts(int $ubicacion_id, float $current_ce_s, AnalisisLixiviacion $current_analysis): void
    {
        $open_alerts = Alerta::where('ubicacion_id', $ubicacion_id)
            ->where('estado', 'ABIERTA')
            ->get();

        foreach ($open_alerts as $alert) {
            $alert->resuelta = true;
            $alert->fecha_resolucion = now();
            $alert->notas_resolucion = 'Valores regresaron a la normalidad.';
            $alert->estado = 'RESUELTA';
            $alert->save();

            $start = $alert->tiempo_riesgo ?? $alert->created_at;
            $end = $current_analysis->fecha_deteccion ?? now();

            $duration = $end->diffInMinutes($start);

            $threshold = config('agrolixisync.persistence_minutes', 10);

            // Clasificación básica del evento
            $resultado = $duration >= $threshold ? 'VP' : 'FP';

            ObservacionCampo::create([
                'ubicacion_id'       => $ubicacion_id,
                'grupo_experimental' => 'experimental',
                'alerta_id'          => $alert->id,
                'ce_real'            => $current_ce_s,
                'diagnostico'        => $alert->tipo ?? 'LIXIVIACION',
                'resultado'          => $resultado,
            ]);

            Log::info("Observación registrada", [
                'resultado' => $resultado,
                'duration' => $duration,
                'threshold' => $threshold
            ]);
        }
    }

    /**
     * ============================================================
     * 2. MÉTRICAS PRINCIPALES DEL SISTEMA (PDS)
     * ============================================================
     * PDS = VP / (VP + FP + FN)
     */
    public function getPdsStats(int $ubicacion_id = null): array
    {
        $q = ObservacionCampo::query();

        if ($ubicacion_id) {
            $q->where('ubicacion_id', $ubicacion_id);
        }

        $vp = (clone $q)->where('resultado', 'VP')->count();
        $fp = (clone $q)->where('resultado', 'FP')->count();
        $fn = (clone $q)->where('resultado', 'FN')->count();
        $vn = (clone $q)->where('resultado', 'VN')->count();

        // =========================
        // PDS (MÉTRICA PRINCIPAL)
        // =========================
        $pds_div = $vp + $fp + $fn;
        $pds = $pds_div > 0 ? ($vp / $pds_div) * 100 : 0;

        // =========================
        // MÉTRICAS SECUNDARIAS
        // =========================

        // Recall (sensibilidad)
        $recall_div = $vp + $fn;
        $recall = $recall_div > 0 ? ($vp / $recall_div) * 100 : 0;

        // Accuracy (incluye VN, pero NO afecta PDS)
        $total = $vp + $fp + $fn + $vn;
        $accuracy = $total > 0 ? (($vp + $vn) / $total) * 100 : 0;

        // Error rate
        $error_rate = $total > 0 ? (($fp + $fn) / $total) * 100 : 0;

        return [
            'vp' => $vp,
            'fp' => $fp,
            'fn' => $fn,
            'vn' => $vn,

            // MÉTRICA PRINCIPAL
            'pds_percentage' => round($pds, 2),

            // MÉTRICAS SECUNDARIAS
            'recall' => round($recall, 2),
            'accuracy' => round($accuracy, 2),
            'error_rate' => round($error_rate, 2),
        ];
    }

    /**
     * ============================================================
     * 3. COMPARACIÓN CONTROL vs EXPERIMENTAL
     * ============================================================
     */
    public function getComparisonStats(?int $ubicacion_id = null): array
    {
        $control = RegistroPorcentajePerdida::where('grupo_experimental', 'control');
        $exp = RegistroPorcentajePerdida::where('grupo_experimental', 'experimental');

        if ($ubicacion_id) {
            $control->where('ubicacion_id', $ubicacion_id);
            $exp->where('ubicacion_id', $ubicacion_id);
        }

        $control_count = $control->count();
        $exp_count = $exp->count();

        $control_pf = (clone $control)->avg('porcentaje_pf') ?? 0;
        $exp_pf = (clone $exp)->avg('porcentaje_pf') ?? 0;

        return [
            'control' => [
                'count' => $control_count,
                'avg_pf' => round($control_pf, 2),
                'avg_ce_sup' => round((float)$control->avg('ce_superficial'), 3),
                'avg_ce_prof' => round((float)$control->avg('ce_profunda'), 3),
            ],
            'experimental' => [
                'count' => $exp_count,
                'avg_pf' => round($exp_pf, 2),
                'avg_ce_sup' => round((float)$exp->avg('ce_superficial'), 3),
                'avg_ce_prof' => round((float)$exp->avg('ce_profunda'), 3),
            ]
        ];
    }

    /**
     * ============================================================
     * 4. ANÁLISIS DIARIO (VP/FP/FN/VN + PDS CORRECTO)
     * ============================================================
     */
    public function getDailyComparisonStats(?int $ubicacion_id = null): array
    {
        $query = ObservacionCampo::where('grupo_experimental', 'experimental');

        if ($ubicacion_id) {
            $query->where('ubicacion_id', $ubicacion_id);
        }

        $rows = [];

        $dates = (clone $query)
            ->selectRaw('DATE(created_at) as date')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('date');

        foreach ($dates as $date) {
            $day = (clone $query)->whereDate('created_at', $date);

            $vp = (clone $day)->where('resultado', 'VP')->count();
            $fp = (clone $day)->where('resultado', 'FP')->count();
            $fn = (clone $day)->where('resultado', 'FN')->count();
            $vn = (clone $day)->where('resultado', 'VN')->count();

            // =========================
            // PDS (NO incluye VN)
            // =========================
            $pds_div = $vp + $fp + $fn;
            $pds = $pds_div > 0 ? ($vp / $pds_div) * 100 : 0;

            $total = $vp + $fp + $fn + $vn;

            $rows[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date)->format('d/m/Y'),

                'vp' => $vp,
                'fp' => $fp,
                'fn' => $fn,
                'vn' => $vn,

                'total' => $total,

                // MÉTRICA PRINCIPAL
                'pds' => round($pds, 2),

                // AUXILIARES
                'accuracy' => $total > 0 ? round((($vp + $vn) / $total) * 100, 2) : 0,
                'error_rate' => $total > 0 ? round((($fp + $fn) / $total) * 100, 2) : 0,
            ];
        }

        return $rows;
    }
}