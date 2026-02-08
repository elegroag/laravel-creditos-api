<?php

use App\Http\Controllers\Api\AdminDashboardController;
use Illuminate\Support\Facades\Route;

// Admin Dashboard routes - Estadísticas para dashboard administrativo
Route::middleware('auth.jwt')->group(function () {
    Route::prefix('admin/dashboard')->group(function () {
        // Estadísticas generales del dashboard
        Route::get('estadisticas', [AdminDashboardController::class, 'obtenerEstadisticasGenerales']);
    });
});

// http://localhost:8000/api/admin/dashboard/estadisticas
