<?php

namespace App\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

interface ChannelContract
{
    public static function key(): string;

    public function shouldHandle(NotificationPayload $payload): bool;

    /**
     * Encola en el pool el/los request(s) HTTP de este canal.
     * Puede hacer trabajo previo (validaciones, avisos) fuera del pool.
     * Devuelve false si, tras ese trabajo previo, no hay nada para enviar
     * (en cuyo caso handleResponse() no será invocado para este canal).
     */
    public function enqueue(Pool $pool, NotificationPayload $payload): bool;

    public function handleResponse(NotificationPayload $payload, Response|\Throwable $response): void;

    /**
     * Reglas de validación (nombres de campo tal como llegan en el request,
     * planos, sin prefijo) que este canal necesita.
     */
    public static function rules(): array;
}
