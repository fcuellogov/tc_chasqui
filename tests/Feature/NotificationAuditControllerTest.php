<?php

namespace Tests\Feature;

use App\Models\NotificationAttempt;
use App\Models\NotificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function headers(array $overrides = []): array
    {
        return array_merge([
            'X-Chasqui-Key' => config('sistema.chasqui_key'),
        ], $overrides);
    }

    protected function crearRequestConIntento(array $overrides = [], array $intento = []): NotificationRequest
    {
        $notificationRequest = NotificationRequest::create(array_merge([
            'sistema' => 'personal',
            'canal'   => 'slack',
            'mensaje' => 'hola',
            'nivel'   => 'info',
            'estado'  => 'procesado',
        ], $overrides));

        NotificationAttempt::create(array_merge([
            'notification_request_id' => $notificationRequest->id,
            'canal'  => 'slack',
            'estado' => 'enviado',
            'http_status' => 200,
        ], $intento));

        return $notificationRequest;
    }

    public function test_requiere_api_key(): void
    {
        $this->getJson('/api/notificaciones')->assertStatus(401);
    }

    public function test_lista_las_solicitudes_con_sus_intentos(): void
    {
        $this->crearRequestConIntento();

        $response = $this->getJson('/api/notificaciones', $this->headers());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sistema', 'personal')
            ->assertJsonPath('data.0.attempts.0.canal', 'slack');
    }

    public function test_filtra_por_sistema(): void
    {
        $this->crearRequestConIntento(['sistema' => 'salud']);
        $this->crearRequestConIntento(['sistema' => 'personal']);

        $response = $this->getJson('/api/notificaciones?sistema=salud', $this->headers());

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('salud', $response->json('data.0.sistema'));
    }

    public function test_filtra_por_estado(): void
    {
        $this->crearRequestConIntento(['estado' => 'procesado']);
        $this->crearRequestConIntento(['estado' => 'fallido']);

        $response = $this->getJson('/api/notificaciones?estado=fallido', $this->headers());

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('fallido', $response->json('data.0.estado'));
    }

    public function test_muestra_el_detalle_de_una_solicitud_puntual(): void
    {
        $notificationRequest = $this->crearRequestConIntento();

        $response = $this->getJson("/api/notificaciones/{$notificationRequest->id}", $this->headers());

        $response->assertStatus(200)
            ->assertJsonPath('id', $notificationRequest->id)
            ->assertJsonPath('attempts.0.estado', 'enviado');
    }

    public function test_404_si_la_solicitud_no_existe(): void
    {
        $this->getJson('/api/notificaciones/id-inexistente', $this->headers())
            ->assertStatus(404);
    }
}
