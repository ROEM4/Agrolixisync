{{-- 
  Componente: Thesis Metrics KPI Cards
  
  Muestra indicadores académicos en el dashboard:
  - TAR (Tiempo de Alerta de Riesgo)
  - PDS (Precisión del Diagnóstico)
  - NCES (Nivel de Conductividad Eléctrica)
  
  Uso en blade:
  @include('components.thesis-kpi-cards', ['location' => $location])
--}}

<div class="row mt-4">
    <div class="col-12">
        <h4 class="mb-3">
            <i class="fas fa-graduation-cap"></i> Indicadores de Tesis Académica
        </h4>
    </div>
</div>

<div class="row">
    {{-- TAR - Tiempo de Alerta de Riesgo --}}
    <div class="col-md-4 mb-3">
        <div class="card border-info h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-hourglass-end"></i> TAR
                    <small class="d-block">Tiempo de Alerta de Riesgo</small>
                </h5>
            </div>
            <div class="card-body">
                @if($thesisMetric && $thesisMetric->tar_minutes)
                    <p class="h3 text-info mb-2">
                        {{ number_format($thesisMetric->tar_minutes, 1) }} <small>min</small>
                    </p>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" style="width: {{ min($thesisMetric->tar_minutes / 10 * 100, 100) }}%"></div>
                    </div>
                    <small class="text-muted d-block">
                        <i class="fas fa-check-circle text-success"></i>
                        Muestras: {{ $thesisMetric->tar_sample_count }}
                    </small>
                    <small class="text-muted d-block">
                        <i class="fas fa-clock"></i>
                        Calculado: {{ $thesisMetric->tar_calculated_at->format('M d, H:i') }}
                    </small>
                    
                    {{-- Interpretación --}}
                    @php
                        $tarInterpretation = match(true) {
                            $thesisMetric->tar_minutes < 5 => ['class' => 'success', 'text' => 'Excelente < 5min'],
                            $thesisMetric->tar_minutes < 15 => ['class' => 'info', 'text' => 'Bueno 5-15min'],
                            $thesisMetric->tar_minutes < 30 => ['class' => 'warning', 'text' => 'Regular 15-30min'],
                            default => ['class' => 'danger', 'text' => 'Crítico > 30min']
                        };
                    @endphp
                    <div class="alert alert-{{ $tarInterpretation['class'] }} py-2 mt-2 mb-0">
                        <small>{{ $tarInterpretation['text'] }}</small>
                    </div>
                @else
                    <div class="alert alert-secondary py-2 text-center">
                        <small><i class="fas fa-info-circle"></i> Sin datos disponibles</small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- PDS - Precisión del Diagnóstico --}}
    <div class="col-md-4 mb-3">
        <div class="card border-success h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-crosshairs"></i> PDS
                    <small class="d-block">Precisión del Diagnóstico</small>
                </h5>
            </div>
            <div class="card-body">
                @if($thesisMetric && $thesisMetric->pds_percentage)
                    <p class="h3 text-success mb-2">
                        {{ number_format($thesisMetric->pds_percentage, 1) }} <small>%</small>
                    </p>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" style="width: {{ $thesisMetric->pds_percentage }}%"></div>
                    </div>
                    <small class="text-muted d-block">
                        <i class="fas fa-check-circle text-success"></i>
                        Correctas: {{ $thesisMetric->pds_correct_detections }}/{{ $thesisMetric->pds_total_tests }}
                    </small>
                    <small class="text-muted d-block">
                        <i class="fas fa-times-circle text-danger"></i>
                        FP: {{ $thesisMetric->pds_false_positives }} / FN: {{ $thesisMetric->pds_false_negatives }}
                    </small>
                    <small class="text-muted d-block">
                        <i class="fas fa-clock"></i>
                        Calculado: {{ $thesisMetric->pds_calculated_at->format('M d, H:i') }}
                    </small>

                    {{-- Interpretación --}}
                    @php
                        $pdsInterpretation = match(true) {
                            $thesisMetric->pds_percentage >= 95 => ['class' => 'success', 'text' => 'Excelente ≥ 95%'],
                            $thesisMetric->pds_percentage >= 85 => ['class' => 'info', 'text' => 'Bueno 85-95%'],
                            $thesisMetric->pds_percentage >= 75 => ['class' => 'warning', 'text' => 'Regular 75-85%'],
                            default => ['class' => 'danger', 'text' => 'Crítico < 75%']
                        };
                    @endphp
                    <div class="alert alert-{{ $pdsInterpretation['class'] }} py-2 mt-2 mb-0">
                        <small>{{ $pdsInterpretation['text'] }}</small>
                    </div>
                @else
                    <div class="alert alert-secondary py-2 text-center">
                        <small><i class="fas fa-info-circle"></i> Sin datos disponibles</small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- NCES - Nivel de Conductividad Eléctrica --}}
    <div class="col-md-4 mb-3">
        <div class="card border-warning h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-water"></i> NCES
                    <small class="d-block">Diferencial de CE (µS/cm)</small>
                </h5>
            </div>
            <div class="card-body">
                @if($thesisMetric && $thesisMetric->nces_difference !== null)
                    <p class="h3 mb-2" style="color: {{ $thesisMetric->nces_difference > 0 ? '#dc3545' : '#28a745' }}">
                        {{ number_format($thesisMetric->nces_difference, 1) }} <small>µS/cm</small>
                    </p>
                    
                    {{-- Gráfico simple de comparación --}}
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <small class="d-block text-muted">Control</small>
                                <p class="h5 mb-0">
                                    {{ number_format($thesisMetric->nces_control_avg, 0) }}
                                </p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <small class="d-block text-muted">Experimental</small>
                                <p class="h5 mb-0">
                                    {{ number_format($thesisMetric->nces_experimental_avg, 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <small class="text-muted d-block">
                        <i class="fas fa-flask"></i>
                        Muestras: C:{{ $thesisMetric->nces_control_samples }} / E:{{ $thesisMetric->nces_experimental_samples }}
                    </small>
                    <small class="text-muted d-block">
                        <i class="fas fa-clock"></i>
                        Calculado: {{ $thesisMetric->nces_calculated_at->format('M d, H:i') }}
                    </small>

                    {{-- Interpretación --}}
                    @php
                        $ncesInterpretation = match(true) {
                            $thesisMetric->nces_difference > 50 => ['class' => 'danger', 'text' => 'Control alta lixiviación'],
                            $thesisMetric->nces_difference > 0 => ['class' => 'warning', 'text' => 'Control > Experimental'],
                            $thesisMetric->nces_difference > -50 => ['class' => 'info', 'text' => 'Experimental > Control'],
                            default => ['class' => 'success', 'text' => 'Experimental alta lixiviación']
                        };
                    @endphp
                    <div class="alert alert-{{ $ncesInterpretation['class'] }} py-2 mt-2 mb-0">
                        <small>{{ $ncesInterpretation['text'] }}</small>
                    </div>
                @else
                    <div class="alert alert-secondary py-2 text-center">
                        <small><i class="fas fa-info-circle"></i> Sin datos disponibles</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Información del Período --}}
@if($thesisMetric)
<div class="row mt-3">
    <div class="col-12">
        <div class="alert alert-light border-left-4 border-primary">
            <small class="text-muted">
                <i class="fas fa-calendar"></i>
                <strong>Período:</strong> 
                {{ $thesisMetric->period_start_date->format('d/m/Y') }} 
                a 
                {{ $thesisMetric->period_end_date->format('d/m/Y') }}
            </small>
        </div>
    </div>
</div>
@endif

<style>
.border-left-4 {
    border-left: 4px solid !important;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}
</style>
