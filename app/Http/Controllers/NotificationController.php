<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use App\Models\NotificationRequest;
use App\Notifications\Chasqui\ChannelRegistry;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $rules = array_merge([
            'sistema' => 'required|string',
            'canal'   => 'nullable|string|in:' . implode(',', ChannelRegistry::keys()),
            'mensaje' => 'required|string',
            'nivel'   => 'required|string|in:error,success,info',
        ], ChannelRegistry::validationRules());

        $validated = $request->validate($rules);

        $datos = ChannelRegistry::extractDatos($validated);

        $notificationRequest = NotificationRequest::create([
            'sistema'   => $validated['sistema'],
            'canal'     => $validated['canal'] ?? null,
            'mensaje'   => $validated['mensaje'],
            'nivel'     => $validated['nivel'],
            'datos'     => $datos,
            'ip_origen' => $request->ip(),
            'estado'    => 'pendiente',
        ]);

        SendNotification::dispatch(
            $notificationRequest->id,
            $notificationRequest->sistema,
            $notificationRequest->canal,
            $notificationRequest->mensaje,
            $notificationRequest->nivel,
            $datos,
        );

        return response()->json([
            'status' => 'Encolado',
            'id'     => $notificationRequest->id,
        ], 202);
    }
}
