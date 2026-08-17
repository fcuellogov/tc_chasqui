<?php

namespace Tests\Unit\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\ChannelRegistry;
use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailrelayChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sistema.mailrelay.url'          => 'https://mailrelay.test/send',
            'sistema.mailrelay.token'        => 'mailrelay-token',
            'sistema.mailrelay.from.address' => 'no-reply@tccatamarca.online',
            'sistema.mailrelay.from.name'    => 'El Chasqui',
        ]);
    }

    public function test_no_envia_nada_sin_destinatarios(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'mailrelay', 'hola', 'info'));

        Http::assertNothingSent();
    }

    public function test_envia_solo_los_correos_validos(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'mailrelay', 'contenido', 'info', [
            'destinatarios' => ['valido@test.com', 'no-es-un-mail', ' otro-valido@test.com '],
            'asunto'        => 'Asunto de prueba',
        ]));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mailrelay.test/send'
                && $request['subject'] === 'Asunto de prueba'
                && $request['to'] === [
                    ['email' => 'valido@test.com'],
                    ['email' => 'otro-valido@test.com'],
                ];
        });
    }

    public function test_avisa_por_slack_cuando_hay_correos_invalidos(): void
    {
        Http::fake();

        config(['sistema.slack.errores_url' => 'https://hooks.slack.com/test-errores']);

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'mailrelay', 'contenido', 'error', [
            'destinatarios' => ['valido@test.com', 'invalido'],
            'asunto'        => 'Asunto',
        ]));

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/test-errores');
    }

    public function test_no_llama_a_mailrelay_si_no_hay_correos_validos(): void
    {
        Http::fake();

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'mailrelay', 'contenido', 'info', [
            'destinatarios' => ['no-es-un-mail'],
            'asunto'        => 'Asunto',
        ]));

        Http::assertNotSent(fn ($request) => $request->url() === 'https://mailrelay.test/send');
    }
}
