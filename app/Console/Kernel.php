<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\CalculateThesisMetricsJob;
use App\Jobs\ExportDataToCsvJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ═══════════════════════════════════════════════════════════════
        // CÁLCULO DE MÉTRICAS DE TESIS
        // ═══════════════════════════════════════════════════════════════

        /**
         * Calcular TAR, PDS, NCES diariamente a las 2 AM
         * Período: últimos 30 días
         */
        $schedule->job(
            new CalculateThesisMetricsJob()
        )
        ->dailyAt('02:00')
        ->name('calculate-thesis-metrics-daily')
        ->description('Calculate daily thesis metrics (TAR, PDS, NCES)')
        ->onSuccess(function () {
            \Illuminate\Support\Facades\Log::info('[SCHEDULER] Thesis metrics calculation succeeded');
        })
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::error('[SCHEDULER] Thesis metrics calculation failed');
        });

        /**
         * Calcular métricas semanales todos los lunes a las 3 AM
         * Período: últimos 7 días
         */
        $schedule->call(function () {
            $start = \Illuminate\Support\Carbon::now()->subWeek();
            $end = \Illuminate\Support\Carbon::now();
            dispatch(new CalculateThesisMetricsJob(null, $start, $end));
        })
        ->weeklyOn(1, '03:00')  // Monday
        ->name('calculate-thesis-metrics-weekly')
        ->description('Calculate weekly thesis metrics');

        /**
         * Calcular métricas mensuales el primer día del mes a las 4 AM
         * Período: mes anterior
         */
        $schedule->call(function () {
            $start = \Illuminate\Support\Carbon::now()->subMonth()->startOfMonth();
            $end = \Illuminate\Support\Carbon::now()->subMonth()->endOfMonth();
            dispatch(new CalculateThesisMetricsJob(null, $start, $end));
        })
        ->monthlyOn(1, '04:00')
        ->name('calculate-thesis-metrics-monthly')
        ->description('Calculate monthly thesis metrics');

        // ═══════════════════════════════════════════════════════════════
        // EXPORTACIÓN DE DATOS A CSV
        // ═══════════════════════════════════════════════════════════════

        /**
         * Exportar datos completos diariamente a las 5 AM
         */
        $schedule->job(
            new ExportDataToCsvJob('PERIODIC_EXPORT')
        )
        ->dailyAt('05:00')
        ->name('export-data-daily')
        ->description('Daily export of all sensor data to CSV')
        ->onSuccess(function () {
            \Illuminate\Support\Facades\Log::info('[SCHEDULER] Daily data export succeeded');
        })
        ->onFailure(function () {
            \Illuminate\Support\Facades\Log::error('[SCHEDULER] Daily data export failed');
        });

        /**
         * Exportar análisis los viernes a las 6 AM
         */
        $schedule->job(
            new ExportDataToCsvJob('ANALYSIS_EXPORT')
        )
        ->weeklyOn(5, '06:00')  // Friday
        ->name('export-analysis-weekly')
        ->description('Weekly export of analysis data');

        /**
         * Exportar métricas de tesis el último día de cada mes a las 7 AM
         */
        $schedule->call(function () {
            dispatch(new ExportDataToCsvJob('THESIS_METRICS'));
        })
        ->monthlyOn(28, '07:00')  // Close to last day
        ->name('export-thesis-metrics-monthly')
        ->description('Monthly export of thesis metrics');

        /**
         * Exportar resultados de pruebas del sistema el 15 de cada mes
         */
        $schedule->job(
            new ExportDataToCsvJob('SYSTEM_TESTS')
        )
        ->monthlyOn(15, '08:00')
        ->name('export-system-tests-monthly')
        ->description('Monthly export of system test results');

        // ═══════════════════════════════════════════════════════════════
        // MANTENIMIENTO & LIMPIEZA
        // ═══════════════════════════════════════════════════════════════

        /**
         * Limpiar exportes antiguos (configurar en config/agrolixisync.php)
         * Se ejecuta automáticamente en el Job de exportación
         */

        /**
         * Limpiar logs antiguos
         */
        $schedule->command('log:prune')
            ->daily()
            ->at('01:00')
            ->name('prune-logs')
            ->description('Prune old log files');

        /**
         * Cleanupp de cache
         */
        $schedule->command('cache:prune-stale-tags')
            ->hourly()
            ->name('prune-cache')
            ->description('Prune stale cache tags');

        // ═══════════════════════════════════════════════════════════════
        // MONITOREO & ALERTAS (FUTURO)
        // ═══════════════════════════════════════════════════════════════

        /**
         * Monitor de salud del sistema - cada 5 minutos
         */
        $schedule->call(function () {
            \Illuminate\Support\Facades\Log::info('[SCHEDULER] System health check executed');
        })
        ->everyFiveMinutes()
        ->name('system-health-check')
        ->description('Check system health and connectivity');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
