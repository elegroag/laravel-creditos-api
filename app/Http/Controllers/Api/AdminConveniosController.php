<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\EmpresaConvenio;
use App\Services\ConvenioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class AdminConveniosController extends Controller
{
    protected ConvenioService $convenioService;

    public function __construct(ConvenioService $convenioService)
    {
        $this->convenioService = $convenioService;
    }

    /**
     * Obtener lista de empresas con convenios con paginación y filtros
     *
     * Query params:
     * - page: número de página (default: 1)
     * - limit: límite de resultados por página (default: 20)
     * - estado: filtro por estado (Activo, Inactivo)
     * - busqueda: búsqueda por razón social, NIT o representante
     * - nit: filtro por NIT
     */
    #[OA\Get(
        path: '/admin/convenios',
        tags: ['AdminConvenios'],
        summary: 'Listar convenios administrativos',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Límite por página', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'estado', in: 'query', required: false, description: 'Estado del convenio', schema: new OA\Schema(type: 'string', enum: ['Activo', 'Inactivo'])),
            new OA\Parameter(name: 'busqueda', in: 'query', required: false, description: 'Búsqueda por razón social', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'nit', in: 'query', required: false, description: 'Filtro por NIT', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de convenios'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 422, description: 'Parámetros inválidos')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            // Validar parámetros de paginación
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100',
                'estado' => 'string|in:Activo,Inactivo',
                'nit' => 'string',
                'busqueda' => 'string|max:255'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Parámetros inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $page = (int) $request->get('page', 1);
            $limit = (int) $request->get('limit', 20);
            $estado = $request->get('estado');
            $nit = $request->get('nit');
            $busqueda = $request->get('busqueda');

            Log::info('Obteniendo empresas con convenios', [
                'page' => $page,
                'limit' => $limit,
                'estado' => $estado,
                'nit' => $nit,
                'busqueda' => $busqueda
            ]);

            // Construir query
            $query = EmpresaConvenio::query();

            // Aplicar filtros
            if ($estado) {
                $query->where('estado', $estado);
            }

            if ($nit) {
                $query->where('nit', $nit);
            }

            if ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('razon_social', 'LIKE', "%{$busqueda}%")
                        ->orWhere('nit', 'LIKE', "%{$busqueda}%")
                        ->orWhere('representante_nombre', 'LIKE', "%{$busqueda}%")
                        ->orWhere('representante_documento', 'LIKE', "%{$busqueda}%")
                        ->orWhere('correo', 'LIKE', "%{$busqueda}%");
                });
            }

            // Obtener total
            $total = $query->count();

            // Obtener empresas con paginación
            $empresas = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            // Formatear datos
            $empresasFormateadas = $empresas->map(function ($empresa) {
                return [
                    'id' => $empresa->id,
                    'nit' => $empresa->nit,
                    'razon_social' => $empresa->razon_social,
                    'fecha_convenio' => $empresa->fecha_convenio,
                    'fecha_vencimiento' => $empresa->fecha_vencimiento,
                    'estado' => $empresa->estado,
                    'representante_documento' => $empresa->representante_documento,
                    'representante_nombre' => $empresa->representante_nombre,
                    'telefono' => $empresa->telefono,
                    'correo' => $empresa->correo,
                    'created_at' => $empresa->created_at,
                    'updated_at' => $empresa->updated_at,
                ];
            });

            // Generar respuesta de paginación
            $totalPages = (int) ceil($total / $limit);

            // Obtener conteos por estado
            $conteoEstados = EmpresaConvenio::selectRaw('estado, COUNT(*) as count')
                ->groupBy('estado')
                ->pluck('count', 'estado')
                ->toArray();

            $data = [
                'empresas' => $empresasFormateadas,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'conteo_estados' => $conteoEstados
            ];

            return ApiResource::success($data, 'Empresas con convenios obtenidas exitosamente')->response();
        } catch (ValidationException $e) {
            return ErrorResource::validationError($e->errors(), 'Parámetros inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error al obtener empresas con convenios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener empresas con convenios', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener detalles de una empresa con convenio específica
     */
    #[OA\Get(
        path: '/admin/convenios/{id}',
        tags: ['AdminConvenios'],
        summary: 'Obtener convenio por ID',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del convenio',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Convenio encontrado'),
            new OA\Response(response: 404, description: 'Convenio no encontrado'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function show(string $id): JsonResponse
    {
        try {
            $empresa = EmpresaConvenio::find($id);

            if (!$empresa) {
                return ErrorResource::notFound('Empresa con convenio no encontrada')->response();
            }

            $empresaFormateada = [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'razon_social' => $empresa->razon_social,
                'fecha_convenio' => $empresa->fecha_convenio,
                'fecha_vencimiento' => $empresa->fecha_vencimiento,
                'estado' => $empresa->estado,
                'representante_documento' => $empresa->representante_documento,
                'representante_nombre' => $empresa->representante_nombre,
                'telefono' => $empresa->telefono,
                'correo' => $empresa->correo,
                'created_at' => $empresa->created_at,
                'updated_at' => $empresa->updated_at,
            ];

            return ApiResource::success($empresaFormateada, 'Empresa con convenio obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener empresa con convenio', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al obtener empresa con convenio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Crear una nueva empresa con convenio
     */
    #[OA\Post(
        path: '/admin/convenios',
        tags: ['AdminConvenios'],
        summary: 'Crear convenio',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nit', 'razon_social', 'representante_nombre'],
                properties: [
                    new OA\Property(property: 'nit', type: 'string', example: '900123456'),
                    new OA\Property(property: 'razon_social', type: 'string', example: 'Empresa SA'),
                    new OA\Property(property: 'representante_nombre', type: 'string', example: 'Juan Pérez')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Convenio creado'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nit' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
                'representante_documento' => 'required|string|max:20',
                'representante_nombre' => 'required|string|max:255',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
                'fecha_vencimiento' => 'nullable|date',
                'estado' => 'nullable|string|in:Activo,Inactivo'
            ], [
                'nit.required' => 'El NIT es requerido',
                'razon_social.required' => 'La razón social es requerida',
                'representante_documento.required' => 'El documento del representante es requerido',
                'representante_nombre.required' => 'El nombre del representante es requerido',
                'correo.email' => 'El correo debe ser un email válido'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();

            // Verificar si la empresa ya existe por NIT
            if (EmpresaConvenio::where('nit', $data['nit'])->exists()) {
                return ErrorResource::errorResponse('El NIT de la empresa ya está registrado')
                    ->response()
                    ->setStatusCode(400);
            }

            // Crear empresa
            $empresa = EmpresaConvenio::create([
                'nit' => $data['nit'],
                'razon_social' => $data['razon_social'],
                'fecha_convenio' => Carbon::now(),
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'estado' => $data['estado'] ?? 'Activo',
                'representante_documento' => $data['representante_documento'],
                'representante_nombre' => $data['representante_nombre'],
                'telefono' => $data['telefono'] ?? '',
                'correo' => $data['correo'] ?? '',
            ]);

            Log::info('Empresa con convenio creada', [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'razon_social' => $empresa->razon_social
            ]);

            return ApiResource::success(['id' => $empresa->id], 'Empresa con convenio creada exitosamente')->response()
                ->setStatusCode(201);
        } catch (ValidationException $e) {
            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error al crear empresa con convenio', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ErrorResource::serverError('Error interno al crear empresa con convenio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Actualizar una empresa con convenio existente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $empresa = EmpresaConvenio::find($id);

            if (!$empresa) {
                return ErrorResource::notFound('Empresa con convenio no encontrada')->response();
            }

            $validator = Validator::make($request->all(), [
                'nit' => 'sometimes|required|string|max:20',
                'razon_social' => 'sometimes|required|string|max:255',
                'representante_documento' => 'sometimes|required|string|max:20',
                'representante_nombre' => 'sometimes|required|string|max:255',
                'telefono' => 'sometimes|nullable|string|max:20',
                'correo' => 'sometimes|nullable|email|max:255',
                'fecha_vencimiento' => 'sometimes|nullable|date',
                'estado' => 'sometimes|nullable|string|in:Activo,Inactivo'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();

            // Si se actualiza el NIT, verificar que no exista
            if (isset($data['nit']) && $data['nit'] !== $empresa->nit) {
                if (EmpresaConvenio::where('nit', $data['nit'])->where('id', '!=', $id)->exists()) {
                    return ErrorResource::errorResponse('El NIT de la empresa ya está registrado')
                        ->response()
                        ->setStatusCode(400);
                }
            }

            // Actualizar empresa
            $empresa->update($data);

            Log::info('Empresa con convenio actualizada', [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'updated_fields' => array_keys($data)
            ]);

            return ApiResource::success(null, 'Empresa con convenio actualizada exitosamente')->response();
        } catch (ValidationException $e) {
            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar empresa con convenio', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ErrorResource::serverError('Error interno al actualizar empresa con convenio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Eliminar una empresa con convenio
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $empresa = EmpresaConvenio::find($id);

            if (!$empresa) {
                return ErrorResource::notFound('Empresa con convenio no encontrada')->response();
            }

            $empresa->delete();

            Log::info('Empresa con convenio eliminada', [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'razon_social' => $empresa->razon_social
            ]);

            return ApiResource::success(null, 'Empresa con convenio eliminada exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al eliminar empresa con convenio', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al eliminar empresa con convenio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Cambiar el estado de una empresa con convenio (Activo/Inactivo)
     */
    public function toggleEstado(Request $request, string $id): JsonResponse
    {
        try {
            $empresa = EmpresaConvenio::find($id);

            if (!$empresa) {
                return ErrorResource::notFound('Empresa con convenio no encontrada')->response();
            }

            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:Activo,Inactivo'
            ], [
                'estado.required' => 'Se debe proporcionar el nuevo estado',
                'estado.in' => 'Estado inválido. Debe ser: Activo o Inactivo'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $nuevoEstado = $data['estado'];

            $empresa->update(['estado' => $nuevoEstado]);

            Log::info('Estado de empresa con convenio cambiado', [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'nuevo_estado' => $nuevoEstado
            ]);

            return ApiResource::success([
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'razon_social' => $empresa->razon_social,
                'nuevo_estado' => $nuevoEstado
            ], "Estado de la empresa con convenio cambiado a {$nuevoEstado} exitosamente")->response();
        } catch (ValidationException $e) {
            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de empresa con convenio', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ErrorResource::serverError('Error interno al cambiar estado de la empresa con convenio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Exportar lista de empresas con convenios a CSV
     */
    public function export(): JsonResponse
    {
        try {
            $empresas = EmpresaConvenio::orderBy('created_at', 'desc')->get();

            $csvData = [];
            $csvData[] = [
                'ID',
                'NIT',
                'Razón Social',
                'Fecha Convenio',
                'Fecha Vencimiento',
                'Estado',
                'Representante Documento',
                'Representante Nombre',
                'Teléfono',
                'Correo',
                'Fecha Creación',
                'Fecha Actualización'
            ];

            foreach ($empresas as $empresa) {
                $csvData[] = [
                    $empresa->id,
                    $empresa->nit,
                    $empresa->razon_social,
                    $empresa->fecha_convenio,
                    $empresa->fecha_vencimiento,
                    $empresa->estado,
                    $empresa->representante_documento,
                    $empresa->representante_nombre,
                    $empresa->telefono,
                    $empresa->correo,
                    $empresa->created_at,
                    $empresa->updated_at
                ];
            }

            Log::info('Empresas con convenios exportadas', [
                'count' => $empresas->count()
            ]);

            return ApiResource::success([
                'csv_data' => $csvData,
                'filename' => 'empresas_convenios_export.csv'
            ], 'Datos exportados exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al exportar empresas con convenios', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al exportar empresas con convenios', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Importar empresas con convenios desde Excel
     * Formato esperado: NIT, Razón Social, Representante Documento, Representante Nombre, Teléfono, Correo, Fecha Vencimiento, Estado
     */
    public function import(Request $request): JsonResponse
    {
        try {
            // Validar que se envíe un archivo
            if (!$request->hasFile('file')) {
                return ErrorResource::errorResponse('No se proporcionó ningún archivo')
                    ->response()
                    ->setStatusCode(400);
            }

            $file = $request->file('file');

            if (!$file->isValid()) {
                return ErrorResource::errorResponse('Archivo inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Validar extensión del archivo
            $allowedExtensions = ['xlsx', 'xls', 'csv'];
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, $allowedExtensions)) {
                return ErrorResource::errorResponse('El archivo debe ser de formato Excel (.xlsx, .xls) o CSV')
                    ->response()
                    ->setStatusCode(400);
            }

            Log::info('Iniciando importación de empresas desde Excel', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);

            // Por ahora, retornamos una respuesta indicando que la funcionalidad está en desarrollo
            // ya que la importación de Excel requiere librerías adicionales

            return ApiResource::success([
                'total_filas' => 0,
                'procesadas' => 0,
                'creadas' => 0,
                'actualizadas' => 0,
                'errores' => [],
                'message' => 'Funcionalidad de importación en desarrollo. Por favor, use la API REST para crear/actualizar empresas.'
            ], 'Importación completada: 0 filas procesadas, 0 creadas, 0 actualizadas, 0 errores')->response();
        } catch (\Exception $e) {
            Log::error('Error al importar empresas desde Excel', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName()
            ]);

            return ErrorResource::serverError('Error interno al importar empresas desde Excel', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
