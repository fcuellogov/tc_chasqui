<?php

namespace Tests\Unit\Notifications\Chasqui;

use App\Notifications\Chasqui\ChannelRegistry;
use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelRegistryTest extends TestCase
{
    public function test_keys_devuelve_los_canales_registrados(): void
    {
        $this->assertEqualsCanonicalizing(
            ['slack', 'telegram', 'whatsapp', 'mailrelay'],
            ChannelRegistry::keys(),
        );
    }

    public function test_validation_rules_agrega_las_reglas_de_cada_canal(): void
    {
        $rules = ChannelRegistry::validationRules();

        $this->assertArrayHasKey('telefono', $rules);
        $this->assertArrayHasKey('template', $rules);
        $this->assertArrayHasKey('parametros', $rules);
        $this->assertArrayHasKey('destinatarios', $rules);
        $this->assertArrayHasKey('asunto', $rules);
    }

    public function test_extract_datos_descarta_los_campos_universales(): void
    {
        $datos = ChannelRegistry::extractDatos([
            'sistema'  => 'personal',
            'canal'    => 'whatsapp',
            'mensaje'  => 'hola',
            'nivel'    => 'info',
            'telefono' => '5493834123456',
        ]);

        $this->assertSame(['telefono' => '5493834123456'], $datos);
    }

    public function test_dispatch_con_canal_explicito_solo_llama_a_ese_canal(): void
    {
        Http::fake();

        $resultados = ChannelRegistry::dispatch(new NotificationPayload(
            sistema: 'personal',
            canal: 'telegram',
            mensaje: 'hola',
            nivel: 'info',
        ));

        Http::assertSentCount(1);
        $this->assertSame(['telegram'], array_keys($resultados));
        $this->assertSame('enviado', $resultados['telegram']->estado);
    }

    public function test_dispatch_sin_canal_llama_a_todos_los_canales_aplicables(): void
    {
        Http::fake();

        config(['sistema.openwa.url' => 'https://openwa.test', 'sistema.openwa.session_id' => 'chasqui']);

        $resultados = ChannelRegistry::dispatch(new NotificationPayload(
            sistema: 'personal',
            canal: null,
            mensaje: 'hola',
            nivel: 'info',
            datos: ['telefono' => '5493834123456'],
        ));

        // slack + telegram + whatsapp (mailrelay se omite: no hay destinatarios)
        Http::assertSentCount(3);
        $this->assertSame('enviado', $resultados['slack']->estado);
        $this->assertSame('enviado', $resultados['telegram']->estado);
        $this->assertSame('enviado', $resultados['whatsapp']->estado);
        $this->assertSame('omitido', $resultados['mailrelay']->estado);
    }

    public function test_dispatch_salta_canales_sin_los_datos_necesarios(): void
    {
        Http::fake();

        $resultados = ChannelRegistry::dispatch(new NotificationPayload(
            sistema: 'personal',
            canal: null,
            mensaje: 'hola',
            nivel: 'info',
            // sin telefono ni destinatarios: whatsapp y mailrelay deben omitirse
        ));

        Http::assertSentCount(2); // solo slack + telegram
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openwa'));
        $this->assertSame('omitido', $resultados['whatsapp']->estado);
        $this->assertSame('omitido', $resultados['mailrelay']->estado);
    }

    public function test_una_conexion_caida_en_un_canal_no_impide_que_los_demas_se_procesen(): void
    {
        config(['sistema.openwa.url' => 'https://openwa.test', 'sistema.openwa.session_id' => 'chasqui']);

        Http::fake([
            'openwa.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
            '*' => Http::response(['ok' => true], 200),
        ]);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with('Chasqui [Whatsapp/OpenWA]: excepción al enviar.', \Mockery::type('array'));

        // No debe lanzar excepción hacia afuera aunque whatsapp/openwa esté caído.
        $resultados = ChannelRegistry::dispatch(new NotificationPayload(
            sistema: 'personal',
            canal: null,
            mensaje: 'hola',
            nivel: 'info',
            datos: ['telefono' => '5493834123456'],
        ));

        // slack y telegram sí se enviaron pese a la falla de whatsapp.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hooks.slack.com')
            || str_contains($request->url(), 'api.telegram.org'));

        $this->assertSame('enviado', $resultados['slack']->estado);
        $this->assertSame('enviado', $resultados['telegram']->estado);
        $this->assertSame('fallido', $resultados['whatsapp']->estado);
    }
}
