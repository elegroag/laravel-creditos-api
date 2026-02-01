<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SolicitudService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends Controller
{
    protected SolicitudService $solicitudService;
    protected UserService $userService;

    public function __construct(
        SolicitudService $solicitudService,
        UserService $userService
    ) {
        $this->solicitudService = $solicitudService;
        $this->userService = $userService;
    }

    public function index(): Response|RedirectResponse
    {
        try {
            // Obtener datos de solicitudes
            $user = Auth::user();

            if (!$user) {
                return redirect()->route('login');
            }

            // Obtener solicitudes del usuario usando el service
            $solicitudesResult = $this->solicitudService->getByOwner($user->username, 0, 1000);
            $solicitudes = collect($solicitudesResult['solicitudes'] ?? []);

            // Obtener estados disponibles usando el service
            $estados = $this->solicitudService->getEstadosDisponibles();

            // Obtener estadísticas básicas
            $estadisticas = [
                'total_solicitudes' => $solicitudes->count(),
                'pendientes' => $solicitudes->where('estado', 'POSTULADO')->count(),
                'aprobadas' => $solicitudes->where('estado', 'APROBADO')->count(),
                'rechazadas' => $solicitudes->where('estado', 'RECHAZADO')->count(),
            ];

            return Inertia::render('inicio/index', [
                'solicitudesData' => [
                    'success' => true,
                    'data' => $solicitudes->toArray()
                ],
                'estadosData' => [
                    'success' => true,
                    'data' => $estados
                ],
                'estadisticasData' => [
                    'success' => true,
                    'data' => $estadisticas
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InicioController@index', [
                'error' => $e->getMessage(),
                'user' => Auth::user()?->username
            ]);

            return Inertia::render('inicio/index', [
                'solicitudesData' => [
                    'success' => false,
                    'data' => []
                ],
                'estadosData' => [
                    'success' => false,
                    'data' => []
                ],
                'estadisticasData' => [
                    'success' => false,
                    'data' => [
                        'total_solicitudes' => 0,
                        'pendientes' => 0,
                        'aprobadas' => 0,
                        'rechazadas' => 0
                    ]
                ]
            ]);
        }
    }
}
