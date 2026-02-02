<?php

use App\Http\Controllers\Api\AdminConveniosController;
use Illuminate\Support\Facades\Route;

// Admin Convenios routes
Route::middleware('auth.jwt')->group(function () {
    Route::prefix('admin')->group(function () {

        // Operaciones específicas (deben ir antes de las rutas con parámetros)
        Route::get('empresas-convenios/export', [AdminConveniosController::class, 'export']);
        Route::post('empresas-convenios/import', [AdminConveniosController::class, 'import']);

        // Empresas convenios CRUD
        Route::get('empresas-convenios', [AdminConveniosController::class, 'index']);
        Route::post('empresas-convenios', [AdminConveniosController::class, 'store']);
        Route::get('empresas-convenios/{id}', [AdminConveniosController::class, 'show']);
        Route::put('empresas-convenios/{id}', [AdminConveniosController::class, 'update']);
        Route::delete('empresas-convenios/{id}', [AdminConveniosController::class, 'destroy']);
        Route::put('empresas-convenios/{id}/estado', [AdminConveniosController::class, 'toggleEstado']);
    });
});
