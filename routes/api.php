<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notificar', [NotificationController::class, 'index'])->middleware('chasqui.auth');