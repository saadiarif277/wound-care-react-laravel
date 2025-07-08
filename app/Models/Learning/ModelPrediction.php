<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelPrediction extends Model
{
    use HasFactory;

    protected $table = 'model_predictions';

    protected $fillable = [
        'model_id',
        'input_data',
        'prediction',
        'confidence',
        'actual_outcome',
        'user_feedback',
        'feedback_received_at',
        'execution_time_ms',
        'created_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'prediction' => 'array',
        'user_feedback' => 'array',
        'confidence' => 'float',
        'actual_outcome' => 'boolean',
        'feedback_received_at' => 'datetime',
    ];

    /**
     * Get the model that made this prediction
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(MLModel::class);
    }

    /**
     * Scope to get accurate predictions
     */
    public function scopeAccurate($query)
    {
        return $query->where('actual_outcome', true);
    }

    /**
     * Scope to get predictions with feedback
     */
    public function scopeWithFeedback($query)
    {
        return $query->whereNotNull('actual_outcome');
    }

    /**
     * Scope to get recent predictions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if prediction was accurate
     */
    public function wasAccurate(): ?bool
    {
        return $this->actual_outcome;
    }

    /**
     * Get confidence level description
     */
    public function getConfidenceLevel(): string
    {
        if ($this->confidence >= 0.9) return 'very_high';
        if ($this->confidence >= 0.7) return 'high';
        if ($this->confidence >= 0.5) return 'medium';
        if ($this->confidence >= 0.3) return 'low';
        return 'very_low';
    }
} 