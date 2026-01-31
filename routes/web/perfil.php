<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\PerfilController;

Route::middleware('auth')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'index'])->name('perfil');
});
