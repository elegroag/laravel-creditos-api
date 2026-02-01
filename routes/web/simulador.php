<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\SimuladorController;

Route::prefix('web')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('/simulador', [SimuladorController::class, 'index'])->name('simulador.index');
        Route::get('/simulador/lineas-credito', [SimuladorController::class, 'lineasCredito'])->name('simulador.lineas_credito');
        Route::get('/simulador/{tipcre}', [SimuladorController::class, 'showLinea'])->name('simulador.tipcre');
    });
});
