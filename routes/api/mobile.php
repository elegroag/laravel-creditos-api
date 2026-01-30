<?php

use App\Http\Controllers\Api\MovileController;
use Illuminate\Support\Facades\Route;

// Movile routes
Route::prefix('auth/mobile')->group(function () {
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Endpoints principales (Python original)
        Route::get('qr-token', [MovileController::class, 'getQrToken']);
        Route::get('authorize/{token}', [MovileController::class, 'mobileAuthorize']);
        
        // Endpoints adicionales (mejoras Laravel)
        Route::get('status', [MovileController::class, 'getMobileAuthStatus']);
        Route::delete('qr-token', [MovileController::class, 'revokeQrToken']);
        Route::post('confirma-capturas', [MovileController::class, 'confirmCapturas']);
        Route::get('device-info', [MovileController::class, 'getDeviceInfo']);
        Route::post('validate-qr-token', [MovileController::class, 'validateQrToken']);
    });
});
