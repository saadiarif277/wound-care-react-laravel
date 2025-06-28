<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Docuseal\DocusealTemplate;

class MappingAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'template_id',
        'user_id',
        'action',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the template this audit log belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocusealTemplate::class, 'template_id');
    }

    /**
     * Get the user who made the change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create audit log for mapping creation
     */
    public static function logCreation(int $templateId, array $mappingData, ?int $userId = null): self
    {
        return self::create([
            'template_id' => $templateId,
            'user_id' => $userId ?? auth()->id(),
            'action' => 'created',
            'changes' => [
                'before' => null,
                'after' => $mappingData,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create audit log for mapping update
     */
    public static function logUpdate(int $templateId, array $before, array $after, ?int $userId = null): self
    {
        return self::create([
            'template_id' => $templateId,
            'user_id' => $userId ?? auth()->id(),
            'action' => 'updated',
            'changes' => [
                'before' => $before,
                'after' => $after,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create audit log for mapping deletion
     */
    public static function logDeletion(int $templateId, array $mappingData, ?int $userId = null): self
    {
        return self::create([
            'template_id' => $templateId,
            'user_id' => $userId ?? auth()->id(),
            'action' => 'deleted',
            'changes' => [
                'before' => $mappingData,
                'after' => null,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create audit log for bulk update
     */
    public static function logBulkUpdate(int $templateId, array $changes, ?int $userId = null): self
    {
        return self::create([
            'template_id' => $templateId,
            'user_id' => $userId ?? auth()->id(),
            'action' => 'bulk_update',
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get human-readable action name
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'created' => 'Created Mapping',
            'updated' => 'Updated Mapping',
            'deleted' => 'Deleted Mapping',
            'bulk_update' => 'Bulk Update',
            default => ucfirst($this->action),
        };
    }
}