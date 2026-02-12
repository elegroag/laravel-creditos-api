<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\LineaInversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LineasInversionController extends Controller
{
    protected LineaInversionService $lineaService;

    public function __construct(LineaInversionService $lineaService)
    {
        $this->lineaService = $lineaService;
    }

    /**
     * Obtener todas las líneas de inversión disponibles.
     *
     * Response:
     * {
     *     "success": true,
     *     "message": "Líneas de inversión obtenidas exitosamente",
     *     "data": [
     *         {
     *             "id": 1,
     *             "linea_credito": "EDUCACION SUPERIOR (20 SMLMV)",
     *             "monto_maximo_pesos": 23200000,
     *             "plazo_maximo": "60 meses",
     *             "tasas_interes_anual": {
     *                 "categoria_a": "10%",
     *                 "categoria_b": "11%",
     *                 "categoria_c": "14%"
     *             },
     *             "requisitos": [...]
     *         }
     *     ]
     * }
     */
    #[OA\Get(
        path: '/lineas-inversion',
        tags: ['LineasInversion'],
        summary: 'Obtener todas las líneas de inversión',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Líneas de inversión obtenidas'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerTodas(): JsonResponse
    {
        try {
            Log::info('Obteniendo todas las líneas de inversión');

            // Inicializar datos si es necesario
            $this->lineaService->initializeData();

            // Obtener todas las líneas
            $lineas = $this->lineaService->getAllLineas();

            Log::info('Líneas de inversión obtenidas exitosamente', [
                'count' => count($lineas)
            ]);

            return ApiResource::success($lineas, 'Líneas de inversión obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener todas las líneas de inversión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener líneas de inversión', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener una línea de inversión específica por su ID.
     *
     * Args:
     *     * @param int $linea_id ID de la línea de inversión
     * Returns:
     *     * JsonResponse
     * Response:
     *     * {
     *         "success": true,
     *         "message": "Línea de inversión obtenida exitosamente",
     *         "data": {...}
     *     }
     */
    #[OA\Get(
        path: '/lineas-inversion/{linea_id}',
        tags: ['LineasInversion'],
        summary: 'Obtener línea de inversión por ID',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'linea_id',
                in: 'path',
                required: true,
                description: 'ID de la línea',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Línea obtenida'),
            new OA\Response(response: 404, description: 'Línea no encontrada'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function obtenerPorId(int $linea_id): JsonResponse
    {
        try {
            Log::info('Obteniendo línea de inversión por ID', ['linea_id' => $linea_id]);

            // Validar que el ID sea positivo
            if ($linea_id <= 0) {
                return ErrorResource::errorResponse('ID de línea de inversión inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Obtener la línea específica
            $linea = $this->lineaService->getLineaById($linea_id);

            if (!$linea) {
                Log::warning('Línea de inversión no encontrada', ['linea_id' => $linea_id]);

                return ErrorResource::notFound('Línea de inversión con ID ' . $linea_id . ' no encontrada')->response();
            }

            Log::info('Línea de inversión obtenida exitosamente', [
                'linea_id' => $linea_id,
                'linea_credito' => $linea['linea_credito'] ?? 'N/A'
            ]);

            return ApiResource::success($linea, 'Línea de inversión obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener línea de inversión por ID', [
                'linea_id' => $linea_id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al obtener línea de inversión', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener líneas de inversión por categoría.
     *
     * Args:
     *     * @param string $categoria Categoría de la línea (EDUCACION, VIVIENDA, etc.)
     * Returns:
     *     * JsonResponse
     * Response:
     *     * {
     *         "success": true,
     *         "message": "Líneas de inversión por categoría obtenidas",
     *         "data": [...]
     *     }
     */
    #[OA\Get(
        path: '/lineas-inversion/categoria/{categoria}',
        tags: ['LineasInversion'],
        summary: 'Obtener líneas por categoría',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'categoria',
                in: 'path',
                required: true,
                description: 'Categoría',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Líneas por categoría obtenidas'),
            new OA\Response(response: 404, description: 'Categoría no encontrada'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function obtenerPorCategoria(string $categoria): JsonResponse
    {
        try {
            Log::info('Obteniendo líneas de inversión por categoría', ['categoria' => $categoria]);

            // Validar categoría
            $categoriaUpper = strtoupper($categoria);

            if (!in_array($categoriaUpper, ['A', 'B', 'C'])) {
                return ErrorResource::errorResponse('Categoría inválida. Debe ser A, B o C')
                    ->response()
                    ->setStatusCode(400);
            }

            // Obtener líneas por categoría
            $lineas = $this->lineaService->getLineasByCategoria($categoriaUpper);

            Log::info('Líneas de inversión por categoría obtenidas', [
                'categoria' => $categoriaUpper,
                'count' => count($lineas)
            ]);

            return ApiResource::success($lineas, 'Líneas de inversión para categoría ' . $categoriaUpper . ' obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener líneas de inversión por categoría', [
                'categoria' => $categoria,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al obtener líneas por categoría', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Inicializar la colección de líneas de inversión con datos por defecto.
     * Este endpoint es principalmente para administración.
     *
     * Response:
     * {
     *     "success": true,
     *     "message": "Líneas de inversión inicializadas exitosamente"
     * }
     */
    public function inicializarLineas(): JsonResponse
    {
        try {
            Log::info('Inicializando líneas de inversión');

            // Crear índices si es necesario
            $this->lineaService->ensureIndex();

            // Inicializar datos
            $success = $this->lineaService->initializeData();

            if ($success) {
                Log::info('Líneas de inversión inicializadas exitosamente');

                return ApiResource::success(null, 'Líneas de inversión inicializadas exitosamente')->response();
            } else {
                Log::error('Error al inicializar las líneas de inversión');

                return ErrorResource::errorResponse('Error al inicializar las líneas de inversión')
                    ->response()
                    ->setStatusCode(500);
            }
        } catch (\Exception $e) {
            Log::error('Error al inicializar líneas de inversión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al inicializar líneas de inversión', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener estadísticas de líneas de inversión
     */
    public function obtenerEstadisticas(): JsonResponse
    {
        try {
            Log::info('Obteniendo estadísticas de líneas de inversión');

            // Obtener todas las líneas
            $this->lineaService->initializeData();
            $lineas = $this->lineaService->getAllLineas();

            // Calcular estadísticas
            $estadisticas = [
                'total_lineas' => count($lineas),
                'categorias' => [
                    'A' => count($this->lineaService->getLineasByCategoria('A')),
                    'B' => count($this->lineaService->getLineasByCategoria('B')),
                    'C' => count($this->lineaService->getLineasByCategoria('C'))
                ],
                'monto_maximo_total' => array_sum(array_column($lineas, 'monto_maximo_pesos')),
                'plazos_disponibles' => array_unique(array_column($lineas, 'plazo_maximo')),
                'fecha_consulta' => now()->toISOString()
            ];

            Log::info('Estadísticas de líneas de inversión calculadas', $estadisticas);

            return ApiResource::success($estadisticas, 'Estadísticas obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de líneas de inversión', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al obtener estadísticas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Buscar líneas de inversión por texto
     */
    public function buscarLineas(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'termino' => 'required|string|min:2|max:100',
                'categoria' => 'nullable|string|in:A,B,C'
            ], [
                'termino.required' => 'El término de búsqueda es requerido',
                'termino.min' => 'El término debe tener al menos 2 caracteres',
                'termino.max' => 'El término no puede exceder 100 caracteres',
                'categoria.in' => 'La categoría debe ser A, B o C'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $termino = $data['termino'];
            $categoria = $data['categoria'] ?? null;

            Log::info('Buscando líneas de inversión', [
                'termino' => $termino,
                'categoria' => $categoria
            ]);

            // Obtener todas las líneas
            $this->lineaService->initializeData();
            $lineas = $this->lineaService->getAllLineas();

            // Filtrar por término de búsqueda
            $resultados = [];
            foreach ($lineas as $linea) {
                $textoBusqueda = strtolower($linea['linea_credito'] . ' ' . ($linea['descripcion'] ?? ''));

                if (strpos($textoBusqueda, strtolower($termino)) !== false) {
                    // Si se especifica categoría, filtrar también por categoría
                    if ($categoria) {
                        $tasas = $linea['tasas_interes_anual'] ?? [];
                        if (isset($tasas['categoria_' . strtolower($categoria)])) {
                            $resultados[] = $linea;
                        }
                    } else {
                        $resultados[] = $linea;
                    }
                }
            }

            Log::info('Búsqueda de líneas completada', [
                'termino' => $termino,
                'categoria' => $categoria,
                'resultados_count' => count($resultados)
            ]);

            return ApiResource::success([
                'resultados' => $resultados,
                'total' => count($resultados),
                'termino' => $termino,
                'categoria' => $categoria
            ], 'Búsqueda completada')->response();
        } catch (\Exception $e) {
            Log::error('Error al buscar líneas de inversión', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ErrorResource::serverError('Error interno al buscar líneas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Comparar líneas de inversión
     */
    public function compararLineas(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'lineas_ids' => 'required|array|min:2|max:5',
                'lineas_ids.*' => 'required|integer|min:1'
            ], [
                'lineas_ids.required' => 'Debe especificar los IDs de las líneas a comparar',
                'lineas_ids.min' => 'Debe especificar al menos 2 líneas para comparar',
                'lineas_ids.max' => 'No puede comparar más de 5 líneas a la vez',
                'lineas_ids.*.integer' => 'Cada ID debe ser un número entero',
                'lineas_ids.*.min' => 'Cada ID debe ser un número positivo'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $lineasIds = $data['lineas_ids'];

            Log::info('Comparando líneas de inversión', ['lineas_ids' => $lineasIds]);

            // Obtener las líneas especificadas
            $lineasComparar = [];
            foreach ($lineasIds as $id) {
                $linea = $this->lineaService->getLineaById($id);
                if ($linea) {
                    $lineasComparar[] = $linea;
                }
            }

            if (count($lineasComparar) < 2) {
                return ErrorResource::errorResponse('No se encontraron suficientes líneas válidas para comparar')
                    ->response()
                    ->setStatusCode(400);
            }

            // Preparar datos de comparación
            $comparacion = [
                'lineas' => $lineasComparar,
                'comparacion' => [
                    'mayor_monto' => max(array_column($lineasComparar, 'monto_maximo_pesos')),
                    'menor_monto' => min(array_column($lineasComparar, 'monto_maximo_pesos')),
                    'mayor_plazo' => $this->obtenerMayorPlazo($lineasComparar),
                    'menor_plazo' => $this->obtenerMenorPlazo($lineasComparar),
                    'tasas_promedio' => $this->calcularTasasPromedio($lineasComparar)
                ],
                'fecha_comparacion' => now()->toISOString()
            ];

            Log::info('Comparación de líneas completada', [
                'lineas_comparadas' => count($lineasComparar)
            ]);

            return ApiResource::success($comparacion, 'Comparación completada')->response();
        } catch (\Exception $e) {
            Log::error('Error al comparar líneas de inversión', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ErrorResource::serverError('Error interno al comparar líneas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener el mayor plazo de un conjunto de líneas
     */
    private function obtenerMayorPlazo(array $lineas): string
    {
        $plazos = array_column($lineas, 'plazo_maximo');

        // Extraer números de plazos (ej: "60 meses" -> 60)
        $valores = [];
        foreach ($plazos as $plazo) {
            if (preg_match('/(\d+)\s*meses?/i', $plazo, $matches)) {
                $valores[] = (int) $matches[1];
            }
        }

        return !empty($valores) ? max($valores) . ' meses' : 'N/A';
    }

    /**
     * Obtener el menor plazo de un conjunto de líneas
     */
    private function obtenerMenorPlazo(array $lineas): string
    {
        $plazos = array_column($lineas, 'plazo_maximo');

        // Extraer números de plazos (ej: "60 meses" -> 60)
        $valores = [];
        foreach ($plazos as $plazo) {
            if (preg_match('/(\d+)\s*meses?/i', $plazo, $matches)) {
                $valores[] = (int) $matches[1];
            }
        }

        return !empty($valores) ? min($valores) . ' meses' : 'N/A';
    }

    /**
     * Calcular tasas promedio
     */
    private function calcularTasasPromedio(array $lineas): array
    {
        $tasasA = [];
        $tasasB = [];
        $tasasC = [];

        foreach ($lineas as $linea) {
            $tasas = $linea['tasas_interes_anual'] ?? [];
            if (isset($tasas['categoria_a'])) {
                $tasasA[] = floatval(str_replace('%', '', $tasas['categoria_a']));
            }
            if (isset($tasas['categoria_b'])) {
                $tasasB[] = floatval(str_replace('%', '', $tasas['categoria_b']));
            }
            if (isset($tasas['categoria_c'])) {
                $tasasC[] = floatval(str_replace('%', '', $tasas['categoria_c']));
            }
        }

        return [
            'categoria_a' => !empty($tasasA) ? round(array_sum($tasasA) / count($tasasA), 2) . '%' : 'N/A',
            'categoria_b' => !empty($tasasB) ? round(array_sum($tasasB) / count($tasasB), 2) . '%' : 'N/A',
            'categoria_c' => !empty($tasasC) ? round(array_sum($tasasC) / count($tasasC), 2) . '%' : 'N/A'
        ];
    }
}
