@extends('layouts.app')

@section('title', 'Índice de Lixiviación — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.8);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --accent-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

    /* SCADA Colors for mirror cards */
    .border-crit { border-left: 6px solid #ef4444; }
    .border-warn { border-left: 6px solid #f59e0b; }
    .border-ok   { border-left: 6px solid #22c55e; }
    .border-info { border-left: 6px solid #3b82f6; }
</style>

<div class="page-container">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <div class="live-dot"></div>
                <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Monitoreo en Tiempo Real</span>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Índice de Lixiviación</h1>
            <div class="mt-8 text-center text-[10px] font-bold text-slate-400 italic">
                * El Índice de Lixiviación mide la relación entre nutrientes profundos y superficiales.
            </div>
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex bg-white p-1.5 rounded-2xl shadow-md border border-slate-100">
                @foreach(['24h', '7d', '14d', '30d', 'all'] as $f)
                <a href="{{ route('lixiviacion', ['location_id' => $location_id, 'filter' => $f]) }}" 
                   class="filter-btn {{ $filter == $f ? 'active' : '' }}">{{ strtoupper($f) }}</a>
                @endforeach
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="background-color: #dcfce7; border: 1px solid #bbf7d0; color: #16a34a; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 600; font-size: 0.9rem;">{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 700; font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                <span>Por favor corrige los siguientes errores:</span>
            </div>
            <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.85rem; font-weight: 600;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Controls Row --}}
    <div class="flex flex-col lg:flex-row gap-6 mb-10 items-end">
        <div class="flex-grow max-w-xl">
            <form method="GET" action="{{ route('lixiviacion') }}" id="location-form">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">📍 Seleccionar Ubicación</label>
                <select name="location_id" id="location-selector" onchange="this.form.submit()" 
                        class="w-full p-4 bg-white border-2 border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:border-emerald-500 transition-all shadow-sm">
                    <option value="">-- Seleccionar Lote/Ubicación --</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                            {{ $loc->lote->name ?? $loc->name }} — {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
                <div>
                    <label class="section-label mb-2 text-indigo-600">Índice de Lixiviación (Control)</label>
                    <input type="number" step="0.0001" name="ce_reference" id="ilx_control" class="w-full p-2.5 bg-indigo-50 border-2 border-indigo-100 rounded-xl font-mono font-bold text-indigo-700 text-xs outline-none focus:border-indigo-500" placeholder="Referencia" required />
                </div>
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

    {{-- Mirror Cards (Real-time / Manual for Control) --}}
    @if(isset($selectedLocation))
        @php $isCtrl = $selectedLocation->experimental_group === 'control'; @endphp
        <div class="mb-8 p-8 {{ $isCtrl ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200' }} border-2 rounded-3xl shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="p-3 {{ $isCtrl ? 'bg-amber-500' : 'bg-emerald-500' }} text-white rounded-2xl shadow-lg">
                        <i class="fas {{ $isCtrl ? 'fa-user-edit' : 'fa-robot' }}"></i>
                    </span>
                    <div>
                        <h3 class="text-xl font-black {{ $isCtrl ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $isCtrl ? 'Entrada Manual — Grupo Control' : 'Monitoreo Automático — Grupo Experimental' }}
                        </h3>
                        <p class="text-sm font-medium {{ $isCtrl ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $isCtrl ? 'Ingrese las lecturas manuales del conductimetro digital' : 'Datos recolectados por sensores IoT en tiempo real.' }}
                        </p>
                    </div>
                </div>
                @if(!$isCtrl)
                    <div class="px-4 py-2 bg-white rounded-xl border border-emerald-100 flex items-center gap-2">
                        <div class="live-dot"></div>
                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Sensores Activos</span>
                    </div>
                @endif
            </div>

            @if($isCtrl)
                <form action="{{ route('lixiviacion.store_manual') }}" method="POST" id="manual-lix-form" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    @csrf
                    <input type="hidden" name="location_id" value="{{ $location_id }}">
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-amber-600 uppercase mb-2 tracking-widest">CE Superficial (dS/m)</label>
                        <input type="number" step="0.001" name="conductivity_superficial" id="manual-ce-sup" oninput="updateManualCards()"
                               class="w-full p-4 bg-white border-2 border-amber-100 rounded-2xl font-black text-slate-700 outline-none focus:border-amber-500 transition-all shadow-inner" placeholder="0.000" required>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-amber-600 uppercase mb-2 tracking-widest">CE Profunda (dS/m)</label>
                        <input type="number" step="0.001" name="conductivity_profundo" id="manual-ce-prof" oninput="updateManualCards()"
                               class="w-full p-4 bg-white border-2 border-amber-100 rounded-2xl font-black text-slate-700 outline-none focus:border-amber-500 transition-all shadow-inner" placeholder="0.000" required>
                    </div>
                    <div class="md:col-span-2 flex items-end">
                        <button type="submit" class="w-full py-4 bg-amber-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-amber-200 hover:bg-amber-700 transform hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-save"></i> GUARDAR REGISTRO MANUAL
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif



    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        {{-- CE Superficial --}}
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

        {{-- CE Profunda --}}
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

        {{-- ILx --}}
        <div class="glass-card p-8 border-l-4 border-indigo-500" id="card-ilx">
            <div class="kpi-title">Índice de Lixiviación</div>
            <div class="flex items-baseline mb-4">
                <div class="kpi-value" id="kpi-ilx">--</div>
                <div class="kpi-unit">ratio</div>
            </div>
            <div>
                <span class="status-badge bg-indigo-50 text-indigo-600" id="status-ilx">--</span>
            </div>
        </div>
    </div>

    {{-- Charts Block --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="glass-card p-6">
            <h4 class="kpi-title">CE Superficial — Promedio Diario</h4>
            <div style="height:220px;"><canvas id="lxCeSupChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Índice de Lixiviación — Evolución</h4>
            <div style="height:220px;"><canvas id="lxIlxChart"></canvas></div>
        </div>
        <div class="glass-card p-6">
            <h4 class="kpi-title">Registros por Día</h4>
            <div style="height:220px;"><canvas id="lxCountsChart"></canvas></div>
        </div>
    </div>



    {{-- Historical Analysis Table (Ficha de Registro IL) --}}
    <div class="mb-10">
        <h2 class="text-2xl font-black text-slate-800 mb-6 flex items-center gap-3">
            <span class="p-3 bg-indigo-600 text-white rounded-2xl shadow-lg shadow-indigo-200">📊</span>
            Historial de Análisis de Lixiviación
        </h2>
        
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50/50 border-b border-slate-100">
                        <tr>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">Fecha / Hora</th>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">Ubicación</th>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">CE Sup</th>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">CE Prof</th>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">
                                Índice de Lixiviación <br>
                                <span class="text-[8px] text-indigo-400 normal-case">(IL = CE Prof / CE Sup)</span>
                            </th>
                            <th class="px-8 py-5 font-black text-slate-400 uppercase tracking-wider text-[10px]">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($analysisRecords as $record)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5 whitespace-nowrap font-bold text-slate-700">
                                    {{ $record->analyzed_at ? $record->analyzed_at->format('d/m/Y H:i') : '--' }}
                                </td>
                                <td class="px-8 py-5 text-slate-600 font-medium">
                                    {{ $record->location->lote->name ?? '' }} <br>
                                    <span class="text-[10px] text-slate-400 uppercase font-black">{{ $record->location->name ?? '--' }}</span>
                                </td>
                                <td class="px-8 py-5 font-mono font-black text-blue-600">
                                    {{ number_format($record->conductivity_superficial, 3) }}
                                </td>
                                <td class="px-8 py-5 font-mono font-black text-emerald-600">
                                    {{ number_format($record->conductivity_profundo, 3) }}
                                </td>
                                <td class="px-8 py-5">
                                    <span class="font-mono font-black text-indigo-600 text-lg">{{ number_format($record->ilx, 4) }}</span>
                                </td>
                                <td class="px-8 py-5">
                                    @php
                                        $est = $record->ilx_estado ?? '--';
                                        $cls = 'bg-slate-100 text-slate-600';
                                        if (str_contains($est, 'LIXIVI')) $cls = 'bg-red-100 text-red-700';
                                        elseif (str_contains($est, 'EQUILI')) $cls = 'bg-green-100 text-green-700';
                                        elseif (str_contains($est, 'RETEN') || str_contains($est, 'ACUMU')) $cls = 'bg-amber-100 text-amber-700';
                                    @endphp
                                    <span class="status-badge {{ $cls }} shadow-none">{{ $est }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-8 py-20 text-center text-slate-400 font-medium">
                                    <i class="fas fa-search-minus mb-3 block text-4xl opacity-20"></i>
                                    No se encontraron registros en este periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($analysisRecords->hasPages())
                <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100">
                    {{ $analysisRecords->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let locationId = '{{ $location_id }}';
let pollTimer = null;

const THRESHOLDS = {
    ce_sup_max : 0.600,
    ce_prof_max: 0.750,
};

function classifyILx(ilx) {
    if (isNaN(ilx)) return { estado: 'SIN DATOS', icon: '⚪', level: 'none' };
    if (ilx > 1.20) return { estado: 'LIXIVIACIÓN ALTA', icon: '🔴', level: 'crit' };
    if (ilx > 1.05) return { estado: 'LIXIVIACIÓN', icon: '🟠', level: 'warn' };
    if (ilx >= 0.90) return { estado: 'EQUILIBRIO', icon: '✅', level: 'ok' };
    if (ilx >= 0.70) return { estado: 'RETENCIÓN', icon: '🔵', level: 'info' };
    return { estado: 'ACUMULACIÓN', icon: '🟡', level: 'warn' };
}


async function poll() {
    if (!locationId) return;
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
    // CE Sup
    const elSup = document.getElementById('kpi-ce-sup');
    const badgeSup = document.getElementById('status-ce-sup');
    elSup.textContent = isNaN(ce_s) ? '--' : ce_s.toFixed(3);
    if (!isNaN(ce_s)) {
        badgeSup.textContent = ce_s > THRESHOLDS.ce_sup_max ? 'ALERTA' : 'OK';
        badgeSup.className = `status-badge ${ce_s > THRESHOLDS.ce_sup_max ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`;
    }

    // CE Prof
    const elProf = document.getElementById('kpi-ce-prof');
    const badgeProf = document.getElementById('status-ce-prof');
    elProf.textContent = isNaN(ce_p) ? '--' : ce_p.toFixed(3);
    if (!isNaN(ce_p)) {
        badgeProf.textContent = ce_p > THRESHOLDS.ce_prof_max ? 'ALERTA' : 'OK';
        badgeProf.className = `status-badge ${ce_p > THRESHOLDS.ce_prof_max ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`;
    }

    // ILx
    const elIlx = document.getElementById('kpi-ilx');
    const badgeIlx = document.getElementById('status-ilx');
    const cardIlx = document.getElementById('card-ilx');
    elIlx.textContent = isNaN(ilx) ? '--' : ilx.toFixed(4);
    if (!isNaN(ilx)) {
        const cls = classifyILx(ilx);
        badgeIlx.textContent = cls.estado;
        const colorMap = {
            crit: 'bg-red-50 text-red-600',
            warn: 'bg-amber-50 text-amber-600',
            ok: 'bg-green-50 text-green-600',
            info: 'bg-blue-50 text-blue-600',
            none: 'bg-slate-50 text-slate-600'
        };
        badgeIlx.className = `status-badge ${colorMap[cls.level] || colorMap.none}`;
        cardIlx.className = `glass-card p-8 border-l-4 ${cls.level === 'crit' ? 'border-red-500' : (cls.level === 'warn' ? 'border-amber-500' : 'border-indigo-500')}`;
    }
}

function setConn(msg) { document.getElementById('conn-text').textContent = msg; }

const isExperimental = '{{ $selectedLocation->experimental_group ?? "" }}' === 'experimental';

function updateManualCards() {
    const ce_s = parseFloat(document.getElementById('manual-ce-sup')?.value) || NaN;
    const ce_p = parseFloat(document.getElementById('manual-ce-prof')?.value) || NaN;
    const ilx  = (!isNaN(ce_s) && ce_s > 0 && !isNaN(ce_p)) ? ce_p / ce_s : NaN;
    updateCards(ce_s, ce_p, ilx);
    setConn('🟡 Modo Manual');
}

if (locationId) {
    if (isExperimental) {
        poll();
        pollTimer = setInterval(poll, 3000);
    } else {
        setConn('🟡 Modo Manual');
        const firstRow = document.querySelector('tbody tr');
        if (firstRow && !firstRow.querySelector('td[colspan]')) {
            const ce_s = parseFloat(firstRow.cells[2].textContent);
            const ce_p = parseFloat(firstRow.cells[3].textContent);
            const ilx  = parseFloat(firstRow.cells[4].textContent);
            
            const inputSup = document.getElementById('manual-ce-sup');
            const inputProf = document.getElementById('manual-ce-prof');
            if (inputSup) inputSup.value = ce_s;
            if (inputProf) inputProf.value = ce_p;
            
            updateCards(ce_s, ce_p, ilx);
        }
    }
}
</script>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const dates = @json($datesJson ?? '[]');
    const ceSup = @json($ceSupJson ?? '[]');
    const ilx = @json($ilxJson ?? '[]');
    const counts = @json($countsJson ?? '[]');

    const parseDates = JSON.parse(dates);
    const ceData = JSON.parse(ceSup);
    const ilxData = JSON.parse(ilx);
    const cntData = JSON.parse(counts);

    const c1 = document.getElementById('lxCeSupChart');
    if (c1) new Chart(c1, { type:'line', data:{ labels: parseDates, datasets:[{ label:'CE Superficial (dS/m)', data: ceData, borderColor:'#0ea5a0', backgroundColor:'rgba(14,165,160,0.05)', fill:true }]}, options:{ responsive:true, maintainAspectRatio:false } });

    const c2 = document.getElementById('lxIlxChart');
    if (c2) new Chart(c2, { type:'line', data:{ labels: parseDates, datasets:[{ label:'ILx (ratio)', data: ilxData, borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.05)', fill:true }]}, options:{ responsive:true, maintainAspectRatio:false } });

    const c3 = document.getElementById('lxCountsChart');
    if (c3) new Chart(c3, { type:'bar', data:{ labels: parseDates, datasets:[{ label:'Registros', data: cntData, backgroundColor:'#4f46e5' }]}, options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, stepSize:1 } } } });
});
</script>
@endpush
