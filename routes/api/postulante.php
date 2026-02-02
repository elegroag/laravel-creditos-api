<?php

use App\Http\Controllers\Api\PostulanteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    // Postulante routes
    Route::prefix('postulante')->group(function () {
        // Protected routes (require authentication){
    });

    //se debe usar sin prefix, asi lo requiere el frontend
    Route::post('conyuge-trabajador', [PostulanteController::class, 'buscarConyugeTrabajador']);
});

#http://localhost:5001/api/conyuge-trabajador
