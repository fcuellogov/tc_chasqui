<?php

namespace App\Notifications\Chasqui;

final class ResultadoEnvio
{
    private function __construct(
        public readonly string $estado, // enviado | fallido | omitido
        public readonly ?int $httpStatus = null,
        public readonly ?string $detalle = null,
    ) {}

    public static function enviado(?int $httpStatus = null, ?string $detalle = null): self
    {
        return new self('enviado', $httpStatus, static::truncar($detalle));
    }

    public static function fallido(?string $detalle = null, ?int $httpStatus = null): self
    {
        return new self('fallido', $httpStatus, static::truncar($detalle));
    }

    public static function omitido(string $detalle): self
    {
        return new self('omitido', null, static::truncar($detalle));
    }

    protected static function truncar(?string $texto, int $limite = 500): ?string
    {
        if ($texto === null) {
            return null;
        }

        return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite) . '…' : $texto;
    }
}
