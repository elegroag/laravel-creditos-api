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
        Schema::create('entidad_digital', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('tipo_identificacion', 10);
            $table->string('numero_identificacion', 20);
            $table->json('documentos')->nullable();
            $table->string('selfie', 500)->nullable();
            $table->string('clave_firma_hash', 255)->nullable();
            $table->enum('estado', ['activa', 'inactiva', 'bloqueada'])->default('activa');
            $table->json('metadata')->nullable();
            $table->json('validaciones')->nullable();
            $table->timestamp('last_validation_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');

            // Índices
            $table->index('tipo_identificacion');
            $table->index('numero_identificacion');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entidad_digital');
    }
};
