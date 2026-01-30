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
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->enum('tipo_postulante', ['trabajador', 'empresa']);
            $table->bigInteger('empresa_nit')->nullable();
            $table->string('empresa_razon_social', 255)->nullable();
            $table->json('datos_personales')->nullable();
            $table->json('datos_laborales')->nullable();
            $table->json('datos_financieros')->nullable();
            $table->enum('estado', ['iniciada', 'completa', 'verificada', 'aprobada', 'rechazada'])->default('iniciada');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
            $table->foreign('empresa_nit')->references('nit')->on('empresas_convenio')->onDelete('set null');

            // Índices
            $table->index('username');
            $table->index('tipo_postulante');
            $table->index('empresa_nit');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};
