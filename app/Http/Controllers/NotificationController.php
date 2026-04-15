<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'sistema'   => 'required|string',
            'canal'     => 'nullable|string|in:slack,telegram,whatsapp,sms,mailrelay',
            'mensaje'   => 'required|string',
            'nivel'     => 'required|string|in:error,success,info',
            'telefono'  => 'required_if:canal,whatsapp,sms|nullable|string|regex:/^[0-9]+$/',
            'destinatarios' => 'required_if:canal,mailrelay|nullable|array|min:1',
            'asunto'  => 'required_if:canal,mailrelay|nullable|string|max:50',
        ]);

        SendNotification::dispatch(
            $request->sistema,
            $request->canal,
            $request->mensaje,
            $request->nivel,
            $request->telefono,
            $request->destinatarios ?? [],
            $request->asunto,
        );

        return response()->json(['status' => 'Encolado'], 202);
    }
}
