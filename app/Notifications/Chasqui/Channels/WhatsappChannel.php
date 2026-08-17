<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use App\Notifications\Chasqui\ResultadoEnvio;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class WhatsappChannel implements ChannelContract
{
    public static function key(): string
    {
        return 'whatsapp';
    }

    public function shouldHandle(NotificationPayload $payload): bool
    {
        return !empty($payload->dato('telefono'));
    }

    public function enqueue(Pool $pool, NotificationPayload $payload): ?ResultadoEnvio
    {
        $request = $pool->as(static::key())
            ->withHeaders([
                'X-API-Key'    => config('sistema.openwa.token'),
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(10);

        $template = $payload->dato('template');

        if ($template) {
            $request->post($this->endpoint('send-template'), [
                'chatId'       => $this->chatId($payload),
                'templateName' => $template,
                'vars'         => $payload->dato('parametros', []),
            ]);
        } else {
            $request->post($this->endpoint('send-text'), [
                'chatId' => $this->chatId($payload),
                'text'   => $this->textoPlano($payload),
            ]);
        }

        return null;
    }

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): ResultadoEnvio
    {
        if ($response instanceof \Throwable) {
            Log::warning('Chasqui [Whatsapp/OpenWA]: excepción al enviar.', [
                'sistema' => $payload->sistema,
                'error'   => $response->getMessage(),
            ]);

            return ResultadoEnvio::fallido($response->getMessage());
        }

        if ($response->failed()) {
            Log::warning('Chasqui [Whatsapp/OpenWA]: fallo el envío.', [
                'sistema' => $payload->sistema,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return ResultadoEnvio::fallido($response->body(), $response->status());
        }

        return ResultadoEnvio::enviado($response->status());
    }

    protected function endpoint(string $accion): string
    {
        $baseUrl = rtrim(config('sistema.openwa.url'), '/');
        $sessionId = config('sistema.openwa.session_id');

        return "{$baseUrl}/api/sessions/{$sessionId}/messages/{$accion}";
    }

    protected function chatId(NotificationPayload $payload): string
    {
        return $payload->dato('telefono') . '@c.us';
    }

    protected function textoPlano(NotificationPayload $payload): string
    {
        $texto = '🖥️ *Sistema:* ' . strtoupper($payload->sistema) . "\n";
        $texto .= '📢 *Mensaje:* ' . $payload->mensaje . "\n";
        $texto .= '📊 *Nivel:* ' . strtoupper($payload->nivel);

        return $texto;
    }

    public static function rules(): array
    {
        return [
            'telefono'   => 'required_if:canal,whatsapp|nullable|string|regex:/^[0-9]+$/',
            'template'   => 'nullable|string',
            'parametros' => 'nullable|array',
        ];
    }
}
