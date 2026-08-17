<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use App\Notifications\Chasqui\ResultadoEnvio;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

interface ChannelContract
{
    public static function key(): string;

    public function shouldHandle(NotificationPayload $payload): bool;

    /**
     * Encola en el pool el/los request(s) HTTP de este canal.
     * Puede hacer trabajo previo (validaciones, avisos) fuera del pool.
     *
     * Devuelve null si encoló un request y hay que esperar handleResponse(),
     * o un ResultadoEnvio si, tras ese trabajo previo, ya se decidió el
     * resultado sin necesidad de llamar a nadie (p.ej. mailrelay sin
     * destinatarios válidos).
     */
    public function enqueue(Pool $pool, NotificationPayload $payload): ?ResultadoEnvio;

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): ResultadoEnvio;

    /**
     * Reglas de validación (nombres de campo tal como llegan en el request,
     * planos, sin prefijo) que este canal necesita.
     */
    public static function rules(): array;
}
