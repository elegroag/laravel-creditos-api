<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // Tipo de notificación (firma_completada, estado_cambiado, etc.)
            $table->string('notifiable_type'); // Tipo de entidad (User, etc.)
            $table->string('notifiable_id'); // ID del usuario que recibe la notificación
            $table->json('data'); // Datos de la notificación
            $table->timestamp('read_at')->nullable(); // Fecha de lectura
            $table->timestamps();

            // Índices para mejorar performance
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('type');
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
