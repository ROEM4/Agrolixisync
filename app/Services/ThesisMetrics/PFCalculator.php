<?php

namespace App\Services\ThesisMetrics;

use App\Models\Location;
use App\Models\Reading;

class PFCalculator
{
    private Location $location;
    private \Illuminate\Support\Carbon $periodStart;
    private \Illuminate\Support\Carbon $periodEnd;

    public function __construct(Location $location, \Illuminate\Support\Carbon $periodStart, \Illuminate\Support\Carbon $periodEnd)
    {
        $this->location = $location;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    public function calculate(): ?array
    {
        $referenceCe = $this->getReferenceCe();
        $measuredCe = $this->getMeasuredCe();

        if ($referenceCe === null || $measuredCe === null || $referenceCe == 0) {
            return null;
        }

        $pfPercentage = (($referenceCe - $measuredCe) / $referenceCe) * 100;

        return [
            'reference_ce' => round($referenceCe, 4),
            'measured_ce' => round($measuredCe, 4),
            'pf_percentage' => round($pfPercentage, 2),
        ];
    }

    private function getReferenceCe(): ?float
    {
        if ($this->location->lote && $this->location->lote->reference_ce) {
            return (float) $this->location->lote->reference_ce;
        }

        // Fallback opcional: usar configuración global si existe.
        $setting = \DB::table('settings')->where('key', 'pf_reference_ce')->first();
        return $setting ? (float) $setting->value : null;
    }

    private function getMeasuredCe(): ?float
    {
        $sensorIds = $this->location->sensors()->pluck('id')->toArray();
        if (empty($sensorIds)) {
            return null;
        }

        $readings = Reading::whereIn('sensor_id', $sensorIds)
            ->whereBetween('recorded_at', [$this->periodStart, $this->periodEnd])
            ->get();

        if ($readings->isEmpty()) {
            return null;
        }

        return (float) $readings->avg('conductivity');
    }

    public function getInterpretation(array $data): string
    {
        if (!$data || !isset($data['pf_percentage'])) {
            return 'No hay suficiente referencia CE para calcular PF';
        }

        $pf = $data['pf_percentage'];

        if ($pf <= 0) {
            return 'No hay pérdida de fertilizante detectada; podría estar en rango o en exceso';
        }
        if ($pf <= 10) {
            return 'Pérdida baja de fertilizante';
        }
        if ($pf <= 25) {
            return 'Pérdida moderada de fertilizante';
        }
        return 'Alta pérdida de fertilizante';
    }
}
