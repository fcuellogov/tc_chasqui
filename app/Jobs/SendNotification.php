<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $color;

    public function __construct(
        public string $sistema,
        public string $canal, // 'slack', 'telegram'
        public string $mensaje,
        public string $nivel // 'error', 'success', 'info'
    ) {}

    public function handle()
    {
        $this->color = match($this->nivel) {
            'error'   => '#E01E5A', // Rojo
            'success' => '#2EB67D', // Verde
            default   => '#36C5F0', // Azul
        };

        if (str_contains($this->canal, 'slack')) {
            $this->enviarSlack();
        }
        
        // TODO: activar luego
        //if ($this->canal == 'telegram'){ 
        //     $this->sendToTelegram(); 
        //}
    }

    protected function enviarSlack()
    {
        $webhook = match($this->nivel) {
            'error' => config('sistema.slack.errores_url'),
            'success' => config('sistema.slack.alertas_url'),
            default   => config('sistema.slack.alertas_url'),
        };

        Http::post($webhook, [
            'attachments' => [[
                'fallback' => "Nuevo aviso de {$this->sistema}",
                'color'    => $this->color,
                'pretext'  => "*Nuevo evento de sistema*",
                'author_name' => "🖥️ Microservicio: " . strtoupper($this->sistema),
                'text'     => $this->mensaje,
                'fields'   => [
                    [
                        'title' => '📊 Nivel',
                        'value' => strtoupper($this->nivel),
                        'short' => true
                    ],
                ],
                'footer'   => 'El Chasqui',
            ]]
        ]);
    }
}