<?php

namespace App\Notifications\Chasqui;

use App\Notifications\Chasqui\Channels\ChannelContract;
use App\Notifications\Chasqui\Channels\MailrelayChannel;
use App\Notifications\Chasqui\Channels\SlackChannel;
use App\Notifications\Chasqui\Channels\TelegramChannel;
use App\Notifications\Chasqui\Channels\WhatsappChannel;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ChannelRegistry
{
    /** @var class-string<ChannelContract>[] */
    protected static array $channels = [
        SlackChannel::class,
        TelegramChannel::class,
        WhatsappChannel::class,
        MailrelayChannel::class,
    ];

    /** @return ChannelContract[] */
    public static function all(): array
    {
        return array_map(fn (string $class) => app($class), static::$channels);
    }

    public static function keys(): array
    {
        return array_map(fn (string $class) => $class::key(), static::$channels);
    }

    /**
     * Filtra los canales en alcance (según el canal pedido), dispara sus
     * requests HTTP concurrentemente y devuelve el resultado de cada uno
     * (clave = nombre del canal), incluyendo los que se omitieron.
     *
     * @return array<string, ResultadoEnvio>
     */
    public static function dispatch(NotificationPayload $payload): array
    {
        $enAlcance = [];

        foreach (static::all() as $channel) {
            if (!is_null($payload->canal) && $payload->canal !== $channel::key()) {
                continue;
            }

            $enAlcance[$channel::key()] = $channel;
        }

        $resultados = [];
        $activos = [];

        foreach ($enAlcance as $clave => $channel) {
            if ($channel->shouldHandle($payload)) {
                $activos[$clave] = $channel;
            } else {
                $resultados[$clave] = ResultadoEnvio::omitido(
                    'El canal no aplica: faltan los datos requeridos para esta notificación.'
                );
            }
        }

        if (!empty($activos)) {
            $enviados = [];

            $respuestas = Http::pool(function (Pool $pool) use ($activos, $payload, &$enviados, &$resultados) {
                foreach ($activos as $clave => $channel) {
                    $resultado = $channel->enqueue($pool, $payload);

                    if ($resultado === null) {
                        $enviados[] = $clave;
                    } else {
                        $resultados[$clave] = $resultado;
                    }
                }
            }, concurrency: count($activos));

            foreach ($enviados as $clave) {
                $resultados[$clave] = $activos[$clave]->handleResponse($payload, $respuestas[$clave]);
            }
        }

        return $resultados;
    }

    public static function validationRules(): array
    {
        $rules = [];

        foreach (static::$channels as $class) {
            $rules = array_merge($rules, $class::rules());
        }

        return $rules;
    }

    /**
     * Todo lo validado que no sea uno de los campos universales queda
     * disponible para los canales como "datos" (telefono, destinatarios,
     * asunto, template, parametros, etc.).
     */
    public static function extractDatos(array $validated): array
    {
        return Arr::except($validated, ['sistema', 'canal', 'mensaje', 'nivel']);
    }
}
