@extends('layouts.app')
@section('title', 'Nivel de Lixiviación — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.8);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --accent-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }

    .glass-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        backdrop-filter: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border-radius: 14px;
    }

    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
    }

    .kpi-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.75rem;
    }

    .kpi-value {
        font-size: 3rem;
        font-weight: 900;
        color: #1e293b;
        line-height: 0.9;
        font-family: 'Consolas', monospace;
        letter-spacing: -1px;
    }

    .kpi-unit {
        font-size: 1rem;
        font-weight: 700;
        color: #94a3b8;
        margin-left: 0.25rem;
    }

    .status-badge {
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.35rem 1rem;
        border-radius: 9999px;
        text-transform: uppercase;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .filter-btn {
        padding: 0.6rem 1.25rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.25s;
    }

    .filter-btn.active {
        background: #1e293b;
        color: white;
        box-shadow: 0 4px 12px rgba(30, 41, 59, 0.2);
    }

    .filter-btn:not(.active) {
        background: white;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .filter-btn:not(.active):hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1rem;
    }

    @keyframes pulse-ring {
        0% { transform: scale(0.33); }
        80%, 100% { opacity: 0; }
    }
    .live-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
        position: relative; display: inline-block;
    }
    .live-dot::after {
        content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 50%; background: inherit; animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
    }

    .border-crit { border-left: 6px solid #ef4444; }
    .border-warn { border-left: 6px solid #f59e0b; }
    .border-ok   { border-left: 6px solid #22c55e; }
    .border-info { border-left: 6px solid #3b82f6; }

    /* ===== MODAL ESTILO ACADÉMICO ===== */
    .eval-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 100;
        padding: 1rem;
    }
    .eval-overlay.active { display: flex; }

    .eval-box {
        background: #fff;
        border-radius: 24px;
        width: 100%;
        max-width: 520px;
        padding: 2rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        animation: evalPop 0.25s ease-out;
    }
    @keyframes evalPop {
        from { transform: scale(0.92); opacity: 0; }
        to   { transform: scale(1);    opacity: 1; }
    }

    .eval-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f1f5f9;
    }
    .eval-header h3 {
        font-weight: 800;
        font-size: 1.15rem;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .eval-close {
        background: #f1f5f9;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-weight: 900;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .eval-close:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="page-container">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Nivel de Lixiviación</h1>
            <div class="mt-2 text-[10px] font-bold text-slate-400 italic">
                * El Nivel de Lixiviación mide la relación entre nutrientes profundos y superficiales.
            </div>
        </div>
    </div>

    @if(session('success'))
        <div style="background-color: #dcfce7; border: 1px solid #bbf7d0; color: #16a34a; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 600; font-size: 0.9rem;">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background-color: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <span style="font-weight: 600;">{{ session('error') }}</span>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 🎯 SELECTOR DE MODO (IoT / Manual) - ÚNICO                   --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">📡 Modo de Visualización</label>
        <div class="flex gap-3">
            @php
                $defaultIotLoc = $plantasGE->first()?->ubicaciones->first()?->id;
                $defaultManualLoc = $plantasGC->first()?->ubicaciones->first()?->id;
            @endphp
            
            <a href="{{ route('lixiviacion', ['mode' => 'iot', 'location_id' => ($location_id && !$isCtrl && $location_id !== 'all') ? $location_id : $defaultIotLoc, 'filter' => $filter]) }}" 
               class="flex-1 max-w-xs px-6 py-4 rounded-2xl font-black text-sm transition-all flex items-center justify-center gap-3 {{ $mode === 'iot' ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-200' : 'bg-white text-slate-600 border-2 border-slate-200 hover:border-emerald-300' }}">
                <i class="fas fa-robot text-xl"></i>
                <div class="text-left">
                    <div class="text-xs uppercase tracking-wider">Sensores IoT</div>
                    <div class="text-[10px] font-medium opacity-80">Datos en tiempo real</div>
                </div>
            </a>
            
            <a href="{{ route('lixiviacion', ['mode' => 'manual', 'location_id' => ($location_id && $isCtrl && $location_id !== 'all') ? $location_id : $defaultManualLoc, 'filter' => $filter]) }}" 
               class="flex-1 max-w-xs px-6 py-4 rounded-2xl font-black text-sm transition-all flex items-center justify-center gap-3 {{ $mode === 'manual' ? 'bg-amber-600 text-white shadow-xl shadow-amber-200' : 'bg-white text-slate-600 border-2 border-slate-200 hover:border-amber-300' }}">
                <i class="fas fa-user-edit text-xl"></i>
                <div class="text-left">
                    <div class="text-xs uppercase tracking-wider">Registro Manual</div>
                    <div class="text-[10px] font-medium opacity-80">Datos de campo</div>
                </div>
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 🌳 SELECTOR DE PLANTA (FILTRA SEGÚN MODO)                     --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col lg:flex-row gap-6 mb-10 items-end">
        <div class="flex-grow max-w-xl">
            <form method="GET" action="{{ route('lixiviacion') }}" id="location-form">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">
                    🌳 Seleccionar Planta de Palto — {{ $mode === 'iot' ? 'Grupo Experimental' : 'Grupo Control' }}
                </label>
                <select name="location_id" id="location-selector" onchange="this.form.submit()" 
                        class="w-full p-4 bg-white border-2 border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:border-{{ $mode === 'iot' ? 'emerald' : 'amber' }}-500 transition-all shadow-sm">
                    
                    @if($mode === 'iot')
                        <optgroup label="🔵 GRUPO EXPERIMENTAL (IoT)">
                            {{-- ✅ NUEVA OPCIÓN: TODAS LAS PLANTAS --}}
                            <option value="all" {{ $location_id === 'all' ? 'selected' : '' }}>
                                🌳🌳 Todas las Plantas (Consolidado)
                            </option>
                            @foreach($plantasGE as $planta)
                                @php $loc = $planta->ubicaciones->first(); @endphp
                                @if($loc)
                                    <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                                        🌳 {{ $planta->nombre }} (Planta {{ $planta->numero_planta }}){{ $loc->codigo_dispositivo ? ' — ' . $loc->codigo_dispositivo : '' }}
                                    </option>
                                @endif
                            @endforeach
                        </optgroup>
                    @else
                        <optgroup label="🟢 GRUPO CONTROL (Manual)">
                            {{-- ✅ NUEVA OPCIÓN: TODAS LAS PLANTAS --}}
                            <option value="all" {{ $location_id === 'all' ? 'selected' : '' }}>
                                🌳🌳 Todas las Plantas (Consolidado)
                            </option>
                            @foreach($plantasGC as $planta)
                                @php $loc = $planta->ubicaciones->first(); @endphp
                                @if($loc)
                                    <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                                        🌳 {{ $planta->nombre }} (Planta {{ $planta->numero_planta }})
                                    </option>
                                @endif
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </form>
        </div>
        
        <div class="hidden lg:block text-right pb-2">
            <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Estado de Conexión</div>
            <div id="conn-text" class="text-xs font-bold text-slate-500">Esperando...</div>
        </div>

        <div class="hidden lg:block text-right pb-2 border-l border-slate-200 pl-6">
            <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Última Actualización</div>
            <div id="last-update" class="text-sm font-black text-slate-900 font-mono">--:--:--</div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 🎯 PANEL SUPERIOR: MODO MANUAL O IoT                          --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if(isset($ubicacionSeleccionada))
        <div class="mb-8 p-8 rounded-3xl border {{ $mode === 'manual' ? 'border-amber-200/70 bg-gradient-to-br from-amber-50 to-white shadow-md shadow-amber-100/40' : 'border-emerald-200/70 bg-gradient-to-br from-emerald-50 to-white shadow-md shadow-emerald-100/40' }}">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="p-3 {{ $mode === 'manual' ? 'bg-amber-500' : 'bg-emerald-500' }} text-white rounded-2xl shadow-lg">
                        <i class="fas {{ $mode === 'manual' ? 'fa-user-edit' : 'fa-robot' }}"></i>
                    </span>
                    <div>
                        <h3 class="text-xl font-black {{ $mode === 'manual' ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $mode === 'manual' ? '📝 Modo Manual — ' : '📡 Modo IoT — ' }}{{ $ubicacionSeleccionada->planta->nombre ?? 'N/D' }}
                        </h3>
                        <p class="text-sm font-medium {{ $mode === 'manual' ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $mode === 'manual' ? 'Ingrese las lecturas manuales del conductímetro digital' : 'Datos recolectados por sensores IoT en tiempo real.' }}
                        </p>
                    </div>
                </div>
                
                @if($mode === 'manual')
                    {{-- ✅ BOTÓN PARA ABRIR MODAL --}}
                    <button type="button" onclick="openManualModal()" class="px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-2xl font-black text-sm shadow-xl shadow-amber-200 transform hover:-translate-y-1 transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> NUEVO REGISTRO MANUAL
                    </button>
                @else
                    <div class="px-4 py-2 bg-white rounded-xl border border-emerald-100 flex items-center gap-2">
                        <div class="live-dot"></div>
                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Sensores Activos</span>
                    </div>
                @endif
            </div>
        </div>
    @elseif($isAllPlants ?? false)
        {{-- ✅ NUEVO: PANEL PARA TODAS LAS PLANTAS --}}
        <div class="mb-8 p-8 rounded-3xl border {{ $mode === 'manual' ? 'border-amber-200/70 bg-gradient-to-br from-amber-50 to-white shadow-md shadow-amber-100/40' : 'border-emerald-200/70 bg-gradient-to-br from-emerald-50 to-white shadow-md shadow-emerald-100/40' }}">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="p-3 {{ $mode === 'manual' ? 'bg-amber-500' : 'bg-emerald-500' }} text-white rounded-2xl shadow-lg">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <div>
                        <h3 class="text-xl font-black {{ $mode === 'manual' ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $mode === 'manual' ? '📝 Modo Manual — ' : '📡 Modo IoT — ' }}🌳🌳 Todas las Plantas
                        </h3>
                        <p class="text-sm font-medium {{ $mode === 'manual' ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $mode === 'manual' ? 'Vista consolidada de todas las plantas del Grupo Control' : 'Vista consolidada de todas las plantas del Grupo Experimental' }}
                        </p>
                    </div>
                </div>
                
                <span class="px-4 py-2 bg-white rounded-xl border border-{{ $mode === 'manual' ? 'amber' : 'emerald' }}-200 flex items-center gap-2">
                    <span class="text-[10px] font-black text-{{ $mode === 'manual' ? 'amber' : 'emerald' }}-600 uppercase tracking-widest">
                        Vista Consolidada
                    </span>
                </span>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 📊 CARDS DE INDICADORES                                       --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="glass-card p-8 border-l-4 border-blue-500" id="card-ce-sup">
            <div class="kpi-title">CE Superficial (20cm)</div>
            <div class="flex items-baseline mb-4">
                <div class="kpi-value" id="kpi-ce-sup">--</div>
                <div class="kpi-unit">dS/m</div>
            </div>
            <div>
                <span class="status-badge bg-blue-50 text-blue-600" id="status-ce-sup">--</span>
            </div>
        </div>

        <div class="glass-card p-8 border-l-4 border-emerald-500" id="card-ce-prof">
            <div class="kpi-title">CE Profunda (60cm)</div>
            <div class="flex items-baseline mb-4">
                <div class="kpi-value" id="kpi-ce-prof">--</div>
                <div class="kpi-unit">dS/m</div>
            </div>
            <div>
                <span class="status-badge bg-emerald-50 text-emerald-600" id="status-ce-prof">--</span>
            </div>
        </div>

        <div class="glass-card p-8 border-l-4 border-indigo-500" id="card-ilx">
            <div class="kpi-title">Nivel de Lixiviación</div>
            <div class="flex items-baseline mb-4">
                <div class="kpi-value" id="kpi-ilx">--</div>
                <div class="kpi-unit">ratio</div>
            </div>
            <div>
                <span class="status-badge bg-indigo-50 text-indigo-600" id="status-ilx">--</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 📈 GRÁFICOS                                                   --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="glass-card p-6">
            <h4 class="kpi-title">CE Superficial — Promedio Diario</h4>
            <div style="height:220px;"><canvas id="lxCeSupChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Nivel de Lixiviación</h4>
            <div style="height:220px;"><canvas id="lxIlxChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Registros por Día</h4>
            <div style="height:220px;"><canvas id="lxCountsChart"></canvas></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 📋 TABLA DE REGISTROS (DATOS REALES)                          --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="mb-10">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <span class="p-3 bg-indigo-600 text-white rounded-2xl shadow-lg shadow-indigo-200">📊</span>
                Tabla de Registro del indicador Nivel de Lixiviación
                @if($isAllPlants ?? false)
                    <span class="ml-2 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-black border border-emerald-200">
                        🌳🌳 Todas las Plantas
                    </span>
                @elseif(isset($ubicacionSeleccionada))
                    <span class="ml-2 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-xs font-black border border-indigo-200">
                        🌳 {{ $ubicacionSeleccionada->planta->nombre ?? 'N/D' }} N°{{ $ubicacionSeleccionada->planta->numero_planta ?? '?' }}
                    </span>
                @endif
            </h2>
            
            {{-- Filtros de tiempo --}}
            <div class="flex items-center">
                <div class="inline-flex bg-white/80 backdrop-blur-md p-1 rounded-2xl shadow-sm ring-1 ring-slate-200">
                    @foreach(['24h', '7d', '14d', '30d', 'all'] as $f)
                        <a href="{{ route('lixiviacion', ['location_id' => $location_id, 'mode' => $mode, 'filter' => $f]) }}"
                           class="filter-btn {{ $filter == $f ? 'active' : '' }}">
                            {{ strtoupper($f) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50/50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">Planta de palto</th>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">Fecha</th>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">CE_S</th>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">CE_P</th>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">Nivel de lixiviado</th>
                            <th class="px-6 py-4 font-black text-slate-400 uppercase tracking-wider text-[10px]">IL = CE_p / CE_s</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($records as $record)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 font-bold">
                                    🌳 {{ $record->planta?->nombre ?? ($ubicacionSeleccionada?->planta?->nombre ?? 'N/D') }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ \Carbon\Carbon::parse($record->fecha_analisis)->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 font-mono text-blue-600">
                                    {{ number_format($record->conductividad_superficial, 3) }}
                                </td>
                                <td class="px-6 py-4 font-mono text-emerald-600">
                                    {{ number_format($record->conductividad_profundo, 3) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $ilxValue = (float) ($record->ilx ?? 0);
                                        
                                        if ($ilxValue > 1.0) {
                                            $nivelTexto = 'ALTA LIXIVIACIÓN';
                                            $badgeClass = 'bg-red-100 text-red-700 border border-red-200';
                                        } elseif ($ilxValue >= 0.6) {
                                            $nivelTexto = 'MEDIA LIXIVIACIÓN';
                                            $badgeClass = 'bg-amber-100 text-amber-700 border border-amber-200';
                                        } else {
                                            $nivelTexto = 'BAJA LIXIVIACIÓN';
                                            $badgeClass = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                                        }
                                    @endphp
                                    <span class="status-badge {{ $badgeClass }}">{{ $nivelTexto }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono font-black text-indigo-600">
                                    {{ number_format($record->ilx ?? 0, 3) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-300 italic font-medium">
                                    No hay registros para esta planta en el período seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if(method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- 🆕 MODAL: REGISTRO MANUAL (GRUPO CONTROL)                        --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div id="manualModal" class="eval-overlay">
    <div class="eval-box">
        <div class="eval-header">
            <h3>📝 Nuevo Registro Manual — Grupo Control</h3>
            <button type="button" class="eval-close" onclick="closeManualModal()">✕</button>
        </div>

        <p class="text-sm text-slate-600 mb-4 font-semibold">
            Ingrese las lecturas del conductímetro digital para la planta seleccionada.
        </p>

        <form action="{{ route('lixiviacion.store_manual') }}" method="POST">
            @csrf
            
            {{-- 🌳 SELECTOR DE PLANTA GC --}}
            <div class="mb-4">
                <label class="text-xs font-black text-slate-500 uppercase tracking-wider">🌳 Planta de palto (Grupo Control)</label>
                <select name="planta_id" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                    <option value="">Seleccione planta</option>
                    @foreach($plantasGC as $planta)
                        <option value="{{ $planta->id }}" {{ ($ubicacionSeleccionada && $ubicacionSeleccionada->planta_id == $planta->id) ? 'selected' : '' }}>
                            🌳 {{ $planta->nombre }} (Planta {{ $planta->numero_planta }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">📅 Fecha y hora</label>
                    <input type="datetime-local" name="fecha_analisis" value="{{ date('Y-m-d\TH:i') }}" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">📝 Observación (opcional)</label>
                    <input type="text" name="observacion" maxlength="255" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" placeholder="Ej: Medición post-riego" />
                </div>

                <div>
                    <label class="text-xs font-black text-amber-600 uppercase tracking-wider">CE Superficial (dS/m)</label>
                    <input type="number" step="0.001" min="0" name="conductividad_superficial" id="modal-ce-sup" oninput="updateModalILx()"
                           class="w-full p-3 border border-amber-100 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" placeholder="0.000" required />
                </div>

                <div>
                    <label class="text-xs font-black text-amber-600 uppercase tracking-wider">CE Profunda (dS/m)</label>
                    <input type="number" step="0.001" min="0" name="conductividad_profundo" id="modal-ce-prof" oninput="updateModalILx()"
                           class="w-full p-3 border border-amber-100 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" placeholder="0.000" required />
                </div>
            </div>

            {{-- Preview ILx en tiempo real --}}
            <div class="mt-4 p-4 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-xl">
                <div class="text-[10px] font-black uppercase text-indigo-600 tracking-widest mb-1">Vista previa del cálculo</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs font-bold text-slate-600">ILx =</span>
                    <span class="text-2xl font-black text-indigo-700 font-mono" id="modal-ilx-preview">--</span>
                    <span class="status-badge bg-indigo-50 text-indigo-600 ml-2" id="modal-nivel-preview">--</span>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeManualModal()" class="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-black shadow-sm flex items-center gap-2">
                    <i class="fas fa-save"></i> Guardar Registro
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
let locationId = '{{ $location_id }}';
let pollTimer = null;
const isCtrl = @json($isCtrl ?? false);

const THRESHOLDS = {
    ce_sup_max : 0.600,
    ce_prof_max: 0.750,
};

function classifyILx(ilx) {
    if (isNaN(ilx)) return { estado: 'SIN DATOS', icon: '⚪', level: 'none' };
    if (ilx < 0.4) return { estado: 'BAJA LIXIVIACIÓN', icon: '🟢', level: 'low' };
    if (ilx >= 0.6 && ilx <= 1.0) return { estado: 'MEDIA LIXIVIACIÓN', icon: '🟡', level: 'medium' };
    if (ilx > 1.0) return { estado: 'ALTA LIXIVIACIÓN', icon: '🔴', level: 'high' };
    return { estado: 'MEDIA LIXIVIACIÓN', icon: '🟡', level: 'medium' };
}

async function poll() {
    if (!locationId || isCtrl || locationId === 'all') return;
    try {
        const res  = await fetch(`/api/readings/latest?location_id=${locationId}&_=${Date.now()}`);
        const json = await res.json();
        if (!res.ok || json.status !== 'success') { setConn('❌ Error API'); return; }

        const readings = Array.isArray(json.data?.readings) ? json.data.readings : [];
        if (readings.length === 0) { setConn('Esperando datos...'); return; }

        const sup  = readings.find(r => Number(r.sensor.depth) === 20) ?? null;
        const prof = readings.find(r => Number(r.sensor.depth) === 60) ?? null;

        const ce_s = sup  ? Number(sup.conductivity_raw  ?? sup.conductivity)  : NaN;
        const ce_p = prof ? Number(prof.conductivity_raw ?? prof.conductivity) : NaN;
        const ilx  = (!isNaN(ce_s) && ce_s > 0 && !isNaN(ce_p)) ? ce_p / ce_s : NaN;

        updateCards(ce_s, ce_p, ilx);
        setConn('🟢 Conectado');
        document.getElementById('last-update').textContent = new Date().toLocaleTimeString('es', {hour12:false});
    } catch (e) {
        setConn('❌ Sin conexión');
    }
}

function updateCards(ce_s, ce_p, ilx) {
    const safe = (v) => (isNaN(v) ? '--' : v);

    document.getElementById('kpi-ce-sup').textContent = safe(ce_s) !== '--' ? ce_s.toFixed(3) : '--';
    document.getElementById('kpi-ce-prof').textContent = safe(ce_p) !== '--' ? ce_p.toFixed(3) : '--';
    document.getElementById('kpi-ilx').textContent = isNaN(ilx) ? '--' : ilx.toFixed(3);

    const badgeSup = document.getElementById('status-ce-sup');
    const badgeProf = document.getElementById('status-ce-prof');
    const badgeIlx = document.getElementById('status-ilx');

    if (!isNaN(ce_s)) {
        const alert = ce_s > THRESHOLDS.ce_sup_max;
        badgeSup.textContent = alert ? 'ALERTA' : 'OK';
        badgeSup.className = `status-badge ${alert ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`;
    }

    if (!isNaN(ce_p)) {
        const alert = ce_p > THRESHOLDS.ce_prof_max;
        badgeProf.textContent = alert ? 'ALERTA' : 'OK';
        badgeProf.className = `status-badge ${alert ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`;
    }

    if (!isNaN(ilx)) {
        const cls = classifyILx(ilx);
        const colorMap = {
            high: 'bg-red-50 text-red-600',
            medium: 'bg-yellow-50 text-yellow-700',
            low: 'bg-green-50 text-green-600',
            none: 'bg-slate-50 text-slate-600'
        };
        badgeIlx.textContent = cls.estado;
        badgeIlx.className = `status-badge ${colorMap[cls.level] || colorMap.none}`;
    }
}

function setConn(msg) { document.getElementById('conn-text').textContent = msg; }

/* ========== MODAL DE REGISTRO MANUAL ========== */
function openManualModal() {
    document.getElementById('manualModal').classList.add('active');
}

function closeManualModal() {
    document.getElementById('manualModal').classList.remove('active');
}

function updateModalILx() {
    const ce_s = Number(document.getElementById('modal-ce-sup').value);
    const ce_p = Number(document.getElementById('modal-ce-prof').value);
    const ilx = (!isNaN(ce_s) && ce_s > 0 && !isNaN(ce_p)) ? ce_p / ce_s : NaN;
    
    document.getElementById('modal-ilx-preview').textContent = isNaN(ilx) ? '--' : ilx.toFixed(3);
    
    let estado, badgeClass;
    if (isNaN(ilx)) {
        estado = 'SIN DATOS';
        badgeClass = 'bg-slate-50 text-slate-600';
    } else if (ilx > 1.0) {
        estado = 'ALTA LIXIVIACIÓN';
        badgeClass = 'bg-red-100 text-red-700';
    } else if (ilx >= 0.6) {
        estado = 'MEDIA LIXIVIACIÓN';
        badgeClass = 'bg-amber-100 text-amber-700';
    } else {
        estado = 'BAJA LIXIVIACIÓN';
        badgeClass = 'bg-emerald-100 text-emerald-700';
    }
    
    const badge = document.getElementById('modal-nivel-preview');
    badge.textContent = estado;
    badge.className = `status-badge ${badgeClass}`;
}

// Inicialización
if (locationId) {
    if (isCtrl || locationId === 'all') {
        setConn(locationId === 'all' ? '📊 Vista Consolidada' : '🟡 Modo Manual');
    } else {
        poll();
        pollTimer = setInterval(poll, 3000);
    }
}

// Cerrar modal al hacer click fuera
document.querySelectorAll('.eval-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('active');
    });
});
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dates   = @json($datesJson ?? []);
    const ceSup   = @json($ceSupJson ?? []);
    const ilx     = @json($ilxJson ?? []);
    const counts  = @json($countsJson ?? []);

    const c1 = document.getElementById('lxCeSupChart');
    if (c1 && dates.length > 0) {
        new Chart(c1, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'CE Superficial (dS/m)',
                    data: ceSup,
                    borderColor: '#0ea5a0',
                    backgroundColor: 'rgba(14,165,160,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true, ticks: { precision: 2 } } }
            }
        });
    }

    const c2 = document.getElementById('lxIlxChart');
    if (c2 && dates.length > 0) {
        new Chart(c2, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'ILx (CEp / CEs)',
                    data: ilx,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, suggestedMin: 0, suggestedMax: 2 } }
            }
        });
    }

    const c3 = document.getElementById('lxCountsChart');
    if (c3 && dates.length > 0) {
        new Chart(c3, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Registros por día',
                    data: counts,
                    backgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
});
</script>
@endpush