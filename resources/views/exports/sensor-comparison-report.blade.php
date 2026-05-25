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
            font-size: 11px;
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
            font-size: 24px;
        }

        header p {
            color: #7f8c8d;
            font-size: 11px;
            margin: 5px 0;
        }

        .location-info {
            background-color: #ecf0f1;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }

        .location-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .location-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
        }

        .detail-label {
            font-weight: bold;
            color: #2c3e50;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: white;
            padding: 12px;
            border-radius: 3px;
            text-align: center;
            border-top: 2px solid #3498db;
            border: 1px solid #bdc3c7;
        }

        .summary-item .value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }

        .summary-item .label {
            font-size: 10px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .section-title {
            background-color: #34495e;
            color: white;
            padding: 10px 15px;
            margin: 20px 0 10px 0;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }

        table thead {
            background-color: #34495e;
            color: white;
        }

        table th {
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #2c3e50;
        }

        table td {
            padding: 6px 8px;
            border-bottom: 1px solid #bdc3c7;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
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

        .sensor-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            margin-right: 5px;
        }

        .superficial {
            background-color: #dbeafe;
            color: #0c4a6e;
        }

        .profundo {
            background-color: #fce7f3;
            color: #831843;
        }

        .timestamp {
            text-align: right;
            font-size: 9px;
            color: #95a5a6;
            margin-top: 20px;
            border-top: 1px solid #bdc3c7;
            padding-top: 10px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <header>
        <h1>{{ $title }}</h1>
        <p>AGROlixisync - Comparativa de Sensores</p>
        @if($dateRange)
            <p>Período: {{ $dateRange['start'] ?? 'N/A' }} al {{ $dateRange['end'] ?? 'N/A' }}</p>
        @endif
    </header>

    {{-- Información de Ubicación --}}
    <div class="location-info">
        <h3>📍 Información de Ubicación</h3>
        <div class="location-details">
            <div class="detail-item">
                <span class="detail-label">Ubicación:</span>
                <span>{{ $location->name ?? 'N/A' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Descripción:</span>
                <span>{{ $location->description ?? 'N/A' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Coordenadas:</span>
                <span>{{ $location->latitude ?? 'N/A' }}, {{ $location->longitude ?? 'N/A' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado:</span>
                <span>{{ $location->is_active ? 'Activa' : 'Inactiva' }}</span>
            </div>
        </div>
    </div>

    {{-- Información de Sensores --}}
    <div class="section-title">🔍 Sensores Configurados</div>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Profundidad</th>
                <th>Estado</th>
                <th>Última Lectura</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="sensor-badge superficial">SUPERFICIAL</span></td>
                <td>{{ $superficialSensor->code ?? 'N/A' }}</td>
                <td>{{ $superficialSensor->name ?? 'N/A' }}</td>
                <td>{{ $superficialSensor->depth ?? 0 }} cm</td>
                <td><span class="success">{{ $superficialSensor->status ?? 'Activo' }}</span></td>
                <td>{{ $superficialSensor->last_reading_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><span class="sensor-badge profundo">PROFUNDO</span></td>
                <td>{{ $deepSensor->code ?? 'N/A' }}</td>
                <td>{{ $deepSensor->name ?? 'N/A' }}</td>
                <td>{{ $deepSensor->depth ?? 'N/A' }} cm</td>
                <td><span class="success">{{ $deepSensor->status ?? 'Activo' }}</span></td>
                <td>{{ $deepSensor->last_reading_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Resumen de Análisis --}}
    <div class="section-title">📊 Resumen de Análisis</div>
    <div class="summary-grid">
        <div class="summary-item">
            <div class="value">{{ $summary['total_analyses'] ?? 0 }}</div>
            <div class="label">Total de Análisis</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #e74c3c;">{{ $summary['lixiviation_events'] ?? 0 }}</div>
            <div class="label">Eventos de Lixiviación</div>
        </div>
        <div class="summary-item">
            <div class="value">{{ number_format($summary['lixiviation_rate'] ?? 0, 1) }}%</div>
            <div class="label">Tasa de Lixiviación</div>
        </div>
        <div class="summary-item">
            <div class="value">{{ number_format($summary['average_delta'] ?? 0, 2) }}</div>
            <div class="label">Δ Promedio (µS/cm)</div>
        </div>
        <div class="summary-item">
            <div class="value">{{ number_format($summary['max_delta'] ?? 0, 2) }}</div>
            <div class="label">Δ Máximo (µS/cm)</div>
        </div>
        <div class="summary-item">
            <div class="value" style="font-size: 12px;">{{ $summary['current_status'] ?? 'Desconocido' }}</div>
            <div class="label">Estado Actual</div>
        </div>
    </div>

    {{-- Tabla de Análisis Reciente --}}
    <div class="section-title">📋 Últimos Análisis Realizados</div>
    @if($analyses && count($analyses) > 0)
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>CE Sup (µS/cm)</th>
                    <th>CE Prof (µS/cm)</th>
                    <th>Δ CE (µS/cm)</th>
                    <th>Umbral (µS/cm)</th>
                    <th>Lixiviación</th>
                    <th>Riesgo</th>
                    <th>% Riesgo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($analyses->take(20) as $analysis)
                    <tr>
                        <td>{{ $analysis->analyzed_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                        <td style="text-align: center;">{{ number_format($analysis->conductivity_superficial ?? 0, 0) }}</td>
                        <td style="text-align: center;">{{ number_format($analysis->conductivity_profundo ?? 0, 0) }}</td>
                        <td style="text-align: center;">{{ number_format($analysis->delta_conductivity ?? 0, 2) }}</td>
                        <td style="text-align: center;">{{ number_format($analysis->threshold_used ?? 0, 2) }}</td>
                        <td>
                            <span class="@if($analysis->lixiviation_detected) danger @else success @endif">
                                @if($analysis->lixiviation_detected)
                                    SÍ 🔴
                                @else
                                    NO ✅
                                @endif
                            </span>
                        </td>
                        <td>
                            <span class="@if($analysis->risk_level === 'alto') danger @elseif($analysis->risk_level === 'medio') warning @else success @endif">
                                {{ ucfirst($analysis->risk_level) }}
                            </span>
                        </td>
                        <td style="text-align: center;">{{ number_format($analysis->risk_percentage ?? 0, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; color: #7f8c8d; padding: 20px;">No hay análisis registrados en este período.</p>
    @endif

    <div class="timestamp">
        Reporte generado: {{ $generated_at }} | AGROlixisync v2.0
    </div>
</body>
</html>
