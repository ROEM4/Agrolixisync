<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Planta;
use App\Models\Ubicacion;
use App\Models\Lectura;
use App\Models\AnalisisLixiviacion;
use Carbon\Carbon;

class LixiviacionController extends Controller
{
    public function index(Request $request)
    {
        $location_id = $request->query('location_id');
        $filter = $request->query('filter', 'all');
        $mode = $request->query('mode', 'iot');

        // 🌳 CARGAR PLANTAS POR GRUPO
        $plantasGC = Planta::where('grupo_experimental', 'control')
            ->with('ubicaciones')
            ->orderBy('numero_planta')->get();
        $plantasGE = Planta::where('grupo_experimental', 'experimental')
            ->with('ubicaciones')
            ->orderBy('numero_planta')->get();

        $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;

        // 🛡️ CORRECCIÓN DE LÓGICA INVERSA
        if ($mode === 'iot' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control') {
            $firstGE = $plantasGE->first();
            $location_id = $firstGE && $firstGE->ubicaciones->isNotEmpty() ? $firstGE->ubicaciones->first()->id : null;
            $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;
        }

        if ($mode === 'manual' && $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'experimental') {
            $firstGC = $plantasGC->first();
            $location_id = $firstGC && $firstGC->ubicaciones->isNotEmpty() ? $firstGC->ubicaciones->first()->id : null;
            $ubicacionSeleccionada = $location_id ? Ubicacion::with('planta')->find($location_id) : null;
        }

        if (!$ubicacionSeleccionada) {
            if ($mode === 'iot') {
                $firstGE = $plantasGE->first();
                if ($firstGE && $firstGE->ubicaciones->isNotEmpty()) {
                    $location_id = $firstGE->ubicaciones->first()->id;
                    $ubicacionSeleccionada = Ubicacion::with('planta')->find($location_id);
                }
            } else {
                $firstGC = $plantasGC->first();
                if ($firstGC && $firstGC->ubicaciones->isNotEmpty()) {
                    $location_id = $firstGC->ubicaciones->first()->id;
                    $ubicacionSeleccionada = Ubicacion::with('planta')->find($location_id);
                }
            }
        }

        $isCtrl = $ubicacionSeleccionada && $ubicacionSeleccionada->grupo_experimental === 'control';

        // ✅ Obtener registros (filtrar por grupo según modo)
        $query = AnalisisLixiviacion::with(['planta', 'ubicacion']);
        
        if ($location_id) {
            $query->where('ubicacion_id', $location_id);
        } else {
            // Si no hay ubicación, filtrar por grupo
            if ($mode === 'manual') {
                $query->where('grupo_experimental', 'control');
            } else {
                $query->where('grupo_experimental', 'experimental');
            }
        }

        switch ($filter) {
            case '24h': $query->where('fecha_analisis', '>=', Carbon::today()); break;
            case '7d':  $query->where('fecha_analisis', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $query->where('fecha_analisis', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $query->where('fecha_analisis', '>=', Carbon::today()->subDays(30)); break;
        }

        $records = $query->orderByDesc('fecha_analisis')->paginate(30)->withQueryString();

        // ✅ Datos para gráficos (mismo filtro)
        $chartQuery = AnalisisLixiviacion::query();
        
        if ($location_id) {
            $chartQuery->where('ubicacion_id', $location_id);
        } else {
            if ($mode === 'manual') {
                $chartQuery->where('grupo_experimental', 'control');
            } else {
                $chartQuery->where('grupo_experimental', 'experimental');
            }
        }

        switch ($filter) {
            case '24h': $chartQuery->where('fecha_analisis', '>=', Carbon::today()); break;
            case '7d':  $chartQuery->where('fecha_analisis', '>=', Carbon::today()->subDays(7)); break;
            case '14d': $chartQuery->where('fecha_analisis', '>=', Carbon::today()->subDays(14)); break;
            case '30d': $chartQuery->where('fecha_analisis', '>=', Carbon::today()->subDays(30)); break;
        }

        $chartRows = $chartQuery->orderBy('fecha_analisis')->get();

        $datesJson = json_encode($chartRows->map(fn($r) => Carbon::parse($r->fecha_analisis)->format('d/m/Y'))->toArray());
        $ceSupJson = json_encode($chartRows->map(fn($r) => (float) $r->conductividad_superficial)->toArray());
        $ilxJson = json_encode($chartRows->map(fn($r) => (float) $r->ilx)->toArray());
        $countsJson = json_encode($chartRows->groupBy(fn($r) => Carbon::parse($r->fecha_analisis)->format('d/m/Y'))->map->count()->values()->toArray());

        return view('dashboard.lixiviacion', [
            'plantasGC' => $plantasGC,
            'plantasGE' => $plantasGE,
            'ubicaciones' => Ubicacion::with('planta')->orderBy('nombre')->get(),
            'location_id' => $location_id,
            'ubicacion' => $ubicacionSeleccionada,
            'ubicacionSeleccionada' => $ubicacionSeleccionada,
            'isCtrl' => $isCtrl,
            'mode' => $mode,
            'records' => $records,
            'filter' => $filter,
            'datesJson' => $datesJson,
            'ceSupJson' => $ceSupJson,
            'ilxJson' => $ilxJson,
            'countsJson' => $countsJson,
        ]);
    }

    public function export(Request $request)
    {
        return back()->with('success', 'Exportación iniciada');
    }

    /**
     * Almacena un registro manual de lixiviación (Grupo Control)
     */
    public function storeManual(Request $request)
    {
        $request->validate([
            'planta_id'                => 'required|exists:plantas,id',
            'fecha_analisis'           => 'required|date',
            'conductividad_superficial'=> 'required|numeric|min:0',
            'conductividad_profundo'   => 'required|numeric|min:0',
            'observacion'              => 'nullable|string|max:255',
        ]);

        $planta = Planta::findOrFail($request->planta_id);
        $ubicacion = $planta->ubicaciones->first();

        if (!$ubicacion) {
            return back()->withErrors(['planta_id' => 'La planta no tiene ubicación asociada.'])->withInput();
        }

        $ceSup = (float) $request->conductividad_superficial;
        $ceProf = (float) $request->conductividad_profundo;
        $ilx = $ceSup > 0 ? round($ceProf / $ceSup, 4) : 0;

        // ✅ CLASIFICACIÓN CONSISTENTE CON EL MODAL
        if ($ilx > 1.0) {
            $ilxEstado = 'ALTA LIXIVIACIÓN';
            $nivelRiesgo = 'alto';
        } elseif ($ilx >= 0.6) {
            $ilxEstado = 'MEDIA LIXIVIACIÓN';
            $nivelRiesgo = 'medio';
        } else {
            $ilxEstado = 'BAJA LIXIVIACIÓN';
            $nivelRiesgo = 'bajo';
        }

        AnalisisLixiviacion::create([
            'planta_id' => $planta->id,
            'ubicacion_id' => $ubicacion->id,
            'grupo_experimental' => $ubicacion->grupo_experimental,
            'conductividad_superficial' => $ceSup,
            'conductividad_profundo' => $ceProf,
            'delta_conductividad' => round($ceProf - $ceSup, 4),
            'ilx' => $ilx,
            'ilx_estado' => $ilxEstado,
            'estado_ilx' => $ilxEstado,
            'umbral_usado' => 1.0,
            'lixiviacion_detectada' => $ilx > 1.0 ? 1 : 0,
            'nivel_riesgo' => $nivelRiesgo,
            'porcentaje_riesgo' => round(min(100, max(0, $ilx * 100)), 2),
            'fecha_analisis' => $request->fecha_analisis,
            'tipo_evento' => 'LIXIVIATION',
            'validado' => 1,
            'fecha_validacion' => now(),
            'validado_por' => 'manual',
            'nivel_confianza' => 100,
            'notas' => $request->observacion,
            'notas_academicas' => $request->observacion,
        ]);

        return redirect()->route('lixiviacion', [
            'location_id' => $ubicacion->id,
            'mode' => 'manual',
            'filter' => 'all'
        ])->with('success', '✅ Registro manual guardado correctamente. ILx: ' . number_format($ilx, 3) . ' (' . $ilxEstado . ')');
    }
}