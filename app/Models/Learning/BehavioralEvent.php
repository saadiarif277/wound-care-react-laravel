<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class BehavioralEvent extends Model
{
    use HasFactory;

    protected $table = 'behavioral_events';

    protected $fillable = [
        'event_id',
        'user_id',
        'user_role',
        'facility_id',
        'organization_id',
        'event_type',
        'event_category',
        'timestamp',
        'session_id',
        'ip_hash',
        'user_agent_hash',
        'url_path',
        'http_method',
        'event_data',
        'context',
        'browser_info',
        'performance_metrics',
    ];

    protected $casts = [
        'event_data' => 'array',
        'context' => 'array',
        'browser_info' => 'array',
        'performance_metrics' => 'array',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the user that triggered this event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get events for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get events by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope to get events by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to get recent events
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get events for ML feature extraction
     */
    public static function getMLFeatures(int $userId, array $eventTypes = [], int $days = 30): array
    {
        $query = static::forUser($userId)->recent($days);
        
        if (!empty($eventTypes)) {
            $query->whereIn('event_type', $eventTypes);
        }
        
        return $query->select([
            'event_type',
            'event_category',
            'event_data',
            'context',
            'browser_info',
            'performance_metrics',
            'created_at'
        ])->get()->toArray();
    }

    /**
     * Get user behavior summary
     */
    public static function getUserBehaviorSummary(int $userId, int $days = 30): array
    {
        return static::forUser($userId)
            ->recent($days)
            ->selectRaw('
                event_category,
                COUNT(*) as total_events,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                AVG(JSON_EXTRACT(performance_metrics, "$.execution_time")) as avg_execution_time
            ')
            ->groupBy('event_category')
            ->get()
            ->toArray();
    }
} 