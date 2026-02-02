<?php

use App\Http\Controllers\Api\SolicitudPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Solicitud PDF routes
    Route::prefix('solicitudes/{solicitud_id}')->group(function () {
        // Protected routes (require authentication)

        // Endpoints principales (Python original)
        Route::post('generar-pdf', [SolicitudPdfController::class, 'generarPdfSolicitud']);
        Route::get('descargar-pdf', [SolicitudPdfController::class, 'descargarPdfSolicitud']);
        Route::get('estado-pdf', [SolicitudPdfController::class, 'verificarEstadoPdf']);

        // Endpoints adicionales (mejoras Laravel)
        Route::delete('eliminar-pdf', [SolicitudPdfController::class, 'eliminarPdfSolicitud']);
        Route::get('estadisticas-pdf', [SolicitudPdfController::class, 'obtenerEstadisticasPdf']);
    });
});
