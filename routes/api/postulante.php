<?php

use App\Http\Controllers\Api\PostulanteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Postulante routes
    Route::prefix('postulante')->group(function () {
        Route::get('crear-tercero-api/{solicitud_id}', [PostulanteController::class, 'crearTerceroUseApi']);
        Route::post('conyuge-trabajador', [PostulanteController::class, 'buscarConyugeTrabajador']);
        Route::get('buscar-tercero-api/{tipdoc}/{cedula}', [PostulanteController::class, 'buscarTerceroUseApi']);
    });
});

#http://localhost:5001/api/conyuge-trabajador
