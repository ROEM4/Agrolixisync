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
    --green-mid: #16a34a;
    --green-light:#22c55e;
    --bg-page:#0d1117;
    --bg-card:#161b22;
    --bg-sidebar:#0d1117;
    --border:#30363d;
    --text-main:#e6edf3;
    --text-muted:#8b949e;
}

* {
    box-sizing:border-box;
}

body {
    background:#f0f4f8;
    font-family:'Segoe UI', system-ui, sans-serif;
    margin:0;
}


/* ===========================
        SIDEBAR
=========================== */

.sidebar {

    position:fixed;
    top:0;
    left:0;

    width:var(--sidebar-w);
    height:100vh;

    background:var(--bg-sidebar);

    display:flex;
    flex-direction:column;

    z-index:1000;

    border-right:1px solid var(--border);

    /* NUEVO */
    overflow-y:auto;
    overflow-x:hidden;

}


/* Scroll bonito */
.sidebar::-webkit-scrollbar {
    width:6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background:#30363d;
    border-radius:10px;
}


/* LOGO */

.sidebar-logo {

    display:flex;
    align-items:center;
    gap:.6rem;

    padding:1.25rem 1.25rem 1rem;

    border-bottom:1px solid var(--border);

    text-decoration:none;

}


.logo-icon {

    width:36px;
    height:36px;

    background:linear-gradient(
        135deg,
        var(--green-mid),
        var(--green-dark)
    );

    border-radius:8px;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:1.1rem;

    flex-shrink:0;

}


.logo-text span:first-child {

    display:block;

    font-size:.95rem;

    font-weight:700;

    color:white;

}


.logo-text span:last-child {

    display:block;

    font-size:.68rem;

    color:var(--text-muted);

}


/* USUARIO */

.sidebar-user {

    margin:.75rem 1rem;

    padding:.65rem .85rem;

    background:rgba(255,255,255,.04);

    border:1px solid var(--border);

    border-radius:8px;

}


.u-name {

    font-size:.82rem;

    font-weight:600;

    color:white;

}


.u-role {

    font-size:.7rem;

    color:var(--green-light);

    text-transform:uppercase;

}



/* TITULOS */

.nav-section {

    padding:.9rem 1.25rem .3rem;

    font-size:.65rem;

    font-weight:700;

    color:var(--text-muted);

    text-transform:uppercase;

}



/* LINKS */

.nav-link {

    display:flex;

    align-items:center;

    gap:.65rem;

    padding:.6rem 1.25rem;

    color:var(--text-muted);

    text-decoration:none;

    font-size:.85rem;

    font-weight:500;

    border-left:3px solid transparent;

    transition:.2s;

    /* IMPORTANTE */
    white-space:normal;

    overflow-wrap:anywhere;

}


.nav-link:hover {

    color:white;

    background:rgba(255,255,255,.05);

}


.nav-link.active {

    color:var(--green-light);

    background:rgba(34,197,94,.08);

    border-left-color:var(--green-light);

}


.nav-link i {

    width:16px;

    text-align:center;

    flex-shrink:0;

}


.nav-divider {

    border-top:1px solid var(--border);

    margin:.5rem 0;

}



/* FOOTER */

.sidebar-footer {

    margin-top:auto;

    border-top:1px solid var(--border);

    padding:.75rem 0;

}



/* ===========================
        CONTENIDO
=========================== */


.content {

    margin-left:var(--sidebar-w);

    min-height:100vh;

    padding:2rem;

    transition:.3s;

}


/* ===========================
        BOTON MOVIL
=========================== */


.mobile-toggle {

    display:none;

    position:fixed;

    top:1rem;

    left:1rem;

    width:40px;

    height:40px;

    background:var(--green-mid);

    color:white;

    border:none;

    border-radius:8px;

    align-items:center;

    justify-content:center;

    z-index:1100;

}


/* ===========================
        TABLETS / MOVILES
=========================== */


@media(max-width:900px){


    .sidebar {

        transform:translateX(-100%);

        transition:.3s ease;

        box-shadow:
        10px 0 30px rgba(0,0,0,.5);

    }


    .sidebar.show {

        transform:translateX(0);

    }


    .content {

        margin-left:0!important;

        padding:1rem;

        padding-top:5rem;

    }


    .mobile-toggle {

        display:flex;

    }


}



/* ===========================
        LAPTOPS PEQUEÑAS
=========================== */


@media(max-width:1400px){


    .content {

        padding:1rem;

    }


}



/* OVERLAY */


.sidebar-overlay {

    display:none;

    position:fixed;

    inset:0;

    background:rgba(0,0,0,.6);

    backdrop-filter:blur(4px);

    z-index:999;

}


.sidebar-overlay.show {

    display:block;

}



/* TARJETAS / TABLAS */


.card,
.glass-card,
.table-card {

    max-width:100%;

    overflow-x:auto;

}


.table-responsive {

    overflow-x:auto;

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
