<?php

use App\Http\Controllers\NotificationAuditController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:chasqui', 'chasqui.auth'])->group(function () {
    Route::post('/notificar', [NotificationController::class, 'index']);
    Route::get('/notificaciones', [NotificationAuditController::class, 'index']);
    Route::get('/notificaciones/{notificationRequest}', [NotificationAuditController::class, 'show']);
});
