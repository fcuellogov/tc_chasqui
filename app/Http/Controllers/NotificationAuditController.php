<?php

namespace App\Http\Controllers;

use App\Models\NotificationRequest;
use Illuminate\Http\Request;

class NotificationAuditController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'sistema'  => 'nullable|string',
            'canal'    => 'nullable|string',
            'estado'   => 'nullable|string|in:pendiente,procesado,parcial,fallido,sin_canales',
            'desde'    => 'nullable|date',
            'hasta'    => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = NotificationRequest::query()->with('attempts')->latest();

        $query->when($request->filled('sistema'), fn ($q) => $q->where('sistema', $request->input('sistema')));
        $query->when($request->filled('canal'), fn ($q) => $q->where('canal', $request->input('canal')));
        $query->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')));
        $query->when($request->filled('desde'), fn ($q) => $q->where('created_at', '>=', $request->date('desde')));
        $query->when($request->filled('hasta'), fn ($q) => $q->where('created_at', '<=', $request->date('hasta')));

        return $query->paginate($request->integer('per_page', 25));
    }

    public function show(NotificationRequest $notificationRequest)
    {
        return $notificationRequest->load('attempts');
    }
}
