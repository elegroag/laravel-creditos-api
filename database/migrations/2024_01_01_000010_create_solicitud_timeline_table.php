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
            $table->bigInteger('solicitud_id');
            $table->string('estado_codigo', 50);
            $table->timestamp('fecha')->useCurrent();
            $table->text('detalle')->nullable();
            $table->string('usuario_username', 100)->nullable();
            $table->boolean('automatico')->default(false);
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('solicitud_id')->references('id', 'solicitudes_credito')->onDelete('cascade');
            $table->foreign('estado_codigo')->references('codigo', 'estados_solicitud');
            $table->foreign('usuario_username')->references('username', 'users')->onDelete('set null');
            
            // Índices
            $table->index('solicitud_id');
            $table->index('estado_codigo');
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
