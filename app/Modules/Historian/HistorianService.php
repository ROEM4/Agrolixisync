<?php

namespace App\Modules\Historian;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * HistorianService
 *
 * Responsabilidad: agregar datos históricos desde la tabla readings
 * hacia readings_daily (performance industrial).
 *
 * Principio: humedad y temperatura NO son tablas separadas.
 * Son columnas en readings, agrupadas aquí por día para vistas analíticas.
 *
 * Flujo:
 *   readings (tiempo real, cada 5 min)
 *     → HistorianService::aggregate()  [cron diario o on-demand]
 *       → readings_daily (1 fila por sensor por día)
 *         → /api/historian/daily  (dashboard histórico)
 *         → /api/historian/range  (gráficos de tendencia)
 */
class HistorianService
{
    /**
     * Agrega un día específico para todos los sensores de una location.
     * Idempotente: usa INSERT ... ON DUPLICATE KEY UPDATE.
     */
    public function aggregateDay(int $location_id, string $date): int
    {
        $sensor_ids = DB::table('sensors')
            ->where('location_id', $location_id)
            ->pluck('id');

        $aggregated = 0;

        foreach ($sensor_ids as $sensor_id) {
            $stats = DB::table('readings')
                ->where('sensor_id', $sensor_id)
                ->whereDate('recorded_at', $date)
                ->whereNotNull('conductivity')
                ->selectRaw('
                    COUNT(*)              as n,
                    AVG(conductivity)     as ce_avg,
                    MIN(conductivity)     as ce_min,
                    MAX(conductivity)     as ce_max,
                    AVG(humidity)         as hum_avg,
                    MIN(humidity)         as hum_min,
                    MAX(humidity)         as hum_max,
                    AVG(temperature)      as temp_avg,
                    MIN(temperature)      as temp_min,
                    MAX(temperature)      as temp_max
                ')
                ->first();

            if (!$stats || $stats->n == 0) continue;

            DB::table('readings_daily')->upsert(
                [[
                    'sensor_id'   => $sensor_id,
                    'location_id' => $location_id,
                    'day'         => $date,
                    'n'           => $stats->n,
                    'ce_avg'      => $stats->ce_avg,
                    'ce_min'      => $stats->ce_min,
                    'ce_max'      => $stats->ce_max,
                    'hum_avg'     => $stats->hum_avg,
                    'hum_min'     => $stats->hum_min,
                    'hum_max'     => $stats->hum_max,
                    'temp_avg'    => $stats->temp_avg,
                    'temp_min'    => $stats->temp_min,
                    'temp_max'    => $stats->temp_max,
                    'updated_at'  => now(),
                ]],
                ['sensor_id', 'day'],                          // unique key
                ['n', 'ce_avg', 'ce_min', 'ce_max',           // columnas a actualizar
                 'hum_avg', 'hum_min', 'hum_max',
                 'temp_avg', 'temp_min', 'temp_max', 'updated_at']
            );

            $aggregated++;
        }

        return $aggregated;
    }

    /**
     * Agrega los últimos N días para una location.
     * Usado por el cron diario o para backfill inicial.
     */
    public function aggregateRange(int $location_id, int $days = 7): array
    {
        $results = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $n    = $this->aggregateDay($location_id, $date);
            $results[$date] = $n;
        }
        return $results;
    }

    /**
     * Consulta datos diarios desde readings_daily.
     * Si no hay datos agregados, cae back a readings en tiempo real.
     */
    public function getDaily(int $location_id, int $days = 7): array
    {
        $since = Carbon::today()->subDays($days - 1)->toDateString();

        $rows = DB::table('readings_daily as rd')
            ->join('sensors as s', 's.id', '=', 'rd.sensor_id')
            ->where('rd.location_id', $location_id)
            ->where('rd.day', '>=', $since)
            ->orderBy('rd.day')
            ->orderBy('s.depth')
            ->select([
                'rd.day',
                's.depth',
                'rd.n',
                'rd.ce_avg', 'rd.ce_min', 'rd.ce_max',
                'rd.hum_avg', 'rd.hum_min', 'rd.hum_max',
                'rd.temp_avg', 'rd.temp_min', 'rd.temp_max',
            ])
            ->get();

        // Agrupar por profundidad para respuesta estructurada
        $grouped = ['superficial' => [], 'profundo' => []];
        foreach ($rows as $row) {
            $key = (int) $row->depth === 20 ? 'superficial' : 'profundo';
            $grouped[$key][] = $this->formatDailyRow($row);
        }

        return $grouped;
    }

    /**
     * Consulta un rango de fechas arbitrario.
     */
    public function getRange(int $location_id, string $from, string $to): array
    {
        $rows = DB::table('readings_daily as rd')
            ->join('sensors as s', 's.id', '=', 'rd.sensor_id')
            ->where('rd.location_id', $location_id)
            ->whereBetween('rd.day', [$from, $to])
            ->orderBy('rd.day')
            ->orderBy('s.depth')
            ->select([
                'rd.day', 's.depth', 'rd.n',
                'rd.ce_avg', 'rd.ce_min', 'rd.ce_max',
                'rd.hum_avg', 'rd.hum_min', 'rd.hum_max',
                'rd.temp_avg', 'rd.temp_min', 'rd.temp_max',
            ])
            ->get();

        $grouped = ['superficial' => [], 'profundo' => []];
        foreach ($rows as $row) {
            $key = (int) $row->depth === 20 ? 'superficial' : 'profundo';
            $grouped[$key][] = $this->formatDailyRow($row);
        }

        return $grouped;
    }

    private function formatDailyRow(object $row): array
    {
        return [
            'day'      => $row->day,
            'depth'    => (int) $row->depth,
            'n'        => (int) $row->n,
            'ce'       => ['avg' => round((float)$row->ce_avg, 6), 'min' => round((float)$row->ce_min, 6), 'max' => round((float)$row->ce_max, 6)],
            'humidity' => ['avg' => round((float)$row->hum_avg, 2), 'min' => round((float)$row->hum_min, 2), 'max' => round((float)$row->hum_max, 2)],
            'temp'     => ['avg' => round((float)$row->temp_avg, 2), 'min' => round((float)$row->temp_min, 2), 'max' => round((float)$row->temp_max, 2)],
        ];
    }
}
