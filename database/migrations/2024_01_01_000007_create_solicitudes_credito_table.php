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
        Schema::create('solicitudes_credito', function (Blueprint $table) {
            $table->string('numero_solicitud', 20)->primary();
            $table->string('owner_username', 100);
            $table->string('xml_filename', 255)->nullable();
            $table->decimal('monto_solicitado', 15, 2);
            $table->decimal('monto_aprobado', 15, 2)->default(0);
            $table->integer('plazo_meses');
            $table->decimal('tasa_interes', 5, 2);
            $table->string('destino_credito', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('estado_codigo', 50);
            $table->timestamps();

            // Foreign keys
            $table->foreignId('owner_username')->references('username', 'users')->onDelete('cascade');
            $table->foreign('estado_codigo')->references('codigo', 'estados_solicitud');

            // Índices
            $table->index('numero_solicitud');
            $table->index('owner_username');
            $table->index('estado_codigo');
            $table->index('monto_solicitado');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_credito');
    }
};
