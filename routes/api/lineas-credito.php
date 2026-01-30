<?php

use App\Http\Controllers\Api\LineasCreditoController;
use Illuminate\Support\Facades\Route;

// Líneas de Crédito routes
Route::prefix('lineas_credito')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Endpoints principales
        Route::get('parametros', [LineasCreditoController::class, 'obtenerParametros']);
        Route::get('tipo_creditos', [LineasCreditoController::class, 'obtenerTiposCreditos']);
        
        // Endpoints adicionales
        Route::get('completo', [LineasCreditoController::class, 'obtenerLineasCredito']);
        Route::get('disponibilidad', [LineasCreditoController::class, 'verificarDisponibilidad']);
        Route::get('estadisticas', [LineasCreditoController::class, 'obtenerEstadisticas']);
    });
});
