<?php

use App\Http\Controllers\Api\SolicitudesCreditoController;
use Illuminate\Support\Facades\Route;

// Protected routes (require JWT authentication)
Route::middleware('auth.jwt')->group(function () {
    // Solicitudes de Crédito routes
    Route::prefix('solicitudes-credito')->group(function () {

        // Endpoints principales (Python original)
        Route::post('/', [SolicitudesCreditoController::class, 'crearSolicitudCredito']);
        Route::get('/', [SolicitudesCreditoController::class, 'listarSolicitudesCredito']);
        Route::get('all-user', [SolicitudesCreditoController::class, 'listarSolicitudesCreditoForUser']);
        Route::get('{solicitud_id}', [SolicitudesCreditoController::class, 'obtenerSolicitudCredito']);
        Route::put('{solicitud_id}', [SolicitudesCreditoController::class, 'actualizarSolicitudCredito']);
        Route::delete('{solicitud_id}', [SolicitudesCreditoController::class, 'eliminarSolicitudCredito']);
        Route::post('{solicitud_id}/finalizar', [SolicitudesCreditoController::class, 'finalizarProcesoSolicitud']);
        // Endpoints adicionales (mejoras Laravel)
        Route::get('estadisticas', [SolicitudesCreditoController::class, 'obtenerEstadisticasSolicitudes']);
        Route::post('buscar', [SolicitudesCreditoController::class, 'buscarSolicitudes']);
        // Endpoint para contar solicitudes por estado
        Route::get('estados/count', [SolicitudesCreditoController::class, 'contarSolicitudesPorEstado']);

        #http://localhost:5001/api/solicitudes-credito/paginado/20/0/@
        Route::get('paginado/{limit}/{offset}/{estado}', [SolicitudesCreditoController::class, 'listarSolicitudesCreditoPaginado']);
    });

    //no cambiar esta ruta es  requerida por el frontend del sistema
    Route::post('solicitud-credito/guardar', [SolicitudesCreditoController::class, 'guardarSolicitud']);

    //asi lo requiere el frontend no se puede cambiar la ruta /api/estados-solicitud
    Route::get('estados-solicitud', [SolicitudesCreditoController::class, 'obtenerEstadosSolicitud']);
});
