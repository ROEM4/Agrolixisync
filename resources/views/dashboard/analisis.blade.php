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
        --control-primary: #d97706;
        --control-secondary: #475569;
        --exp-primary: #4f46e5;
        --exp-secondary: #059669;
    }

    body { font-family: var(--academic-font); }

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

    .metric-value { font-family: var(--outfit-font); font-weight: 800; line-height: 1; }
    .metric-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }

    .scientific-badge {
        font-family: var(--outfit-font);
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        font-size: 0.65rem;
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* ===== MODAL DE EVALUACIÓN DE ALERTA (Telegram) ===== */
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
        font-family: var(--outfit-font);
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

    .alert-info-card {
        background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%);
        border: 1px solid #c7d2fe;
        border-radius: 16px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }
    .alert-info-card .label {
        font-size: 0.65rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #6366f1;
        margin-bottom: 0.25rem;
    }
    .alert-info-card .value {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
    }

    .eval-actions {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .eval-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.9rem 1.1rem;
        border-radius: 14px;
        border: 2px solid transparent;
        font-family: var(--outfit-font);
        font-weight: 800;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
        text-align: left;
    }
    .eval-btn small {
        font-weight: 500;
        font-size: 0.72rem;
        opacity: 0.85;
    }
    .eval-btn-vp { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .eval-btn-vp:hover { background: #d1fae5; transform: translateX(4px); }
    .eval-btn-fp { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .eval-btn-fp:hover { background: #fee2e2; transform: translateX(4px); }

    .eval-divider {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 1rem 0 0.5rem;
        color: #94a3b8;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    .eval-divider::before, .eval-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    .close-day-btn {
        width: 100%;
        padding: 0.85rem;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        border: none;
        border-radius: 14px;
        font-family: var(--outfit-font);
        font-weight: 800;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .close-day-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(15,23,42,0.25); }

    /* ===== LISTA DE ALERTAS PENDIENTES ===== */
    .alert-pending-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.9rem 1.1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #6366f1;
        border-radius: 14px;
        margin-bottom: 0.6rem;
        transition: all 0.2s;
    }
    .alert-pending-item:hover {
        border-left-color: #4f46e5;
        box-shadow: 0 4px 12px rgba(99,102,241,0.1);
        transform: translateX(2px);
    }
    .alert-pending-info { display: flex; flex-direction: column; gap: 0.15rem; }
    .alert-pending-title { font-weight: 800; color: #0f172a; font-size: 0.85rem; }
    .alert-pending-meta { font-size: 0.7rem; color: #64748b; font-weight: 600; }
    .alert-eval-btn {
        padding: 0.45rem 0.9rem;
        background: #4f46e5;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .alert-eval-btn:hover { background: #4338ca; transform: scale(1.05); }

    .empty-alerts {
        text-align: center;
        padding: 2rem;
        color: #94a3b8;
        font-style: italic;
        font-size: 0.85rem;
        background: #f8fafc;
        border-radius: 14px;
        border: 1px dashed #cbd5e1;
    }

    /* ===== BOTÓN CERRAR DÍA ===== */
    .close-day-header-btn {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-family: var(--outfit-font);
        font-weight: 800;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        box-shadow: 0 4px 12px rgba(15,23,42,0.2);
    }
    .close-day-header-btn:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 20px rgba(15,23,42,0.3);
    }

    .day-closed-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: #fff;
        border-radius: 12px;
        font-family: var(--outfit-font);
        font-weight: 800;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
</style>

<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

    {{-- ==================================================================
         1. HEADER
         ================================================================== --}}
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-6">
        <div>
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight" style="font-family: var(--outfit-font);">
                Porcentaje de Precisión de detección de Pérdida de Fertilizantes
            </h1>
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            {{-- SELECTOR PARA CAMBIAR DE PLANTA --}}
            @if(isset($locations) && $locations->isNotEmpty())
                <div class="flex flex-col">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">
                        🔵 Cambiar Planta GE
                    </label>
                    <select id="location-selector-analisis" 
                            class="min-w-[280px] p-2.5 border-2 border-slate-200 rounded-xl text-sm font-bold text-slate-700 bg-white focus:border-indigo-500 outline-none transition-colors shadow-sm">
                        <option value="all" {{ ($isAllPlants ?? false) ? 'selected' : '' }}>🌳 Todas las Plantas (Grupo Experimental)</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" 
                                    {{ (isset($ubicacionSeleccionada) && $ubicacionSeleccionada->id == $loc->id) ? 'selected' : '' }}>
                                🌳 {{ $loc->planta->nombre ?? 'Planta' }} N°{{ $loc->planta->numero_planta ?? '?' }} — {{ $loc->codigo_dispositivo ?? 'N/D' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    {{-- ==================================================================
        2. RESUMEN METODOLÓGICO
        ================================================================== --}}
    <div class="p-6 bg-gradient-to-r from-slate-50 to-slate-100 border-l-4 border-indigo-500 rounded-r-2xl mb-10 shadow-sm relative overflow-hidden">
        <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-5 text-9xl pointer-events-none select-none">🔬</div>
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0 shadow-sm border border-indigo-100">
                <span class="text-lg">📋</span>
            </div>
            <div class="flex-1">
                <h4 class="text-xs font-black uppercase text-indigo-700 tracking-widest mb-1 scientific-badge">Resumen Metodológico</h4>
                <p class="text-sm text-slate-700 leading-relaxed font-semibold italic mb-3">
                    "El grupo control establece la condición real de lixiviación mediante mediciones de conductividad eléctrica, mientras que el sistema IoT evalúa su capacidad de detección comparando sus resultados contra dicha referencia."
                </p>
                
                {{-- NUEVO: FÓRMULA Y VALOR IDEAL --}}
                <div class="mt-3 p-3 bg-white/60 rounded-xl border border-indigo-200">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm">📐</span>
                        <span class="text-xs font-black uppercase text-indigo-700 tracking-wider">Fórmula de Precisión</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-center">
                        <div class="p-2 bg-indigo-50 rounded-lg">
                            <div class="text-[9px] font-black uppercase text-indigo-600 mb-1">ILx Ideal</div>
                            <div class="text-lg font-black text-indigo-800">0.80</div>
                            <div class="text-[9px] text-indigo-600">FAO (LF 20%)</div>
                        </div>
                        <div class="p-2 bg-slate-50 rounded-lg">
                            <div class="text-[9px] font-black uppercase text-slate-600 mb-1">Fórmula</div>
                            <div class="text-xs font-mono font-bold text-slate-800">P = (1 - |ILx - 0.8| / 0.8) × 100</div>
                            <div class="text-[9px] text-slate-600">Error Relativo %</div>
                        </div>
                        <div class="p-2 bg-emerald-50 rounded-lg">
                            <div class="text-[9px] font-black uppercase text-emerald-600 mb-1">ILx = CE_prof / CE_sup</div>
                            <div class="text-xs font-bold text-emerald-800">Nivel de Lixiviación</div>
                            <div class="text-[9px] text-emerald-600">Relación de Conductividades</div>
                        </div>
                    </div>
                </div>
                
                {{-- CLASIFICACIÓN DE NIVELES --}}
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-black border border-blue-200">
                        🔵 Baja: ILx &lt; 0.4
                    </span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-[10px] font-black border border-amber-200">
                        🟡 Media: 0.6 ≤ ILx ≤ 1.0
                    </span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full text-[10px] font-black border border-red-200">
                        🔴 Alta: ILx &gt; 1.0
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================================================================
         3. TABLAS DE DATOS
         ================================================================== --}}
    <div class="grid grid-cols-1 gap-12 mb-12">

        {{-- TABLA GRUPO CONTROL --}}
        <div class="col-span-1">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    Tabla Diaria: Grupo Control
                </h3>
                <div class="flex items-center gap-3">
                    <button type="button" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black transition-colors shadow-sm" id="openManualBtn">
                        <i class="fas fa-plus mr-1"></i> Ingreso Manual
                    </button>
                </div>
            </div>

            <div class="academic-card">
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Planta de palto</th>
                                <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Sup</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">CE Prof</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">ILx</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Precisión %</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Eventos</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($controlRecords as $record)
                                @php
                                    $ceSup = (float) ($record['ce_superficial'] ?? 0);
                                    $ceProf = (float) ($record['ce_profunda'] ?? 0);
                                    $ilx = $ceSup > 0 ? round($ceProf / $ceSup, 4) : 0;
                                    $precision = (float) ($record['porcentaje_pf'] ?? 0);
                                    // Clasificación según tu metodología: Baja (<0.4), Media (0.6-1.0), Alta (>1.0)
                                    if ($ilx < 0.4) {
                                        $estadoMock = 'Baja pérdida';
                                        $estadoClass = 'bg-blue-100 text-blue-700';
                                    } elseif ($ilx >= 0.6 && $ilx <= 1.0) {
                                        $estadoMock = 'Pérdida media';
                                        $estadoClass = 'bg-amber-100 text-amber-700';
                                    } else {
                                        $estadoMock = 'Alta pérdida';
                                        $estadoClass = 'bg-red-100 text-red-700';
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-700">
                                        🌳 {{ $record['planta_nombre'] }} #{{ $record['planta_numero'] }}
                                    </td>
                                    <td class="px-4 py-3">{{ $record['date_label'] }}</td>
                                    <td class="px-3 py-3 font-mono text-blue-600">{{ number_format($ceSup, 3) }}</td>
                                    <td class="px-3 py-3 font-mono text-emerald-600">{{ number_format($ceProf, 3) }}</td>
                                    <td class="px-3 py-3 font-mono font-bold text-slate-800">{{ number_format($ilx, 4) }}</td>
                                    <td class="px-3 py-3 font-black text-amber-700">{{ number_format($precision, 1) }}%</td>
                                    <td class="px-3 py-3 text-center font-mono text-slate-600">{{ $record['subparcela'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black {{ $estadoClass }}">
                                            {{ $estadoMock }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-slate-300 italic font-medium">
                                        No hay registros de control. Usa "Ingreso Manual" para agregar datos del Grupo Control.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    {{-- ==================================================================
        TABLA GRUPO EXPERIMENTAL (IoT) - CON BOTÓN CERRAR DÍA
        ================================================================== --}}
    <div class="col-span-1">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                Tabla Diaria: Grupo Experimental (IoT)
                @if(isset($ubicacionSeleccionada))
                    <span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-lg text-[10px] font-black border border-indigo-200">
                        🌳 {{ $ubicacionSeleccionada->planta->nombre ?? 'N/D' }} N°{{ $ubicacionSeleccionada->planta->numero_planta ?? '?' }}
                    </span>
                @elseif($isAllPlants ?? false)
                    <span class="ml-2 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-black border border-emerald-200">
                        🌳 Todas las Plantas (Consolidado)🌳
                    </span>
                @endif
            </h3>
            
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-[10px] font-black uppercase tracking-wider border border-indigo-200">
                    {{ count($dailyStats) }} día(s) evaluado(s)
                </span>
                
                {{-- ✅ BOTÓN CERRAR DÍA (visible cuando hay evaluaciones pendientes) --}}
                @if(isset($ubicacionSeleccionada) && ($estadoDia ?? '') === 'progreso')
                    <button type="button" onclick="closeDay()" 
                            class="close-day-header-btn">
                        <span>🔒</span>
                        <span>Cerrar Día</span>
                    </button>
                @elseif(isset($ubicacionSeleccionada) && ($estadoDia ?? '') === 'cerrado')
                    <span class="day-closed-badge">
                        <span>✅</span>
                        <span>Día Cerrado</span>
                    </span>
                @endif
            </div>
        </div>

        <div class="academic-card">
            <div class="overflow-x-auto w-full">
                {{-- ✅ CORRECCIÓN: Solo mostrar mensaje si NO hay planta seleccionada Y NO es "Todas las Plantas" --}}
                @if(!isset($ubicacionSeleccionada) && !($isAllPlants ?? false))
                    {{-- ⚠️ MENSAJE SI NO HAY PLANTA SELECCIONADA --}}
                    <div class="p-12 text-center">
                        <div class="text-6xl mb-4">🎯</div>
                        <h4 class="text-lg font-black text-slate-700 mb-2">Selecciona una Planta para ver sus datos</h4>
                        <p class="text-sm text-slate-500 mb-4">
                            Ve a <a href="{{ route('realtime') }}" class="text-indigo-600 font-bold hover:underline">Monitoreo en Tiempo Real</a> 
                            y selecciona una planta del grupo experimental, o usa el selector superior.
                        </p>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 text-amber-700 rounded-xl text-xs font-bold border border-amber-200">
                            💡 Los datos se sincronizan automáticamente con realtime.blade.php
                        </div>
                    </div>
                @else
                    {{-- ✅ MOSTRAR TABLA (ya sea para planta individual o todas las plantas) --}}
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Planta de palto</th>
                                <th class="px-4 py-3 text-[9px] font-extrabold uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider text-center">VP</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider text-center">FP</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider text-center bg-slate-100/80 font-black">Total Alertas</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">PDS %</th>
                                <th class="px-3 py-3 text-[9px] font-extrabold uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($dailyStats as $day)
                                @php
                                    $totalAlerts = ($day['vp'] ?? 0) + ($day['fp'] ?? 0);
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-700">
                                        🌳 {{ $day['planta_nombre'] }} 
                                        <span class="text-slate-400 font-medium text-[10px]">N°{{ $day['planta_numero'] ?? '?' }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-bold">{{ $day['date_label'] }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-emerald-700">{{ $day['vp'] }}</td>
                                    <td class="px-3 py-3 text-center font-bold text-red-600">{{ $day['fp'] }}</td>
                                    <td class="px-3 py-3 text-center font-black bg-slate-50/50 text-slate-800 font-mono text-sm">{{ $totalAlerts }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-black {{ $day['pds_percentage'] >= 80 ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : 'bg-amber-100 text-amber-700 border border-amber-300' }}">
                                            {{ number_format($day['pds_percentage'], 1) }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($day['consolidado'] ?? false)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">
                                                🔒 Consolidado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-700">
                                                ⏳ Pendiente
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic font-medium">
                                        <div class="text-4xl mb-3">📭</div>
                                        <div class="font-bold text-slate-600 mb-1">Sin evaluaciones registradas</div>
                                        <div class="text-xs">
                                            @if($isAllPlants ?? false)
                                                No hay evaluaciones para ninguna planta del grupo experimental.
                                            @else
                                                {{ $mensajeDia ?? 'Evalúa alertas desde la sección inferior para ver datos aquí.' }}
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ==================================================================
         4. KPIs y Gráficos
         ================================================================== --}}
        @if(isset($ubicacionSeleccionada) || ($isAllPlants ?? false))        
        {{-- KPIs --}}
        <div class="mb-8">
            <div class="academic-card p-6 border-t-4 border-indigo-500 max-w-3xl mx-auto">
                <h4 class="text-[10px] font-black uppercase text-indigo-700 tracking-wider mb-5 scientific-badge text-center">
                    Indicadores de Desempeño del Sistema (KPIs)
                </h4>
                @php
                    $kpiVP    = $stats['vp']  ?? 0;
                    $kpiFP    = $stats['fp']  ?? 0;
                    $kpiTotal = $kpiVP + $kpiFP;
                    $kpiPDS   = $stats['pds_percentage'] ?? 0;
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-2xl bg-emerald-100 border border-emerald-300 p-5 text-center">
                        <div class="text-[10px] font-black uppercase text-emerald-700">VP</div>
                        <div class="text-2xl font-black text-emerald-800 mt-2">{{ $kpiVP }}</div>
                        <div class="text-[10px] text-emerald-600 mt-2">Verdaderos Positivos</div>
                    </div>
                    <div class="rounded-2xl bg-red-100 border border-red-300 p-5 text-center">
                        <div class="text-[10px] font-black uppercase text-red-700">FP</div>
                        <div class="text-2xl font-black text-red-800 mt-2">{{ $kpiFP }}</div>
                        <div class="text-[10px] text-red-600 mt-2">Falsos Positivos</div>
                    </div>
                    <div class="rounded-2xl bg-slate-100 border border-slate-300 p-5 text-center">
                        <div class="text-[10px] font-black uppercase text-slate-700">Total Alertas</div>
                        <div class="text-2xl font-black text-slate-800 mt-2">{{ $kpiTotal }}</div>
                        <div class="text-[10px] text-slate-600 mt-2">VP + FP</div>
                    </div>
                    <div class="rounded-2xl bg-indigo-100 border border-indigo-300 p-5 text-center">
                        <div class="text-[10px] font-black uppercase text-indigo-700">PDS %</div>
                        <div class="text-2xl font-black text-indigo-800 mt-2">{{ number_format($kpiPDS, 1) }}%</div>
                        <div class="text-[10px] text-indigo-600 mt-2">Precisión del Sistema</div>
                    </div>
                </div>
                <div class="mt-5 text-center border-t border-slate-100 pt-3">
                    <span class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                        Evaluación de detección de eventos de lixiviación — 
                        @if($isAllPlants ?? false)
                            Todas las Plantas del Grupo Experimental
                        @else
                            {{ $ubicacionSeleccionada->planta->nombre ?? 'N/D' }} N°{{ $ubicacionSeleccionada->planta->numero_planta ?? '?' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- GRÁFICOS --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <div class="academic-card p-6 border-t-4 border-emerald-500">
                <h4 class="text-[10px] font-black uppercase text-emerald-700 tracking-wider mb-4 scientific-badge text-center">Precisión de Detección vs Tasa de Error</h4>
                <div class="relative h-[240px] w-full flex items-center justify-center">
                    <canvas id="precisionErrorChart"></canvas>
                </div>
            </div>

            <div class="academic-card p-6 border-t-4 border-blue-500">
                <h4 class="text-[10px] font-black uppercase text-blue-700 tracking-wider mb-4 scientific-badge text-center">Evolución Temporal del Desempeño del Sistema</h4>
                <div class="relative h-[240px] w-full flex items-center justify-center">
                    <canvas id="temporalEvolutionChart"></canvas>
                </div>
            </div>
        </div>
    @endif

    {{-- ==================================================================
         5. EVALUACIÓN (Alertas Pendientes)
         ================================================================== --}}
        @if(isset($ubicacionSeleccionada) || ($isAllPlants ?? false))        <div class="academic-card p-6 mb-10 border-t-4 border-indigo-500">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600">
                        <span class="text-lg">📡</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                            Alertas Recibidas vía Telegram
                        </h3>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">
                            {{ $mensajeDia ?? 'Evalúa cada alerta comparándola con la verdad de campo' }}
                        </p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-[10px] font-black uppercase tracking-wider border border-indigo-200">
                    {{ count($pendingAlertas ?? []) }} pendiente(s)
                </span>
            </div>

            <div id="alertsContainer">
                @forelse($pendingAlertas ?? [] as $alert)
                    <div class="alert-pending-item" data-alert-id="{{ $alert->id }}">
                        <div class="alert-pending-info">
                            <span class="alert-pending-title">
                                🌳 Planta de palto: {{ $alert->ubicacion->planta->nombre ?? 'N/D' }} — {{ $alert->tipo ?? 'Lixiviación detectada' }}
                            </span>
                            <span class="alert-pending-meta">
                                📅 {{ \Carbon\Carbon::parse($alert->created_at)->format('d/m/Y H:i') }} · 
                                CE Actual: {{ number_format($alert->ce_actual ?? 0, 3) }} · 
                                Δ CE: {{ number_format($alert->delta_ce ?? 0, 3) }}
                            </span>
                        </div>
                        <button type="button" class="alert-eval-btn" id="btn-eval-{{ $alert->id }}" data-alert-id="{{ $alert->id }}" data-lote-name="{{ addslashes($alert->ubicacion->planta->nombre ?? 'N/D') }}" data-date="{{ \Carbon\Carbon::parse($alert->created_at)->format('d/m/Y H:i') }}" data-type="{{ addslashes($alert->tipo ?? 'Lixiviación') }}" data-device="{{ addslashes($alert->ubicacion->codigo_dispositivo ?? 'N/D') }}">Evaluar</button>
                            Evaluar
                        
                    </div>
                @empty
                    <div class="empty-alerts">
                        🔕 No hay alertas pendientes de evaluación para esta planta.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

</div>

{{-- ===== MODAL: INGRESO MANUAL (GRUPO CONTROL) ===== --}}
<div id="manualModal" class="eval-overlay">
    <div class="eval-box">
        <div class="eval-header">
            <h3>📝 Ingreso Manual — Grupo Control</h3>
            <button type="button" class="eval-close" id="closeManualBtn">✕</button>
        </div>

        <form action="{{ route('analisis.pf_manual') }}" method="POST">
            @csrf
            
            {{-- ⚠️ PRIMERO LA FECHA, LUEGO LA UBICACIÓN --}}
            <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm">📅</span>
                    <span class="text-xs font-black uppercase text-amber-700 tracking-wider">Paso 1: Selecciona la Fecha</span>
                </div>
                <input type="date" name="fecha_registro" id="fecha_registro" value="{{ date('Y-m-d') }}" 
                       class="w-full p-3 border-2 border-amber-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent font-bold" required />
                <p class="text-[10px] text-amber-600 mt-1 font-semibold">💡 Se mostrarán solo las ubicaciones sin registros para esta fecha</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">
                        🌳 Ubicación (Location) 
                        <span id="ubicacionesDisponibles" class="text-emerald-600 ml-2"></span>
                    </label>
                    <select name="ubicacion_id" id="ubicacion_selector" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                        <option value="">🔄 Selecciona primero una fecha...</option>
                    </select>
                    <div id="loadingUbicaciones" class="hidden mt-2 flex items-center gap-2 text-xs text-slate-500">
                        <span class="animate-spin">⏳</span>
                        <span>Cargando ubicaciones disponibles...</span>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">CE Superficial (dS/m)</label>
                    <input type="number" step="0.001" id="ce_superficial" name="ce_superficial" placeholder="Ej: 0.450" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">CE Profunda (dS/m)</label>
                    <input type="number" step="0.001" id="ce_profunda" name="ce_profunda" placeholder="Ej: 0.520" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">🔢 Número de Evento</label>
                    <input type="number" name="events" min="1" placeholder="Ej: 15" class="w-full p-3 border border-slate-200 rounded-xl mt-1 focus:ring-2 focus:ring-amber-500 focus:border-transparent" required />
                </div>

                <div>
                    <label class="text-xs font-black text-slate-500 uppercase tracking-wider">📊 % Precisión</label>
                    <input type="number" step="0.1" min="0" max="100" id="porcentaje_pf" name="porcentaje_pf" placeholder="Se calcula automáticamente" class="w-full p-3 border-2 border-indigo-300 rounded-xl mt-1 bg-indigo-50 font-bold text-indigo-700" readonly />
                    <p class="text-[10px] text-indigo-600 mt-1 font-semibold">💡 Se calcula automáticamente al ingresar CE</p>
                </div>
            </div>

            {{-- PANEL DE CÁLCULO EN TIEMPO REAL --}}
            <div id="calcPreview" class="mt-4 p-4 bg-gradient-to-r from-slate-50 to-slate-100 border-l-4 border-indigo-500 rounded-r-xl hidden">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-lg">🧮</span>
                    <span class="text-xs font-black uppercase text-indigo-700 tracking-wider">Cálculo en Tiempo Real</span>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <div class="text-[9px] font-black uppercase text-slate-500">ILx</div>
                        <div class="text-lg font-black text-slate-800" id="previewILx">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black uppercase text-slate-500">Categoría</div>
                        <div class="text-sm font-black px-2 py-1 rounded-lg" id="previewCategoria">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black uppercase text-slate-500">Precisión</div>
                        <div class="text-lg font-black text-indigo-700" id="previewPrecision">—</div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="cancelManual" class="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-black shadow-sm">
                    💾 Guardar Registro
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== MODAL: EVALUAR ALERTA DE TELEGRAM ===== --}}
<div id="evalModal" class="eval-overlay">
    <div class="eval-box">
        <div class="eval-header">
            <h3>🧠 Evaluar Alerta</h3>
            <button type="button" class="eval-close" onclick="closeEvalModal()">✕</button>
        </div>

        <p class="text-sm text-slate-600 mb-4 font-semibold">
            ¿Esta alerta fue correcta según la situación real del cultivo?
        </p>

        <div class="alert-info-card">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="label">🌳 Planta de palto</div>
                    <div class="value" id="evalLoteName">—</div>
                </div>
                <div>
                    <div class="label">📅 Fecha y hora</div>
                    <div class="value" id="evalDate">—</div>
                </div>
                <div>
                    <div class="label">📟 Device Code</div>
                    <div class="value" id="evalDevice">—</div>
                </div>
                <div>
                    <div class="label">📢 Tipo de alerta</div>
                    <div class="value" id="evalType">—</div>
                </div>
            </div>
        </div>

        <form id="evalForm" method="POST">
            @csrf
            <input type="hidden" name="alert_id" id="evalAlertId">
            <input type="hidden" name="evaluation" id="evalValue">

            <div class="eval-actions">
                <button type="submit" class="eval-btn eval-btn-vp" onclick="setEval('VP')">
                    <span>✔ Verdadero Positivo</span>
                    <small>Alerta correcta</small>
                </button>
                <button type="submit" class="eval-btn eval-btn-fp" onclick="setEval('FP')">
                    <span>❌ Falso Positivo</span>
                    <small>Error de alerta</small>
                </button>
            </div>

            <div class="eval-divider">Corte metodológico</div>

            <button type="button" class="close-day-btn" onclick="closeDay()">
                🔒 Cerrar día y consolidar evaluaciones
            </button>
        </form>
    </div>
</div>

{{-- ===== SCRIPTS ===== --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    /* ═══════════════════════════════════════════════════════════════
       SINCRONIZACIÓN AUTOMÁTICA CON REALTIME.BLADE.PHP
       ═══════════════════════════════════════════════════════════════ */
    const selectorAnalisis = document.getElementById('location-selector-analisis');
    const currentUrl = new URL(window.location.href);
    const currentLocId = currentUrl.searchParams.get('ubicacion_id');

    // 1. Al cargar: si NO hay ubicación en URL, leer de localStorage o de la resolución de highlight_alert
    if (!currentLocId) {
        const highlightAlert = currentUrl.searchParams.get('highlight_alert');
        if (highlightAlert && selectorAnalisis && selectorAnalisis.value) {
            localStorage.setItem('agro_loc', selectorAnalisis.value);
            currentUrl.searchParams.set('ubicacion_id', selectorAnalisis.value);
            window.location.href = currentUrl.toString();
            return;
        }

        const savedLoc = localStorage.getItem('agro_loc');
        if (savedLoc && selectorAnalisis && selectorAnalisis.querySelector(`option[value="${savedLoc}"]`)) {
            currentUrl.searchParams.set('ubicacion_id', savedLoc);
            window.location.href = currentUrl.toString();
            return;
        }
    }

    // 2. Sincronizar selector con la URL actual
    if (selectorAnalisis && currentLocId) {
        selectorAnalisis.value = currentLocId;
    }

    // 3. Al cambiar la selección: guardar en localStorage y recargar
    if (selectorAnalisis) {
        selectorAnalisis.addEventListener('change', function() {
            const newLocId = this.value;
            const url = new URL(window.location.href);
            
            if (newLocId) {
                localStorage.setItem('agro_loc', newLocId);
                url.searchParams.set('ubicacion_id', newLocId);
            } else {
                localStorage.removeItem('agro_loc');
                url.searchParams.delete('ubicacion_id');
            }
            window.location.href = url.toString();
        });
    }

    // 4. Escuchar cambios desde realtime (en otra pestaña)
    window.addEventListener('storage', function(e) {
        if (e.key === 'agro_loc' && e.newValue !== currentLocId) {
            const url = new URL(window.location.href);
            if (e.newValue) {
                url.searchParams.set('ubicacion_id', e.newValue);
            } else {
                url.searchParams.delete('ubicacion_id');
            }
            window.location.href = url.toString();
        }
    });

    /* ---------- MODAL INGRESO MANUAL ---------- */
    const openManualBtn = document.getElementById('openManualBtn');
    const manualModal = document.getElementById('manualModal');
    const closeManualBtn = document.getElementById('closeManualBtn');
    const cancelManual = document.getElementById('cancelManual');

    if (openManualBtn) openManualBtn.addEventListener('click', () => manualModal.classList.add('active'));
    if (closeManualBtn) closeManualBtn.addEventListener('click', () => manualModal.classList.remove('active'));
    if (cancelManual) cancelManual.addEventListener('click', () => manualModal.classList.remove('active'));

    /* ---------- CHARTS ---------- */
    @if(isset($ubicacionSeleccionada) || ($isAllPlants ?? false))
        const ctxPrecision = document.getElementById('precisionErrorChart');
        if (ctxPrecision) {
            new Chart(ctxPrecision, {
                type: 'doughnut',
                data: {
                    labels: ['Precisión del Sistema', 'Tasa de Error'],
                    datasets: [{
                        data: [{{ $stats['pds_percentage'] ?? 0 }}, {{ $stats['error_rate'] ?? 0 }}],
                        backgroundColor: ['#4f46e5', '#f87171'],
                        borderWidth: 0
                    }]
                },
                plugins: [{
                    id: 'centerText',
                    beforeDraw(chart) {
                        const { ctx } = chart;
                        const meta = chart.getDatasetMeta(0);
                        if (!meta.data.length) return;
                        const x = meta.data[0].x;
                        const y = meta.data[0].y;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#4f46e5';
                        ctx.font = 'bold 28px Outfit';
                        ctx.fillText('{{ round($stats["pds_percentage"] ?? 0, 1) }}%', x, y - 8);
                        ctx.font = '11px Outfit';
                        ctx.fillStyle = '#64748b';
                        ctx.fillText('Precisión', x, y + 18);
                        ctx.restore();
                    }
                }],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { family: 'Outfit', size: 10, weight: 'bold' }
                            }
                        }
                    }
                }
            });
        }

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
                            label: 'Meta de Precisión (80%)',
                            data: Array(datesArr.length).fill(80),
                            borderColor: '#10b981',
                            borderWidth: 2,
                            borderDash: [6,6],
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Precisión % por día',
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
                            max: 100,
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

    // Add event listeners for evaluation buttons using data attributes
    document.querySelectorAll('.alert-eval-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const alertId = btn.dataset.alertId;
            const loteName = btn.dataset.loteName;
            const date = btn.dataset.date;
            const type = btn.dataset.type;
            const device = btn.dataset.device;
            openEvalModal(alertId, loteName, date, type, device);
        });
    });

    // Auto-abrir modal si viene highlight_alert en la URL
    const highlightAlertId = currentUrl.searchParams.get('highlight_alert');
    if (highlightAlertId) {
        const btn = document.getElementById(`btn-eval-${highlightAlertId}`);
        if (btn) {
            setTimeout(() => {
                btn.click();
            }, 300);
        }
    }
});

/* ---------- LÓGICA DEL MODAL DE EVALUACIÓN ---------- */
function openEvalModal(alertId, loteName, date, type, device) {
    document.getElementById('evalAlertId').value = alertId;
    document.getElementById('evalLoteName').textContent = loteName;
    document.getElementById('evalDate').textContent = date;
    document.getElementById('evalDevice').textContent = device;
    document.getElementById('evalType').textContent = type;
    document.getElementById('evalForm').action = "{{ url('analisis/evaluar-alerta') }}/" + alertId;
    document.getElementById('evalModal').classList.add('active');
}

function closeEvalModal() {
    document.getElementById('evalModal').classList.remove('active');
}

function setEval(value) {
    document.getElementById('evalValue').value = value;
}

function closeDay() {
    const urlParams = new URLSearchParams(window.location.search);
    const ubicacionId = urlParams.get('ubicacion_id') || localStorage.getItem('agro_loc');
    
    if (!ubicacionId) {
        alert('⚠️ Selecciona una planta primero antes de cerrar el día.');
        return;
    }
    
    if (!confirm('¿Confirmas el cierre del día? Se consolidarán todas las evaluaciones registradas y no podrás modificarlas.')) 
        return;
        
    fetch("{{ route('analisis.cerrar_dia') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            ubicacion_id: ubicacionId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('highlight_alert');
            window.location.href = cleanUrl.toString();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => alert('Error al cerrar el día: ' + err.message));
}

document.querySelectorAll('.eval-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('active');
    });
});

// ===== CÁLCULO AUTOMÁTICO DE PRECISIÓN =====
const ceSupInput = document.getElementById('ce_superficial');
const ceProfInput = document.getElementById('ce_profunda');
const precisionInput = document.getElementById('porcentaje_pf');
const calcPreview = document.getElementById('calcPreview');
const previewILx = document.getElementById('previewILx');
const previewCategoria = document.getElementById('previewCategoria');
const previewPrecision = document.getElementById('previewPrecision');

const ILX_IDEAL = 0.8; // Valor ideal según FAO (20% de fracción de lixiviación)

function calcularPrecision() {
    const ceSup = parseFloat(ceSupInput.value) || 0;
    const ceProf = parseFloat(ceProfInput.value) || 0;
    
    if (ceSup > 0 && ceProf > 0) {
        // Calcular ILx
        const ilx = ceProf / ceSup;
        
        // Calcular Precisión
        const precision = (1 - Math.abs(ilx - ILX_IDEAL) / ILX_IDEAL) * 100;
        const precisionMax = Math.max(0, Math.min(100, precision));
        
        // Determinar categoría
        let categoria = '';
        let categoriaColor = '';
        if (ilx < 0.4) {
            categoria = 'Baja';
            categoriaColor = 'bg-blue-100 text-blue-700';
        } else if (ilx >= 0.6 && ilx <= 1.0) {
            categoria = 'Media';
            categoriaColor = 'bg-amber-100 text-amber-700';
        } else {
            categoria = 'Alta';
            categoriaColor = 'bg-red-100 text-red-700';
        }
        
        // Actualizar campo de precisión
        precisionInput.value = precisionMax.toFixed(1);
        
        // Mostrar panel de cálculo
        calcPreview.classList.remove('hidden');
        previewILx.textContent = ilx.toFixed(3);
        previewCategoria.textContent = categoria;
        previewCategoria.className = `text-sm font-black px-2 py-1 rounded-lg ${categoriaColor}`;
        previewPrecision.textContent = precisionMax.toFixed(1) + '%';
        
        console.log(`ILx: ${ilx.toFixed(3)}, Precisión: ${precisionMax.toFixed(1)}%, Categoría: ${categoria}`);
    } else {
        // Limpiar si no hay valores
        precisionInput.value = '';
        calcPreview.classList.add('hidden');
    }
}

// Event listeners
if (ceSupInput && ceProfInput) {
    ceSupInput.addEventListener('input', calcularPrecision);
    ceProfInput.addEventListener('input', calcularPrecision);
}

// Reset al cerrar el modal
document.getElementById('closeManualBtn')?.addEventListener('click', () => {
    ceSupInput.value = '';
    ceProfInput.value = '';
    precisionInput.value = '';
    calcPreview.classList.add('hidden');
});

document.getElementById('cancelManual')?.addEventListener('click', () => {
    ceSupInput.value = '';
    ceProfInput.value = '';
    precisionInput.value = '';
    calcPreview.classList.add('hidden');
});

// ===== CARGA DINÁMICA DE UBICACIONES DISPONIBLES =====
const fechaInput = document.getElementById('fecha_registro');
const ubicacionSelector = document.getElementById('ubicacion_selector');
const loadingUbicaciones = document.getElementById('loadingUbicaciones');
const ubicacionesDisponibles = document.getElementById('ubicacionesDisponibles');

// Función para cargar ubicaciones disponibles
async function cargarUbicacionesDisponibles() {
    const fecha = fechaInput.value;
    
    if (!fecha) {
        ubicacionSelector.innerHTML = '<option value="">🔄 Selecciona primero una fecha...</option>';
        return;
    }
    
    // Mostrar loading
    loadingUbicaciones.classList.remove('hidden');
    ubicacionSelector.innerHTML = '<option value="">⏳ Cargando...</option>';
    ubicacionSelector.disabled = true;
    
    try {
        const response = await fetch(`{{ url('analisis/ubicaciones-disponibles') }}?fecha=${fecha}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        // Actualizar el select
        if (data.disponibles.length > 0) {
            ubicacionSelector.innerHTML = '<option value="">Seleccione ubicación GC</option>';
            
            data.disponibles.forEach(ubicacion => {
                const option = document.createElement('option');
                option.value = ubicacion.id;
                option.textContent = `🌳 Planta de palto #${ubicacion.numero} — ${ubicacion.descripcion}`;
                ubicacionSelector.appendChild(option);
            });
            
            // Mostrar contador
            ubicacionesDisponibles.textContent = `✅ ${data.total_disponibles} disponible(s)`;
            ubicacionesDisponibles.className = 'text-emerald-600 ml-2 font-bold';
            
        } else {
            ubicacionSelector.innerHTML = '<option value="">⚠️ Todas las ubicaciones ya tienen registros para esta fecha</option>';
            ubicacionesDisponibles.textContent = '❌ Sin disponibles';
            ubicacionesDisponibles.className = 'text-red-600 ml-2 font-bold';
        }
        
        ubicacionSelector.disabled = false;
        
    } catch (error) {
        console.error('Error al cargar ubicaciones:', error);
        ubicacionSelector.innerHTML = '<option value="">❌ Error al cargar ubicaciones</option>';
        ubicacionesDisponibles.textContent = '⚠️ Error';
        ubicacionesDisponibles.className = 'text-red-600 ml-2 font-bold';
    } finally {
        loadingUbicaciones.classList.add('hidden');
    }
}

// Event listener para el cambio de fecha
if (fechaInput) {
    fechaInput.addEventListener('change', cargarUbicacionesDisponibles);
    
    // Cargar ubicaciones al abrir el modal
    document.getElementById('openManualBtn')?.addEventListener('click', () => {
        setTimeout(cargarUbicacionesDisponibles, 100);
    });
}

// Reset al cerrar el modal
document.getElementById('closeManualBtn')?.addEventListener('click', () => {
    fechaInput.value = '{{ date('Y-m-d') }}';
    ubicacionSelector.innerHTML = '<option value="">🔄 Selecciona primero una fecha...</option>';
    ubicacionesDisponibles.textContent = '';
});

document.getElementById('cancelManual')?.addEventListener('click', () => {
    fechaInput.value = '{{ date('Y-m-d') }}';
    ubicacionSelector.innerHTML = '<option value="">🔄 Selecciona primero una fecha...</option>';
    ubicacionesDisponibles.textContent = '';
});

</script>
@endsection