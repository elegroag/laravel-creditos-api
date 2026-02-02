<?php

use App\Http\Controllers\Api\FirmasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Firmas routes
    Route::prefix('solicitud-credito')->group(function () {
        // Protected routes (require authentication and permissions)

        // Gestión de firmas
        Route::post('firmas', [FirmasController::class, 'firmarDocumento']);

        // Tokens para compartir firmas
        Route::post('firmas/share', [FirmasController::class, 'crearTokenShare']);
        Route::get('firmas/share/{token}', [FirmasController::class, 'obtenerTokenShare']);
        Route::post('firmas/share/{token}/firmar', [FirmasController::class, 'firmarConToken']);
    });
});
