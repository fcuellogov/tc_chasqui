<?php

namespace App\Jobs;

use App\Notifications\Chasqui\ChannelRegistry;
use App\Notifications\Chasqui\NotificationAuditor;
use App\Notifications\Chasqui\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $notificationRequestId,
        public string $sistema,
        public ?string $canal,
        public string $mensaje,
        public string $nivel,
        public array $datos = [],
    ) {}

    public function handle(): void
    {
        $resultados = ChannelRegistry::dispatch(new NotificationPayload(
            sistema: $this->sistema,
            canal: $this->canal,
            mensaje: $this->mensaje,
            nivel: $this->nivel,
            datos: $this->datos,
        ));

        NotificationAuditor::registrar($this->notificationRequestId, $resultados);
    }
}
