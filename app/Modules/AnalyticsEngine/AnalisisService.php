<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alert;
use App\Models\Observacion;
use App\Models\Analysis;
use App\Models\PFRecord;
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
    public function evaluateOpenAlerts(int $location_id, float $current_ce_s, Analysis $current_analysis): void
    {
        $open_alerts = Alert::where('location_id', $location_id)
            ->where('status', 'OPEN')
            ->get();

        foreach ($open_alerts as $alert) {

            $alert->resolve('Valores regresaron a la normalidad.');
            $alert->status = 'CLOSED';
            $alert->save();

            $start = $alert->tiempo_riesgo ?? $alert->created_at;
            $end = $current_analysis->event_detected_at ?? now();

            $duration = $end->diffInMinutes($start);

            $threshold = config('agrolixisync.persistence_minutes', 10);

            // Clasificación básica del evento
            $resultado = $duration >= $threshold ? 'VP' : 'FP';

            Observacion::create([
                'location_id'        => $location_id,
                'experimental_group' => 'experimental',
                'alert_id'           => $alert->id,
                'ce_real'            => $current_ce_s,
                'diagnostico'        => $alert->type ?? 'LIXIVIACION',
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
    public function getPdsStats(int $location_id = null): array
    {
        $q = Observacion::query();

        if ($location_id) {
            $q->where('location_id', $location_id);
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
    public function getComparisonStats(?int $location_id = null): array
    {
        $control = PFRecord::where('experimental_group', 'control');
        $exp = PFRecord::where('experimental_group', 'experimental');

        if ($location_id) {
            $control->where('location_id', $location_id);
            $exp->where('location_id', $location_id);
        }

        $control_count = $control->count();
        $exp_count = $exp->count();

        $control_pf = (clone $control)->avg('pf_percentage') ?? 0;
        $exp_pf = (clone $exp)->avg('pf_percentage') ?? 0;

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
    public function getDailyComparisonStats(?int $location_id = null): array
    {
        $query = Observacion::where('experimental_group', 'experimental');

        if ($location_id) {
            $query->where('location_id', $location_id);
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