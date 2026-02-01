<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthenticationService;
use App\Services\TrabajadorService;
use App\Services\UserService;

class AuthController extends Controller
{

    protected UserService $userService;
    protected TrabajadorService $trabajadorService;
    protected AuthenticationService $authService;

    public function __construct(
        UserService $userService,
        TrabajadorService $trabajadorService,
        AuthenticationService $authService
    ) {
        $this->userService = $userService;
        $this->trabajadorService = $trabajadorService;
        $this->authService = $authService;
    }

    /**
     * Mostrar la página de login
     */
    public function showLoginForm()
    {
        // Si el usuario ya está autenticado, redirigir al inicio
        if (Auth::check()) {
            return redirect()->intended(route('inicio'));
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    /**
     * Mostrar la página de login para asesores
     */
    public function showAdviserLoginForm()
    {
        // Si el usuario ya está autenticado, redirigir al inicio
        if (Auth::check()) {
            return Inertia::location(route('inicio'));
        }

        return Inertia::render('auth/adviser');
    }

    /**
     * Mostrar pantalla de verificación de identidad (código de 6 dígitos)
     */
    public function showVerify(Request $request): Response
    {
        return Inertia::render('auth/verify', [
            'coddoc' => $request->query('coddoc'),
            'documento' => $request->query('documento'),
        ]);
    }

    /**
     * Procesar verificación de identidad (código de 6 dígitos)
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'codigo' => ['required', 'digits:6'],
            'coddoc' => ['nullable', 'string'],
            'documento' => ['nullable', 'string'],
        ]);

        // TODO: Conectar aquí la validación real del código contra el servicio/API.

        return redirect()
            ->route('login')
            ->with('status', 'Verificación enviada. Si el código es correcto, ya puedes iniciar sesión.');
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
                'access_token' => '',
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

            // Redirigir a la URL solicitada o al inicio
            return redirect()->intended(route('inicio'));
        }

        // Si la autenticación falla, verificar si el usuario existe para redirigir a registro
        $user = User::where('username', $request->username)->first();

        if (!$user) {
            // Usuario no encontrado, redirigir a registro
            return redirect()->route('register')
                ->with('username', $request->username)
                ->with('redirect', $request->get('redirect', route('inicio')));
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
            $user = User::create([
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

            return redirect()->intended(route('inicio'));
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
        $tipo_documentos = tipo_documentos_array();
        unset($tipo_documentos['3']);
        return Inertia::render('auth/register', [
            'username' => session('username'),
            'redirect' => session('redirect'),
            'tipo_documentos' => $tipo_documentos,
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

    /**
     * Crear sesión web para asesores tras seleccionar punto.
     */
    public function createAdviserSession(Request $request)
    {
        try {
            $validated = $request->validate([
                'access_token' => ['required', 'string'],
                'selected_punto' => ['required', 'array'],
                'selected_punto.numero' => ['nullable'],
                'selected_punto.oficina_afiliacion' => ['nullable', 'string'],
                'selected_punto.code_oficina' => ['nullable', 'string'],
                'selected_punto.nombre_usuario' => ['nullable', 'string'],
                'selected_punto.email' => ['nullable', 'string'],
                'selected_punto.estado' => ['nullable', 'string'],
            ]);

            $token = $validated['access_token'];

            $verified = $this->authService->verifyToken($token);
            $userId = (int) ($verified['user']['id'] ?? 0);

            if ($userId <= 0) {
                throw new \Exception('Token inválido');
            }

            $user = $this->userService->getById($userId);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            Auth::login($user);
            $request->session()->regenerate();

            $rawPunto = $validated['selected_punto'];
            $selectedPunto = [
                'id' => (int) ($rawPunto['numero'] ?? 0),
                'nombre' => (string) (($rawPunto['oficina_afiliacion'] ?? '') ?: ($rawPunto['nombre_usuario'] ?? '')),
                'direccion' => '',
                'telefono' => '',
                'ciudad' => '',
                'estado' => (string) ($rawPunto['estado'] ?? ''),
            ];

            $request->session()->put('selected_punto', $selectedPunto);

            return redirect()->route('inicio');
        } catch (\Exception $e) {
            Log::error('Error creando sesión web de asesor: ' . $e->getMessage());

            throw ValidationException::withMessages([
                'access_token' => 'No fue posible crear la sesión del asesor',
            ]);
        }
    }
}
