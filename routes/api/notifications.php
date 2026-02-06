<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

// Todas las rutas requieren autenticación JWT
Route::middleware('auth.jwt')->group(function () {
    // Obtener todas las notificaciones del usuario
    Route::get('/', [NotificationController::class, 'index']);
    
    // Obtener contador de notificaciones no leídas
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    
    // Marcar todas las notificaciones como leídas
    Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    
    // Marcar una notificación específica como leída
    Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
    
    // Eliminar una notificación
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});
