@extends('layouts.app')
@section('title', 'Histórico — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(255, 255, 255, 0.4);
    }
    body { background: #f8fafc; }
    .page-header { margin-bottom: 2rem; }
    .page-header h1 { font-size: 2rem; font-weight: 900; color: #0f172a; letter-spacing: -0.025em; }
    .page-header p  { margin: 0.25rem 0 0; font-size: 0.95rem; color: #64748b; font-weight: 500; }
    
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
    }

    .toolbar {
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid var(--glass-border);
        display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: flex-end;
        margin-bottom: 2rem;
    }
    .toolbar label { font-size: 0.65rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; }
    .toolbar select, .toolbar input {
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0; border-radius: 12px;
        font-size: 0.85rem; background: #fff; font-weight: 600; color: #334155;
        outline: none; transition: border-color 0.2s;
    }
    .toolbar select:focus { border-color: #10b981; }

    .btn-load {
        padding: 0.75rem 1.5rem;
        background: #10b981;
        color: #fff; border: none; border-radius: 12px;
        font-weight: 800; font-size: 0.85rem; cursor: pointer;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        transition: all 0.2s;
    }
    .btn-load:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3); }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem; }

    .stat-card {
        background: var(--glass-bg); backdrop-filter: blur(16px);
        border-radius: 20px; padding: 1.5rem;
        border: 1px solid var(--glass-border);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }
    .stat-card .s-label { font-size: 0.65rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.75rem; }
    .stat-card .s-val   { font-size: 2rem; font-weight: 900; letter-spacing: -0.05em; }
    .stat-card .s-sub   { font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 0.25rem; }
    .stat-card .s-range { font-size: 0.75rem; color: #6b7280; margin-top: 0.3rem; }

    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    
    .chart-card {
        background: var(--glass-bg); backdrop-filter: blur(16px);
        border-radius: 24px; padding: 1.75rem;
        border: 1px solid var(--glass-border);
    }
    .chart-card .c-title { font-weight: 800; color: #1e293b; font-size: 0.95rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
    .chart-wrap { position: relative; height: 260px; }

    .table-card {
        background: var(--glass-bg); backdrop-filter: blur(16px);
        border-radius: 24px; border: 1px solid var(--glass-border);
        overflow: hidden; margin-bottom: 2rem;
    }
    .table-head {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--glass-border);
        font-weight: 800; font-size: 1rem; color: #1e293b;
    }
    table { width: 100%; border-collapse: collapse; }
    table th { background: rgba(248, 250, 252, 0.5); padding: 1rem 1.5rem; font-weight: 800; color: #64748b; text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.1em; }
    table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--glass-border); font-family: 'Consolas', monospace; font-size: 0.85rem; font-weight: 500; }
    tr:last-child td { border-bottom: none; }

    .placeholder { padding: 4rem; text-align: center; color: #9ca3af; font-size: 1rem; }
</style>

<div class="page-header">
    <h1>📋 Registro e Histórico de Lecturas</h1>
    <p>Historial reciente y tendencias diarias de CE, humedad y temperatura por lote</p>
</div>

{{-- Toolbar --}}
<div class="toolbar">
    <div>
        <label>Lote / Ubicación</label>
        <select id="h-location">
            <option value="">-- Seleccionar --</option>
            @foreach($locations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->lote->name ?? $loc->name }} — {{ $loc->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Período</label>
        <select id="h-days">
            <option value="1">Últimas 24 horas</option>
            <option value="7">Últimos 7 días</option>
            <option value="14">Últimos 14 días</option>
            <option value="30" selected>Últimos 30 días</option>
            <option value="60">Últimos 60 días</option>
            <option value="90">Últimos 90 días</option>
        </select>
    </div>
    <button class="btn-load" onclick="loadHistorico()">📊 Cargar datos</button>
    <button id="export-csv" class="btn-load" style="background:linear-gradient(135deg,#2563eb,#1d4ed8); display:none;" onclick="exportCsv()">📥 Exportar CSV</button>
    <span id="h-status" style="font-size:0.8rem;color:#6b7280;align-self:center;"></span>
</div>

{{-- RECENT READINGS TABLE (Moved from Realtime) --}}
<div class="table-card" id="recent-table-section" style="display:none; margin-bottom:1.5rem;">
    <div class="table-head">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span>⏱️ Últimas Lecturas Registradas</span>
        </div>
    </div>
    <div style="padding:0.75rem 1.25rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:0.8rem; color:#475569;">
        <strong>📌 Leyenda de Umbrales:</strong> 🟢 Verde = OK | 🔴 Rojo = ALERTA (CE Sup > 0.600, CE Prof > 0.750)
    </div>
    <div style="overflow-x:auto; max-height: 400px; overflow-y:auto;">
        <table>
            <thead style="position: sticky; top: 0; background: #f9fafb; z-index: 10;">
                <tr>
                    <th>Hora (RTC)</th>
                    <th>Sensor</th>
                    <th>Profundidad</th>
                    <th>CE (dS/m)</th>
                    <th>Humedad</th>
                    <th>Temp</th>
                </tr>
            </thead>
            <tbody id="recent-body"></tbody>
        </table>
    </div>
</div>

<h2 style="font-size:1.2rem; color:#1e293b; font-weight:800; margin-bottom:1rem;">Histórico Diario</h2>

{{-- Stats --}}
<div class="stats-grid" id="stats-section" style="display:none;">
    <div class="stat-card" style="border-left:4px solid #f59e0b;">
        <div class="s-label">Total Lecturas</div>
        <div class="s-val" id="s-total" style="color:#f59e0b;">--</div>
        <div class="s-sub">registros en el período</div>
        <div class="s-range" id="s-days-range"></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981;">
        <div class="s-label">Humedad Sup — Promedio</div>
        <div class="s-val" id="s-hum-sup" style="color:#10b981;">--</div>
        <div class="s-sub">% &nbsp;|&nbsp; <span id="s-hum-sup-range"></span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #14b8a6;">
        <div class="s-label">Temperatura Sup — Promedio</div>
        <div class="s-val" id="s-temp-sup" style="color:#14b8a6;">--</div>
        <div class="s-sub">°C &nbsp;|&nbsp; <span id="s-temp-sup-range"></span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #8b5cf6;">
        <div class="s-label">Humedad Prof — Promedio</div>
        <div class="s-val" id="s-hum-prof" style="color:#8b5cf6;">--</div>
        <div class="s-sub">% &nbsp;|&nbsp; <span id="s-hum-prof-range"></span></div>
    </div>
</div>

{{-- Gráficos --}}
<div class="charts-grid" id="charts-section" style="display:none;">
    <div class="chart-card">
        <div class="c-title">📈 CE Diaria — Superficial vs Profundo (dS/m)</div>
        <div class="chart-wrap"><canvas id="chart-ce-daily"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="c-title">💧 Humedad Diaria (%)</div>
        <div class="chart-wrap"><canvas id="chart-hum-daily"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="c-title">🌡️ Temperatura Diaria (°C)</div>
        <div class="chart-wrap"><canvas id="chart-temp-daily"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="c-title">⚖️ Δ CE Diaria (Sup − Prof)</div>
        <div class="chart-wrap"><canvas id="chart-delta-daily"></canvas></div>
    </div>
</div>

{{-- Tabla diaria --}}
<div class="table-card" id="table-section" style="display:none;">
    <div class="table-head">Registros Históricos</div>
    <div style="overflow-x:auto;">
        <table id="hist-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>CE Superficial</th>
                    <th>CE Profunda</th>
                    <th>Δ CE (Dif.)</th>
                    <th>Ratio ILx</th>
                    <th>Hum. Sup (%)</th>
                    <th>Temp. Sup (°C)</th>
                    <th>Muestras (n)</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="hist-body">
                <tr>
                    <td colspan="9" class="placeholder">
                        <i class="fas fa-search mb-2 block text-2xl opacity-20"></i>
                        Selecciona filtros y pulsa "Cargar Histórico"
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- IMPORTAR CSV --}}
<div class="table-card" style="padding:1.25rem; margin-top:1.5rem;">
    <h3 style="font-size:1rem; color:#1e293b; font-weight:700; margin:0 0 0.5rem 0;">💾 Importar respaldo desde MicroSD</h3>
    <p style="margin:0 0 1rem; font-size:0.85rem; color:#64748b;">En caso de desconexión WiFi, sube el archivo <code>lecturas.csv</code> para sincronizar.</p>
    <form id="csv-import-form" style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
        @csrf
        <input type="file" id="csv-file" accept=".csv,.txt" required
            style="padding:0.5rem; border:2px solid #e2e8f0; border-radius:6px; font-size:0.85rem;">
        <button type="submit"
            style="padding:0.6rem 1.2rem; background:#10b981; color:white; border:none; border-radius:6px; font-weight:700; cursor:pointer; font-size:0.85rem;">
            📤 Importar Datos
        </button>
        <span id="import-result" style="font-size:0.85rem; font-weight:600;"></span>
    </form>
