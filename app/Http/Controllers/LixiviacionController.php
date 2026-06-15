<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Lote;
use App\Models\Reading;
use App\Models\PFRecord;
use App\Models\Analysis;
use App\Models\Alert;
use App\Models\Observacion;
use App\Models\DetectionTimeRecord;
use Carbon\Carbon;

class LixiviacionController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', '30d');
        $mode = $request->query('mode', 'manual'); // 'iot' o 'manual'
        $location_id = $request->query('location_id');
        
        // ═══════════════════════════════════════════════════════════════
        // 🌳 CARGAR PLANTAS POR GRUPO
        // ═══════════════════════════════════════════════════════════════
        $lotesGC = Lote::where('experimental_group', 'control')->orderBy('plant_number')->get();
        $lotesGE = Lote::where('experimental_group', 'experimental')->orderBy('plant_number')->get();
        
        $selectedLocation = $location_id ? Location::find($location_id) : null;

        // 🛡️ CORRECCIÓN DE LÓGICA INVERSA:
        // Si el modo es 'iot' pero la ubicación seleccionada es de control, cambiamos a la primera de GE
        if ($mode === 'iot' && $selectedLocation && $selectedLocation->experimental_group === 'control') {
            $firstGE = $lotesGE->first();
            $location_id = $firstGE && $firstGE->locations->isNotEmpty() ? $firstGE->locations->first()->id : null;
            $selectedLocation = $location_id ? Location::find($location_id) : null;
        }
        
        // Si el modo es 'manual' pero la ubicación seleccionada es experimental, cambiamos a la primera de GC
        if ($mode === 'manual' && $selectedLocation && $selectedLocation->experimental_group === 'experimental') {
            $firstGC = $lotesGC->first();
            $location_id = $firstGC && $firstGC->locations->isNotEmpty() ? $firstGC->locations->first()->id : null;
            $selectedLocation = $location_id ? Location::find($location_id) : null;
        }

        // Si aún no hay ubicación seleccionada, asignar por defecto según el modo
        if (!$selectedLocation) {
            if ($mode === 'iot') {
                $firstGE = $lotesGE->first();
                if ($firstGE && $firstGE->locations->isNotEmpty()) {
                    $location_id = $firstGE->locations->first()->id;
                    $selectedLocation = Location::find($location_id);
                }
            } else {
                $firstGC = $lotesGC->first();
                if ($firstGC && $firstGC->locations->isNotEmpty()) {
                    $location_id = $firstGC->locations->first()->id;
                    $selectedLocation = Location::find($location_id);
                }
            }
        }

        $isCtrl = $selectedLocation && $selectedLocation->experimental_group === 'control';
        
        // ═══════════════════════════════════════════════════════════════
        // 📊 OBTENER REGISTROS PARA LA TABLA
        // ═══════════════════════════════════════════════════════════════
        if ($isCtrl) {
            $query = PFRecord::with('location.lote')->orderByDesc('recorded_at');
            if ($location_id) $query->where('location_id', $location_id);
            
            switch ($filter) {
                case '24h': $query->where('recorded_at', '>=', now()->subHours(24)); break;
                case '7d':  $query->where('recorded_at', '>=', now()->subDays(7)); break;
                case '14d': $query->where('recorded_at', '>=', now()->subDays(14)); break;
                case '30d': $query->where('recorded_at', '>=', now()->subDays(30)); break;
            }
            
            $records = $query->paginate(20)->withQueryString();
            $records->getCollection()->transform(function($r) {
                $r->ilx = ($r->ce_superficial > 0) ? round($r->ce_profunda / $r->ce_superficial, 4) : 0;
                $r->nivel = $this->clasificarILx($r->ilx);
                return $r;
            });
        } else {
            $query = Analysis::with('location.lote')->orderByDesc('analyzed_at');
            if ($location_id) $query->where('location_id', $location_id);
            
            switch ($filter) {
                case '24h': $query->where('analyzed_at', '>=', now()->subHours(24)); break;
                case '7d':  $query->where('analyzed_at', '>=', now()->subDays(7)); break;
                case '14d': $query->where('analyzed_at', '>=', now()->subDays(14)); break;
                case '30d': $query->where('analyzed_at', '>=', now()->subDays(30)); break;
            }
            
            $analysisRecords = $query->paginate(20)->withQueryString();
            $records = $analysisRecords->getCollection()->map(function($r) {
                return (object)[
                    'id' => $r->id,
                    'lote' => $r->location->lote ?? null,
                    'location' => $r->location,
                    'recorded_at' => $r->analyzed_at,
                    'ce_superficial' => $r->conductivity_superficial,
                    'ce_profunda' => $r->conductivity_profundo,
                    'ilx' => $r->ilx,
                    'nivel' => $r->ilx_estado ?? $this->clasificarILx($r->ilx),
                ];
            });
            $records = new \Illuminate\Pagination\LengthAwarePaginator(
                $records, $analysisRecords->total(), $analysisRecords->perPage(),
                $analysisRecords->currentPage(), ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        // ═══════════════════════════════════════════════════════════════
        // 📈 DATOS PARA GRÁFICOS
        // ═══════════════════════════════════════════════════════════════
        if ($isCtrl) {
            $chartQuery = PFRecord::query()->orderBy('recorded_at');
            if ($location_id) $chartQuery->where('location_id', $location_id);
            switch ($filter) {
                case '24h': $chartQuery->where('recorded_at', '>=', now()->subHours(24)); break;
                case '7d':  $chartQuery->where('recorded_at', '>=', now()->subDays(7)); break;
                case '14d': $chartQuery->where('recorded_at', '>=', now()->subDays(14)); break;
                case '30d': $chartQuery->where('recorded_at', '>=', now()->subDays(30)); break;
            }
            $chartRows = $chartQuery->get()->groupBy(fn($r) => $r->recorded_at->format('Y-m-d'));
        } else {
            $chartQuery = Analysis::query()->orderBy('analyzed_at');
            if ($location_id) $chartQuery->where('location_id', $location_id);
            switch ($filter) {
                case '24h': $chartQuery->where('analyzed_at', '>=', now()->subHours(24)); break;
                case '7d':  $chartQuery->where('analyzed_at', '>=', now()->subDays(7)); break;
                case '14d': $chartQuery->where('analyzed_at', '>=', now()->subDays(14)); break;
                case '30d': $chartQuery->where('analyzed_at', '>=', now()->subDays(30)); break;
            }
            $chartRows = $chartQuery->get()->groupBy(fn($r) => $r->analyzed_at->format('Y-m-d'));
        }
        
        $dates = []; $avgCeSup = []; $avgIlx = []; $counts = [];
        foreach ($chartRows as $date => $rows) {
            $dates[] = Carbon::parse($date)->format('d/m');
            if ($isCtrl) {
                $avgCeSup[] = round($rows->avg('ce_superficial'), 3);
                $avgIlx[] = round($rows->avg(fn($r) => $r->ce_superficial > 0 ? $r->ce_profunda / $r->ce_superficial : 0), 4);
            } else {
                $avgCeSup[] = round($rows->avg('conductivity_superficial'), 3);
                $avgIlx[] = round($rows->avg('ilx'), 4);
            }
            $counts[] = $rows->count();
        }
        
        return view('dashboard.lixiviacion', compact(
            'lotesGC', 'lotesGE', 'location_id', 'filter', 'mode', 
            'selectedLocation', 'isCtrl', 'records'
        ))->with([
            'datesJson'  => json_encode($dates),
            'ceSupJson'  => json_encode($avgCeSup),
            'ilxJson'    => json_encode($avgIlx),
            'countsJson' => json_encode($counts),
        ]);
    }

    private function clasificarILx(float $ilx): string
    {
        if ($ilx < 0.4) return 'Baja lixiviación';
        if ($ilx >= 0.6 && $ilx <= 1.0) return 'Media lixiviación';
        if ($ilx > 1.0) return 'Alta lixiviación';
        return 'Media lixiviación';
    }
    /**
     * Guardar registro manual del GRUPO CONTROL
     */
    public function storeManual(Request $request)
    {
        $request->validate([
            'lote_id'                  => 'required|exists:lotes,id',
            'conductivity_superficial' => 'required|numeric|min:0',
            'conductivity_profundo'    => 'required|numeric|min:0',
            'recorded_at'              => 'nullable|date',
            'observacion'              => 'nullable|string|max:255',
        ]);
        
        $lote = Lote::findOrFail($request->lote_id);
        $location = $lote->locations->first();
        
        if (!$location) {
            return back()->with('error', 'La planta seleccionada no tiene una ubicación asociada.');
        }
        
        $ce_s = (float) $request->conductivity_superficial;
        $ce_p = (float) $request->conductivity_profundo;
        $ilx = $ce_s > 0 ? round($ce_p / $ce_s, 4) : 0;
        
        // Guardar en pf_records
        PFRecord::create([
            'location_id'        => $location->id,
            'experimental_group' => 'control',
            'recorded_at'        => $request->recorded_at ?? now(),
            'ce_superficial'     => $ce_s,
            'ce_profunda'        => $ce_p,
            'ce_reference'       => null,
            'ce_measured'        => null,
            'subparcela'         => null,
            'pf_percentage'      => null,
        ]);
        
        return redirect()->route('lixiviacion', ['location_id' => $location->id])
            ->with('success', "✅ Registro manual guardado para {$lote->name}. ILx calculado: " . number_format($ilx, 4));
    }
    
    public function export(Request $request)
    {
        // Tu lógica existente
    }
}