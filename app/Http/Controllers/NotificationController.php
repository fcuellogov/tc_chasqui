<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'sistema' => 'required|string',
            'canal' => 'required|string|in:slack,telegram',
            'mensaje' => 'required|string',
            'nivel'   => 'required|string|in:error,success,info'
        ]);

        SendNotification::dispatch(
            $request->sistema,
            $request->canal,
            $request->mensaje,
            $request->nivel
        );

        return response()->json(['status' => 'Encolado'], 202);
    }
}
