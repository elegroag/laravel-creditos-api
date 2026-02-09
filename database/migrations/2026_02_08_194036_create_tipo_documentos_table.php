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
        Schema::create('tipo_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->unique(); // key del tipo de documento
            $table->string('detalle', 100); // descripción del tipo
            $table->smallInteger('orden'); // orden para mostrar
            $table->boolean('activo')->default(true); // para activar/desactivar tipos
            $table->timestamps();

            // Índices
            $table->index('tipo');
            $table->index('orden');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_documentos');
    }
};