</div>

<div id="placeholder" class="placeholder">
    <div style="font-size:2.5rem;margin-bottom:0.5rem;">📊</div>
    Selecciona un lote y período para ver el histórico
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let chartCE, chartHum, chartTemp, chartDelta;

function destroyCharts() {
    [chartCE, chartHum, chartTemp, chartDelta].forEach(c => c?.destroy());
}

async function loadHistorico() {
    const locId = document.getElementById('h-location').value;
    const days  = document.getElementById('h-days').value;

    if (!locId) { alert('Selecciona una ubicación'); return; }

    const btn = document.querySelector('.btn-load');
    const originalBtnText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
    btn.disabled = true;

    document.getElementById('h-status').textContent = '⏳ Cargando...';
    document.getElementById('placeholder').style.display = 'none';

    try {
        const [histRes, analyticsRes, recentRes] = await Promise.all([
            fetch(`/api/historian/daily?location_id=${locId}&days=${days}`),
            fetch(`/api/readings/analytics?location_id=${locId}&days=${days}`),
            fetch(`/api/readings/history?location_id=${locId}&limit=50`)
        ]);
        const hist      = await histRes.json();
        const analytics = await analyticsRes.json();
        const recent    = await recentRes.json();

        renderStats(analytics);
        renderCharts(hist.data ?? [], analytics);
        renderTable(hist.data ?? []);
        renderRecent(recent.data ?? []);

        document.getElementById('stats-section').style.display  = 'grid';
        document.getElementById('charts-section').style.display = 'grid';
        document.getElementById('table-section').style.display  = 'block';
        document.getElementById('recent-table-section').style.display = 'block';
        document.getElementById('export-csv').style.display = 'block';
        document.getElementById('h-status').textContent = `✅ 15 días cargados`;
    } catch(e) {
        document.getElementById('h-status').textContent = '❌ Error al cargar';
        console.error(e);
    } finally {
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
    }
}

