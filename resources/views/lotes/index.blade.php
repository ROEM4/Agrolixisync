@extends('layouts.app')
@section('title', 'Lotes — AgroLixiSync')

@section('content')
<style>
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
    .page-header h1 { margin:0; font-size:1.4rem; font-weight:700; color:#1a472a; }
    .page-header p  { margin:0.25rem 0 0; font-size:0.85rem; color:#6b7280; }
    .btn-primary {
        padding:0.6rem 1.25rem;
        background:linear-gradient(135deg,#16a34a,#15803d);
        color:#fff; border:none; border-radius:8px;
        font-weight:700; font-size:0.85rem; text-decoration:none;
        display:inline-flex; align-items:center; gap:0.4rem;
    }
    .btn-primary:hover { opacity:0.9; color:#fff; }

    .alert-success {
        background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;
        padding:0.85rem 1.1rem; margin-bottom:1.25rem;
        color:#166534; font-size:0.85rem; font-weight:600;
    }

    .lotes-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1.25rem; }

    .lote-card {
        background:#fff; border-radius:10px;
        box-shadow:0 1px 6px rgba(0,0,0,0.07);
        border-left:4px solid #16a34a;
        padding:1.25rem;
        transition:box-shadow 0.2s, transform 0.2s;
    }
    .lote-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.12); transform:translateY(-2px); }

    .lote-card .lote-name { font-size:1rem; font-weight:700; color:#1a472a; margin:0 0 0.25rem; }
    .lote-card .lote-crop { font-size:0.8rem; color:#6b7280; margin:0; }
    .lote-card .lote-desc { font-size:0.83rem; color:#555; margin:0.75rem 0; line-height:1.5; }

    .lote-meta { font-size:0.72rem; color:#9ca3af; margin-top:0.75rem; }

    .locations-list { margin-top:0.75rem; padding-top:0.75rem; border-top:1px solid #f3f4f6; }
    .locations-list .loc-label { font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:0.4rem; }
    .locations-list ul { margin:0; padding:0; list-style:none; }
    .locations-list li { font-size:0.8rem; color:#6b7280; padding:0.15rem 0; }

    .lote-actions { margin-top:1rem; display:flex; gap:0.5rem; }
    .btn-sm-dash {
        flex:1; text-align:center;
        padding:0.45rem 0.75rem;
        background:#0ea5e9; color:#fff;
        border:none; border-radius:6px;
        font-size:0.78rem; font-weight:600;
        text-decoration:none;
    }
    .btn-sm-dash:hover { opacity:0.9; color:#fff; }

    .empty-state {
        text-align:center; padding:3rem 1rem;
        background:linear-gradient(135deg,#f0fdf4,#dbeafe);
        border:2px dashed #16a34a; border-radius:12px;
    }
    .empty-state .icon { font-size:3rem; margin-bottom:0.75rem; }
    .empty-state h2 { margin:0 0 0.5rem; font-size:1.2rem; font-weight:700; color:#1a472a; }
    .empty-state p  { margin:0 0 1.25rem; color:#6b7280; font-size:0.9rem; }
</style>

<div class="page-header">
    <div>
        <h1>🌾 Gestión de Lotes</h1>
        <p>Parcelas de cultivo y puntos de monitoreo</p>
    </div>
    <a href="{{ route('lotes.create') }}" class="btn-primary">➕ Nuevo Lote</a>
</div>

@if(session('success'))
    <div class="alert-success">✅ {{ session('success') }}</div>
@endif

@if($lotes->count() > 0)
    <div class="lotes-grid">
        @foreach($lotes as $lote)
        @if(in_array($lote->name, ['LOTE-01', 'Auto - ESP32-G1']))
        <div class="lote-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p class="lote-name">{{ $lote->name }}</p>
                    <p class="lote-crop">
                        @switch($lote->crop_type)
                            @case('palta')    🥑 Palta @break
                            @case('citricos') 🍊 Cítricos @break
                            @case('frutilla') 🍓 Frutilla @break
                            @default          📌 {{ ucfirst($lote->crop_type ?? 'otro') }}
                        @endswitch
                    </p>
                </div>
                <span style="font-size:1.8rem;">🌾</span>
            </div>

            @if($lote->description)
                <p class="lote-desc">{{ $lote->description }}</p>
            @endif

            <div class="locations-list">
                <div class="loc-label">📍 Ubicaciones ({{ $lote->locations->count() }})</div>
                @if($lote->locations->count())
                    <ul>
                        @foreach($lote->locations as $loc)
                            <li>• {{ $loc->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <span style="font-size:0.8rem;color:#9ca3af;font-style:italic;">Sin ubicaciones</span>
                @endif
            </div>

            <div class="lote-meta">Creado: {{ $lote->created_at->format('d/m/Y H:i') }}</div>

            <div class="lote-actions">
                @if($lote->locations->first())
                <a href="{{ route('dashboard') }}?location={{ $lote->locations->first()->id }}"
                   class="btn-sm-dash">📊 Ver en Dashboard</a>
                @endif
            </div>
        </div>
        @endif
        @endforeach
    </div>

    @if($lotes->hasPages())
        <div style="margin-top:1.5rem;display:flex;justify-content:center;">
            {{ $lotes->links() }}
        </div>
    @endif

@else
    <div class="empty-state">
        <div class="icon">🌾</div>
        <h2>No hay lotes configurados</h2>
        <p>Crea tu primer lote para comenzar el monitoreo de lixiviación</p>
        <a href="{{ route('lotes.create') }}" class="btn-primary">➕ Crear Primer Lote</a>
    </div>
@endif
@endsection
