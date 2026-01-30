<?php

use App\Http\Controllers\Api\ConveniosController;
use Illuminate\Support\Facades\Route;

// Convenios routes
Route::prefix('convenios')->group(function () {
    // Public routes
    Route::get('validar/{nit_empresa}/{cedula_trabajador}', [ConveniosController::class, 'validarConvenioTrabajador']);
    Route::post('validar', [ConveniosController::class, 'validarConvenioPost']);
});
