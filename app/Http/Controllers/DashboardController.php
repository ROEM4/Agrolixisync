<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Response;
use App\Models\Analysis;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('dashboard.iot');
    }

    public function realtime(Request $request)
    {
        // ✅ TRAER TODAS LAS LOCATIONS EXPERIMENTALES (GE)
        $locations = \App\Models\Location::with('lote')
            ->where('experimental_group', 'experimental')
            ->orderBy('id')
            ->get();

        $analysisRecords = Analysis::with(['location', 'lote'])
            ->when($request->query('location_id'), fn($q, $locationId) => $q->where('location_id', $locationId))
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
            ->when($request->query('from'), fn($q, $from) => $q->where('analyzed_at', '>=', $from . ' 00:00:00'))
            ->when($request->query('to'), fn($q, $to) => $q->where('analyzed_at', '<=', $to . ' 23:59:59'))
            ->orderByDesc('analyzed_at')
            ->paginate(30)
            ->withQueryString();

        return view('dashboard.realtime', [
            'locations' => $locations,
            'analysisRecords' => $analysisRecords,
        ]);
    }

    public function updatePerfil(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . Auth::id()],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->password) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        return back()->with('success', 'Perfil actualizado correctamente.');
    }


}