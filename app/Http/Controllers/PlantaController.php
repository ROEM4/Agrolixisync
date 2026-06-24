<?php

namespace App\Http\Controllers;

use App\Models\Planta;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlantaController extends Controller
{
    public function index(Request $request)
    {
        $query = Planta::where('usuario_id', Auth::id())
            ->with('ubicaciones');

        if ($request->filled('grupo')) {

            if ($request->grupo === 'GC') {
                $query->where('grupo_experimental', 'control');
            }

            if ($request->grupo === 'GE') {
                $query->where('grupo_experimental', 'experimental');
            }
        }

        $plantas = $query
            ->orderBy('numero_planta')
            ->get();

        return view('dashboard.plantas.index', compact('plantas'));
    }


    public function create()
    {
        return view('dashboard.plantas.create');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([

            'nombre' => [
                'required',
                'string',
                'max:255'
            ],

            'numero_planta' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('plantas')
                    ->where('usuario_id', Auth::id())
            ],

            'grupo_experimental' => [
                'required',
                'in:control,experimental'
            ],

            'tipo_cultivo' => [
                'nullable',
                'string',
                'max:255'
            ],

            'ubicacion_nombre' => [
                'required',
                'string',
                'max:255'
            ],

        ]);


        $usuarioId = Auth::id();


        $planta = Planta::create([

            'nombre' => $validated['nombre'],

            'numero_planta' => $validated['numero_planta'],

            'grupo_experimental' => $validated['grupo_experimental'],

            'tipo_cultivo' => $validated['tipo_cultivo'] ?? 'palta',

            'usuario_id' => $usuarioId,

        ]);


        Ubicacion::create([

            'planta_id' => $planta->id,

            'nombre' => $validated['ubicacion_nombre'],

            'grupo_experimental' => $validated['grupo_experimental'],

            // Generación automática A1, A2, A3...
            'codigo_dispositivo' => 
                $validated['grupo_experimental'] === 'experimental'
                    ? 'A' . $validated['numero_planta']
                    : null,

            'activa' => true,

        ]);


        return redirect()
            ->route('plantas.index')
            ->with('success', 'Planta creada correctamente');
    }



    public function edit(Planta $planta)
    {
        $this->authorizeOwner($planta);

        $planta->load('ubicaciones');

        return view(
            'dashboard.plantas.edit',
            compact('planta')
        );
    }



    public function update(Request $request, Planta $planta)
    {
        $this->authorizeOwner($planta);


        $validated = $request->validate([

            'nombre' => [
                'required',
                'string',
                'max:255'
            ],


            'numero_planta' => [

                'required',
                'integer',
                'min:1',

                Rule::unique('plantas')
                    ->where('usuario_id', Auth::id())
                    ->ignore($planta->id)

            ],


            'grupo_experimental' => [
                'required',
                'in:control,experimental'
            ],


            'tipo_cultivo' => [
                'nullable',
                'string',
                'max:255'
            ],


            'ubicacion_nombre' => [
                'required',
                'string',
                'max:255'
            ],

        ]);



        $planta->update([

            'nombre' => $validated['nombre'],

            'numero_planta' => $validated['numero_planta'],

            'grupo_experimental' => $validated['grupo_experimental'],

            'tipo_cultivo' =>
                $validated['tipo_cultivo']
                ?? $planta->tipo_cultivo,

        ]);



        $ubicacion = $planta
            ->ubicaciones()
            ->first();



        $ubicData = [

            'nombre' => $validated['ubicacion_nombre'],


            'grupo_experimental' =>
                $validated['grupo_experimental'],


            // Actualiza automáticamente A+número
            'codigo_dispositivo' =>
                $validated['grupo_experimental'] === 'experimental'
                    ? 'A' . $validated['numero_planta']
                    : null,

        ];



        if ($ubicacion) {

            $ubicacion->update($ubicData);

        } else {


            Ubicacion::create([

                'planta_id' => $planta->id,

                'nombre' => $validated['ubicacion_nombre'],

                'grupo_experimental' =>
                    $validated['grupo_experimental'],

                'codigo_dispositivo' =>
                    $validated['grupo_experimental'] === 'experimental'
                        ? 'A' . $validated['numero_planta']
                        : null,

                'activa' => true,

            ]);

        }



        return redirect()
            ->route('plantas.index')
            ->with('success', 'Planta actualizada correctamente');
    }



    public function destroy(Planta $planta)
    {
        $this->authorizeOwner($planta);


        $planta->delete();


        return redirect()
            ->route('plantas.index')
            ->with('success', 'Planta eliminada correctamente');
    }



    /**
     * Verifica que la planta pertenezca al usuario autenticado
     */
    private function authorizeOwner(Planta $planta)
    {
        if ($planta->usuario_id !== Auth::id()) {

            abort(403, 'No tienes permiso para acceder a esta planta.');

        }
    }
}