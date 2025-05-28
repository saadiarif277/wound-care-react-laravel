<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocusealFolder extends Model
{
    use HasUuids;

    protected $fillable = [
        'manufacturer_id',
        'docuseal_folder_id',
        'folder_name',
        'delivery_endpoint',
        'delivery_credentials_encrypted',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active folders
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the folder for a specific manufacturer
     */
    public static function getForManufacturer(string $manufacturerId): ?self
    {
        return static::active()
            ->where('manufacturer_id', $manufacturerId)
            ->first();
    }
} 