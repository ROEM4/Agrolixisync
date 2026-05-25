# Configuración de Alertas por Telegram - AgroLixiSync

Para integrar las alertas del sistema directamente a tu celular a través de Telegram, seguiremos estos pasos. Es posible y muy eficiente para monitoreo en tiempo real.

## 1. Crear el Bot en Telegram
1. Abre Telegram y busca a **@BotFather**.
2. Envía el comando `/newbot`.
3. Sigue las instrucciones: elige un nombre para tu bot y un nombre de usuario (debe terminar en `bot`).
4. **Guarda el Token de API** que te proporcionará (ejemplo: `734567890:AAH_...`).

## 2. Obtener tu Chat ID
Para que el bot sepa a quién enviarle los mensajes, necesitamos tu ID de chat:
1. Busca tu bot recién creado en Telegram y presiona **Iniciar**.
2. Envía un mensaje cualquiera al bot.
3. Accede a la siguiente URL en tu navegador reemplazando `TU_TOKEN` por el token obtenido:
   `https://api.telegram.org/botTU_TOKEN/getUpdates`
4. Busca en el JSON el campo `"id":` dentro del objeto `"chat"`. Ese es tu **Chat ID**.

## 3. Configuración en Laravel (.env)
Añade las siguientes variables a tu archivo `.env`:

```env
TELEGRAM_BOT_TOKEN=tu_token_aqui
TELEGRAM_CHAT_ID=tu_chat_id_aqui
```

## 4. Implementación del Servicio
Crearemos un servicio simple para manejar el envío.

### Paso A: Crear el Servicio `TelegramService`
Crea el archivo `app/Services/TelegramService.php`:

```php
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
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
    }

    public function sendMessage($message)
    {
        if (!$this->token || !$this->chatId) {
            Log::warning('Telegram no configurado: Falta TOKEN o CHAT_ID');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error enviando mensaje a Telegram: ' . $e->getMessage());
            return false;
        }
    }
}
```

## 5. Conectar con el Generador de Alertas
Debemos modificar `app/Services/LixiviationAnalysisService.php` para que llame a este servicio cuando se genera una alerta crítica.

### Ejemplo de modificación en `generateAlert`:

```php
// Inyectar TelegramService en el constructor o usar resolve()
$telegram = resolve(TelegramService::class);

$msg = "🚨 <b>NUEVA ALERTA DE LIXIVIACIÓN</b>\n\n"
     . "📍 <b>Lote:</b> {$analysis->lote->name}\n"
     . "⚠️ <b>Nivel:</b> " . strtoupper($level) . "\n"
     . "📝 <b>Detalle:</b> {$descriptions[$level]}\n"
     . "💡 <b>Recom:</b> {$recommendations[$level]}";

$telegram->sendMessage($msg);
```

---
**Nota:** Para un sistema más robusto, se recomienda usar el sistema de **Notifications** nativo de Laravel con el canal de Telegram (`laravel-notification-channels/telegram`), pero este método manual es el más rápido para empezar sin instalar dependencias adicionales pesadas.
