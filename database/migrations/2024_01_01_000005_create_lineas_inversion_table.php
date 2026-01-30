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
        Schema::create('lineas_inversion', function (Blueprint $table) {
            $table->id();
            $table->string('linea_credito', 255);
            $table->decimal('monto_maximo_pesos', 15, 2);
            $table->string('plazo_maximo', 50)->nullable();
            $table->json('tasas_interes_anual')->nullable();
            $table->json('requisitos')->nullable();
            $table->enum('categoria', ['A', 'B', 'C'])->default('B');
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');
            $table->timestamps();
            
            // Índices
            $table->index('categoria');
            $table->index('estado');
            $table->index('monto_maximo_pesos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineas_inversion');
    }
};
