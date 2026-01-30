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
        Schema::create('empresas_convenio', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nit')->unique();
            $table->string('razon_social', 255);
            $table->date('fecha_convenio')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('estado', ['Activo', 'Inactivo', 'Suspendido', 'Vencido'])->default('Activo');
            $table->string('representante_documento', 20)->nullable();
            $table->string('representante_nombre', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('sector_economico', 100)->nullable();
            $table->integer('numero_empleados')->nullable();
            $table->string('tipo_empresa', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('notas_internas')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('nit');
            $table->index('estado');
            $table->index('fecha_vencimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas_convenio');
    }
};
