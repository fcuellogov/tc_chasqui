<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('sistema')->index();
            $table->string('key_hash', 64)->unique();
            $table->text('detalles')->nullable();
            $table->boolean('es_admin')->default(false);
            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
