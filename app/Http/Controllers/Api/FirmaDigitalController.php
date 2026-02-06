<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\Postulacion;
use App\Services\FirmaPlusService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FirmaDigitalController extends Controller
{
    protected FirmaPlusService $firmaService;
    protected NotificationService $notificationService;

    public function __construct(FirmaPlusService $firmaService, NotificationService $notificationService)
    {
        $this->firmaService = $firmaService;
        $this->notificationService = $notificationService;
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
     * Este endpoint requiere validación de firma HMAC (manejada por middleware).
     *
     * Body esperado:
     * {
     *     "transaccion_id": "string",
     *     "estado": "FIRMADO" | "RECHAZADO" | "EXPIRADO" | "CANCELADO",
     *     "solicitud_id": "string",
     *     "firmantes_completados": number,
     *     "firmantes": [
     *         {
     *             "nombre": "string",
     *             "email": "string",
     *             "firmado": boolean,
     *             "fecha_firma": "ISO8601"
     *         }
     *     ],
     *     "documento_firmado_url": "string" (opcional)
     * }
     *
     * Headers:
     * X-Signature: HMAC-SHA256 signature (validado por middleware)
     *
     * Returns:
     * 200: Webhook procesado correctamente
     * 401: Firma inválida (validado por middleware)
     * 400: Datos inválidos
     * 404: Solicitud no encontrada
     */
    public function webhookFirmaCompletada(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Obtener datos del webhook
            $data = $request->json()->all();

            Log::info('Webhook FirmaPlus recibido', [
                'data_keys' => array_keys($data),
                'ip' => $request->ip()
            ]);

            // Validar estructura del payload
            $erroresValidacion = $this->firmaService->validarPayloadWebhook($data);

            if (!empty($erroresValidacion)) {
                Log::error('Payload de webhook inválido', [
                    'errores' => $erroresValidacion,
                    'data' => $data
                ]);

                return ErrorResource::errorResponse(
                    'Datos de webhook inválidos: ' . implode(', ', $erroresValidacion)
                )->response()->setStatusCode(400);
            }

            $transaccionId = $data['transaccion_id'];
            $solicitudId = $data['solicitud_id'];
            $nuevoEstado = $data['estado'];
            $firmantesData = $data['firmantes'] ?? [];

            // Validar UUID de solicitud
            if (!Str::isUuid($solicitudId)) {
                return ErrorResource::errorResponse('solicitud_id no es un UUID válido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Buscar solicitud
            $solicitud = Postulacion::find($solicitudId);

            if (!$solicitud) {
                Log::error('Solicitud no encontrada en webhook', [
                    'solicitud_id' => $solicitudId,
                    'transaccion_id' => $transaccionId
                ]);

                return ErrorResource::notFound('Solicitud no encontrada')->response();
            }

            // Verificar que el transaccion_id coincida
            $procesoFirmado = $solicitud->proceso_firmado ?? [];
            $transaccionEsperada = $procesoFirmado['transaccion_id'] ?? null;

            if ($transaccionEsperada && $transaccionEsperada !== $transaccionId) {
                Log::warning('Transacción ID no coincide', [
                    'esperada' => $transaccionEsperada,
                    'recibida' => $transaccionId,
                    'solicitud_id' => $solicitudId
                ]);
            }

            Log::info('Procesando webhook de FirmaPlus', [
                'solicitud_id' => $solicitudId,
                'transaccion_id' => $transaccionId,
                'estado_nuevo' => $nuevoEstado,
                'estado_anterior' => $solicitud->estado
            ]);

            // Si el documento está firmado, descargar PDF
            $pdfFirmadoPath = null;
            $pdfDescargado = false;

            if ($nuevoEstado === 'FIRMADO') {
                try {
                    // Preparar ruta para PDF firmado
                    $pdfOriginalPath = $solicitud->pdf_generado['path'] ?? '';

                    if (empty($pdfOriginalPath)) {
                        Log::error('No se encontró ruta de PDF original', [
                            'solicitud_id' => $solicitudId
                        ]);
                    } else {
                        $directorio = dirname($pdfOriginalPath);

                        // Asegurar que el directorio existe
                        if (!is_dir($directorio)) {
                            mkdir($directorio, 0755, true);
                        }

                        $pdfFirmadoPath = $directorio . '/solicitud_' . $solicitudId . '_firmado_' . time() . '.pdf';

                        // Descargar con reintentos
                        $pdfDescargado = $this->firmaService->descargarDocumentoFirmadoConReintentos(
                            $transaccionId,
                            $pdfFirmadoPath,
                            3
                        );

                        if ($pdfDescargado) {
                            // Verificar integridad
                            if (!$this->firmaService->verificarIntegridadPdf($pdfFirmadoPath)) {
                                Log::error('PDF firmado no pasó verificación de integridad', [
                                    'solicitud_id' => $solicitudId,
                                    'ruta' => $pdfFirmadoPath
                                ]);

                                // Eliminar archivo corrupto
                                if (file_exists($pdfFirmadoPath)) {
                                    unlink($pdfFirmadoPath);
                                }

                                $pdfDescargado = false;
                                $pdfFirmadoPath = null;
                            } else {
                                Log::info('PDF firmado descargado y verificado exitosamente', [
                                    'solicitud_id' => $solicitudId,
                                    'ruta' => $pdfFirmadoPath,
                                    'size' => filesize($pdfFirmadoPath)
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error descargando PDF firmado', [
                        'solicitud_id' => $solicitudId,
                        'transaccion_id' => $transaccionId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // No fallar el webhook por este error, continuar
                    $pdfDescargado = false;
                }
            }

            // Actualizar proceso de firmado
            $procesoFirmado['estado'] = $nuevoEstado;
            $procesoFirmado['fecha_completado'] = Carbon::now()->toISOString();
            $procesoFirmado['firmantes_completados'] = $data['firmantes_completados'] ?? count($firmantesData);
            $procesoFirmado['firmantes_data'] = $firmantesData;
            $procesoFirmado['webhook_recibido_at'] = Carbon::now()->toISOString();

            // Preparar datos para actualizar
            $updateData = [
                'proceso_firmado' => $procesoFirmado,
                'estado' => $nuevoEstado
            ];

            // Si se descargó el PDF exitosamente
            if ($pdfFirmadoPath && $pdfDescargado) {
                $updateData['pdf_firmado'] = [
                    'path' => $pdfFirmadoPath,
                    'filename' => basename($pdfFirmadoPath),
                    'fecha_firmado' => Carbon::now()->toISOString(),
                    'size' => filesize($pdfFirmadoPath),
                    'transaccion_id' => $transaccionId
                ];
            }

            $solicitud->update($updateData);

            // Agregar al timeline
            $timeline = $solicitud->timeline ?? [];
            $timeline[] = [
                'fecha' => Carbon::now()->toISOString(),
                'evento' => 'WEBHOOK_' . $nuevoEstado,
                'descripcion' => $this->getDescripcionEvento($nuevoEstado, $pdfDescargado),
                'usuario' => 'SYSTEM_FIRMAPLUS',
                'datos' => [
                    'transaccion_id' => $transaccionId,
                    'firmantes_completados' => $procesoFirmado['firmantes_completados'],
                    'pdf_descargado' => $pdfDescargado,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]
            ];
            $solicitud->timeline = $timeline;
            $solicitud->save();

            // Enviar notificación según el estado
            try {
                switch ($nuevoEstado) {
                    case 'FIRMADO':
                        $this->notificationService->notifyFirmaCompletada($solicitud, [
                            'pdf_descargado' => $pdfDescargado,
                            'firmantes_completados' => $procesoFirmado['firmantes_completados']
                        ]);
                        break;
                    case 'RECHAZADO':
                        $this->notificationService->notifyFirmaRechazada($solicitud);
                        break;
                    case 'EXPIRADO':
                        $this->notificationService->notifyFirmaExpirada($solicitud);
                        break;
                }
            } catch (\Exception $e) {
                Log::warning('Error al enviar notificación (no crítico)', [
                    'solicitud_id' => $solicitudId,
                    'estado' => $nuevoEstado,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('Webhook procesado exitosamente', [
                'solicitud_id' => $solicitudId,
                'transaccion_id' => $transaccionId,
                'estado' => $nuevoEstado,
                'pdf_descargado' => $pdfDescargado,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            // Respuesta exitosa
            return ApiResource::success([
                'procesado' => true,
                'solicitud_id' => $solicitudId,
                'transaccion_id' => $transaccionId,
                'estado' => $nuevoEstado,
                'pdf_descargado' => $pdfDescargado,
                'timestamp' => Carbon::now()->toISOString()
            ], 'Webhook procesado correctamente')->response();
        } catch (\Exception $e) {
            Log::error('Error crítico procesando webhook de FirmaPlus', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->json()->all(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            return ErrorResource::serverError('Error procesando webhook', [
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString()
            ])->response();
        }
    }

    /**
     * Obtener descripción del evento para timeline
     */
    private function getDescripcionEvento(string $estado, bool $pdfDescargado): string
    {
        $descripciones = [
            'FIRMADO' => $pdfDescargado
                ? 'Documento firmado exitosamente y PDF descargado'
                : 'Documento firmado (PDF no pudo descargarse)',
            'RECHAZADO' => 'Documento rechazado por uno o más firmantes',
            'EXPIRADO' => 'Proceso de firma expiró sin completarse',
            'CANCELADO' => 'Proceso de firma cancelado'
        ];

        return $descripciones[$estado] ?? "Estado actualizado a: {$estado}";
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
