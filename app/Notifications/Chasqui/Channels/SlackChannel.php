<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use App\Notifications\Chasqui\SlackWebhook;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SlackChannel implements ChannelContract
{
    public static function key(): string
    {
        return 'slack';
    }

    public function shouldHandle(NotificationPayload $payload): bool
    {
        return true;
    }

    public function enqueue(Pool $pool, NotificationPayload $payload): bool
    {
        $pool->as(static::key())
            ->timeout(5)
            ->post(SlackWebhook::url($payload->nivel), SlackWebhook::body($payload->nivel, $payload->sistema, $payload->mensaje));

        return true;
    }

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): void
    {
        if ($response instanceof \Throwable) {
            Log::warning('Chasqui [Slack]: excepción al enviar.', [
                'sistema' => $payload->sistema,
                'error'   => $response->getMessage(),
            ]);

            return;
        }

        if ($response->failed()) {
            Log::warning('Chasqui [Slack]: fallo el envío.', [
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
