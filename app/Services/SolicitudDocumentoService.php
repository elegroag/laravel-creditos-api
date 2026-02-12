<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Models\SolicitudDocumento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SolicitudDocumentoService
{
    /**
     * Obtener documentos requeridos según el tipo de crédito
     */
    public function obtenerDocumentosPorTipoCredito(string $detalleModalidad): array
    {
        // Documentos base para todos los créditos
        $documentosBase = [
            [
                'id' => 'cedula_frente',
                'nombre' => 'Cédula de Ciudadanía - Frente',
                'descripcion' => 'Copia legible de la cédula por el frente',
                'tipo' => 'identificacion',
                'obligatorio' => true
            ],
            [
                'id' => 'cedula_reverso',
                'nombre' => 'Cédula de Ciudadanía - Reverso',
                'descripcion' => 'Copia legible de la cédula por el reverso',
                'tipo' => 'identificacion',
                'obligatorio' => true
            ],
            [
                'id' => 'recibo_servicios',
                'nombre' => 'Recibo de Servicios Públicos',
                'descripcion' => 'Recibo de servicios públicos no mayor a 3 meses',
                'tipo' => 'domicilio',
                'obligatorio' => true
            ]
        ];

        // Documentos según tipo de crédito
        $documentosPorTipo = [];

        // Documentos adicionales según modalidad
        switch (strtoupper($detalleModalidad)) {
            case 'EDUCACION SUPERIOR':
                $documentosPorTipo = [
                    [
                        'id' => 'certificado_estudios',
                        'nombre' => 'Certificado de Estudios',
                        'descripcion' => 'Certificado de estudios actual',
                        'tipo' => 'academico',
                        'obligatorio' => true
                    ],
                    [
                        'id' => 'matricula_profesional',
                        'nombre' => 'Matrícula Profesional',
                        'descripcion' => 'Matrícula profesional si aplica',
                        'tipo' => 'profesional',
                        'obligatorio' => false
                    ]
                ];
                break;

            case 'VIVIENDA':
                $documentosPorTipo = [
                    [
                        'id' => 'escritura_inmueble',
                        'nombre' => 'Escritura del Inmueble',
                        'descripcion' => 'Escritura pública del inmueble',
                        'tipo' => 'inmueble',
                        'obligatorio' => true
                    ],
                    [
                        'id' => 'certificado_tradicion',
                        'nombre' => 'Certificado de Tradición y Libertad',
                        'descripcion' => 'Certificado de tradición y libertad actualizado',
                        'tipo' => 'inmueble',
                        'obligatorio' => true
                    ]
                ];
                break;

            case 'VEHICULO':
                $documentosPorTipo = [
                    [
                        'id' => 'soat',
                        'nombre' => 'SOAT',
                        'descripcion' => 'SOAT vigente del vehículo',
                        'tipo' => 'vehiculo',
                        'obligatorio' => true
                    ],
                    [
                        'id' => 'tecnicomecanica',
                        'nombre' => 'Tecnicomecánica',
                        'descripcion' => 'Tecnicomecánica vigente',
                        'tipo' => 'vehiculo',
                        'obligatorio' => true
                    ]
                ];
                break;

            case 'MICROCREDITO':
                $documentosPorTipo = [
                    [
                        'id' => 'negocio_registro',
                        'nombre' => 'Registro del Negocio',
                        'descripcion' => 'Registro mercantil o cédula de ciudadanía del negocio',
                        'tipo' => 'negocio',
                        'obligatorio' => true
                    ],
                    [
                        'id' => 'estados_financieros',
                        'nombre' => 'Estados Financieros',
                        'descripcion' => 'Estados financieros de los últimos 6 meses',
                        'tipo' => 'financiero',
                        'obligatorio' => false
                    ]
                ];
                break;

            case 'LIBRE INVERSION':
                $documentosPorTipo = [
                    [
                        'id' => 'declaracion_renta',
                        'nombre' => 'Declaración de Renta',
                        'descripcion' => 'Declaración de renta del último año',
                        'tipo' => 'financiero',
                        'obligatorio' => false
                    ],
                    [
                        'id' => 'certificado_laboral',
                        'nombre' => 'Certificado Laboral',
                        'descripcion' => 'Certificado laboral actual',
                        'tipo' => 'laboral',
                        'obligatorio' => true
                    ]
                ];
                break;

            default:
                // Para tipos no específicos, solo documentos base
                $documentosPorTipo = [];
                break;
        }

        return array_merge($documentosBase, $documentosPorTipo);
    }

    /**
     * Obtener datos del usuario autenticado desde JWT middleware
     */
    public function getAuthenticatedUser(Request $request): array
    {
        $authenticatedUser = $request->get('authenticated_user');
        return $authenticatedUser['user'] ?? [];
    }

    /**
     * Validar si el usuario autenticado puede acceder a una solicitud
     */
    public function canAccessSolicitud(array $userData, array $solicitud): bool
    {
        $username = $userData['username'] ?? null;
        $userRoles = $userData['roles'] ?? [];

        $isAdministrator = in_array('administrator', $userRoles);
        $isAdviser = in_array('adviser', $userRoles);

        if ($isAdministrator || $isAdviser) {
            return true;
        }

        return $username && ($solicitud['owner_username'] ?? '') === $username;
    }

    /**
     * Agregar documento a la solicitud usando el modelo SolicitudDocumento
     */
    public function agregarDocumentoASolicitud(string $solicitudId, array $fileData): array
    {
        try {
            $solicitud = (new SolicitudService())->getById($solicitudId);

            if (!$solicitud) {
                throw new \Exception("Solicitud no encontrada: {$solicitudId}");
            }

            // Crear registro en la tabla solicitud_documentos
            $documento = SolicitudDocumento::create([
                'solicitud_id' => $solicitudId,
                'documento_uuid' => $fileData['id'],
                'documento_requerido_id' => $fileData['documento_requerido_id'],
                'nombre_original' => $fileData['nombre_original'],
                'saved_filename' => basename($fileData['ruta_archivo']),
                'tipo_mime' => $fileData['tipo_mime'],
                'tamano_bytes' => $fileData['tamano'],
                'ruta_archivo' => $fileData['ruta_archivo'],
                'activo' => true
            ]);

            Log::info('Documento guardado en base de datos', [
                'documento_id' => $documento->id,
                'solicitud_id' => $solicitudId,
                'documento_uuid' => $fileData['id']
            ]);

            return [
                'documento' => $documento->toArray(),
                'solicitud' => $solicitud->fresh()->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Error al agregar documento a solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generar nombre único para archivo
     */
    public function generarNombreArchivo(string $solicitudId, string $documentoId, string $extension): string
    {
        $timestamp = Carbon::now()->format('YmdHis');
        return "{$solicitudId}_{$documentoId}_{$timestamp}.{$extension}";
    }

    /**
     * Eliminar documento de la solicitud
     */
    public function eliminarDocumentoDeSolicitud(string $solicitudId, string $documentoId): ?array
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();

            if (!$solicitud) {
                throw new \Exception("Solicitud no encontrada: {$solicitudId}");
            }

            $documento = SolicitudDocumento::where('solicitud_id', $solicitud->id)
                ->where('documento_id', $documentoId)
                ->first();

            if (!$documento) {
                throw new \Exception("Documento no encontrado: {$documentoId}");
            }

            // Eliminar archivo del storage
            if ($documento->ruta && Storage::exists($documento->ruta)) {
                Storage::delete($documento->ruta);
            }

            // Eliminar registro de la base de datos
            $documentoData = $documento->toArray();
            $documento->delete();

            return $documentoData;
        } catch (\Exception $e) {
            throw new \Exception("Error al eliminar documento: " . $e->getMessage());
        }
    }
}
