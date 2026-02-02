<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\Postulacion;
use App\Services\FirmaPlusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FirmaDigitalController extends Controller
{
    protected FirmaPlusService $firmaService;

    public function __construct(FirmaPlusService $firmaService)
    {
        $this->firmaService = $firmaService;
    }

    /**
     * Inicia el proceso de firmado digital enviando el PDF a FirmaPlus.
     *
     * Args:
     * solicitud_id: ID de la solicitud
     *
     * Returns:
     * 200: Proceso de firmado iniciado exitosamente
     * 404: Solicitud o PDF no encontrado
     * 400: Error de validación
     */
    public function iniciarProcesoFirmado(string $solicitud_id): JsonResponse
    {
        try {
            // Validar solicitud_id
            if (!Str::isUuid($solicitud_id)) {
                return ErrorResource::errorResponse('ID de solicitud inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Obtener solicitud
            $solicitud = Postulacion::find($solicitud_id);

            if (!$solicitud) {
                return ErrorResource::notFound('Solicitud no encontrada')->response();
            }

            // Verificar que tenga PDF generado
            $pdfData = $solicitud->pdf_generado;
            if (!$pdfData || !isset($pdfData['path'])) {
                return ErrorResource::errorResponse('La solicitud no tiene un PDF generado. Genere el PDF primero.')
                    ->response()
                    ->setStatusCode(400);
            }

            $pdfPath = $pdfData['path'];

            // Verificar que existan firmantes
            $firmantes = $solicitud->firmantes ?? [];
            if (empty($firmantes)) {
                return ErrorResource::errorResponse('La solicitud no tiene firmantes definidos')
                    ->response()
                    ->setStatusCode(400);
            }

            Log::info('Iniciando proceso de firmado digital', [
                'solicitud_id' => $solicitud_id,
                'num_firmantes' => count($firmantes),
                'pdf_path' => $pdfPath
            ]);

            // Preparar metadatos
            $metadata = [
                'solicitud_id' => $solicitud_id,
                'tipo_documento' => 'SOLICITUD_CREDITO',
                'fecha_solicitud' => $solicitud->created_at->toISOString()
            ];

            // Enviar a FirmaPlus
            $resultado = $this->firmaService->enviarDocumentoParaFirma(
                documentoPath: $pdfPath,
                firmantes: $firmantes,
                metadata: $metadata
            );

            // Actualizar solicitud con datos del proceso de firmado
            $transaccionId = $resultado['transaccion_id'] ?? null;

            $procesoFirmado = [
                'transaccion_id' => $transaccionId,
                'estado' => 'PENDIENTE_FIRMADO',
                'fecha_inicio' => Carbon::now(),
                'proveedor' => 'FirmaPlus',
                'urls_firma' => $resultado['url_firmantes'] ?? [],
                'firmantes_completados' => 0,
                'firmantes_pendientes' => count($firmantes)
            ];

            $solicitud->update([
                'proceso_firmado' => $procesoFirmado,
                'estado' => 'PENDIENTE_FIRMADO'
            ]);

            // Agregar al timeline
            $timeline = $solicitud->timeline ?? [];
            $timeline[] = [
                'fecha' => Carbon::now(),
                'evento' => 'FIRMADO_INICIADO',
                'descripcion' => 'Documento enviado a FirmaPlus para firma digital',
                'datos' => [
                    'transaccion_id' => $transaccionId,
                    'num_firmantes' => count($firmantes)
                ]
            ];
            $solicitud->timeline = $timeline;
            $solicitud->save();

            // TODO: Crear registro en recepcion_firmas cuando se implemente el modelo

            Log::info('Proceso de firmado iniciado exitosamente', [
                'solicitud_id' => $solicitud_id,
                'transaccion_id' => $transaccionId
            ]);

            return ApiResource::success([
                'solicitud_id' => $solicitud_id,
                'transaccion_id' => $transaccionId,
                'estado' => 'PENDIENTE_FIRMADO',
                'urls_firma' => $resultado['url_firmantes'] ?? [],
                'firmantes' => count($firmantes),
                'mensaje' => 'Proceso de firmado iniciado exitosamente'
            ], 'Documento enviado para firma digital')->response();
        } catch (\Exception $e) {
            Log::error('Error al iniciar proceso de firmado', [
                'solicitud_id' => $solicitud_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error al iniciar el proceso de firmado', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Consulta el estado actual del proceso de firmado en FirmaPlus.
     *
     * Args:
     * solicitud_id: ID de la solicitud
     *
     * Returns:
     * 200: Estado del proceso de firmado
     * 404: Solicitud no encontrada o sin proceso de firmado
     */
    public function consultarEstadoFirmado(string $solicitud_id): JsonResponse
    {
        try {
            // Validar solicitud_id
            if (!Str::isUuid($solicitud_id)) {
                return ErrorResource::errorResponse('ID de solicitud inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            $solicitud = Postulacion::find($solicitud_id);

            if (!$solicitud) {
                return ErrorResource::notFound('Solicitud no encontrada')->response();
            }

            $procesoFirmado = $solicitud->proceso_firmado;

            if (!$procesoFirmado) {
                return ErrorResource::errorResponse('La solicitud no tiene un proceso de firmado iniciado')
                    ->response()
                    ->setStatusCode(404);
            }

            $transaccionId = $procesoFirmado['transaccion_id'] ?? null;

            // Consultar estado en FirmaPlus
            $estadoActual = $this->firmaService->consultarEstadoDocumento($transaccionId);

            // Actualizar estado local si cambió
            if ($estadoActual['estado'] !== $procesoFirmado['estado']) {
                $procesoFirmado['estado'] = $estadoActual['estado'];
                $procesoFirmado['firmantes_completados'] = $estadoActual['firmantes_completados'] ?? 0;
                $procesoFirmado['firmantes_pendientes'] = $estadoActual['firmantes_pendientes'] ?? 0;

                $solicitud->update([
                    'proceso_firmado' => $procesoFirmado
                ]);
            }

            return ApiResource::success([
                'solicitud_id' => $solicitud_id,
                'transaccion_id' => $transaccionId,
                'estado' => $estadoActual['estado'] ?? null,
                'firmantes_completados' => $estadoActual['firmantes_completados'] ?? 0,
                'firmantes_pendientes' => $estadoActual['firmantes_pendientes'] ?? 0,
                'fecha_consulta' => Carbon::now()->toISOString()
            ], 'Estado consultado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error consultando estado de firmado', [
                'solicitud_id' => $solicitud_id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al consultar el estado', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Webhook para recibir notificaciones de FirmaPlus cuando se completa el firmado.
     *
     * Este endpoint NO requiere autenticación JWT ya que es llamado por FirmaPlus.
     * La autenticación se valida mediante token en el body o headers.
     *
     * Body esperado:
     * {
     *     "transaccion_id": "string",
     *     "estado": "FIRMADO" | "RECHAZADO" | "EXPIRADO",
     *     "solicitud_id": "string",
     *     "firmantes_completados": number,
     *     "documento_firmado_url": "string" (opcional)
     * }
     *
     * Returns:
     * 200: Webhook procesado correctamente
     * 401: Token de autenticación inválido
     * 400: Datos inválidos
     */
    public function webhookFirmaCompletada(Request $request): JsonResponse
    {
        try {
            // Obtener datos del webhook
            $data = $request->json()->all();

            if (empty($data)) {
                return ErrorResource::errorResponse('No se proporcionaron datos')
                    ->response()
                    ->setStatusCode(400);
            }

            // Validar token de autenticación del webhook
            $webhookToken = $data['token'] ?? $request->header('X-Webhook-Token');

            // TODO: Validar token contra configuración
            // if (!$this->validarWebhookToken($webhookToken)) {
            //     return response()->json([
            //         'success' => false,
            //         'error' => 'Token de webhook inválido',
            //         'details' => []
            //     ], 401);
            // }

            $transaccionId = $data['transaccion_id'] ?? null;
            $solicitudId = $data['solicitud_id'] ?? null;
            $nuevoEstado = $data['estado'] ?? null;

            if (!$transaccionId || !$solicitudId || !$nuevoEstado) {
                return ErrorResource::errorResponse('Faltan campos requeridos: transaccion_id, solicitud_id, estado')
                    ->response()
                    ->setStatusCode(400);
            }

            Log::info('Webhook de FirmaPlus recibido', [
                'transaccion_id' => $transaccionId,
                'solicitud_id' => $solicitudId,
                'estado' => $nuevoEstado
            ]);

            // Validar solicitud_id
            if (!Str::isUuid($solicitudId)) {
                return ErrorResource::errorResponse('solicitud_id inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Buscar solicitud
            $solicitud = Postulacion::find($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound('Solicitud no encontrada')->response();
            }

            // Si el documento está firmado, descargar PDF
            $pdfFirmadoPath = null;
            if ($nuevoEstado === 'FIRMADO') {
                try {
                    // Preparar ruta para PDF firmado
                    $pdfOriginalPath = $solicitud->pdf_generado['path'] ?? '';
                    $directorio = dirname($pdfOriginalPath);
                    $pdfFirmadoPath = $directorio . '/solicitud_' . $solicitudId . '_firmado.pdf';

                    // Descargar de FirmaPlus
                    $this->firmaService->descargarDocumentoFirmado($transaccionId, $pdfFirmadoPath);

                    Log::info('PDF firmado descargado exitosamente', [
                        'solicitud_id' => $solicitudId,
                        'path' => $pdfFirmadoPath
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error descargando PDF firmado', [
                        'transaccion_id' => $transaccionId,
                        'error' => $e->getMessage()
                    ]);
                    // No fallar el webhook por este error
                }
            }

            // Actualizar solicitud
            $procesoFirmado = $solicitud->proceso_firmado ?? [];
            $procesoFirmado['estado'] = $nuevoEstado;
            $procesoFirmado['fecha_completado'] = Carbon::now();
            $procesoFirmado['firmantes_completados'] = $data['firmantes_completados'] ?? 0;

            $updateData = [
                'proceso_firmado' => $procesoFirmado,
                'estado' => $nuevoEstado
            ];

            if ($pdfFirmadoPath) {
                $updateData['pdf_firmado'] = [
                    'path' => $pdfFirmadoPath,
                    'filename' => basename($pdfFirmadoPath),
                    'fecha_firmado' => Carbon::now()
                ];
            }

            $solicitud->update($updateData);

            // Agregar al timeline
            $timeline = $solicitud->timeline ?? [];
            $timeline[] = [
                'fecha' => Carbon::now(),
                'evento' => 'FIRMADO_' . $nuevoEstado,
                'descripcion' => 'Documento ' . strtolower($nuevoEstado) . ' por FirmaPlus',
                'datos' => [
                    'transaccion_id' => $transaccionId,
                    'firmantes_completados' => $data['firmantes_completados'] ?? 0
                ]
            ];
            $solicitud->timeline = $timeline;
            $solicitud->save();

            // TODO: Actualizar recepcion_firmas cuando se implemente el modelo

            Log::info('Webhook procesado exitosamente', [
                'solicitud_id' => $solicitudId,
                'transaccion_id' => $transaccionId,
                'estado' => $nuevoEstado
            ]);

            return ApiResource::success([
                'procesado' => true,
                'solicitud_id' => $solicitudId,
                'estado' => $nuevoEstado
            ], 'Webhook procesado correctamente')->response();
        } catch (\Exception $e) {
            Log::error('Error procesando webhook de FirmaPlus', [
                'error' => $e->getMessage(),
                'data' => $request->json()->all()
            ]);

            return ErrorResource::serverError('Error procesando webhook', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Valida el token de webhook de FirmaPlus
     * TODO: Implementar validación contra configuración
     */
    private function validarWebhookToken(?string $token): bool
    {
        // Por ahora, siempre retorna true
        // En producción, validar contra configuración segura
        return true;
    }
}
