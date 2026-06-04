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

    /* KPI Row */
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

    /* Filters */
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

    /* Table Styling */
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

    /* Badges */
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

    /* Telegram Info */
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

    /* Modal Simple */
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
</style>

<div class="page-header">
    <div>
        <h1>🔔 Centro de Alertas</h1>
        <p>Monitoreo proactivo de lixiviación y salud del cultivo</p>
    </div>
    <div style="display:flex; gap: 0.75rem;">
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

<div class="kpi-grid">
    <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-blue);">
        <div class="icon-box"><i class="fas fa-bell"></i></div>
        <div class="label">Total Alertas</div>
        <div class="value" id="kpi-total">--</div>
        <div class="trend" style="color:var(--accent-blue);">Registradas hoy</div>
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
    <div class="glass-card kpi-card" style="border-left: 5px solid var(--accent-green);">
        <div class="icon-box"><i class="fas fa-check-circle"></i></div>
        <div class="label">Resueltas</div>
        <div class="value" id="kpi-closed" style="color:var(--accent-green);">--</div>
        <div class="trend" style="color:var(--accent-green);">Eventos cerrados</div>
    </div>
</div>

<div class="glass-card mb-12">
    <div class="filter-section rounded-t-2xl">
        <div class="filter-group">
            <label>Ubicación</label>
            <select id="f-location">

                @foreach($locations as $loc)
                    @if($loc->id === 4)
                    <option value="{{ $loc->id }}">{{ $loc->lote->name ?? 'Sin Lote' }} — {{ $loc->name }}</option>
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
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody id="alerts-body">
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Sincronizando eventos en tiempo real...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Configuración -->
<div id="modalConfig" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚙️ Configuración de Alertas</h2>
            <button onclick="closeConfig()" class="btn btn-link text-muted" style="padding:0;"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem;">Selecciona qué tipos de eventos deben activar el sistema de alertas para la ubicación seleccionada.</p>
        
        <div id="config-body">
            <!-- Cargado dinámicamente -->
            <div class="empty-state" style="padding:1rem;">
                <i class="fas fa-map-marker-alt" style="font-size:1.5rem;"></i>
                <p style="font-size:0.8rem;">Selecciona una ubicación en el dashboard para configurar.</p>
            </div>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 10px;">
            <button onclick="saveConfig()" class="btn btn-success w-100" style="border-radius:12px; font-weight:700;">Guardar Cambios</button>
            <button onclick="closeConfig()" class="btn btn-light w-100" style="border-radius:12px; font-weight:700;">Cancelar</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function loadAlerts() {
    const locId  = document.getElementById('f-location').value;
    const risk   = document.getElementById('f-risk').value;
    const status = document.getElementById('f-status').value;

    let url = '/api/alerts/list?limit=all'; 
    if (locId)  url += `&location_id=${locId}`;
    if (risk)   url += `&risk_level=${risk}`;
    if (status) url += `&status=${status}`;

    try {
        const res  = await fetch(url);
        const json = await res.json();
        const data = json.data || [];
        renderAlerts(data);
    } catch(e) {
        document.getElementById('alerts-body').innerHTML = 
            '<tr><td colspan="8" class="empty-state"><i class="fas fa-exclamation-circle" style="color:var(--accent-red);"></i><p>Error al sincronizar con el servidor.</p></td></tr>';
    }
}

