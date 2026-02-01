<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

abstract class EloquentService
{
    /**
     * Validate required fields.
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new ValidationException("Campos requeridos faltantes: " . implode(', ', $missing));
        }
    }

    /**
     * Validate data using Laravel Validator.
     */
    protected function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * Ensure entity exists.
     */
    protected function ensureExists(Model $entity, string $entityName = 'Entidad'): Model
    {
        if (!$entity) {
            throw new \Exception("{$entityName} no encontrada");
        }

        return $entity;
    }

    /**
     * Handle database errors.
     */
    protected function handleDatabaseError(\Exception $e, string $operation = 'operación'): void
    {
        Log::error("Error en {$operation}: " . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);

        throw new \Exception("Error en {$operation}. Por favor intente nuevamente.");
    }

    /**
     * Format success response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Operación exitosa'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Format error response.
     */
    protected function errorResponse(string $message, mixed $data = null, string $code = 'ERROR'): array
    {
        return [
            'success' => false,
            'error' => $message,
            'data' => $data,
            'code' => $code
        ];
    }

    /**
     * Log operation.
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        Log::log($level, $message, array_merge([
            'service' => static::class,
            'timestamp' => now()->toISOString()
        ], $context));
    }
}
