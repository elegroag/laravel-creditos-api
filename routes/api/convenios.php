<?php

use App\Http\Controllers\Api\ConveniosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Convenios routes
    Route::prefix('convenios')->group(function () {
        // Public routes
        Route::get('validar/{nit_empresa}/{cedula_trabajador}', [ConveniosController::class, 'validarConvenioTrabajador']);
        Route::post('validar', [ConveniosController::class, 'validarConvenioPost']);

        // Protected routes
        Route::get('activo', [ConveniosController::class, 'obtenerConvenioActivo']);
    });
});
