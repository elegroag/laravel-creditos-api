<?php

use App\Http\Controllers\Api\LineasInversionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Líneas de Inversión routes
    Route::prefix('lineas-inversion')->group(function () {
        // Endpoints principales (Python original)
        Route::get('/', [LineasInversionController::class, 'obtenerTodas']);
        Route::get('{linea_id}', [LineasInversionController::class, 'obtenerPorId']);
        Route::get('categoria/{categoria}', [LineasInversionController::class, 'obtenerPorCategoria']);
        Route::post('initialize', [LineasInversionController::class, 'inicializarLineas']);

        // Endpoints adicionales (mejoras Laravel)
        Route::get('estadisticas', [LineasInversionController::class, 'obtenerEstadisticas']);
        Route::post('buscar', [LineasInversionController::class, 'buscarLineas']);
        Route::post('comparar', [LineasInversionController::class, 'compararLineas']);
    });
});
