<?php

namespace Tests\Feature;

use App\Models\NotificationAttempt;
use App\Models\NotificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaApiClients;
use Tests\TestCase;

class NotificationAuditControllerTest extends TestCase
{
    use RefreshDatabase, CreaApiClients;

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

    public function test_un_sistema_no_admin_solo_ve_sus_propias_solicitudes(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal']);

        $this->crearRequestConIntento(['sistema' => 'personal']);
        $this->crearRequestConIntento(['sistema' => 'salud']);

        $response = $this->getJson('/api/notificaciones', ['X-Chasqui-Key' => $clave]);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('personal', $response->json('data.0.sistema'));
    }

    public function test_un_admin_ve_todas_las_solicitudes_y_puede_filtrar_por_sistema(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'auditor', 'es_admin' => true]);

        $this->crearRequestConIntento(['sistema' => 'personal']);
        $this->crearRequestConIntento(['sistema' => 'salud']);

        $this->getJson('/api/notificaciones', ['X-Chasqui-Key' => $clave])
            ->assertStatus(200)->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/notificaciones?sistema=salud', ['X-Chasqui-Key' => $clave]);
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('salud', $response->json('data.0.sistema'));
    }

    public function test_un_sistema_no_admin_no_puede_forzar_ver_otro_sistema_por_query_string(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal']);

        $this->crearRequestConIntento(['sistema' => 'personal']);
        $this->crearRequestConIntento(['sistema' => 'salud']);

        // Intenta pedir "salud" por query string estando autenticado como "personal": se ignora.
        $response = $this->getJson('/api/notificaciones?sistema=salud', ['X-Chasqui-Key' => $clave]);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('personal', $response->json('data.0.sistema'));
    }

    public function test_filtra_por_estado_dentro_del_propio_alcance(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal']);

        $this->crearRequestConIntento(['sistema' => 'personal', 'estado' => 'procesado']);
        $this->crearRequestConIntento(['sistema' => 'personal', 'estado' => 'fallido']);

        $response = $this->getJson('/api/notificaciones?estado=fallido', ['X-Chasqui-Key' => $clave]);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('fallido', $response->json('data.0.estado'));
    }

    public function test_muestra_el_detalle_de_una_solicitud_propia(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal']);
        $notificationRequest = $this->crearRequestConIntento(['sistema' => 'personal']);

        $response = $this->getJson("/api/notificaciones/{$notificationRequest->id}", ['X-Chasqui-Key' => $clave]);

        $response->assertStatus(200)
            ->assertJsonPath('id', $notificationRequest->id)
            ->assertJsonPath('attempts.0.estado', 'enviado');
    }

    public function test_404_al_intentar_ver_el_detalle_de_otro_sistema(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal']);
        $ajena = $this->crearRequestConIntento(['sistema' => 'salud']);

        $this->getJson("/api/notificaciones/{$ajena->id}", ['X-Chasqui-Key' => $clave])
            ->assertStatus(404);
    }

    public function test_un_admin_puede_ver_el_detalle_de_cualquier_sistema(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'auditor', 'es_admin' => true]);
        $ajena = $this->crearRequestConIntento(['sistema' => 'salud']);

        $this->getJson("/api/notificaciones/{$ajena->id}", ['X-Chasqui-Key' => $clave])
            ->assertStatus(200);
    }

    public function test_404_si_la_solicitud_no_existe(): void
    {
        [, $clave] = $this->crearApiClient(['sistema' => 'personal', 'es_admin' => true]);

        $this->getJson('/api/notificaciones/id-inexistente', ['X-Chasqui-Key' => $clave])
            ->assertStatus(404);
    }
}
