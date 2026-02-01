<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\SolicitudRepository;
use App\Repositories\EstadoSolicitudRepository;
use App\Services\SolicitudService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends Controller
{
    protected SolicitudRepository $solicitudRepository;
    protected EstadoSolicitudRepository $estadoRepository;
    protected SolicitudService $solicitudService;

    public function __construct(
        SolicitudRepository $solicitudRepository,
        EstadoSolicitudRepository $estadoRepository
    ) {
        $this->solicitudRepository = $solicitudRepository;
        $this->estadoRepository = $estadoRepository;
        $this->solicitudService = new SolicitudService($solicitudRepository, $estadoRepository);
    }

    public function index(): Response
    {
        try {
            // Obtener datos de solicitudes
            $user = auth()->user();
            $solicitudes = $this->solicitudRepository
                ->where('owner_username', $user->username)
                ->orderBy('created_at', 'desc')
                ->get();

            // Obtener estados de solicitud
            $estados = $this->estadoRepository->all();

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
                    'data' => $estados->toArray()
                ],
                'estadisticasData' => [
                    'success' => true,
                    'data' => $estadisticas
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener datos para el inicio', [
                'error' => $e->getMessage()
            ]);

            return Inertia::render('inicio/index', [
                'solicitudesData' => [
                    'success' => false,
                    'message' => 'Error al cargar las solicitudes',
                    'data' => []
                ],
                'estadosData' => [
                    'success' => false,
                    'message' => 'Error al cargar los estados',
                    'data' => []
                ],
                'estadisticasData' => [
                    'success' => false,
                    'message' => 'Error al cargar las estadísticas',
                    'data' => null
                ]
            ]);
        }
    }
}
