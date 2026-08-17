<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitorMicroserviciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerta_y_audita_cuando_un_microservicio_no_responde_bien(): void
    {
        config(['sistema.servicios' => [
            ['nombre' => 'Personal', 'url' => 'https://personal.test'],
        ]]);

        Http::fake([
            'personal.test' => Http::response('', 503),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('monitor:check')->assertExitCode(0);

        $this->assertDatabaseHas('notification_requests', [
            'sistema' => 'Health Check',
            'canal'   => null,
        ]);

        $notificationRequest = \App\Models\NotificationRequest::where('sistema', 'Health Check')->firstOrFail();

        $this->assertSame('procesado', $notificationRequest->estado);
        $this->assertStringContainsString('Personal', $notificationRequest->mensaje);
        $this->assertTrue($notificationRequest->attempts()->where('canal', 'slack')->exists());
    }
}
