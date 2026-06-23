@extends('layouts.app')
@section('title', 'Usuarios — AgroLixiSync')

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

    .table-card {
        background:#fff; border-radius:10px;
        box-shadow:0 1px 6px rgba(0,0,0,0.07);
        overflow:hidden;
    }
    .table-head {
        padding:0.9rem 1.25rem;
        border-bottom:1px solid #f3f4f6;
        font-weight:700; font-size:0.88rem; color:#1a472a;
    }
    table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    th { padding:0.7rem 1rem; text-align:left; color:#6b7280; font-weight:600; font-size:0.75rem; background:#f9fafb; }
    td { padding:0.75rem 1rem; border-bottom:1px solid #f3f4f6; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:#fafafa; }

    .badge-role {
        display:inline-block;
        padding:2px 10px; border-radius:12px;
        font-size:0.7rem; font-weight:700; text-transform:uppercase;
    }
    .badge-admin      { background:#dbeafe; color:#1e40af; }
    .badge-agricultor { background:#dcfce7; color:#166534; }

    .empty-state { padding:3rem; text-align:center; color:#9ca3af; }
    .empty-state .icon { font-size:2.5rem; margin-bottom:0.5rem; }
</style>

<div class="page-header">
    <div>
        <h1>👥 Gestión de Usuarios</h1>
        <p>Administradores y agricultores del sistema</p>
    </div>
    <a href="{{ route('usuarios.create') }}" class="btn-primary">➕ Nuevo Usuario</a>
</div>

@if(session('success'))
    <div class="alert-success">✅ {{ session('success') }}</div>
@endif

<div class="table-card">
    <div class="table-head">📋 Usuarios Registrados</div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Correo Electrónico</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
                    <td style="color:#9ca3af;font-family:monospace;">{{ $loop->iteration }}</td>
                    <td style="font-weight:600;color:#1a472a;">{{ $u->nombre }}</td>
                    <td style="font-family:monospace;font-size:0.8rem;color:#6b7280;">{{ $u->email }}</td>
                    <td>
                        <span class="badge-role badge-{{ $u->rol }}">
                            {{ $u->rol === 'admin' ? '🔑 Admin' : '🌾 Agricultor' }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('usuarios.edit', $u->id) }}" class="btn-edit" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-state">
                        <div class="icon">👤</div>
                        Sin usuarios registrados
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
