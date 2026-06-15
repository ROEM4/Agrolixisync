@extends('layouts.app')

@section('title', 'Lotes')

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

    .pagination-wrapper {
        margin-top: 20px;
    }
</style>

<div class="page-header">
    <div>
        <h1>🌾 Plantas registradas</h1>
        <p>Listado del sistema</p>
    </div>

    <a href="{{ route('lotes.create') }}" class="btn-primary">
        ➕ Nueva Planta
    </a>
</div>

@if(session('success'))
    <div style="color:#166534;font-weight:600;margin-bottom:1rem;">
        {{ session('success') }}
    </div>
@endif

<div class="filter-bar">
    <form method="GET" action="{{ route('lotes.index') }}">
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
                GC
            </option>

            <option value="GE"
                {{ request('grupo') == 'GE' ? 'selected' : '' }}>
                GE
            </option>
        </select>
    </form>
</div>

@if($lotes->count())

<div class="lotes-grid">

@foreach($lotes as $lote)

<div class="lote-card">

    <div class="lote-name">
        {{ $lote->name }}
    </div>

    <div class="lote-meta">
        N° planta: {{ $lote->plant_number }}
    </div>

    <div class="lote-meta">
        Grupo:

        @if($lote->experimental_group === 'control')
            <span class="badge badge-control">🟢 GC</span>
        @else
            <span class="badge badge-exp">🔵 GE</span>
        @endif
    </div>

    <div class="lote-meta">
        🌱 Cultivo: {{ $lote->crop_type }}
    </div>

    <div class="lote-meta">
        📍 Ubicación: {{ $lote->locations->first()->name ?? 'Sin ubicación' }}
    </div>
    <div class="lote-meta">
        🏷️ Device Code: {{ $lote->locations->first()->device_code ?? 'N/A' }}
    </div>

    <!-- ✅ BOTÓN EDITAR AGREGADO -->
    <div style="margin-top:15px;">
        <a href="{{ route('lotes.edit', $lote->id) }}"
           class="btn-primary"
           style="display:block;text-align:center;">
            ✏️ Editar planta
        </a>
    </div>

</div>

@endforeach

</div>

<div class="pagination-wrapper">
    {{ $lotes->links() }}
</div>

@else

<p>No hay plantas registradas para el filtro seleccionado.</p>

@endif

@endsection