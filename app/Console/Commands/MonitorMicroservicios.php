<?php

namespace App\Console\Commands;

use App\Jobs\SendNotification;
use App\Models\NotificationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitorMicroservicios extends Command
{
    protected $signature = 'monitor:check';
    protected $description = 'Chequea la salud de los microservicios cada 30 min';

    public function handle()
    {
        $servicios = config('sistema.servicios');

        foreach ($servicios as $servicio) {
            try {
                $respuesta = Http::timeout(3)->get($servicio['url']);

                if ($respuesta->status() != 500){
                    $this->alertar($servicio['nombre'], "Respuesta fallida: " . $respuesta->status());
                }
            } catch (\Exception $e) {
                $this->alertar($servicio['nombre'], "No responde (Timeout/Offline)");
            }
        }
    }

    protected function alertar($nombre, $error)
    {
        $mensaje = "🚨 EL MICROSERVICIO {$nombre} ESTÁ CAÍDO: \nDetalle: {$error}";

        $notificationRequest = NotificationRequest::create([
            'sistema' => 'Health Check',
            'canal'   => null,
            'mensaje' => $mensaje,
            'nivel'   => 'error',
            'estado'  => 'pendiente',
        ]);

        SendNotification::dispatch(
            $notificationRequest->id,
            'Health Check',
            null,
            $mensaje,
            'error',
        );
    }
}