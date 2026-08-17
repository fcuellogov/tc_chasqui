<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaApiClients;
use Tests\TestCase;

class EnsureApiKeyIsValidTest extends TestCase
{
    use RefreshDatabase, CreaApiClients;

    protected function payload(): array
    {
        return ['mensaje' => 'hola', 'nivel' => 'info'];
    }

    public function test_rechaza_sin_header(): void
    {
        Http::fake();

        $this->postJson('/api/notificar', $this->payload())->assertStatus(401);
    }

    public function test_rechaza_clave_desconocida(): void
    {
        Http::fake();

        $this->postJson('/api/notificar', $this->payload(), ['X-Chasqui-Key' => 'clave-inexistente'])
            ->assertStatus(401);
    }

    public function test_rechaza_clave_vencida(): void
    {
        Http::fake();

        [, $clave] = $this->crearApiClient([
            'fecha_desde' => now()->subMonths(2)->toDateString(),
            'fecha_hasta' => now()->subDay()->toDateString(),
        ]);

        $this->postJson('/api/notificar', $this->payload(), ['X-Chasqui-Key' => $clave])
            ->assertStatus(401);
    }

    public function test_rechaza_clave_que_todavia_no_es_valida(): void
    {
        Http::fake();

        [, $clave] = $this->crearApiClient([
            'fecha_desde' => now()->addDay()->toDateString(),
        ]);

        $this->postJson('/api/notificar', $this->payload(), ['X-Chasqui-Key' => $clave])
            ->assertStatus(401);
    }

    public function test_acepta_clave_valida(): void
    {
        Http::fake();

        [, $clave] = $this->crearApiClient();

        $this->postJson('/api/notificar', $this->payload(), ['X-Chasqui-Key' => $clave])
            ->assertStatus(202);
    }
}
