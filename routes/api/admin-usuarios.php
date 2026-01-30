<?php

use App\Http\Controllers\Api\AdminUsuariosController;
use Illuminate\Support\Facades\Route;

// Admin Usuarios routes
Route::prefix('admin/users')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Endpoints principales (Python original)
        Route::get('/', [AdminUsuariosController::class, 'obtenerUsuarios']);
        Route::get('{user_id}', [AdminUsuariosController::class, 'obtenerUsuario']);
        Route::post('/', [AdminUsuariosController::class, 'crearUsuario']);
        Route::put('{user_id}', [AdminUsuariosController::class, 'actualizarUsuario']);
        Route::delete('{user_id}', [AdminUsuariosController::class, 'eliminarUsuario']);
        Route::put('{user_id}/estado', [AdminUsuariosController::class, 'cambiarEstadoUsuario']);
        Route::get('export', [AdminUsuariosController::class, 'exportarUsuarios']);
        
        // Endpoints adicionales (mejoras Laravel)
        Route::get('estadisticas', [AdminUsuariosController::class, 'obtenerEstadisticas']);
        Route::post('import', [AdminUsuariosController::class, 'importarUsuarios']);
        Route::get('{user_id}/actividad', [AdminUsuariosController::class, 'obtenerActividadUsuario']);
    });
});
