@extends('layouts.app')

@section('title', 'Tiempo Promedio de Detección — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.3);
        --accent-green: #16a34a;
        --accent-blue: #3b82f6;
        --accent-purple: #9333ea;
        --accent-amber: #d97706;
    }
    
    .filter-btn {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        color: #64748b;
        text-decoration: none;
        transition: all 0.25s ease;
        position: relative;
    }

    .kpi-card { transition: all 0.3s ease; }
    .kpi-card:hover { transform: translateY(-3px); }

    .filter-btn:hover {
        background: rgba(16, 163, 74, 0.08);
        color: #16a34a;
    }

    .filter-btn.active {
        background: #16a34a;
        color: white;
        box-shadow: 0 6px 18px rgba(22, 163, 74, 0.25);
    }

    .filter-btn.active::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 20%;
        width: 60%;
        height: 2px;
        background: #16a34a;
        border-radius: 999px;
        opacity: 0.6;
    }

    .page-header { 
        margin-bottom: 2rem; 
        display: flex; 
        justify-content: space-between;
        align-items: flex-end;
    }
    .page-header h1 { margin: 0; font-size: 1.8rem; font-weight: 800; color: #1a472a; letter-spacing: -0.02em; }
    .page-header p  { margin: 0.25rem 0 0; font-size: 0.95rem; color: #6b7280; }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        transition: transform 0.2s ease;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    .kpi-card {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .kpi-card .icon-box {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        font-size: 1.5rem;
        opacity: 0.2;
    }
    .kpi-card .label { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-card .value { font-size: 2.2rem; font-weight: 900; margin: 0.5rem 0; font-family: 'Inter', sans-serif; }
    .kpi-card .trend { font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; }

    .filter-section {
        padding: 1.25rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
        background: rgba(255,255,255,0.5);
        border-bottom: 1px solid #eee;
    }
    .filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .filter-group label { font-size: 0.7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; }
    .filter-section select, .filter-section input {
        padding: 0.5rem 0.8rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 0.85rem;
        background: #fff;
        min-width: 180px;
        outline: none;
        transition: border-color 0.2s;
    }
    .filter-section select:focus { border-color: var(--accent-green); }

    .table-container { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { 
        padding: 1rem; 
        text-align: left; 
        color: #4b5563;
        font-weight: 700; 
        font-size: 0.75rem; 
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: rgba(0,0,0,0.02);
    }
    td { padding: 1.1rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.88rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(22, 163, 74, 0.02); }

    .empty-state { padding: 4rem; text-align: center; }
    .empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block; }
    .empty-state p { color: #9ca3af; font-size: 1rem; }

    .badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        padding: 2rem 1rem;
        flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        padding: 0.5rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .pagination a:hover {
        background: #f3f4f6;
        border-color: var(--accent-green);
        color: var(--accent-green);
    }
    .pagination .active {
        background: var(--accent-green);
        border-color: var(--accent-green);
        color: white;
    }
    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ===== MODAL ESTILO ACADÉMICO ===== */
    .eval-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }
    .eval-overlay.active { display: flex; }

    .eval-box {
        background: #fff;
        border-radius: 24px;
        width: 100%;
        max-width: 520px;
        max-height: 90vh; /* Límite de altura para que no desborde la pantalla */
        overflow-y: auto;
        /* Permite scroll interno si el contenido es muy largo */
        padding: 2rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        animation: evalPop 0.25s ease-out;
    }

    /* Ajuste para pantallas pequeñas */
    @media (max-width: 640px) {
        .eval-box {
            padding: 1.5rem;
            max-height: 95vh;
            border-radius: 16px;
        }
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
        position: sticky;
        /* Fija el encabezado en la parte superior */
        top: 0;
        background: #fff;
        /* Oculta el texto que pasa por debajo al hacer scroll */
        z-index: 10;
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

    @keyframes pulse-ring {
        0% { transform: scale(0.33); }
        80%, 100% { opacity: 0; }
    }
    .live-dot {
        width: 8px;
        height: 8px; border-radius: 50%; background: #22c55e;
        position: relative; display: inline-block;
    }
    .live-dot::after {
        content: ""; position: absolute; top: 0;
        left: 0; right: 0; bottom: 0;
        border-radius: 50%; background: inherit; animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
    }
</style>

<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    {{-- Header Section --}}
    <div class="page-header">
        <div>
            <h1>Tiempo Promedio de Detección</h1>
            <p>3er Indicador</p>
        </div>
        <div style="display:flex; gap: 0.75rem;">
            <a href="{{ route('detection_time.export', ['location_id' => $location_id, 'mode' => $mode, 'filter' => $filter]) }}" class="btn btn-light shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem; text-decoration: none; display: flex; align-items: center; justify-content: center; padding: 0.5rem 1rem;">
                <i class="fas fa-download"></i> Descargar
            </a>
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
    {{-- 🎯 SELECTOR DE MODO (IoT / Manual)                            --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">📡 Modo de Visualización</label>
        <div class="flex gap-3">
            @php
                $defaultIotLoc = $plantasGE->first()?->ubicaciones->first()?->id;
                $defaultManualLoc = $plantasGC->first()?->ubicaciones->first()?->id;
            @endphp
            
            <a href="{{ route('detection_time', ['mode' => 'iot', 'location_id' => ($location_id === 'all' ? 'all' : (($location_id && !$isCtrl) ? $location_id : $defaultIotLoc)), 'filter' => $filter]) }}" 
               class="flex-1 max-w-xs px-6 py-4 rounded-2xl font-black text-sm transition-all flex items-center justify-center gap-3 {{ $mode === 'iot' ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-200' : 'bg-white text-slate-600 border-2 border-slate-200 hover:border-emerald-300' }}">
                <i class="fas fa-robot text-xl"></i>
                <div class="text-left">
                    <div class="text-xs uppercase tracking-wider">Sensores IoT</div>
                    <div class="text-[10px] font-medium opacity-80">Datos automáticos</div>
                </div>
            </a>
            
            <a href="{{ route('detection_time', ['mode' => 'manual', 'location_id' => ($location_id === 'all' ? 'all' : (($location_id && $isCtrl) ? $location_id : $defaultManualLoc)), 'filter' => $filter]) }}" 
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
            <form method="GET" action="{{ route('detection_time') }}" id="location-form">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">
                    🌳 Seleccionar Planta de Palto — {{ $mode === 'iot' ? 'Grupo Experimental' : 'Grupo Control' }}
                </label>
                <select name="location_id" id="location-selector" 
                        class="w-full p-4 bg-white border-2 border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:border-{{ $mode === 'iot' ? 'emerald' : 'amber' }}-500 transition-all shadow-sm">
                    @if($mode === 'iot')
                        <optgroup label=" GRUPO EXPERIMENTAL (IoT)">
                            <option value="all" {{ $location_id === 'all' ? 'selected' : '' }}>
                                🌳- Todas las Plantas (Consolidado)
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
                            <option value="all" {{ $location_id === 'all' ? 'selected' : '' }}>
                                🌳- Todas las Plantas (Consolidado)
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
                            {{ $mode === 'manual' ? 'Registre manualmente los tiempos de detección de eventos' : 'Tiempos calculados automáticamente desde las alertas del sistema.' }}
                        </p>
                    </div>
                </div>
                
                @if($mode === 'manual')
                    <button type="button" onclick="openManualModal()" class="px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-2xl font-black text-sm shadow-xl shadow-amber-200 transform hover:-translate-y-1 transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> NUEVO REGISTRO MANUAL
                    </button>
                @else
                    <div class="px-4 py-2 bg-white rounded-xl border border-emerald-100 flex items-center gap-2">
                        <div class="live-dot"></div>
                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Cálculo Automático</span>
                    </div>
                @endif
            </div>
        </div>
    @elseif($isAllPlants ?? false)
        <div class="mb-8 p-8 rounded-3xl border {{ $mode === 'manual' ? 'border-amber-200/70 bg-gradient-to-br from-amber-50 to-white shadow-md shadow-amber-100/40' : 'border-emerald-200/70 bg-gradient-to-br from-emerald-50 to-white shadow-md shadow-emerald-100/40' }}">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="p-3 {{ $mode === 'manual' ? 'bg-amber-500' : 'bg-emerald-500' }} text-white rounded-2xl shadow-lg">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <div>
                        <h3 class="text-xl font-black {{ $mode === 'manual' ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $mode === 'manual' ? ' Modo Manual — ' : '📡 Modo IoT — ' }}🌳- Todas las Plantas
                        </h3>
                        <p class="text-sm font-medium {{ $mode === 'manual' ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $mode === 'manual' ? 'Vista consolidada de tiempos de detección de todas las plantas del Grupo Control' : 'Vista consolidada de tiempos de detección de todas las plantas del Grupo Experimental' }}
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

    {{-- KPIs --}}
    <div class="kpi-grid">
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-blue);">
            <div class="icon-box"><i class="fas fa-chart-bar"></i></div>
            <div class="label">Total de Alertas</div>
            <div class="value" style="color: var(--accent-blue);">{{ $total_alerts }}</div>
            <div class="trend" style="color: var(--accent-blue);">Registradas</div>
        </div>
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-green);">
            <div class="icon-box"><i class="fas fa-calendar-days"></i></div>
            <div class="label">Días Analizados</div>
            <div class="value" style="color: var(--accent-green);">{{ $unique_days }}</div>
            <div class="trend" style="color: var(--accent-green);">Período</div>
        </div>
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-purple);">
            <div class="icon-box"><i class="fas fa-hourglass-end"></i></div>
            <div class="label">Promedio Gral.</div>
            <div class="value" style="color: var(--accent-purple); font-family: monospace;">
                {{ $unique_days > 0 && $total_alerts > 0 ? round(collect($detectionRecords->items())->avg('tiempo_promedio_segundos'), 2) : '--' }}s
            </div>
            <div class="trend" style="color: var(--accent-purple);">segundos</div>
        </div>
    </div>

    {{-- Charts Block --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="glass-card p-6">
            <h4 class="kpi-title">Evolución Tiempo Promedio</h4>
            <div style="height:220px;"><canvas id="dtAvgTimeChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Eventos por Día</h4>
            <div style="height:220px;"><canvas id="dtEventsChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Entradas: Manual vs Automático</h4>
            <div style="height:220px; display:flex; align-items:center; justify-content:center;"><canvas id="dtManualAutoChart" style="max-width:260px;"></canvas></div>
        </div>
    </div>

    {{-- Tabla de Registros --}}
    <div class="glass-card mb-12">
        <div class="filter-section rounded-t-2xl">
            <div class="filter-group">
                <label>Planta Actual</label>
                <div class="px-4 py-2 bg-slate-50 rounded-xl font-bold text-slate-700">
                    @if($isAllPlants ?? false)
                        🌳 Todas las Plantas
                    @else
                        @php $sel = $ubicacionSeleccionada ?? null; @endphp
                        🌳 {{ $sel?->planta?->nombre ?? 'N/D' }}
                        @if($sel?->planta?->numero_planta)
                            (Planta {{ $sel->planta->numero_planta }})
                        @endif
                    @endif
                </div>
            </div>
            <div class="filter-group">
                <label>Período</label>
                <div class="flex items-center">
                    <div class="inline-flex bg-white/80 backdrop-blur-md p-1 rounded-2xl shadow-sm ring-1 ring-slate-200">
                        @foreach(['24h', '7d', '14d', '30d', 'all'] as $f)
                            <a href="{{ route('detection_time', ['location_id' => $location_id, 'mode' => $mode, 'filter' => $f]) }}"
                               class="filter-btn {{ $filter === $f ? 'active' : '' }}">
                                {{ strtoupper($f) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                <span class="px-3 py-1 bg-slate-100 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    {{ count($detectionRecords->items()) }} Registros
                </span>
            </div>
        </div>

        <div class="table-container">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-4">#</th>
                        <th class="px-6 py-4">Fecha</th>
                        <th class="px-6 py-4">Ti (Inicio)</th>
                        <th class="px-6 py-4">Tf (Confirmación)</th>
                        <th class="px-6 py-4 text-center">
                            <div>N (Tiempo)</div>
                            <div style="font-size: 0.6rem; font-weight: 500; color: #8b5cf6;">Solo VP medibles</div>
                        </th>
                        <th class="px-6 py-4">Tiempo Promedio</th>
                        <th class="px-6 py-4">Planta</th>
                        <th class="px-6 py-4">Tipo Entrada</th>
                    </tr>
                </thead>
                <tbody id="detection-body">
                    @if(count($detectionRecords->items()) > 0)
                        @foreach($detectionRecords->items() as $index => $day)
                            @php
                                // N Tiempo viene de tiempos_deteccion (cantidad_eventos)
                                $nTiempo = $day->cantidad_eventos;
                                
                                // Calcular Ti y Tf aproximados
                                $tiHora = $day->tiempo_inicial ? $day->tiempo_inicial->format('H:i:s') : '08:00:00';
                                $tfHora = $day->tiempo_final ? $day->tiempo_final->format('H:i:s') : '08:00:00';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #374151;">
                                        {{ $detectionRecords->firstItem() + $index }}
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1a472a;">
                                        {{ \Carbon\Carbon::parse($day->fecha)->format('d/m/Y') }}
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 700; color: #3b82f6;">
                                        {{ $tiHora }}
                                    </div>
                                    <div style="font-size: 0.65rem; color: #9ca3af;">Ti</div>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 700; color: #10b981;">
                                        {{ $tfHora }}
                                    </div>
                                    <div style="font-size: 0.65rem; color: #9ca3af;">Tf</div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-weight: 900; color: #7c3aed; font-size: 1.25rem; line-height: 1;">
                                        {{ $nTiempo }}
                                    </div>
                                    <div style="font-size: 0.65rem; color: #64748b; font-weight: 700; margin-top: 2px;">
                                        Eventos medibles
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 900; color: #059669; font-size: 1.1rem;">
                                        {{ number_format($day->tiempo_promedio_segundos, 2) }}s
                                    </div>
                                    <div style="font-size: 0.7rem; color: #9ca3af; font-weight: 600;">
                                        (~{{ round($day->tiempo_promedio_segundos / 60, 2) }} min)
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #374151; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                        <span style="font-size: 1rem;">🌳</span>
                                        <div>
                                            <div>{{ $day->ubicacion?->planta?->nombre ?? 'N/D' }}</div>
                                            @if($day->ubicacion?->planta?->numero_planta)
                                                <div style="font-size: 0.75rem; color: #9ca3af; font-weight: 600;">
                                                    (N°{{ $day->ubicacion->planta->numero_planta }})
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($day->tipo_entrada === 'manual')
                                        <span class="badge bg-amber-100 text-amber-700" style="padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 800;">
                                            <i class="fas fa-user-edit"></i> Manual
                                        </span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-600" style="padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 800;">
                                            <i class="fas fa-robot"></i> Automático
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No se encontraron registros de tiempo de detección con los filtros seleccionados.</p>
                            </td>
                         </tr>
                    @endif
                </tbody>
            </table>
        </div> </div> </div> {{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- 🆕 MODAL: REGISTRO MANUAL                                           --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div id="manualModal" class="eval-overlay">
    <div class="eval-box">
        <div class="eval-header">
            <h3>⏱️ Nuevo Registro Manual — Tiempo de Detección</h3>
            <button type="button" class="eval-close" onclick="closeManualModal()">✕</button>
        </div>

        <p class="text-sm text-slate-600 mb-4 font-semibold">
            Registre manualmente los tiempos de detección para la planta seleccionada.
        </p>

        <form action="{{ route('detection_time.store_manual') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="text-xs font-black text-slate-500 uppercase tracking-wider">🌳 Planta de palto (Grupo Control)</label>
                <select name="ubicacion_id" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                    <option value="">Seleccione planta</option>
                    @foreach($plantasGC as $planta)
                        @php $loc = $planta->ubicaciones->first(); @endphp
                        @if($loc)
                            <option value="{{ $loc->id }}" {{ (isset($ubicacionSeleccionada) && $ubicacionSeleccionada->id == $loc->id) ? 'selected' : '' }}>
                                 {{ $planta->nombre }} (Planta {{ $planta->numero_planta }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">📅 Fecha del Evento</label>
                    <input type="date" name="fecha" value="{{ date('Y-m-d') }}" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">🔢 Cantidad de Eventos</label>
                    <input type="number" name="cantidad_eventos" value="{{ old('cantidad_eventos', 1) }}" min="1" required placeholder="Ej: 3"
                        class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" />
                </div>

                <div>
                    <label class="text-xs font-black text-amber-600 uppercase tracking-wider">⏰ Hora de Alerta (Ti)</label>
                    <input type="time" step="1" name="hora_alerta" id="modal-ti" oninput="updateModalTAR()"
                           class="w-full p-3 border border-amber-100 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-amber-600 uppercase tracking-wider">⏰ Hora de Evento (Tf)</label>
                    <input type="time" step="1" name="hora_evento" id="modal-tf" oninput="updateModalTAR()"
                           class="w-full p-3 border border-amber-100 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>
            </div>

            {{-- Preview TAR en tiempo real --}}
            <div class="mt-4 p-4 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl">
                <div class="text-[10px] font-black uppercase text-purple-600 tracking-widest mb-1">Vista previa del cálculo</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs font-bold text-slate-600">Tiempo de Detección =</span>
                    <span class="text-2xl font-black text-purple-700 font-mono" id="modal-tar-preview">--</span>
                    <span class="status-badge bg-purple-50 text-purple-600 ml-2" id="modal-tar-unit">segundos</span>
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
/* ========== MODAL DE REGISTRO MANUAL ========== */
function openManualModal() {
    document.getElementById('manualModal').classList.add('active');
}

function closeManualModal() {
    document.getElementById('manualModal').classList.remove('active');
}

// ✅ CORREGIDO: Sincronización con localStorage respetando "all"
(function syncRealtimeSelection() {
    const mode = '{{ $mode }}';
    if (mode !== 'iot') return;
    
    const selector = document.getElementById('location-selector');
    if (!selector) return;
    
    const currentUrl = new URL(window.location.href);
    const urlLocationId = currentUrl.searchParams.get('location_id');
    
    if (urlLocationId === 'all') {
        selector.value = 'all';
        return;
    }
    
    const savedLoc = localStorage.getItem('agro_loc');
    if (!savedLoc || savedLoc === 'all') return;
    
    const opt = selector.querySelector(`option[value="${savedLoc}"]`);
    if (opt && selector.value !== savedLoc) {
        selector.value = savedLoc;
        selector.closest('form').submit();
    }
})();

// ✅ NUEVO: Guardar selección en localStorage cuando cambia
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('location-selector');
    if (selector) {
        selector.addEventListener('change', function() {
            const newValue = this.value;
            if (newValue === 'all') {
                localStorage.setItem('agro_loc', 'all');
            } else if (newValue) {
                localStorage.setItem('agro_loc', newValue);
            } else {
                localStorage.removeItem('agro_loc');
            }
            this.closest('form').submit();
        });
    }
});

function updateModalTAR() {
    const ti = document.getElementById('modal-ti').value;
    const tf = document.getElementById('modal-tf').value;
    if (!ti || !tf) {
        document.getElementById('modal-tar-preview').textContent = '--';
        return;
    }
    
    const [tiH, tiM, tiS] = ti.split(':').map(Number);
    const [tfH, tfM, tfS] = tf.split(':').map(Number);
    const tiSec = tiH * 3600 + tiM * 60 + tiS;
    const tfSec = tfH * 3600 + tfM * 60 + tfS;
    
    const diff = Math.abs(tfSec - tiSec);
    document.getElementById('modal-tar-preview').textContent = diff;
    
    if (diff >= 60) {
        document.getElementById('modal-tar-unit').textContent = `seg (~${(diff/60).toFixed(1)} min)`;
    } else {
        document.getElementById('modal-tar-unit').textContent = 'segundos';
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
document.addEventListener('DOMContentLoaded', function(){
    const dates = @json($datesJson ?? '[]');
    const avgTimes = @json($avgTimesJson ?? '[]');
    const events = @json($eventsJson ?? '[]');
    const manual = {{ $manualCount ?? 0 }};
    const automatic = {{ $automaticCount ?? 0 }};

    const ctx1 = document.getElementById('dtAvgTimeChart');
    if (ctx1) new Chart(ctx1, {
        type: 'line',
        data: { labels: JSON.parse(dates), datasets: [{ label: 'Tiempo promedio (s)', data: JSON.parse(avgTimes), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.05)', fill:true, tension:0.25 }] },
        options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
    });

    const ctx2 = document.getElementById('dtEventsChart');
    if (ctx2) new Chart(ctx2, {
        type: 'bar',
        data: { labels: JSON.parse(dates), datasets: [{ label: 'Eventos', data: JSON.parse(events), backgroundColor:'#3b82f6' }] },
        options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, stepSize:1 } } }
    });

    const ctx3 = document.getElementById('dtManualAutoChart');
    if (ctx3) new Chart(ctx3, {
        type: 'doughnut',
        data: { labels:['Manual','Automático'], datasets:[{ data:[manual, automatic], backgroundColor:['#f59e0b','#64748b'] }] },
        options: { responsive:true, maintainAspectRatio:false, cutout:'60%' }
    });
});
</script>
@endpush