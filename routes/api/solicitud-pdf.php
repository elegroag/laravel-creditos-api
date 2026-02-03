<?php

use App\Http\Controllers\Api\SolicitudPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Solicitud PDF routes
    Route::prefix('solicitudes')->group(function () {
        // Protected routes (require authentication)

        // Endpoints principales (Python original)
        Route::post('/{solicitud_id}/generar-pdf', [SolicitudPdfController::class, 'generarPdfSolicitud']);
        Route::get('/{solicitud_id}/descargar-pdf', [SolicitudPdfController::class, 'descargarPdfSolicitud']);
        Route::get('/{solicitud_id}/estado-pdf', [SolicitudPdfController::class, 'verificarEstadoPdf']);

        // Endpoints adicionales (mejoras Laravel)
        Route::delete('/{solicitud_id}/eliminar-pdf', [SolicitudPdfController::class, 'eliminarPdfSolicitud']);
        Route::get('/{solicitud_id}/estadisticas-pdf', [SolicitudPdfController::class, 'obtenerEstadisticasPdf']);
    });
});
