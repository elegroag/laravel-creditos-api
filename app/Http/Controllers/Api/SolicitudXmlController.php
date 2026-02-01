<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudCredito;
use App\Services\SolicitudXmlService;
use App\Services\NumeroSolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SolicitudXmlController extends Controller
{
    protected SolicitudXmlService $xmlService;
    protected NumeroSolicitudService $numeroSolicitudService;

    public function __construct(SolicitudXmlService $xmlService, NumeroSolicitudService $numeroSolicitudService)
    {
        $this->xmlService = $xmlService;
        $this->numeroSolicitudService = $numeroSolicitudService;
    }

    /**
     * Generar XML de solicitud de crédito
     * @deprecated - Usar el endpoint en FirmasController
     */
    public function generarXmlSolicitud(Request $request): Response|JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'save_xml' => 'sometimes|boolean',
                'solicitud' => 'sometimes|array',
                'solicitante' => 'sometimes|array'
            ], [
                'save_xml.boolean' => 'El campo save_xml debe ser booleano'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $saveXml = $data['save_xml'] ?? false;

            Log::info('Generando XML de solicitud de crédito', [
                'save_xml' => $saveXml
            ]);

            try {
                $xmlBytes = $this->xmlService->buildSolicitudCreditoXml($data);
            } catch (\Exception $e) {
                Log::error('Error al generar XML', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'No fue posible generar el XML',
                    'details' => [
                        'internal_error' => $e->getMessage()
                    ]
                ], 500);
            }

            $savedFilename = null;
            $savedSolicitudId = null;

            if ($saveXml) {
                try {
                    // Generar número de solicitud si se va a guardar
                    $solicitudPayload = $data['solicitud'] ?? [];
                    $numeroSolicitud = $this->generarNumeroSolicitudSiEsNecesario($solicitudPayload);

                    // Guardar XML en el sistema de archivos
                    $savedFilename = $this->guardarXmlEnSistemaArchivos($xmlBytes, $numeroSolicitud);

                    // Guardar en base de datos
                    $savedSolicitudId = $this->guardarSolicitudEnBaseDatos($data, $numeroSolicitud, $savedFilename);

                    Log::info('XML guardado exitosamente', [
                        'filename' => $savedFilename,
                        'solicitud_id' => $savedSolicitudId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al guardar XML', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'No fue posible guardar el XML',
                        'details' => [
                            'internal_error' => $e->getMessage()
                        ]
                    ], 500);
                }
            }

            // Retornar XML como respuesta
            $response = new Response($xmlBytes, 200, [
                'Content-Type' => 'application/xml'
            ]);

            if ($savedFilename) {
                $response->headers->set('X-Saved-Filename', $savedFilename);
            }

            if ($savedSolicitudId) {
                $response->headers->set('X-Solicitud-Id', $savedSolicitudId);
            }

            Log::info('XML de solicitud generado exitosamente', [
                'save_xml' => $saveXml,
                'saved_filename' => $savedFilename
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Error interno al generar XML de solicitud', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al generar XML',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Extraer datos de XML de solicitud de crédito
     * @deprecated - Usar el endpoint en FirmasController
     */
    public function extraerDatosXml(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string|max:255',
                'validate' => 'sometimes|boolean'
            ], [
                'filename.required' => 'El campo filename es requerido',
                'filename.string' => 'El filename debe ser texto',
                'validate.boolean' => 'El campo validate debe ser booleano'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $filename = $data['filename'];
            $validate = $data['validate'] ?? true;

            Log::info('Extrayendo datos de XML', [
                'filename' => $filename,
                'validate' => $validate
            ]);

            // Validar que el filename termine en .xml
            if (!Str::endsWith($filename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            // Construir ruta segura
            $xmlDir = storage_path('app/xml');
            $xmlPath = $xmlDir . '/' . $filename;

            // Validar que la ruta sea segura
            if (!Str::startsWith(realpath($xmlPath), realpath($xmlDir))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ruta de archivo no permitida',
                    'details' => []
                ], 400);
            }

            // Verificar que el archivo exista
            if (!file_exists($xmlPath)) {
                return response()->json([
                    'success' => false,
                    'error' => "No existe el archivo: {$filename}",
                    'details' => []
                ], 404);
            }

            try {
                $xmlBytes = file_get_contents($xmlPath);
                $data = $this->xmlService->extractSolicitudCreditoDataFromXml($xmlBytes, $validate);

                Log::info('Datos extraídos exitosamente del XML', [
                    'filename' => $filename
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            } catch (\ValueError $e) {
                Log::error('XML inválido', [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => "XML inválido: {$e->getMessage()}",
                    'details' => []
                ], 400);
            } catch (\Exception $e) {
                Log::error('Error al extraer datos del XML', [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'No fue posible extraer el XML',
                    'details' => [
                        'internal_error' => $e->getMessage()
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error interno al extraer datos XML', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al extraer XML',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Generar número de solicitud si es necesario
     */
    private function generarNumeroSolicitudSiEsNecesario(array &$solicitudPayload): string
    {
        $numeroSolicitud = $solicitudPayload['numero_solicitud'] ?? '';

        if (!is_string($numeroSolicitud) || empty(trim($numeroSolicitud))) {
            // Obtener línea de crédito para generar el número
            $lineaCredito = $solicitudPayload['tipcre'] ?? '03';

            if (is_string($lineaCredito) && !empty(trim($lineaCredito))) {
                $numeroSolicitud = $this->numeroSolicitudService->generarNumeroSolicitud(trim($lineaCredito));
                $solicitudPayload['numero_solicitud'] = $numeroSolicitud;
            } else {
                $numeroSolicitud = '';
            }
        } else {
            $numeroSolicitud = trim($numeroSolicitud);
        }

        return $numeroSolicitud;
    }

    /**
     * Guardar XML en el sistema de archivos
     */
    private function guardarXmlEnSistemaArchivos(string $xmlBytes, string $numeroSolicitud): string
    {
        $activosDir = storage_path('app/storage/activos');

        // Crear directorio si no existe
        if (!is_dir($activosDir)) {
            mkdir($activosDir, 0755, true);
        }

        // Generar nombre de archivo seguro
        $base = !empty($numeroSolicitud) ? $this->safeFilenameComponent($numeroSolicitud) : 'solicitud';
        $timestamp = Carbon::now()->format('Ymd-His');
        $candidate = "{$base}-{$timestamp}.xml";
        $filePath = $activosDir . '/' . $candidate;

        // Validar que la ruta sea segura
        if (!Str::startsWith(realpath(dirname($filePath)), realpath($activosDir))) {
            throw new \ValueError('Ruta de guardado no permitida');
        }

        // Si el archivo ya existe, agregar timestamp único
        if (file_exists($filePath)) {
            $candidate = "{$base}-{$timestamp}-" . time() . ".xml";
            $filePath = $activosDir . '/' . $candidate;
        }

        // Guardar archivo
        file_put_contents($filePath, $xmlBytes);

        Log::info('XML guardado en sistema de archivos', [
            'file_path' => $filePath,
            'filename' => $candidate
        ]);

        return $candidate;
    }

    /**
     * Guardar solicitud en base de datos
     */
    private function guardarSolicitudEnBaseDatos(array $data, string $numeroSolicitud, string $savedFilename): string
    {
        $user = Auth::user();

        if (!$user) {
            throw new \ValueError('Token inválido');
        }

        $username = $user->username;
        $now = Carbon::now();

        // Preparar datos del solicitante
        $solicitantePayload = $data['solicitante'] ?? [];
        $solicitudPayload = $data['solicitud'] ?? [];

        $montoSolicitado = $solicitudPayload['valor_solicitado'] ??
            $solicitudPayload['valor_solicitud'] ?? 0;

        $plazoMeses = $solicitudPayload['plazo_meses'] ?? 0;
        $estado = 'POSTULADO';

        // Buscar solicitud existente
        $query = SolicitudCredito::where('owner_username', $username);

        if (!empty($numeroSolicitud)) {
            $query->where('numero_solicitud', $numeroSolicitud);
        } else {
            $query->where('xml_filename', $savedFilename);
        }

        $existing = $query->first(['id', 'estado']);
        $estadoDoc = $existing?->estado ?? $estado;

        // Preparar datos para actualización/creación
        $updateData = [
            'owner_username' => $username,
            'numero_solicitud' => $numeroSolicitud,
            'monto_solicitado' => $montoSolicitado,
            'plazo_meses' => is_numeric($plazoMeses) ? (int)$plazoMeses : 0,
            'solicitante' => [
                'tipo_identificacion' => $solicitantePayload['tipo_identificacion'] ?? null,
                'numero_identificacion' => $solicitantePayload['numero_identificacion'] ?? null,
                'nombres_apellidos' => $solicitantePayload['nombres_apellidos'] ?? null,
                'email' => $solicitantePayload['email'] ?? null,
                'telefono_movil' => $solicitantePayload['telefono_movil'] ?? null,
            ],
            'xml_filename' => $savedFilename,
            'payload' => $data,
            'updated_at' => $now,
        ];

        if ($existing) {
            // Actualizar solicitud existente
            $existing->update($updateData);

            // Agregar al timeline
            $timeline = $existing->timeline ?? [];
            $timeline[] = [
                'estado' => $estadoDoc,
                'fecha' => $now->toISOString(),
                'detalle' => 'Actualización por guardado de XML'
            ];
            $existing->update(['timeline' => $timeline]);

            $savedSolicitudId = $existing->id;
        } else {
            // Crear nueva solicitud
            $updateData['created_at'] = $now;
            $updateData['documentos'] = [];
            $updateData['estado'] = $estado;
            $updateData['timeline'] = [
                [
                    'estado' => $estadoDoc,
                    'fecha' => $now->toISOString(),
                    'detalle' => 'Creación por guardado de XML'
                ]
            ];

            $solicitud = SolicitudCredito::create($updateData);
            $savedSolicitudId = $solicitud->id;
        }

        Log::info('Solicitud guardada en base de datos', [
            'solicitud_id' => $savedSolicitudId,
            'numero_solicitud' => $numeroSolicitud,
            'owner_username' => $username
        ]);

        return $savedSolicitudId;
    }

    /**
     * Generar componente de nombre de archivo seguro
     */
    private function safeFilenameComponent(string $input): string
    {
        // Reemplazar caracteres no seguros
        $safe = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $input);

        // Limitar longitud
        if (strlen($safe) > 50) {
            $safe = substr($safe, 0, 50);
        }

        // Eliminar guiones bajos y guiones múltiples
        $safe = preg_replace('/[_\-]+/', '_', $safe);

        // Eliminar guiones bajos y guiones al inicio y final
        $safe = trim($safe, '_-');

        return $safe ?: 'solicitud';
    }

    /**
     * Validar estructura de XML
     */
    public function validarXml(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'xml_content' => 'required|string',
                'strict' => 'sometimes|boolean'
            ], [
                'xml_content.required' => 'El contenido XML es requerido',
                'xml_content.string' => 'El contenido XML debe ser texto'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $xmlContent = $data['xml_content'];
            $strict = $data['strict'] ?? true;

            Log::info('Validando estructura XML', ['strict' => $strict]);

            try {
                $isValid = $this->xmlService->validarEstructuraXml($xmlContent);

                if ($isValid) {
                    // Intentar extraer datos para validación adicional
                    $extractedData = $this->xmlService->extractSolicitudCreditoDataFromXml($xmlContent, $strict);

                    Log::info('XML validado exitosamente', [
                        'data_extracted' => !empty($extractedData)
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'XML válido',
                        'data' => [
                            'valid' => true,
                            'extracted_data' => $extractedData
                        ]
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'XML inválido',
                        'data' => [
                            'valid' => false
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error en validación XML', [
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al validar XML',
                    'details' => [
                        'internal_error' => $e->getMessage()
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error interno al validar XML', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al validar XML',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener lista de archivos XML disponibles
     */
    public function listarArchivosXml(): JsonResponse
    {
        try {
            Log::info('Listando archivos XML disponibles');

            $xmlDir = storage_path('app/xml');

            if (!is_dir($xmlDir)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files' => [],
                        'total' => 0,
                        'directory' => $xmlDir
                    ],
                    'message' => 'Directorio de XML no existe'
                ]);
            }

            $files = [];
            $iterator = new \DirectoryIterator($xmlDir);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();

                if (Str::endsWith($filename, '.xml')) {
                    $files[] = [
                        'filename' => $filename,
                        'size' => $fileInfo->getSize(),
                        'modified' => Carbon::createFromTimestamp($fileInfo->getMTime())->toISOString(),
                        'path' => $xmlDir . '/' . $filename
                    ];
                }
            }

            // Ordenar por fecha de modificación descendente
            usort($files, function ($a, $b) {
                return strcmp($b['modified'], $a['modified']);
            });

            Log::info('Archivos XML listados', [
                'total' => count($files)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'total' => count($files),
                    'directory' => $xmlDir
                ],
                'message' => 'Archivos XML obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar archivos XML', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al listar archivos XML',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Eliminar archivo XML
     */
    public function eliminarArchivoXml(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string|max:255'
            ], [
                'filename.required' => 'El nombre del archivo es requerido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $filename = $data['filename'];

            Log::info('Eliminando archivo XML', ['filename' => $filename]);

            // Validar que el filename termine en .xml
            if (!Str::endsWith($filename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            // Construir ruta segura
            $xmlDir = storage_path('app/xml');
            $xmlPath = $xmlDir . '/' . $filename;

            // Validar que la ruta sea segura
            if (!Str::startsWith(realpath(dirname($xmlPath)), realpath($xmlDir))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ruta de archivo no permitida',
                    'details' => []
                ], 400);
            }

            // Verificar que el archivo exista
            if (!file_exists($xmlPath)) {
                return response()->json([
                    'success' => false,
                    'error' => "No existe el archivo: {$filename}",
                    'details' => []
                ], 404);
            }

            // Eliminar archivo
            if (!unlink($xmlPath)) {
                throw new \Exception('No se pudo eliminar el archivo');
            }

            Log::info('Archivo XML eliminado exitosamente', ['filename' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar archivo XML', [
                'filename' => $request->get('filename'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al eliminar archivo',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
