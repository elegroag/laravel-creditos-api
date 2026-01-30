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
        Schema::create('pdfs_generados', function (Blueprint $table) {
            $table->id();
            $table->string('solicitud_id', 20)->unique();
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->json('generado_en')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('solicitud_id')->references('numero_solicitud', 'solicitudes_credito')->onDelete('cascade');

            // Índices
            $table->index('solicitud_id');
            $table->index('filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdfs_generados');
    }
};
