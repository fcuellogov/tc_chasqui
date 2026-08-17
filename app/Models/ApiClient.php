<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'sistema',
        'key_hash',
        'detalles',
        'es_admin',
        'fecha_desde',
        'fecha_hasta',
    ];

    protected function casts(): array
    {
        return [
            'es_admin'    => 'boolean',
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
        ];
    }

    public static function generarClave(): string
    {
        return Str::random(40);
    }

    public static function hashearClave(string $clave): string
    {
        return hash('sha256', $clave);
    }

    public static function buscarPorClave(string $clave): ?self
    {
        return static::where('key_hash', static::hashearClave($clave))->first();
    }

    public function estaVigente(?Carbon $momento = null): bool
    {
        $momento ??= now();

        if ($this->fecha_desde && $momento->lt($this->fecha_desde)) {
            return false;
        }

        if ($this->fecha_hasta && $momento->gt($this->fecha_hasta->copy()->endOfDay())) {
            return false;
        }

        return true;
    }
}
