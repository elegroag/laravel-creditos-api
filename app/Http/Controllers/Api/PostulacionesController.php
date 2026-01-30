<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use App\Services\PostulacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PostulacionesController extends Controller
{
    protected PostulacionService $postulacionService;

    public function __construct(PostulacionService $postulacionService)
    {
        $this->postulacionService = $postulacionService;
    }

    /**
     * Crear una nueva postulación
     */
    public function crearPostulacion(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'solicitante' => 'sometimes|array',
                'solicitante.nombre' => 'sometimes|required|string|max:100',
                'solicitante.apellido' => 'sometimes|required|string|max:100',
                'solicitante.tipo_documento' => 'sometimes|required|string|max:20',
                'solicitante.numero_documento' => 'sometimes|required|string|max:20',
                'solicitante.email' => 'sometimes|required|email|max:255',
                'solicitante.telefono' => 'sometimes|string|max:20',
                'monto_solicitado' => 'required|numeric|min:0',
                'plazo_meses' => 'required|integer|min:1|max:360'
            ], [
                'monto_solicitado.required' => 'El monto solicitado es requerido',
                'monto_solicitado.numeric' => 'El monto solicitado debe ser un número',
                'monto_solicitado.min' => 'El monto solicitado debe ser mayor o igual a 0',
                'plazo_meses.required' => 'El plazo en meses es requerido',
                'plazo_meses.integer' => 'El plazo debe ser un número entero',
                'plazo_meses.min' => 'El plazo debe ser al menos 1 mes',
                'plazo_meses.max' => 'El plazo no puede exceder 360 meses',
                'solicitante.nombre.required' => 'El nombre del solicitante es requerido',
                'solicitante.apellido.required' => 'El apellido del solicitante es requerido',
                'solicitante.tipo_documento.required' => 'El tipo de documento es requerido',
                'solicitante.numero_documento.required' => 'El número de documento es requerido',
                'solicitante.email.required' => 'El email del solicitante es requerido',
                'solicitante.email.email' => 'El email debe ser válido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            
            Log::info('Creando nueva postulación', [
                'monto_solicitado' => $data['monto_solicitado'],
                'plazo_meses' => $data['plazo_meses']
            ]);

            // Preparar datos de la postulación
            $solicitante = $data['solicitante'] ?? [];
            $monto = $data['monto_solicitado'];
            $plazo = $data['plazo_meses'];
            $estado = 'POSTULADO';
            $now = Carbon::now();

            $postulacionData = [
                'solicitante' => $solicitante,
                'monto_solicitado' => $monto,
                'plazo_meses' => $plazo,
                'estado' => $estado,
                'timeline' => [
                    [
                        'estado' => $estado,
                        'fecha' => $now->toISOString(),
                        'detalle' => 'Creación de postulación'
                    ]
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Crear postulación
            $postulacion = Postulacion::create($postulacionData);

            // Enviar evento WebSocket (simulado)
            $this->enviarEventoPostulacionCreada($postulacion);

            Log::info('Postulación creada exitosamente', [
                'postulacion_id' => $postulacion->id,
                'estado' => $estado
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'postulacion' => $this->formatearPostulacion($postulacion)
                ],
                'message' => 'Postulación creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear postulación', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al crear postulación',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Listar postulaciones con filtros
     */
    public function listarPostulaciones(Request $request): JsonResponse
    {
        try {
            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'estado' => 'sometimes|string|max:50',
                'limit' => 'sometimes|integer|min:1|max:200',
                'page' => 'sometimes|integer|min:1'
            ], [
                'limit.integer' => 'El límite debe ser un número entero',
                'limit.min' => 'El límite debe ser al menos 1',
                'limit.max' => 'El límite no puede exceder 200',
                'page.integer' => 'La página debe ser un número entero',
                'page.min' => 'La página debe ser al menos 1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $params = $validator->validated();
            $estado = $params['estado'] ?? null;
            $limit = $params['limit'] ?? 200;
            $page = $params['page'] ?? 1;

            Log::info('Listando postulaciones', [
                'estado' => $estado,
                'limit' => $limit,
                'page' => $page
            ]);

            // Construir consulta
            $query = Postulacion::query();

            if ($estado) {
                $query->where('estado', $estado);
            }

            // Obtener postulaciones
            $postulaciones = $query->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

            // Formatear resultados
            $resultados = [];
            foreach ($postulaciones as $postulacion) {
                $resultados[] = $this->formatearPostulacion($postulacion);
            }

            Log::info('Postulaciones listadas exitosamente', [
                'total' => count($resultados),
                'estado' => $estado
            ]);

            return response()->json([
                'success' => true,
                'data' => $resultados,
                'message' => 'Postulaciones obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar postulaciones', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al listar postulaciones',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener una postulación específica
     */
    public function obtenerPostulacion(string $postulacionId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($postulacionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ID de postulación inválido',
                    'details' => []
                ], 400);
            }

            Log::info('Obteniendo postulación', ['postulacion_id' => $postulacionId]);

            // Buscar postulación
            $postulacion = Postulacion::find($postulacionId);

            if (!$postulacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Postulación no encontrada',
                    'details' => []
                ], 404);
            }

            Log::info('Postulación obtenida exitosamente', [
                'postulacion_id' => $postulacionId,
                'estado' => $postulacion->estado
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'postulacion' => $this->formatearPostulacion($postulacion)
                ],
                'message' => 'Postulación obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener postulación', [
                'postulacion_id' => $postulacionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener postulación',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una postulación
     */
    public function actualizarEstado(Request $request, string $postulacionId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($postulacionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ID de postulación inválido',
                    'details' => []
                ], 400);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|max:50',
                'detalle' => 'sometimes|string|max:255'
            ], [
                'estado.required' => 'El estado es requerido',
                'estado.string' => 'El estado debe ser texto',
                'detalle.string' => 'El detalle debe ser texto',
                'detalle.max' => 'El detalle no puede exceder 255 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $nuevoEstado = $data['estado'];
            $detalle = $data['detalle'] ?? 'Cambio de estado';

            Log::info('Actualizando estado de postulación', [
                'postulacion_id' => $postulacionId,
                'nuevo_estado' => $nuevoEstado,
                'detalle' => $detalle
            ]);

            // Buscar postulación
            $postulacion = Postulacion::find($postulacionId);

            if (!$postulacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Postulación no encontrada',
                    'details' => []
                ], 404);
            }

            // Actualizar estado y timeline
            $now = Carbon::now();
            $timeline = $postulacion->timeline ?? [];
            $timeline[] = [
                'estado' => $nuevoEstado,
                'fecha' => $now->toISOString(),
                'detalle' => $detalle
            ];

            $postulacion->update([
                'estado' => $nuevoEstado,
                'timeline' => $timeline,
                'updated_at' => $now
            ]);

            // Enviar evento WebSocket (simulado)
            $this->enviarEventoEstadoActualizado($postulacion);

            Log::info('Estado de postulación actualizado exitosamente', [
                'postulacion_id' => $postulacionId,
                'nuevo_estado' => $nuevoEstado
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'postulacion' => $this->formatearPostulacion($postulacion)
                ],
                'message' => 'Estado actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de postulación', [
                'postulacion_id' => $postulacionId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al actualizar estado',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Eliminar una postulación
     */
    public function eliminarPostulacion(string $postulacionId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($postulacionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ID de postulación inválido',
                    'details' => []
                ], 400);
            }

            Log::info('Eliminando postulación', ['postulacion_id' => $postulacionId]);

            // Buscar postulación
            $postulacion = Postulacion::find($postulacionId);

            if (!$postulacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Postulación no encontrada',
                    'details' => []
                ], 404);
            }

            // Eliminar postulación
            $postulacion->delete();

            Log::info('Postulación eliminada exitosamente', [
                'postulacion_id' => $postulacionId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Postulación eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar postulación', [
                'postulacion_id' => $postulacionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al eliminar postulación',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de postulaciones
     */
    public function obtenerEstadisticas(): JsonResponse
    {
        try {
            Log::info('Obteniendo estadísticas de postulaciones');

            // Obtener conteos por estado
            $conteoEstados = Postulacion::selectRaw('estado, COUNT(*) as count')
                                   ->groupBy('estado')
                                   ->pluck('count', 'estado')
                                   ->toArray();

            // Obtener postulaciones recientes
            $postulacionesRecientes = Postulacion::orderBy('created_at', 'desc')
                                              ->limit(10)
                                              ->get();

            // Calcular montos totales
            $montoTotal = Postulacion::sum('monto_solicitado') ?? 0;
            $montoPromedio = Postulacion::avg('monto_solicitado') ?? 0;
            $plazoPromedio = Postulacion::avg('plazo_meses') ?? 0;

            // Formatear postulaciones recientes
            $recientes = [];
            foreach ($postulacionesRecientes as $postulacion) {
                $recientes[] = [
                    'id' => $postulacion->id,
                    'estado' => $postulacion->estado,
                    'monto_solicitado' => $postulacion->monto_solicitado,
                    'plazo_meses' => $postulacion->plazo_meses,
                    'created_at' => $postulacion->created_at->toISOString(),
                    'solicitante_nombre' => ($postulacion->solicitante['nombre'] ?? '') . ' ' . ($postulacion->solicitante['apellido'] ?? '')
                ];
            }

            $estadisticas = [
                'total_postulaciones' => Postulacion::count(),
                'conteo_estados' => $conteoEstados,
                'monto_total' => $montoTotal,
                'monto_promedio' => round($montoPromedio, 2),
                'plazo_promedio' => round($plazoPromedio, 2),
                'postulaciones_recientes' => $recientes,
                'fecha_consulta' => Carbon::now()->toISOString()
            ];

            Log::info('Estadísticas de postulaciones obtenidas', [
                'total_postulaciones' => $estadisticas['total_postulaciones']
            ]);

            return response()->json([
                'success' => true,
                'data' => $estadisticas,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de postulaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener estadísticas',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Buscar postulaciones
     */
    public function buscarPostulaciones(Request $request): JsonResponse
    {
        try {
            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'termino' => 'required|string|min:2|max:100',
                'estado' => 'sometimes|string|max:50',
                'limit' => 'sometimes|integer|min:1|max:100'
            ], [
                'termino.required' => 'El término de búsqueda es requerido',
                'termino.min' => 'El término debe tener al menos 2 caracteres',
                'termino.max' => 'El término no puede exceder 100 caracteres',
                'limit.integer' => 'El límite debe ser un número entero',
                'limit.min' => 'El límite debe ser al menos 1',
                'limit.max' => 'El límite no puede exceder 100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $termino = $data['termino'];
            $estado = $data['estado'] ?? null;
            $limit = $data['limit'] ?? 50;

            Log::info('Buscando postulaciones', [
                'termino' => $termino,
                'estado' => $estado,
                'limit' => $limit
            ]);

            // Construir búsqueda
            $query = Postulacion::where(function ($q) use ($termino) {
                $q->where('solicitante->nombre', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->apellido', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->email', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->numero_documento', 'LIKE', '%' . $termino . '%');
            });

            if ($estado) {
                $query->where('estado', $estado);
            }

            $postulaciones = $query->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

            // Formatear resultados
            $resultados = [];
            foreach ($postulaciones as $postulacion) {
                $resultados[] = $this->formatearPostulacion($postulacion);
            }

            Log::info('Búsqueda de postulaciones completada', [
                'termino' => $termino,
                'resultados' => count($resultados)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'postulaciones' => $resultados,
                    'total' => count($resultados),
                    'termino' => $termino,
                    'estado' => $estado
                ],
                'message' => 'Búsqueda completada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al buscar postulaciones', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al buscar postulaciones',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Formatear postulación para respuesta
     */
    private function formatearPostulacion(Postulacion $postulacion): array
    {
        return [
            'id' => $postulacion->id,
            'solicitante' => $postulacion->solicitante ?? [],
            'monto_solicitado' => $postulacion->monto_solicitado,
            'plazo_meses' => $postulacion->plazo_meses,
            'estado' => $postulacion->estado,
            'timeline' => $postulacion->timeline ?? [],
            'created_at' => $postulacion->created_at?->toISOString(),
            'updated_at' => $postulacion->updated_at?->toISOString()
        ];
    }

    /**
     * Enviar evento de postulación creada (simulado WebSocket)
     */
    private function enviarEventoPostulacionCreada(Postulacion $postulacion): void
    {
        try {
            // En una implementación real, esto enviaría un evento WebSocket
            // Por ahora, solo lo registramos en el log
            Log::info('Evento WebSocket: postulacion_created', [
                'postulacion_id' => $postulacion->id,
                'estado' => $postulacion->estado
            ]);

            // Aquí iría el código real de WebSocket:
            // socketio.emit("postulacion_created", ["postulacion" => $this->formatearPostulacion($postulacion)]);

        } catch (\Exception $e) {
            Log::error('Error al enviar evento WebSocket de postulación creada', [
                'postulacion_id' => $postulacion->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enviar evento de estado actualizado (simulado WebSocket)
     */
    private function enviarEventoEstadoActualizado(Postulacion $postulacion): void
    {
        try {
            // En una implementación real, esto enviaría un evento WebSocket a una sala específica
            // Por ahora, solo lo registramos en el log
            Log::info('Evento WebSocket: postulacion_estado', [
                'postulacion_id' => $postulacion->id,
                'estado' => $postulacion->estado,
                'room' => "postulacion:{$postulacion->id}"
            ]);

            // Aquí iría el código real de WebSocket:
            // $room = "postulacion:{$postulacion->id}";
            // socketio.emit("postulacion_estado", ["postulacion" => $this->formatearPostulacion($postulacion)], to=room);

        } catch (\Exception $e) {
            Log::error('Error al enviar evento WebSocket de estado actualizado', [
                'postulacion_id' => $postulacion->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Unirse a sala de postulación (simulado WebSocket)
     */
    public function unirseSalaPostulacion(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'postulacion_id' => 'required|string|max:255'
            ], [
                'postulacion_id.required' => 'El ID de postulación es requerido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $postulacionId = $data['postulacion_id'];

            Log::info('Usuario uniéndose a sala de postulación', [
                'postulacion_id' => $postulacionId,
                'room' => "postulacion:{$postulacionId}"
            ]);

            // En una implementación real, esto uniría al usuario a una sala WebSocket
            // join_room("postulacion:{$postulacionId}");

            return response()->json([
                'success' => true,
                'message' => 'Unido a la sala de postulación',
                'data' => [
                    'room' => "postulacion:{$postulacionId}"
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al unirse a sala de postulación', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al unirse a la sala',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
