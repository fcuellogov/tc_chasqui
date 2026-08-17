<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendNotification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendNotificationTest extends TestCase
{
    public function test_handle_delega_en_el_channel_registry(): void
    {
        Http::fake();

        (new SendNotification('personal', 'telegram', 'hola', 'info'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }
}
