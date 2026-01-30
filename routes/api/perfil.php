<?php

use App\Http\Controllers\Api\PerfilController;
use Illuminate\Support\Facades\Route;

// Perfil routes
Route::prefix('perfil')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Endpoints principales (Python original)
        Route::get('/', [PerfilController::class, 'obtenerPerfil']);
        Route::put('/', [PerfilController::class, 'actualizarPerfil']);
        Route::post('/', [PerfilController::class, 'actualizarPerfil']);
        Route::put('password', [PerfilController::class, 'cambiarPassword']);
        Route::get('activity', [PerfilController::class, 'obtenerActividadUsuario']);
        
        // Endpoints adicionales (mejoras Laravel)
        Route::get('estadisticas', [PerfilController::class, 'obtenerEstadisticasPerfil']);
        Route::get('configuracion', [PerfilController::class, 'obtenerConfiguracionPerfil']);
        Route::put('configuracion', [PerfilController::class, 'actualizarConfiguracionPerfil']);
    });
});
