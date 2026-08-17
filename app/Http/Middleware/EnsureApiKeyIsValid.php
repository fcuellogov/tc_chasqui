<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;

class EnsureApiKeyIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $clave = (string) $request->header('X-Chasqui-Key');

        if ($clave === '') {
            return response()->json(['error' => 'No autorizado.'], 401);
        }

        $cliente = ApiClient::buscarPorClave($clave);

        if (!$cliente || !$cliente->estaVigente()) {
            return response()->json(['error' => 'No autorizado.'], 401);
        }

        $request->attributes->set('api_client', $cliente);

        return $next($request);
    }
}