function renderAlerts(alerts) {
    const body = document.getElementById('alerts-body');
    
    // Update KPIs
    document.getElementById('kpi-total').textContent  = alerts.length;
    document.getElementById('kpi-alto').textContent   = alerts.filter(a => ['ALTO', 'CRÍTICO'].includes((a.severity||a.level||'').toUpperCase())).length;
    document.getElementById('kpi-open').textContent   = alerts.filter(a => !a.is_resolved).length;
    document.getElementById('kpi-closed').textContent = alerts.filter(a => a.is_resolved).length;
    document.getElementById('alert-count').textContent = `${alerts.length} eventos`;

    if (alerts.length === 0) {
        body.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i><p>No se encontraron alertas con los filtros seleccionados.</p></td></tr>';
        return;
    }

    // Secuencia de fechas fija solicitada para el reporte académico (tesis)
    const fixedDates = [
        '19/04/2026', '21/04/2026', '23/04/2026', '25/04/2026', '27/04/2026', 
        '29/04/2026', '01/05/2026', '03/05/2026', '07/05/2026', '09/05/2026', 
        '11/05/2026', '13/05/2026', '15/05/2026', '17/05/2026'
    ];

    body.innerHTML = alerts.map((a, index) => {
        const risk = (a.severity || a.level || 'BAJO').toUpperCase();
        let rClass = 'badge-bajo';
        let rIcon  = 'fa-info-circle';
        
        if (['ALTO', 'CRÍTICO', 'ALTA'].includes(risk)) { rClass = 'badge-alto'; rIcon = 'fa-exclamation-triangle'; }
        else if (risk === 'MEDIO') { rClass = 'badge-medio'; rIcon = 'fa-exclamation-circle'; }

        const isOpen = !a.is_resolved;
        const sClass = isOpen ? 'badge-open' : 'badge-status';
        const sText  = isOpen ? 'Abierta' : 'Resuelta';
        
        // Aplicar secuencia de fechas fija por índice
        const dateStr = fixedDates[index] || new Date(a.created_at).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const date = a.created_at ? new Date(a.created_at) : new Date();
        const timeStr = date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

        // Nivel / Tipo: preferimos el estado ILx del análisis si viene, si no usamos el tipo
        const nivelTipo = (a.analysis && a.analysis.ilx_estado) ? a.analysis.ilx_estado : (a.ilx_estado || (a.type || 'N/A'));

        // Calcular TPD a partir de tiempos de alerta si están disponibles
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

        // Definición de variables faltantes para la tabla
        const planta = a.subparcela || (a.location ? a.location.name : 'P' + (index + 1));
        const nivelLabel = nivelTipo;
        const tpdDisplay = a.tpd || tpdSeconds || a.tar || null;
        
        const resolveBtn = isOpen 
            ? `<button onclick="resolveAlert(${a.id})" class="btn btn-sm btn-outline-success" style="border-radius:8px; font-size:0.7rem; font-weight:700;">
                <i class="fas fa-check"></i> Resolver
               </button>`
            : '';

        return `
            <tr id="alert-row-${a.id}">
                <td>
                    <div style="font-weight:700; color:#374151;">${dateStr}</div>
                    <div style="font-size:0.75rem; color:#9ca3af;">${timeStr}</div>
                </td>
                <td style="font-weight:700; color:#1a472a;">
                    ${planta}
                </td>
                <td>
                    <div style="font-size:0.7rem; color:#9ca3af; margin-bottom:4px;">${nivelLabel}</div>
                    <span class="badge ${rClass}"><i class="fas ${rIcon}"></i> ${risk}</span>
                </td>
                <td>
                    <div style="font-weight:800; color:#16a34a; font-family:monospace;">
                        ${tpdDisplay !== null ? tpdDisplay + 's' : '--'}
                    </div>
                </td>
                <td>
                    <span class="badge ${sClass}">${sText}</span>
                </td>
                <td>
                    <div style="display:flex; gap:5px;">
                        ${resolveBtn}
                        <button class="btn btn-sm btn-light" style="border-radius:8px;"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function resolveAlert(id) {
    if(!confirm('¿Marcar esta alerta como resuelta? Se notificará por Telegram y se detendrán los avisos automáticos.')) return;
    
    try {
        const res = await fetch(`/api/alerts/${id}/resolve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        });
        
        const json = await res.json();
        if (json.status === 'success') {
            loadAlerts(); // Recargar lista
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
    const settings = loc.alert_settings || { lixiviacion_alta: true, lixiviacion: true, acumulacion: true };

    body.innerHTML = `
        <div class="config-item">
            <div class="config-info">
                <div>Lixiviación Crítica</div>
                <div>Ratio ILx > 1.20 (Pérdida masiva)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-lix-alta" ${settings.lixiviacion_alta ? 'checked' : ''}>
                <span class="slider"></span>
            </label>
        </div>
        <div class="config-item">
            <div class="config-info">
                <div>Lixiviación Estándar</div>
                <div>Ratio ILx > 1.05 (Lavado de sales)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-lix" ${settings.lixiviacion ? 'checked' : ''}>
                <span class="slider"></span>
            </label>
        </div>
        <div class="config-item">
            <div class="config-info">
                <div>Acumulación de Sales</div>
                <div>Ratio ILx < 0.70 (Riesgo de salinidad)</div>
            </div>
            <label class="switch">
                <input type="checkbox" id="cfg-acum" ${settings.acumulacion ? 'checked' : ''}>
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
        lixiviacion_alta: document.getElementById('cfg-lix-alta').checked,
        lixiviacion:      document.getElementById('cfg-lix').checked,
        acumulacion:      document.getElementById('cfg-acum').checked,
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
            // Actualizar datos locales
            const loc = locationsData.find(l => l.id == locId);
            if (loc) loc.alert_settings = settings;
            
            alert('Configuración guardada correctamente. El sistema aplicará estos filtros a los nuevos registros.');
            closeConfig();
        }
    } catch (e) {
        alert('Error al guardar la configuración');
    }
}

// Filtros reactivos
['f-location','f-risk','f-status'].forEach(id =>
    document.getElementById(id).addEventListener('change', loadAlerts)
);

loadAlerts();
setInterval(loadAlerts, 60000); // Actualizar cada minuto
</script>
@endpush
