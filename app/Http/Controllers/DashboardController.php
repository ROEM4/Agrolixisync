<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ubicacion;
use App\Models\AnalisisLixiviacion;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('dashboard.iot');
    }

    public function realtime(Request $request)
    {
        // ✅ TRAER TODAS LAS UBICACIONES EXPERIMENTALES (GE)
        $ubicaciones = Ubicacion::with('planta')
            ->where('grupo_experimental', 'experimental')
            ->orderBy('id')
            ->get();

        $analisisRecords = AnalisisLixiviacion::with(['ubicacion', 'planta'])
            ->when($request->query('ubicacion_id'), fn($q, $ubicacionId) => $q->where('ubicacion_id', $ubicacionId))
            ->when($request->query('analysis_type'), function ($q, $type) {
                if ($type === 'lixiviacion_alta') {
                    return $q->where('ilx_estado', 'LIXIVIACIÓN ALTA');
                }
                if ($type === 'lixiviacion') {
                    return $q->where('ilx_estado', 'LIXIVIACIÓN');
                }
                if ($type === 'equilibrio') {
                    return $q->where('ilx_estado', 'EQUILIBRIO');
                }
                if ($type === 'retencion') {
                    return $q->where('ilx_estado', 'RETENCIÓN');
                }
                if ($type === 'acumulacion') {
                    return $q->where('ilx_estado', 'ACUMULACIÓN');
                }
                return $q;
            })
            ->when($request->query('from'), fn($q, $from) => $q->where('fecha_analisis', '>=', $from . ' 00:00:00'))
            ->when($request->query('to'), fn($q, $to) => $q->where('fecha_analisis', '<=', $to . ' 23:59:59'))
            ->orderByDesc('fecha_analisis')
            ->paginate(30)
            ->withQueryString();

        return view('dashboard.realtime', [
            'locations' => $ubicaciones,
            'analisisRecords' => $analisisRecords,
        ]);
    }

    public function updatePerfil(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:usuarios,email,' . auth()->id()],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user = auth()->user();
        $user->nombre = $request->nombre;
        $user->email = $request->email;
        if ($request->password) {
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        }
        $user->save();

        return back()->with('success', 'Perfil actualizado correctamente.');
    }
}