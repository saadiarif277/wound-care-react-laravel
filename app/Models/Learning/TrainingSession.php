<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSession extends Model
{
    use HasFactory;

    protected $table = 'training_sessions';

    protected $fillable = [
        'model_type',
        'model_id',
        'training_samples',
        'feature_count',
        'data_quality_score',
        'training_parameters',
        'status',
        'accuracy',
        'validation_accuracy',
        'loss',
        'validation_loss',
        'epochs_completed',
        'training_time_seconds',
        'error_message',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'training_parameters' => 'array',
        'data_quality_score' => 'float',
        'accuracy' => 'float',
        'validation_accuracy' => 'float',
        'loss' => 'float',
        'validation_loss' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the model that was trained in this session
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(MLModel::class);
    }

    /**
     * Scope to get completed training sessions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed training sessions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get training sessions by model type
     */
    public function scopeByModelType($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to get recent training sessions
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if training was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'completed' && $this->accuracy !== null;
    }

    /**
     * Get training duration in seconds
     */
    public function getDurationSeconds(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get training performance summary
     */
    public function getPerformanceSummary(): array
    {
        return [
            'accuracy' => $this->accuracy,
            'validation_accuracy' => $this->validation_accuracy,
            'loss' => $this->loss,
            'validation_loss' => $this->validation_loss,
            'training_samples' => $this->training_samples,
            'feature_count' => $this->feature_count,
            'epochs_completed' => $this->epochs_completed,
            'training_time_seconds' => $this->training_time_seconds,
            'data_quality_score' => $this->data_quality_score,
        ];
    }

    /**
     * Mark training session as completed
     */
    public function markCompleted(array $results): void
    {
        $this->update([
            'status' => 'completed',
            'accuracy' => $results['accuracy'] ?? null,
            'validation_accuracy' => $results['validation_accuracy'] ?? null,
            'loss' => $results['loss'] ?? null,
            'validation_loss' => $results['validation_loss'] ?? null,
            'epochs_completed' => $results['epochs_completed'] ?? null,
            'training_time_seconds' => $results['training_time_seconds'] ?? null,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark training session as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
} 