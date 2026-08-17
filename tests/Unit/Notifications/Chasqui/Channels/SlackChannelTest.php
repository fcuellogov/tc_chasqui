<?php

namespace Tests\Unit\Notifications\Chasqui\Channels;

use App\Notifications\Chasqui\ChannelRegistry;
use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackChannelTest extends TestCase
{
    public function test_envia_al_webhook_de_error_cuando_el_nivel_es_error(): void
    {
        Http::fake();
        config(['sistema.slack.errores_url' => 'https://hooks.slack.com/errores']);

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'slack', 'algo falló', 'error'));

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/errores'
            && $request['attachments'][0]['color'] === '#E01E5A');
    }

    public function test_envia_al_webhook_de_alertas_para_otros_niveles(): void
    {
        Http::fake();
        config(['sistema.slack.alertas_url' => 'https://hooks.slack.com/alertas']);

        ChannelRegistry::dispatch(new NotificationPayload('personal', 'slack', 'todo bien', 'success'));

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/alertas'
            && $request['attachments'][0]['color'] === '#2EB67D');
    }
}
