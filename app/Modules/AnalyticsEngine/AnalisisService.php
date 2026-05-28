<?php

namespace App\Modules\AnalyticsEngine;

use App\Models\Alert;
use App\Models\Observacion;
use App\Models\Analysis;
use App\Models\PFRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
     * Calcula las estadísticas globales del PDS para el Grupo Experimental.
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
        $total = $vp + $fp + $fn + $vn;
        
        // PDS (según especificación): VP / (VP + FP + FN) * 100
        $pds_div = $vp + $fp + $fn;
        $pds = $pds_div > 0 ? ($vp / $pds_div) * 100 : 0;

        // Sensibilidad (Recall) = (VP / (VP + FN)) * 100
        $recall_divisor = $vp + $fn;
        $recall = $recall_divisor > 0 ? ($vp / $recall_divisor) * 100 : 0;

        // Accuracy = (VP + VN) / Total * 100
        $accuracy = $total > 0 ? (($vp + $vn) / $total) * 100 : 0;

        // Tasa de error = (FP + FN) / Total * 100
        $error_rate = $total > 0 ? (($fp + $fn) / $total) * 100 : 0;
        
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
            'vn' => $vn,
            'total' => $total,
            'pds_percentage' => round($pds, 2),
            'recall' => round($recall, 2),
            'accuracy' => round($accuracy, 2),
            'error_rate' => round($error_rate, 2),
            'avg_response_time' => round($avg_time, 2)
        ];
    }

    /**
     * Compara Grupo Control vs Experimental para tesis (estadísticas globales).
     */
    public function getComparisonStats(?int $location_id = null): array
    {
        $control_query = PFRecord::where('experimental_group', 'control');
        $exp_query = PFRecord::where('experimental_group', 'experimental');

        if ($location_id) {
            $control_query->where('location_id', $location_id);
            $exp_query->where('location_id', $location_id);
        }

        $totalControl = $control_query->count();
        $lossControl = (clone $control_query)
            ->where(function($query) {
                $query->where('ce_reference', '>', 1.05)
                      ->orWhereRaw('ce_profunda > ce_superficial');
            })->count();
        $lossPercentage = $totalControl > 0 ? ($lossControl / $totalControl) * 100 : 0;

        $stats = [
            'control' => [
                'count' => $totalControl,
                'avg_ce_sup' => round((float)($control_query->avg('ce_superficial') ?? 0), 3),
                'avg_ce_prof' => round((float)($control_query->avg('ce_profunda') ?? 0), 3),
                'avg_ilx' => round((float)($control_query->avg('ce_reference') ?? 0), 4),
                'avg_pf' => round((float)($control_query->avg('pf_percentage') ?? 0), 2),
                'loss_percentage' => round($lossPercentage, 2),
            ],
            'experimental' => [
                'count' => $exp_query->count(),
                'avg_ce_sup' => round((float)($exp_query->avg('ce_superficial') ?? 0), 3),
                'avg_ce_prof' => round((float)($exp_query->avg('ce_profunda') ?? 0), 3),
                'avg_ilx' => round((float)($exp_query->avg('ce_measured') ?? 0), 4),
                'avg_pf' => round((float)($exp_query->avg('pf_percentage') ?? 0), 2),
            ]
        ];

        // Eficiencia = ((PF_control - PF_exp) / PF_control) * 100
        $efficiency = 0;
        if ($stats['control']['avg_pf'] > 0) {
            $efficiency = (($stats['control']['avg_pf'] - $stats['experimental']['avg_pf']) / $stats['control']['avg_pf']) * 100;
        }
        $stats['efficiency'] = round($efficiency, 2);

        return $stats;
    }

    /**
     * Genera el análisis descriptivo diario alineado por fecha (Control + Experimental).
     *
     * Cada entrada del array resultante tiene la estructura:
     * [
     *   'date'         => 'YYYY-MM-DD',
     *   'date_label'   => 'DD/MM/YYYY',
     *   'control'      => [ total, con_lixiviacion, sin_lixiviacion, pct_lixiviacion ],
     *   'experimental' => [ vp, fp, fn, vn, total, precision, recall, error_rate ],
     *   'has_control'  => bool,
     *   'has_experimental' => bool,
     * ]
     *
     * @param  int|null $location_id  Filtrar por ubicación (opcional)
     * @return array                  Array de días con estadísticas comparativas
     */
    public function getDailyComparisonStats(?int $location_id = null): array
    {
        // ── GRUPO CONTROL ─────────────────────────────────────────────────────────
        $controlQuery = PFRecord::where('experimental_group', 'control');
        if ($location_id) {
            $controlQuery->where('location_id', $location_id);
        }

        $controlRaw = $controlQuery
            ->selectRaw("DATE(recorded_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN ce_reference > 1.05 OR ce_profunda > ce_superficial THEN 1 ELSE 0 END) as con_lixiviacion")
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy(DB::raw('DATE(recorded_at)'))
            ->get();

        // Indexar por fecha y calcular promedios para control
        $controlByDate = [];
        foreach ($controlRaw as $row) {
            $total = (int) $row->total;
            $con   = (int) $row->con_lixiviacion;
            $sin   = $total - $con;
            $pct   = $total > 0 ? round(($con / $total) * 100, 2) : 0.0;
            // obtener promedios diarios
            $dayAvgSup = (clone $controlQuery)->whereDate('recorded_at', $row->date)->avg('ce_superficial');
            $dayAvgProf = (clone $controlQuery)->whereDate('recorded_at', $row->date)->avg('ce_profunda');
            $dayAvgIlx = (clone $controlQuery)->whereDate('recorded_at', $row->date)->avg('ce_reference');

            $controlByDate[$row->date] = [
                'total'             => $total,
                'con_lixiviacion'   => $con,
                'sin_lixiviacion'   => $sin,
                'pct_lixiviacion'   => $pct,
                'avg_ce_sup'        => round((float)($dayAvgSup ?? 0), 3),
                'avg_ce_prof'       => round((float)($dayAvgProf ?? 0), 3),
                'avg_ilx'           => round((float)($dayAvgIlx ?? 0), 4),
            ];
        }

        // Calcular promedios globales de control para imputación si faltan días
        $globalControlAvgSup = (clone $controlQuery)->avg('ce_superficial') ?? 0;
        $globalControlAvgProf = (clone $controlQuery)->avg('ce_profunda') ?? 0;
        $globalControlAvgIlx = (clone $controlQuery)->avg('ce_reference') ?? 0;
        $globalControlLossPct = 0;
        $totalCtrlAll = (clone $controlQuery)->count();
        if ($totalCtrlAll > 0) {
            $lossAll = (clone $controlQuery)
                ->where(function($q) { $q->where('ce_reference', '>', 1.05)->orWhereRaw('ce_profunda > ce_superficial'); })
                ->count();
            $globalControlLossPct = $lossAll > 0 ? round(($lossAll / $totalCtrlAll) * 100, 2) : 0;
        }

        // ── GRUPO EXPERIMENTAL: recalcular por día comparando detecciones IoT contra la verdad de campo diaria
        $expQueryBase = Observacion::where('experimental_group', 'experimental');
        if ($location_id) {
            $expQueryBase->where('location_id', $location_id);
        }

        $expByDate = [];
        // Para evitar muchas consultas si la cantidad de fechas es grande, primero obtener el rango de fechas presentes en observaciones
        $expDates = (clone $expQueryBase)
            ->selectRaw('DATE(created_at) as date')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->pluck('date')
            ->toArray();

        foreach ($expDates as $date) {
            $totalExp = (clone $expQueryBase)->whereDate('created_at', $date)->count();

            // Definir detección IoT: diagnósticos explícitos 'LIXIVIACION' o resultados ya marcados como VP/FP
            $detectedCount = (clone $expQueryBase)
                ->whereDate('created_at', $date)
                ->where(function($q) {
                    $q->where('diagnostico', 'LIXIVIACION')
                      ->orWhereIn('resultado', ['VP','FP']);
                })->count();

            $notDetected = $totalExp - $detectedCount;

            // Verdad de campo para el día: si el control reportó cualquiera con_lixiviacion > 0
            $controlDay = $controlByDate[$date] ?? null;
            $controlHasLoss = ($controlDay && ($controlDay['con_lixiviacion'] ?? 0) > 0) ? true : false;

            // Si no hay datos de control para la fecha, imputar usando promedios globales
            $imputedControl = false;
            if (!$controlDay) {
                $controlDay = [
                    'total' => 0,
                    'con_lixiviacion' => ($globalControlLossPct > 50 ? 1 : 0),
                    'sin_lixiviacion' => 0,
                    'pct_lixiviacion' => $globalControlLossPct,
                    'avg_ce_sup' => round((float)$globalControlAvgSup, 3),
                    'avg_ce_prof' => round((float)$globalControlAvgProf, 3),
                    'avg_ilx' => round((float)$globalControlAvgIlx, 4),
                ];
                $controlHasLoss = ($controlDay['con_lixiviacion'] ?? 0) > 0;
                $imputedControl = true;
            }

            if ($controlHasLoss) {
                // Si hubo pérdida real en el día, detección = VP, no detección = FN
                $vp = $detectedCount;
                $fn = $notDetected;
                $fp = 0;
                $vn = 0;
            } else {
                // Si no hubo pérdida real en el día, detección = FP, no detección = VN
                $fp = $detectedCount;
                $vn = $notDetected;
                $vp = 0;
                $fn = 0;
            }

            $total = $vp + $fp + $fn + $vn;

            $pds_div = $vp + $fp + $fn; // según especificación
            $pds = $pds_div > 0 ? round(($vp / $pds_div) * 100, 2) : 0.0;

            $recall_div = $vp + $fn;
            $recall = $recall_div > 0 ? round(($vp / $recall_div) * 100, 2) : 0.0;

            $accuracy = $total > 0 ? round((($vp + $vn) / $total) * 100, 2) : 0.0;
            $error_rate = $total > 0 ? round((($fp + $fn) / $total) * 100, 2) : 0.0;

            $expByDate[$date] = [
                'vp' => $vp,
                'fp' => $fp,
                'fn' => $fn,
                'vn' => $vn,
                'total' => $total,
                'pds' => $pds,
                'recall' => $recall,
                'accuracy' => $accuracy,
                'error_rate' => $error_rate,
                'control_imputed' => $imputedControl,
            ];
        }

        // ── UNIR POR FECHA ─────────────────────────────────────────────────────────
        $allDates = array_unique(array_merge(
            array_keys($controlByDate),
            array_keys($expByDate)
        ));
        rsort($allDates); // descendente (más reciente primero)

        $rows = [];
        foreach ($allDates as $date) {
            $rows[] = [
                'date'             => $date,
                'date_label'       => Carbon::parse($date)->format('d/m/Y'),
                'control'          => $controlByDate[$date] ?? null,
                'experimental'     => $expByDate[$date] ?? null,
                'has_control'      => isset($controlByDate[$date]),
                'has_experimental' => isset($expByDate[$date]),
            ];
        }

        return $rows;
    }
}
