<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Chasqui-Key');

        if ($apiKey !== config('sistema.chasqui_key')) {
            return response()->json(['error' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}