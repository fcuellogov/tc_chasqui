<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'sistema',
        'canal',
        'mensaje',
        'nivel',
        'datos',
        'ip_origen',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class);
    }
}
