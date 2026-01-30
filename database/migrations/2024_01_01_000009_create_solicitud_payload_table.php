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
            $table->bigInteger('solicitud_id');
            $table->string('version', 20)->default('1.0');
            $table->json('encabezado')->nullable();
            $table->json('solicitud')->nullable();
            $table->json('producto_solicitado')->nullable();
            $table->json('solicitante')->nullable();
            $table->json('informacion_laboral')->nullable();
            $table->json('ingresos_descuentos')->nullable();
            $table->json('informacion_economica')->nullable();
            $table->json('propiedades')->nullable();
            $table->json('deudas')->nullable();
            $table->json('referencias')->nullable();
            $table->json('linea_credito')->nullable();
            $table->boolean('save_xml')->default(true);
            $table->timestamps();
            
            // Foreign key
            $table->foreignId('solicitud_id')->references('id', 'solicitudes_credito')->onDelete('cascade');
            
            // Índices
            $table->index('solicitud_id');
            $table->index('version');
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
