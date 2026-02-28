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
        
        if ($this->canal == 'telegram'){ 
            $this->enviarTelegram(); 
        }
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

    protected function enviarTelegram()
    {
        $token = config('sistema.telegram.token');
        $chatId = config('sistema.telegram.chat_id');

        // Armamos un mensaje con HTML para que sea bien legible
        $html = "<b>🚀 NOVEDAD</b>\n";
        $html .= "───────────────────\n";
        $html .= "<b>🖥️ Sistema:</b> " . e(strtoupper($this->sistema)) . "\n";
        $html .= "<b>📢 Mensaje:</b> " . e($this->mensaje) . "\n";
        $html .= "<b>📊 Nivel:</b> #" . e(strtoupper($this->nivel));

        $respuesta = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML', 
        ]);

        dd($respuesta);
    }
}