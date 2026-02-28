<?php

use Illuminate\Support\Str;

return [
    'chasqui_key' => env('CHASQUI_API_KEY'),
    'slack' => [
        'errores_url' => env('SLACK_ERRORES_URL'),
        'alertas_url' => env('SLACK_ALERTAS_URL'),
    ],
];