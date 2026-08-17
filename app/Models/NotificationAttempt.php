<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttempt extends Model
{
    protected $fillable = [
        'notification_request_id',
        'canal',
        'estado',
        'http_status',
        'detalle',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(NotificationRequest::class, 'notification_request_id');
    }
}
