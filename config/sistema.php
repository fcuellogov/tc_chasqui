<?php

return [
    'chasqui_key' => env('CHASQUI_API_KEY'),
    'slack' => [
        'errores_url' => env('SLACK_ERRORES_URL'),
        'alertas_url' => env('SLACK_ALERTAS_URL'),
    ],
    'telegram' => [
        'token' => env('TELEGRAM_API_KEY'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
    'whatsapp' => [
        'url' => env('WHATSAPP_URL'),
        'token' => env('WHATSAPP_API_KEY'),
    ],
    'servicios' => [
        ['nombre' => 'Personal', 'url' => 'https://personal.tccatamarca.online'],
        ['nombre' => 'Jefatura', 'url' => 'https://jefatura.tccatamarca.online'],
        ['nombre' => 'Agentes', 'url' => 'https://agentes.tccatamarca.online'],
        ['nombre' => 'Salud', 'url' => 'https://salud.tccatamarca.online'],
        ['nombre' => 'Liquidaciones', 'url' => 'https://liquidaciones.tccatamarca.online'],
        //['nombre' => 'Auth', 'url' => 'https://auth.tccatamarca.online'],
    ],
];