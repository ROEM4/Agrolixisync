<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EvaluacionAlerta;
use App\Models\ConsolidacionDiaria;
use App\Models\Alerta;
use App\Models\Ubicacion;
use App\Models\Planta;
use App\Models\RegistroPorcentajePerdida;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalisisController extends Controller
{
    public function index(Request $request)
    {
        // ═══ UBICACIÓN SELECCIONADA ═══
        $ubicacionSeleccionada = null;
        $ubicacionId = $request->query('ubicacion_id');
        
        // ✅ NUEVO: Manejar caso "all" (todas las plantas)
        $isAllPlants = ($ubicacionId === 'all');
        
        // Si viene un highlight_alert pero no la ubicacion_id, la resolvemos a partir de la alerta
        if (!$ubicacionId && $request->filled('highlight_alert')) {
            $hlAlert = Alerta::find($request->highlight_alert);
            if ($hlAlert) {
                $ubicacionId = $hlAlert->ubicacion_id;
            }
        }
        
        if (!$ubicacionId && session()->has('agro_loc')) {
            $ubicacionId = session('agro_loc');
        }
        
        if ($ubicacionId && $ubicacionId !== 'all') {
            $ubicacionSeleccionada = Ubicacion::with('planta')->find($ubicacionId);
        }

        // ═══ PLANTAS DEL GRUPO EXPERIMENTAL ═══
        $locations = Ubicacion::with('planta')
            ->whereHas('planta', fn($q) => $q->where('grupo_experimental', 'experimental'))
            ->orderBy('nombre')
            ->get();

        // ═══ ALERTAS PENDIENTES ═══
        $pendingAlertasQuery = Alerta::with(['ubicacion.planta'])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereDoesntHave('evaluacion');
        
        if ($isAllPlants) {
            // Si es "all", mostrar alertas de todas las plantas experimentales
            $ubicacionIds = $locations->pluck('id');
            $pendingAlertasQuery->whereIn('ubicacion_id', $ubicacionIds);
        } elseif ($ubicacionSeleccionada) {
            $pendingAlertasQuery->where('ubicacion_id', $ubicacionSeleccionada->id);
        } else {
            $pendingAlertasQuery->whereRaw('1 = 0');
        }
        
        $pendingAlertas = $pendingAlertasQuery->orderByDesc('created_at')->limit(20)->get();

        // ═══ TOTALES ACUMULADOS (VP/FP) ═══
        $evalsQuery = EvaluacionAlerta::query();
        
        if ($isAllPlants) {
            // Si es "all", contar evaluaciones de todas las plantas experimentales
            $ubicacionIds = $locations->pluck('id');
            $evalsQuery->whereIn('ubicacion_id', $ubicacionIds);
        } elseif ($ubicacionSeleccionada) {
            $evalsQuery->where('ubicacion_id', $ubicacionSeleccionada->id);
        } else {
            $evalsQuery->whereRaw('1 = 0');
        }
        
        $vp = (clone $evalsQuery)->where('etiqueta', 'VP')->count();
        $fp = (clone $evalsQuery)->where('etiqueta', 'FP')->count();
        $total = $vp + $fp;

        $pdsPercentage = $total > 0 ? (($vp / $total) * 100) : 0;
        $errorRate = 100 - $pdsPercentage;

        $stats = [
            'vp' => $vp,
            'fp' => $fp,
            'total' => $total,
            'pds_percentage' => round($pdsPercentage, 2),
            'error_rate' => round($errorRate, 2),
        ];

        // ═══ DATOS PARA TABLA IoT ═══
        $today = now()->toDateString();
        $consolidacionHoy = false;
        $evalsHoyCount = 0;

        if ($ubicacionSeleccionada && !$isAllPlants) {
            $consolidacionHoy = ConsolidacionDiaria::where('fecha_consolidacion', $today)
                ->where('planta_id', $ubicacionSeleccionada->planta_id)
                ->exists();
            
            if ($consolidacionHoy) {
                $pendingAlertas = collect();
            }
            
            $evalsHoyCount = EvaluacionAlerta::where('ubicacion_id', $ubicacionSeleccionada->id)
                ->whereDate('created_at', $today)
                ->count();
        }

        // ✅ DEFINIR ESTADO Y MENSAJE DEL DÍA
        if ($isAllPlants) {
            $estadoDia = 'todas_plantas';
            $mensajeDia = '📊 Mostrando datos de todas las plantas del grupo experimental';
        } elseif (!$ubicacionSeleccionada) {
            $estadoDia = 'sin_seleccion';
            $mensajeDia = '🎯 Selecciona una planta del selector superior o desde Monitoreo en Tiempo Real.';
        } elseif ($consolidacionHoy) {
            $estadoDia = 'cerrado';
            $mensajeDia = '🔒 Día cerrado — Los datos están consolidados';
        } elseif ($evalsHoyCount > 0) {
            $estadoDia = 'progreso';
            $mensajeDia = "⏳ En progreso — {$evalsHoyCount} evaluación(es) pendiente(s) de consolidar";
        } else {
            $estadoDia = 'sin_actividad';
            $mensajeDia = '📭 Sin actividad para esta planta. Evalúa alertas para comenzar.';
        }

        // ═══ DATOS PARA TABLA: CONSOLIDACIONES DIARIAS ═══
        $dailyStats = collect();

        if ($isAllPlants) {
            // ✅ NUEVO: Obtener consolidaciones de TODAS las plantas
            $consolidaciones = ConsolidacionDiaria::with('planta')
                ->whereIn('planta_id', $locations->pluck('id'))
                ->orderByDesc('fecha_consolidacion')
                ->get();

            $dailyStats = $consolidaciones->map(function ($day) {
                return [
                    'date' => $day->fecha_consolidacion,
                    'date_label' => Carbon::parse($day->fecha_consolidacion)->format('d/m/Y'),
                    'vp' => $day->vp,
                    'fp' => $day->fp,
                    'pds_percentage' => $day->porcentaje_pds ?? 0,
                    'planta_nombre' => $day->planta?->nombre ?? 'N/D',
                    'planta_numero' => $day->planta?->numero_planta ?? '?',
                    'consolidado' => true,
                ];
            });
        } elseif ($ubicacionSeleccionada) {
            // Obtener consolidaciones de la planta seleccionada
            $consolidaciones = ConsolidacionDiaria::with('planta')
                ->where('planta_id', $ubicacionSeleccionada->planta_id)
                ->orderByDesc('fecha_consolidacion')
                ->get();

            $dailyStats = $consolidaciones->map(function ($day) {
                return [
                    'date' => $day->fecha_consolidacion,
                    'date_label' => Carbon::parse($day->fecha_consolidacion)->format('d/m/Y'),
                    'vp' => $day->vp,
                    'fp' => $day->fp,
                    'pds_percentage' => $day->porcentaje_pds ?? 0,
                    'planta_nombre' => $day->planta?->nombre ?? 'N/D',
                    'planta_numero' => $day->planta?->numero_planta ?? '?',
                    'consolidado' => true,
                ];
            });

            // Si NO hay consolidación para hoy, agregar evaluaciones pendientes de hoy
            if (!$consolidacionHoy) {
                $evalsHoy = EvaluacionAlerta::where('ubicacion_id', $ubicacionSeleccionada->id)
                    ->whereDate('created_at', $today)
                    ->get();

                if ($evalsHoy->count() > 0) {
                    $vpHoy = $evalsHoy->where('etiqueta', 'VP')->count();
                    $fpHoy = $evalsHoy->where('etiqueta', 'FP')->count();
                    $totalHoy = $vpHoy + $fpHoy;

                    $dailyStats->prepend([
                        'date' => $today,
                        'date_label' => Carbon::parse($today)->format('d/m/Y') . ' (Hoy)',
                        'vp' => $vpHoy,
                        'fp' => $fpHoy,
                        'pds_percentage' => $totalHoy > 0 ? round(($vpHoy / $totalHoy) * 100, 2) : 0,
                        'planta_nombre' => $ubicacionSeleccionada->planta?->nombre ?? 'N/D',
                        'planta_numero' => $ubicacionSeleccionada->planta?->numero_planta ?? '?',
                        'consolidado' => false,
                    ]);
                }
            }
        }

        $dailyStats = $dailyStats->sortByDesc('date')->values();

        // ═══ GRÁFICOS ═══
        $dates = $dailyStats->pluck('date_label')->toArray();
        $pdsJson = json_encode($dailyStats->pluck('pds_percentage')->toArray());
        $errorJson = json_encode($dailyStats->map(fn($d) => 100 - $d['pds_percentage'])->toArray());

        // ═══ GRUPO CONTROL ═══
        $controlRecords = RegistroPorcentajePerdida::with('ubicacion.planta')
            ->orderByDesc('fecha_registro')
            ->limit(15)
            ->get()
            ->map(function ($record) {
                $planta = $record->ubicacion?->planta;
                return [
                    'id' => $record->id,
                    'ubicacion_id' => $record->ubicacion_id,
                    'planta' => $planta,
                    'planta_nombre' => $planta?->nombre ?? 'N/D',
                    'planta_numero' => $planta?->numero_planta ?? '?',
                    'fecha_registro' => $record->fecha_registro,
                    'date_label' => Carbon::parse($record->fecha_registro)->format('d/m/Y'),
                    'ce_superficial' => $record->ce_superficial,
                    'ce_profunda' => $record->ce_profunda,
                    'porcentaje_pf' => $record->porcentaje_pf,
                    'subparcela' => $record->subparcela ?? '',
                ];
            });

        // ═══ PLANTAS GC ═══
        $plantasGC = Planta::where('grupo_experimental', 'control')
            ->with('ubicaciones')
            ->orderBy('numero_planta')
            ->get();

        return view('dashboard.analisis', compact(
            'pendingAlertas',
            'stats',
            'dailyStats',
            'dates',
            'pdsJson',
            'errorJson',
            'controlRecords',
            'plantasGC',
            'ubicacionSeleccionada',
            'locations',
            'estadoDia',
            'mensajeDia',
            'isAllPlants'
        ));
    }

    // ═══ MÉTODO: INGRESO MANUAL (GRUPO CONTROL) ═══
    public function pfManual(Request $request)
    {
        $request->validate([
            'ubicacion_id' => 'required|exists:ubicaciones,id',
            'fecha_registro' => 'required|date',
            'ce_superficial' => 'required|numeric|min:0',
            'ce_profunda' => 'required|numeric|min:0',
            'events' => 'required|integer|min:1',
            'porcentaje_pf' => 'required|numeric|min:0|max:100',
        ]);

        $ubicacion = Ubicacion::findOrFail($request->ubicacion_id);

        // Verificar si ya existe un registro para esta fecha y ubicación
        $exists = RegistroPorcentajePerdida::where('ubicacion_id', $ubicacion->id)
            ->whereDate('fecha_registro', $request->fecha_registro)
            ->exists();

        if ($exists) {
            return back()->with('error', '❌ Ya existe un registro para esta fecha y ubicación');
        }

        RegistroPorcentajePerdida::create([
            'ubicacion_id' => $ubicacion->id,
            'grupo_experimental' => $ubicacion->grupo_experimental ?? 'control',
            'fecha_registro' => $request->fecha_registro,
            'ce_superficial' => $request->ce_superficial,
            'ce_profunda' => $request->ce_profunda,
            'ce_referencia' => $request->ce_superficial,
            'ce_medida' => $request->ce_profunda,
            'subparcela' => '' . $request->events,
            'porcentaje_pf' => $request->porcentaje_pf,
        ]);

        return back()->with('success', '✅ Registro de Grupo Control guardado correctamente');
    }

    // ═══ MÉTODO: EVALUAR ALERTA INDIVIDUAL (VP/FP) ═══
    public function evaluarAlerta(Request $request, Alerta $alerta)
    {
        $request->validate([
            'evaluation' => 'required|in:VP,FP',
        ]);

        // Verificar si la alerta ya fue evaluada
        $exists = EvaluacionAlerta::where('alerta_id', $alerta->id)->exists();

        if ($exists) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => '⚠️ Esta alerta ya fue evaluada previamente',
                ], 422);
            }
            return redirect()->route('analisis', [
                'ubicacion_id' => $alerta->ubicacion_id,
            ])->with('error', '⚠️ Esta alerta ya fue evaluada previamente');
        }

        // Crear evaluación
        EvaluacionAlerta::create([
            'alerta_id'    => $alerta->id,
            'planta_id'    => $alerta->planta_id,
            'ubicacion_id' => $alerta->ubicacion_id,
            'etiqueta'     => $request->evaluation,
            'session_id'   => session()->getId(),
        ]);

        // Marcar alerta como resuelta
        $alerta->update([
            'resuelta'         => true,
            'fecha_resolucion' => now(),
            'estado'           => 'RESUELTA',
            'notas_resolucion' => "Evaluada como {$request->evaluation}",
        ]);

        // Disparar cálculo de tiempo de detección si es VP
        if ($request->evaluation === 'VP') {
            $alerta->load('evaluacion');
            \App\Jobs\CalcularTiempoDeteccion::dispatch($alerta);
        }

        // Notificar a Telegram que la alerta ha sido evaluada
        try {
            $telegram = new \App\Services\TelegramService();
            $device = $alerta->ubicacion->codigo_dispositivo ?? 'N/D';
            $msg = "✅ Alerta #{$alerta->id} (Device: {$device}) evaluada como <b>{$request->evaluation}</b>.";
            $telegram->sendMessage($msg);
        } catch (\Exception $e) {
            \Log::error('Error sending Telegram evaluation notification: ' . $e->getMessage());
        }

        // ✅ Si es petición AJAX/fetch, responder con JSON para que el modal pueda cerrarse
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'     => 'success',
                'message'    => "✅ Alerta evaluada como {$request->evaluation}",
                'alerta_id'  => $alerta->id,
                'evaluation' => $request->evaluation,
            ]);
        }

        // Para formularios normales: redirigir
        return redirect()->route('analisis', [
            'ubicacion_id' => $alerta->ubicacion_id,
        ])->with('success', "✅ Alerta evaluada como {$request->evaluation}");
    }

    // ═══ MÉTODO: CERRAR DÍA Y CONSOLIDAR EVALUACIONES ═══
    public function cerrarDia(Request $request)
    {
        $today = now()->toDateString();
        
        // ✅ Obtener ubicación desde el request (POST) o sesión
        $ubicacionId = $request->input('ubicacion_id') ?? session('agro_loc');
        $ubicacion = $ubicacionId ? Ubicacion::find($ubicacionId) : null;

        if (!$ubicacion) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se ha seleccionado ninguna planta para cerrar el día.'
            ], 400);
        }

        // Verificar si ya existe consolidación para hoy y esta planta
        $existing = ConsolidacionDiaria::where('fecha_consolidacion', $today)
            ->where('planta_id', $ubicacion->planta_id)
            ->first();
            
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'El día ya fue consolidado para esta planta. No se puede cerrar dos veces.'
            ], 400);
        }

        // Contar evaluaciones de HOY para esta ubicación
        $evaluations = EvaluacionAlerta::whereDate('created_at', $today)
            ->where('ubicacion_id', $ubicacion->id)
            ->get();

        $vp = $evaluations->where('etiqueta', 'VP')->count();
        $fp = $evaluations->where('etiqueta', 'FP')->count();
        $total = $vp + $fp;

        if ($total === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay evaluaciones para esta planta hoy. Evalúa al menos una alerta antes de cerrar el día.'
            ], 400);
        }

        $pdsPercentage = round(($vp / $total) * 100, 2);

        // Consolidar para esta planta específica
        ConsolidacionDiaria::create([
            'planta_id' => $ubicacion->planta_id,
            'fecha_consolidacion' => $today,
            'vp' => $vp,
            'fp' => $fp,
            'cerrada' => true,
            'cerrada_por' => Auth::id(),
            'fecha_cierre' => now(),
        ]);

        // ✅ LÓGICA NUEVA: Si se cierra el día, TODAS las alertas abiertas de esta planta para hoy,
        // se marcan automáticamente como "RESUELTA" para congelar su TPD y evitar que sigan
        // corriendo para el día siguiente. Quedan como "Sin evaluar" pero cerradas.
        $alertasActualizadas = \App\Models\Alerta::whereDate('tiempo_alerta', $today)
            ->where('ubicacion_id', $ubicacion->id)
            ->where('resuelta', false)
            ->update([
                'resuelta' => true,
                'estado' => 'RESUELTA',
                'fecha_resolucion' => now(),
                'notas_resolucion' => 'Auto-resuelta por cierre de día (Sin evaluar)'
            ]);

        return response()->json([
            'status' => 'success',
            'message' => "Día cerrado para {$ubicacion->planta->nombre} N°{$ubicacion->planta->numero_planta}. PDS%: {$pdsPercentage}% (VP: {$vp}, FP: {$fp})",
            'data' => [
                'vp' => $vp,
                'fp' => $fp,
                'total' => $total,
                'pds_percentage' => $pdsPercentage
            ]
        ]);
    }

    // ═══ MÉTODO: OBTENER UBICACIONES DISPONIBLES PARA INGRESO MANUAL ═══
    public function ubicacionesDisponibles(Request $request)
    {
        $fecha = $request->input('fecha', now()->toDateString());
        
        // Validar formato de fecha
        if (!\DateTime::createFromFormat('Y-m-d', $fecha)) {
            return response()->json([
                'error' => 'Formato de fecha inválido. Use YYYY-MM-DD'
            ], 400);
        }
        
        // Obtener todas las ubicaciones del Grupo Control
        $todasUbicaciones = Ubicacion::with('planta')
            ->whereHas('planta', fn($q) => $q->where('grupo_experimental', 'control'))
            ->get();
        
        // Obtener las ubicaciones que YA tienen registro para esa fecha
        $ubicacionesConRegistro = RegistroPorcentajePerdida::whereDate('fecha_registro', $fecha)
            ->pluck('ubicacion_id')
            ->toArray();
        
        // Filtrar solo las que NO tienen registro
        $ubicacionesDisponibles = $todasUbicaciones->filter(function($ubicacion) use ($ubicacionesConRegistro) {
            return !in_array($ubicacion->id, $ubicacionesConRegistro);
        });
        
        return response()->json([
            'disponibles' => $ubicacionesDisponibles->map(function($ubicacion) {
                return [
                    'id' => $ubicacion->id,
                    'nombre' => $ubicacion->planta->nombre ?? 'N/D',
                    'numero' => $ubicacion->planta->numero_planta ?? '?',
                    'descripcion' => $ubicacion->nombre ?? 'Sin descripción'
                ];
            }),
            'total_disponibles' => $ubicacionesDisponibles->count(),
            'total_registradas' => count($ubicacionesConRegistro),
            'fecha' => $fecha
        ]);
    }

    // ═══ MÉTODO: EXPORTAR ═══
    public function export()
    {
        // TODO: Implementar exportación a Excel/CSV
        return back()->with('success', 'Exportación iniciada');
    }
}