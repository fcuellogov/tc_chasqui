<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_request_id')
                ->constrained('notification_requests')
                ->cascadeOnDelete();
            $table->string('canal')->index();
            $table->string('estado')->index(); // enviado, fallido, omitido
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('detalle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
