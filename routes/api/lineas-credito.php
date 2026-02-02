<?php

use App\Http\Controllers\Api\LineasCreditoController;
use Illuminate\Support\Facades\Route;

// Líneas de Crédito routes
Route::middleware('auth.jwt')->group(function () {

    Route::prefix('lineas_credito')->group(function () {
        // Protected routes (require authentication)
        // Endpoints principales
        Route::get('parametros', [LineasCreditoController::class, 'obtenerParametros']);
        Route::get('tipo_creditos', [LineasCreditoController::class, 'obtenerTiposCreditos']);

        // Endpoints adicionales
        Route::get('completo', [LineasCreditoController::class, 'obtenerLineasCredito']);
        Route::get('disponibilidad', [LineasCreditoController::class, 'verificarDisponibilidad']);
        Route::get('estadisticas', [LineasCreditoController::class, 'obtenerEstadisticas']);
    });
});
