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
            $table->string('estado', 50);
            $table->timestamps();

            // Foreign keys
            $table->foreign('owner_username')->references('username')->on('users')->onDelete('cascade');
            $table->foreign('estado')->references('id')->on('estados_solicitud');

            // Índices
            $table->index('owner_username');
            $table->index('estado');
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
