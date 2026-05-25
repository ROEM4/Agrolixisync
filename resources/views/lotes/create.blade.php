@extends('layouts.app')
@section('title', 'Nuevo Lote — AgroLixiSync')

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
        color:#6b7280; text-transform:uppercase; letter-spacing:0.04em;
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
    .field .hint  { font-size:0.75rem; color:#9ca3af; margin-top:0.3rem; }
    .field .error { font-size:0.78rem; color:#dc2626; margin-top:0.3rem; }

    .error-box {
        background:#fef2f2; border:1px solid #fecaca; border-radius:8px;
        padding:0.85rem 1rem; margin-bottom:1.25rem;
        color:#dc2626; font-size:0.82rem;
    }

    .info-box {
        background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;
        padding:0.85rem 1rem; margin-bottom:1.25rem;
        color:#1e40af; font-size:0.82rem; display:flex; gap:0.6rem;
    }

    .actions { display:flex; gap:0.75rem; margin-top:1.5rem; }
    .btn-submit {
        flex:1; padding:0.7rem;
        background:linear-gradient(135deg,#16a34a,#15803d);
        color:#fff; border:none; border-radius:8px;
        font-weight:700; font-size:0.9rem; cursor:pointer;
    }
    .btn-submit:hover { opacity:0.9; }
    .btn-cancel {
        flex:1; padding:0.7rem;
        background:#f3f4f6; color:#374151;
        border:none; border-radius:8px;
        font-weight:600; font-size:0.9rem;
        text-decoration:none; text-align:center;
    }
    .btn-cancel:hover { background:#e5e7eb; color:#374151; }
</style>

<div class="form-wrap">
    <div class="page-header">
        <h1>🌾 Crear Nuevo Lote</h1>
        <p>Registra una parcela de cultivo para monitoreo</p>
    </div>

    @if($errors->any())
        <div class="error-box">
            @foreach($errors->all() as $e) <div>⚠ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="form-card">
        <form action="{{ route('lotes.store') }}" method="POST">
            @csrf

            <div class="field">
                <label>Nombre del Lote <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       placeholder="Ej: Lote A, Parcela Norte...">
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Tipo de Cultivo <span style="color:#ef4444;">*</span></label>
                <select name="crop_type" required>
                    <option value="">-- Seleccionar --</option>
                    @foreach($cropTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('crop_type') == $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('crop_type') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Descripción <span style="color:#9ca3af;font-weight:400;">(Opcional)</span></label>
                <textarea name="description" rows="3"
                          placeholder="Ubicación, tamaño, notas adicionales...">{{ old('description') }}</textarea>
                <div class="hint">Máximo 1000 caracteres</div>
                @error('description') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="info-box">
                <span>ℹ️</span>
                <span>Se creará automáticamente una ubicación por defecto. Podrás agregar sensores desde el dashboard.</span>
            </div>

            <div class="actions">
                <a href="{{ route('lotes.index') }}" class="btn-cancel">← Cancelar</a>
                <button type="submit" class="btn-submit">✅ Crear Lote</button>
            </div>
        </form>
    </div>
</div>
@endsection
