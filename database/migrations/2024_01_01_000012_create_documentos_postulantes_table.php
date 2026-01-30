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
        Schema::create('documentos_postulantes', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->string('tipo_documento', 50);
            $table->string('nombre_original', 255);
            $table->string('saved_filename', 255);
            $table->string('tipo_mime', 100)->nullable();
            $table->integer('tamano_bytes')->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Foreign key
            $table->foreignId('username')->references('username', 'users')->onDelete('cascade');
            
            // Índices
            $table->index('username');
            $table->index('tipo_documento');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_postulantes');
    }
};
