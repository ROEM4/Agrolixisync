<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function __construct(
        protected TelegramService $telegram
    ) {}

    /**
     * Enviar alerta al sistema de notificación
     */
    public function dispatch(Alert $alert, bool $isUpdate = false): bool
    {
        try {
            if (!$this->shouldNotify($alert)) {
                return false;
            }

            $message = $this->buildMessage($alert, $isUpdate);
            $buttons = $this->buildButtons($alert);

            return $this->telegram->sendMessageWithButtons($message, $buttons);

        } catch (\Throwable $e) {
            Log::error('AlertService dispatch error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reglas de negocio para decidir si se notifica
     */
    private function shouldNotify(Alert $alert): bool
    {
        // No notificar si ya está resuelta
        if ($alert->is_resolved) {
            return false;
        }

        // Evitar spam básico (si ya fue enviada recientemente)
        if ($this->wasRecentlySent($alert)) {
            return false;
        }

        $settings = is_array($alert->location->alert_settings ?? null)
            ? $alert->location->alert_settings
            : [];

        $severity = $this->normalizeSeverity($alert->severity ?? $alert->level);

        return match ($severity) {
            'ALTA' => $settings['lixiviacion_alta'] ?? true,
            'MEDIA' => $settings['lixiviacion'] ?? true,
            'BAJA'  => $settings['acumulacion'] ?? true,
            default => true,
        };
    }

    /**
     * Normaliza severidad (evita inconsistencias en DB)
     */
    private function normalizeSeverity(?string $severity): string
    {
        return match (strtoupper($severity ?? 'BAJA')) {
            'ALTO', 'ALTA', 'CRITICO', 'CRÍTICO' => 'ALTA',
            'MEDIO', 'MEDIA' => 'MEDIA',
            'BAJO', 'BAJA' => 'BAJA',
            default => 'BAJA',
        };
    }

    /**
     * Evitar spam básico (misma alerta en corto tiempo)
     */
    private function wasRecentlySent(Alert $alert): bool
    {
        if (!$alert->updated_at) {
            return false;
        }

        return $alert->updated_at->diffInSeconds(now()) < 60;
    }

    /**
     * Construcción del mensaje de Telegram
     */
    private function buildMessage(Alert $alert, bool $isUpdate): string
    {
        $lote = $alert->location->lote->name ?? 'N/A';
        $loc  = $alert->location->name ?? 'N/A';

        $severity = $this->normalizeSeverity($alert->severity ?? $alert->level);

        $emoji = match ($severity) {
            'ALTA' => '🔴',
            'MEDIA' => '🟠',
            default => '🟢'
        };

        $title = $isUpdate
            ? "🔄 <b>ACTUALIZACIÓN DE ALERTA</b>"
            : "{$emoji} <b>ALERTA DE LIXIVIACIÓN</b>";

        $ceActual = number_format((float) ($alert->ce_actual ?? 0), 4);
        $deltaCe  = number_format((float) ($alert->delta_ce ?? 0), 4);

        return "{$title}\n"
            . "───────────────────\n"
            . "📍 <b>Lote:</b> {$lote}\n"
            . "📍 <b>Ubicación:</b> {$loc}\n"
            . "⚠️ <b>Nivel:</b> <code>{$severity}</code>\n"
            . "───────────────────\n"
            . "📊 <b>Métricas:</b>\n"
            . "• CE: {$ceActual} dS/m\n"
            . "• ΔCE: {$deltaCe} dS/m\n"
            . "───────────────────\n"
            . "📝 <b>Detalle:</b>\n"
            . "<i>" . ($alert->description ?? 'Detección automática del sistema') . "</i>\n\n"
            . "📅 " . now()->format('d/m/Y H:i:s');
    }

    /**
     * Botones de acción rápida en Telegram
     */
    private function buildButtons(Alert $alert): array
    {
        $url = rtrim(config('app.url'), '/');

        return [
            [
                [
                    'text' => '🔍 Ver alerta',
                    'url' => "{$url}/alertas?alert_id={$alert->id}"
                ]
            ],
            [
                [
                    'text' => '📊 Dashboard',
                    'url' => "{$url}/alertas"
                ],
                [
                    'text' => '✅ Resolver',
                    'url' => "{$url}/alertas/{$alert->id}/quick-resolve"
                ]
            ]
        ];
    }
}