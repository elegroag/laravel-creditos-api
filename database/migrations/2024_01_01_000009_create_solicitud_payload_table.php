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
        Schema::create('solicitud_payload', function (Blueprint $table) {
            $table->id();
            $table->string('solicitud_id', 20); // Cambiar a string para coincidir con numero_solicitud
            $table->string('version', 20)->default('1.0');
            $table->json('informacion_laboral')->nullable();
            $table->json('ingresos_descuentos')->nullable();
            $table->json('informacion_economica')->nullable();
            $table->json('propiedades')->nullable();
            $table->json('deudas')->nullable();
            $table->json('referencias')->nullable();
            $table->json('linea_credito')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('solicitud_id')->references('numero_solicitud')->on('solicitudes_credito')->onDelete('cascade');

            // Índices
            $table->index('solicitud_id');
            $table->unique(['solicitud_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_payload');
    }
};
