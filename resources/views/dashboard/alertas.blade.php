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

    /* ═══════════════════════════════════════════════════════════════
       🎯 ESTILOS PARA ALERTA RESALTADA (desde Telegram)
       ═══════════════════════════════════════════════════════════════ */
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

    /* PDS Badge */
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

    /* Modal */
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
                <select id="f-location">
                    <option value="">Todas las plantas</option>
                    @foreach($lotesGE as $lote)
                        @php $loc = $lote->locations->first(); @endphp
                        @if($loc)
                            <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                                🌳 {{ $lote->name }} (Planta {{ $lote->plant_number }})
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
@endsection

@push('scripts')
<script>
@php
    $pdsDataDefault = $pdsData ?? ['vp' => 0, 'fp' => 0, 'fn' => 0, 'pds_percentage' => 0];
@endphp
const PDS_DATA = @json($pdsDataDefault);

async function loadAlerts() {
    const locId  = document.getElementById('f-location').value;
    const risk   = document.getElementById('f-risk').value;
    const status = document.getElementById('f-status').value;
    
    // Obtener filtro de período de la URL actual
    const urlParams = new URLSearchParams(window.location.search);
    const filter = urlParams.get('filter') || 'all';

    let url = '/api/alerts/list?limit=all'; 
    if (locId)  url += `&location_id=${locId}`;
    if (risk)   url += `&risk_level=${risk}`;
    if (status) url += `&status=${status}`;
    if (filter && filter !== 'all') url += `&filter=${filter}`;

    try {
        const res  = await fetch(url);
        
        if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
        }
        
        const json = await res.json();
        const data = json.data || [];
        renderAlerts(data);
        updatePDS();
        
        // Feedback visual de actualización exitosa
        showNotification(' Alertas actualizadas', 'success');
    } catch(e) {
        console.error('Error al cargar alertas:', e);
        document.getElementById('alerts-body').innerHTML = 
            `<tr><td colspan="7" class="empty-state">
                <i class="fas fa-exclamation-circle" style="color:var(--accent-red);"></i>
                <p>Error al cargar alertas: ${e.message}</p>
                <p style="font-size:0.8rem; margin-top:0.5rem;">Verifica que las rutas API estén configuradas.</p>
            </td></tr>`;
        showNotification(' Error al actualizar', 'error');
    }
}

