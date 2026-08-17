<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sistema')->index();
            $table->string('canal')->nullable()->index();
            $table->text('mensaje');
            $table->string('nivel')->index();
            $table->json('datos')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->string('estado')->default('pendiente')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_requests');
    }
};
