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
            $table->string('radicado', 20)->unique();
            $table->integer('numeric_secuencia')->default(0);
            $table->string('linea_credito', 10)->default('03');
            $table->integer('vigencia');
            $table->timestamps();
            
            // Índices
            $table->index('vigencia');
            $table->index('linea_credito');
            $table->index('numeric_secuencia');
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
