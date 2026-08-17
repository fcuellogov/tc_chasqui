<?php

namespace App\Notifications\Chasqui;

use Illuminate\Support\Facades\Http;

class SlackWebhook
{
    public static function post(string $nivel, string $sistema, string $texto, string $pretext = '*Nuevo evento de sistema*'): void
    {
        Http::post(static::url($nivel), static::body($nivel, $sistema, $texto, $pretext));
    }

    public static function url(string $nivel): ?string
    {
        return match ($nivel) {
            'error'   => config('sistema.slack.errores_url'),
            'success' => config('sistema.slack.alertas_url'),
            default   => config('sistema.slack.alertas_url'),
        };
    }

    public static function body(string $nivel, string $sistema, string $texto, string $pretext = '*Nuevo evento de sistema*'): array
    {
        return [
            'attachments' => [[
                'fallback'    => "Nuevo aviso de {$sistema}",
                'color'       => static::color($nivel),
                'pretext'     => $pretext,
                'author_name' => '🖥️ Microservicio: ' . strtoupper($sistema),
                'text'        => $texto,
                'fields'      => [[
                    'title' => '📊 Nivel',
                    'value' => strtoupper($nivel),
                    'short' => true,
                ]],
                'footer' => 'El Chasqui',
            ]],
        ];
    }

    protected static function color(string $nivel): string
    {
        return match ($nivel) {
            'error'   => '#E01E5A',
            'success' => '#2EB67D',
            default   => '#36C5F0',
        };
    }
}
