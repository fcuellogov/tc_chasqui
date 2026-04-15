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
        public ?string $canal = null,
        public string $mensaje,
        public string $nivel,
        public ?string $telefono = null,
        public ?array $destinatarios = [],
        public ?string $asunto = null,
    ) {}

    public function handle()
    {
        $this->color = match($this->nivel) {
            'error'   => '#E01E5A', // Rojo
            'success' => '#2EB67D', // Verde
            default   => '#36C5F0', // Azul
        };

        if (is_null($this->canal) || str_contains($this->canal, 'slack')) {
            $this->enviarSlack();
        }
        
        if (is_null($this->canal) || $this->canal == 'telegram') { 
            $this->enviarTelegram(); 
        }

        if (is_null($this->canal) || ($this->canal == 'whatsapp') && !is_null($this->telefono)) { 
            $this->enviarWhatsapp(); 
        }

        if (is_null($this->canal) || ($this->canal == 'mailrelay' && !empty($this->destinatarios))) {
            $this->enviarMailrelay();
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
        $html = "<b>🖥️ Sistema:</b> " . e(strtoupper($this->sistema)) . "\n";
        $html .= "<b>📢 Mensaje:</b> " . e($this->mensaje) . "\n";
        $html .= "<b>📊 Nivel:</b> #" . e(strtoupper($this->nivel));

        Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML', 
        ]);
    }
    
    protected function enviarWhatsapp()
    {
        $token = config('sistema.whatsapp.token');
        $url   = config('sistema.whatsapp.url');

        $texto = "🖥️ *Sistema:* " . strtoupper($this->sistema) . "\n";
        $texto .= "📢 *Mensaje:* " . $this->mensaje . "\n";
        $texto .= "📊 *Nivel:* " . strtoupper($this->nivel);

        Http::timeout(5)->connectTimeout(2)
                ->withHeaders([
                    'x-api-key' => $token,
                    'Accept'    => 'application/json',
                ])
                ->asForm()
                ->post($url, [
                    'numeroTelefono' => $this->telefono,
                    'mensaje'        => $texto,
                ]);
    }

    protected function enviarMailrelay()
    {
        $destinatariosNormalizados = array_map('trim', $this->destinatarios);
        
        $correosValidos = array_filter($destinatariosNormalizados, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });

        $correosInvalidos = array_diff($destinatariosNormalizados, $correosValidos);

        if (!empty($correosInvalidos)) {
            $mensaje = "Se intentó enviar una notificación desde {$this->sistema} pero se detectaron " . count($correosInvalidos) . " direcciones inválidas.";
            $mensaje .= " Direcciónes inválidas: " . implode(", ", $correosInvalidos);
            $this->enviarErroresMailRelay($mensaje);
            Log::warning("Chasqui [Mailrelay]: Se descartaron correos con formato inválido.", [
                'sistema_origen' => $this->sistema,
                'cantidad'       => count($correosInvalidos),
                'lista_errores'  => array_values($correosInvalidos)
            ]);
        }

        $totalOriginal = count($destinatariosNormalizados);
        $totalValidos = count($correosValidos);
        $descartados = count($correosInvalidos);

        if ($totalValidos === 0) {
            Log::warning("Chasqui [Mailrelay]: Abortando envío. No se encontraron correos válidos de {$totalOriginal} recibidos. Sistema: {$this->sistema}");
            return;
        }

        if ($descartados > 0) {
            Log::info("Chasqui [Mailrelay]: Se descartaron {$descartados} correos inválidos del sistema {$this->sistema}.");
        }

        $destinatarios = array_map(fn($email) => ['email' => trim($email)], array_values($correosValidos));

        $token = config('sistema.mailrelay.token');
        $url   = config('sistema.mailrelay.url');

        $payload = [
            'from' => [
                'email' => config('sistema.mailrelay.from.address'),
                'name'  => config('sistema.mailrelay.from.name')
            ],
            'to' => $destinatarios,
            'subject' => $this->asunto,
            'html_part' => $this->mensaje,
        ];

        Http::timeout(10)->connectTimeout(3)
            ->withHeaders([
                'X-AUTH-TOKEN' => $token,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $payload);
    }

    protected function enviarErroresMailRelay($mensaje)
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
                'pretext'  => "*Error al enviar mails masivos*",
                'author_name' => "🖥️ Microservicio: " . strtoupper($this->sistema),
                'text'     => $mensaje,
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