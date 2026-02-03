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
        Schema::create('solicitud_timeline', function (Blueprint $table) {
            $table->id();
            $table->string('solicitud_id', 20); // Cambiar a string para coincidir con numero_solicitud
            $table->string('estado', 50);
            $table->timestamp('fecha')->useCurrent();
            $table->text('detalle')->nullable();
            $table->string('usuario_username', 100)->nullable();
            $table->boolean('automatico')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('solicitud_id')->references('numero_solicitud')->on('solicitudes_credito')->onDelete('cascade');
            $table->foreign('estado')->references('id')->on('estados_solicitud');
            $table->foreign('usuario_username')->references('username')->on('users')->onDelete('set null');

            // Índices
            $table->index('solicitud_id');
            $table->index('estado');
            $table->index('fecha');
            $table->index('usuario_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_timeline');
    }
};
