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
        Schema::create('solicitud_solicitante', function (Blueprint $table) {
            $table->id();

            // Foreign key a solicitudes_credito
            $table->string('solicitud_id', 20);

            // Datos básicos del solicitante
            $table->enum('tipo_persona', ['natural', 'juridica'])->default('natural');
            $table->string('tipo_documento', 3);
            $table->string('numero_documento', 20);
            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('razon_social', 200)->nullable();
            $table->string('nit', 20)->nullable();

            // Datos personales
            $table->date('fecha_nacimiento')->nullable();
            $table->char('pais_nacimiento', 3)->nullable();
            $table->date('fecha_expedicion')->nullable();
            $table->enum('genero', ['M', 'F', 'O'])->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->string('nivel_educativo', 50)->nullable();
            $table->string('profesion', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->char('telefono_fijo', 10)->nullable();
            $table->char('telefono_movil', 10)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('barrio', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->char('pais_residencia', 3)->nullable();
            $table->char('tipo_vivienda', 2)->nullable();
            $table->boolean('vive_con_nucleo_familiar')->default(false);
            $table->smallInteger('personas_a_cargo')->default(0);
            $table->string('departamento', 100)->nullable();
            $table->string('codigo_categoria', 1)->nullable();

            // Datos laborales
            $table->string('cargo', 100)->nullable();
            $table->decimal('salario', 15, 2)->nullable();
            $table->integer('antiguedad_meses')->nullable();
            $table->string('tipo_contrato', 50)->nullable();
            $table->string('sector_economico', 100)->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('solicitud_id')->references('numero_solicitud')->on('solicitudes_credito')->onDelete('cascade');

            // Índices
            $table->index('solicitud_id');
            $table->index('tipo_documento');
            $table->index('numero_documento');
            $table->index('nit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_solicitante');
    }
};
