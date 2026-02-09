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
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->char('rol_en_solicitud', 1)->default('T')->comment('T=Trabajador, S=Solicitante, C=Codeudor, E=Empleador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->dropColumn('rol_en_solicitud');
        });
    }
};
