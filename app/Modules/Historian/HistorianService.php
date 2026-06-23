<?php

namespace App\Modules\Historian;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * HistorianService
 *
 * Responsabilidad: agregar datos históricos desde la tabla `lecturas`
 * hacia `lecturas_diarias` (performance industrial).
 *
 * Flujo:
 *   lecturas (tiempo real, cada 5 min)
 *     → HistorianService::aggregate()  [cron diario o on-demand]
 *       → lecturas_diarias (1 fila por sensor por día)
 *         → /api/historian/daily  (dashboard histórico)
 *         → /api/historian/range  (gráficos de tendencia)
 */
class HistorianService
{
    /**
     * Agrega un día específico para todos los sensores de una ubicación.
     * Idempotente: usa INSERT ... ON DUPLICATE KEY UPDATE.
     */
    public function aggregateDay(int $ubicacion_id, string $date): int
    {
        $sensor_ids = DB::table('sensores')
            ->where('ubicacion_id', $ubicacion_id)
            ->pluck('id');

        $aggregated = 0;

        foreach ($sensor_ids as $sensor_id) {
            $stats = DB::table('lecturas')
                ->where('sensor_id', $sensor_id)
                ->whereDate('fecha_registro', $date)
                ->whereNotNull('conductividad')
                ->selectRaw('
                    COUNT(*)                 as n,
                    AVG(conductividad)       as ce_avg,
                    MIN(conductividad)       as ce_min,
                    MAX(conductividad)       as ce_max,
                    AVG(humedad)             as hum_avg,
                    MIN(humedad)             as hum_min,
                    MAX(humedad)             as hum_max,
                    AVG(temperatura)         as temp_avg,
                    MIN(temperatura)         as temp_min,
                    MAX(temperatura)         as temp_max
                ')
                ->first();

            if (!$stats || $stats->n == 0) continue;

            DB::table('lecturas_diarias')->upsert(
                [[
                    'sensor_id'    => $sensor_id,
                    'ubicacion_id' => $ubicacion_id,
                    'dia'          => $date,
                    'n'            => $stats->n,
                    'ce_avg'       => $stats->ce_avg,
                    'ce_min'       => $stats->ce_min,
                    'ce_max'       => $stats->ce_max,
                    'hum_avg'      => $stats->hum_avg,
                    'hum_min'      => $stats->hum_min,
                    'hum_max'      => $stats->hum_max,
                    'temp_avg'     => $stats->temp_avg,
                    'temp_min'     => $stats->temp_min,
                    'temp_max'     => $stats->temp_max,
                    'updated_at'   => now(),
                ]],
                ['sensor_id', 'dia'],
                ['n', 'ce_avg', 'ce_min', 'ce_max',
                 'hum_avg', 'hum_min', 'hum_max',
                 'temp_avg', 'temp_min', 'temp_max', 'updated_at']
            );

            $aggregated++;
        }

        return $aggregated;
    }

    /**
     * Agrega los últimos N días para una ubicación.
     */
    public function aggregateRange(int $ubicacion_id, int $days = 7): array
    {
        $results = [];
        for ($i = 0; $i < $days; $i++) {
            $date           = Carbon::today()->subDays($i)->toDateString();
            $results[$date] = $this->aggregateDay($ubicacion_id, $date);
        }
        return $results;
    }

    /**
     * Consulta datos diarios desde lecturas_diarias.
     */
    public function getDaily(int $ubicacion_id, int $days = 7): array
    {
        $since = Carbon::today()->subDays($days - 1)->toDateString();

        $rows = DB::table('lecturas_diarias as ld')
            ->join('sensores as s', 's.id', '=', 'ld.sensor_id')
            ->where('ld.ubicacion_id', $ubicacion_id)
            ->where('ld.dia', '>=', $since)
            ->orderBy('ld.dia')
            ->orderBy('s.profundidad')
            ->select([
                'ld.dia',
                's.profundidad',
                'ld.n',
                'ld.ce_avg', 'ld.ce_min', 'ld.ce_max',
                'ld.hum_avg', 'ld.hum_min', 'ld.hum_max',
                'ld.temp_avg', 'ld.temp_min', 'ld.temp_max',
            ])
            ->get();

        $grouped = ['superficial' => [], 'profundo' => []];
        foreach ($rows as $row) {
            $key             = (int) $row->profundidad <= 20 ? 'superficial' : 'profundo';
            $grouped[$key][] = $this->formatDailyRow($row);
        }

        return $grouped;
    }

    /**
     * Consulta un rango de fechas arbitrario.
     */
    public function getRange(int $ubicacion_id, string $from, string $to): array
    {
        $rows = DB::table('lecturas_diarias as ld')
            ->join('sensores as s', 's.id', '=', 'ld.sensor_id')
            ->where('ld.ubicacion_id', $ubicacion_id)
            ->whereBetween('ld.dia', [$from, $to])
            ->orderBy('ld.dia')
            ->orderBy('s.profundidad')
            ->select([
                'ld.dia', 's.profundidad', 'ld.n',
                'ld.ce_avg', 'ld.ce_min', 'ld.ce_max',
                'ld.hum_avg', 'ld.hum_min', 'ld.hum_max',
                'ld.temp_avg', 'ld.temp_min', 'ld.temp_max',
            ])
            ->get();

        $grouped = ['superficial' => [], 'profundo' => []];
        foreach ($rows as $row) {
            $key             = (int) $row->profundidad <= 20 ? 'superficial' : 'profundo';
            $grouped[$key][] = $this->formatDailyRow($row);
        }

        return $grouped;
    }

    private function formatDailyRow(object $row): array
    {
        return [
            'dia'        => $row->dia,
            'profundidad'=> (int) $row->profundidad,
            'n'          => (int) $row->n,
            'ce'         => ['avg' => round((float) $row->ce_avg, 6), 'min' => round((float) $row->ce_min, 6), 'max' => round((float) $row->ce_max, 6)],
            'humedad'    => ['avg' => round((float) $row->hum_avg, 2), 'min' => round((float) $row->hum_min, 2), 'max' => round((float) $row->hum_max, 2)],
            'temperatura'=> ['avg' => round((float) $row->temp_avg, 2), 'min' => round((float) $row->temp_min, 2), 'max' => round((float) $row->temp_max, 2)],
        ];
    }
}
