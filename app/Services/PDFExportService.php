<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Reading;
use App\Models\Sensor;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFExportService
{
    /**
     * Generar PDF de reporte de análisis
     */
    public function generateAnalysisReport(
        $analyses,
        array $options = []
    ) {
        if (!is_array($analyses)) {
            $analyses = [$analyses];
        } elseif (!is_array($analyses)) {
            $analyses = $analyses->toArray();
        }

        $title = $options['title'] ?? 'Reporte de Análisis de Lixiviación';
        $includeGraphs = $options['include_graphs'] ?? false;
        $dateRange = $options['date_range'] ?? null;

        // Preparar datos para la vista
        $data = [
            'title' => $title,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'analyses' => $analyses,
            'dateRange' => $dateRange,
            'summary' => $this->calculateSummary($analyses),
        ];

        // Crear PDF a partir de la vista
        $pdf = Pdf::loadView('exports.analysis-report', $data)
            ->setPaper('a4')
            ->setOptions([
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
                'fontDir' => storage_path('fonts'),
            ]);

        $filename = 'analisis_lixiviacion_' . now()->format('Y-m-d_His') . '.pdf';

        if (isset($options['download']) && $options['download']) {
            return $pdf->download($filename);
        }

        if (isset($options['stream']) && $options['stream']) {
            return $pdf->stream($filename);
        }

        return $pdf->output();
    }

    /**
     * Generar PDF con resumen de sensores
     */
    public function generateSensorComparisonReport($location, array $options = [])
    {
        $title = $options['title'] ?? 'Reporte Comparativo de Sensores';
        $days = $options['days'] ?? 7;

        // Obtener sensores
        $superficialSensor = $location->superficialSensors()->first();
        $deepSensor = $location->deepSensors()->first();

        if (!$superficialSensor || !$deepSensor) {
            throw new \Exception('No se encontraron ambos sensores en esta ubicación');
        }

        // Obtener análisis reciente
        $analyses = Analysis::where('location_id', $location->id)
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->latest('analyzed_at')
            ->get();

        // Obtener lecturas
        $startDate = now()->subDays($days);
        $readings = Reading::where('sensor_id', $superficialSensor->id)
            ->orWhere('sensor_id', $deepSensor->id)
            ->where('recorded_at', '>=', $startDate)
            ->orderBy('recorded_at')
            ->get();

        $data = [
            'title' => $title,
            'location' => $location,
            'superficialSensor' => $superficialSensor,
            'deepSensor' => $deepSensor,
            'analyses' => $analyses,
            'readings' => $readings,
            'summary' => $this->calculateLocationSummary($analyses),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
            ],
        ];

        $pdf = Pdf::loadView('exports.sensor-comparison-report', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

        $filename = 'comparativa_sensores_' . $location->id . '_' . now()->format('Y-m-d_His') . '.pdf';

        if (isset($options['download']) && $options['download']) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Generar PDF con histórico completo
     */
    public function generateHistoryReport($sensors, Carbon $startDate, Carbon $endDate, array $options = [])
    {
        $title = $options['title'] ?? 'Reporte Histórico de Lecturas';

        if (!is_array($sensors)) {
            $sensors = [$sensors];
        }

        // Obtener lecturas
        $readings = Reading::whereIn('sensor_id', array_map(fn($s) => $s->id, $sensors))
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();

        $data = [
            'title' => $title,
            'sensors' => $sensors,
            'readings' => $readings,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'summary' => $this->calculateReadingsSummary($readings),
        ];

        $pdf = Pdf::loadView('exports.history-report', $data)
            ->setPaper('a4')
            ->setOptions([
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

        $filename = 'historico_lecturas_' . now()->format('Y-m-d_His') . '.pdf';

        if (isset($options['download']) && $options['download']) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Calcular resumen de análisis
     */
    private function calculateSummary($analyses)
    {
        $analyses = collect($analyses);

        return [
            'total_analyses' => $analyses->count(),
            'lixiviation_detected' => $analyses->where('lixiviation_detected', true)->count(),
            'high_risk' => $analyses->where('risk_level', 'alto')->count(),
            'medium_risk' => $analyses->where('risk_level', 'medio')->count(),
            'low_risk' => $analyses->where('risk_level', 'bajo')->count(),
            'average_delta' => $analyses->avg('delta_conductivity'),
            'max_delta' => $analyses->max('delta_conductivity'),
            'min_delta' => $analyses->min('delta_conductivity'),
            'average_risk' => $analyses->avg('risk_percentage'),
        ];
    }

    /**
     * Calcular resumen de análisis por ubicación
     */
    private function calculateLocationSummary($analyses)
    {
        $analyses = collect($analyses);

        return [
            'total_analyses' => $analyses->count(),
            'lixiviation_events' => $analyses->where('lixiviation_detected', true)->count(),
            'lixiviation_rate' => $analyses->count() > 0 
                ? ($analyses->where('lixiviation_detected', true)->count() / $analyses->count()) * 100 
                : 0,
            'average_delta' => $analyses->avg('delta_conductivity'),
            'max_delta' => $analyses->max('delta_conductivity'),
            'current_status' => $analyses->first()?->lixiviation_detected ? 'Lixiviación Detectada' : 'Normal',
        ];
    }

    /**
     * Calcular resumen de lecturas
     */
    private function calculateReadingsSummary($readings)
    {
        $readings = collect($readings);

        return [
            'total_readings' => $readings->count(),
            'average_temperature' => $readings->avg('temperature'),
            'average_humidity' => $readings->avg('humidity'),
            'average_conductivity' => $readings->avg('conductivity'),
            'max_temperature' => $readings->max('temperature'),
            'min_temperature' => $readings->min('temperature'),
            'max_humidity' => $readings->max('humidity'),
            'min_humidity' => $readings->min('humidity'),
        ];
    }
}
