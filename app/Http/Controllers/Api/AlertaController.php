<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\EvaluacionAlerta;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertaController extends Controller
{
    /**
     * Listar alertas con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alerta::with(['ubicacion.planta', 'analisis', 'evaluacion']);

        // Filtro por ubicación
        if ($request->filled('location_id')) {
            $query->where('ubicacion_id', $request->location_id);
        }

        // Filtro por nivel de riesgo
        if ($request->filled('risk_level')) {
            $query->where('severidad', $request->risk_level);
        }

        // Filtro por estado
        if ($request->filled('status')) {
            if ($request->status === 'open') {
                $query->where('resuelta', false);
            } elseif ($request->status === 'resolved') {
                $query->where('resuelta', true);
            }
        }

        // Filtro por período
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case '24h': $query->where('created_at', '>=', now()->subHours(24)); break;
                case '7d':  $query->where('created_at', '>=', now()->subDays(7)); break;
                case '14d': $query->where('created_at', '>=', now()->subDays(14)); break;
                case '30d': $query->where('created_at', '>=', now()->subDays(30)); break;
            }
        }

        $limit = $request->get('limit', 50);
        $alertas = $query->orderByDesc('created_at');

        if ($limit !== 'all') {
            $alertas = $alertas->limit((int) $limit);
        }

        $alertas = $alertas->get();

        // ✅ Transformar para el frontend
        $data = $alertas->map(function ($alerta) {
            return [
                'id' => $alerta->id,
                'ubicacion_id' => $alerta->ubicacion_id,
                'planta_id' => $alerta->planta_id,
                'tipo' => $alerta->tipo,
                'severidad' => $alerta->severidad,
                'level' => $alerta->severidad, // ✅ Alias para compatibilidad
                'nivel' => $alerta->nivel,
                'status' => $alerta->estado,
                'descripcion' => $alerta->descripcion,
                'resuelta' => $alerta->resuelta,
                'is_resolved' => $alerta->resuelta, // ✅ Alias para compatibilidad
                'fecha_resolucion' => $alerta->fecha_resolucion,
                'created_at' => $alerta->created_at,
                'tiempo_alerta' => $alerta->tiempo_alerta,
                'tiempo_riesgo' => $alerta->tiempo_riesgo,
                'tar' => $alerta->tar,
                'subparcela' => $alerta->subparcela,
                'ubicacion' => $alerta->ubicacion ? [
                    'id' => $alerta->ubicacion->id,
                    'nombre' => $alerta->ubicacion->nombre,
                    'planta' => $alerta->ubicacion->planta ? [
                        'id' => $alerta->ubicacion->planta->id,
                        'nombre' => $alerta->ubicacion->planta->nombre,
                        'numero_planta' => $alerta->ubicacion->planta->numero_planta,
                    ] : null,
                ] : null,
                'analysis' => $alerta->analisis ? [
                    'ilx' => $alerta->analisis->ilx,
                    'ilx_estado' => $alerta->analisis->ilx_estado,
                ] : null,
                // ✅ Evaluación con etiqueta (VP/FP)
                'evaluation' => $alerta->evaluacion ? [
                    'resultado' => $alerta->evaluacion->etiqueta, // VP o FP
                    'label' => $alerta->evaluacion->etiqueta === 'VP' ? '✔ VP' : '❌ FP',
                    'etiqueta' => $alerta->evaluacion->etiqueta,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Resolver alerta
     */
    public function resolve(Alerta $alerta): JsonResponse
    {
        $alerta->update([
            'resuelta' => true,
            'fecha_resolucion' => now(),
            'estado' => 'RESUELTA',
            'notas_resolucion' => 'Resuelta manualmente desde el panel',
        ]);

        // Intentar notificar por Telegram
        try {
            if (env('TELEGRAM_BOT_TOKEN')) {
                $telegram = resolve(\App\Services\TelegramService::class);
                $plantaName = $alerta->ubicacion->planta->nombre ?? $alerta->ubicacion->nombre;

                $telegram->sendMessage(
                    "✅ <b>ALERTA RESUELTA</b>\n" .
                    "───────────────────\n" .
                    "📍 <b>Planta:</b> {$plantaName}\n" .
                    "🕐 <b>Resuelta:</b> " . now()->format('d/m/Y H:i')
                );
            }
        } catch (\Exception $e) {
            \Log::error('Error Telegram: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Alerta resuelta correctamente',
        ]);
    }
}