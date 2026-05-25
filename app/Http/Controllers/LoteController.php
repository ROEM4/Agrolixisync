<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoteController extends Controller
{
    /**
     * Lista todos los lotes del usuario
     */
    public function index()
    {
        $lotes = Lote::where('user_id', Auth::id())
            ->with('locations')
            ->paginate(15);

        return view('lotes.index', compact('lotes'));
    }

    /**
     * Muestra formulario para crear lote
     */
    public function create()
    {
        $cropTypes = [
            'palta' => '🥑 Palta (Palto)',
            'citricos' => '🍊 Cítricos',
            'frutilla' => '🍓 Frutilla',
            'otro' => 'Otro',
        ];

        return view('lotes.create', compact('cropTypes'));
    }

    /**
     * Almacena un nuevo lote
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'crop_type' => 'required|string|in:palta,citricos,frutilla,otro',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = Auth::id();

        $lote = Lote::create($validated);

        // Para diseño experimental puro:
        // 1. Crear ubicación de CONTROL (Tradicional/Manual)
        $lote->locations()->create([
            'name' => $validated['name'] . ' (Control)',
            'experimental_group' => 'control',
            'latitude' => -25.2637,
            'longitude' => -57.5759,
            'is_active' => true,
        ]);

        // 2. Crear ubicación EXPERIMENTAL (Sistema/Automatizado)
        $lote->locations()->create([
            'name' => $validated['name'] . ' (Experimental)',
            'experimental_group' => 'experimental',
            'latitude' => -25.2638, // Ligera diferencia para visualización
            'longitude' => -57.5760,
            'is_active' => true,
        ]);

        return redirect()->route('lotes.index')
            ->with('success', "✅ Lote '{$lote->name}' creado con grupos Control y Experimental configurados.");
    }
}
