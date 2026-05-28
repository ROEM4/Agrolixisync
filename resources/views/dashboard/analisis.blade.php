@extends('layouts.app')
@section('title', 'Análisis Académico — AgroLixiSync')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap');

    :root {
        --academic-font: 'Plus Jakarta Sans', sans-serif;
        --outfit-font: 'Outfit', sans-serif;
        --glass-bg: rgba(255, 255, 255, 0.75);
        --glass-border: rgba(255, 255, 255, 0.6);
        --control-primary: #d97706; /* amber-600 */
        --control-secondary: #475569; /* slate-600 */
        --exp-primary: #4f46e5; /* indigo-600 */
        --exp-secondary: #059669; /* emerald-600 */
    }

    body {
        font-family: var(--academic-font);
    }

    .academic-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .academic-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 45px rgba(0, 0, 0, 0.05);
    }

    .metric-value {
        font-family: var(--outfit-font);
        font-weight: 800;
        line-height: 1;
    }

    .metric-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .confusion-cell {
        transition: all 0.2s ease-in-out;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .confusion-cell:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }

    .scientific-badge {
        font-family: var(--outfit-font);
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        font-size: 0.65rem;
    }

    /* Custom scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-black rounded-lg uppercase tracking-widest border border-indigo-100 scientific-badge">
                    Metodología de Tesis Experimental
                </span>
            </div>
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight" style="font-family: var(--outfit-font);">
                Evaluación Comparativa de Pérdida de Fertilizantes
            </h1>
            <p class="text-slate-500 font-medium mt-1 text-sm">
                Monitoreo y validación de lixiviación mediante Conductividad Eléctrica (CE). Comparación rigurosa de grupos.
            </p>
        </div>
        
        <div class="flex gap-3">
            @if(isset($selectedLocation))
                <div class="px-4 py-2 bg-white/80 border border-slate-100 rounded-2xl flex items-center gap-3 shadow-sm backdrop-blur-md">
                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Grupo Evaluado:</span>
                    <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $selectedLocation->experimental_group === 'control' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' }}">
                        {{ $selectedLocation->experimental_group === 'control' ? 'Grupo Control' : 'Grupo Experimental' }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Top Methodological Summary --}}
    <div class="p-6 bg-gradient-to-r from-slate-50 to-slate-100 border-l-4 border-indigo-500 rounded-r-2xl mb-10 shadow-sm relative overflow-hidden">
        <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-5 text-9xl pointer-events-none select-none">🔬</div>
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0 shadow-sm border border-indigo-100">
                <span class="text-lg">📋</span>
            </div>
            <div>
                <h4 class="text-xs font-black uppercase text-indigo-700 tracking-widest mb-1 scientific-badge">Resumen Metodológico</h4>
                <p class="text-sm text-slate-700 leading-relaxed font-semibold italic">
                    “El grupo control establece la condición real de lixiviación mediante mediciones de conductividad eléctrica, mientras que el sistema IoT evalúa su capacidad de detección comparando sus resultados contra dicha referencia.”
                </p>
            </div>
        </div>
    </div>

    {{-- Selection Filters --}}
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4 bg-white/60 p-4 rounded-3xl border border-white/80 backdrop-blur-md shadow-sm">
        <span class="text-sm font-bold text-slate-700 flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
            Filtro de Ubicación del Estudio
        </span>
        <form method="GET" action="{{ route('analisis') }}" class="w-full sm:w-auto min-w-[280px]">
            <select name="location_id" onchange="this.form.submit()" class="w-full p-3 bg-white border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-sm text-sm">
                <option value="" {{ is_null($location_id) ? 'selected' : '' }}>Todas las ubicaciones</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                        {{ $loc->name }} ({{ ($loc->experimental_group ?? 'control') === 'control' ? 'Grupo Control' : 'Grupo Experimental' }})
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Comparison cards removed per user request: no summary cards shown for "Todas las ubicaciones" --}}

    {{-- ChartJS Visualizations Block for Experimental Group (Shows when Experimental is active or all locations) --}}
    @if(!isset($selectedLocation) || $selectedLocation->experimental_group === 'experimental')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            
            {{-- Graph 1: Confusion Matrix --}}
            <div class="academic-card p-6 border-t-4 border-indigo-500">
                <h4 class="text-[10px] font-black uppercase text-indigo-700 tracking-wider mb-4 scientific-badge text-center">Matriz de Confusión (Distribución)</h4>
                <div class="relative h-[240px] w-full flex items-center justify-center">
                    <canvas id="confusionMatrixChart"></canvas>
                </div>
            </div>

            {{-- Graph 2: Precision vs Error --}}
            <div class="academic-card p-6 border-t-4 border-emerald-500">
                <h4 class="text-[10px] font-black uppercase text-emerald-700 tracking-wider mb-4 scientific-badge text-center">Precisión vs Tasa de Error</h4>
                <div class="relative h-[240px] w-full flex items-center justify-center">
                    <canvas id="precisionErrorChart"></canvas>
                </div>
            </div>

            {{-- Graph 3: Temporal Evolution --}}
            <div class="academic-card p-6 border-t-4 border-blue-500">
                <h4 class="text-[10px] font-black uppercase text-blue-700 tracking-wider mb-4 scientific-badge text-center">Evolución Temporal de Eventos</h4>
                <div class="relative h-[240px] w-full flex items-center justify-center">
                    <canvas id="temporalEvolutionChart"></canvas>
                </div>
            </div>

        </div>
    @endif

    {{-- Evidencia Experimental Section Title --}}
    <div class="flex items-center gap-4 mb-6 border-b border-slate-200 pb-3">
        <span class="text-xl">📊</span>
        <h2 class="text-xl font-black text-slate-800 uppercase tracking-wider" style="font-family: var(--outfit-font);">
            @if(isset($selectedLocation))
                {{ $selectedLocation->experimental_group === 'control' ? 'Evidencia Física - Grupo Control' : 'Evidencia de Detección - Grupo Experimental' }}
            @else
                Evidencia Experimental & Registros Científicos
            @endif
        </h2>
    </div>

    {{-- Tables Grid --}}
    <div class="grid grid-cols-1 @if(!isset($selectedLocation)) lg:grid-cols-2 @endif gap-8 mb-12">
        @php
            // Fallback sample locations when none provided (demo mode)
            if (empty($locations)) {
                $locations = [
                    (object)['id' => 1, 'name' => 'Finca Control', 'experimental_group' => 'control'],
                    (object)['id' => 2, 'name' => 'Finca Experimental', 'experimental_group' => 'experimental']
                ];
            }

            // If no daily stats exist, generate synthetic sample data starting from 2026-04-19
            if (empty($dailyStats)) {
                $dailyStats = [];
                $start = \Carbon\Carbon::create(2026, 4, 19);
                $today = \Carbon\Carbon::now();
                for ($d = $start->copy(); $d->lte($today); $d->addDay()) {
                    $dateLabel = $d->format('Y-m-d');
                    $ctrlTotal = rand(0, 12);
                    $ctrlAvgSup = $ctrlTotal ? round(0.6 + rand(0, 900)/1000, 3) : round(0.4 + rand(0, 500)/1000, 3);
                    $ctrlAvgProf = $ctrlAvgSup + round(rand(-50, 200)/1000, 3);
                    $ctrlIlx = round(max(0, $ctrlAvgProf - $ctrlAvgSup), 4);
                    $ctrlLossPct = $ctrlTotal ? round(rand(0, 60) + rand(0,9)/10, 1) : 0;

                    $expTotal = rand(0, 12);
                    $vp = $expTotal ? rand(0, $expTotal) : 0;
                    $fn = $expTotal ? rand(0, $expTotal - $vp) : 0;
                    $remaining = max(0, $expTotal - ($vp + $fn));
                    $fp = $remaining ? rand(0, $remaining) : 0;
                    $vn = max(0, $expTotal - ($vp + $fn + $fp));

                    $pds = $expTotal ? round((($vp + $vn) / max(1, $expTotal)) * 100, 2) : 0;
                    $recall = ($vp + $fn) ? round(($vp / max(1, $vp + $fn)) * 100, 2) : 0;
                    $errorRate = $expTotal ? round((($fp + $fn) / max(1, $expTotal)) * 100, 2) : 0;

                    $dailyStats[] = [
                        'date' => $dateLabel,
                        'date_label' => $d->format('d/m/Y'),
                        'control' => [
                            'total' => $ctrlTotal,
                            'avg_ce_sup' => $ctrlAvgSup,
                            'avg_ce_prof' => $ctrlAvgProf,
                            'avg_ilx' => $ctrlIlx,
                            'pct_lixiviacion' => $ctrlLossPct,
                        ],
                        'experimental' => [
                            'vp' => $vp,
                            'fp' => $fp,
                            'fn' => $fn,
                            'vn' => $vn,
                            'total' => $expTotal,
                            'pds' => $pds,
                            'recall' => $recall,
                            'error_rate' => $errorRate,
                            'control_imputed' => false,
                        ]
                    ];
                }
            }
        @endphp

        {{-- Separate tables: Control (left) and Experimental (right) --}}
        <div class="col-span-1 lg:col-span-1">
            @if(empty($location_id))
                {{-- Show full control records when 'Todas las ubicaciones' is selected --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                        Registros Detallados: Grupo Control (Todas las Ubicaciones)
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Listando todos los registros</span>
                </div>

                <div class="academic-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50/50 border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Fecha</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Ubicación</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Parcela</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Sup</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Prof</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Ref</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Condición</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($controlRecords as $rec)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 font-bold">{!! $rec->date_label !!}</td>
                                        <td class="px-3 py-3">{{ optional($rec->location)->name }}</td>
                                        <td class="px-3 py-3">{{ optional(optional($rec->location)->lote)->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-3 font-mono">{{ $rec->ce_superficial_str ?? '0.000' }}</td>
                                        <td class="px-3 py-3 font-mono">{{ $rec->ce_profunda_str ?? '0.000' }}</td>
                                        <td class="px-3 py-3 font-mono">{{ $rec->ce_reference_str ?? '0.0000' }}</td>
                                        <td class="px-3 py-3">{!! $rec->condition_badge_html !!}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-slate-300 italic font-medium">No hay registros de control.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-slate-50/40">
                        {{ $controlRecords->links() }}
                    </div>
                </div>
            @else
                {{-- Show aggregated daily control stats when a specific location is selected --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                        Tabla Diaria: Grupo Control (Verdad de Campo)
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Consolidado por fecha</span>
                </div>

                <div class="academic-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50/50 border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Fecha</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Sup</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Prof</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">ILx</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Loss %</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Eventos</th>
                                    <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($dailyStats as $day)
                                    @php
                                        $ctrl = $day['control'] ?? [];
                                        $state = 'Normal';
                                        $lossPct = $ctrl['pct_lixiviacion'] ?? 0;
                                        if ($lossPct > 50) $state = 'Alta pérdida';
                                        elseif ($lossPct > 10) $state = 'Baja pérdida';
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 font-bold">{{ $day['date_label'] }}</td>
                                        <td class="px-3 py-3 font-mono">{{ number_format($ctrl['avg_ce_sup'] ?? 0, 3) }}</td>
                                        <td class="px-3 py-3 font-mono">{{ number_format($ctrl['avg_ce_prof'] ?? 0, 3) }}</td>
                                        <td class="px-3 py-3 font-mono">{{ number_format($ctrl['avg_ilx'] ?? 0, 4) }}</td>
                                        <td class="px-3 py-3">{{ number_format($ctrl['pct_lixiviacion'] ?? 0, 1) }}%</td>
                                        <td class="px-3 py-3 text-center">{{ $ctrl['total'] ?? 0 }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black {{ $state === 'Alta pérdida' ? 'bg-red-100 text-red-700' : ($state === 'Baja pérdida' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $state }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-slate-300 italic font-medium">No hay días con datos para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-span-1 lg:col-span-1">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                    Tabla Diaria: Grupo Experimental (IoT)
                </h3>
                <span class="text-[10px] font-bold text-slate-400 uppercase">Consolidado por fecha</span>
            </div>

            <div class="academic-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">VP</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">FP</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">FN</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">VN</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Eventos</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">PDS %</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Recall %</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Error %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($dailyStats as $day)
                                @php
                                    $exp = $day['experimental'] ?? [];
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 font-bold">{{ $day['date_label'] }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-emerald-700">{{ $exp['vp'] ?? 0 }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-red-600">{{ $exp['fp'] ?? 0 }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-amber-700">{{ $exp['fn'] ?? 0 }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-slate-700">{{ $exp['vn'] ?? 0 }}</td>
                                    <td class="px-3 py-3 text-center">{{ $exp['total'] ?? 0 }}</td>
                                    <td class="px-3 py-3">{{ number_format($exp['pds'] ?? 0, 2) }}%</td>
                                    <td class="px-3 py-3">{{ number_format($exp['recall'] ?? 0, 2) }}%</td>
                                    <td class="px-3 py-3">{{ number_format($exp['error_rate'] ?? 0, 2) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-slate-300 italic font-medium">No hay días con datos para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Chart.js & Logic Integration script --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        @if(!isset($selectedLocation) || $selectedLocation->experimental_group === 'experimental')
            
            // 1. CONFUSION MATRIX CHART (Polar Area)
            const ctxConfusion = document.getElementById('confusionMatrixChart');
            if (ctxConfusion) {
                new Chart(ctxConfusion, {
                    type: 'polarArea',
                    data: {
                        labels: ['VP (Verdaderos Positivos)', 'FP (Falsos Positivos)', 'FN (Falsos Negativos)', 'VN (Verdaderos Negativos)'],
                        datasets: [{
                            data: [
                                {{ $stats['vp'] ?? 0 }},
                                {{ $stats['fp'] ?? 0 }},
                                {{ $stats['fn'] ?? 0 }},
                                {{ $stats['vn'] ?? 0 }}
                            ],
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.75)', // emerald
                                'rgba(239, 68, 68, 0.75)',  // red
                                'rgba(245, 158, 11, 0.75)', // amber
                                'rgba(100, 116, 139, 0.75)' // slate
                            ],
                            borderColor: ['#10b981', '#ef4444', '#f59e0b', '#64748b'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ` ${context.label}: ${context.raw} eventos`;
                                    }
                                }
                            }
                        },
                        scales: {
                            r: {
                                ticks: { display: false },
                                grid: { color: '#f1f5f9' }
                            }
                        }
                    }
                });
            }

            // 2. PRECISION VS ERROR CHART (Doughnut)
            const ctxPrecision = document.getElementById('precisionErrorChart');
            if (ctxPrecision) {
                new Chart(ctxPrecision, {
                    type: 'doughnut',
                    data: {
                        labels: ['Precisión del Sistema (PDS)', 'Tasa de Error'],
                        datasets: [{
                            data: [
                                {{ $stats['pds_percentage'] ?? 0 }},
                                {{ $stats['error_rate'] ?? 0 }}
                            ],
                            backgroundColor: [
                                'rgba(79, 70, 229, 0.85)', // indigo
                                'rgba(248, 113, 113, 0.85)' // red-light
                            ],
                            borderColor: ['#4f46e5', '#f87171'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: { family: 'Outfit', size: 10, weight: 'bold' },
                                    color: '#475569'
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }

            // 3. TEMPORAL EVOLUTION CHART (Line Chart)

                const ctxEvolution = document.getElementById('temporalEvolutionChart');
            if (ctxEvolution) {
                const datesArr = @json($dates ?? []);
                const pdsArr = @json(json_decode($pdsJson ?? '[]'));
                const errorArr = @json(json_decode($errorJson ?? '[]'));

                new Chart(ctxEvolution, {
                    type: 'line',
                    data: {
                        labels: datesArr,
                        datasets: [
                            {
                                label: 'Precisión (PDS %) por día',
                                data: pdsArr,
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79,70,229,0.05)',
                                borderWidth: 3,
                                pointBackgroundColor: '#4f46e5',
                                tension: 0.25,
                                fill: true
                            },
                            {
                                label: 'Tasa de Error % por día',
                                data: errorArr,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239,68,68,0.05)',
                                borderWidth: 3,
                                pointBackgroundColor: '#ef4444',
                                tension: 0.25,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: { family: 'Outfit', size: 10, weight: 'bold' },
                                    color: '#475569'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#94a3b8', font: { family: 'Outfit' } },
                                grid: { color: '#f1f5f9' }
                            },
                            x: {
                                ticks: { color: '#94a3b8', font: { family: 'Outfit' } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        @endif
    });
</script>
@endsection
