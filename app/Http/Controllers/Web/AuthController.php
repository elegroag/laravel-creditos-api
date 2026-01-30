<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Mostrar la página de login
     */
    public function showLoginForm()
    {
        // Si el usuario ya está autenticado, redirigir al dashboard
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    /**
     * Procesar el login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Intentar autenticación
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Preparar respuesta de autenticación (token para API si se necesita)
            // En web, Laravel maneja la sesión automáticamente
            $authData = [
                'access_token' => $user->createToken('web')->plainTextToken,
                'token_type' => 'bearer',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'tipo_documento' => $user->tipo_documento,
                    'numero_documento' => $user->numero_documento,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'roles' => $this->getUserRoles($user),
                    'permissions' => $this->getUserPermissions($user),
                    'full_name' => trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? '')),
                    'trabajador' => $user->trabajador ? [
                        'cargo' => $user->trabajador->cargo ?? '',
                        'cedula' => $user->trabajador->cedula ?? '',
                        'ciudad_codigo' => $user->trabajador->ciudad_codigo ?? '',
                        'ciudad_nacimiento' => $user->trabajador->ciudad_nacimiento ?? '',
                        'direccion' => $user->trabajador->direccion ?? '',
                        'email' => $user->trabajador->email ?? '',
                        'empresa' => $user->trabajador->empresa ? [
                            'ciudad_codigo' => $user->trabajador->empresa->ciudad_codigo ?? '',
                            'direccion' => $user->trabajador->empresa->direccion ?? '',
                            'nit' => $user->trabajador->empresa->nit ?? '',
                            'razon_social' => $user->trabajador->empresa->razon_social ?? '',
                            'telefono' => $user->trabajador->empresa->telefono ?? '',
                        ] : null,
                        'estado' => $user->trabajador->estado ?? '',
                        'estado_civil' => $user->trabajador->estado_civil ?? '',
                        'fecha_afiliacion' => $user->trabajador->fecha_afiliacion ?? '',
                        'fecha_nacimiento' => $user->trabajador->fecha_nacimiento ?? '',
                        'fecha_salario' => $user->trabajador->fecha_salario ?? '',
                        'nivel_educativo' => $user->trabajador->nivel_educativo ?? '',
                        'primer_apellido' => $user->trabajador->primer_apellido ?? '',
                        'primer_nombre' => $user->trabajador->primer_nombre ?? '',
                        'salario' => $user->trabajador->salario ?? 0,
                        'segundo_apellido' => $user->trabajador->segundo_apellido ?? '',
                        'segundo_nombre' => $user->trabajador->segundo_nombre ?? '',
                        'sexo' => $user->trabajador->sexo ?? '',
                        'telefono' => $user->trabajador->telefono ?? '',
                        'tipo_documento' => $user->trabajador->tipo_documento ?? '',
                        'codigo_categoria' => $user->trabajador->codigo_categoria ?? '',
                    ] : null,
                ],
            ];

            // Redirigir a la URL solicitada o al dashboard
            return redirect()->intended(route('dashboard'));
        }

        // Si la autenticación falla, verificar si el usuario existe para redirigir a registro
        $user = \App\Models\User::where('username', $request->username)->first();

        if (!$user) {
            // Usuario no encontrado, redirigir a registro
            return redirect()->route('register')
                ->with('username', $request->username)
                ->with('redirect', $request->get('redirect', route('dashboard')));
        }

        // Usuario encontrado pero contraseña incorrecta
        throw ValidationException::withMessages([
            'username' => __('auth.failed'),
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Procesar el registro
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tipo_documento' => ['required', 'string', 'in:CC,CE,PASAPORTE,NIT'],
            'numero_documento' => ['required', 'string', 'unique:users,numero_documento'],
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['required', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $user = \App\Models\User::create([
                'tipo_documento' => $validated['tipo_documento'],
                'numero_documento' => $validated['numero_documento'],
                'nombres' => $validated['nombres'],
                'apellidos' => $validated['apellidos'],
                'email' => $validated['email'],
                'telefono' => $validated['telefono'],
                'username' => $validated['username'],
                'password' => bcrypt($validated['password']),
                'roles' => json_encode(['user_trabajador']), // Rol por defecto
                'permissions' => json_encode([]), // Sin permisos adicionales
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Autenticar al usuario después del registro
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            Log::error('Error en registro', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            throw ValidationException::withMessages([
                'email' => 'Error al crear la cuenta. Por favor, inténtalo de nuevo.',
            ]);
        }
    }

    /**
     * Mostrar la página de registro
     */
    public function showRegistrationForm()
    {
        return Inertia::render('auth/register', [
            'username' => session('username'),
            'redirect' => session('redirect'),
        ]);
    }

    /**
     * Obtener roles del usuario
     */
    private function getUserRoles($user): array
    {
        if (isset($user->roles)) {
            return is_array($user->roles) ? $user->roles : json_decode($user->roles, true) ?? [];
        }
        return [];
    }

    /**
     * Obtener permisos del usuario
     */
    private function getUserPermissions($user): array
    {
        if (isset($user->permissions)) {
            return is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true) ?? [];
        }
        return [];
    }
}
