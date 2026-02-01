<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\InicioController;

Route::prefix('web')->group(function () {

    Route::middleware('auth')->group(function () {
        Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');
        Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', function () {
            return redirect()->route('inicio');
        })->name('dashboard');
    });
});
