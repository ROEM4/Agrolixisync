<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use League\Csv\Writer;
use SplTempFileObject;

class DataExportService
{
    /**
     * Exportar lecturas a CSV
     * 
     * @param Sensor|array $sensors - Sensor o array de sensores
     * @param Carbon $startDate - Fecha de inicio
     * @param Carbon $endDate - Fecha de fin
     * @param string|null $outputPath - Ruta de salida (opcional)
     * @return string - Contenido CSV o ruta del archivo
     */
    public function exportReadingsToCSV(
        $sensors,
        Carbon $startDate,
        Carbon $endDate,
        ?string $outputPath = null
    ): string {
        // Normalizar entrada
        if (!is_array($sensors)) {
            $sensors = [$sensors];
        }

        // Obtener IDs de sensores
        $sensorIds = array_map(fn($s) => $s->id, $sensors);

        // Obtener lecturas
        $readings = Reading::whereIn('sensor_id', $sensorIds)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();

        // Crear CSV
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Encabezados
        $csv->insertOne([
            'Fecha',
            'Hora',
            'Sensor (código)',
            'Sensor (nombre)',
            'Profundidad (cm)',
            'Temperatura (°C)',
            'Humedad (%)',
            'CE (µS/cm)',
            'CE (dS/m)',
        ]);

        // Insertar datos
        foreach ($readings as $reading) {
            $ce_us = $reading->conductivity ?? null;
            $ce_ds = $ce_us !== null ? round($ce_us / 1000, 3) : 'N/A';

            $csv->insertOne([
                $reading->recorded_at->format('Y-m-d'),
                $reading->recorded_at->format('H:i:s'),
                $reading->sensor->code,
                $reading->sensor->name ?? 'N/A',
                $reading->sensor->depth ?? 'N/A',
                $reading->temperature ?? 'N/A',
                $reading->humidity ?? 'N/A',
                $ce_us ?? 'N/A',
                $ce_ds,
            ]);
        }

        // Retornar contenido o guardar archivo
        if ($outputPath) {
            $csv->output($outputPath);
            return $outputPath;
        }

        return (string) $csv;
    }

    /**
     * Exportar análisis a CSV
     */
    public function exportAnalysisToCSV(
        $analyses,
        ?string $outputPath = null
    ): string {
        // Normalizar entrada
        if (!is_array($analyses) && !($analyses instanceof Collection)) {
            $analyses = [$analyses];
        }

        // Crear CSV
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Encabezados
        $csv->insertOne([
            'Fecha',
            'Hora',
            'Lote',
            'Ubicación',
            'Sensor Superficial',
            'Sensor Profundo',
            'CE Superficial (dS/m)',
            'CE Profundo (dS/m)',
            'Delta CE (dS/m)',
            'Ratio CE',
            'Umbral Delta (dS/m)',
            'Lixiviación Detectada',
            'Nivel de Riesgo',
            'Estado',
        ]);

        // Insertar datos
        foreach ($analyses as $analysis) {
            $ce_sup  = $analysis->conductivity_superficial !== null
                ? round($analysis->conductivity_superficial / 1000, 3) : 'N/A';
            $ce_prof = $analysis->conductivity_profundo !== null
                ? round($analysis->conductivity_profundo / 1000, 3) : 'N/A';
            $delta   = $analysis->delta_conductivity !== null
                ? round($analysis->delta_conductivity / 1000, 3) : 'N/A';
            $ratio   = ($analysis->conductivity_superficial > 0 && $analysis->conductivity_profundo !== null)
                ? round($analysis->conductivity_profundo / $analysis->conductivity_superficial, 3) : 'N/A';
            $threshold = round($analysis->threshold_used / 1000, 3);

            if ($analysis->lixiviation_detected) {
                $estado = 'Perdida de fertilizante';
            } elseif ($analysis->risk_level === 'MEDIO') {
                $estado = 'Movimiento de sales';
            } else {
                $estado = 'Retencion normal';
            }

            $csv->insertOne([
                $analysis->analyzed_at->format('Y-m-d'),
                $analysis->analyzed_at->format('H:i:s'),
                $analysis->lote->name,
                $analysis->location->name,
                $analysis->sensorSuperficial->code,
                $analysis->sensorProfundo->code,
                $ce_sup,
                $ce_prof,
                $delta,
                $ratio,
                $threshold,
                $analysis->lixiviation_detected ? 'SÍ' : 'NO',
                ucfirst(strtolower($analysis->risk_level)),
                $estado,
            ]);
        }

        // Retornar contenido o guardar archivo
        if ($outputPath) {
            $csv->output($outputPath);
            return $outputPath;
        }

        return (string) $csv;
    }

