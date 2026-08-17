<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
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

        SendNotification::dispatch(
            $validated['sistema'],
            $validated['canal'] ?? null,
            $validated['mensaje'],
            $validated['nivel'],
            ChannelRegistry::extractDatos($validated),
        );

        return response()->json(['status' => 'Encolado'], 202);
    }
}
