<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponseHelper
{
    /**
     * Respuesta exitosa estándar
     *
     * @param mixed $data Datos a retornar
     * @param string $message Mensaje de éxito
     * @param int $status Código HTTP
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Operación exitosa', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Respuesta de error estándar
     *
     * @param string $error Mensaje de error
     * @param mixed $trace Traza del error (opcional)
     * @param int $status Código HTTP
     * @param array $additionalData Datos adicionales (opcional)
     * @return JsonResponse
     */
    public static function error(string $error, $trace = null, int $status = 400, array $additionalData = []): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => $error,
            'timestamp' => now()->toISOString(),
        ];

        if ($trace !== null) {
            $response['trace'] = $trace;
        }

        if (!empty($additionalData)) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response, $status);
    }

    /**
     * Respuesta de error de autenticación
     *
     * @param string $message Mensaje de error
     * @param mixed $trace Traza del error (opcional)
     * @return JsonResponse
     */
    public static function authError(string $message = 'Error de autenticación', $trace = null): JsonResponse
    {
        return self::error($message, $trace, 401);
    }

    /**
     * Respuesta de error de validación
     *
     * @param array $errors Errores de validación
     * @param string $message Mensaje general
     * @return JsonResponse
     */
    public static function validationError(array $errors, string $message = 'Datos inválidos'): JsonResponse
    {
        return self::error($message, null, 422, ['validation_errors' => $errors]);
    }

    /**
     * Respuesta de no encontrado
     *
     * @param string $message Mensaje de error
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return self::error($message, null, 404);
    }

    /**
     * Respuesta de acceso denegado
     *
     * @param string $message Mensaje de error
     * @param mixed $details Detalles adicionales
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'Acceso denegado', $details = null): JsonResponse
    {
        $additionalData = [];
        if ($details !== null) {
            $additionalData['details'] = $details;
        }
        
        return self::error($message, null, 403, $additionalData);
    }

    /**
     * Respuesta de error del servidor
     *
     * @param string $message Mensaje de error
     * @param mixed $trace Traza del error
     * @return JsonResponse
     */
    public static function serverError(string $message = 'Error interno del servidor', $trace = null): JsonResponse
    {
        return self::error($message, $trace, 500);
    }
}
