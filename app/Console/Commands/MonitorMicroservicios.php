<?php

namespace App\Console\Commands;

use App\Jobs\SendNotification;
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
        SendNotification::dispatch(
            'Health Check', 
            null, 
            "🚨 EL MICROSERVICIO {$nombre} ESTÁ CAÍDO: \nDetalle: {$error}", 
            'error');
    }
}