<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $token;
    protected $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $this->chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');
    }

    /**
     * Enviar mensaje genérico a Telegram
     */
    public function sendMessage(string $message, string $parseMode = 'HTML'): bool
    {
        if (!$this->token || !$this->chatId) {
            Log::warning('Telegram Service: Configuración incompleta (Falta TOKEN o CHAT_ID)');
            return false;
        }

        try {
            $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::error('Telegram Service Error: ' . $response->status() . ' - ' . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram Service Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar mensaje con botones inline (para alertas interactivas)
     */
    public function sendMessageWithButtons(string $message, array $buttons, string $parseMode = 'HTML'): bool
    {
        if (!$this->token || !$this->chatId) {
            Log::warning('Telegram Service: Configuración incompleta (Falta TOKEN o CHAT_ID)');
            return false;
        }

        try {
            $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);

            if (!$response->successful()) {
                Log::error('Telegram Service Error (buttons): ' . $response->status() . ' - ' . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram Service Exception (buttons): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía una alerta de lixiviación estructurada CON BOTONES INTERACTIVOS
     */
    public function sendAlert($alert, bool $isUpdate = false): bool
    {
        $loteName = $alert->ubicacion->planta->nombre ?? 'N/A';
        $locationName = $alert->ubicacion->nombre ?? 'N/A';
        $deviceCode = $alert->ubicacion->codigo_dispositivo ?? 'N/D';
        $level = strtoupper($alert->severidad ?? $alert->nivel ?? 'BAJO');

        $emoji = '🟢';
        if (in_array($level, ['ALTO', 'CRÍTICO'])) $emoji = '🔴';
        elseif ($level === 'MEDIO') $emoji = '🟠';

        $duration = "";
        if ($isUpdate && $alert->tiempo_riesgo) {
            $diff = now()->diffInMinutes($alert->tiempo_riesgo);
            $duration = " (⚠️ PERSISTE: {$diff} min)";
        }

        $trend = "";
        if ($alert->ce_actual && $alert->ce_anterior) {
            $diff = $alert->ce_actual - $alert->ce_anterior;
            $trendEmoji = $diff > 0 ? '📈' : ($diff < 0 ? '📉' : '➡️');
            $trend = "\n{$trendEmoji} <b>Tendencia CE:</b> " . ($diff > 0 ? '+' : '') . number_format($diff, 4) . " dS/m";
        }

        $title = $isUpdate ? "🔄 <b>ACTUALIZACIÓN DE ALERTA</b>" : "{$emoji} <b>ALERTA DE LIXIVIACIÓN</b>";
        
        $msg = "{$title}\n"
             . "───────────────────\n"
             . "📍 <b>Planta:</b> {$loteName}\n"
             . "📍 <b>Ubicación:</b> {$locationName}\n"
             . "📍 <b>Device Code:</b> {$deviceCode}\n"
             . "⚠️ <b>Nivel de Riesgo:</b> <code>{$level}</code>{$duration}\n"
             . "───────────────────\n"
             . "📊 <b>Métricas de Control:</b>\n"
             . "• <b>CE Actual:</b> " . number_format($alert->ce_actual ?? 0, 4) . " dS/m"
             . ($trend ?: "") . "\n"
             . "• <b>Δ CE (S-P):</b> " . number_format($alert->delta_ce ?? 0, 4) . " dS/m\n"
             . "• <b>Tiempo (TAR):</b> " . ($alert->tar ?? '--') . " seg\n"
             . "───────────────────\n"
             . "📝 <b>Detalle Técnico y Recomendación:</b>\n"
             . "<i>" . ($alert->descripcion ?? 'Detección automática por sistema de monitoreo.') . "</i>\n"
             . "───────────────────\n"
             . "📅 <i>Generado el: " . now()->format('d/m/Y H:i:s') . "</i>";

        // ═══════════════════════════════════════════════════════════════
        // 🎯 BOTONES INTERACTIVOS — Acción rápida desde Telegram
        // ═══════════════════════════════════════════════════════════════
        $appUrl = rtrim(config('app.url'), '/');
        $alertUrl = "{$appUrl}/alertas?alert_id={$alert->id}";
        $dashboardUrl = "{$appUrl}/alertas";
        $quickResolveUrl = "{$appUrl}/alertas/{$alert->id}/quick-resolve";

        $buttons = [
            // Fila 1: Ver detalle de esta alerta específica
            [
                [
                    'text' => '🔍 Ver esta Alerta',
                    'url' => $alertUrl
                ]
            ],
            // Fila 2: Dashboard completo + Marcar resuelta rápido
            [
                [
                    'text' => '📊 Ver Dashboard',
                    'url' => $dashboardUrl
                ],
                [
                    'text' => '✅ Marcar Resuelta',
                    'url' => $quickResolveUrl
                ]
            ]
        ];

        // Enviar con botones interactivos
        return $this->sendMessageWithButtons($msg, $buttons);
    }
}