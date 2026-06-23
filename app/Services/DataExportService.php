<?php

namespace App\Services;

use App\Models\Lectura;
use App\Models\Sensor;
use App\Models\AnalisisLixiviacion;
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
        $readings = Lectura::whereIn('sensor_id', $sensorIds)
            ->whereBetween('fecha_registro', [$startDate, $endDate])
            ->orderBy('fecha_registro')
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
            $ce_us = $reading->conductividad ?? null;
            $ce_ds = $ce_us !== null ? round($ce_us / 1000, 3) : 'N/A';

            $csv->insertOne([
                $reading->fecha_registro->format('Y-m-d'),
                $reading->fecha_registro->format('H:i:s'),
                $reading->sensor->codigo,
                $reading->sensor->nombre ?? 'N/A',
                $reading->sensor->profundidad ?? 'N/A',
                $reading->temperatura ?? 'N/A',
                $reading->humedad ?? 'N/A',
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
            'Planta',
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
            $ce_sup  = $analysis->conductividad_superficial !== null
                ? round($analysis->conductividad_superficial / 1000, 3) : 'N/A';
            $ce_prof = $analysis->conductividad_profundo !== null
                ? round($analysis->conductividad_profundo / 1000, 3) : 'N/A';
            $delta   = $analysis->delta_conductividad !== null
                ? round($analysis->delta_conductividad / 1000, 3) : 'N/A';
            $ratio   = ($analysis->conductividad_superficial > 0 && $analysis->conductividad_profundo !== null)
                ? round($analysis->conductividad_profundo / $analysis->conductividad_superficial, 3) : 'N/A';
            $threshold = round($analysis->umbral_usado / 1000, 3);

            if ($analysis->lixiviacion_detectada) {
                $estado = 'Perdida de fertilizante';
            } elseif ($analysis->nivel_riesgo === 'MEDIO') {
                $estado = 'Movimiento de sales';
            } else {
                $estado = 'Retencion normal';
            }

            $csv->insertOne([
                $analysis->fecha_analisis->format('Y-m-d'),
                $analysis->fecha_analisis->format('H:i:s'),
                $analysis->planta->nombre,
                $analysis->ubicacion->nombre,
                $analysis->sensorSuperficial->codigo,
                $analysis->sensorProfundo->codigo,
                $ce_sup,
                $ce_prof,
                $delta,
                $ratio,
                $threshold,
                $analysis->lixiviacion_detectada ? 'SÍ' : 'NO',
                ucfirst(strtolower($analysis->nivel_riesgo)),
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
     * Generar reporte HTML de análisis
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

        // Agrupar por planta
        $byLote = collect($analyses)->groupBy('planta_id');

        $htmlContent = $this->buildReportHTML($title, $byLote->toArray(), $startDate, $endDate);

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

        // Contenido por planta
        foreach ($byLote as $plantaId => $analysesList) {
            $analyses = collect($analysesList);
            $planta = $analyses->first()->planta;
            $loxivationCount = $analyses->where('lixiviacion_detectada', true)->count();
            $avgDelta = $analyses->avg('delta_conductividad');
            $maxRisk = $analyses->max('porcentaje_riesgo');
            $loxivationClass = $loxivationCount > 0 ? 'danger' : 'success';

            $html .= <<<HTML
            <div class="lote-section">
                <div class="lote-title">{$planta->nombre}</div>
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
                            <th>Delta (dS/m)</th>
                            <th>Nivel de Riesgo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
HTML;

            foreach ($analyses as $analysis) {
                $dateTime = $analysis->fecha_analisis->format('d/m/Y H:i:s');
                $locationName = $analysis->ubicacion->nombre;
                $condSup = $analysis->conductividad_superficial ?? 'N/A';
                $condProf = $analysis->conductividad_profundo ?? 'N/A';
                $delta = $analysis->delta_conductividad;
                $riskLevel = ucfirst($analysis->nivel_riesgo);
                $status = $analysis->lixiviacion_detectada ? '<span class="danger">⚠ LIXIVIACIÓN</span>' : '<span class="success">✓ OK</span>';

                $html .= <<<HTML
                        <tr>
                            <td>{$dateTime}</td>
                            <td>{$locationName}</td>
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
