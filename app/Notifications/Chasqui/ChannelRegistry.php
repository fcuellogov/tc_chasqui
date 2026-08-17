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
     * Filtra los canales aplicables y dispara sus requests HTTP concurrentemente
     * (en vez de uno detrás del otro), así el tiempo total queda acotado por el
     * canal más lento en vez de por la suma de todos.
     */
    public static function dispatch(NotificationPayload $payload): void
    {
        $activos = [];

        foreach (static::all() as $channel) {
            if (!is_null($payload->canal) && $payload->canal !== $channel::key()) {
                continue;
            }

            if (!$channel->shouldHandle($payload)) {
                continue;
            }

            $activos[$channel::key()] = $channel;
        }

        if (empty($activos)) {
            return;
        }

        $enviados = [];

        $respuestas = Http::pool(function (Pool $pool) use ($activos, $payload, &$enviados) {
            foreach ($activos as $clave => $channel) {
                if ($channel->enqueue($pool, $payload)) {
                    $enviados[] = $clave;
                }
            }
        }, concurrency: count($activos));

        foreach ($enviados as $clave) {
            $activos[$clave]->handleResponse($payload, $respuestas[$clave]);
        }
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
