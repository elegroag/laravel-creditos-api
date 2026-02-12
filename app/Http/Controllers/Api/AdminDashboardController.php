<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SolicitudCredito;
use App\Models\User;
use App\Models\EmpresaConvenio;
use App\Models\EstadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use OpenApi\Attributes as OA;

/**
 * Controlador para obtener estadísticas del dashboard administrativo
 */
class AdminDashboardController extends Controller
{
    /**
     * Obtener estadísticas completas del dashboard
     */
    #[OA\Get(
        path: '/admin/dashboard/estadisticas',
        tags: ['AdminDashboard'],
        summary: 'Obtener estadísticas del dashboard',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas obtenidas'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 403, description: 'Acceso denegado')
        ]
    )]
    public function obtenerEstadisticasGenerales(Request $request): JsonResponse
    {
        try {
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            $userRoles = $userData['roles'] ?? [];
            // el rol administrator y adviser tienen permiso para ver todas las solicitudes
            $isAdmin = in_array('administrator', $userRoles);
            $isAdviser = in_array('adviser', $userRoles);

            $hasAccess = $isAdmin || $isAdviser;

            if (!$hasAccess && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para ver esta solicitud')->response();
            }

            Log::info('Obteniendo estadísticas generales del dashboard');

            // Estadísticas de solicitudes
            $statsSolicitudes = $this->obtenerEstadisticasSolicitudes();

            // Estadísticas de convenios
            $statsConvenios = $this->obtenerEstadisticasConvenios();

            // Estadísticas de usuarios
            $statsUsuarios = $this->obtenerEstadisticasUsuarios();

            // Actividad reciente
            $actividadReciente = $this->obtenerActividadReciente();

            $data = [
                'solicitudes' => $statsSolicitudes,
                'convenios' => $statsConvenios,
                'usuarios' => $statsUsuarios,
                'actividadReciente' => $actividadReciente,
                'ultimaActualizacion' => now()->toISOString()
            ];

            Log::info('Estadísticas generales obtenidas exitosamente', [
                'total_solicitudes' => $statsSolicitudes['total'],
                'convenios_activos' => $statsConvenios['activos'],
                'trabajadores' => $statsUsuarios['trabajadores']
            ]);

            return ApiResource::success($data, 'Estadísticas del dashboard obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas generales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener estadísticas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener estadísticas de solicitudes de crédito
     */
    private function obtenerEstadisticasSolicitudes(): array
    {
        try {
            // Estadísticas básicas
            $total = SolicitudCredito::count();
            $aprobadas = SolicitudCredito::where('estado', 'APROBADA')->count();
            $activas = SolicitudCredito::whereNotIn('estado', ['FINALIZADA', 'DESISTE', 'RECHAZADA'])->count();
            $pendientesFirma = SolicitudCredito::whereIn('estado', ['ENVIADO_FIRMA', 'FIRMA_PENDIENTE'])->count();

            // Monto total aprobado
            $montoAprobado = SolicitudCredito::where('estado', 'APROBADA')
                ->sum('valor_solicitud');

            // Tasa de aprobación
            $tasaAprobacion = $total > 0 ? round(($aprobadas / $total) * 100, 1) : 0;

            // Distribución por estado
            $distribucionPorEstado = DB::table('solicitudes_credito')
                ->select('estado', DB::raw('COUNT(*) as count'))
                ->groupBy('estado')
                ->orderBy('count', 'desc')
                ->get();

            $solicitudesPorEstado = [];
            $coloresPorEstado = [
                'POSTULADO' => '#F59E0B',
                'ENVIADO_VALIDACION' => '#3B82F6',
                'EN_VALIDACION' => '#8B5CF6',
                'APROBADA' => '#10B981',
                'ENVIADO_FIRMA' => '#F97316',
                'FIRMA_PENDIENTE' => '#F97316',
                'FIRMADA' => '#06B6D4',
                'DESEMBOLSADA' => '#84CC16',
                'FINALIZADA' => '#6B7280',
                'RECHAZADA' => '#EF4444',
                'DESISTE' => '#EF4444'
            ];

            foreach ($distribucionPorEstado as $item) {
                $solicitudesPorEstado[] = [
                    'estado' => $item->estado,
                    'count' => $item->count,
                    'color' => $coloresPorEstado[$item->estado] ?? '#6B7280'
                ];
            }

            // Solicitudes por mes (últimos 6 meses)
            $solicitudesPorMes = [];
            for ($i = 5; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $conteo = SolicitudCredito::whereMonth('created_at', $fecha->month)
                    ->whereYear('created_at', $fecha->year)
                    ->count();

                $solicitudesPorMes[] = [
                    'mes' => $fecha->format('Y-m'),
                    'nombre' => $fecha->format('M Y'),
                    'conteo' => $conteo
                ];
            }

            return [
                'total' => $total,
                'aprobadas' => $aprobadas,
                'activas' => $activas,
                'pendientesFirma' => $pendientesFirma,
                'tasaAprobacion' => $tasaAprobacion,
                'montoTotalAprobado' => (float) $montoAprobado,
                'porEstado' => $solicitudesPorEstado,
                'porMes' => $solicitudesPorMes
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de solicitudes', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de convenios
     */
    private function obtenerEstadisticasConvenios(): array
    {
        try {
            // Convenios activos
            $activos = EmpresaConvenio::where('estado', 'Activo')->count();

            // Total de empresas con convenio
            $totalEmpresas = EmpresaConvenio::distinct('nit')->count();

            // Top 5 empresas con más trabajadores
            $topEmpresas = EmpresaConvenio::where('estado', 'Activo')
                ->orderBy('numero_empleados', 'desc')
                ->limit(5)
                ->get([
                    'razon_social',
                    'tipo_empresa',
                    'numero_empleados'
                ]);

            $topEmpresasFormateadas = [];
            foreach ($topEmpresas as $empresa) {
                $topEmpresasFormateadas[] = [
                    'nombre' => $empresa->razon_social,
                    'convenio' => $empresa->tipo_empresa,
                    'trabajadores' => $empresa->numero_empleados ?? 0
                ];
            }

            // Distribución por tipo de convenio
            $distribucionPorTipo = EmpresaConvenio::where('estado', 'Activo')
                ->select('tipo_empresa', DB::raw('COUNT(*) as count'))
                ->groupBy('tipo_empresa')
                ->orderBy('count', 'desc')
                ->get();

            $porTipo = [];
            foreach ($distribucionPorTipo as $item) {
                $porTipo[] = [
                    'tipo' => $item->tipo_empresa,
                    'count' => $item->count
                ];
            }

            return [
                'activos' => $activos,
                'totalEmpresas' => $totalEmpresas,
                'topEmpresas' => $topEmpresasFormateadas,
                'porTipo' => $porTipo
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de convenios', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de usuarios
     */
    private function obtenerEstadisticasUsuarios(): array
    {
        try {
            // Total de usuarios
            $total = User::count();

            // Usuarios activos
            $activos = User::where('disabled', false)->count();

            // Usuarios por rol
            $usuariosPorRol = [];
            $conteoRoles = $this->obtenerConteoRoles();

            foreach ($conteoRoles as $rol => $cantidad) {
                $usuariosPorRol[] = [
                    'rol' => $rol,
                    'count' => $cantidad
                ];
            }

            // Trabajadores específicamente
            $trabajadores = $conteoRoles['user_trabajador'] ?? 0;

            // Usuarios nuevos en últimos 30 días
            $recientes = User::where('created_at', '>=', now()->subDays(30))->count();

            return [
                'total' => $total,
                'activos' => $activos,
                'trabajadores' => $trabajadores,
                'porRol' => $usuariosPorRol,
                'recientes' => $recientes
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de usuarios', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener actividad reciente
     */
    private function obtenerActividadReciente(): array
    {
        try {
            $actividad = [];

            // Últimas 5 solicitudes
            $solicitudesRecientes = SolicitudCredito::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['numero_solicitud', 'estado', 'created_at', 'owner_username']);

            foreach ($solicitudesRecientes as $solicitud) {
                $actividad[] = [
                    'id' => $solicitud->numero_solicitud,
                    'tipo' => 'Solicitud',
                    'descripcion' => "Solicitud {$solicitud->numero_solicitud} - {$solicitud->estado}",
                    'usuario' => $solicitud->owner_username,
                    'fecha' => $solicitud->created_at->toISOString()
                ];
            }

            // Últimos 5 usuarios creados
            $usuariosRecientes = User::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['username', 'roles', 'created_at']);

            foreach ($usuariosRecientes as $usuario) {
                $rolPrincipal = is_array($usuario->roles) ? $usuario->roles[0] : 'user_trabajador';
                $actividad[] = [
                    'id' => $usuario->username,
                    'tipo' => 'Usuario',
                    'descripcion' => "Usuario {$usuario->username} - {$rolPrincipal}",
                    'usuario' => $usuario->username,
                    'fecha' => $usuario->created_at->toISOString()
                ];
            }

            // Ordenar por fecha (más reciente primero)
            usort($actividad, function ($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            // Limitar a 10 actividades más recientes
            return array_slice($actividad, 0, 10);
        } catch (\Exception $e) {
            Log::error('Error al obtener actividad reciente', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener conteo de usuarios por rol
     */
    private function obtenerConteoRoles(): array
    {
        $conteo = [];

        $usuarios = User::all();
        foreach ($usuarios as $usuario) {
            $roles = $usuario->roles ?? [];
            foreach ($roles as $rol) {
                if (!isset($conteo[$rol])) {
                    $conteo[$rol] = 0;
                }
                $conteo[$rol]++;
            }
        }

        return $conteo;
    }

    /**
     * Obtiene los datos del usuario autenticado desde JWT middleware
     */
    private function getAuthenticatedUser(Request $request): array
    {
        $authenticatedUser = $request->get('authenticated_user');
        return $authenticatedUser['user'] ?? [];
    }
}
