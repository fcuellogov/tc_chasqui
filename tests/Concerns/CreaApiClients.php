<?php

namespace Tests\Concerns;

use App\Models\ApiClient;

trait CreaApiClients
{
    /**
     * @return array{0: ApiClient, 1: string} [cliente, clave en texto plano]
     */
    protected function crearApiClient(array $overrides = []): array
    {
        $clave = ApiClient::generarClave();

        $cliente = ApiClient::create(array_merge([
            'sistema'     => 'personal',
            'key_hash'    => ApiClient::hashearClave($clave),
            'detalles'    => 'Cliente de test',
            'es_admin'    => false,
            'fecha_desde' => now()->subDay()->toDateString(),
            'fecha_hasta' => null,
        ], $overrides));

        return [$cliente, $clave];
    }
}
