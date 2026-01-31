<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\InicioController;

Route::middleware('auth')->group(function () {
    Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', function () {
        return inertia('dashboard');
    })->name('dashboard');
});