    /**
     * Generar reporte PDF (requiere DomPDF o similar)
     * Este es un esquema básico - requiere que instales una librería PDF
     */
    public function generateAnalysisReport(
        $analyses,
        array $options = []
    ): string {
        $title = $options['title'] ?? 'Reporte de Análisis de Lixiviación';
        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;

        // Normalizar entrada
        if (!is_array($analyses) && !($analyses instanceof Collection)) {
            $analyses = [$analyses];
        }

        // Agrupar por lote
        $byLote = collect($analyses)->groupBy('lote_id');

        $htmlContent = $this->buildReportHTML($title, $byLote, $startDate, $endDate);

        // Si tienes DomPDF instalado, puedes generar PDF
        // De lo contrario, retornar HTML
        return $htmlContent;
    }

    /**
     * Construir HTML del reporte
     */
    private function buildReportHTML(
        string $title,
        array $byLote,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): string {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2c3e50; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #2c3e50; }
        .header p { margin: 5px 0; color: #7f8c8d; }
        .lote-section { margin-bottom: 40px; page-break-inside: avoid; }
        .lote-title { background-color: #34495e; color: white; padding: 10px 15px; margin-bottom: 15px; border-radius: 3px; }
        .summary { background-color: #ecf0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #3498db; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .summary-item { background: white; padding: 10px; border-radius: 3px; text-align: center; }
        .summary-item .value { font-size: 1.5em; font-weight: bold; color: #3498db; }
        .summary-item .label { font-size: 0.9em; color: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #34495e; color: white; padding: 10px; text-align: left; font-size: 0.9em; }
        td { padding: 8px; border-bottom: 1px solid #bdc3c7; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .danger { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .success { color: #27ae60; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; color: #95a5a6; font-size: 0.9em; border-top: 1px solid #bdc3c7; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <p>Generado: {$this->formatDate(now())}</p>
HTML;

        if ($startDate && $endDate) {
            $html .= "<p>Período: {$this->formatDate($startDate)} a {$this->formatDate($endDate)}</p>";
        }

        $html .= "</div>";

        // Contenido por lote
        foreach ($byLote as $loteId => $analyses) {
            $lote = $analyses[0]->lote;
            $loxivationCount = collect($analyses)->where('lixiviation_detected', true)->count();
            $avgDelta = collect($analyses)->avg('delta_conductivity');
            $maxRisk = collect($analyses)->max('risk_percentage');
            $loxivationClass = $loxivationCount > 0 ? 'danger' : 'success';

            $html .= <<<HTML
            <div class="lote-section">
                <div class="lote-title">{$lote->name}</div>
                <div class="summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="value">{$analyses->count()}</div>
                            <div class="label">Análisis</div>
                        </div>
                        <div class="summary-item">
                            <div class="value {$loxivationClass}">{$loxivationCount}</div>
                            <div class="label">Lixiviaciones Detectadas</div>
                        </div>
                        <div class="summary-item">
                            <div class="value">{$maxRisk}%</div>
                            <div class="label">Riesgo Máximo</div>
                        </div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Ubicación</th>
                            <th>Conductividad Superficial</th>
                            <th>Conductividad Profunda</th>
                            <th>Delta (µS/cm)</th>
                            <th>Nivel de Riesgo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
HTML;

            foreach ($analyses as $analysis) {
                $dateTime = $analysis->analyzed_at->format('d/m/Y H:i:s');
                $location = $analysis->location->name;
                $condSup = $analysis->conductivity_superficial ?? 'N/A';
                $condProf = $analysis->conductivity_profundo ?? 'N/A';
                $delta = $analysis->delta_conductivity;
                $riskLevel = ucfirst($analysis->risk_level);
                $status = $analysis->lixiviation_detected ? '<span class="danger">⚠ LIXIVIACIÓN</span>' : '<span class="success">✓ OK</span>';

                $html .= <<<HTML
                        <tr>
                            <td>{$dateTime}</td>
                            <td>{$location}</td>
                            <td>{$condSup}</td>
                            <td>{$condProf}</td>
                            <td>{$delta}</td>
                            <td>{$riskLevel}</td>
                            <td>{$status}</td>
                        </tr>
HTML;
            }

            $html .= "</tbody></table></div>";
        }

        $html .= '<div class="footer"><p>Reporte generado automáticamente por AgroLixiSync</p></div></body></html>';

        return $html;
    }

    /**
     * Formato de fecha legible
     */
    private function formatDate(Carbon $date): string
    {
        return $date->format('d/m/Y H:i:s');
    }
}
