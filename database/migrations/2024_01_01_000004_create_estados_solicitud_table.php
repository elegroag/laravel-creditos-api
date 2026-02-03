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
        Schema::create('estados_solicitud', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->integer('orden');
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índices
            $table->index('orden');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_solicitud');
    }
};
