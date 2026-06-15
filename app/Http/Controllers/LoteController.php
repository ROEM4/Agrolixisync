<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Lote::where('user_id', Auth::id())
            ->with('locations');

        // Filtro por grupo
        if ($request->filled('grupo')) {

            if ($request->grupo === 'GC') {
                $query->where('experimental_group', 'control');
            }

            if ($request->grupo === 'GE') {
                $query->where('experimental_group', 'experimental');
            }
        }

        $lotes = $query
            ->orderBy('plant_number', 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('lotes.index', compact('lotes'));
    }

    public function create()
    {
        return view('lotes.create');
    }

    public function edit(Lote $lote)
    {
        if ($lote->user_id !== Auth::id()) {
            abort(403);
        }

        $lote->load('locations');

        return view('lotes.edit', compact('lote'));
    }

   public function update(Request $request, Lote $lote)
    {
        if ($lote->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plant_number' => 'required|integer|min:1|max:30',
            'experimental_group' => 'required|in:GC,GE',
            'crop_type' => 'required|string',
        ]);

        // MAPEO GC / GE → BD
        $validated['experimental_group'] =
            $validated['experimental_group'] === 'GC'
                ? 'control'
                : 'experimental';

        $lote->update($validated);

        return redirect()
            ->route('lotes.index')
            ->with('success', 'Planta actualizada correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plant_number' => 'required|integer|min:1|max:30',
            'experimental_group' => 'required|in:GC,GE',
            'crop_type' => 'required|string',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        // 🔥 MAPEO CRÍTICO GC/GE → BD
        $validated['experimental_group'] =
            $validated['experimental_group'] === 'GC'
            ? 'control'
            : 'experimental';

        $validated['user_id'] = Auth::id();

        $lote = Lote::create($validated);

        // Control
        $lote->locations()->create([
            'name' => $lote->name . ' - Control',
            'experimental_group' => 'control',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'is_active' => true,
        ]);

        // Experimental
        $lote->locations()->create([
            'name' => $lote->name . ' - Experimental',
            'experimental_group' => 'experimental',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'is_active' => true,
        ]);

        return redirect()->route('lotes.index')
            ->with('success', "Planta '{$lote->name}' creada correctamente.");
    }
}