<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Analysis;
use App\Models\Location;
use App\Models\Lote;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Generar alertas de prueba desde el 19 de abril
     */
    public function run(): void
    {
        // Obtener lotes y ubicaciones existentes
        $lotes = Lote::all();
        $locations = Location::all();

        if ($lotes->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('No hay lotes o ubicaciones. Ejecuta los seeders previos primero.');
            return;
        }

        // Fecha inicial: 19 de abril de 2026
        $startDate = Carbon::parse('2026-04-19 08:00:00');
        
        // Generar datos para 7 días (19-25 de abril)
        for ($day = 0; $day < 7; $day++) {
            $currentDate = $startDate->clone()->addDays($day);
            
            // Cantidad de alertas variable por día (3-7 alertas diarias)
            $alertsPerDay = rand(3, 7);
            
            for ($i = 0; $i < $alertsPerDay; $i++) {
                // Seleccionar ubicación aleatoria
                $location = $locations->random();
                $lote = $location->lote;

                // Generar tiempos dentro del día
                // Tiempo de alerta: entre 8:00 y 18:00
                $hour = rand(8, 17);
                $minute = rand(0, 59);
                $second = rand(0, 59);
                
                $timeAlerta = $currentDate->clone()
                    ->setHour($hour)
                    ->setMinute($minute)
                    ->setSecond($second);

                // Tiempo de evento: 5 a 30 minutos después
                $delayMinutes = rand(5, 30);
                $timeEvento = $timeAlerta->clone()->addMinutes($delayMinutes);

                // Calcular TAR en segundos
                $tarSeconds = $timeEvento->diffInSeconds($timeAlerta);

                // Crear o buscar análisis para esta ubicación
                $analysis = Analysis::where('location_id', $location->id)
                    ->orderByDesc('analyzed_at')
                    ->first();

                if (!$analysis) {
                    $analysis = Analysis::create([
                        'location_id' => $location->id,
                        'lote_id' => $lote->id,
                        'conductivity_superficial' => rand(1000, 3000) / 100,
                        'conductivity_profundo' => rand(1500, 4000) / 100,
                        'analyzed_at' => $timeAlerta,
                        'alert_generated_at' => $timeAlerta,
                    ]);
                }

                // Crear alerta
                Alert::create([
                    'analysis_id' => $analysis->id,
                    'lote_id' => $lote->id,
                    'location_id' => $location->id,
                    'type' => 'lixiviacion',
                    'severity' => $this->randomSeverity(),
                    'status' => 'active',
                    'level' => ['bajo', 'medio', 'alto'][rand(0, 2)],
                    'description' => 'Alerta de lixiviación detectada por sistema automático',
                    'ce_anterior' => rand(1500, 2500) / 100,
                    'ce_actual' => rand(2000, 3500) / 100,
                    'delta_ce' => rand(300, 1000) / 100,
                    'tiempo_alerta' => $timeAlerta,
                    'tiempo_riesgo' => $timeEvento,
                    'tar' => $tarSeconds,
                    'is_resolved' => rand(0, 1) === 1,
                    'resolved_at' => rand(0, 1) === 1 ? $timeEvento->clone()->addMinutes(rand(15, 60)) : null,
                    'notified' => true,
                    'notified_at' => $timeAlerta,
                ]);
            }
        }

        $this->command->info('✅ AlertSeeder: Se generaron alertas de prueba desde el 19 de abril.');
    }

    /**
     * Generar severidad aleatoria
     */
    private function randomSeverity(): string
    {
        $severities = ['BAJO', 'MEDIO', 'ALTO', 'CRÍTICO'];
        return $severities[array_rand($severities)];
    }
}
