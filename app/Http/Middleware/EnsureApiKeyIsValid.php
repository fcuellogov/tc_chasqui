<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $claveConfigurada = (string) config('sistema.chasqui_key');
        $claveRecibida = (string) $request->header('X-Chasqui-Key');

        if ($claveConfigurada === '' || !hash_equals($claveConfigurada, $claveRecibida)) {
            return response()->json(['error' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
