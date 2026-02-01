<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;

Route::prefix('web')->group(function () {
    // Rutas de autenticación
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/adviser', [AuthController::class, 'showAdviserLoginForm'])->name('adviser.login');
        Route::post('/adviser/session', [AuthController::class, 'createAdviserSession'])->name('adviser.session.store');
        Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register']);

        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify.show');
        Route::post('/verify', [AuthController::class, 'verify'])->name('verify.store');
    });
});
