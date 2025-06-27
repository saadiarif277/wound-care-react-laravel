<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Get all records
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated records
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find record by ID
     */
    public function find($id, array $columns = ['*']): ?Model;

    /**
     * Find record by ID or fail
     */
    public function findOrFail($id, array $columns = ['*']): Model;

    /**
     * Find records by attribute
     */
    public function findBy(string $attribute, $value, array $columns = ['*']): Collection;

    /**
     * Find first record by attribute
     */
    public function findOneBy(string $attribute, $value, array $columns = ['*']): ?Model;

    /**
     * Find records by multiple attributes
     */
    public function findWhere(array $where, array $columns = ['*']): Collection;

    /**
     * Create new record
     */
    public function create(array $data): Model;

    /**
     * Update record
     */
    public function update($id, array $data): bool;

    /**
     * Update or create record
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * Delete record
     */
    public function delete($id): bool;

    /**
     * Delete records by attribute
     */
    public function deleteWhere(array $where): int;

    /**
     * Count records
     */
    public function count(array $where = []): int;

    /**
     * Check if record exists
     */
    public function exists($id): bool;
}