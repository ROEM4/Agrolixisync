<?php

namespace App\Services;

use App\Models\Alerta;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function __construct(
        protected TelegramService $telegram
    ) {}

    /**
     * Enviar alerta al sistema de notificación
     */
    public function dispatch(Alerta $alert, bool $isUpdate = false): bool
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
    private function shouldNotify(Alerta $alert): bool
    {
        if ($alert->resuelta) {
            return false;
        }

        if ($this->wasRecentlySent($alert)) {
            return false;
        }

        $settings = is_array($alert->ubicacion->configuracion_alertas ?? null)
            ? $alert->ubicacion->configuracion_alertas
            : [];

        // Si no hay configuración guardada, notificar siempre
        if (empty($settings)) {
            return true;
        }

        // Mapear nivel de alerta a clave de configuración de alertas.blade.php
        $severity = strtoupper($alert->severidad ?? $alert->nivel ?? '');
        $key = match ($severity) {
            'ALTO', 'ALTA'            => 'lixiviacion_alta',
            'MEDIO', 'MEDIA'          => 'lixiviacion_media',
            'BAJO', 'BAJA'            => 'lixiviacion_baja',
            default                   => null,
        };

        if ($key === null) {
            return true;
        }

        return $settings[$key] ?? false;
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
    private function wasRecentlySent(Alerta $alert): bool
    {
        // Para alertas nuevas (recién creadas) siempre notificar
        if ($alert->wasRecentlyCreated ?? false) {
            return false;
        }

        // Anti-spam: no reenviar si se actualizó hace menos de 5 minutos
        if (!$alert->updated_at) {
            return false;
        }

        return $alert->updated_at->diffInSeconds(now()) < 300;
    }

    /**
     * Construcción del mensaje de Telegram
     */
    private function buildMessage(Alerta $alert, bool $isUpdate): string
    {
        $lote = $alert->ubicacion->planta->nombre ?? 'N/A';
        $loc  = $alert->ubicacion->nombre ?? 'N/A';

        $severity = $this->normalizeSeverity($alert->severidad ?? $alert->nivel);

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
            . "📍 <b>Planta:</b> {$lote}\n"
            . "📍 <b>Ubicación:</b> {$loc}\n"
            . "⚠️ <b>Nivel:</b> <code>{$severity}</code>\n"
            . "───────────────────\n"
            . "📊 <b>Métricas:</b>\n"
            . "• CE: {$ceActual} dS/m\n"
            . "• ΔCE: {$deltaCe} dS/m\n"
            . "───────────────────\n"
            . "📝 <b>Detalle:</b>\n"
            . "<i>" . ($alert->descripcion ?? 'Detección automática del sistema') . "</i>\n\n"
            . "📅 " . now()->format('d/m/Y H:i:s');
    }

    /**
     * Botones de acción rápida en Telegram
     */
    private function buildButtons(Alerta $alert): array
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