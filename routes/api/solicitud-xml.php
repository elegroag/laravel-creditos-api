<?php

use App\Http\Controllers\Api\SolicitudXmlController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Solicitud XML routes
    Route::prefix('solicitud-credito/xml')->group(function () {
        // Protected routes (require authentication)
        // Endpoints principales (Python original - deprecated)
        Route::post('/', [SolicitudXmlController::class, 'generarXmlSolicitud']);
        Route::post('extract', [SolicitudXmlController::class, 'extraerDatosXml']);

        // Endpoints adicionales (mejoras Laravel)
        Route::post('validar', [SolicitudXmlController::class, 'validarXml']);
        Route::get('archivos', [SolicitudXmlController::class, 'listarArchivosXml']);
        Route::delete('archivos', [SolicitudXmlController::class, 'eliminarArchivoXml']);
        Route::get('ejemplo', [SolicitudXmlController::class, 'generarXmlEjemplo']);
        Route::post('comparar', [SolicitudXmlController::class, 'compararXmls']);
    });
});
