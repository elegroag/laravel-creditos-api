<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear una notificación de prueba
        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'firma_completada',
            'notifiable_type' => 'User',
            'notifiable_id' => 'test_user',
            'data' => json_encode([
                'titulo' => 'Notificación de Prueba',
                'mensaje' => 'Esta es una notificación de prueba para el sistema',
                'solicitud_id' => 'TEST-001',
                'url' => '/admin/solicitudes/show/TEST-001'
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_notification');
    }
};