function renderStats(analytics) {
    const sup  = analytics.superficial;
    const prof = analytics.profundo;
    const fmt  = (v, d=4) => v !== null && v !== undefined ? parseFloat(v).toFixed(d) : '--';

    document.getElementById('s-total').textContent   = (sup?.stats?.total_readings ?? 0) + (prof?.stats?.total_readings ?? 0);
    document.getElementById('s-days-range').textContent = `${analytics.days} días`;

    if (sup?.stats) {
        document.getElementById('s-hum-sup').textContent      = fmt(sup.stats.hum_avg, 1);
        document.getElementById('s-hum-sup-range').textContent = `min ${fmt(sup.stats.hum_min,1)} / max ${fmt(sup.stats.hum_max,1)}`;
        document.getElementById('s-temp-sup').textContent     = fmt(sup.stats.temp_avg, 1);
        document.getElementById('s-temp-sup-range').textContent = `min ${fmt(sup.stats.temp_min,1)} / max ${fmt(sup.stats.temp_max,1)}`;
    }
    if (prof?.stats) {
        document.getElementById('s-hum-prof').textContent     = fmt(prof.stats.hum_avg, 1);
        document.getElementById('s-hum-prof-range').textContent = `min ${fmt(prof.stats.hum_min,1)} / max ${fmt(prof.stats.hum_max,1)}`;
    }
}

