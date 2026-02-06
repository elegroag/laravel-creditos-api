<?php

use App\Http\Controllers\Api\FirmaDigitalController;
use Illuminate\Support\Facades\Route;

// Rutas autenticadas con JWT
Route::middleware('auth.jwt')->group(function () {
    // Firma Digital routes
    Route::prefix('solicitudes')->group(function () {
        // Proceso de firmado
        Route::post('{solicitud_id}/iniciar-firmado', [FirmaDigitalController::class, 'iniciarProcesoFirmado']);
        Route::get('{solicitud_id}/estado-firmado', [FirmaDigitalController::class, 'consultarEstadoFirmado']);
    });
});

// Webhook sin autenticación JWT, pero con validación de firma HMAC
Route::prefix('firmas')->group(function () {
    Route::post('webhook', [FirmaDigitalController::class, 'webhookFirmaCompletada'])
        ->middleware('webhook.signature:firma_plus');
});
