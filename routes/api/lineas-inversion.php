<?php

use App\Http\Controllers\Api\LineasInversionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Líneas de Inversión routes
    Route::prefix('lineas-inversion')->group(function () {

        Route::post('buscar-todas', [LineasInversionController::class, 'buscarLineas']);
    });
});
