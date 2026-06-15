<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertEvaluation;
use App\Models\DailyConsolidation;
use App\Models\Alert;
use App\Models\Location;
use App\Models\Lote;
use App\Models\PFRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AnalisisController extends Controller
{
    public function index(Request $request)
    {
        // ═══ ALERTAS PENDIENTES DE EVALUACIÓN (últimos 30 días) ═══
        $pendingAlerts = Alert::with(['location.lote', 'lote'])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereDoesntHave('evaluation')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // ═══ TOTALES ACUMULADOS (VP/FP/FN) ═══
        $vp = AlertEvaluation::where('label', 'VP')->count();
        $fp = AlertEvaluation::where('label', 'FP')->count();
        $fn = AlertEvaluation::where('label', 'FN')->count();
        $total = $vp + $fp + $fn;

        $pdsPercentage = $total > 0 ? (($vp / $total) * 100) : 0;
        $errorRate = 100 - $pdsPercentage;

        $stats = [
            'vp' => $vp,
            'fp' => $fp,
            'fn' => $fn,
            'total' => $total,
            'pds_percentage' => round($pdsPercentage, 2),
            'error_rate' => round($errorRate, 2),
        ];

        // ═══ DATOS PARA GRÁFICO DE EVOLUCIÓN TEMPORAL ═══
        // Usa los campos REALES: consolidation_date, vp, fp, fn
        $dailyStats = DailyConsolidation::orderBy('consolidation_date')
            ->get()
            ->map(function ($day) {
                return [
                    'date' => $day->consolidation_date,
                    'date_label' => Carbon::parse($day->consolidation_date)->format('d/m/Y'),
                    'vp' => $day->vp ?? 0,
                    'fp' => $day->fp ?? 0,
                    'fn' => $day->fn ?? 0,
                    'pds_percentage' => $day->pds_percentage ?? 0,
                    'lote_name' => $day->lote?->name ?? 'N/D',
                ];
            });

        $dates = $dailyStats->pluck('date_label')->toArray();
        $pdsJson = json_encode($dailyStats->pluck('pds_percentage')->toArray());
        $errorJson = json_encode($dailyStats->map(fn($d) => 100 - $d['pds_percentage'])->toArray());

        // ═══ DATOS PARA TABLA DE CONTROL (grupo control) ═══
        $controlRecords = PFRecord::with(['location', 'location.lote'])
            ->orderByDesc('recorded_at')
            ->limit(15)
            ->get()
            ->map(function ($record) {
                $lote = $record->location ? $record->location->lote : null;
                
                return [
                    'id' => $record->id,
                    'location_id' => $record->location_id,
                    'lote' => $lote,
                    'lote_name' => $lote ? $lote->name : 'N/D',
                    'lote_plant_number' => $lote ? $lote->plant_number : '?',
                    'recorded_at' => $record->recorded_at,
                    'date_label' => Carbon::parse($record->recorded_at)->format('d/m/Y'),
                    'ce_superficial' => $record->ce_superficial,
                    'ce_profunda' => $record->ce_profunda,
                    'pf_percentage' => $record->pf_percentage,
                    'subparcela' => $record->subparcela ?? 'A',
                ];
            });

        // ═══ LOTES PARA MODAL DE INGRESO MANUAL ═══
        $lotes = Lote::where('experimental_group', 'control')
            ->with('locations')
            ->orderBy('plant_number')
            ->get();

        // ═══ UBICACIÓN SELECCIONADA ═══
        $selectedLocation = null;
        if ($request->has('location_id')) {
            $selectedLocation = Location::find($request->location_id);
        }

        return view('dashboard.analisis', compact(
            'pendingAlerts',
            'stats',
            'dailyStats',
            'dates',
            'pdsJson',
            'errorJson',
            'controlRecords',
            'lotes',
            'selectedLocation'
        ));
    }

    // ═══ MÉTODO: INGRESO MANUAL (GRUPO CONTROL) ═══
    public function pfManual(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'recorded_at' => 'required|date',
            'ce_superficial' => 'required|numeric|min:0',
            'ce_profunda' => 'required|numeric|min:0',
            'events' => 'required|integer|min:1',
            'precision_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $location = Location::findOrFail($request->location_id);

        PFRecord::create([
            'location_id' => $location->id,
            'experimental_group' => $location->experimental_group ?? 'control',
            'recorded_at' => $request->recorded_at,
            'ce_superficial' => $request->ce_superficial,
            'ce_profunda' => $request->ce_profunda,
            'ce_reference' => $request->ce_superficial,
            'ce_measured' => $request->ce_profunda,
            'subparcela' => 'Evento #' . $request->events,  // Guardamos como "Evento #15"
            'pf_percentage' => $request->precision_percentage,  // Aquí guardamos el % de precisión
        ]);

        return back()->with('success', '✅ Registro de Grupo Control guardado correctamente');
    }

    // ═══ MÉTODO: EVALUAR ALERTA INDIVIDUAL (VP/FP/FN) ═══
    public function evaluarAlerta(Request $request, Alert $alert)
    {
        $request->validate([
            'evaluation' => 'required|in:VP,FP,FN',
        ]);

        // Crear o actualizar evaluación
        // Campos REALES: alert_id, lote_id, location_id, label, session_id
        AlertEvaluation::updateOrCreate(
            ['alert_id' => $alert->id],
            [
                'lote_id' => $alert->lote_id,
                'location_id' => $alert->location_id,
                'label' => $request->evaluation,
                'session_id' => session()->getId(),
            ]
        );

        return back()->with('success', "✅ Alerta evaluada como {$request->evaluation}");
    }

    // ═══ MÉTODO: CERRAR DÍA Y CONSOLIDAR EVALUACIONES ═══
    public function cerrarDia(Request $request)
    {
        $today = now()->toDateString();

        // Verificar si ya existe consolidación para hoy
        $existing = DailyConsolidation::where('consolidation_date', $today)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'El día ya fue consolidado. No se puede cerrar dos veces.'
            ], 400);
        }

        // Contar evaluaciones de hoy
        $evaluations = AlertEvaluation::whereDate('created_at', $today)->get();

        $vp = $evaluations->where('label', 'VP')->count();
        $fp = $evaluations->where('label', 'FP')->count();
        $fn = $evaluations->where('label', 'FN')->count();
        $total = $vp + $fp + $fn;

        $pdsPercentage = $total > 0 ? round(($vp / $total) * 100, 2) : 0;
        $errorRate = 100 - $pdsPercentage;

        // Consolidar por cada lote con evaluaciones hoy
        $loteIds = $evaluations->pluck('lote_id')->filter()->unique();

        foreach ($loteIds as $loteId) {
            $loteEvals = $evaluations->where('lote_id', $loteId);
            $loteTotal = $loteEvals->count();

            DailyConsolidation::create([
                'lote_id' => $loteId,
                'consolidation_date' => $today,
                'vp' => $loteEvals->where('label', 'VP')->count(),
                'fp' => $loteEvals->where('label', 'FP')->count(),
                'fn' => $loteEvals->where('label', 'FN')->count(),
                'total_evaluations' => $loteTotal,
                'pds_percentage' => $loteTotal > 0
                    ? round(($loteEvals->where('label', 'VP')->count() / $loteTotal) * 100, 2)
                    : 0,
                'error_rate' => $loteTotal > 0
                    ? round((($loteEvals->where('label', 'FP')->count() + $loteEvals->where('label', 'FN')->count()) / $loteTotal) * 100, 2)
                    : 0,
                'is_closed' => true,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Día cerrado. PDS%: {$pdsPercentage}% (VP: {$vp}, FP: {$fp}, FN: {$fn})"
        ]);
    }

    // ═══ MÉTODO: EXPORTAR ═══
    public function export()
    {
        return back()->with('success', 'Exportación iniciada');
    }
}