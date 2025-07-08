<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Learning\MLModel;
use App\Models\Learning\TrainingSession;
use Illuminate\Support\Facades\Log;

class TrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $modelType;
    protected array $trainingData;
    protected ?int $trainingSessionId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $modelType, array $trainingData, ?int $trainingSessionId = null)
    {
        $this->modelType = $modelType;
        $this->trainingData = $trainingData;
        $this->trainingSessionId = $trainingSessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $trainingSession = $this->trainingSessionId ? 
            TrainingSession::find($this->trainingSessionId) : 
            null;

        try {
            Log::info('Starting model training', [
                'model_type' => $this->modelType,
                'training_samples' => count($this->trainingData['dataset'] ?? []),
                'session_id' => $this->trainingSessionId,
            ]);

            // Update training session status
            if ($trainingSession) {
                $trainingSession->update(['status' => 'training']);
            }

            // Train the model (this would call your actual ML service)
            $trainingResults = $this->trainModel($this->modelType, $this->trainingData);

            // Create the trained model record
            $model = MLModel::create([
                'model_type' => $this->modelType,
                'model_name' => $this->generateModelName($this->modelType),
                'version' => $this->generateVersion($this->modelType),
                'status' => 'trained',
                'accuracy' => $trainingResults['accuracy'] ?? null,
                'training_samples' => count($this->trainingData['dataset'] ?? []),
                'feature_count' => count($this->trainingData['metadata']['features_extracted'] ?? []),
                'model_parameters' => $trainingResults['parameters'] ?? [],
                'model_artifacts' => $trainingResults['artifacts'] ?? [],
                'performance_metrics' => $trainingResults['metrics'] ?? [],
                'total_predictions' => 0,
            ]);

            // Update training session with results
            if ($trainingSession) {
                $trainingSession->markCompleted($trainingResults);
                $trainingSession->update(['model_id' => $model->id]);
            }

            // Activate the model if it meets quality thresholds
            if ($this->shouldActivateModel($model, $trainingResults)) {
                $model->activate();
                Log::info('Model activated', [
                    'model_id' => $model->id,
                    'model_type' => $this->modelType,
                    'accuracy' => $trainingResults['accuracy'] ?? 'unknown'
                ]);
            }

            Log::info('Model training completed successfully', [
                'model_id' => $model->id,
                'model_type' => $this->modelType,
                'accuracy' => $trainingResults['accuracy'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Model training failed', [
                'model_type' => $this->modelType,
                'error' => $e->getMessage(),
                'session_id' => $this->trainingSessionId,
            ]);

            if ($trainingSession) {
                $trainingSession->markFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Train the actual model (placeholder for ML service integration)
     */
    private function trainModel(string $modelType, array $trainingData): array
    {
        // This would typically call your ML service (Python, Azure ML, etc.)
        // For now, we'll simulate training with realistic results
        
        $sampleCount = count($trainingData['dataset'] ?? []);
        $featureCount = count($trainingData['metadata']['features_extracted'] ?? []);
        
        // Simulate training time based on data size
        $trainingTimeSeconds = max(10, $sampleCount * 0.1);
        
        // Simulate model performance based on data quality
        $dataQuality = $trainingData['metadata']['data_quality_score'] ?? 0.8;
        $baseAccuracy = 0.7;
        $accuracyBonus = ($dataQuality - 0.5) * 0.4; // Max 0.2 bonus
        $sampleBonus = min(0.1, $sampleCount / 10000); // Max 0.1 bonus
        
        $accuracy = min(0.95, $baseAccuracy + $accuracyBonus + $sampleBonus);
        
        // Add some randomness to simulate real training variance
        $accuracy += (mt_rand(-5, 5) / 100); // ±5% variance
        $accuracy = max(0.5, min(0.95, $accuracy));
        
        return [
            'accuracy' => round($accuracy, 3),
            'validation_accuracy' => round($accuracy - 0.02, 3), // Slightly lower validation
            'loss' => round((1 - $accuracy) * 2, 3),
            'validation_loss' => round((1 - $accuracy) * 2.2, 3),
            'epochs_completed' => mt_rand(10, 50),
            'training_time_seconds' => $trainingTimeSeconds,
            'parameters' => $this->generateModelParameters($modelType),
            'artifacts' => $this->generateModelArtifacts($modelType),
            'metrics' => [
                'precision' => round($accuracy + 0.01, 3),
                'recall' => round($accuracy - 0.01, 3),
                'f1_score' => round($accuracy, 3),
                'auc_roc' => round($accuracy + 0.05, 3),
            ],
        ];
    }

    /**
     * Generate model name
     */
    private function generateModelName(string $modelType): string
    {
        return ucfirst(str_replace('_', ' ', $modelType)) . ' Model';
    }

    /**
     * Generate model version
     */
    private function generateVersion(string $modelType): string
    {
        $latestModel = MLModel::byType($modelType)->orderBy('created_at', 'desc')->first();
        $latestVersion = $latestModel ? $latestModel->version : '1.0.0';
        
        // Increment patch version
        $versionParts = explode('.', $latestVersion);
        $versionParts[2] = (int)$versionParts[2] + 1;
        
        return implode('.', $versionParts);
    }

    /**
     * Check if model should be activated
     */
    private function shouldActivateModel(MLModel $model, array $trainingResults): bool
    {
        $accuracy = $trainingResults['accuracy'] ?? 0;
        
        // Minimum accuracy threshold
        if ($accuracy < 0.7) {
            return false;
        }
        
        // Check if it's better than current active model
        $currentModel = MLModel::byType($this->modelType)->active()->first();
        if ($currentModel && $currentModel->accuracy > $accuracy) {
            return false;
        }
        
        return true;
    }

    /**
     * Generate model parameters based on type
     */
    private function generateModelParameters(string $modelType): array
    {
        $baseParams = [
            'learning_rate' => 0.001,
            'batch_size' => 32,
            'optimizer' => 'adam',
            'regularization' => 0.01,
        ];
        
        switch ($modelType) {
            case 'behavioral_prediction':
                return array_merge($baseParams, [
                    'hidden_layers' => [128, 64, 32],
                    'dropout_rate' => 0.3,
                    'activation' => 'relu',
                ]);
                
            case 'product_recommendation':
                return array_merge($baseParams, [
                    'embedding_dim' => 50,
                    'hidden_factors' => 100,
                    'regularization' => 0.05,
                ]);
                
            case 'workflow_optimization':
                return array_merge($baseParams, [
                    'sequence_length' => 20,
                    'lstm_units' => 64,
                    'attention_heads' => 4,
                ]);
                
            default:
                return $baseParams;
        }
    }

    /**
     * Generate model artifacts (file paths, model weights, etc.)
     */
    private function generateModelArtifacts(string $modelType): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $modelId = uniqid();
        
        return [
            'model_file' => "models/{$modelType}/{$modelId}_{$timestamp}.pkl",
            'weights_file' => "models/{$modelType}/{$modelId}_{$timestamp}_weights.h5",
            'config_file' => "models/{$modelType}/{$modelId}_{$timestamp}_config.json",
            'scaler_file' => "models/{$modelType}/{$modelId}_{$timestamp}_scaler.pkl",
            'feature_names' => "models/{$modelType}/{$modelId}_{$timestamp}_features.json",
        ];
    }
} 