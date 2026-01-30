<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Find a record by ID.
     */
    public function findById($id)
    {
        return $this->model->find($id);
    }

    /**
     * Find a record by given criteria.
     */
    public function findOne(array $criteria)
    {
        return $this->model->where($criteria)->first();
    }

    /**
     * Find multiple records by criteria.
     */
    public function findMany(array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null): Collection
    {
        $query = $this->model->where($criteria);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        if ($limit) {
            $query->limit($limit);
        }

        if ($offset) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
     * Update a record by ID.
     */
    public function update(int $id, array $data): bool
    {
        $record = $this->findById($id);

        if (!$record) {
            return false;
        }

        return $record->update($data);
    }

    /**
     * Update a record and get the updated record.
     */
    public function updateAndGet(int $id, array $data)
    {
        $record = $this->findById($id);

        if (!$record) {
            return null;
        }

        $record->update($data);

        return $record->fresh();
    }

    /**
     * Delete a record by ID (soft delete if enabled).
     */
    public function delete(int $id): bool
    {
        $record = $this->findById($id);

        if (!$record) {
            return false;
        }

        return $record->delete();
    }

    /**
     * Force delete a record (permanent deletion).
     */
    public function forceDelete(int $id): bool
    {
        $record = $this->model->withTrashed()->find($id);

        if (!$record) {
            return false;
        }

        return $record->forceDelete();
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(int $id): bool
    {
        $record = $this->model->withTrashed()->find($id);

        if (!$record) {
            return false;
        }

        return $record->restore();
    }

    /**
     * Count records by criteria.
     */
    public function count(array $criteria = []): int
    {
        return $this->model->where($criteria)->count();
    }

    /**
     * Check if a record exists by criteria.
     */
    public function exists(array $criteria): bool
    {
        return $this->model->where($criteria)->exists();
    }

    /**
     * Get paginated results.
     */
    public function paginate(int $perPage = 15, array $criteria = [], array $orderBy = []): LengthAwarePaginator
    {
        $query = $this->model->where($criteria);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all records.
     */
    public function all(array $orderBy = []): Collection
    {
        $query = $this->model->query();

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Get records with trashed (including soft-deleted).
     */
    public function withTrashed(array $criteria = []): Collection
    {
        return $this->model->withTrashed()->where($criteria)->get();
    }

    /**
     * Get only trashed records.
     */
    public function onlyTrashed(array $criteria = []): Collection
    {
        return $this->model->onlyTrashed()->where($criteria)->get();
    }

    /**
     * Find records where field is in given values.
     */
    public function findIn(string $field, array $values, array $orderBy = []): Collection
    {
        $query = $this->model->whereIn($field, $values);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Find records where field is not in given values.
     */
    public function findNotIn(string $field, array $values, array $orderBy = []): Collection
    {
        $query = $this->model->whereNotIn($field, $values);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Search records by text in specified fields.
     */
    public function search(string $term, array $fields = [], array $orderBy = []): Collection
    {
        $query = $this->model->query();

        if (empty($fields)) {
            // Search in all string fields
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        } else {
            $query->where(function ($q) use ($term, $fields) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%{$term}%");
                }
            });
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Get first or create record.
     */
    public function firstOrCreate(array $criteria, array $data = []): Model
    {
        return $this->model->firstOrCreate($criteria, $data);
    }

    /**
     * Update or create record.
     */
    public function updateOrCreate(array $criteria, array $data): Model
    {
        return $this->model->updateOrCreate($criteria, $data);
    }

    /**
     * Get latest record by criteria.
     */
    public function latest(array $criteria = [], string $column = 'created_at'): ?Model
    {
        return $this->model->where($criteria)->latest($column)->first();
    }

    /**
     * Get oldest record by criteria.
     */
    public function oldest(array $criteria = [], string $column = 'created_at'): ?Model
    {
        return $this->model->where($criteria)->oldest($column)->first();
    }

    /**
     * Get random records.
     */
    public function random(int $count = 1, array $criteria = []): Collection
    {
        return $this->model->where($criteria)->inRandomOrder()->limit($count)->get();
    }

    /**
     * Chunk results for processing large datasets.
     */
    public function chunk(int $count, callable $callback, array $criteria = []): bool
    {
        return $this->model->where($criteria)->chunk($count, $callback);
    }

    /**
     * Get model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set model instance.
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    /**
     * Commit a database transaction.
     */
    public function commit(): void
    {
        DB::commit();
    }

    /**
     * Rollback a database transaction.
     */
    public function rollback(): void
    {
        DB::rollBack();
    }

    /**
     * Execute a callback within a database transaction.
     */
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
