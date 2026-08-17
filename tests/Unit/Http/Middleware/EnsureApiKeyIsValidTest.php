<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnsureApiKeyIsValidTest extends TestCase
{
    use RefreshDatabase;


    public function test_rechaza_si_la_clave_configurada_esta_vacia_aunque_el_header_tambien_lo_este(): void
    {
        Http::fake();
        config(['sistema.chasqui_key' => null]);

        $response = $this->postJson('/api/notificar', [
            'sistema' => 'personal',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], ['X-Chasqui-Key' => '']);

        $response->assertStatus(401);
    }

    public function test_acepta_con_la_clave_correcta(): void
    {
        Http::fake();
        config(['sistema.chasqui_key' => 'clave-de-test']);

        $response = $this->postJson('/api/notificar', [
            'sistema' => 'personal',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], ['X-Chasqui-Key' => 'clave-de-test']);

        $response->assertStatus(202);
    }
}
