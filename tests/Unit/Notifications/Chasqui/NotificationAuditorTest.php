<?php

namespace Tests\Unit\Notifications\Chasqui;

use App\Models\NotificationRequest;
use App\Notifications\Chasqui\NotificationAuditor;
use App\Notifications\Chasqui\ResultadoEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAuditorTest extends TestCase
{
    use RefreshDatabase;

    protected function crearRequest(): NotificationRequest
    {
        return NotificationRequest::create([
            'sistema' => 'personal',
            'mensaje' => 'hola',
            'nivel'   => 'info',
            'estado'  => 'pendiente',
        ]);
    }

    public function test_estado_procesado_cuando_todo_se_envio(): void
    {
        $req = $this->crearRequest();

        NotificationAuditor::registrar($req->id, [
            'slack'    => ResultadoEnvio::enviado(200),
            'telegram' => ResultadoEnvio::enviado(200),
        ]);

        $this->assertSame('procesado', $req->fresh()->estado);
        $this->assertDatabaseCount('notification_attempts', 2);
    }

    public function test_estado_parcial_cuando_hay_enviados_y_fallidos(): void
    {
        $req = $this->crearRequest();

        NotificationAuditor::registrar($req->id, [
            'slack'    => ResultadoEnvio::enviado(200),
            'whatsapp' => ResultadoEnvio::fallido('timeout', 500),
        ]);

        $this->assertSame('parcial', $req->fresh()->estado);
    }

    public function test_estado_fallido_cuando_nada_se_envio(): void
    {
        $req = $this->crearRequest();

        NotificationAuditor::registrar($req->id, [
            'whatsapp' => ResultadoEnvio::fallido('timeout', 500),
        ]);

        $this->assertSame('fallido', $req->fresh()->estado);
    }

    public function test_estado_sin_canales_cuando_todo_se_omitio(): void
    {
        $req = $this->crearRequest();

        NotificationAuditor::registrar($req->id, [
            'whatsapp'  => ResultadoEnvio::omitido('falta teléfono'),
            'mailrelay' => ResultadoEnvio::omitido('falta destinatarios'),
        ]);

        $this->assertSame('sin_canales', $req->fresh()->estado);
    }
}
