@extends('layouts.app')

@section('title', 'Nueva Planta')

@section('content')

<style>
    /* 👇 MISMO DISEÑO CONSERVADO */
    .form-wrap { max-width:560px; margin:0 auto; }
    .page-header { margin-bottom:1.5rem; }
    .page-header h1 { margin:0; font-size:1.4rem; font-weight:700; color:#1a472a; }
    .page-header p  { margin:0.25rem 0 0; font-size:0.85rem; color:#6b7280; }

    .form-card {
        background:#fff; border-radius:10px;
        box-shadow:0 1px 6px rgba(0,0,0,0.07);
        padding:1.75rem;
    }

    .field { margin-bottom:1.25rem; }
    .field label {
        display:block; font-size:0.78rem; font-weight:700;
        color:#6b7280; text-transform:uppercase;
        margin-bottom:0.4rem;
    }

    .field input, .field select, .field textarea {
        width:100%; padding:0.65rem 0.85rem;
        border:1.5px solid #d1d5db; border-radius:7px;
        font-size:0.9rem; background:#f9fafb;
        transition:border-color 0.2s; outline:none;
        font-family:inherit;
    }

    .field input:focus, .field select:focus, .field textarea:focus {
        border-color:#16a34a; background:#fff;
    }

    .actions {
        display:flex; gap:0.75rem; margin-top:1.5rem;
    }

    .btn-submit {
        flex:1; padding:0.7rem;
        background:linear-gradient(135deg,#16a34a,#15803d);
        color:#fff; border:none; border-radius:8px;
        font-weight:700; font-size:0.9rem;
        cursor:pointer;
    }
</style>

<div class="form-wrap">

    <div class="page-header">
        <h1>🌱 Registrar Planta</h1>
        <p>Formulario simplificado de registro</p>
    </div>

    <div class="form-card">

        <form method="POST" action="{{ route('lotes.store') }}">
            @csrf

            <div class="field">
                <label>Número de planta</label>
                <input type="number" name="plant_number" min="1" max="30" required>
            </div>

            <div class="field">
                <label>Grupo</label>
                <select name="experimental_group" required>
                    <option value="">Seleccionar</option>
                    <option value="control">🟢 Control (GC)</option>
                    <option value="experimental">🔵 Experimental (GE)</option>
                </select>
            </div>

            <div class="field">
                <label>Nombre</label>
                <input type="text" name="name" required>
            </div>

            <div class="field">
                <label>Cultivo</label>
                <select name="crop_type">
                    <option value="palta">Palta</option>
                </select>
            </div>

            <div class="field">
                <label>Latitud</label>
                <input type="number" step="any" name="latitude" required>
            </div>

            <div class="field">
                <label>Longitud</label>
                <input type="number" step="any" name="longitude" required>
            </div>

            <div class="actions">
                <button type="submit" class="btn-submit">
                    Crear planta
                </button>
            </div>

        </form>

    </div>
</div>

@endsection