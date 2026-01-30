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
        Schema::create('solicitud_firmantes', function (Blueprint $table) {
            $table->id();
            $table->string('solicitud_id', 20);
            $table->integer('orden');
            $table->string('tipo', 50);
            $table->string('nombre_completo', 255);
            $table->string('numero_documento', 50);
            $table->string('email', 255);
            $table->string('rol', 100);
            $table->timestamps();

            // Foreign key
            $table->foreign('solicitud_id')->references('numero_solicitud', 'solicitudes_credito')->onDelete('cascade');

            // Índices
            $table->index('solicitud_id');
            $table->index('email');
            $table->index('numero_documento');
            $table->unique(['solicitud_id', 'orden'], 'unique_firmante_solicitud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firmantes_solicitud');
    }
};
