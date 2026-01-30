<?php

use App\Http\Controllers\Api\PostulanteController;
use Illuminate\Support\Facades\Route;

// Postulante routes
Route::prefix('postulante')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('conyuge-trabajador', [PostulanteController::class, 'buscarConyugeTrabajador']);
    });
});
