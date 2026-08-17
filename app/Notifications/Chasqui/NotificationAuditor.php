<?php

namespace App\Notifications\Chasqui;

use App\Models\NotificationAttempt;
use App\Models\NotificationRequest;

class NotificationAuditor
{
    /** @param array<string, ResultadoEnvio> $resultados */
    public static function registrar(string $notificationRequestId, array $resultados): void
    {
        foreach ($resultados as $canal => $resultado) {
            NotificationAttempt::create([
                'notification_request_id' => $notificationRequestId,
                'canal'       => $canal,
                'estado'      => $resultado->estado,
                'http_status' => $resultado->httpStatus,
                'detalle'     => $resultado->detalle,
            ]);
        }

        NotificationRequest::whereKey($notificationRequestId)->update([
            'estado' => static::estadoAgregado($resultados),
        ]);
    }

    /** @param array<string, ResultadoEnvio> $resultados */
    protected static function estadoAgregado(array $resultados): string
    {
        $estados = array_map(fn (ResultadoEnvio $r) => $r->estado, $resultados);

        $tieneEnviados = in_array('enviado', $estados, true);
        $tieneFallidos = in_array('fallido', $estados, true);

        return match (true) {
            $tieneEnviados && $tieneFallidos => 'parcial',
            $tieneEnviados => 'procesado',
            $tieneFallidos => 'fallido',
            default => 'sin_canales', // ningún canal aplicaba o todos fueron omitidos
        };
    }
}
