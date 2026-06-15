@extends('layouts.app')

@section('title', 'Editar Planta')

@section('content')

<style>
    .form-wrap { max-width:560px; margin:0 auto; }
    .page-header { margin-bottom:1.5rem; }
    .page-header h1 { margin:0; font-size:1.4rem; font-weight:700; color:#1a472a; }
    .page-header p  { margin:0.25rem 0 0; font-size:0.85rem; color:#6b7280; }

    .form-card {
        background:#fff;
        border-radius:10px;
        box-shadow:0 1px 6px rgba(0,0,0,0.07);
        padding:1.75rem;
    }

    .field { margin-bottom:1.25rem; }

    .field label {
        display:block;
        font-size:0.78rem;
        font-weight:700;
        color:#6b7280;
        text-transform:uppercase;
        margin-bottom:0.4rem;
    }

    .field input, .field select {
        width:100%;
        padding:0.65rem 0.85rem;
        border:1.5px solid #d1d5db;
        border-radius:7px;
        font-size:0.9rem;
        background:#f9fafb;
        outline:none;
    }

    .field input:focus, .field select:focus {
        border-color:#16a34a;
        background:#fff;
    }

    .btn-submit {
        width:100%;
        padding:0.7rem;
        background:linear-gradient(135deg,#16a34a,#15803d);
        color:#fff;
        border:none;
        border-radius:8px;
        font-weight:700;
        cursor:pointer;
    }
</style>

<div class="form-wrap">

    <div class="page-header">
        <h1>✏️ Editar Planta</h1>
        <p>Modificar datos del lote</p>
    </div>

    <div class="form-card">

        <form method="POST" action="{{ route('lotes.update', $lote) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Número de planta</label>
                <input type="number"
                    name="plant_number"
                    min="1"
                    max="30"
                    value="{{ old('plant_number', $lote->plant_number) }}"
                    required>
            </div>

            <div class="field">
                <label>Grupo</label>
                <select name="experimental_group" required>

                    <option value="GC"
                        {{ $lote->experimental_group == 'control' ? 'selected' : '' }}>
                        Control (GC)
                    </option>

                    <option value="GE"
                        {{ $lote->experimental_group == 'experimental' ? 'selected' : '' }}>
                        Experimental (GE)
                    </option>

                </select>
            </div>

            <div class="field">
                <label>Nombre</label>
                <input type="text"
                    name="name"
                    value="{{ old('name', $lote->name) }}"
                    required>
            </div>

            <div class="field">
                <label>Cultivo</label>
                <select name="crop_type">

                    <option value="palta"
                        {{ $lote->crop_type == 'palta' ? 'selected' : '' }}>
                        Palta
                    </option>

                </select>
            </div>

            <!-- SOLO LECTURA (opcional visual) -->
            <div class="field">
                <label>Ubicación (fija)</label>
                <input type="text"
                    value="{{ $lote->locations->first()->latitude ?? '' }}, {{ $lote->locations->first()->longitude ?? '' }}"
                    disabled>
            </div>

            <button type="submit" class="btn-submit">
                Guardar cambios
            </button>

        </form>

    </div>
</div>

@endsection