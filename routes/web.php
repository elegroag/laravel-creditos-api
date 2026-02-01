<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('inicio');
})->name('welcome');


// Importar rutas de los diferentes módulos
require __DIR__ . '/web/guest.php';
require __DIR__ . '/web/inicio.php';
require __DIR__ . '/web/simulador.php';
require __DIR__ . '/web/perfil.php';
