<?php

namespace App\Http\Controllers;

use App\Models\Planta;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlantaController extends Controller
{
    public function index(Request $request)
    {
        $query = Planta::where('usuario_id', Auth::id())->with('ubicaciones');

        if ($request->filled('grupo')) {
            if ($request->grupo === 'GC') {
                $query->where('grupo_experimental', 'control');
            }
            if ($request->grupo === 'GE') {
                $query->where('grupo_experimental', 'experimental');
            }
        }

        $plantas = $query->orderBy('numero_planta')->get();

        return view('dashboard.plantas.index', compact('plantas'));
    }

    public function create()
    {
        return view('dashboard.plantas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'             => 'required|string|max:255',
            'numero_planta'      => 'required|integer|min:1',
            'grupo_experimental' => 'required|in:control,experimental',
            'tipo_cultivo'       => 'nullable|string|max:255',
            'ubicacion_nombre'   => 'required|string|max:255',
            'device_code'        => 'nullable|string|max:100',
        ]);

        $validated['usuario_id'] = Auth::id();

        $planta = Planta::create([
            'nombre'             => $validated['nombre'],
            'numero_planta'      => $validated['numero_planta'],
            'grupo_experimental' => $validated['grupo_experimental'],
            'tipo_cultivo'       => $validated['tipo_cultivo'] ?? 'palta',
            'usuario_id'         => $validated['usuario_id'],
        ]);

        // Crear la ubicación asociada
        Ubicacion::create([
            'planta_id'           => $planta->id,
            'nombre'              => $validated['ubicacion_nombre'],
            'grupo_experimental'  => $validated['grupo_experimental'],
            'codigo_dispositivo'  => $validated['grupo_experimental'] === 'experimental'
                                     ? ($validated['device_code'] ?? null)
                                     : null,
            'activa'              => true,
        ]);

        return redirect()->route('plantas.index')
            ->with('success', 'Planta creada correctamente');
    }

    public function edit(Planta $planta)
    {
        $planta->load('ubicaciones');
        return view('dashboard.plantas.edit', compact('planta'));
    }

    public function update(Request $request, Planta $planta)
    {
        $validated = $request->validate([
            'nombre'             => 'required|string|max:255',
            'numero_planta'      => 'required|integer|min:1',
            'grupo_experimental' => 'required|in:control,experimental',
            'tipo_cultivo'       => 'nullable|string|max:255',
            'ubicacion_nombre'   => 'required|string|max:255',
            'device_code'        => 'nullable|string|max:100',
        ]);

        $planta->update([
            'nombre'             => $validated['nombre'],
            'numero_planta'      => $validated['numero_planta'],
            'grupo_experimental' => $validated['grupo_experimental'],
            'tipo_cultivo'       => $validated['tipo_cultivo'] ?? $planta->tipo_cultivo,
        ]);

        // Actualizar o crear ubicación
        $ubicacion = $planta->ubicaciones()->first();
        $ubicData = [
            'nombre'             => $validated['ubicacion_nombre'],
            'grupo_experimental' => $validated['grupo_experimental'],
            'codigo_dispositivo' => $validated['grupo_experimental'] === 'experimental'
                                    ? ($validated['device_code'] ?? null)
                                    : null,
        ];

        if ($ubicacion) {
            $ubicacion->update($ubicData);
        } else {
            Ubicacion::create(array_merge($ubicData, [
                'planta_id' => $planta->id,
                'activa'    => true,
            ]));
        }

        return redirect()->route('plantas.index')
            ->with('success', 'Planta actualizada correctamente');
    }

    public function destroy(Planta $planta)
    {
        $planta->delete();

        return redirect()->route('plantas.index')
            ->with('success', 'Planta eliminada correctamente');
    }
}
