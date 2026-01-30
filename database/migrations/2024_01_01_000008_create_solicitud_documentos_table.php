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
        Schema::create('solicitud_documentos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('solicitud_id');
            $table->string('documento_uuid', 36)->unique();
            $table->string('documento_requerido_id', 100);
            $table->string('nombre_original', 255);
            $table->string('saved_filename', 255);
            $table->string('tipo_mime', 100)->nullable();
            $table->integer('tamano_bytes')->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Foreign key
            $table->foreignId('solicitud_id')->references('id', 'solicitudes_credito')->onDelete('cascade');
            
            // Índices
            $table->index('solicitud_id');
            $table->index('documento_uuid');
            $table->index('documento_requerido_id');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_documentos');
    }
};
