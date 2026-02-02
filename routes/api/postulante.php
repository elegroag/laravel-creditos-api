<?php

use App\Http\Controllers\Api\PostulanteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Postulante routes
    Route::prefix('postulante')->group(function () {
        // Protected routes (require authentication){
        Route::post('conyuge-trabajador', [PostulanteController::class, 'buscarConyugeTrabajador']);
    });
});
