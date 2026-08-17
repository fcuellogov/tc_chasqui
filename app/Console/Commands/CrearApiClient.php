<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;

class CrearApiClient extends Command
{
    protected $signature = 'chasqui:crear-cliente
        {sistema : Nombre del sistema, ej: Personal}
        {--detalles= : Descripción libre}
        {--admin : Si puede ver la auditoría de todos los sistemas, no solo la propia}
        {--desde= : Fecha desde (YYYY-MM-DD), por defecto hoy}
        {--hasta= : Fecha hasta (YYYY-MM-DD), por defecto sin vencimiento}';

    protected $description = 'Genera una nueva API key para un sistema emisor de Chasqui';

    public function handle(): int
    {
        $clave = ApiClient::generarClave();

        $cliente = ApiClient::create([
            'sistema'     => $this->argument('sistema'),
            'key_hash'    => ApiClient::hashearClave($clave),
            'detalles'    => $this->option('detalles'),
            'es_admin'    => (bool) $this->option('admin'),
            'fecha_desde' => $this->option('desde') ?? now()->toDateString(),
            'fecha_hasta' => $this->option('hasta'),
        ]);

        $this->info("Cliente #{$cliente->id} creado para '{$cliente->sistema}'.");
        $this->newLine();
        $this->warn('Guardá esta clave ahora: no se puede volver a mostrar (solo se guarda su hash).');
        $this->line($clave);

        return self::SUCCESS;
    }
}
