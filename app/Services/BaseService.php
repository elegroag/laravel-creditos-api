<?php

namespace App\Services;

use App\Repositories\BaseRepository;
use App\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected BaseRepository $repository;

    public function __construct(BaseRepository $repository)
    {
        $this->repository = $repository;
    }

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
    protected function ensureExists(int $id, string $entityName = 'Entidad'): Model
    {
        $entity = $this->repository->findById($id);

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
     * Create entity with error handling.
     */
    protected function createEntity(array $data)
    {
        try {
            return $this->repository->create($data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación');
        }
    }

    /**
     * Update entity with error handling.
     */
    protected function updateEntity(int $id, array $data)
    {
        try {
            return $this->repository->update($id, $data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización');
        }
    }

    /**
     * Delete entity with error handling.
     */
    protected function deleteEntity(int $id)
    {
        try {
            return $this->repository->delete($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación');
        }
    }

    /**
     * Get entity with error handling.
     */
    protected function getEntity(int $id)
    {
        try {
            return $this->repository->findById($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención');
        }
    }

    /**
     * List entities with error handling.
     */
    protected function listEntities(array $criteria = [], array $orderBy = [], ?int $limit = null)
    {
        try {
            return $this->repository->findMany($criteria, $orderBy, $limit);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'listado');
        }
    }

    /**
     * Count entities with error handling.
     */
    protected function countEntities(array $criteria = [])
    {
        try {
            return $this->repository->count($criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'conteo');
        }
    }

    /**
     * Check if entity exists with error handling.
     */
    protected function entityExists(array $criteria)
    {
        try {
            return $this->repository->exists($criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'verificación de existencia');
        }
    }

    /**
     * Paginate entities with error handling.
     */
    protected function paginateEntities(int $perPage = 15, array $criteria = [], array $orderBy = [])
    {
        try {
            return $this->repository->paginate($perPage, $criteria, $orderBy);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'paginación');
        }
    }

    /**
     * Execute operation within transaction.
     */
    protected function transaction(callable $callback)
    {
        try {
            return $this->repository->transaction($callback);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'transacción');
        }
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
     * Get repository instance.
     */
    protected function getRepository(): BaseRepository
    {
        return $this->repository;
    }

    /**
     * Set repository instance.
     */
    protected function setRepository(BaseRepository $repository): self
    {
        $this->repository = $repository;
        return $this;
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

    /**
     * Log debug information.
     */
    protected function debug(string $message, array $context = []): void
    {
        $this->log($message, $context, 'debug');
    }

    /**
     * Log error.
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log($message, $context, 'error');
    }

    /**
     * Log warning.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log($message, $context, 'warning');
    }

    /**
     * Transform data for API response.
     */
    protected function transformForApi($model): array
    {
        if (method_exists($model, 'toApiArray')) {
            return $model->toApiArray();
        }

        return $model->toArray();
    }

    /**
     * Transform collection for API response.
     */
    protected function transformCollectionForApi($collection): array
    {
        return $collection->map(fn($item) => $this->transformForApi($item))->toArray();
    }

    /**
     * Get model class name.
     */
    protected function getModelClass(): string
    {
        return get_class($this->repository->getModel());
    }

    /**
     * Format date for API response.
     */
    protected function formatDateForApi($date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof \Carbon\Carbon) {
            return $date->toISOString();
        }

        if ($date instanceof \DateTime) {
            return $date->format(\DateTime::ATOM);
        }

        return (string) $date;
    }

    /**
     * Format currency for API response.
     */
    protected function formatCurrencyForApi(float $amount, string $currency = 'COP'): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => number_format($amount, 2, ',', '.') . ' ' . $currency
        ];
    }

    /**
     * Validate entity exists by criteria.
     */
    protected function ensureExistsBy(array $criteria, string $entityName = 'Entidad'): Model
    {
        $entity = $this->repository->findOne($criteria);

        if (!$entity) {
            throw new \Exception("{$entityName} no encontrada");
        }

        return $entity;
    }

    /**
     * Create or update entity.
     */
    protected function createOrUpdate(array $criteria, array $data)
    {
        try {
            return $this->repository->updateOrCreate($criteria, $data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación o actualización');
        }
    }

    /**
     * Find first or create entity.
     */
    protected function firstOrCreate(array $criteria, array $data = [])
    {
        try {
            return $this->repository->firstOrCreate($criteria, $data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda o creación');
        }
    }

    /**
     * Get entities by IDs.
     */
    protected function getEntitiesByIds(array $ids)
    {
        try {
            return $this->repository->findIn('id', $ids);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención por IDs');
        }
    }

    /**
     * Search entities.
     */
    protected function searchEntities(string $term, array $fields = [])
    {
        try {
            return $this->repository->search($term, $fields);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda');
        }
    }

    /**
     * Get latest entity.
     */
    protected function getLatest(array $criteria = [], string $column = 'created_at')
    {
        try {
            return $this->repository->latest($criteria, $column);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención del más reciente');
        }
    }

    /**
     * Get oldest entity.
     */
    protected function getOldest(array $criteria = [], string $column = 'created_at')
    {
        try {
            return $this->repository->oldest($criteria, $column);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención del más antiguo');
        }
    }

    /**
     * Get random entities.
     */
    protected function getRandom(int $count = 1, array $criteria = [])
    {
        try {
            return $this->repository->random($count, $criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención aleatoria');
        }
    }

    /**
     * Process entities in chunks.
     */
    protected function chunkEntities(int $count, callable $callback, array $criteria = [])
    {
        try {
            return $this->repository->chunk($count, $callback, $criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'procesamiento por lotes');
        }
    }

    /**
     * Restore soft deleted entity.
     */
    protected function restoreEntity(int $id)
    {
        try {
            return $this->repository->restore($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'restauración');
        }
    }

    /**
     * Force delete entity.
     */
    protected function forceDeleteEntity(int $id)
    {
        try {
            return $this->repository->forceDelete($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación permanente');
        }
    }

    /**
     * Get entities with trashed.
     */
    protected function getEntitiesWithTrashed(array $criteria = [])
    {
        try {
            return $this->repository->withTrashed($criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención con eliminados');
        }
    }

    /**
     * Get only trashed entities.
     */
    protected function getOnlyTrashed(array $criteria = [])
    {
        try {
            return $this->repository->onlyTrashed($criteria);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de eliminados');
        }
    }
}
