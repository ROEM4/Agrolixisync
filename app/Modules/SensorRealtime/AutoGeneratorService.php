<?php

namespace App\Modules\SensorRealtime;

use App\Models\Location;
use App\Models\Reading;
use App\Models\Analysis;
use App\Models\Sensor;
use App\Modules\AnalyticsEngine\LixiviationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoGeneratorService
{
    protected LixiviationService $lixiviation;

    public function __construct(LixiviationService $lixiviation)
    {
        $this->lixiviation = $lixiviation;
    }

    /**
     * Asegura que existan lecturas recientes para todas las ubicaciones activas.
     * Si no hay lecturas en los últimos 15 minutos, genera una nueva.
     */
    public function ensureFreshData(): void
    {
        $locations = Location::where('is_active', true)->get();

        foreach ($locations as $location) {
            $lastReading = Reading::whereHas('sensor', fn($q) => $q->where('location_id', $location->id))
                ->orderByDesc('recorded_at')
                ->first();

            // Si la última lectura es de hace más de 10 minutos (o no existe)
            if (!$lastReading || $lastReading->recorded_at->diffInMinutes(now()) >= 10) {
                $this->generateReading($location);
            }
        }
    }

    protected function generateReading(Location $location): void
    {
        $sensors = Sensor::where('location_id', $location->id)->get()->keyBy('depth');
        $s_sup = $sensors->get(20);
        $s_prof = $sensors->get(60);

        if (!$s_sup || !$s_prof) return;

        $isExp = $location->experimental_group === 'experimental';
        
        // Simular valores realistas basados en el grupo
        if ($isExp) {
            // Experimental: Menor lixiviación (valores más cercanos o equilibrio)
            $ce_s = 1.0 + (mt_rand(0, 50) / 100);
            $ce_p = $ce_s * (0.85 + (mt_rand(0, 25) / 100)); // ILx alrededor de 0.85 - 1.10
        } else {
            // Control: Mayor lixiviación
            $ce_s = 1.0 + (mt_rand(0, 50) / 100);
            $ce_p = $ce_s * (1.10 + (mt_rand(0, 40) / 100)); // ILx alrededor de 1.10 - 1.50
        }

        $now = now();

        $r_sup = Reading::create([
            'sensor_id' => $s_sup->id,
            'conductivity' => $ce_s,
            'humidity' => mt_rand(45, 55),
            'temperature' => mt_rand(22, 24),
            'recorded_at' => $now,
        ]);

        $r_prof = Reading::create([
            'sensor_id' => $s_prof->id,
            'conductivity' => $ce_p,
            'humidity' => mt_rand(45, 55),
            'temperature' => mt_rand(22, 24),
            'recorded_at' => $now,
        ]);

        // Ejecutar el análisis real del sistema
        $this->lixiviation->analyze($s_sup, $s_prof);

        Log::info("AutoData: Generada lectura y análisis para ubicación {$location->name}");
    }
}
