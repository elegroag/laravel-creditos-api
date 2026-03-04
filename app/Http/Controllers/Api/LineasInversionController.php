<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\ExternalApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LineasInversionController extends Controller
{
    protected ExternalApiService $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Buscar líneas de inversión por texto
     */
    #[OA\Get(
        path: '/lineas-inversion/buscar',
        tags: ['LineasInversion'],
        summary: 'Buscar líneas de inversión',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, description: 'Texto de búsqueda', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categoria', in: 'query', required: false, description: 'Filtrar por categoría', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resultados de búsqueda'),
            new OA\Response(response: 422, description: 'Parámetros inválidos'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function buscarLineas(Request $request): JsonResponse
    {
        try {
            // Realizar la consulta a la API externa
            $response = $this->externalApiService->post('/creditos/tipo-inversiones');

            // Verificar si la respuesta fue exitosa
            $isSuccess = ($response['success'] ?? true) && !isset($response['error']);

            if (!$isSuccess) {
                return ErrorResource::errorResponse('Error al consultar las lineas de inversión', [
                    'external_error' => $response['error'] ?? 'Error en el servicio externo',
                    'status_code' => $response['status_code'] ?? 500
                ])->response()->setStatusCode(500);
            }

            // Verificar si la respuesta contiene error (para respuestas directas de API externa)
            if (isset($response['error']) && $response['error']) {
                return ErrorResource::errorResponse('Error al consultar las lineas de inversión', [
                    'external_error' => $response['error'] ?? 'Error desconocido',
                    'external_detail' => $response['detail'] ?? null
                ])->response()->setStatusCode(400);
            }

            return ApiResource::success(
                $response['data'] ?? [],
                'Datos generales obtenidos exitosamente'
            )->response();
        } catch (\Exception $e) {
            return ErrorResource::serverError('Error interno al buscar líneas de inversión', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
