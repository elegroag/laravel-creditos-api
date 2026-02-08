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
            $table->decimal('valor_solicitud', 15, 2);
            $table->integer('plazo_meses');
            $table->decimal('tasa_interes', 5, 2);
            $table->string('estado', 50);
            $table->date('fecha_radicado')->nullable();
            $table->char('producto_tipo', 2)->nullable();
            $table->boolean('ha_tenido_credito')->default(false);
            $table->string('detalle_modalidad')->nullable();
            $table->char('tipo_credito', 2)->nullable();
            $table->char('moneda', 3)->default('COP');
            $table->decimal('cuota_mensual', 12, 2)->nullable();
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
