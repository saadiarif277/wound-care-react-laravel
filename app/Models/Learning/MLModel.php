<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MLModel extends Model
{
    use HasFactory;

    protected $table = 'ml_models';

    protected $fillable = [
        'model_type',
        'model_name',
        'version',
        'status',
        'accuracy',
        'training_samples',
        'feature_count',
        'model_parameters',
        'model_artifacts',
        'performance_metrics',
        'created_by',
        'last_prediction_at',
        'total_predictions',
    ];

    protected $casts = [
        'model_parameters' => 'array',
        'model_artifacts' => 'array',
        'performance_metrics' => 'array',
        'accuracy' => 'float',
        'last_prediction_at' => 'datetime',
    ];

    /**
     * Get the predictions made by this model
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(ModelPrediction::class);
    }

    /**
     * Get the training session that created this model
     */
    public function trainingSession(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * Scope to get active models
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get models by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Get the latest version of a model type
     */
    public static function getLatestVersion(string $modelType): ?self
    {
        return static::byType($modelType)
            ->active()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Mark this model as active and deactivate others of same type
     */
    public function activate(): void
    {
        // Deactivate other models of same type
        static::byType($this->model_type)
            ->where('id', '!=', $this->id)
            ->update(['status' => 'inactive']);
        
        // Activate this model
        $this->update(['status' => 'active']);
    }

    /**
     * Get model performance summary
     */
    public function getPerformanceSummary(): array
    {
        $predictions = $this->predictions()->whereNotNull('actual_outcome')->get();
        
        if ($predictions->isEmpty()) {
            return [
                'accuracy' => null,
                'total_predictions' => 0,
                'confidence_avg' => null,
                'recent_accuracy' => null,
            ];
        }
        
        $accuracy = $predictions->where('actual_outcome', true)->count() / $predictions->count();
        $recentPredictions = $predictions->where('created_at', '>=', now()->subDays(7));
        $recentAccuracy = $recentPredictions->isEmpty() ? null : 
            $recentPredictions->where('actual_outcome', true)->count() / $recentPredictions->count();
        
        return [
            'accuracy' => $accuracy,
            'total_predictions' => $predictions->count(),
            'confidence_avg' => $predictions->avg('confidence'),
            'recent_accuracy' => $recentAccuracy,
            'last_prediction' => $this->last_prediction_at,
        ];
    }
} 