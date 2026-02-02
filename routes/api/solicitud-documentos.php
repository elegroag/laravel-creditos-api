<?php

use App\Http\Controllers\Api\SolicitudDocumentosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Solicitud Documentos routes
    Route::prefix('solicitudes-credito/{solicitud_id}/documentos')->group(function () {
        // Protected routes (require authentication)

        // Endpoints principales (Python original)
        Route::get('requeridos', [SolicitudDocumentosController::class, 'listarDocumentosRequeridos']);
        Route::get('/', [SolicitudDocumentosController::class, 'listarDocumentosSolicitud']);
        Route::post('/', [SolicitudDocumentosController::class, 'agregarDocumentoSolicitud']);
        Route::delete('{documento_id}', [SolicitudDocumentosController::class, 'eliminarDocumentoSolicitud']);

        // Endpoints adicionales (mejoras Laravel)
        Route::get('{documento_id}/descargar', [SolicitudDocumentosController::class, 'descargarDocumento']);
        Route::get('estadisticas', [SolicitudDocumentosController::class, 'obtenerEstadisticasDocumentos']);
    });
});
