<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUsuariosController;
use App\Http\Controllers\Api\SolicitudesCreditoController;
use Illuminate\Support\Facades\Route;

// Ejemplos de uso de middlewares de autenticación

// 1. Autenticación básica (solo requiere estar autenticado)
Route::middleware('auth.api')->group(function () {
    Route::get('/user/profile', [AuthController::class, 'me']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
});

// 2. Autenticación con rol específico
Route::middleware(['auth.api', 'role:administrator'])->group(function () {
    Route::get('/admin/users', [AdminUsuariosController::class, 'listarUsuarios']);
    Route::post('/admin/users', [AdminUsuariosController::class, 'crearUsuario']);
    Route::put('/admin/users/{id}', [AdminUsuariosController::class, 'actualizarUsuario']);
    Route::delete('/admin/users/{id}', [AdminUsuariosController::class, 'eliminarUsuario']);
});

// 3. Autenticación con múltiples roles (OR)
Route::middleware(['auth.api', 'role:administrator,adviser'])->group(function () {
    Route::get('/solicitudes/all', [SolicitudesCreditoController::class, 'listarSolicitudesCredito']);
    Route::get('/solicitudes/estadisticas', [SolicitudesCreditoController::class, 'obtenerEstadisticasSolicitudes']);
});

// 4. Autenticación con permiso específico
Route::middleware(['auth.api', 'permission:manage_solicitudes'])->group(function () {
    Route::post('/solicitudes', [SolicitudesCreditoController::class, 'crearSolicitudCredito']);
    Route::put('/solicitudes/{id}', [SolicitudesCreditoController::class, 'actualizarSolicitudCredito']);
    Route::delete('/solicitudes/{id}', [SolicitudesCreditoController::class, 'eliminarSolicitudCredito']);
});

// 5. Autenticación con múltiples permisos (OR)
Route::middleware(['auth.api', 'permission:view_solicitudes,manage_solicitudes'])->group(function () {
    Route::get('/solicitudes/{id}', [SolicitudesCreditoController::class, 'obtenerSolicitudCredito']);
    Route::post('/solicitudes/buscar', [SolicitudesCreditoController::class, 'buscarSolicitudes']);
});

// 6. Combinación de rol y permiso (AND)
Route::middleware(['auth.api', 'role:adviser', 'permission:view_reports'])->group(function () {
    Route::get('/reports/solicitudes', [ReportController::class, 'solicitudesReport']);
    Route::get('/reports/users', [ReportController::class, 'usersReport']);
});

// 7. Uso del AuthMiddleware con parámetros
Route::middleware('auth.api:administrator')->group(function () {
    // Solo usuarios con rol 'administrator'
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

Route::middleware('auth.api:null,manage_users')->group(function () {
    // Cualquier usuario autenticado con permiso 'manage_users'
    Route::post('/admin/users', [AdminUsuariosController::class, 'crearUsuario']);
});

// 8. Middleware personalizado con múltiples parámetros
Route::middleware('auth.api:adviser,view_solicitudes')->group(function () {
    // Usuarios con rol 'adviser' Y permiso 'view_solicitudes'
    Route::get('/adviser/solicitudes', [AdviserController::class, 'solicitudes']);
});

// 9. Ejemplo de uso en controladores con el trait HasAuthHelpers
Route::middleware('auth.api')->group(function () {
    Route::get('/user/info', function () {
        // En un controller que use HasAuthHelpers:
        // $this->isAdmin() - Verifica si es admin
        // $this->hasRole('adviser') - Verifica rol específico
        // $this->hasPermission('manage_users') - Verifica permiso específico
        // $this->hasAnyRole(['admin', 'adviser']) - Verifica múltiples roles
        // $this->hasAnyPermission(['view_users', 'manage_users']) - Verifica múltiples permisos
        
        return response()->json([
            'message' => 'Endpoint de ejemplo con helpers de autenticación'
        ]);
    });
});

// 10. Rutas públicas (sin autenticación)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/auth/verify', [AuthController::class, 'verify']);

// 11. Rutas con rate limiting adicional
Route::middleware(['auth.api', 'throttle:60,1'])->group(function () {
    Route::post('/sensitive-operation', [SensitiveController::class, 'operation']);
});

// 12. Rutas con validación de IP (ejemplo para admin)
Route::middleware(['auth.api:administrator', 'throttle:10,1'])->group(function () {
    Route::post('/admin/critical-operation', [AdminController::class, 'criticalOperation']);
});