async function resolveAlert(id) {
    if(!confirm('¿Marcar esta alerta como resuelta? Se notificará por Telegram.')) return;
    
    try {
        const res = await fetch(`/api/alerts/${id}/resolve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
        }
        
        const json = await res.json();
        
        if (json.status === 'success') {
            showNotification('✅ Alerta resuelta correctamente', 'success');
            loadAlerts(); // Recargar lista
        } else {
            throw new Error(json.message || 'Error desconocido');
        }
    } catch (e) {
        console.error('Error al resolver alerta:', e);
        showNotification('❌ Error: ' + e.message, 'error');
    }
}

async function saveConfig() {
    const locId = document.getElementById('f-location').value;
    
    if (!locId) {
        showNotification('⚠️ Selecciona una ubicación primero', 'warning');
        return;
    }
    
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

        if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
        }

        const json = await res.json();
        
        if (json.status === 'success') {
            // Actualizar datos locales
            const loc = locationsData.find(l => l.id == locId);
            if (loc) loc.alert_settings = settings;
            
            showNotification('✅ Configuración guardada correctamente', 'success');
            closeConfig();
        } else {
            throw new Error(json.message || 'Error desconocido');
        }
    } catch (e) {
        console.error('Error al guardar configuración:', e);
        showNotification('❌ Error: ' + e.message, 'error');
    }
}

// ═══════════════════════════════════════════════════════════════
// 🔔 SISTEMA DE NOTIFICACIONES VISUALES
// ═══════════════════════════════════════════════════════════════
function showNotification(message, type = 'success') {
    // Remover notificaciones anteriores
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const colors = {
        success: { bg: '#dcfce7', border: '#16a34a', text: '#166534', icon: '✅' },
        error:   { bg: '#fee2e2', border: '#dc2626', text: '#991b1b', icon: '❌' },
        warning: { bg: '#fef3c7', border: '#d97706', text: '#92400e', icon: '⚠️' },
    };
    
    const color = colors[type] || colors.success;
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${color.bg};
        border-left: 4px solid ${color.border};
        color: ${color.text};
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        z-index: 9999;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: slideIn 0.3s ease-out;
    `;
    toast.innerHTML = `<span>${color.icon}</span><span>${message}</span>`;
    
    document.body.appendChild(toast);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Agregar animaciones CSS
if (!document.getElementById('toast-animations')) {
    const style = document.createElement('style');
    style.id = 'toast-animations';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

function updatePDS() {
    const pds = PDS_DATA.pds_percentage || 0;
    const badge = document.getElementById('pds-badge');
    document.getElementById('kpi-pds').textContent = pds.toFixed(1) + '%';
    
    if (pds >= 80) {
        badge.className = 'pds-badge pds-good';
        badge.innerHTML = `✔ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | FN:${PDS_DATA.fn}`;
    } else if (pds >= 60) {
        badge.className = 'pds-badge pds-warn';
        badge.innerHTML = `⚠ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | FN:${PDS_DATA.fn}`;
    } else {
        badge.className = 'pds-badge pds-bad';
        badge.innerHTML = `✘ VP:${PDS_DATA.vp} | FP:${PDS_DATA.fp} | FN:${PDS_DATA.fn}`;
    }
}

function renderAlerts(alerts) {
    const body = document.getElementById('alerts-body');
    
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('alert_id');
    
    // Update KPIs
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
        
        // ✅ USAR FECHA REAL DE LA BD (no hardcodeada)
        const createdAt = new Date(a.created_at);
        const dateStr = createdAt.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const timeStr = createdAt.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false });

        const nivelTipo = (a.analysis && a.analysis.ilx_estado) ? a.analysis.ilx_estado : (a.type || 'N/A');

        let tpdSeconds = null;
        if (a.tiempo_alerta && a.tiempo_riesgo) {
            try {
                const ta = new Date(a.tiempo_alerta);
                const tr = new Date(a.tiempo_riesgo);
                tpdSeconds = Math.abs(Math.round((tr.getTime() - ta.getTime()) / 1000));
            } catch(e) {
                tpdSeconds = null;
            }
        }

        // ✅ USAR NOMBRE REAL DEL LOTE
        const planta = a.location && a.location.lote 
            ? `${a.location.lote.name} (Planta ${a.location.lote.plant_number || a.location.lote.id})`
            : (a.subparcela || 'N/D');
        
        // ✅ Evaluar si ya fue evaluada (VP/FP/FN)
        const evalBadge = a.evaluation 
            ? `<span class="badge badge-eval">${a.evaluation.label}</span>`
            : `<button onclick="goToEval(${a.id})" class="btn-eval"><i class="fas fa-graduation-cap"></i> Evaluar</button>`;

        const resolveBtn = isOpen 
            ? `<button onclick="resolveAlert(${a.id})" class="btn btn-sm btn-outline-success" style="border-radius:8px; font-size:0.7rem; font-weight:700;">
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
                    <div style="font-weight:800; color:#16a34a; font-family:monospace;">
                        ${tpdSeconds !== null ? tpdSeconds + 's' : '--'}
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

function goToEval(alertId) {
    window.location.href = `/analisis?highlight_alert=${alertId}`;
}

async function resolveAlert(id) {
    if(!confirm('¿Marcar esta alerta como resuelta? Se notificará por Telegram.')) return;
    
    try {
        const res = await fetch(`/alertas/${id}/quick-resolve`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        });
        
        if (res.ok || res.redirected) {
            window.location.reload();
        } else {
            alert('Error al resolver la alerta');
        }
    } catch (e) {
        console.error(e);
        alert('Error de conexión');
    }
}

// Lógica de Configuración
const locationsData = @json($locations);

function openConfig() {
    const locId = document.getElementById('f-location').value;
    const modal = document.getElementById('modalConfig');
    const body  = document.getElementById('config-body');
    
    if (!locId) {
        alert('Por favor, selecciona una ubicación específica para configurar sus alertas.');
        return;
    }

    const loc = locationsData.find(l => l.id == locId);
    const settings = loc.alert_settings || { lixiviacion_alta: true, lixiviacion_media: true, lixiviacion_baja: true };

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
            if (loc) loc.alert_settings = settings;
            alert('Configuración guardada correctamente.');
            closeConfig();
        }
    } catch (e) {
        alert('Error al guardar la configuración');
    }
}

['f-location','f-risk','f-status'].forEach(id =>
    document.getElementById(id).addEventListener('change', loadAlerts)
);

loadAlerts();
setInterval(loadAlerts, 60000);
</script>
@endpush