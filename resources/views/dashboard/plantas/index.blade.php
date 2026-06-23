@extends('layouts.app')

@section('title', 'Plantas')

@section('content')

<style>
    .page-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:1.5rem;
        flex-wrap:wrap;
        gap:1rem;
    }

    .page-header h1 {
        margin:0;
        font-size:1.4rem;
        font-weight:700;
        color:#1a472a;
    }

    .btn-primary {
        padding:0.6rem 1.25rem;
        background:linear-gradient(135deg,#16a34a,#15803d);
        color:#fff;
        border:none;
        border-radius:8px;
        font-weight:700;
        font-size:0.85rem;
        text-decoration:none;
    }

    .filter-bar {
        margin-bottom:20px;
    }

    .filter-select {
        padding:8px 12px;
        border:1px solid #d1d5db;
        border-radius:8px;
        background:#fff;
    }

    .lotes-grid {
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
        gap:1.25rem;
    }

    .lote-card {
        background:#fff;
        border-radius:10px;
        box-shadow:0 1px 6px rgba(0,0,0,0.07);
        border-left:4px solid #16a34a;
        padding:1.25rem;
    }

    .lote-card.grupo-control {
        border-left-color: #d97706;
    }

    .lote-name {
        font-size:1rem;
        font-weight:700;
        color:#1a472a;
    }

    .lote-meta {
        font-size:0.85rem;
        color:#6b7280;
        margin:0.25rem 0;
    }

    .device-code-tag {
        display:inline-block;
        background:#dbeafe;
        color:#1e40af;
        border:1px solid #bfdbfe;
        padding:2px 10px;
        border-radius:20px;
        font-size:0.75rem;
        font-weight:700;
        font-family:monospace;
        margin-top:4px;
    }

    .badge {
        display:inline-block;
        padding:0.2rem 0.5rem;
        border-radius:6px;
        font-size:0.75rem;
        font-weight:600;
    }

    .badge-control {
        background:#dcfce7;
        color:#166534;
    }

    .badge-exp {
        background:#dbeafe;
        color:#1e3a8a;
    }
</style>

<div class="page-header">
    <div>
        <h1>🌾 Plantas de palto registradas</h1>
        <p>Listado del sistema</p>
    </div>

    <a href="{{ route('plantas.create') }}" class="btn-primary">
        ➕ Nueva Planta
    </a>
</div>

@if(session('success'))
    <div style="color:#166534;font-weight:600;margin-bottom:1rem;background:#dcfce7;padding:0.75rem 1rem;border-radius:8px;">
        {{ session('success') }}
    </div>
@endif

<div class="filter-bar">
    <form method="GET" action="{{ route('plantas.index') }}">
        <label for="grupo"><strong>Filtrar por grupo:</strong></label>

        <select
            name="grupo"
            id="grupo"
            class="filter-select"
            onchange="this.form.submit()"
        >
            <option value="">Todos</option>

            <option value="GC"
                {{ request('grupo') == 'GC' ? 'selected' : '' }}>
                🟢 GC — Grupo Control
            </option>

            <option value="GE"
                {{ request('grupo') == 'GE' ? 'selected' : '' }}>
                🔵 GE — Grupo Experimental
            </option>
        </select>
    </form>
</div>

@if($plantas->count())

<div class="lotes-grid">

@foreach($plantas as $planta)

@php $ubicacion = $planta->ubicaciones->first(); @endphp

<div class="lote-card {{ $planta->grupo_experimental === 'control' ? 'grupo-control' : '' }}">

    <div class="lote-name">
        🌳 {{ $planta->nombre }}
    </div>

    <div class="lote-meta">
        <strong>N° planta:</strong> {{ $planta->numero_planta }}
    </div>

    <div class="lote-meta">
        Grupo:
        @if($planta->grupo_experimental === 'control')
            <span class="badge badge-control">🟢 GC — Control</span>
        @else
            <span class="badge badge-exp">🔵 GE — Experimental</span>
        @endif
    </div>

    <div class="lote-meta">
        📍 Ubicación: {{ $ubicacion?->nombre ?? 'Sin ubicación' }}
    </div>

    @if($planta->grupo_experimental === 'experimental')
        <div class="lote-meta">
            📡 Device Code:
            @if($ubicacion?->codigo_dispositivo)
                <span class="device-code-tag">{{ $ubicacion->codigo_dispositivo }}</span>
            @else
                <span style="color:#ef4444;font-weight:600;">Sin código asignado</span>
            @endif
        </div>
    @endif

    <div style="margin-top:15px;display:flex;gap:8px;">
        <a href="{{ route('plantas.edit', $planta->id) }}"
           class="btn-primary"
           style="display:block;text-align:center;flex:1;">
            ✏️ Editar
        </a>
        <form method="POST" action="{{ route('plantas.destroy', $planta->id) }}" onsubmit="return confirm('¿Eliminar esta planta?')">
            @csrf
            @method('DELETE')
            <button type="submit" style="padding:0.6rem 1rem;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;font-weight:700;font-size:0.85rem;cursor:pointer;">
                🗑
            </button>
        </form>
    </div>

</div>

@endforeach

</div>

@else

<div style="padding:3rem;text-align:center;color:#9ca3af;">
    <p style="font-size:1.1rem;">No hay plantas registradas para el filtro seleccionado.</p>
    <a href="{{ route('plantas.create') }}" class="btn-primary" style="margin-top:1rem;display:inline-block;">➕ Crear primera planta</a>
</div>

@endif

@endsection
