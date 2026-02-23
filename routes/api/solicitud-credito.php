<?php

use App\Http\Controllers\Api\SolicitudesCreditoController;
use Illuminate\Support\Facades\Route;

// Rutas para solicitud-credito (sin generación XML)
Route::middleware('auth.jwt')->group(function () {
    Route::prefix('solicitud-credito')->group(function () {
        Route::get('enviar-solicitud/{solicitud_id}', [SolicitudesCreditoController::class, 'enviarSolicitudUseApi']);
        Route::get('consultar-solicitud/{solicitud_id}', [SolicitudesCreditoController::class, 'consultarSolicitudUseApi']);
    });
});
