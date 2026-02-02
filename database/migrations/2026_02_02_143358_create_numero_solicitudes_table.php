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
        Schema::create('numero_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('linea_credito', 10); // Ej: '03', '04', etc.
            $table->integer('vigencia'); // YYYYMM format
            $table->integer('secuencia'); // Consecutivo auto-incrementable
            $table->timestamps();

            // Índices únicos para evitar duplicados
            $table->unique(['linea_credito', 'vigencia'], 'uniq_linea_vigencia');

            // Índices para búsquedas
            $table->index('linea_credito');
            $table->index('vigencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numero_solicitudes');
    }
};
