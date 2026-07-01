@extends('layouts.app')
@section('title', 'Alertas — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.3);
        --accent-green: #10b981;
        --accent-red: #ef4444;
        --accent-orange: #f59e0b;
        --accent-blue: #3b82f6;
        --accent-indigo: #6366f1;
    }

    .page-header { 
        margin-bottom: 2rem; 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 1rem;
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
        transition: all 0.3s ease;
    }
    .kpi-card:hover { transform: translateY(-3px); }
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
    .filter-section select {
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
    .filter-btn:hover { background: rgba(16, 163, 74, 0.08); color: #16a34a; }
    .filter-btn.active {
        background: #16a34a;
        color: white;
        box-shadow: 0 6px 18px rgba(22, 163, 74, 0.25);
    }

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
    .badge-alto   { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .badge-medio  { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .badge-bajo   { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .badge-status { background: #f3f4f6; color: #4b5563; }
    .badge-open   { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
    .badge-eval   { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }

    .telegram-banner {
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
        background: linear-gradient(90deg, #0088cc, #229ed9);
        color: #fff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .telegram-banner i { font-size: 1.5rem; margin-right: 1rem; }
    .btn-test-tg {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.4);
        color: #fff;
        padding: 0.4rem 1rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-test-tg:hover { background: rgba(255,255,255,0.3); color: #fff; }

    .empty-state { padding: 4rem; text-align: center; }
    .empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block; }
    .empty-state p { color: #9ca3af; font-size: 1rem; }

    @keyframes pulse-highlight {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
        50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    }
    .alert-highlighted {
        background: linear-gradient(90deg, #fef3c7, #fef9c3) !important;
        border-left: 4px solid #f59e0b !important;
        animation: pulse-highlight 2s infinite;
    }
    .alert-highlighted:hover {
        background: linear-gradient(90deg, #fde68a, #fef3c3) !important;
    }
    .telegram-badge {
        background: #f59e0b;
        color: white;
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 6px;
        font-weight: 800;
        letter-spacing: 0.03em;
        vertical-align: middle;
    }
    .tg-info-banner {
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
        background: linear-gradient(90deg, #eff6ff, #dbeafe);
        border-left: 5px solid #3b82f6;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #1e40af;
        font-size: 0.9rem;
    }
    .tg-info-banner i { font-size: 1.3rem; color: #3b82f6; }
    .tg-info-banner a { color: #1e40af; font-weight: 700; text-decoration: underline; }
    .success-banner {
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
        background: linear-gradient(90deg, #dcfce7, #bbf7d0);
        border-left: 5px solid #16a34a;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #166534;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .success-banner i { font-size: 1.3rem; color: #16a34a; }

    .pds-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        font-family: monospace;
    }
    .pds-good { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .pds-warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .pds-bad  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000;
    }
    .modal-content {
        background: #fff; padding: 2rem; border-radius: 20px; width: 90%; max-width: 500px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 800; color: #1a472a; }
    .config-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f3f4f6; }
    .config-item:last-child { border-bottom: none; }
    .config-info { flex: 1; }
    .config-info div:first-child { font-weight: 700; font-size: 0.9rem; color: #374151; }
    .config-info div:last-child { font-size: 0.75rem; color: #9ca3af; }
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent-green); }
    input:checked + .slider:before { transform: translateX(20px); }

    .btn-eval {
        padding: 6px 12px;
        background: var(--accent-indigo);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .btn-eval:hover { background: #4338ca; transform: scale(1.05); }

    /* ── Modal Evaluación ── */
    .eval-option {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.2rem 1.4rem;
        border-radius: 14px;
        border: 2px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s;
        background: #f9fafb;
    }
    .eval-option:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .eval-option.vp { border-color: #86efac; }
    .eval-option.vp:hover, .eval-option.vp.selected { background: #dcfce7; border-color: #16a34a; }
    .eval-option.fp { border-color: #fca5a5; }
    .eval-option.fp:hover, .eval-option.fp.selected { background: #fee2e2; border-color: #dc2626; }
    .eval-option .eval-icon { font-size: 2rem; }
    .eval-option .eval-label { font-weight: 800; font-size: 1rem; }
    .eval-option .eval-desc  { font-size: 0.78rem; color: #6b7280; margin-top: 2px; }
    .eval-option.selected .eval-desc { color: inherit; }
</style>

{{-- ═══════════════════════════════════════════════════════════════════════════
     🎯 BANNER INFORMATIVO — Cuando llegas desde Telegram (?alert_id=X)
     ═══════════════════════════════════════════════════════════════════════════ --}}
@if(isset($alertId) && $alertId)
<div class="tg-info-banner">
    <i class="fab fa-telegram"></i>
    <div>
        <strong>Llegaste desde Telegram</strong> — Mostrando alerta #{{ $alertId }} resaltada abajo.
        <a href="{{ route('alertas') }}" class="ms-2">Ver todas las alertas</a>
    </div>
</div>
@endif

@if(session('success'))
<div class="success-banner">
    <i class="fas fa-check-circle"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="page-header">
        <div>
            <h1>🔔 Centro de Alertas IoT</h1>
            <p>Monitoreo proactivo de lixiviación — Grupo Experimental</p>
        </div>
        <div style="display:flex; gap: 0.75rem;">
            <a href="{{ route('analisis') }}" class="btn btn-light shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:6px; padding:0.5rem 1rem; background:white; border:1px solid #e5e7eb;">
                <i class="fas fa-graduation-cap"></i> Ir a Análisis Académico
            </a>
            <button onclick="openConfig()" class="btn btn-light shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem;">
                <i class="fas fa-cog"></i> Configurar
            </button>
            <button onclick="loadAlerts()" class="btn btn-light shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem;">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
    </div>

    @if(!env('TELEGRAM_BOT_TOKEN'))
    <div class="telegram-banner">
        <div style="display:flex; align-items:center;">
            <i class="fab fa-telegram"></i>
            <div>
                <div style="font-weight:700;">Alertas al Celular</div>
                <div style="font-size:0.8rem; opacity:0.9;">Configura Telegram para recibir alertas críticas en tiempo real.</div>
            </div>
        </div>
        <a href="https://t.me/BotFather" target="_blank" class="btn-test-tg">Configurar</a>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 📊 KPIs + PDS% (Indicador de Precisión del Sistema)           --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="kpi-grid">
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-blue);">
            <div class="icon-box"><i class="fas fa-bell"></i></div>
            <div class="label">Total Alertas</div>
            <div class="value" id="kpi-total" style="color:var(--accent-blue);">--</div>
            <div class="trend" style="color:var(--accent-blue);">Registradas</div>
        </div>
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-red);">
            <div class="icon-box"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="label">Riesgo Crítico</div>
            <div class="value" id="kpi-alto" style="color:var(--accent-red);">--</div>
            <div class="trend" style="color:var(--accent-red);">Requieren atención</div>
        </div>
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-orange);">
            <div class="icon-box"><i class="fas fa-clock"></i></div>
            <div class="label">Pendientes</div>
            <div class="value" id="kpi-open" style="color:var(--accent-orange);">--</div>
            <div class="trend" style="color:var(--accent-orange);">Sin resolver</div>
        </div>
        <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-indigo);">
            <div class="icon-box"><i class="fas fa-bullseye"></i></div>
            <div class="label">Precisión del Sistema (PDS%)</div>
            <div class="value" id="kpi-pds" style="color:var(--accent-indigo); font-family:monospace;">--</div>
            <div class="trend" id="pds-trend" style="color:var(--accent-indigo);">
                <span id="pds-badge" class="pds-badge pds-good">Calculando...</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 🎯 TABLA DE ALERTAS                                           --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="glass-card mb-12">
        <div class="filter-section rounded-t-2xl">
            <div class="filter-group">
                <label>🌳 Planta de Palto (GE)</label>
                <select id="f-location" onchange="onLocationChange(this.value)">
                    <option value="">Todas las plantas</option>
                    @foreach($plantasGE as $planta)
                        @php $loc = $planta->ubicaciones->first(); @endphp
                        @if($loc)
                            <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                                🌳 {{ $planta->nombre }} (Planta {{ $planta->numero_planta }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Nivel de Riesgo</label>
                <select id="f-risk">
                    <option value="">Cualquier nivel</option>
                    <option value="ALTO">🔴 Crítico / Alto</option>
                    <option value="MEDIO">🟠 Moderado</option>
                    <option value="BAJO">🟢 Informativo</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Estado</label>
                <select id="f-status">
                    <option value="">Todos</option>
                    <option value="open">Abiertas</option>
                    <option value="resolved">Resueltas</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Período</label>
                <div class="inline-flex bg-white/80 p-1 rounded-2xl shadow-sm ring-1 ring-slate-200">
                    @foreach(['24h', '7d', '14d', '30d', 'all'] as $f)
                        <a href="{{ route('alertas', ['filter' => $f, 'location_id' => $location_id]) }}"
                           class="filter-btn {{ $filter === $f ? 'active' : '' }}">
                            {{ strtoupper($f) }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                <span id="alert-count" class="px-3 py-1 bg-slate-100 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest"></span>
            </div>
        </div>

        <div class="table-container">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-4">Fecha de detección</th>
                        <th class="px-6 py-4">Planta de palto</th>
                        <th class="px-6 py-4">Nivel / Tipo</th>
                        <th class="px-6 py-4">TPD</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4">Evaluación</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody id="alerts-body">
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Sincronizando eventos en tiempo real...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Configuración -->
<div id="modalConfig" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚙️ Configuración de Alertas</h2>
            <button onclick="closeConfig()" class="btn btn-link text-muted" style="padding:0; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem;">Selecciona qué niveles de lixiviado deben activar el sistema de alertas para la ubicación seleccionada.</p>
        
        <div id="config-body">
            <div class="empty-state" style="padding:1rem;">
                <i class="fas fa-map-marker-alt" style="font-size:1.5rem;"></i>
                <p style="font-size:0.8rem;">Selecciona una ubicación en el dashboard para configurar.</p>
            </div>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 10px;">
            <button onclick="saveConfig()" class="btn btn-success w-100" style="border-radius:12px; font-weight:700; flex:1; padding:0.75rem; background:var(--accent-green); color:white; border:none; cursor:pointer;">Guardar Cambios</button>
            <button onclick="closeConfig()" class="btn btn-light w-100" style="border-radius:12px; font-weight:700; flex:1; padding:0.75rem; cursor:pointer; border:1px solid #e5e7eb; background:#fff; color:#374151;">Cancelar</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     🎓 Modal Evaluación VP / FP
     ══════════════════════════════════════════════════════════ -->
<div id="modalEval" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header" style="margin-bottom:0.5rem;">
            <h2 style="font-size:1.15rem;">🎓 Evaluar Alerta</h2>
            <button onclick="closeEval()" style="padding:0; border:none; background:transparent; cursor:pointer; color:#9ca3af; font-size:1.2rem;"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:0.82rem; color:#6b7280; margin-bottom:1.4rem;">Indica si esta alerta correspondió a un evento <strong>real de lixiviación</strong> o fue una <strong>falsa alarma</strong>.</p>

        <div id="eval-alert-info" style="background:#f3f4f6; border-radius:10px; padding:0.8rem 1rem; margin-bottom:1.4rem; font-size:0.82rem; color:#374151;">
            <!-- Se llena dinámicamente -->
        </div>

        <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
            <div class="eval-option vp" id="eval-vp" onclick="selectEval('VP')">
                <div class="eval-icon">✅</div>
                <div>
                    <div class="eval-label" style="color:#15803d;">VP — Verdadero Positivo</div>
                    <div class="eval-desc">La alerta fue correcta. Hubo lixiviación real en campo.</div>
                </div>
            </div>
            <div class="eval-option fp" id="eval-fp" onclick="selectEval('FP')">
                <div class="eval-icon">❌</div>
                <div>
                    <div class="eval-label" style="color:#dc2626;">FP — Falso Positivo</div>
                    <div class="eval-desc">La alerta fue incorrecta. No hubo lixiviación real.</div>
                </div>
            </div>
        </div>

        <input type="hidden" id="eval-alert-id" value="">
        <input type="hidden" id="eval-selected" value="">

        <div style="display:flex; gap:10px;">
            <button id="btn-submit-eval"
                onclick="submitEval()"
                style="flex:1; padding:0.75rem; border-radius:12px; font-weight:700; font-size:0.9rem;
                       background:var(--accent-indigo); color:white; border:none; cursor:pointer;
                       transition:all 0.2s; opacity:0.5; pointer-events:none;"
                disabled>
                <i class="fas fa-check"></i> Confirmar Evaluación
            </button>
            <button onclick="closeEval()"
                style="padding:0.75rem 1.2rem; border-radius:12px; font-weight:700; font-size:0.9rem;
                       background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; cursor:pointer;">
                Cancelar
            </button>
        </div>

        <div id="eval-error" style="display:none; margin-top:0.75rem; padding:0.6rem 1rem;
             background:#fee2e2; border-radius:8px; color:#dc2626; font-size:0.82rem; font-weight:600;">
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@php
    $pdsDataDefault = $pdsData ?? ['vp' => 0, 'fp' => 0, 'total' => 0, 'pds_percentage' => 0];
@endphp
let PDS_DATA = @json($pdsDataDefault);

// ═══════════════════════════════════════════════════════════════
// 🔄 SINCRONIZACIÓN CON LOCALSTORAGE
// ═══════════════════════════════════════════════════════════════
function onLocationChange(locId) {
    const url = new URL(window.location.href);
    
    if (locId) {
        localStorage.setItem('agro_loc', locId);
        url.searchParams.set('location_id', locId);
    } else {
        localStorage.removeItem('agro_loc');
        url.searchParams.delete('location_id');
    }
    url.searchParams.set('filter', url.searchParams.get('filter') || 'all');
    window.location.href = url.toString();
}

// Al cargar: sincronizar con localStorage
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('f-location');
    const currentUrl = new URL(window.location.href);
    const currentLocId = currentUrl.searchParams.get('location_id');
    const savedLoc = localStorage.getItem('agro_loc');
    
    // Si no hay location_id en URL pero hay en localStorage, recargar
    if (!currentLocId && savedLoc && selector.querySelector(`option[value="${savedLoc}"]`)) {
        currentUrl.searchParams.set('location_id', savedLoc);
        window.location.href = currentUrl.toString();
        return;
    }
    
    // Sincronizar selector con URL actual
    if (selector && currentLocId) {
        selector.value = currentLocId;
    }
    
    // Escuchar cambios desde otras pestañas
    window.addEventListener('storage', function(e) {
        if (e.key === 'agro_loc' && e.newValue !== currentLocId) {
            const url = new URL(window.location.href);
            if (e.newValue) {
                url.searchParams.set('location_id', e.newValue);
            } else {
                url.searchParams.delete('location_id');
            }
            window.location.href = url.toString();
        }
    });
    
    loadAlerts();
    setInterval(loadAlerts, 10000);
});

// ═══════════════════════════════════════════════════════════════
// 📊 CARGAR Y RENDERIZAR ALERTAS
// ═══════════════════════════════════════════════════════════════
async function loadAlerts() {
    const locId  = document.getElementById('f-location').value;
    const risk   = document.getElementById('f-risk').value;
    const status = document.getElementById('f-status').value;
    
    const urlParams = new URLSearchParams(window.location.search);
    const filter = urlParams.get('filter') || 'all';

    let url = '/api/alerts/list?limit=all'; 
    if (locId)  url += `&location_id=${locId}`;
    if (risk)   url += `&risk_level=${risk}`;
    if (status) url += `&status=${status}`;
    if (filter && filter !== 'all') url += `&filter=${filter}`;

    try {
        const res  = await fetch(url);
        if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
        
        const json = await res.json();
        const data = json.data || [];
        renderAlerts(data);
        updatePDS();
    } catch(e) {
        console.error('Error al cargar alertas:', e);
        document.getElementById('alerts-body').innerHTML = 
            `<tr><td colspan="7" class="empty-state">
                <i class="fas fa-exclamation-circle" style="color:var(--accent-red);"></i>
                <p>Error al cargar alertas: ${e.message}</p>
            </td></tr>`;
    }
}

function updatePDS() {
    const pds = PDS_DATA.pds_percentage || 0;
    const badge = document.getElementById('pds-badge');
    document.getElementById('kpi-pds').textContent = pds.toFixed(1) + '%';
    
    if (pds >= 80) {
        badge.className = 'pds-badge pds-good';
        badge.innerHTML = `✔ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | Total:${PDS_DATA.total || (PDS_DATA.vp + PDS_DATA.fp)} | ${pds.toFixed(1)}%`;
    } else if (pds >= 60) {
        badge.className = 'pds-badge pds-warn';
        badge.innerHTML = `⚠ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | Total:${PDS_DATA.total || (PDS_DATA.vp + PDS_DATA.fp)} | ${pds.toFixed(1)}%`;
    } else {
        badge.className = 'pds-badge pds-bad';
        badge.innerHTML = `✘ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | Total:${PDS_DATA.total || (PDS_DATA.vp + PDS_DATA.fp)} | ${pds.toFixed(1)}%`;
    }
}

function renderAlerts(alerts) {
    const body = document.getElementById('alerts-body');
    
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('alert_id');
    
    document.getElementById('kpi-total').textContent  = alerts.length;
    document.getElementById('kpi-alto').textContent   = alerts.filter(a => ['ALTO', 'CRÍTICO'].includes((a.severity||a.level||'').toUpperCase())).length;
    document.getElementById('kpi-open').textContent   = alerts.filter(a => !a.is_resolved).length;
    document.getElementById('alert-count').textContent = `${alerts.length} eventos`;

    if (alerts.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i><p>No se encontraron alertas con los filtros seleccionados.</p></td></tr>';
        return;
    }

    body.innerHTML = alerts.map((a, index) => {
        const risk = (a.severity || a.level || 'BAJO').toUpperCase();
        let rClass = 'badge-bajo';
        let rIcon  = 'fa-info-circle';
        
        if (['ALTO', 'CRÍTICO', 'ALTA'].includes(risk)) { rClass = 'badge-alto'; rIcon = 'fa-exclamation-triangle'; }
        else if (risk === 'MEDIO') { rClass = 'badge-medio'; rIcon = 'fa-exclamation-circle'; }

        const isOpen = !a.is_resolved;
        const sClass = isOpen ? 'badge-open' : 'badge-status';
        const sText  = isOpen ? 'Abierta' : 'Resuelta';
        
        const isHighlighted = highlightId && a.id == highlightId;
        const rowClass = isHighlighted ? 'alert-highlighted' : '';
        const highlightBadge = isHighlighted 
            ? '<span class="telegram-badge">📱 DESDE TELEGRAM</span>' 
            : '';
        
        const createdAt = new Date(a.created_at);
        const dateStr = createdAt.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const timeStr = createdAt.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false });

        const nivelTipo = (a.analysis && a.analysis.ilx_estado) ? a.analysis.ilx_estado : (a.type || 'N/A');

        // ── TPD: Tiempo de Detección / Tiempo de Respuesta ──
        // • Alerta RESUELTA + evaluada VP → tar real del Job
        // • Alerta RESUELTA sin tar → fecha_resolucion - tiempo_alerta
        // • Alerta ABIERTA → tiempo transcurrido desde tiempo_alerta (en vivo)
        let tpdSeconds  = null;
        let tpdIsLive   = false;
        let tpdLabel    = 'TAR';

        if (a.is_resolved) {
            // Usar tar solo si fue calculado por el Job (VP) y es razonable (> 0, no 300 hardcodeado)
            if (a.tar && a.tar > 0 && a.tar !== 300) {
                tpdSeconds = parseInt(a.tar);
                tpdLabel   = 'TAR';
            } else if (a.fecha_resolucion && a.tiempo_alerta) {
                try {
                    const ta   = new Date(a.tiempo_alerta);
                    const tr   = new Date(a.fecha_resolucion);
                    const diff = tr.getTime() - ta.getTime();
                    if (diff > 0) { tpdSeconds = Math.round(diff / 1000); tpdLabel = 'Duración'; }
                } catch(e) { tpdSeconds = null; }
            }
        } else {
            // Alerta ABIERTA → tiempo desde tiempo_alerta hasta ahora
            if (a.tiempo_alerta) {
                try {
                    const ta   = new Date(a.tiempo_alerta);
                    const now  = new Date();
                    const diff = now.getTime() - ta.getTime();
                    if (diff > 0) { tpdSeconds = Math.round(diff / 1000); tpdIsLive = true; tpdLabel = 'En curso'; }
                } catch(e) { tpdSeconds = null; }
            }
        }

        const planta = a.ubicacion && a.ubicacion.planta 
            ? `${a.ubicacion.planta.nombre} (Planta ${a.ubicacion.planta.numero_planta || a.ubicacion.planta.id})`
            : (a.subparcela || 'N/D');
        
        let evalBadge = '';
        if (a.evaluation) {
            if (a.evaluation.etiqueta === 'VP') {
                evalBadge = `<span class="badge" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;">✔ VP</span>`;
            } else {
                evalBadge = `<span class="badge" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">❌ FP</span>`;
            }
        } else {
            evalBadge = `<button onclick="goToEval(${a.id}, ${a.ubicacion_id})" class="btn-eval"><i class="fas fa-graduation-cap"></i> Evaluar</button>`;
        }

        const resolveBtn = isOpen && a.evaluation
            ? `<button onclick="resolveAlertNow(${a.id})" class="btn btn-sm btn-outline-success" style="border-radius:8px; font-size:0.7rem; font-weight:700;">
                <i class="fas fa-check"></i> Resolver
               </button>`
            : '';

        return `
            <tr id="alert-row-${a.id}" class="${rowClass}">
                <td>
                    <div style="font-weight:700; color:#374151;">${dateStr}${highlightBadge}</div>
                    <div style="font-size:0.75rem; color:#9ca3af;">${timeStr}</div>
                </td>
                <td style="font-weight:700; color:#1a472a;">
                    🌳 ${planta}
                </td>
                <td>
                    <div style="font-size:0.7rem; color:#9ca3af; margin-bottom:4px;">${nivelTipo}</div>
                    <span class="badge ${rClass}"><i class="fas ${rIcon}"></i> ${risk}</span>
                </td>
                <td>
                    <div style="font-size:0.65rem; color:#9ca3af; margin-bottom:2px; text-transform:uppercase; letter-spacing:0.04em;">
                        ${tpdIsLive ? '<span style="color:#f59e0b;">⏱</span> ' : ''}${tpdLabel}
                    </div>
                    <div style="font-weight:800; color:${tpdIsLive ? '#f59e0b' : '#16a34a'}; font-family:monospace; font-size:1rem;">
                        ${tpdSeconds !== null ? tpdSeconds + 's' : '—'}
                    </div>
                    ${tpdSeconds !== null ? `<div style="font-size:0.7rem; color:#9ca3af;">(~${(tpdSeconds/60).toFixed(1)} min)</div>` : ''}
                </td>
                <td>
                    <span class="badge ${sClass}">${sText}</span>
                </td>
                <td>
                    ${evalBadge}
                </td>
                <td>
                    ${resolveBtn}
                </td>
            </tr>
        `;
    }).join('');

    if (highlightId) {
        setTimeout(() => {
            const row = document.getElementById(`alert-row-${highlightId}`);
            if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
    }
}

// ══════════════════════════════════════════════
// 🎓 EVALUAR — redirige a analisis.blade.php donde está el modal VP/FP
// ══════════════════════════════════════════════
function goToEval(alertId, alertLocId) {
    if (alertLocId) {
        localStorage.setItem('agro_loc', alertLocId);
    }
    let url = `/analisis?highlight_alert=${alertId}`;
    if (alertLocId) {
        url += `&ubicacion_id=${alertLocId}`;
    }
    window.location.href = url;
}

// Funciones del modal de evaluación (modalEval en el HTML)
let _evalSelected = null;

function closeEval() {
    document.getElementById('modalEval').style.display = 'none';
}

function selectEval(value) {
    _evalSelected = value;
    document.getElementById('eval-vp').classList.toggle('selected', value === 'VP');
    document.getElementById('eval-fp').classList.toggle('selected', value === 'FP');
    const btnSubmit = document.getElementById('btn-submit-eval');
    btnSubmit.disabled = false;
    btnSubmit.style.opacity = '1';
    btnSubmit.style.pointerEvents = 'auto';
}

async function submitEval() {
    const alertId  = document.getElementById('eval-alert-id').value;
    if (!_evalSelected) { return; }
    const btn = document.getElementById('btn-submit-eval');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const res  = await fetch(`/analisis/evaluar-alerta/${alertId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ evaluation: _evalSelected }),
        });
        const json = await res.json().catch(() => ({}));
        if (res.ok && json.status === 'success') {
            closeEval();
            loadAlerts();
        } else {
            const errEl = document.getElementById('eval-error');
            errEl.textContent = json.message || 'Error al evaluar. Intenta de nuevo.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirmar Evaluación';
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar Evaluación';
    }
}


async function resolveAlertNow(id) {
    if(!confirm('¿Marcar esta alerta como resuelta?')) return;
    
    try {
        const res = await fetch(`/alertas/${id}/quick-resolve`, { method: 'GET' });
        if (res.ok || res.redirected) window.location.reload();
        else alert('Error al resolver la alerta');
    } catch (e) {
        console.error(e);
        alert('Error de conexión');
    }
}

// ═══════════════════════════════════════════════════════════════
// ⚙️ CONFIGURACIÓN DE ALERTAS
// ═══════════════════════════════════════════════════════════════
const locationsData = @json($ubicaciones);

function openConfig() {
    const locId = document.getElementById('f-location').value;
    const modal = document.getElementById('modalConfig');
    const body  = document.getElementById('config-body');
    
    if (!locId) {
        alert('Por favor, selecciona una ubicación específica para configurar sus alertas.');
        return;
    }

    const loc = locationsData.find(l => l.id == locId);
    const settings = loc.configuracion_alertas || { lixiviacion_alta: true, lixiviacion_media: true, lixiviacion_baja: true };

    body.innerHTML = `
        <div class="config-item">
            <div class="config-info">
                <div>🔴 Lixiviación Alta</div>
                <div>Ratio ILx &gt; 1.0 (Pérdida significativa de nutrientes)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-lix-alta" ${settings.lixiviacion_alta ? 'checked' : ''}>
                <span class="slider"></span>
            </label>
        </div>
        <div class="config-item">
            <div class="config-info">
                <div>🟠 Lixiviación Media</div>
                <div>Ratio ILx entre 0.6 y 1.0 (Lavado moderado)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-lix-media" ${settings.lixiviacion_media ? 'checked' : ''}>
                <span class="slider"></span>
            </label>
        </div>
        <div class="config-item">
            <div class="config-info">
                <div>🟢 Lixiviación Baja</div>
                <div>Ratio ILx &lt; 0.4 (Acumulación / baja actividad)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-lix-baja" ${settings.lixiviacion_baja ? 'checked' : ''}>
                <span class="slider"></span>
            </label>
        </div>
    `;

    modal.style.display = 'flex';
}

function closeConfig() {
    document.getElementById('modalConfig').style.display = 'none';
}

async function saveConfig() {
    const locId = document.getElementById('f-location').value;
    const settings = {
        lixiviacion_alta:  document.getElementById('cfg-lix-alta').checked,
        lixiviacion_media: document.getElementById('cfg-lix-media').checked,
        lixiviacion_baja:  document.getElementById('cfg-lix-baja').checked,
    };

    try {
        const res = await fetch(`/api/locations/${locId}/settings`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(settings)
        });

        const json = await res.json();
        if (json.status === 'success') {
            const loc = locationsData.find(l => l.id == locId);
            if (loc) loc.configuracion_alertas = settings;
            alert('Configuración guardada correctamente.');
            closeConfig();
        }
    } catch (e) {
        alert('Error al guardar la configuración');
    }
}

['f-risk','f-status'].forEach(id =>
    document.getElementById(id).addEventListener('change', loadAlerts)
);
</script>
@endpush