function renderCharts(daily, analytics) {
    destroyCharts();

    const labels   = daily.map(d => d.day ?? d.date ?? '');
    const ce_sup   = daily.map(d => d.ce_sup_avg   ?? d.avg_ce_sup   ?? null);
    const ce_prof  = daily.map(d => d.ce_prof_avg  ?? d.avg_ce_prof  ?? null);
    const delta    = ce_sup.map((s,i) => s !== null && ce_prof[i] !== null ? parseFloat((s - ce_prof[i]).toFixed(6)) : null);

    const supTrend  = analytics.superficial?.daily_trend ?? [];
    const profTrend = analytics.profundo?.daily_trend    ?? [];
    const humLabels = supTrend.map(d => d.day);
    const humSup    = supTrend.map(d => d.hum_avg);
    const humProf   = profTrend.map(d => d.hum_avg);
    const tempSup   = supTrend.map(d => d.temp_avg);
    const tempProf  = profTrend.map(d => d.temp_avg);

    const opts = (yLabel) => ({
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: { y: { beginAtZero: false, title: { display: true, text: yLabel, font: { size: 10 } } } }
    });

    chartCE = new Chart(document.getElementById('chart-ce-daily'), {
        type: 'line',
        data: { labels, datasets: [
            { label:'SUP 20cm', data: ce_sup,  borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.07)', tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
            { label:'PROF 60cm', data: ce_prof, borderColor:'#06b6d4', backgroundColor:'rgba(6,182,212,0.07)',  tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
        ]},
        options: opts('dS/m')
    });

    chartHum = new Chart(document.getElementById('chart-hum-daily'), {
        type: 'line',
        data: { labels: humLabels, datasets: [
            { label:'Hum SUP', data: humSup,  borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.07)', tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
            { label:'Hum PROF', data: humProf, borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.07)', tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
        ]},
        options: opts('%')
    });

    chartTemp = new Chart(document.getElementById('chart-temp-daily'), {
        type: 'line',
        data: { labels: humLabels, datasets: [
            { label:'Temp SUP', data: tempSup,  borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.07)', tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
            { label:'Temp PROF', data: tempProf, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.07)',  tension:0.3, pointRadius:3, fill:true, borderWidth:2, spanGaps:true },
        ]},
        options: opts('°C')
    });

    chartDelta = new Chart(document.getElementById('chart-delta-daily'), {
        type: 'bar',
        data: { labels, datasets: [{
            label:'Δ CE',
            data: delta,
            backgroundColor: delta.map(v => v === null ? 'transparent' : Math.abs(v) > 0.35 ? 'rgba(220,38,38,0.7)' : Math.abs(v) > 0.15 ? 'rgba(245,158,11,0.7)' : 'rgba(34,197,94,0.7)'),
            borderRadius: 3, spanGaps: true
        }]},
        options: { ...opts('dS/m'), plugins: { legend: { display: false } } }
    });
}

function renderTable(daily) {
    const body = document.getElementById('hist-body');
    if (!daily.length) {
        body.innerHTML = '<tr><td colspan="9" style="padding:2rem;text-align:center;color:#9ca3af;">Sin datos para el período</td></tr>';
        return;
    }
    body.innerHTML = [...daily].reverse().map(d => {
        const cs = d.ce_sup_avg  ?? d.avg_ce_sup  ?? null;
        const cp = d.ce_prof_avg ?? d.avg_ce_prof ?? null;
        const ilx = cs > 0 ? (cp / cs).toFixed(4) : '--';
        const delta = cs !== null && cp !== null ? (cs - cp).toFixed(6) : '--';
        const deltaColor = delta !== '--' ? (Math.abs(parseFloat(delta)) > 0.35 ? '#dc2626' : Math.abs(parseFloat(delta)) > 0.15 ? '#d97706' : '#16a34a') : '#9ca3af';
        const estado = parseFloat(ilx) > 1.0 ? '<span class="text-red-600 font-bold">Lixiviación</span>' : (parseFloat(ilx) < 0.4 ? 'Baja' : 'Normal');
        
        return `<tr>
            <td style="color:#374151; font-weight:700;">${d.day ?? d.date ?? '--'}</td>
            <td style="color:#3b82f6; font-family:monospace;">${cs !== null ? parseFloat(cs).toFixed(4) : '--'}</td>
            <td style="color:#06b6d4; font-family:monospace;">${cp !== null ? parseFloat(cp).toFixed(4) : '--'}</td>
            <td style="color:${deltaColor}; font-family:monospace; font-weight:700;">${delta}</td>
            <td style="font-weight:700; color:#6366f1; font-family:monospace;">${ilx}</td>
            <td style="color:#10b981;">${d.hum_sup_avg ? parseFloat(d.hum_sup_avg).toFixed(1) + '%' : '--'}</td>
            <td style="color:#f59e0b;">${d.temp_sup_avg ? parseFloat(d.temp_sup_avg).toFixed(1) + '°C' : '--'}</td>
            <td style="color:#9ca3af; text-align:center;">${d.n ?? d.count ?? '--'}</td>
            <td>${estado}</td>
        </tr>`;
    }).join('');
}

function renderRecent(data) {
    const body = document.getElementById('recent-body');
    if (!data.length) {
        body.innerHTML = '<tr><td colspan="6" style="padding:2rem;text-align:center;color:#9ca3af;">Sin datos recientes</td></tr>';
        return;
    }

    const fmtDateTime = (iso) => new Date(iso).toLocaleString('es', {hour12:false});
    const getCEColor = (ce, depth) => {
        if (isNaN(ce) || ce === null) return '#64748b';
        const max = Number(depth) === 20 ? 0.600 : 0.750;
        return ce > max ? '#ef4444' : '#1e293b';
    };
    const thresholdIndicator = (ce, depth) => {
        if (isNaN(ce) || ce === null) return '⚪';
        const max = Number(depth) === 20 ? 0.600 : 0.750;
        return ce > max ? '🔴' : '🟢';
    };
    const displayRaw = (val) => val === null || val === undefined ? '--' : parseFloat(Number(val).toPrecision(4)).toString();

    let html = '';
    data.forEach(pair => {
        const row = (r, isSup) => {
            if (!r) return '';
            const ce = isSup ? (r.conductivity_raw ?? r.conductivity) : (r.conductivity_raw ?? r.conductivity);
            const numCe = Number(ce);
            return `<tr style="border-bottom:1px solid #e2e8f0;">
                <td style="font-family:monospace;color:#475569;">${fmtDateTime(r.recorded_at)}</td>
                <td style="font-weight:600;color:#64748b;">${r.sensor.code}</td>
                <td><span style="background:#e0f2fe;color:#0369a1;padding:3px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;">${r.sensor.depth}cm</span></td>
                <td style="font-weight:800;font-family:monospace;color:${getCEColor(numCe, r.sensor.depth)};">
                    ${thresholdIndicator(numCe, r.sensor.depth)} ${displayRaw(ce)}
                </td>
                <td style="font-family:monospace;color:#475569;">${r.humidity !== null ? Number(r.humidity).toFixed(1)+'%' : '--'}</td>
                <td style="font-family:monospace;color:#475569;">${r.temperature !== null ? Number(r.temperature).toFixed(1)+'°C' : '--'}</td>
            </tr>`;
        };
        html += row(pair.sup, true) + row(pair.prof, false);
    });

    body.innerHTML = html;
}

function exportCsv() {
    const locId = document.getElementById('h-location').value;
    if (locId) {
        window.open(`/api/export/csv?location_id=${locId}`, '_blank');
    }
}

document.getElementById('csv-import-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const file = document.getElementById('csv-file').files[0];
    if (!file) return;
    const result = document.getElementById('import-result');
    result.textContent = 'Importando...';
    result.style.color = '#64748b';

    const form = new FormData();
    form.append('file', file);
    const token = document.querySelector('input[name="_token"]').value;
    form.append('_token', token);

    try {
        const res  = await fetch('/api/import/csv', { method: 'POST', body: form });
        const json = await res.json();
        if (json.status === 'success') {
            result.textContent = `✅ Importados: ${json.imported} | Omitidos: ${json.skipped}`;
            result.style.color = '#10b981';
            loadHistorico();
        } else {
            result.textContent = '❌ Error: ' + (json.error || 'desconocido');
            result.style.color = '#ef4444';
        }
    } catch(err) {
        result.textContent = '❌ Error de conexión';
        result.style.color = '#ef4444';
    }
});

// Auto-cargar si hay un lote guardado
const saved = localStorage.getItem('agro_loc');
const sel   = document.getElementById('h-location');
if (saved && sel.querySelector(`option[value="${saved}"]`)) {
    sel.value = saved;
    loadHistorico();
}
</script>
@endpush
