<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
        }

        .page-break {
            page-break-after: always;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }

        header p {
            color: #7f8c8d;
            font-size: 12px;
            margin: 5px 0;
        }

        .summary-section {
            background-color: #ecf0f1;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .summary-item {
            background: white;
            padding: 10px;
            border-radius: 3px;
            text-align: center;
            border-top: 2px solid #3498db;
        }

        .summary-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .summary-item .label {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        table thead {
            background-color: #34495e;
            color: white;
        }

        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #2c3e50;
        }

        table td {
            padding: 8px;
            border-bottom: 1px solid #bdc3c7;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: #ecf0f1;
        }

        .danger {
            color: #e74c3c;
            font-weight: bold;
        }

        .warning {
            color: #f39c12;
            font-weight: bold;
        }

        .success {
            color: #27ae60;
            font-weight: bold;
        }

        .section-title {
            background-color: #34495e;
            color: white;
            padding: 10px 15px;
            margin: 20px 0 10px 0;
            border-radius: 3px;
            font-size: 14px;
            font-weight: bold;
        }

        .timestamp {
            text-align: right;
            font-size: 10px;
            color: #95a5a6;
            margin-top: 20px;
            border-top: 1px solid #bdc3c7;
            padding-top: 10px;
        }

        .data-pair {
            margin-bottom: 15px;
        }

        .data-label {
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            width: 200px;
        }

        .data-value {
            display: inline-block;
        }
    </style>
</head>
<body>
    <header>
        <h1>{{ $title }}</h1>
        <p>AGROlixisync - Sistema de Monitoreo IoT</p>
        @if($dateRange)
            <p>Período: {{ $dateRange['start'] ?? 'N/A' }} al {{ $dateRange['end'] ?? 'N/A' }}</p>
        @endif
    </header>

    {{-- Resumen General --}}
    <div class="summary-section">
        <h3 style="margin-bottom: 10px; color: #2c3e50;">📊 Resumen General</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="value">{{ $summary['total_analyses'] ?? 0 }}</div>
                <div class="label">Total de Análisis</div>
            </div>
            <div class="summary-item">
                <div class="value" style="color: #e74c3c;">{{ $summary['lixiviation_detected'] ?? 0 }}</div>
                <div class="label">Eventos de Lixiviación</div>
            </div>
            <div class="summary-item">
                <div class="value" style="color: #f39c12;">{{ $summary['high_risk'] ?? 0 }}</div>
                <div class="label">Riesgo Alto</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($summary['average_delta'] ?? 0, 2) }}</div>
                <div class="label">Δ Promedio (µS/cm)</div>
            </div>
        </div>
    </div>

    {{-- Estadísticas Detalladas --}}
    <div class="section-title">📋 Estadísticas Detalladas de Riesgo</div>
    <div class="data-pair">
        <span class="data-label">Riesgo Medio:</span>
        <span class="data-value">{{ $summary['medium_risk'] ?? 0 }} eventos</span>
    </div>
    <div class="data-pair">
        <span class="data-label">Riesgo Bajo:</span>
        <span class="data-value">{{ $summary['low_risk'] ?? 0 }} eventos</span>
    </div>
    <div class="data-pair">
        <span class="data-label">Delta Máximo:</span>
        <span class="data-value">{{ number_format($summary['max_delta'] ?? 0, 2) }} µS/cm</span>
    </div>
    <div class="data-pair">
        <span class="data-label">Delta Mínimo:</span>
        <span class="data-value">{{ number_format($summary['min_delta'] ?? 0, 2) }} µS/cm</span>
    </div>
    <div class="data-pair">
        <span class="data-label">Riesgo Promedio:</span>
        <span class="data-value">{{ number_format($summary['average_risk'] ?? 0, 2) }}%</span>
    </div>

    {{-- Tabla de Análisis --}}
    <div class="section-title">📊 Detalle de Análisis</div>
    @if($analyses && count($analyses) > 0)
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Ubicación</th>
                    <th>Δ CE (µS/cm)</th>
                    <th>Umbral (µS/cm)</th>
                    <th>Lixiviación</th>
                    <th>Nivel de Riesgo</th>
                    <th>Riesgo %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($analyses as $analysis)
                    <tr>
                        <td>
                            @if(is_object($analysis) && $analysis->analyzed_at)
                                {{ $analysis->analyzed_at->format('Y-m-d H:i:s') }}
                            @else
                                {{ $analysis['analyzed_at'] ?? 'N/A' }}
                            @endif
                        </td>
                        <td>
                            @if(is_object($analysis))
                                {{ $analysis->location->name ?? 'N/A' }}
                            @else
                                {{ $analysis['location']['name'] ?? 'N/A' }}
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if(is_object($analysis))
                                {{ number_format($analysis->delta_conductivity, 2) }}
                            @else
                                {{ number_format($analysis['delta_conductivity'] ?? 0, 2) }}
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if(is_object($analysis))
                                {{ number_format($analysis->threshold_used, 2) }}
                            @else
                                {{ number_format($analysis['threshold_used'] ?? 0, 2) }}
                            @endif
                        </td>
                        <td>
                            @if(is_object($analysis))
                                <span class="@if($analysis->lixiviation_detected) danger @else success @endif">
                                    @if($analysis->lixiviation_detected)
                                        SÍ 🔴
                                    @else
                                        NO ✅
                                    @endif
                                </span>
                            @else
                                <span class="@if($analysis['lixiviation_detected']) danger @else success @endif">
                                    @if($analysis['lixiviation_detected'])
                                        SÍ 🔴
                                    @else
                                        NO ✅
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td>
                            @if(is_object($analysis))
                                <span class="@if($analysis->risk_level === 'alto') danger @elseif($analysis->risk_level === 'medio') warning @else success @endif">
                                    {{ ucfirst($analysis->risk_level) }}
                                </span>
                            @else
                                <span class="@if($analysis['risk_level'] === 'alto') danger @elseif($analysis['risk_level'] === 'medio') warning @else success @endif">
                                    {{ ucfirst($analysis['risk_level'] ?? 'N/A') }}
                                </span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if(is_object($analysis))
                                {{ number_format($analysis->risk_percentage, 1) }}%
                            @else
                                {{ number_format($analysis['risk_percentage'] ?? 0, 1) }}%
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; color: #7f8c8d; padding: 20px;">No hay datos de análisis para mostrar.</p>
    @endif

    <div class="timestamp">
        Reporte generado: {{ $generated_at }} | AGROlixisync v2.0
    </div>
</body>
</html>
