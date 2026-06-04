@extends('layouts.app')
@section('title', 'Tiempo de Detección — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.3);
        --accent-green: #16a34a;
        --accent-blue: #3b82f6;
        --accent-purple: #9333ea;
    }

    .page-header { 
        margin-bottom: 2rem; 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end;
    }
    .page-header h1 { margin: 0; font-size: 1.8rem; font-weight: 800; color: #1a472a; letter-spacing: -0.02em; }
    .page-header p  { margin: 0.25rem 0 0; font-size: 0.95rem; color: #6b7280; }

    /* Glass Cards */
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

    .empty-state { padding: 4rem; text-align: center; }
    .empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block; }
    .empty-state p { color: #9ca3af; font-size: 1rem; }

    /* Badges y estilos adicionales */
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

    /* Paginación */
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
</style>

<div class="page-header">
    <div>
        <h1>⏱️ Tiempo de Detección</h1>
        <p>Análisis del tiempo promedio de respuesta en detección de eventos</p>
    </div>
    <div style="display:flex; gap: 0.75rem;">
        <button onclick="openManualModal()" class="btn btn-success shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem; background: var(--accent-green); color: white; border: none; padding: 0 1rem; display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <i class="fas fa-plus"></i> Ingresar Datos Manuales
        </button>
        <a href="{{ route('detection_time.export', ['location_id' => $location_id, 'filter' => $filter]) }}" class="btn btn-light shadow-sm" style="border-radius:10px; font-weight:600; font-size:0.85rem; text-decoration: none; display: flex; align-items: center; justify-content: center; padding: 0.5rem 1rem;">
            <i class="fas fa-download"></i> Descargar
        </a>
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
            {{ $unique_days > 0 && $total_alerts > 0 ? round(collect($detectionRecords->items())->avg('tiempo_promedio'), 2) : '--' }}s
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

<div class="glass-card mb-12">
    <div class="filter-section rounded-t-2xl">
        <div class="filter-group">
            <label>Ubicación</label>
                <select id="f-location" onchange="changeFilter('location')">
                    @foreach($locations as $loc)

                        @if(in_array($loc->id, [3, 4]))

                            @php
                                $label = match (true) {
                                    $loc->id == 3 => 'Planta de Palto - GC',
                                    $loc->id == 4 => 'Planta de Palto - GE',
                                    $loc->experimental_group === 'control' => 'Planta de Palto - GC',
                                    $loc->experimental_group === 'experimental' => 'Planta de Palto - GE',
                                    default => ($loc->lote->name ?? 'Sin Lote') . ' — ' . ($loc->name ?? ''),
                                };
                            @endphp

                            <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                                {{ $label }}
                            </option>

                        @endif

                    @endforeach
                </select>
        </div>
        <div class="filter-group">
            <label>Período</label>
            <select id="f-filter" onchange="changeFilter('filter')">
                <option value="24h" {{ $filter === '24h' ? 'selected' : '' }}>Últimas 24 horas</option>
                <option value="7d" {{ $filter === '7d' ? 'selected' : '' }}>Últimos 7 días</option>
                <option value="14d" {{ $filter === '14d' ? 'selected' : '' }}>Últimos 14 días</option>
                <option value="30d" {{ $filter === '30d' ? 'selected' : '' }}>Últimos 30 días</option>
                <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Todos los registros</option>
            </select>
        </div>
        <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
            <span class="px-3 py-1 bg-slate-100 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest">
                {{ count($detectionRecords->items()) }} Días
            </span>
        </div>
    </div>

    <div class="table-container">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-6 py-4">Día</th>
                    <th class="px-6 py-4">Fecha</th>
                    <th class="px-6 py-4">Tiempo Inicial (Ti)</th>
                    <th class="px-6 py-4">Tiempo Final (Tf)</th>
                    <th class="px-6 py-4">Planta de Palto</th>
                    <th class="px-6 py-4">Tiempo Promedio</th>
                    <th class="px-6 py-4 text-center font-medium">Eventos</th>
                </tr>
            </thead>
            <tbody id="detection-body">
                @if(count($detectionRecords->items()) > 0)
                    @foreach($detectionRecords->items() as $index => $day)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #374151;">
                                    {{ $detectionRecords->total() - ($detectionRecords->firstItem() + $index) + 1 }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #1a472a;">{{ \Carbon\Carbon::parse($day['fecha'])->format('d/m/Y') }}</div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; color: #6b7280;">{{ \Carbon\Carbon::parse($day['tiempo_inicial'])->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; color: #6b7280;">{{ \Carbon\Carbon::parse($day['tiempo_final'])->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #9333ea;">{{ $day['subparcela'] }}</div>
                            </td>
                            <td>
                                <div style="font-family: monospace; font-weight: 800; color: #16a34a; font-size: 0.95rem;">
                                    {{ number_format($day['tiempo_promedio'], 2) }}s
                                </div>
                                <div style="font-size: 0.7rem; color: #9ca3af;">
                                    (~{{ round($day['tiempo_promedio'] / 60, 2) }} min)
                                </div>
                            </td>
                            <td>
                                <div class="px-6 py-4 text-center font-medium">{{ $day['cantidad_eventos'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-center" style="display: none;">
                                @if(isset($day['tipo_entrada']) && $day['tipo_entrada'] === 'manual')
                                    @php $recId = isset($day['id']) ? $day['id'] : $day['numero']; @endphp
                                    <button class="text-blue-500 hover:text-blue-700" title="Modificar"><i class="fas fa-edit"></i></button>
                                @else
                                    <span class="text-gray-400" title="Registro automático no modificable">-</span>
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
    </div>

    @if($detectionRecords->hasPages())
        <div class="pagination">
            {{-- Botón anterior --}}
            @if($detectionRecords->onFirstPage())
                <span class="disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
            @else
                <a href="{{ $detectionRecords->previousPageUrl() }}"><i class="fas fa-chevron-left"></i> Anterior</a>
            @endif

            {{-- Links de página --}}
            @foreach($detectionRecords->getUrlRange(1, $detectionRecords->lastPage()) as $page => $url)
                @if($page == $detectionRecords->currentPage())
                    <span class="active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Botón siguiente --}}
            @if($detectionRecords->hasMorePages())
                <a href="{{ $detectionRecords->nextPageUrl() }}">Siguiente <i class="fas fa-chevron-right"></i></a>
            @else
                <span class="disabled">Siguiente <i class="fas fa-chevron-right"></i></span>
            @endif
        </div>
    @endif
</div>

<!-- Modal de Registro Manual -->
<div id="modalManual" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⏱️ Registrar TPD Manual</h2>
            <button onclick="closeManualModal()" class="btn btn-link text-muted" style="padding:0; border: none; background: transparent; cursor: pointer; font-size: 1.25rem;"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('detection_time.store_manual') }}" method="POST" id="manual-form">
            @csrf
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Subparcela (Control)</label>
                <select name="location_id" class="w-full" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                    <option value="">Seleccione una subparcela de control...</option>
                    @foreach($locations as $loc)
                        @if($loc->experimental_group === 'control' && in_array($loc->id, [3, 4]))
                        @php
                            $label = match ($loc->id) {
                                3 => 'Planta de Palto - GC (Control)',
                                4 => 'Planta de Palto - GE (Experimental)',
                                default => ($loc->lote->name ?? 'Sin Lote') . ' — ' . ($loc->name ?? ''),
                            };
                        @endphp

                        <option value="{{ $loc->id }}" {{ $location_id == $loc->id ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Subparcela (ej: S1, S2, S3...)</label>
                <input type="text" name="subparcela" value="{{ old('subparcela') }}" required placeholder="ej: S1" pattern="[Ss]\d+" title="Debe usar la letra 'S' seguida de un número (ej. S1, S2)" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                @error('subparcela') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Fecha del Evento</label>
                <input type="date" name="fecha" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;" value="{{ date('Y-m-d') }}">
            </div>
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="flex: 1;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Hora de Alerta (Ti)</label>
                    <input type="time" step="1" name="hora_alerta" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Hora de Evento (Tf)</label>
                    <input type="time" step="1" name="hora_evento" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                </div>
            </div>
            <div style="margin-top: 2rem; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success" style="flex: 1; border-radius: 12px; font-weight: 700; padding: 0.75rem; background: var(--accent-green); color: white; border: none; cursor: pointer;">Guardar Registro</button>
                <button type="button" onclick="closeManualModal()" class="btn btn-light" style="flex: 1; border-radius: 12px; font-weight: 700; padding: 0.75rem; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; color: #374151;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edición Manual -->
<div id="modalEdit" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Modificar TPD Manual</h2>
            <button onclick="closeEditModal()" class="btn btn-link text-muted" style="padding:0; border: none; background: transparent; cursor: pointer; font-size: 1.25rem;"><i class="fas fa-times"></i></button>
        </div>
        <form action="#" method="POST" id="edit-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="record_id" id="edit-record-id">
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Subparcela</label>
                <input type="text" name="subparcela" id="edit-subparcela" required pattern="[Ss]\d+" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Fecha del Evento</label>
                <input type="date" name="fecha" id="edit-fecha" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
            </div>
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="flex: 1;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Hora de Alerta (Ti)</label>
                    <input type="time" step="1" name="hora_alerta" id="edit-ti" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Hora de Evento (Tf)</label>
                    <input type="time" step="1" name="hora_evento" id="edit-tf" required style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 0.85rem; background: #fff; outline: none;">
                </div>
            </div>
            <div style="margin-top: 2rem; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 12px; font-weight: 700; padding: 0.75rem; background: var(--accent-blue); color: white; border: none; cursor: pointer;">Actualizar Registro</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-light" style="flex: 1; border-radius: 12px; font-weight: 700; padding: 0.75rem; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; color: #374151;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function changeFilter(type) {
    const locationId = document.getElementById('f-location').value;
    const filter = document.getElementById('f-filter').value;
    
    let url = '{{ route('detection_time') }}';
    const params = new URLSearchParams();
    
    if (locationId) params.append('location_id', locationId);
    params.append('filter', filter);
    
    window.location.href = url + (params.toString() ? '?' + params.toString() : '');
}

function openManualModal() {
    document.getElementById('modalManual').style.display = 'flex';
}

function closeManualModal() {
    document.getElementById('modalManual').style.display = 'none';
}

function openEditModal(id, subparcela, fecha, ti, tf) {
    document.getElementById('edit-record-id').value = id;
    document.getElementById('edit-subparcela').value = subparcela;
    
    // Parse the date (yyyy-mm-dd format expected for date input)
    let d = new Date(fecha);
    let dateString = d.toISOString().split('T')[0];
    document.getElementById('edit-fecha').value = dateString;
    
    document.getElementById('edit-ti').value = ti;
    document.getElementById('edit-tf').value = tf;
    
    // Note: To make this functional backend-wise, set form action here
    // document.getElementById('edit-form').action = '/detection-time/manual/' + id;
    
    document.getElementById('modalEdit').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('modalEdit').style.display = 'none';
}
</script>
@endpush

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const dates = @json($datesJson ?? '[]');
    const avgTimes = @json($avgTimesJson ?? '[]');
    const events = @json($eventsJson ?? '[]');
    const manual = {{ $manualCount ?? 0 }};
    const automatic = {{ $automaticCount ?? 0 }};

    // Avg Time Line
    const ctx1 = document.getElementById('dtAvgTimeChart');
    if (ctx1) new Chart(ctx1, {
        type: 'line',
        data: { labels: JSON.parse(dates), datasets: [{ label: 'Tiempo promedio (s)', data: JSON.parse(avgTimes), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.05)', fill:true, tension:0.25 }] },
        options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
    });

    // Events Bar
    const ctx2 = document.getElementById('dtEventsChart');
    if (ctx2) new Chart(ctx2, {
        type: 'bar',
        data: { labels: JSON.parse(dates), datasets: [{ label: 'Eventos', data: JSON.parse(events), backgroundColor:'#3b82f6' }] },
        options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, stepSize:1 } } }
    });

    // Manual vs Auto Doughnut
    const ctx3 = document.getElementById('dtManualAutoChart');
    if (ctx3) new Chart(ctx3, {
        type: 'doughnut',
        data: { labels:['Manual','Automático'], datasets:[{ data:[manual, automatic], backgroundColor:['#f59e0b','#64748b'] }] },
        options: { responsive:true, maintainAspectRatio:false, cutout:'60%' }
    });
});
</script>
