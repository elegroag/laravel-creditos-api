<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use App\Services\FirmaShareTokenService;
use App\Services\FirmaXmlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FirmasController extends Controller
{
    protected FirmaShareTokenService $tokenService;
    protected FirmaXmlService $xmlService;

    public function __construct(FirmaShareTokenService $tokenService, FirmaXmlService $xmlService)
    {
        $this->tokenService = $tokenService;
        $this->xmlService = $xmlService;
    }

    /**
     * Crear token para compartir firmas
     */
    public function crearTokenShare(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'solicitud_filename' => 'required|string|max:255',
                'firmas_filename' => 'nullable|string|max:255'
            ], [
                'solicitud_filename.required' => 'El campo solicitud_filename es requerido',
                'solicitud_filename.string' => 'El campo solicitud_filename debe ser texto',
                'firmas_filename.string' => 'El campo firmas_filename debe ser texto'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $solicitudFilename = $data['solicitud_filename'];
            $firmasFilename = $data['firmas_filename'] ?? null;

            // Validar que termine en .xml
            if (!Str::endsWith($solicitudFilename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El campo solicitud_filename debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            if ($firmasFilename && !Str::endsWith($firmasFilename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El campo firmas_filename debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            // Validar que existan los archivos
            $solicitudPath = $this->validarArchivoXML($solicitudFilename);
            if (!$solicitudPath) {
                return response()->json([
                    'success' => false,
                    'error' => 'No existe el XML de solicitud: ' . $solicitudFilename,
                    'details' => []
                ], 404);
            }

            $firmasPath = null;
            if ($firmasFilename) {
                $firmasPath = $this->validarArchivoXML($firmasFilename);
                if (!$firmasPath) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No existe el XML de firmas: ' . $firmasFilename,
                        'details' => []
                    ], 404);
                }
            }

            // Crear token
            $tokenObj = $this->tokenService->crearToken(
                solicitudFilename: $solicitudFilename,
                firmasFilename: $firmasFilename,
                ttlDays: 3
            );

            Log::info('Token de firma creado exitosamente', [
                'token' => $tokenObj->token,
                'solicitud_filename' => $solicitudFilename,
                'firmas_filename' => $firmasFilename
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token de firma creado exitosamente',
                'data' => [
                    'token' => $tokenObj->token,
                    'solicitud_filename' => $tokenObj->solicitud_filename,
                    'firmas_filename' => $tokenObj->firmas_filename,
                    'expires_at' => $tokenObj->expires_at,
                    'url_path' => '/firmas-share/' . $tokenObj->token
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear token de firma', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al crear el token',
                'details' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Obtener información de token de firma
     */
    public function obtenerTokenShare(string $token): JsonResponse
    {
        try {
            $tokenObj = $this->tokenService->obtenerToken($token);

            if (!$tokenObj) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token no encontrado o expirado',
                    'details' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token de firma obtenido exitosamente',
                'data' => [
                    'token' => $tokenObj->token,
                    'solicitud_filename' => $tokenObj->solicitud_filename,
                    'firmas_filename' => $tokenObj->firmas_filename,
                    'expires_at' => $tokenObj->expires_at,
                    'url_path' => '/firmas-share/' . $tokenObj->token
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener token de firma', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener el token',
                'details' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Firmar documento usando token compartido
     */
    public function firmarConToken(Request $request, string $token): jsonResponse|Response
    {
        try {
            $validator = Validator::make($request->all(), [
                'firma' => 'required|array',
                'clave_firma' => 'required|string|min:10',
                'save_xml' => 'required|boolean'
            ], [
                'firma.required' => 'El campo firma es requerido y debe ser objeto',
                'firma.array' => 'El campo firma debe ser un objeto',
                'clave_firma.required' => 'El campo clave_firma es requerido',
                'clave_firma.min' => 'El campo clave_firma debe tener al menos 10 caracteres',
                'save_xml.required' => 'El campo save_xml es requerido',
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
            $firma = $data['firma'];
            $claveFirma = $data['clave_firma'];
            $saveXml = $data['save_xml'];

            // Para links compartidos, save_xml debe ser true
            if (!$saveXml) {
                return response()->json([
                    'success' => false,
                    'error' => 'Para link compartido, save_xml debe ser true',
                    'details' => []
                ], 400);
            }

            // Validar token
            $tokenObj = $this->tokenService->obtenerToken($token);

            if (!$tokenObj) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token no encontrado o expirado',
                    'details' => []
                ], 404);
            }

            // Validar archivos
            $solicitudPath = $this->validarArchivoXML($tokenObj->solicitud_filename);
            if (!$solicitudPath) {
                return response()->json([
                    'success' => false,
                    'error' => 'No existe el XML de solicitud: ' . $tokenObj->solicitud_filename,
                    'details' => []
                ], 404);
            }

            $firmasXmlBytes = null;
            if ($tokenObj->firmas_filename) {
                $firmasPath = $this->validarArchivoXML($tokenObj->firmas_filename);
                if ($firmasPath) {
                    $firmasXmlBytes = file_get_contents($firmasPath);
                }
            }

            // Generar XML con firmas
            $solicitudXmlBytes = file_get_contents($solicitudPath);

            if ($firmasXmlBytes === null) {
                $firmasOut = $this->xmlService->buildFirmasSolicitudCreditoXml(
                    $solicitudXmlBytes,
                    [$firma],
                    $claveFirma
                );
            } else {
                $firmasOut = $this->xmlService->appendFirmaToFirmasXml(
                    $solicitudXmlBytes,
                    $firmasXmlBytes,
                    $firma,
                    $claveFirma
                );
            }

            // Guardar archivo
            $savedFilename = null;
            if ($saveXml) {
                $savedFilename = $this->guardarArchivoFirmas($firmasOut, $tokenObj->solicitud_filename);

                // Actualizar token con el nombre del archivo guardado
                if (!$tokenObj->firmas_filename && $savedFilename) {
                    $this->tokenService->actualizarFirmasFilename($token, $savedFilename);
                }
            }

            Log::info('Documento firmado con token compartido', [
                'token' => $token,
                'solicitud_filename' => $tokenObj->solicitud_filename,
                'saved_filename' => $savedFilename
            ]);

            $response  = new Response($firmasOut, 200, [
                'Content-Type' => 'application/xml',
                'X-Saved-Filename' => $savedFilename ?? ''
            ]);
            return $response;
        } catch (\Exception $e) {
            Log::error('Error al firmar con token compartido', [
                'token' => $token,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al firmar el documento',
                'details' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Firmar documento directamente
     */
    public function firmarDocumento(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'solicitud_filename' => 'required|string|max:255',
                'firmas_filename' => 'nullable|string|max:255',
                'firma' => 'required|array',
                'clave_firma' => 'required|string|min:10',
                'save_xml' => 'required|boolean'
            ], [
                'solicitud_filename.required' => 'El campo solicitud_filename es requerido',
                'firmas_filename.string' => 'El campo firmas_filename debe ser texto',
                'firma.required' => 'El campo firma es requerido y debe ser objeto',
                'firma.array' => 'El campo firma debe ser un objeto',
                'clave_firma.required' => 'El campo clave_firma es requerido',
                'clave_firma.min' => 'El campo clave_firma debe tener al menos 10 caracteres',
                'save_xml.required' => 'El campo save_xml es requerido',
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
            $solicitudFilename = $data['solicitud_filename'];
            $firmasFilename = $data['firmas_filename'] ?? null;
            $firma = $data['firma'];
            $claveFirma = $data['clave_firma'];
            $saveXml = $data['save_xml'];

            // Validar que termine en .xml
            if (!Str::endsWith($solicitudFilename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El campo solicitud_filename debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            if ($firmasFilename && !Str::endsWith($firmasFilename, '.xml')) {
                return response()->json([
                    'success' => false,
                    'error' => 'El campo firmas_filename debe terminar en .xml',
                    'details' => []
                ], 400);
            }

            // Validar que existan los archivos
            $solicitudPath = $this->validarArchivoXML($solicitudFilename);
            if (!$solicitudPath) {
                return response()->json([
                    'success' => false,
                    'error' => 'No existe el XML de solicitud: ' . $solicitudFilename,
                    'details' => []
                ], 404);
            }

            $firmasXmlBytes = null;
            if ($firmasFilename) {
                $firmasPath = $this->validarArchivoXML($firmasFilename);
                if ($firmasPath) {
                    $firmasXmlBytes = file_get_contents($firmasPath);
                }
            }

            // Generar XML con firmas
            $solicitudXmlBytes = file_get_contents($solicitudPath);

            if ($firmasXmlBytes === null) {
                $firmasOut = $this->xmlService->buildFirmasSolicitudCreditoXml(
                    $solicitudXmlBytes,
                    [$firma],
                    $claveFirma
                );
            } else {
                $firmasOut = $this->xmlService->appendFirmaToFirmasXml(
                    $solicitudXmlBytes,
                    $firmasXmlBytes,
                    $firma,
                    $claveFirma
                );
            }

            // Guardar archivo si se solicita
            $savedFilename = null;
            if ($saveXml) {
                $savedFilename = $this->guardarArchivoFirmas($firmasOut, $solicitudFilename);
            }

            Log::info('Documento firmado directamente', [
                'solicitud_filename' => $solicitudFilename,
                'firmas_filename' => $firmasFilename,
                'saved_filename' => $savedFilename
            ]);

            if ($savedFilename) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento firmado exitosamente',
                    'data' => ['saved_filename' => $savedFilename]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Documento procesado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al firmar documento', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al firmar el documento',
                'details' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Validar que exista un archivo XML en las rutas permitidas
     */
    private function validarArchivoXML(string $filename): ?string
    {
        $xmlDir = storage_path('app/xml');
        $activosDir = storage_path('app/storage/activos');

        // Intentar en activos primero
        $path = $activosDir . '/' . $filename;
        if (file_exists($path) && is_file($path)) {
            return $path;
        }

        // Intentar en xml
        $path = $xmlDir . '/' . $filename;
        if (file_exists($path) && is_file($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Guardar archivo de firmas con nombre único
     */
    private function guardarArchivoFirmas(string $contenido, string $solicitudFilename): string
    {
        $activosDir = storage_path('app/storage/activos');

        // Asegurar que el directorio exista
        if (!is_dir($activosDir)) {
            mkdir($activosDir, 0755, true);
        }

        // Generar nombre único
        $base = 'firmas';
        $solicitudBase = Str::replace('.xml', '', $solicitudFilename);
        if ($solicitudBase) {
            $base = 'firmas-' . Str::slug($solicitudBase);
        }

        $timestamp = Carbon::now()->format('Ymd-His');
        $candidate = $base . '-' . $timestamp . '.xml';
        $counter = 1;

        while (file_exists($activosDir . '/' . $candidate)) {
            $candidate = $base . '-' . $timestamp . '-' . $counter . '.xml';
            $counter++;
        }

        $filepath = $activosDir . '/' . $candidate;
        file_put_contents($filepath, $contenido);

        Log::info('Archivo de firmas guardado', [
            'filename' => $candidate,
            'filepath' => $filepath,
            'size' => strlen($contenido)
        ]);

        return $candidate;
    }
}
