@extends('layouts.app')

@section('title', 'Nueva Planta')

@section('content')

<style>
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

    .field input:focus, .field select:focus {
        border-color:#16a34a; background:#fff;
    }

    .field-info {
        font-size:0.72rem; color:#9ca3af; margin-top:0.3rem;
    }

    .device-code-field {
        display:none;
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
        <p>Formulario de registro de planta de palto</p>
    </div>

    @if($errors->any())
        <div style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;padding:0.75rem 1rem;border-radius:7px;margin-bottom:1rem;font-size:0.85rem;">
            @foreach($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="form-card">

        <form method="POST" action="{{ route('plantas.store') }}" id="plantaForm">
            @csrf

            <div class="field">
                <label>Número de planta</label>
                <input type="number" name="numero_planta" min="1" max="999"
                       value="{{ old('numero_planta') }}" required
                       placeholder="Ej: 1, 2, 3...">
                <div class="field-info">Debe ser un número único y creciente.</div>
            </div>

            <div class="field">
                <label>Nombre</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required
                       placeholder="Ej: Planta Palto A-01">
            </div>

            <div class="field">
                <label>Grupo</label>
                <select name="grupo_experimental" id="grupoSelect" required onchange="toggleDeviceCode()">
                    <option value="">Seleccionar</option>
                    <option value="control"      {{ old('grupo_experimental') == 'control'      ? 'selected' : '' }}>🟢 Control (GC)</option>
                    <option value="experimental" {{ old('grupo_experimental') == 'experimental' ? 'selected' : '' }}>🔵 Experimental (GE)</option>
                </select>
            </div>

            {{-- Device Code: solo para Grupo Experimental --}}
            <div class="field device-code-field" id="deviceCodeField">
                <label>📡 Device Code (IoT)</label>
                <input type="text" name="device_code" id="deviceCodeInput"
                       value="{{ old('device_code') }}"
                       placeholder="Ej: AGR-001, AGR-002...">
                <div class="field-info">Código único del dispositivo IoT asignado a esta planta.</div>
            </div>

            <div class="field">
                <label>📍 Ubicación / Nombre del punto</label>
                <input type="text" name="ubicacion_nombre" value="{{ old('ubicacion_nombre') }}"
                       required placeholder="Ej: Sector Norte, Parcela 1A...">
                <div class="field-info">Nombre descriptivo del lugar donde está la planta.</div>
            </div>

            <div class="field">
                <label>Cultivo</label>
                <select name="tipo_cultivo">
                    <option value="palta">Palta</option>
                </select>
            </div>

            <div class="actions">
                <a href="{{ route('plantas.index') }}" style="flex:1;padding:0.7rem;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-weight:700;font-size:0.9rem;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;">
                    Cancelar
                </a>
                <button type="submit" class="btn-submit">
                    Crear planta
                </button>
            </div>

        </form>

    </div>
</div>

<script>
function toggleDeviceCode() {
    const grupo = document.getElementById('grupoSelect').value;
    const field = document.getElementById('deviceCodeField');
    const input = document.getElementById('deviceCodeInput');
    if (grupo === 'experimental') {
        field.style.display = 'block';
        input.required = true;
    } else {
        field.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
// Ejecutar al cargar si hay valor previo (old input)
document.addEventListener('DOMContentLoaded', toggleDeviceCode);
</script>

@endsection
