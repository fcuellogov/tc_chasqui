<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendNotification;
use App\Models\NotificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_delega_en_el_channel_registry_y_audita_el_resultado(): void
    {
        Http::fake();

        $notificationRequest = NotificationRequest::create([
            'sistema' => 'personal',
            'canal'   => 'telegram',
            'mensaje' => 'hola',
            'nivel'   => 'info',
            'estado'  => 'pendiente',
        ]);

        (new SendNotification($notificationRequest->id, 'personal', 'telegram', 'hola', 'info'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));

        $this->assertDatabaseHas('notification_attempts', [
            'notification_request_id' => $notificationRequest->id,
            'canal'  => 'telegram',
            'estado' => 'enviado',
        ]);

        $this->assertSame('procesado', $notificationRequest->fresh()->estado);
    }
}
