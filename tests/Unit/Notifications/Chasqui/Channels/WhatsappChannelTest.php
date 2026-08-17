<?php

namespace Tests\Unit\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\ChannelRegistry;
use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsappChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sistema.openwa.url'        => 'https://openwa.test',
            'sistema.openwa.token'      => 'secret-key',
            'sistema.openwa.session_id' => 'chasqui',
        ]);
    }

    public function test_no_envia_nada_sin_telefono(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'whatsapp', 'hola', 'info'));

        Http::assertNothingSent();
    }

    public function test_envia_texto_plano_al_endpoint_send_text(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'whatsapp', 'hola', 'info', [
            'telefono' => '5493834123456',
        ]));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openwa.test/api/sessions/chasqui/messages/send-text'
                && $request->method() === 'POST'
                && $request['chatId'] === '5493834123456@c.us'
                && str_contains($request['text'], 'PERSONAL')
                && $request->hasHeader('X-API-Key', 'secret-key');
        });
    }

    public function test_envia_template_al_endpoint_send_template_con_vars(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('salud', 'whatsapp', 'recordatorio', 'info', [
            'telefono'   => '5493834123456',
            'template'   => 'recordatorio_turno',
            'parametros' => ['nombre' => 'Juan', 'hora' => '10:00'],
        ]));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openwa.test/api/sessions/chasqui/messages/send-template'
                && $request['chatId'] === '5493834123456@c.us'
                && $request['templateName'] === 'recordatorio_turno'
                && $request['vars'] === ['nombre' => 'Juan', 'hora' => '10:00'];
        });
    }

    public function test_loguea_advertencia_cuando_la_respuesta_falla(): void
    {
        Http::fake([
            'openwa.test/*' => Http::response(['error' => 'boom'], 500),
        ]);

        Log::shouldReceive('warning')->once()
            ->with('Chasqui [Whatsapp/OpenWA]: fallo el envío.', \Mockery::type('array'));

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'whatsapp', 'hola', 'info', [
            'telefono' => '5493834123456',
        ]));
    }

    public function test_loguea_advertencia_cuando_falla_la_conexion(): void
    {
        Http::fake([
            'openwa.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        Log::shouldReceive('warning')->once()
            ->with('Chasqui [Whatsapp/OpenWA]: excepción al enviar.', \Mockery::type('array'));

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'whatsapp', 'hola', 'info', [
            'telefono' => '5493834123456',
        ]));
    }
}
