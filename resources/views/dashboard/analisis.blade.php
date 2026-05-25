@extends('layouts.app')
@section('title', 'Análisis Académico — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(255, 255, 255, 0.5);
    }
    .academic-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }
    .metric-value { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.25rem; line-height: 1; }
    .metric-label { font-size: 0.65rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; }
    
    .progress-bar-custom { height: 8px; border-radius: 99px; background: #f1f5f9; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 99px; transition: width 1s ease-out; }

    .comparison-row { display: grid; grid-template-columns: 1fr 1px 1fr; gap: 2rem; align-items: center; }
    .v-divider { background: #e2e8f0; width: 1px; height: 80%; }

    .btn-jamovi {
        background: #4f46e5; color: white; padding: 0.75rem 1.5rem; border-radius: 14px; font-weight: 800; font-size: 0.8rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); transition: all 0.2s;
    }
    .btn-jamovi:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4); color: white; }
</style>

<div class="max-w-7xl mx-auto py-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-black rounded-md uppercase tracking-widest">Investigación Experimental</span>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Indicadores de Evaluación</h1>
            <p class="text-slate-500 font-medium mt-1">Análisis estadístico y métricas de desempeño del sistema vs. métodos tradicionales.</p>
        </div>
        
        <div class="flex gap-3">
            @if(isset($selectedLocation))
                <div class="px-4 py-2 bg-white border-2 border-slate-100 rounded-xl flex items-center gap-3 shadow-sm">
                    <span class="text-[10px] font-black uppercase text-slate-400">Grupo Actual:</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $selectedLocation->experimental_group === 'control' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                        {{ $selectedLocation->experimental_group }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Main Metrics Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
        {{-- PDS Efficiency --}}
        <div class="academic-card p-8 border-t-4 border-indigo-500">
            <div class="metric-label">Eficiencia del Sistema</div>
            <div class="flex items-baseline gap-2 mb-4">
                <span class="metric-value text-indigo-600">{{ $comparison['efficiency'] }}%</span>
                <span class="text-xs font-bold text-slate-400">mejor que control</span>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed mb-6">Incremento en la capacidad de retención de fertilizantes frente al grupo control mediante monitoreo activo.</p>
            
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase mb-1">
                        <span>Experimental</span>
                        <span class="text-indigo-600">{{ $comparison['experimental']['count'] }} / 30</span>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill bg-indigo-500" style="width: {{ min(100, ($comparison['experimental']['count'] / 30) * 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase mb-1 text-amber-600">
                        <span>Control</span>
                        <span>{{ $comparison['control']['count'] }} / 30</span>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill bg-amber-500" style="width: {{ min(100, ($comparison['control']['count'] / 30) * 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Precision Metrics --}}
        <div class="academic-card p-8 border-t-4 border-emerald-500">
            <div class="metric-label">Precisión del Diagnóstico (PDS)</div>
            <div class="metric-value text-emerald-600 mb-6">{{ $stats['pds_percentage'] }}%</div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100">
                    <div class="text-[9px] font-black uppercase text-emerald-600 mb-1">Verdaderos (+)</div>
                    <div class="text-xl font-black text-emerald-700">{{ $stats['vp'] }}</div>
                </div>
                <div class="p-4 bg-red-50/50 rounded-2xl border border-red-100">
                    <div class="text-[9px] font-black uppercase text-red-600 mb-1">Falsos (+)</div>
                    <div class="text-xl font-black text-red-700">{{ $stats['fp'] }}</div>
                </div>
            </div>
            
            <div class="mt-6 flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-slate-400 shadow-sm">
                    <i class="fas fa-percentage text-xs"></i>
                </div>
                <div>
                    <div class="text-[9px] font-black uppercase text-slate-400">Tasa de Error</div>
                    <div class="text-sm font-bold text-slate-700">{{ $stats['fp_percentage'] }}%</div>
                </div>
            </div>
        </div>

        {{-- Response Time --}}
        <div class="academic-card p-8 border-t-4 border-blue-500">
            <div class="metric-label">Tiempo de Alerta (TAR)</div>
            <div class="metric-value text-blue-600 mb-2">{{ $stats['avg_response_time'] }} min</div>
            <p class="text-xs text-slate-500 leading-relaxed mb-8">Tiempo promedio transcurrido desde la detección de lixiviación hasta la resolución del evento.</p>
            
            <div class="comparison-row">
                <div class="text-center">
                    <div class="text-[9px] font-black uppercase text-slate-400 mb-1">Índice Lix. Experimental</div>
                    <div class="text-lg font-black text-indigo-600">{{ number_format($comparison['experimental']['avg_ilx'] ?? 0, 3) }}</div>
                </div>
                <div class="v-divider mx-auto"></div>
                <div class="text-center">
                    <div class="text-[9px] font-black uppercase text-slate-400 mb-1">Índice Lix. Control</div>
                    <div class="text-lg font-black text-amber-600">{{ number_format($comparison['control']['avg_ilx'] ?? 0, 3) }}</div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-[10px] font-bold text-slate-400 italic">
                * El Índice de Lixiviación mide la relación entre nutrientes profundos y superficiales.
            </div>
        </div>
    </div>

    {{-- Analysis Table --}}
    <div class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center shadow-lg">📋</span>
                Registro de Eventos Críticos
                @if(isset($selectedLocation))
                    <span class="text-sm font-bold text-slate-400 ml-2">— {{ $selectedLocation->name }}</span>
                @endif
            </h2>
            
            <form method="GET" action="{{ route('analisis') }}" class="min-w-[300px]">
                <select name="location_id" onchange="this.form.submit()" class="w-full p-3 bg-white border-2 border-slate-100 rounded-2xl font-bold text-slate-700 outline-none focus:border-indigo-500 transition-all shadow-sm text-sm">
                    <option value="">Todas las ubicaciones</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->experimental_group === 'control' ? 'CTRL' : 'EXP' }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="academic-card overflow-hidden">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Ítem</th>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Fecha</th>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">VP</th>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">FP</th>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">FN</th>
                        <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">PD (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php 
                        $total_items = $dailyStats->total();
                    @endphp
                    @forelse($dailyStats as $index => $day)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-8 py-5 font-mono text-slate-400">
                                {{ $total_items - ($dailyStats->firstItem() + $index) + 1 }}
                            </td>
                            <td class="px-8 py-5 font-bold text-slate-700">
                                {{ \Carbon\Carbon::parse($day->date)->format('d/m/Y') }}
                            </td>
                            <td class="px-8 py-5 text-center font-bold text-emerald-600">
                                {{ $day->vp }}
                            </td>
                            <td class="px-8 py-5 text-center font-bold text-red-500">
                                {{ $day->fp }}
                            </td>
                            <td class="px-8 py-5 text-center font-bold text-amber-500">
                                {{ $day->fn }}
                            </td>
                            <td class="px-8 py-5 text-right font-black text-indigo-600">
                                @php
                                    $divisor = $day->vp + $day->fp + $day->fn;
                                    $pd = $divisor > 0 ? ($day->vp / $divisor) * 100 : 0;
                                @endphp
                                @if($pd > 0)
                                    {{ number_format($pd, 1) }}%
                                @else
                                    <span class="text-slate-300">0.0%</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center text-slate-300">
                                <i class="fas fa-microscope text-4xl mb-4 opacity-20"></i>
                                <p class="font-bold italic">No hay suficientes datos para el análisis estadístico.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            <div class="px-8 py-6 bg-slate-50/50">
                {{ $dailyStats->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
