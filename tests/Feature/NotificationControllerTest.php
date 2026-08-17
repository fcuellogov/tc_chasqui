<?php

namespace Tests\Feature;

use App\Jobs\SendNotification;
use App\Models\NotificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaApiClients;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase, CreaApiClients;

    protected string $clave;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->clave] = $this->crearApiClient(['sistema' => 'salud']);
    }

    protected function headers(array $overrides = []): array
    {
        return array_merge(['X-Chasqui-Key' => $this->clave], $overrides);
    }

    public function test_rechaza_sin_api_key_valida(): void
    {
        $response = $this->postJson('/api/notificar', [
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers(['X-Chasqui-Key' => 'clave-incorrecta']));

        $response->assertStatus(401);
    }

    public function test_requiere_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/notificar', [], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mensaje', 'nivel']);
    }

    public function test_rechaza_canal_no_registrado(): void
    {
        $response = $this->postJson('/api/notificar', [
            'canal'   => 'sms',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['canal']);
    }

    public function test_canal_whatsapp_requiere_telefono(): void
    {
        $response = $this->postJson('/api/notificar', [
            'canal'   => 'whatsapp',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);
    }

    public function test_canal_mailrelay_requiere_destinatarios_y_asunto(): void
    {
        $response = $this->postJson('/api/notificar', [
            'canal'   => 'mailrelay',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinatarios', 'asunto']);
    }

    public function test_crea_el_registro_de_auditoria_con_el_sistema_del_cliente_autenticado(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/notificar', [
            'canal'      => 'whatsapp',
            'mensaje'    => 'recordatorio',
            'nivel'      => 'info',
            'telefono'   => '5493834123456',
            'template'   => 'recordatorio_turno',
            'parametros' => ['nombre' => 'Juan'],
        ], $this->headers());

        $response->assertStatus(202)
            ->assertJson(['status' => 'Encolado'])
            ->assertJsonStructure(['status', 'id']);

        $id = $response->json('id');

        $this->assertDatabaseHas('notification_requests', [
            'id'      => $id,
            'sistema' => 'salud',
            'canal'   => 'whatsapp',
            'estado'  => 'pendiente',
        ]);

        Bus::assertDispatched(SendNotification::class, function (SendNotification $job) use ($id) {
            return $job->notificationRequestId === $id
                && $job->sistema === 'salud'
                && $job->canal === 'whatsapp'
                && $job->datos === [
                    'telefono'   => '5493834123456',
                    'template'   => 'recordatorio_turno',
                    'parametros' => ['nombre' => 'Juan'],
                ];
        });
    }

    public function test_ignora_el_sistema_que_venga_en_el_body_y_usa_el_del_cliente_autenticado(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/notificar', [
            'sistema' => 'un-sistema-inventado',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers());

        $response->assertStatus(202);

        $this->assertDatabaseHas('notification_requests', [
            'id'      => $response->json('id'),
            'sistema' => 'salud',
        ]);

        $this->assertDatabaseMissing('notification_requests', [
            'sistema' => 'un-sistema-inventado',
        ]);
    }

    public function test_limita_la_cantidad_de_solicitudes_por_minuto(): void
    {
        Http::fake();
        config(['sistema.rate_limit_por_minuto' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/notificar', [
                'mensaje' => 'hola',
                'nivel'   => 'info',
            ], $this->headers())->assertStatus(202);
        }

        $this->postJson('/api/notificar', [
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers())->assertStatus(429);
    }

    public function test_flujo_completo_envia_a_slack_y_queda_auditado(): void
    {
        Http::fake();

        $response = $this->postJson('/api/notificar', [
            'mensaje' => 'hola a todos',
            'nivel'   => 'error',
        ], $this->headers());

        $response->assertStatus(202);

        Http::assertSent(function ($request) {
            return $request->url() === config('sistema.slack.errores_url');
        });

        $id = $response->json('id');

        $notificationRequest = NotificationRequest::find($id);

        $this->assertNotNull($notificationRequest);
        $this->assertSame('procesado', $notificationRequest->estado);
        $this->assertTrue($notificationRequest->attempts()->where('canal', 'slack')->where('estado', 'enviado')->exists());
    }
}
