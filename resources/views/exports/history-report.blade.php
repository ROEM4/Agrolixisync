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
            line-height: 1.5;
            color: #333;
            background-color: #fff;
            font-size: 10px;
        }

        header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
        }

        header h1 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 20px;
        }

        header p {
            color: #7f8c8d;
            font-size: 10px;
            margin: 3px 0;
        }

        .summary-section {
            background-color: #ecf0f1;
            border-left: 4px solid #3498db;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 3px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 8px;
        }

        .summary-item {
            background: white;
            padding: 8px;
            border-radius: 3px;
            text-align: center;
            border: 1px solid #bdc3c7;
        }

        .summary-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }

        .summary-item .label {
            font-size: 9px;
            color: #7f8c8d;
            margin-top: 3px;
        }

        .section-title {
            background-color: #34495e;
            color: white;
            padding: 8px 12px;
            margin: 15px 0 8px 0;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }

        table thead {
            background-color: #34495e;
            color: white;
        }

        table th {
            padding: 6px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #2c3e50;
        }

        table td {
            padding: 5px 6px;
            border-bottom: 1px solid #bdc3c7;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .sensor-info {
            background-color: #ecf0f1;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            border-left: 3px solid #3498db;
        }

        .sensor-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .timestamp {
            text-align: right;
            font-size: 9px;
            color: #95a5a6;
            margin-top: 15px;
            border-top: 1px solid #bdc3c7;
            padding-top: 8px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <header>
        <h1>{{ $title }}</h1>
        <p>AGROlixisync - Historial de Lecturas</p>
        <p>Período: {{ $startDate }} al {{ $endDate }}</p>
    </header>

    {{-- Resumen General --}}
    <div class="summary-section">
        <h3 style="margin-bottom: 8px; color: #2c3e50; font-size: 12px;">📊 Resumen de Lecturas</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="value">{{ $summary['total_readings'] ?? 0 }}</div>
                <div class="label">Total de Lecturas</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($summary['average_temperature'] ?? 0, 1) }}°C</div>
                <div class="label">T° Promedio</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($summary['average_humidity'] ?? 0, 1) }}%</div>
                <div class="label">H Promedio</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($summary['average_conductivity'] ?? 0, 0) }}</div>
                <div class="label">CE Promedio</div>
            </div>
        </div>
    </div>

    {{-- Información de Sensores --}}
    <div class="section-title">🔍 Sensores Monitoreados</div>
    @foreach($sensors as $sensor)
        <div class="sensor-info">
            <div class="sensor-name">{{ $sensor->name ?? $sensor->code }}</div>
            <div style="font-size: 9px; color: #7f8c8d;">
                Código: {{ $sensor->code }} | Tipo: {{ $sensor->sensorType->name ?? 'N/A' }} | Profundidad: {{ $sensor->depth ?? 0 }} cm
            </div>
        </div>
    @endforeach

    {{-- Estadísticas de Rango de Temperatura --}}
    <div class="section-title">🌡️ Estadísticas de Temperatura</div>
    <table>
        <tbody>
            <tr>
                <td style="width: 30%; font-weight: bold;">Temperatura Máxima</td>
                <td style="text-align: center;">{{ number_format($summary['max_temperature'] ?? 0, 1) }}°C</td>
                <td style="width: 30%; font-weight: bold;">Temperatura Mínima</td>
                <td style="text-align: center;">{{ number_format($summary['min_temperature'] ?? 0, 1) }}°C</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Temperatura Promedio</td>
                <td style="text-align: center;">{{ number_format($summary['average_temperature'] ?? 0, 1) }}°C</td>
                <td style="font-weight: bold;">Rango</td>
                <td style="text-align: center;">{{ number_format(($summary['max_temperature'] ?? 0) - ($summary['min_temperature'] ?? 0), 1) }}°C</td>
            </tr>
        </tbody>
    </table>

    {{-- Estadísticas de Humedad --}}
    <div class="section-title">💧 Estadísticas de Humedad</div>
    <table>
        <tbody>
            <tr>
                <td style="width: 30%; font-weight: bold;">Humedad Máxima</td>
                <td style="text-align: center;">{{ number_format($summary['max_humidity'] ?? 0, 1) }}%</td>
                <td style="width: 30%; font-weight: bold;">Humedad Mínima</td>
                <td style="text-align: center;">{{ number_format($summary['min_humidity'] ?? 0, 1) }}%</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Humedad Promedio</td>
                <td style="text-align: center;">{{ number_format($summary['average_humidity'] ?? 0, 1) }}%</td>
                <td style="font-weight: bold;">Rango</td>
                <td style="text-align: center;">{{ number_format(($summary['max_humidity'] ?? 0) - ($summary['min_humidity'] ?? 0), 1) }}%</td>
            </tr>
        </tbody>
    </table>

    {{-- Tabla Detallada de Lecturas --}}
    <div class="section-title">📋 Detalle de Todas las Lecturas</div>
    @if($readings && count($readings) > 0)
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Sensor</th>
                    <th>Temperatura (°C)</th>
                    <th>Humedad (%)</th>
                    <th>Conductividad (µS/cm)</th>
                    <th>Humedad Suelo (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($readings as $reading)
                    <tr>
                        <td>{{ $reading->recorded_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                        <td>{{ $reading->sensor->code ?? 'N/A' }}</td>
                        <td style="text-align: center;">{{ number_format($reading->temperature ?? 0, 1) }}</td>
                        <td style="text-align: center;">{{ number_format($reading->humidity ?? 0, 1) }}</td>
                        <td style="text-align: center;">{{ number_format($reading->conductivity ?? 0, 0) }}</td>
                        <td style="text-align: center;">{{ number_format($reading->soil_moisture ?? 0, 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; color: #7f8c8d; padding: 15px;">No hay lecturas registradas en este período.</p>
    @endif

    <div class="timestamp">
        Reporte generado: {{ $generated_at }} | AGROlixisync v2.0 | Total de registros: {{ count($readings) ?? 0 }}
    </div>
</body>
</html>
