<?php

use App\Http\Controllers\Api\PostulacionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Postulaciones routes
    Route::prefix('postulaciones')->group(function () {
        // Protected routes (require authentication and admin role)

        // Endpoints principales (Python original)
        Route::post('/', [PostulacionesController::class, 'crearPostulacion']);
        Route::get('/', [PostulacionesController::class, 'listarPostulaciones']);
        Route::get('{postulacion_id}', [PostulacionesController::class, 'obtenerPostulacion']);
        Route::patch('{postulacion_id}/estado', [PostulacionesController::class, 'actualizarEstado']);

        // Endpoints adicionales (mejoras Laravel)
        Route::delete('{postulacion_id}', [PostulacionesController::class, 'eliminarPostulacion']);
        Route::get('estadisticas', [PostulacionesController::class, 'obtenerEstadisticas']);
        Route::post('buscar', [PostulacionesController::class, 'buscarPostulaciones']);
        Route::post('sala/unirse', [PostulacionesController::class, 'unirseSalaPostulacion']);

        // Endpoint para WebSocket (simulado)
        Route::post('join_postulacion', [PostulacionesController::class, 'unirseSalaPostulacion']);
    });
});
