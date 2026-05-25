@extends('layouts.app')
@section('title', 'Ficha de Registro PF — AgroLixiSync')

@section('content')
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.8);
        --glass-border: rgba(255, 255, 255, 0.4);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1rem;
    }

    .live-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #10b981;
        position: relative; display: inline-block;
    }
    .live-dot::after {
        content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 50%; background: inherit; animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
    }
    @keyframes pulse-ring {
        0% { transform: scale(0.33); }
        80%, 100% { opacity: 0; }
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.25rem;
        background: white;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none !important;
        transition: all 0.3s;
        margin-bottom: 1.5rem;
    }
    
    .back-link:hover {
        background: #f8fafc;
        transform: translateX(-5px);
    }

    .section-label {
        font-size: 0.65rem;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        margin-bottom: 1rem;
        display: block;
    }
</style>

<div class="page-container">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <a href="{{ route('analisis') }}" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">
                    <i class="fas fa-arrow-left"></i> Volver a Análisis
                </a>
                <span class="text-slate-300">|</span>
                <div class="flex items-center gap-2">
                    <div class="live-dot"></div>
                    <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Registro Académico</span>
                </div>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Ficha de Registro PF</h1>
            <p class="text-slate-500 font-medium mt-1">Control de Pérdida de Fertilizante y validación de sensores.</p>
        </div>
    </div>

    <div class="glass-card p-8 mb-10">
        <span class="section-label">Nueva Entrada de Registro</span>
        @include('components.pf-ficha-inline', [
            'locations' => $locations,
            'location_id' => $location_id,
            'ce_sup' => $ce_sup,
            'ce_prof' => $ce_prof,
            'records' => $records,
            'location' => $location
        ])
    </div>
</div>
@endsection
