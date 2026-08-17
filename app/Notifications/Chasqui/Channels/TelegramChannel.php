<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class TelegramChannel implements ChannelContract
{
    public static function key(): string
    {
        return 'telegram';
    }

    public function shouldHandle(NotificationPayload $payload): bool
    {
        return true;
    }

    public function enqueue(Pool $pool, NotificationPayload $payload): bool
    {
        $token = config('sistema.telegram.token');
        $chatId = config('sistema.telegram.chat_id');

        $html = '<b>🖥️ Sistema:</b> ' . e(strtoupper($payload->sistema)) . "\n";
        $html .= '<b>📢 Mensaje:</b> ' . e($payload->mensaje) . "\n";
        $html .= '<b>📊 Nivel:</b> #' . e(strtoupper($payload->nivel));

        $pool->as(static::key())
            ->timeout(5)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $html,
                'parse_mode' => 'HTML',
            ]);

        return true;
    }

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): void
    {
        if ($response instanceof \Throwable) {
            Log::warning('Chasqui [Telegram]: excepción al enviar.', [
                'sistema' => $payload->sistema,
                'error'   => $response->getMessage(),
            ]);

            return;
        }

        if ($response->failed()) {
            Log::warning('Chasqui [Telegram]: fallo el envío.', [
                'sistema' => $payload->sistema,
                'status'  => $response->status(),
            ]);
        }
    }

    public static function rules(): array
    {
        return [];
    }
}
