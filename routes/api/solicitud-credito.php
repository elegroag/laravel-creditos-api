<?php

use Illuminate\Support\Facades\Route;

// Rutas para solicitud-credito (sin generación XML)
Route::middleware('auth.jwt')->group(function () {
    Route::prefix('solicitud-credito')->group(function () {
        // Aquí se pueden agregar rutas relacionadas con solicitud-credito
        // que no requieran generación de XML
    });
});