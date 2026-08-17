<?php

return [
    'chasqui_key' => env('CHASQUI_API_KEY'),
    'rate_limit_por_minuto' => env('CHASQUI_RATE_LIMIT_POR_MINUTO', 120),
    'slack' => [
        'errores_url' => env('SLACK_ERRORES_URL'),
        'alertas_url' => env('SLACK_ALERTAS_URL'),
    ],
    'telegram' => [
        'token' => env('TELEGRAM_API_KEY'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
    'openwa' => [
        'url' => env('OPENWA_URL'),
        'token' => env('OPENWA_API_KEY'),
        'session_id' => env('OPENWA_SESSION_ID'),
    ],
    'mailrelay' => [
        'url' => env('MAILRELAY_URL'),
        'token' => env('MAILRELAY_API_KEY'),
        'from' => [
            'address' => env('MAILRELAY_FROM_ADDRESS'),
            'name' => env('MAILRELAY_FROM_NAME', 'El Chasqui'),
        ],
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