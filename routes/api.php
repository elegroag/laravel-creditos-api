<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Importar rutas de los diferentes módulos
require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/convenios.php';
require __DIR__ . '/api/postulante.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/admin-usuarios.php';
require __DIR__ . '/api/admin-dashboard.php';
require __DIR__ . '/api/firmas.php';
require __DIR__ . '/api/solicitud-credito.php';
require __DIR__ . '/api/solicitudes-credito.php';
require __DIR__ . '/api/solicitud-documentos.php';
require __DIR__ . '/api/documentos.php';
require __DIR__ . '/api/solicitud-pdf.php';
require __DIR__ . '/api/mobile.php';
require __DIR__ . '/api/lineas-credito.php';
require __DIR__ . '/api/lineas-inversion.php';
require __DIR__ . '/api/perfil.php';
require __DIR__ . '/api/postulaciones.php';

// Rutas de notificaciones
Route::prefix('notifications')->group(function () {
    require __DIR__ . '/api/notifications.php';
});


Route::get('health', function () {
    return response()->json(['status' => 'ok', 'message' => 'Bienvenido a Comfaca en Línea']);
});

// Rutas de la API (ejemplo)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
