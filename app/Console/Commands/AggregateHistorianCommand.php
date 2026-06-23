<?php

namespace App\Console\Commands;

use App\Models\Ubicacion;
use App\Modules\Historian\HistorianService;
use Illuminate\Console\Command;

/**
 * php artisan historian:aggregate [--location=] [--days=]
 *
 * Agrega lecturas de readings → readings_daily.
 * Diseñado para correr como cron diario (00:05 AM).
 * También se puede ejecutar manualmente para backfill.
 *
 * Ejemplos:
 *   php artisan historian:aggregate              # todas las locations, hoy
 *   php artisan historian:aggregate --days=30    # backfill 30 días
 *   php artisan historian:aggregate --location=1 # solo location 1
 */
class AggregateHistorianCommand extends Command
{
    protected $signature   = 'historian:aggregate {--location= : ID de location específica} {--days=1 : Días a agregar hacia atrás}';
    protected $description = 'Agrega lecturas diarias desde readings hacia readings_daily (Historian module)';

    public function handle(HistorianService $historian): int
    {
        $days       = (int) $this->option('days');
        $locationId = $this->option('location');

        $locations = $locationId
            ? Ubicacion::where('id', $locationId)->get()
            : Ubicacion::where('activa', true)->get();

        if ($locations->isEmpty()) {
            $this->warn('No hay locations activas.');
            return self::SUCCESS;
        }

        $this->info("Agregando {$days} día(s) para {$locations->count()} location(s)...");
        $bar = $this->output->createProgressBar($locations->count());

        foreach ($locations as $location) {
            $results = $historian->aggregateRange($location->id, $days);
            $total   = array_sum($results);
            $bar->advance();
            $this->line(" Location {$location->id} ({$location->name}): {$total} sensores agregados");
        }

        $bar->finish();
        $this->newLine();
        $this->info('Agregación completada.');

        return self::SUCCESS;
    }
}
