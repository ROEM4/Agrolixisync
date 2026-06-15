<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    /**
     * Listar alertas con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alert::with(['location.lote', 'analysis', 'evaluation']);
        
        // Filtro por ubicación
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        
        // Filtro por nivel de riesgo
        if ($request->filled('risk_level')) {
            $query->where('severity', $request->risk_level);
        }
        
        // Filtro por estado
        if ($request->filled('status')) {
            if ($request->status === 'open') {
                $query->where('is_resolved', false);
            } elseif ($request->status === 'resolved') {
                $query->where('is_resolved', true);
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
        $alerts = $query->orderByDesc('created_at');
        
        if ($limit !== 'all') {
            $alerts = $alerts->limit((int) $limit);
        }
        
        $alerts = $alerts->get();
        
        // Transformar para el frontend
        $data = $alerts->map(function ($alert) {
            return [
                'id' => $alert->id,
                'location_id' => $alert->location_id,
                'lote_id' => $alert->lote_id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'level' => $alert->level,
                'status' => $alert->status,
                'description' => $alert->description,
                'is_resolved' => $alert->is_resolved,
                'resolved_at' => $alert->resolved_at,
                'created_at' => $alert->created_at,
                'tiempo_alerta' => $alert->tiempo_alerta,
                'tiempo_riesgo' => $alert->tiempo_riesgo,
                'tar' => $alert->tar,
                'subparcela' => $alert->subparcela,
                'location' => $alert->location ? [
                    'id' => $alert->location->id,
                    'name' => $alert->location->name,
                    'lote' => $alert->location->lote ? [
                        'id' => $alert->location->lote->id,
                        'name' => $alert->location->lote->name,
                        'plant_number' => $alert->location->lote->plant_number,
                    ] : null,
                ] : null,
                'analysis' => $alert->analysis ? [
                    'ilx' => $alert->analysis->ilx,
                    'ilx_estado' => $alert->analysis->ilx_estado,
                ] : null,
                'evaluation' => $alert->evaluation ? [
                    'label' => $alert->evaluation->label,
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
    public function resolve(Alert $alert): JsonResponse
    {
        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'status' => 'RESOLVED',
            'resolution_notes' => 'Resuelta manualmente desde el panel',
        ]);
        
        // Intentar notificar por Telegram
        try {
            if (env('TELEGRAM_BOT_TOKEN')) {
                $telegram = resolve(\App\Services\TelegramService::class);
                $loteName = $alert->location->lote->name ?? $alert->location->name;
                
                $telegram->sendMessage(
                    "✅ <b>ALERTA RESUELTA</b>\n" .
                    "───────────────────\n" .
                    "📍 <b>Planta:</b> {$loteName}\n" .
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