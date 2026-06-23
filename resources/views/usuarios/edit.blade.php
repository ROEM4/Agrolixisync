@extends('layouts.app')
@section('title', 'Editar Usuario — AgroLixiSync')

@section('content')
<style>
    .form-wrap { max-width:520px; margin:0 auto; }
    .page-header { margin-bottom:1.5rem; }
    .page-header h1 { margin:0; font-size:1.4rem; font-weight:700; color:#1a472a; }
    .page-header p  { margin:0.25rem 0 0; font-size:0.85rem; color:#6b7280; }
    .form-card { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,0.07); padding:1.75rem; }
    .field { margin-bottom:1.25rem; }
    .field label { display:block; font-size:0.78rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.4rem; }
    .field input, .field select { width:100%; padding:0.65rem 0.85rem; border:1.5px solid #d1d5db; border-radius:7px; font-size:0.9rem; background:#f9fafb; transition:border-color 0.2s; outline:none; }
    .field input:focus, .field select:focus { border-color:#16a34a; background:#fff; }
    .field .error { font-size:0.78rem; color:#dc2626; margin-top:0.3rem; }
    .error-box { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1.25rem; color:#dc2626; font-size:0.82rem; }
    .actions { display:flex; gap:0.75rem; margin-top:1.5rem; }
    .btn-submit { flex:1; padding:0.7rem; background:linear-gradient(135deg,#16a34a,#15803d); color:#fff; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; }
    .btn-submit:hover { opacity:0.9; }
    .btn-cancel { flex:1; padding:0.7rem; background:#f3f4f6; color:#374151; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; text-decoration:none; text-align:center; }
    .btn-cancel:hover { background:#e5e7eb; color:#374151; }
</style>

<div class="form-wrap">
    <div class="page-header">
        <h1>✎ Editar Usuario</h1>
        <p>Actualiza la información del usuario (nombre no editable)</p>
    </div>

    @if($errors->any())
        <div class="error-box">
            @foreach($errors->all() as $e) <div>⚠ {{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('usuarios.update', $user->id) }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label>Nombre Completo</label>
                <input type="text" value="{{ $user->nombre }}" disabled>
            </div>
            <div class="field">
                <label>Correo Electrónico</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Contraseña (dejar vacío para mantener actual)</label>
                <input type="password" name="password" placeholder="Nueva contraseña">
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Rol</label>
                <select name="rol" required>
                    <option value="admin" {{ $user->rol === 'admin' ? 'selected' : '' }}>🔑 Admin</option>
                    <option value="agricultor" {{ $user->rol === 'agricultor' ? 'selected' : '' }}>🌾 Agricultor</option>
                </select>
                @error('rol') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="actions">
                <a href="{{ route('usuarios.index') }}" class="btn-cancel">← Cancelar</a>
                <button type="submit" class="btn-submit">✅ Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
