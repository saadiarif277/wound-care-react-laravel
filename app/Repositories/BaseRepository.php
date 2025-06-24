<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\RepositoryInterface;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->select($columns)->get();
    }

    /**
     * Get paginated records
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->select($columns)->paginate($perPage);
    }

    /**
     * Find record by ID
     */
    public function find($id, array $columns = ['*']): ?Model
    {
        return $this->model->select($columns)->find($id);
    }

    /**
     * Find record by ID or fail
     */
    public function findOrFail($id, array $columns = ['*']): Model
    {
        return $this->model->select($columns)->findOrFail($id);
    }

    /**
     * Find records by attribute
     */
    public function findBy(string $attribute, $value, array $columns = ['*']): Collection
    {
        return $this->model->select($columns)->where($attribute, $value)->get();
    }

    /**
     * Find first record by attribute
     */
    public function findOneBy(string $attribute, $value, array $columns = ['*']): ?Model
    {
        return $this->model->select($columns)->where($attribute, $value)->first();
    }

    /**
     * Find records by multiple attributes
     */
    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        $query = $this->model->select($columns);
        
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($operator, $val) = $value;
                $query->where($field, $operator, $val);
            } else {
                $query->where($field, $value);
            }
        }
        
        return $query->get();
    }

    /**
     * Create new record
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update record
     */
    public function update($id, array $data): bool
    {
        $record = $this->find($id);
        
        if (!$record) {
            return false;
        }
        
        return $record->update($data);
    }

    /**
     * Update or create record
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    /**
     * Delete record
     */
    public function delete($id): bool
    {
        $record = $this->find($id);
        
        if (!$record) {
            return false;
        }
        
        return $record->delete();
    }

    /**
     * Delete records by attribute
     */
    public function deleteWhere(array $where): int
    {
        $query = $this->model->query();
        
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($operator, $val) = $value;
                $query->where($field, $operator, $val);
            } else {
                $query->where($field, $value);
            }
        }
        
        return $query->delete();
    }

    /**
     * Count records
     */
    public function count(array $where = []): int
    {
        if (empty($where)) {
            return $this->model->count();
        }
        
        $query = $this->model->query();
        
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($operator, $val) = $value;
                $query->where($field, $operator, $val);
            } else {
                $query->where($field, $value);
            }
        }
        
        return $query->count();
    }

    /**
     * Check if record exists
     */
    public function exists($id): bool
    {
        return $this->model->where($this->model->getKeyName(), $id)->exists();
    }

    /**
     * Get records with relationships
     */
    public function with(array $relations): self
    {
        $this->model = $this->model->with($relations);
        return $this;
    }

    /**
     * Order records
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->model = $this->model->orderBy($column, $direction);
        return $this;
    }

    /**
     * Apply scope
     */
    public function scope(string $scope, ...$parameters): self
    {
        $this->model = $this->model->$scope(...$parameters);
        return $this;
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): void
    {
        $this->model->getConnection()->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): void
    {
        $this->model->getConnection()->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): void
    {
        $this->model->getConnection()->rollBack();
    }

    /**
     * Get model instance
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set model instance
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }
}