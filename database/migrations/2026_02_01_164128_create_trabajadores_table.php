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
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 20)->unique();
            $table->string('tipo_documento', 10);
            $table->string('primer_nombre', 100);
            $table->string('segundo_nombre', 100)->nullable();
            $table->string('primer_apellido', 100);
            $table->string('segundo_apellido', 100)->nullable();
            $table->text('direccion')->nullable();
            $table->string('ciudad_codigo', 10)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->decimal('salario', 12, 2)->nullable();
            $table->date('fecha_salario')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('ciudad_nacimiento', 100)->nullable();
            $table->string('nivel_educativo', 50)->nullable();
            $table->string('codigo_categoria', 10)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->date('fecha_afiliacion')->nullable();
            $table->string('cargo', 100)->nullable();
            $table->string('empresa_nit', 20)->nullable();
            $table->integer('antiguedad_meses')->nullable();
            $table->string('tipo_contrato', 50)->nullable();
            $table->string('sector_economico', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('cedula');
            $table->index('estado');
            $table->index('empresa_nit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};
