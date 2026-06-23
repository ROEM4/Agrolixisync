<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AgroLixiSync')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --sidebar-w: 260px;
            --green-dark: #0f4c2a;
            --green-mid:  #16a34a;
            --green-light:#22c55e;
            --bg-page:    #0d1117;
            --bg-card:    #161b22;
            --bg-sidebar: #0d1117;
            --border:     #30363d;
            --text-main:  #e6edf3;
            --text-muted: #8b949e;
        }
        * { box-sizing: border-box; }
        body {
            background: #f0f4f8;
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            height: 100vh; width: var(--sidebar-w);
            background: var(--bg-sidebar);
            display: flex; flex-direction: column;
            z-index: 1000;
            border-right: 1px solid var(--border);
        }
        .sidebar-logo {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
        }
        .sidebar-logo .logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .sidebar-logo .logo-text { line-height: 1.1; }
        .sidebar-logo .logo-text span:first-child {
            display: block; font-size: 0.95rem; font-weight: 700; color: #fff;
        }
        .sidebar-logo .logo-text span:last-child {
            display: block; font-size: 0.68rem; color: var(--text-muted); letter-spacing: 0.05em;
        }

        /* user badge */
        .sidebar-user {
            margin: 0.75rem 1rem;
            padding: 0.65rem 0.85rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .sidebar-user .u-name { font-size: 0.82rem; font-weight: 600; color: #fff; }
        .sidebar-user .u-role {
            font-size: 0.7rem; color: var(--green-light);
            text-transform: uppercase; letter-spacing: 0.06em;
        }

        /* nav section label */
        .nav-section {
            padding: 0.9rem 1.25rem 0.3rem;
            font-size: 0.65rem; font-weight: 700;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;
        }

        /* nav link */
        .nav-link {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.6rem 1.25rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem; font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.15s;
        }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-link.active {
            color: var(--green-light);
            background: rgba(34,197,94,0.08);
            border-left-color: var(--green-light);
        }
        .nav-link i { width: 16px; text-align: center; font-size: 0.8rem; }

        /* badge en nav */
        .nav-badge {
            margin-left: auto;
            background: #dc2626; color: #fff;
            font-size: 0.65rem; font-weight: 700;
            padding: 1px 6px; border-radius: 10px;
        }

        /* divider */
        .nav-divider { border-top: 1px solid var(--border); margin: 0.5rem 0; }

        /* sidebar footer */
        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 0.75rem 0;
        }

        /* ── CONTENT ── */
        .content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .mobile-toggle {
            display: none;
            position: fixed; top: 1rem; left: 1rem;
            width: 40px; height: 40px;
            background: var(--green-mid); color: #fff;
            border: none; border-radius: 8px;
            align-items: center; justify-content: center;
            z-index: 1100; cursor: pointer;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        @media (max-width: 1200px) {
            .sidebar { 
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 10px 0 30px rgba(0,0,0,0.5);
            }
            .sidebar.show { transform: translateX(0); }
            .content { margin-left: 0 !important; padding: 1rem; padding-top: 5rem; }
            .mobile-toggle { display: flex; }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
        }
        .sidebar-overlay.show { display: block; }

        /* Estética general de tarjetas para que no se corten */
        .card, .glass-card, .table-card {
            max-width: 100%;
            overflow-x: auto;
        }
    </style>
</head>
<body>
@if(Auth::check())
    <button class="mobile-toggle" id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

<nav class="sidebar">
    {{-- Logo --}}
    <a href="{{ route('dashboard') }}" class="sidebar-logo">
        <div class="logo-icon">🌱</div>
        <div class="logo-text">
            <span>AgroLixiSync</span>
            <span>Sistema de Monitoreo IoT</span>
        </div>
    </a>

    {{-- Usuario --}}
    <div class="sidebar-user">
        <div class="u-name">{{ Auth::user()->nombre }}</div>
        <div class="u-role">{{ Auth::user()->rol }}</div>
    </div>

    {{-- Monitoreo --}}
    <div class="nav-section">Monitoreo</div>
    <a class="nav-link {{ request()->routeIs('dashboard') || request()->routeIs('realtime') || request()->routeIs('monitor') ? 'active' : '' }}"
       href="{{ route('dashboard') }}">
        <i class="fas fa-satellite-dish"></i> Monitoreo en Tiempo Real
        <span class="status-dot" style="margin-left:auto;"></span>
    </a>

    {{-- Análisis --}}
    <div class="nav-divider"></div>
    <div class="nav-section">Indicadores</div>
    <a class="nav-link {{ request()->routeIs('analisis') ? 'active' : '' }}"
       href="{{ route('analisis') }}">
        <i class="fas fa-microscope"></i> Porcentaje de Precisión de detección
    </a>
    <a class="nav-link {{ request()->routeIs('lixiviacion') ? 'active' : '' }}"
       href="{{ route('lixiviacion') }}">
        <i class="fas fa-chart-pie"></i> Nivel de Lixiviación
    </a>
    <a class="nav-link {{ request()->routeIs('detection_time') ? 'active' : '' }}"
       href="{{ route('detection_time') }}">
        <i class="fas fa-hourglass-end"></i> Tiempo promedio de Detección
    </a>

    {{-- Alertas e Histórico --}}
    <div class="nav-divider"></div>
    <a class="nav-link {{ request()->routeIs('alertas') ? 'active' : '' }}"
       href="{{ route('alertas') }}">
        <i class="fas fa-bell"></i> Alertas
    </a>
    <a class="nav-link {{ request()->routeIs('historico') ? 'active' : '' }}"
       href="{{ route('historico') }}">
        <i class="fas fa-history"></i> Historial de Registros
    </a>

    {{-- Gestión --}}
    <div class="nav-divider"></div>
    <div class="nav-section">Gestión</div>
    <a class="nav-link {{ request()->routeIs('plantas*') ? 'active' : '' }}"
       href="{{ route('plantas.index') }}">
        <i class="fas fa-seedling"></i> Plantas
    </a>

    @if(Auth::user()->rol === 'admin')
    <a class="nav-link {{ request()->routeIs('usuarios*') ? 'active' : '' }}"
       href="{{ route('usuarios.index') }}">
        <i class="fas fa-users"></i> Usuarios
    </a>
    @endif

    {{-- Footer --}}
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link w-100 text-start" style="background:none;border:none;color:#ef4444;width:100%;">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </button>
        </form>
    </div>
</nav>
@endif

<main class="{{ Auth::check() ? 'content' : 'p-4' }}">
    @yield('content')
</main>

@stack('scripts')
<script>
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (toggle && sidebar && overlay) {
        const toggleMenu = () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        };

        toggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    }
</script>
</body>
</html>
