<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $response = [
            'success' => false,
            'error' => $this->resource['error'] ?? 'Error desconocido',
            'timestamp' => now()->toISOString(),
        ];

        if (isset($this->resource['trace'])) {
            $response['trace'] = $this->resource['trace'];
        }

        if (isset($this->resource['validation_errors'])) {
            $response['validation_errors'] = $this->resource['validation_errors'];
        }

        if (isset($this->resource['details'])) {
            $response['details'] = $this->resource['details'];
        }

        return $response;
    }

    /**
     * Create an error response
     *
     * @param string $error
     * @param mixed $trace
     * @param array $additionalData
     * @return static
     */
    public static function errorResponse(string $error, $trace = null, array $additionalData = [])
    {
        $data = ['error' => $error];

        if ($trace !== null) {
            $data['trace'] = $trace;
        }

        if (!empty($additionalData)) {
            $data = array_merge($data, $additionalData);
        }

        return new static($data);
    }

    /**
     * Create an authentication error
     *
     * @param string $message
     * @param mixed $trace
     * @return static
     */
    public static function authError(string $message = 'Error de autenticación', $trace = null)
    {
        return new static([
            'error' => $message,
            'trace' => $trace
        ]);
    }

    /**
     * Create a validation error
     *
     * @param array $errors
     * @param string $message
     * @return static
     */
    public static function validationError(array $errors, string $message = 'Datos inválidos')
    {
        return new static([
            'error' => $message,
            'validation_errors' => $errors
        ]);
    }

    /**
     * Create a not found error
     *
     * @param string $message
     * @return static
     */
    public static function notFound(string $message = 'Recurso no encontrado')
    {
        return new static(['error' => $message]);
    }

    /**
     * Create a forbidden error
     *
     * @param string $message
     * @param mixed $details
     * @return static
     */
    public static function forbidden(string $message = 'Acceso denegado', $details = null)
    {
        $data = ['error' => $message];

        if ($details !== null) {
            $data['details'] = $details;
        }

        return new static($data);
    }

    /**
     * Create a server error
     *
     * @param string $message
     * @param mixed $trace
     * @return static
     */
    public static function serverError(string $message = 'Error interno del servidor', $trace = null)
    {
        return new static([
            'error' => $message,
            'trace' => $trace
        ]);
    }
}
