<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use App\Notifications\Chasqui\ResultadoEnvio;
use App\Notifications\Chasqui\SlackWebhook;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class MailrelayChannel implements ChannelContract
{
    public static function key(): string
    {
        return 'mailrelay';
    }

    public function shouldHandle(NotificationPayload $payload): bool
    {
        return !empty($payload->dato('destinatarios'));
    }

    public function enqueue(Pool $pool, NotificationPayload $payload): ?ResultadoEnvio
    {
        $destinatariosNormalizados = array_map('trim', $payload->dato('destinatarios', []));

        $correosValidos = array_filter($destinatariosNormalizados, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });

        $correosInvalidos = array_diff($destinatariosNormalizados, $correosValidos);

        if (!empty($correosInvalidos)) {
            $mensaje = "Se intentó enviar una notificación desde {$payload->sistema} pero se detectaron " . count($correosInvalidos) . " direcciones inválidas.";
            $mensaje .= ' Direcciónes inválidas: ' . implode(', ', $correosInvalidos);

            SlackWebhook::post($payload->nivel, $payload->sistema, $mensaje, '*Error al enviar mails masivos*');

            Log::warning('Chasqui [Mailrelay]: Se descartaron correos con formato inválido.', [
                'sistema_origen' => $payload->sistema,
                'cantidad'       => count($correosInvalidos),
                'lista_errores'  => array_values($correosInvalidos),
            ]);
        }

        $totalOriginal = count($destinatariosNormalizados);
        $totalValidos = count($correosValidos);
        $descartados = count($correosInvalidos);

        if ($totalValidos === 0) {
            Log::warning("Chasqui [Mailrelay]: Abortando envío. No se encontraron correos válidos de {$totalOriginal} recibidos. Sistema: {$payload->sistema}");

            return ResultadoEnvio::fallido("No se encontraron correos válidos de {$totalOriginal} recibidos.");
        }

        if ($descartados > 0) {
            Log::info("Chasqui [Mailrelay]: Se descartaron {$descartados} correos inválidos del sistema {$payload->sistema}.");
        }

        $destinatarios = array_map(fn ($email) => ['email' => trim($email)], array_values($correosValidos));

        $pool->as(static::key())
            ->withHeaders([
                'X-AUTH-TOKEN' => config('sistema.mailrelay.token'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post(config('sistema.mailrelay.url'), [
                'from' => [
                    'email' => config('sistema.mailrelay.from.address'),
                    'name'  => config('sistema.mailrelay.from.name'),
                ],
                'to'        => $destinatarios,
                'subject'   => $payload->dato('asunto'),
                'html_part' => $payload->mensaje,
            ]);

        return null;
    }

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): ResultadoEnvio
    {
        if ($response instanceof \Throwable) {
            Log::warning('Chasqui [Mailrelay]: excepción al enviar.', [
                'sistema' => $payload->sistema,
                'error'   => $response->getMessage(),
            ]);

            return ResultadoEnvio::fallido($response->getMessage());
        }

        if ($response->failed()) {
            Log::warning('Chasqui [Mailrelay]: fallo el envío.', [
                'sistema' => $payload->sistema,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return ResultadoEnvio::fallido($response->body(), $response->status());
        }

        return ResultadoEnvio::enviado($response->status());
    }

    public static function rules(): array
    {
        return [
            'destinatarios'   => 'required_if:canal,mailrelay|nullable|array|min:1',
            'destinatarios.*' => 'string',
            'asunto'          => 'required_if:canal,mailrelay|nullable|string|max:50',
        ];
    }
}
