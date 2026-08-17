<?php

namespace App\Notifications\Chasqui;

readonly class NotificationPayload
{
    public function __construct(
        public string $sistema,
        public ?string $canal,
        public string $mensaje,
        public string $nivel,
        public array $datos = [],
    ) {}

    public function dato(string $clave, mixed $default = null): mixed
    {
        return $this->datos[$clave] ?? $default;
    }
}
