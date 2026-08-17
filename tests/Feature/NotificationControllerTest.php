<?php

namespace Tests\Feature;

use App\Jobs\SendNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    protected function headers(array $overrides = []): array
    {
        return array_merge([
            'X-Chasqui-Key' => config('sistema.chasqui_key'),
        ], $overrides);
    }

    public function test_rechaza_sin_api_key_valida(): void
    {
        $response = $this->postJson('/api/notificar', [
            'sistema' => 'personal',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers(['X-Chasqui-Key' => 'clave-incorrecta']));

        $response->assertStatus(401);
    }

    public function test_requiere_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/notificar', [], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sistema', 'mensaje', 'nivel']);
    }

    public function test_rechaza_canal_no_registrado(): void
    {
        $response = $this->postJson('/api/notificar', [
            'sistema' => 'personal',
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
            'sistema' => 'personal',
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
            'sistema' => 'personal',
            'canal'   => 'mailrelay',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinatarios', 'asunto']);
    }

    public function test_encola_el_job_con_los_datos_especificos_del_canal_agrupados(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/notificar', [
            'sistema'   => 'salud',
            'canal'     => 'whatsapp',
            'mensaje'   => 'recordatorio',
            'nivel'     => 'info',
            'telefono'  => '5493834123456',
            'template'  => 'recordatorio_turno',
            'parametros' => ['nombre' => 'Juan'],
        ], $this->headers());

        $response->assertStatus(202)
            ->assertJson(['status' => 'Encolado']);

        Bus::assertDispatched(SendNotification::class, function (SendNotification $job) {
            return $job->sistema === 'salud'
                && $job->canal === 'whatsapp'
                && $job->datos === [
                    'telefono'   => '5493834123456',
                    'template'   => 'recordatorio_turno',
                    'parametros' => ['nombre' => 'Juan'],
                ];
        });
    }

    public function test_limita_la_cantidad_de_solicitudes_por_minuto(): void
    {
        Http::fake();
        config(['sistema.rate_limit_por_minuto' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/notificar', [
                'sistema' => 'personal',
                'mensaje' => 'hola',
                'nivel'   => 'info',
            ], $this->headers())->assertStatus(202);
        }

        $this->postJson('/api/notificar', [
            'sistema' => 'personal',
            'mensaje' => 'hola',
            'nivel'   => 'info',
        ], $this->headers())->assertStatus(429);
    }

    public function test_flujo_completo_envia_a_slack_cuando_no_se_especifica_canal(): void
    {
        Http::fake();

        $response = $this->postJson('/api/notificar', [
            'sistema' => 'personal',
            'mensaje' => 'hola a todos',
            'nivel'   => 'error',
        ], $this->headers());

        $response->assertStatus(202);

        Http::assertSent(function ($request) {
            return $request->url() === config('sistema.slack.errores_url');
        });
    }
}
