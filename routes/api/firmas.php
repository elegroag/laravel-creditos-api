<?php

use App\Http\Controllers\Api\FirmaDigitalController;
use Illuminate\Support\Facades\Route;

// Firma Digital routes
Route::prefix('solicitudes')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Proceso de firmado
        Route::post('{solicitud_id}/iniciar-firmado', [FirmaDigitalController::class, 'iniciarProcesoFirmado']);
        Route::get('{solicitud_id}/estado-firmado', [FirmaDigitalController::class, 'consultarEstadoFirmado']);
    });
});

// Webhook routes (sin autenticación para recibir notificaciones externas)
Route::prefix('firmas')->group(function () {
    Route::post('webhook', [FirmaDigitalController::class, 'webhookFirmaCompletada']);
});
