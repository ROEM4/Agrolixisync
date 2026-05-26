@extends('layouts.app')

@section('content')
<style>
    /* SCADA Variables */
    :root {
        --scada-ok-bg: #dcfce7;
        --scada-ok-text: #166534;
        --scada-warn-bg: #fef9c3;
        --scada-warn-text: #854d0e;
        --scada-crit-bg: #fee2e2;
        --scada-crit-text: #b91c1c;
        --scada-crit-border: #ef4444;
        --bg-panel: #1e293b;
        --text-main: #f8fafc;
        --font-mono: 'Consolas', 'Courier New', monospace;
    }

    @keyframes kpi-pulse {
        0%   { opacity: 1; transform: scale(1); box-shadow: 0 0 0 rgba(0,0,0,0); }
        50%  { opacity: 0.8; transform: scale(0.98); box-shadow: 0 0 15px rgba(59,130,246,0.3); }
        100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 rgba(0,0,0,0); }
    }
    .kpi-flash { animation: kpi-pulse 0.45s ease-out; }

    @keyframes alert-pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    .alert-flash { animation: alert-pulse 1.5s infinite; }

    /* Tabs */
    .tab-btn {
        padding: 0.75rem 1.5rem; border: none; background: transparent; 
        color: #6b7280; font-weight: 700; border-bottom: 3px solid transparent; 
        cursor: pointer; transition: all 0.2s; font-size: 0.95rem; outline: none;
    }
    .tab-btn:hover { color: #374151; }
    .tab-btn.active { color: #1a472a; border-bottom-color: #1a472a; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Tooltip */
    [data-tooltip] { position: relative; cursor: help; border-bottom: 1px dotted #9ca3af; }
    [data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
        background: #1e293b; color: white; padding: 0.5rem 0.8rem; border-radius: 6px;
        font-size: 0.75rem; white-space: nowrap; z-index: 50; margin-bottom: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .industrial-card {
        background: white; border-radius: 10px; padding: 1.5rem; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
        border: 2px solid transparent;
    }
    .mini-card {
        background: white; border-radius: 8px; padding: 1rem; 
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        border-left: 4px solid #94a3b8;
    }
</style>

<div class="w-full max-w-[1600px] mx-auto p-4 md:p-6 lg:p-10">

    {{-- CABECERA (TOP BAR) --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4 bg-white p-4 md:px-6 rounded-xl shadow-sm border border-slate-100">
        <div>
            <h1 class="m-0 text-xl md:text-2xl font-black text-emerald-900 tracking-tight">AGROlixisync</h1>
            <p class="m-0 text-xs md:text-sm text-slate-500 font-bold">Monitoreo de Lixiviación en AgrolixiSync</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-4 md:gap-8 w-full md:w-auto">
            @if($locations->isNotEmpty())
            <div class="flex-grow md:flex-grow-0">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">📍 Selector de Lote</label>
                <select id="location-selector" class="w-full md:min-w-[260px] p-2.5 border-2 border-slate-200 rounded-lg text-sm font-bold text-slate-700 bg-slate-50 focus:border-emerald-500 outline-none transition-colors">
                    <option value="">-- Seleccionar Lote --</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ request()->query('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->lote->name ?? $loc->name }} — {{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div class="flex-grow md:flex-grow-0 text-center px-4 border-l border-slate-100 hidden sm:block">
                <div class="text-[10px] text-slate-400 font-black tracking-wider mb-1">CONEXIÓN</div>
                <div class="flex items-center justify-center gap-2">
                    <span id="conn-text" class="font-bold text-sm text-slate-500">Esperando...</span>
                </div>
            </div>
            
            <div class="flex-grow md:flex-grow-0 text-right px-4 border-l border-slate-100">
                <div class="text-[10px] text-slate-400 font-black tracking-wider mb-1">ÚLTIMA LECTURA</div>
                <div id="last-update" class="text-sm md:text-base font-black text-slate-900 font-mono">--:--:--</div>
            </div>
        </div>
    </div>

    {{-- TIEMPO REAL --}}
        
        {{-- NIVEL A: BANNER ESTADO GENERAL --}}
        <div id="status-banner" class="rounded-xl p-4 md:p-8 flex flex-col md:flex-row justify-between items-center mb-6 bg-slate-500 text-white transition-all duration-500 border-2 border-transparent">
            @if($locations->isEmpty())
                <div class="text-center md:text-left">
                    <div class="text-xl md:text-3xl font-black tracking-tighter">⚫ SIN LOTES CONFIGURADOS</div>
                    <div class="text-sm md:text-base mt-1 opacity-90">No hay parcelas registradas en el sistema</div>
                </div>
            @else
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <div class="text-2xl md:text-4xl font-black tracking-tighter" id="status-title">⚪ SELECCIONA UN LOTE</div>
                    <div class="text-sm md:text-lg mt-1 opacity-90 font-medium" id="status-sub">Elige una parcela del selector superior para iniciar monitoreo</div>
                </div>
                <div id="alert-toast" class="hidden text-center md:text-right alert-flash">
                    <div class="text-[10px] md:text-xs font-black uppercase tracking-widest opacity-80 mb-1">Tiempo en Alerta</div>
                    <div id="alert-time" class="text-2xl md:text-4xl font-black font-mono">-- min</div>
                </div>
            @endif
        </div>

        {{-- NIVEL B: KPIs CRÍTICOS --}}
        <h2 class="text-xs font-black text-slate-400 mb-3 uppercase tracking-widest">Nivel Crítico (Conductividad)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            
            {{-- CE SUP --}}
            <div class="industrial-card" id="card-ce-sup">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                    <div style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase;">CE Superficial (20cm)</div>
                    <div id="trend-ce-sup" style="font-weight:900; font-size:1.2rem; color:#94a3b8;">-</div>
                </div>
                <div style="display:flex; align-items:baseline; gap:0.5rem;">
                    <div style="font-size:3rem; font-weight:900; color:#1e293b; font-family:var(--font-mono); letter-spacing:-2px; line-height:1;" id="kpi-ce-sup">--</div>
                    <div style="font-size:1rem; font-weight:700; color:#94a3b8;">dS/m</div>
                </div>
                <div style="font-size:0.75rem; color:#64748b; margin-top:0.75rem; font-weight:600; display:flex; justify-content:space-between;">
                    <span>Máx ref: 0.600</span>
                    <span id="status-ce-sup" style="padding:2px 8px; border-radius:12px; background:#e2e8f0;">--</span>
                </div>
            </div>

            {{-- CE PROF --}}
            <div class="industrial-card" id="card-ce-prof">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                    <div style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase;">CE Profundo (60cm)</div>
                    <div id="trend-ce-prof" style="font-weight:900; font-size:1.2rem; color:#94a3b8;">-</div>
                </div>
                <div style="display:flex; align-items:baseline; gap:0.5rem;">
                    <div style="font-size:3rem; font-weight:900; color:#1e293b; font-family:var(--font-mono); letter-spacing:-2px; line-height:1;" id="kpi-ce-prof">--</div>
                    <div style="font-size:1rem; font-weight:700; color:#94a3b8;">dS/m</div>
                </div>
                <div style="font-size:0.75rem; color:#64748b; margin-top:0.75rem; font-weight:600; display:flex; justify-content:space-between;">
                    <span>Máx ref: 0.750</span>
                    <span id="status-ce-prof" style="padding:2px 8px; border-radius:12px; background:#e2e8f0;">--</span>
                </div>
            </div>


            {{-- ILx — INDICADOR PRINCIPAL (reemplaza a la card de Delta CE) --}}
            <div class="industrial-card" id="card-ilx" style="border:2px solid #a78bfa;">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                    <div>
                        <div style="font-size:0.8rem; font-weight:800; color:#7c3aed; text-transform:uppercase;">⚗️ ILx — Índice de Lixiviación</div>
                        <div style="font-size:0.65rem; color:#94a3b8; font-weight:600; margin-top:2px;">CE_p / CE_s · Indicador principal v3</div>
                    </div>
                    <div id="trend-ilx" style="font-weight:900; font-size:1.2rem; color:#94a3b8;">-</div>
                </div>
                <div style="display:flex; align-items:baseline; gap:0.5rem;">
                    <div style="font-size:3rem; font-weight:900; color:#7c3aed; font-family:var(--font-mono); letter-spacing:-2px; line-height:1;" id="kpi-ilx">--</div>
                    <div style="font-size:1rem; font-weight:700; color:#a78bfa;">ratio</div>
                </div>
                <div style="font-size:0.75rem; color:#64748b; margin-top:0.75rem; font-weight:600; display:flex; justify-content:space-between;">
                    <span data-tooltip="Equilibrio: 0.90–1.05 | Lixiviación: >1.05 | Acumulación: <0.70">Equilibrio: 0.90–1.05</span>
                    <span id="status-ilx" style="padding:2px 8px; border-radius:12px; background:#ede9fe; color:#7c3aed; font-weight:700;">--</span>
                </div>
            </div>

        </div>


        {{-- GRÁFICOS SCADA --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div style="background:white; border-radius:10px; padding:1.25rem; box-shadow:0 1px 6px rgba(0,0,0,0.06); border:1px solid #e2e8f0;">
                <div style="font-weight:800; color:#334155; margin-bottom:1rem; font-size:0.95rem; text-transform:uppercase;">📈 Tendencia de Conductividad</div>
                <div style="position:relative; height:300px;">
                    <canvas id="chart-ce"></canvas>
                </div>
            </div>
            <div style="background:white; border-radius:10px; padding:1.25rem; box-shadow:0 1px 6px rgba(0,0,0,0.06); border:1px solid #e2e8f0;">
                <div style="font-weight:800; color:#334155; margin-bottom:1rem; font-size:0.95rem; text-transform:uppercase;">📉 ΔCE — Análisis Complementario</div>
                <div style="font-size:0.72rem; color:#94a3b8; margin-bottom:0.5rem; font-weight:600;">Diferencia CE_s − CE_p (indicador secundario de tendencia)</div>
                <div style="position:relative; height:280px;">
                    <canvas id="chart-delta"></canvas>
                </div>
            </div>
        </div>



        {{-- NIVEL C: KPIs AMBIENTALES --}}
        <h2 class="text-xs font-black text-slate-400 mb-3 uppercase tracking-widest">Variables Ambientales</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="mini-card" style="border-left-color:#38bdf8;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">Humedad Sup (20cm)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-hum-sup">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">%</span></div>
            </div>
            <div class="mini-card" style="border-left-color:#2dd4bf;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">Temp Sup (20cm)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-temp-sup">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">°C</span></div>
            </div>
            <div class="mini-card" style="border-left-color:#0284c7;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">Humedad Prof (60cm)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-hum-prof">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">%</span></div>
            </div>
            <div class="mini-card" style="border-left-color:#0f766e;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">Temp Prof (60cm)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-temp-prof">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">°C</span></div>
            </div>
        </div>

        {{-- ANÁLISIS COMPLEMENTARIO --}}
        <h2 class="text-xs font-black text-slate-400 mb-3 uppercase tracking-widest">Análisis Complementario (ΔCE)</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {{-- ΔCE Sup–Prof --}}
            <div class="mini-card" style="border-left-color:#f59e0b;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">ΔCE (Sup − Prof)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-delta">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">dS/m</span></div>
                <div style="font-size:0.65rem; color:#94a3b8; margin-top:4px;">Positivo → CE_s > CE_p</div>
            </div>
            {{-- ΔCE Temporal --}}
            <div class="mini-card" style="border-left-color:#8b5cf6;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.3rem;">ΔCE Temporal (vs. anterior)</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0f172a; font-family:var(--font-mono);" id="kpi-delta-temporal">--<span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">dS/m</span></div>
                <div style="font-size:0.65rem; color:#94a3b8; margin-top:4px;">Tendencia CE superficial</div>
            </div>
        </div>



    {{-- DEBUG BAR (Opcional, minimizado en footer) --}}
    <div id="debug-bar" style="margin-top:2rem; background:#0f172a; color:#38bdf8; border-radius:6px; padding:0.5rem 1rem; font-family:var(--font-mono); font-size:0.75rem; display:none; justify-content:space-between; opacity:0.8;">
        <div><span>DEBUG:</span> <span id="dbg-cycles">0</span> cyc | <span id="dbg-new">0</span> new | id:<span id="dbg-id">--</span> | streak:<span id="dbg-streak">0</span></div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<script>



// ─── ESTADO ───────────────────────────────────────────────────────────────────
let locationId   = null;
let pollTimer    = null;
let lastMaxId    = 0;
let totalCycles  = 0;
let newRecords   = 0;
let noDataStreak = 0;
let alertStartTime = null;
const STALE_LIMIT = 5;

const MAX_PTS = 60;
// buf.ilx = serie ILx (indicador principal); buf.delta = ΔCE (secundario)
const buf = { labels: [], sup: [], prof: [], delta: [], ilx: [] };

let prevValues = { ce_s: null, ce_p: null, ilx: null };

// ─── UMBRALES ILx (único criterio de decisión) ─────────────────────────────────
const ILX = {
    LIX_ALTA  : 1.20,
    LIX       : 1.05,
    EQUIL_HIGH: 1.05,
    EQUIL_LOW : 0.90,
    RET_LOW   : 0.70,
};

// Umbrales CE (solo para badge de tarjeta, no definen estado del sistema)
const THRESHOLDS = {
    ce_sup_max : 0.600,
    ce_prof_max: 0.750,
};

// ─── CLASIFICACIÓN ILx ────────────────────────────────────────────────────────
function classifyILx(ilx) {
    if (isNaN(ilx)) return { estado: 'SIN DATOS', icon: '⚪', level: 'none' };
    if (ilx > ILX.LIX_ALTA)      return { estado: 'LIXIVIACIÓN ALTA', icon: '🔴', level: 'crit' };
    if (ilx > ILX.LIX)           return { estado: 'LIXIVIACIÓN',      icon: '🟠', level: 'warn' };
    if (ilx >= ILX.EQUIL_LOW)    return { estado: 'EQUILIBRIO',       icon: '✅', level: 'ok'   };
    if (ilx >= ILX.RET_LOW)      return { estado: 'RETENCIÓN',        icon: '🔵', level: 'info' };
    return                               { estado: 'ACUMULACIÓN',      icon: '🟡', level: 'warn' };
}

// ─── CHARTS ───────────────────────────────────────────────────────────────────
Chart.register(window['chartjs-plugin-annotation']);

const commonChartOptions = {
    responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
    plugins: {
        legend: { position: 'top', labels: { font: { size: 12, weight: 'bold' } } },
    },
    scales: {
        x: { grid: { color: '#f1f5f9' } },
        y: { grid: { color: '#e2e8f0' }, beginAtZero: false, ticks: { font: { size: 11, weight: 'bold' } } }
    }
};

const chartCE = new Chart(document.getElementById('chart-ce'), {
    type: 'line',
    data: {
        labels: buf.labels,
        datasets: [
            {
                label: 'CE Sup (20cm)', data: buf.sup, borderColor: '#3b82f6', backgroundColor: '#3b82f6',
                tension: 0.3, pointRadius: 2, borderWidth: 3, spanGaps: true
            },
            {
                label: 'CE Prof (60cm)', data: buf.prof, borderColor: '#06b6d4', backgroundColor: '#06b6d4',
                tension: 0.3, pointRadius: 2, borderWidth: 3, spanGaps: true
            }
        ]
    },
    options: {
        ...commonChartOptions,
        plugins: {
            ...commonChartOptions.plugins,
            annotation: {
                annotations: {
                    limitSup: {
                        type: 'line', yMin: THRESHOLDS.ce_sup_max, yMax: THRESHOLDS.ce_sup_max,
                        borderColor: 'rgba(239,68,68,0.6)', borderWidth: 2, borderDash: [5, 5],
                        label: { display: true, content: 'Umbral Sup (0.60)', backgroundColor: 'rgba(239,68,68,0.8)', position: 'start', font: { weight: 'bold' } }
                    },
                    boxRisk: {
                        type: 'box', yMin: THRESHOLDS.ce_sup_max, backgroundColor: 'rgba(239,68,68,0.05)', borderWidth: 0
                    }
                }
            }
        }
    }
});

// gráfico ΔCE — indicador secundario / complementario
const chartDelta = new Chart(document.getElementById('chart-delta'), {
    type: 'line',
    data: {
        labels: buf.labels,
        datasets: [{
            label: 'ΔCE (Sup − Prof) [secundario]', data: buf.delta,
            borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.12)',
            tension: 0.3, pointRadius: 2, borderWidth: 2,
            fill: { target: 'origin', above: 'rgba(245,158,11,0.1)' }, spanGaps: true
        }]
    },
    options: {
        ...commonChartOptions,
        plugins: {
            ...commonChartOptions.plugins,
            annotation: {
                annotations: {
                    zeroLine: {
                        type: 'line', yMin: 0, yMax: 0,
                        borderColor: 'rgba(100,116,139,0.5)', borderWidth: 1, borderDash: [3, 3],
                        label: { display: true, content: 'Equilibrio (0)', position: 'start', font: { size: 10 } }
                    }
                }
            }
        }
    }
});

// ─── HISTORIAL INICIAL ────────────────────────────────────────────────────────
async function loadHistory() {
    if (!locationId) return;
    try {
        const res  = await fetch(`/api/readings/history?location_id=${locationId}&limit=${MAX_PTS}`);
        const json = await res.json();
        if (json.status !== 'success' || !json.data.length) return;

        for (const pair of json.data) {
            const ce_s = pair.sup  ? Number(pair.sup.conductivity_raw  ?? pair.sup.conductivity)  : null;
            const ce_p = pair.prof ? Number(pair.prof.conductivity_raw ?? pair.prof.conductivity) : null;
            const ilx  = (ce_s !== null && ce_p !== null && ce_s > 0) ? ce_p / ce_s : null;
            const dce  = (ce_s !== null && ce_p !== null) ? ce_s - ce_p : null;

            buf.labels.push(fmtTime(pair.recorded_at));
            buf.sup.push(ce_s);
            buf.prof.push(ce_p);
            buf.ilx.push(ilx);
            buf.delta.push(dce);
        }

        const ids = json.data.flatMap(p => [p.sup?.id, p.prof?.id]).filter(Boolean);
        if (ids.length) lastMaxId = Math.max(...ids);

        const lastIdx = buf.sup.length - 1;
        if (lastIdx >= 0) {
            prevValues.ce_s = buf.sup[lastIdx];
            prevValues.ce_p = buf.prof[lastIdx];
            prevValues.ilx  = buf.ilx[lastIdx];
        }

        chartCE.update('none');
        chartDelta.update('none');
    } catch (e) {
        console.warn('loadHistory error:', e);
    }
}

// ─── POLLING ──────────────────────────────────────────────────────────────────
async function poll() {
    if (!locationId) return;
    try {
        const res  = await fetch(`/api/readings/latest?location_id=${locationId}&_=${Date.now()}`);
        const json = await res.json();

        if (!res.ok || json.status !== 'success') { setConn(false, '❌ Error API'); return; }

        const readings = Array.isArray(json.data?.readings) ? json.data.readings : [];
        const analysis = json.data?.analysis ?? null;

        if (readings.length === 0) { setConn(true, 'Sin datos aún'); return; }

        const sup  = readings.find(r => Number(r.sensor.depth) === 20) ?? null;
        const prof = readings.find(r => Number(r.sensor.depth) === 60) ?? null;

        if (!sup && !prof) { setConn(true, 'Sin sensores reconocidos'); return; }

        const maxId = Math.max(...readings.map(r => r.id));
        const isNew = maxId > lastMaxId;

        totalCycles++;
        document.getElementById('dbg-cycles').textContent = totalCycles;

        if (isNew) {
            lastMaxId = maxId;
            newRecords++;
            noDataStreak = 0;
            document.getElementById('dbg-id').textContent  = maxId;
            document.getElementById('dbg-new').textContent = newRecords;
        } else {
            noDataStreak++;
        }
        document.getElementById('dbg-streak').textContent = noDataStreak;

        renderData(sup, prof, isNew, analysis);

        setConn(true, noDataStreak >= STALE_LIMIT ? '🟡 Sin datos nuevos' : (isNew ? '🟢 Recibiendo...' : '🟢 Conectado'));

    } catch (e) {
        setConn(false, '❌ Sin conexión');
        console.error('Poll error:', e);
    }
}

function startPoll() {
    if (pollTimer) clearInterval(pollTimer);
    totalCycles = newRecords = noDataStreak = lastMaxId = 0;
    alertStartTime = null;
    loadHistory().then(() => { poll(); pollTimer = setInterval(poll, 3000); });
}

function stopPoll() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

// ─── RENDER UI ────────────────────────────────────────────────────────────────
function renderData(sup, prof, pushChart, analysis = null) {
    const rawCeS = sup?.conductivity_raw ?? sup?.conductivity ?? null;
    const rawCeP = prof?.conductivity_raw ?? prof?.conductivity ?? null;
    const ce_s  = rawCeS !== null ? Number(rawCeS) : NaN;
    const ce_p  = rawCeP !== null ? Number(rawCeP) : NaN;
    const hum_s = (sup?.humidity  != null) ? Number(sup.humidity)  : NaN;
    const hum_p = (prof?.humidity != null) ? Number(prof.humidity) : NaN;
    const tmp_s = (sup?.temperature  != null) ? Number(sup.temperature)  : NaN;
    const tmp_p = (prof?.temperature != null) ? Number(prof.temperature) : NaN;

    // ── Indicador PRINCIPAL: ILx ──────────────────────────────────────────────
    // Prioridad: valor del backend (analysis.ilx) > calculado en tiempo real
    const ilx_backend = (analysis?.ilx != null) ? Number(analysis.ilx) : NaN;
    const ilx = !isNaN(ilx_backend)
        ? ilx_backend
        : (!isNaN(ce_s) && ce_s > 0 && !isNaN(ce_p) ? ce_p / ce_s : NaN);

    // ── Indicador SECUNDARIO: ΔCE ─────────────────────────────────────────────
    const delta = (!isNaN(ce_s) && !isNaN(ce_p)) ? ce_s - ce_p : NaN;

    // Tendencias (solo en lectura nueva)
    if (pushChart) {
        setTrend('trend-ce-sup',  ce_s, prevValues.ce_s);
        setTrend('trend-ce-prof', ce_p, prevValues.ce_p);
        setTrend('trend-ilx',     ilx,  prevValues.ilx);

        const delta_temporal = (prevValues.ce_s !== null && !isNaN(ce_s)) ? ce_s - prevValues.ce_s : NaN;
        setKpi('kpi-delta-temporal', isNaN(delta_temporal) ? '--' : parseFloat(delta_temporal.toPrecision(4)).toString());

        prevValues.ce_s = isNaN(ce_s) ? null : ce_s;
        prevValues.ce_p = isNaN(ce_p) ? null : ce_p;
        prevValues.ilx  = isNaN(ilx)  ? null : ilx;
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────
    setKpi('kpi-ce-sup',  displayRaw(rawCeS));
    setKpi('kpi-ce-prof', displayRaw(rawCeP));

    // ILx — indicador principal
    const ilxStr = isNaN(ilx) ? '--' : parseFloat(ilx.toPrecision(4)).toString();
    setKpi('kpi-ilx', ilxStr);

    // ΔCE — mini-card secundaria
    setKpi('kpi-delta', isNaN(delta) ? '--' : parseFloat(delta.toPrecision(4)).toString());

    document.getElementById('kpi-hum-sup').innerHTML  = (isNaN(hum_s) ? '--' : hum_s.toFixed(1))  + '<span style="font-size:0.8rem;color:#94a3b8;font-weight:600;">%</span>';
    document.getElementById('kpi-temp-sup').innerHTML = (isNaN(tmp_s) ? '--' : tmp_s.toFixed(1))  + '<span style="font-size:0.8rem;color:#94a3b8;font-weight:600;">°C</span>';
    document.getElementById('kpi-hum-prof').innerHTML = (isNaN(hum_p) ? '--' : hum_p.toFixed(1))  + '<span style="font-size:0.8rem;color:#94a3b8;font-weight:600;">%</span>';
    document.getElementById('kpi-temp-prof').innerHTML= (isNaN(tmp_p) ? '--' : tmp_p.toFixed(1))  + '<span style="font-size:0.8rem;color:#94a3b8;font-weight:600;">°C</span>';

    // Badges tarjeta CE (umbrales referencia)
    updateCardStyle('card-ce-sup',  'status-ce-sup',  ce_s, THRESHOLDS.ce_sup_max);
    updateCardStyle('card-ce-prof', 'status-ce-prof', ce_p, THRESHOLDS.ce_prof_max);

    // Badge tarjeta ILx (basado en zona agronómica)
    updateILxCardStyle(ilx);

    // ── BANNER — decisión exclusivamente por ILx ──────────────────────────────
    const banner = document.getElementById('status-banner');
    const title  = document.getElementById('status-title');
    const sub    = document.getElementById('status-sub');
    const toast  = document.getElementById('alert-toast');
    const tTime  = document.getElementById('alert-time');

    let cls;
    if (isNaN(ilx)) {
        cls = { estado: 'SISTEMA INCOMPLETO — Esperando sensores', icon: '⚪', level: 'none' };
    } else {
        // Si el backend ya envía el estado (analysis.state), lo usamos; si no, calculamos
        cls = classifyILx(ilx);
        if (analysis?.ilx_estado) cls.estado = analysis.ilx_estado;
    }

    const isCritical = cls.level === 'crit';
    const isWarn     = cls.level === 'warn';

    if (isCritical) {
        banner.style.background = 'var(--scada-crit-bg)';
        banner.style.color      = 'var(--scada-crit-text)';
        banner.style.border     = '2px solid var(--scada-crit-border)';
        toast.style.display     = 'block';
        if (!alertStartTime) alertStartTime = new Date();
        tTime.textContent = Math.floor((new Date() - alertStartTime) / 60000) + ' min';
    } else if (isWarn) {
        banner.style.background = 'var(--scada-warn-bg)';
        banner.style.color      = 'var(--scada-warn-text)';
        banner.style.border     = '2px solid transparent';
        toast.style.display     = 'none';
        alertStartTime = null;
    } else {
        banner.style.background = 'var(--scada-ok-bg)';
        banner.style.color      = 'var(--scada-ok-text)';
        banner.style.border     = '2px solid transparent';
        toast.style.display     = 'none';
        alertStartTime = null;
    }

    title.textContent = `${cls.icon} ${cls.estado}`;
    sub.textContent   = `ILx: ${ilxStr}  |  ΔCE: ${isNaN(delta) ? '--' : parseFloat(delta.toPrecision(4))} dS/m`;

    // ── Actualizar charts ─────────────────────────────────────────────────────
    if (pushChart) {
        const ref = sup ?? prof;
        buf.labels.push(fmtTime(ref.recorded_at));
        buf.sup.push(isNaN(ce_s) ? null : ce_s);
        buf.prof.push(isNaN(ce_p) ? null : ce_p);
        buf.ilx.push(isNaN(ilx) ? null : ilx);
        buf.delta.push(isNaN(delta) ? null : delta);

        if (buf.labels.length > MAX_PTS) {
            buf.labels.shift(); buf.sup.shift(); buf.prof.shift();
            buf.ilx.shift(); buf.delta.shift();
        }
        chartCE.update();
        chartDelta.update();
    }

    document.getElementById('last-update').textContent = new Date().toLocaleTimeString('es', {hour12:false});
}

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function updateILxCardStyle(ilx) {
    const card = document.getElementById('card-ilx');
    const stat = document.getElementById('status-ilx');
    if (!card || !stat) return;
    if (isNaN(ilx)) {
        card.style.borderColor = '#a78bfa';
        stat.textContent = '--';
        stat.style.background = '#ede9fe'; stat.style.color = '#7c3aed';
        return;
    }
    const cls = classifyILx(ilx);
    const map = {
        crit: { border:'#ef4444', bg:'#fee2e2', color:'#b91c1c' },
        warn: { border:'#f59e0b', bg:'#fef9c3', color:'#854d0e' },
        ok  : { border:'#22c55e', bg:'#dcfce7', color:'#166534' },
        info: { border:'#38bdf8', bg:'#e0f2fe', color:'#075985' },
        none: { border:'#a78bfa', bg:'#ede9fe', color:'#7c3aed' },
    };
    const s = map[cls.level] ?? map.none;
    card.style.borderColor    = s.border;
    stat.textContent          = cls.estado;
    stat.style.background     = s.bg;
    stat.style.color          = s.color;
}

function setKpi(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.textContent !== value) {
        el.textContent = value;
        const card = el.closest('.industrial-card, .mini-card');
        if (card) {
            card.classList.remove('kpi-flash');
            void card.offsetWidth;
            card.classList.add('kpi-flash');
        }
    }
}

function setTrend(id, current, previous) {
    const el = document.getElementById(id);
    if (!el) return;
    if (previous === null || isNaN(current) || isNaN(previous)) {
        el.textContent = '-'; el.style.color = '#94a3b8'; return;
    }
    const diff = current - previous;
    if (diff > 0.005)       { el.textContent = '↑'; el.style.color = '#ef4444'; }
    else if (diff < -0.005) { el.textContent = '↓'; el.style.color = '#10b981'; }
    else                    { el.textContent = '→'; el.style.color = '#94a3b8'; }
}

function updateCardStyle(cardId, statusId, val, maxRef) {
    const card = document.getElementById(cardId);
    const stat = document.getElementById(statusId);
    if (!card || !stat) return;
    if (isNaN(val)) {
        card.style.borderColor = 'transparent';
        stat.textContent = '--'; stat.style.background = '#e2e8f0'; stat.style.color = '#64748b';
        return;
    }
    if (val > maxRef) {
        card.style.borderColor = 'var(--scada-crit-border)';
        stat.textContent = 'ALERTA'; stat.style.background = 'var(--scada-crit-bg)'; stat.style.color = 'var(--scada-crit-text)';
    } else {
        card.style.borderColor = 'transparent';
        stat.textContent = 'OK'; stat.style.background = 'var(--scada-ok-bg)'; stat.style.color = 'var(--scada-ok-text)';
    }
}

function fmtTime(iso)     { return new Date(iso).toLocaleTimeString('es', {hour12:false}); }
function fmtDateTime(iso) { return new Date(iso).toLocaleString('es', {hour12:false}); }
function setConn(ok, msg) { document.getElementById('conn-text').textContent = msg; }

function displayRaw(value) {
    if (value === null || value === undefined || value === '') return '--';
    const n = Number(value);
    if (isNaN(n)) return String(value);
    return parseFloat(n.toPrecision(4)).toString();
}

// ─── SELECTOR DE LOTE ─────────────────────────────────────────────────────────
const selector = document.getElementById('location-selector');
if (selector) {
    selector.addEventListener('change', function () {
        stopPoll();
        buf.labels.length = 0; buf.sup.length = 0; buf.prof.length = 0;
        buf.delta.length  = 0; buf.ilx.length = 0;
        prevValues = { ce_s: null, ce_p: null, ilx: null };
        chartCE.update(); chartDelta.update();

        locationId = this.value || null;

        if (locationId) {
            localStorage.setItem('agro_loc', locationId);
            document.getElementById('debug-bar').style.display = 'flex';
            startPoll();
        } else {
            localStorage.removeItem('agro_loc');
            document.getElementById('debug-bar').style.display = 'none';
            setConn(false, 'Esperando...');
        }
    });

    const requestedLocationId = '{{ request()->query('location_id') }}';
    const saved = localStorage.getItem('agro_loc');
    if (requestedLocationId && selector.querySelector(`option[value="${requestedLocationId}"]`)) {
        selector.value = requestedLocationId;
        locationId = requestedLocationId;
        localStorage.setItem('agro_loc', requestedLocationId);
        document.getElementById('debug-bar').style.display = 'flex';
        startPoll();
    } else if (saved && selector.querySelector(`option[value="${saved}"]`)) {
        selector.value = saved;
        locationId = saved;
        document.getElementById('debug-bar').style.display = 'flex';
        startPoll();
    }
}
</script>
@endpush
