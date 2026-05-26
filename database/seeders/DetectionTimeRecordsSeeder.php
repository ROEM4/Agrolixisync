<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DetectionTimeRecord;
use App\Models\Location;
use Carbon\Carbon;

class DetectionTimeRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have at least one control and one experimental location
        $controlLocation = Location::where('experimental_group', 'control')->first();
        $experimentalLocation = Location::where('experimental_group', 'experimental')->first();

        if (! $controlLocation || ! $experimentalLocation) {
            $this->command->info('No control or experimental locations found. Seeder aborted.');
            return;
        }

        // Helper to create a record
        $createRecord = function (Location $loc, string $subparcela, int $daysAgo, float $avgSeconds, int $eventCount) {
            $date = Carbon::today()->subDays($daysAgo);
            DetectionTimeRecord::updateOrCreate(
                [
                    'fecha' => $date,
                    'location_id' => $loc->id,
                ],
                [
                    'lote_id' => $loc->lote_id,
                    'tiempo_promedio_segundos' => $avgSeconds,
                    'cantidad_eventos' => $eventCount,
                    'suma_tiempos_segundos' => (int)($avgSeconds * $eventCount),
                    'tipo_entrada' => 'manual',
                    'subparcela' => $subparcela,
                ]
            );
        };

        // Create 15 days of data for each group
        for ($i = 0; $i < 15; $i++) {
            // Control group: subparcela S{i+1}
            $createRecord($controlLocation, 'S' . ($i + 1), $i, rand(30, 120) / 1.0, rand(1, 5));
            // Experimental group: subparcela E{i+1}
            $createRecord($experimentalLocation, 'E' . ($i + 1), $i, rand(20, 100) / 1.0, rand(2, 6));
        }
    }
}
?>
