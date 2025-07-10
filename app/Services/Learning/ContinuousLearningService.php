<?php

namespace App\Services\Learning;

use App\Models\Learning\BehavioralEvent;
use App\Models\Learning\MLModel;
use App\Models\Learning\ModelPrediction;
use App\Models\Learning\TrainingSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use App\Jobs\TrainModelJob;
use App\Jobs\UpdateModelPredictionsJob;

/**
 * Continuous Learning Service
 * 
 * Implements stacked machine learning models that continuously learn from user behavior:
 * - Behavioral prediction models (next action, completion likelihood)
 * - Product recommendation models (collaborative filtering + content-based)
 * - Workflow optimization models (path prediction, bottleneck detection)
 * - Form optimization models (field completion, error prediction)
 * - Personalization models (UI preferences, content filtering)
 * - Clinical decision support models (diagnosis assistance, treatment recommendations)
 */
class ContinuousLearningService
{
    private MLDataPipelineService $dataPipeline;
    private BehavioralTrackingService $behavioralTracker;
    
    // Model types
    private const MODEL_TYPES = [
        'behavioral_prediction' => 'Behavioral Prediction Model',
        'product_recommendation' => 'Product Recommendation Model',
        'workflow_optimization' => 'Workflow Optimization Model',
        'form_optimization' => 'Form Optimization Model',
        'personalization' => 'Personalization Model',
        'clinical_decision' => 'Clinical Decision Support Model',
    ];
    
    // Training thresholds
    private const MIN_TRAINING_SAMPLES = 100;
    private const RETRAIN_INTERVAL_HOURS = 24;
    private const PREDICTION_CONFIDENCE_THRESHOLD = 0.7;

    public function __construct(
        MLDataPipelineService $dataPipeline,
        BehavioralTrackingService $behavioralTracker
    ) {
        $this->dataPipeline = $dataPipeline;
        $this->behavioralTracker = $behavioralTracker;
    }

    /**
     * Train all models with latest data
     */
    public function trainAllModels(bool $force = false): array
    {
        $results = [];
        
        foreach (self::MODEL_TYPES as $modelType => $modelName) {
            try {
                $result = $this->trainModel($modelType, $force);
                $results[$modelType] = $result;
                
                Log::info("Model training completed", [
                    'model_type' => $modelType,
                    'accuracy' => $result['accuracy'] ?? 'N/A',
                    'training_samples' => $result['training_samples'] ?? 'N/A'
                ]);
                
            } catch (\Exception $e) {
                Log::error("Model training failed", [
                    'model_type' => $modelType,
                    'error' => $e->getMessage()
                ]);
                $results[$modelType] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    /**
     * Train a specific model type
     */
    public function trainModel(string $modelType, bool $force = false): array
    {
        if (!isset(self::MODEL_TYPES[$modelType])) {
            throw new \InvalidArgumentException("Invalid model type: $modelType");
        }

        // Check if model needs retraining
        if (!$force && !$this->shouldRetrain($modelType)) {
            return ['skipped' => 'Model does not need retraining'];
        }

        // Get training data
        $trainingData = $this->getTrainingData($modelType);
        
        if (count($trainingData['dataset']) < self::MIN_TRAINING_SAMPLES) {
            return ['skipped' => 'Insufficient training data'];
        }

        // Train the model
        $trainingSession = TrainingSession::create([
            'model_type' => $modelType,
            'training_samples' => count($trainingData['dataset']),
            'feature_count' => count($trainingData['metadata']['features_extracted']),
            'data_quality_score' => $trainingData['metadata']['data_quality_score'],
            'status' => 'training',
            'started_at' => now(),
        ]);

        try {
            // Dispatch training job
            TrainModelJob::dispatch($modelType, $trainingData, $trainingSession->id);
            
            return [
                'training_session_id' => $trainingSession->id,
                'training_samples' => count($trainingData['dataset']),
                'status' => 'training_started'
            ];
            
        } catch (\Exception $e) {
            $trainingSession->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Make predictions using trained models
     */
    public function predict(string $modelType, array $inputData): array
    {
        $model = MLModel::where('model_type', $modelType)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$model) {
            return $this->getFallbackPrediction($modelType, $inputData);
        }

        try {
            $prediction = $this->performPrediction($model, $inputData);
            
            // Store prediction for analysis
            ModelPrediction::create([
                'model_id' => $model->id,
                'input_data' => $inputData,
                'prediction' => $prediction,
                'confidence' => $prediction['confidence'] ?? 0.0,
                'created_at' => now(),
            ]);
            
            return $prediction;
            
        } catch (\Exception $e) {
            Log::error("Prediction failed", [
                'model_type' => $modelType,
                'model_id' => $model->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->getFallbackPrediction($modelType, $inputData);
        }
    }

    /**
     * Get real-time recommendations for a user
     */
    public function getRealtimeRecommendations(int $userId): array
    {
        // Get recent user behavior
        $userFeatures = $this->dataPipeline->extractUserBehaviorFeatures($userId, 7);
        
        // Get predictions from all relevant models
        $predictions = [
            'behavioral' => $this->predict('behavioral_prediction', $userFeatures),
            'product' => $this->predict('product_recommendation', $userFeatures),
            'workflow' => $this->predict('workflow_optimization', $userFeatures),
            'form' => $this->predict('form_optimization', $userFeatures),
            'personalization' => $this->predict('personalization', $userFeatures),
        ];

        // Combine predictions into actionable recommendations
        return $this->combineRecommendations($predictions, $userFeatures);
    }

    /**
     * Get next best action for a user
     */
    public function getNextBestAction(int $userId, array $currentContext = []): array
    {
        $userFeatures = array_merge(
            $this->dataPipeline->extractUserBehaviorFeatures($userId, 7),
            $currentContext
        );

        $prediction = $this->predict('behavioral_prediction', $userFeatures);
        
        return [
            'recommended_action' => $prediction['next_action'] ?? 'continue_workflow',
            'confidence' => $prediction['confidence'] ?? 0.5,
            'reasoning' => $prediction['reasoning'] ?? 'Based on user behavior patterns',
            'alternatives' => $prediction['alternatives'] ?? [],
            'personalization_hints' => $this->getPersonalizationHints($userId),
        ];
    }

    /**
     * Update model performance based on user feedback
     */
    public function updateModelPerformance(int $predictionId, bool $wasAccurate, array $feedback = []): void
    {
        $prediction = ModelPrediction::find($predictionId);
        if (!$prediction) {
            return;
        }

        $prediction->update([
            'actual_outcome' => $wasAccurate,
            'user_feedback' => $feedback,
            'feedback_received_at' => now(),
        ]);

        // Update model accuracy metrics
        $this->updateModelAccuracy($prediction->model_id, $wasAccurate);
        
        // Trigger model retraining if accuracy drops below threshold
        if ($prediction->model->accuracy < 0.6) {
            $this->scheduleModelRetraining($prediction->model->model_type);
        }
    }

    /**
     * Analyze model performance and suggest improvements
     */
    public function analyzeModelPerformance(string $modelType = null): array
    {
        $query = MLModel::with(['predictions' => function($q) {
            $q->whereNotNull('actual_outcome');
        }]);
        
        if ($modelType) {
            $query->where('model_type', $modelType);
        }
        
        $models = $query->get();
        
        $analysis = [];
        foreach ($models as $model) {
            $predictions = $model->predictions;
            if ($predictions->isEmpty()) continue;
            
            $accuracy = $predictions->where('actual_outcome', true)->count() / $predictions->count();
            $avgConfidence = $predictions->avg('confidence');
            
            $analysis[$model->model_type] = [
                'model_id' => $model->id,
                'accuracy' => $accuracy,
                'average_confidence' => $avgConfidence,
                'total_predictions' => $predictions->count(),
                'last_trained' => $model->created_at,
                'performance_trend' => $this->calculatePerformanceTrend($predictions),
                'recommendations' => $this->getModelImprovementRecommendations($model, $accuracy),
            ];
        }
        
        return $analysis;
    }

    /**
     * A/B test model versions
     */
    public function startABTest(string $modelType, array $testConfig): array
    {
        // Create test variants
        $controlModel = MLModel::where('model_type', $modelType)
            ->where('status', 'active')
            ->first();
            
        if (!$controlModel) {
            throw new \Exception("No active model found for type: $modelType");
        }

        // Train variant model with different parameters
        $variantTrainingData = $this->getTrainingData($modelType);
        // Apply variant configuration (different features, parameters, etc.)
        $variantTrainingData = $this->applyVariantConfig($variantTrainingData, $testConfig);
        
        // Start A/B test
        $testId = $this->createABTest($controlModel, $variantTrainingData, $testConfig);
        
        return [
            'test_id' => $testId,
            'control_model_id' => $controlModel->id,
            'test_duration_days' => $testConfig['duration_days'] ?? 14,
            'traffic_split' => $testConfig['traffic_split'] ?? 50,
            'success_metrics' => $testConfig['success_metrics'] ?? ['accuracy', 'user_satisfaction'],
        ];
    }

    /**
     * Auto-optimize model hyperparameters
     */
    public function autoOptimizeModel(string $modelType): array
    {
        $bestParams = $this->findBestHyperparameters($modelType);
        
        // Retrain with optimized parameters
        $optimizedModel = $this->trainModel($modelType, true);
        
        return [
            'original_accuracy' => $this->getCurrentModelAccuracy($modelType),
            'optimized_accuracy' => $optimizedModel['accuracy'] ?? 'training',
            'best_parameters' => $bestParams,
            'improvement' => 'Training in progress',
        ];
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function shouldRetrain(string $modelType): bool
    {
        $model = MLModel::where('model_type', $modelType)
            ->where('status', 'active')
            ->first();

        if (!$model) {
            return true; // No model exists, need to train
        }

        // Check time since last training
        $hoursSinceTraining = now()->diffInHours($model->created_at);
        if ($hoursSinceTraining >= self::RETRAIN_INTERVAL_HOURS) {
            return true;
        }

        // Check if accuracy has degraded
        if ($model->accuracy < 0.7) {
            return true;
        }

        // Check if significant new data is available
        $newDataCount = BehavioralEvent::where('created_at', '>', $model->created_at)->count();
        $significantDataThreshold = max(100, $model->training_samples * 0.1);
        
        return $newDataCount >= $significantDataThreshold;
    }

    private function getTrainingData(string $modelType): array
    {
        $days = 90; // 3 months of data
        
        switch ($modelType) {
            case 'behavioral_prediction':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['behavioral', 'workflow', 'form_interaction']
                ]);
                
            case 'product_recommendation':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['product_selection', 'clinical_decision', 'user_profile']
                ]);
                
            case 'workflow_optimization':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['workflow', 'navigation', 'completion_patterns']
                ]);
                
            case 'form_optimization':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['form_interaction', 'validation_errors', 'completion_time']
                ]);
                
            case 'personalization':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['user_preferences', 'device_patterns', 'time_patterns']
                ]);
                
            case 'clinical_decision':
                return $this->dataPipeline->buildTrainingDataset([
                    'days' => $days,
                    'features' => ['clinical_decision', 'assessment_patterns', 'outcome_tracking']
                ]);
                
            default:
                throw new \InvalidArgumentException("Unknown model type: $modelType");
        }
    }

    private function performPrediction(MLModel $model, array $inputData): array
    {
        // This would typically call your ML service (Python, Azure ML, etc.)
        // For now, we'll simulate with rule-based predictions
        
        switch ($model->model_type) {
            case 'behavioral_prediction':
                return $this->predictBehavior($inputData);
                
            case 'product_recommendation':
                return $this->predictProducts($inputData);
                
            case 'workflow_optimization':
                return $this->predictWorkflowOptimization($inputData);
                
            case 'form_optimization':
                return $this->predictFormOptimization($inputData);
                
            case 'personalization':
                return $this->predictPersonalization($inputData);
                
            case 'clinical_decision':
                return $this->predictClinicalDecision($inputData);
                
            default:
                throw new \InvalidArgumentException("Unknown model type: {$model->model_type}");
        }
    }

    private function getFallbackPrediction(string $modelType, array $inputData): array
    {
        // Return safe default predictions when model is unavailable
        return [
            'prediction' => 'continue_current_workflow',
            'confidence' => 0.5,
            'reasoning' => 'Fallback prediction due to model unavailability',
            'is_fallback' => true,
        ];
    }

    private function combineRecommendations(array $predictions, array $userFeatures): array
    {
        return [
            'next_action' => $predictions['behavioral']['next_action'] ?? 'continue_workflow',
            'recommended_products' => $predictions['product']['recommended_products'] ?? [],
            'workflow_suggestions' => $predictions['workflow']['suggestions'] ?? [],
            'form_optimizations' => $predictions['form']['optimizations'] ?? [],
            'ui_personalizations' => $predictions['personalization']['ui_changes'] ?? [],
            'overall_confidence' => $this->calculateOverallConfidence($predictions),
            'reasoning' => $this->generateReasoningExplanation($predictions),
        ];
    }

    private function getPersonalizationHints(int $userId): array
    {
        $userFeatures = $this->dataPipeline->extractUserBehaviorFeatures($userId, 30);
        
        return [
            'preferred_workflow_steps' => $this->getPreferredWorkflowSteps($userFeatures),
            'optimal_form_layout' => $this->getOptimalFormLayout($userFeatures),
            'content_preferences' => $this->getContentPreferences($userFeatures),
            'timing_preferences' => $this->getTimingPreferences($userFeatures),
        ];
    }

    private function updateModelAccuracy(int $modelId, bool $wasAccurate): void
    {
        $model = MLModel::find($modelId);
        if (!$model) return;
        
        $totalPredictions = $model->predictions()->whereNotNull('actual_outcome')->count();
        $accuratePredictions = $model->predictions()->where('actual_outcome', true)->count();
        
        $newAccuracy = $totalPredictions > 0 ? $accuratePredictions / $totalPredictions : 0;
        
        $model->update([
            'accuracy' => $newAccuracy,
            'total_predictions' => $totalPredictions,
            'last_prediction_at' => now(),
        ]);
    }

    private function scheduleModelRetraining(string $modelType): void
    {
        Queue::later(now()->addMinutes(5), new TrainModelJob($modelType, [], null));
    }

    private function calculatePerformanceTrend(Collection $predictions): string
    {
        $recent = $predictions->where('created_at', '>=', now()->subDays(7));
        $older = $predictions->where('created_at', '<', now()->subDays(7));
        
        if ($recent->isEmpty() || $older->isEmpty()) {
            return 'insufficient_data';
        }
        
        $recentAccuracy = $recent->where('actual_outcome', true)->count() / $recent->count();
        $olderAccuracy = $older->where('actual_outcome', true)->count() / $older->count();
        
        $difference = $recentAccuracy - $olderAccuracy;
        
        if ($difference > 0.05) return 'improving';
        if ($difference < -0.05) return 'declining';
        return 'stable';
    }

    private function getModelImprovementRecommendations(MLModel $model, float $accuracy): array
    {
        $recommendations = [];
        
        if ($accuracy < 0.7) {
            $recommendations[] = 'Increase training data volume';
            $recommendations[] = 'Review feature engineering';
            $recommendations[] = 'Consider ensemble methods';
        }
        
        if ($model->predictions()->avg('confidence') < 0.6) {
            $recommendations[] = 'Improve confidence calibration';
            $recommendations[] = 'Add uncertainty estimation';
        }
        
        return $recommendations;
    }

    private function calculateOverallConfidence(array $predictions): float
    {
        $confidences = array_map(function($pred) {
            return $pred['confidence'] ?? 0.5;
        }, $predictions);
        
        return array_sum($confidences) / count($confidences);
    }

    private function generateReasoningExplanation(array $predictions): string
    {
        $reasons = [];
        
        foreach ($predictions as $type => $prediction) {
            if (isset($prediction['reasoning'])) {
                $reasons[] = $prediction['reasoning'];
            }
        }
        
        return implode(' ', $reasons);
    }

    // Placeholder prediction methods that would be replaced with actual ML calls
    private function predictBehavior(array $inputData): array 
    {
        return [
            'next_action' => 'form_fill',
            'confidence' => 0.8,
            'reasoning' => 'Based on recent form interaction patterns'
        ];
    }
    
    private function predictProducts(array $inputData): array 
    {
        return [
            'recommended_products' => ['product_1', 'product_2'],
            'confidence' => 0.75,
            'reasoning' => 'Based on similar user preferences'
        ];
    }
    
    private function predictWorkflowOptimization(array $inputData): array 
    {
        return [
            'suggestions' => ['skip_step_3', 'merge_steps_4_5'],
            'confidence' => 0.7,
            'reasoning' => 'Common workflow patterns suggest optimization'
        ];
    }
    
    private function predictFormOptimization(array $inputData): array 
    {
        return [
            'optimizations' => ['reorder_fields', 'add_autocomplete'],
            'confidence' => 0.8,
            'reasoning' => 'Form completion patterns indicate opportunities'
        ];
    }
    
    private function predictPersonalization(array $inputData): array 
    {
        return [
            'ui_changes' => ['compact_layout', 'hide_advanced_options'],
            'confidence' => 0.75,
            'reasoning' => 'User device and usage patterns'
        ];
    }
    
    private function predictClinicalDecision(array $inputData): array 
    {
        return [
            'clinical_suggestions' => ['wound_assessment_A', 'treatment_option_B'],
            'confidence' => 0.9,
            'reasoning' => 'Clinical decision patterns and outcomes'
        ];
    }
    
    // Additional helper methods would be implemented here...
    private function applyVariantConfig(array $trainingData, array $testConfig): array { return $trainingData; }
    private function createABTest(MLModel $controlModel, array $variantData, array $testConfig): string { return 'test_' . uniqid(); }
    private function findBestHyperparameters(string $modelType): array { return []; }
    private function getCurrentModelAccuracy(string $modelType): float { return 0.8; }
    private function getPreferredWorkflowSteps(array $userFeatures): array { return []; }
    private function getOptimalFormLayout(array $userFeatures): array { return []; }
    private function getContentPreferences(array $userFeatures): array { return []; }
    private function getTimingPreferences(array $userFeatures): array { return []; }

    /**
     * Train ML models with provided training data
     */
    public function trainModels(array $trainingData): void
    {
        try {
            // Process training data for different model types
            $this->trainFormOptimizationModels($trainingData);
            $this->trainFieldMappingModels($trainingData);
            $this->trainPersonalizationModels($trainingData);
            $this->trainWorkflowOptimizationModels($trainingData);
            
            // Update model performance metrics
            $this->updateModelPerformanceMetrics();
            
        } catch (\Exception $e) {
            // Log training error but don't throw to avoid breaking the ingestion process
            Log::error('ML model training failed', [
                'error' => $e->getMessage(),
                'training_data_count' => count($trainingData['dataset'] ?? [])
            ]);
        }
    }

    /**
     * Train form optimization models
     */
    private function trainFormOptimizationModels(array $trainingData): void
    {
        // Extract form completion patterns
        $formData = $this->extractFormCompletionPatterns($trainingData);
        
        // Train model for form field ordering
        $this->trainStackedModel('form_optimization', $formData);
    }

    /**
     * Train field mapping models
     */
    private function trainFieldMappingModels(array $trainingData): void
    {
        // Extract field mapping success patterns
        $mappingData = $this->extractMappingSuccessPatterns($trainingData);
        
        // Train model for field mapping accuracy
        $this->trainStackedModel('field_mapping', $mappingData);
    }

    /**
     * Train personalization models
     */
    private function trainPersonalizationModels(array $trainingData): void
    {
        // Extract user preference patterns
        $personalizationData = $this->extractPersonalizationPatterns($trainingData);
        
        // Train model for user personalization
        $this->trainStackedModel('personalization', $personalizationData);
    }

    /**
     * Train workflow optimization models
     */
    private function trainWorkflowOptimizationModels(array $trainingData): void
    {
        // Extract workflow efficiency patterns
        $workflowData = $this->extractWorkflowPatterns($trainingData);
        
        // Train model for workflow optimization
        $this->trainStackedModel('workflow_optimization', $workflowData);
    }

    /**
     * Update model performance metrics
     */
    private function updateModelPerformanceMetrics(): void
    {
        // Update performance metrics for monitoring
        Cache::put('ml_model_training_timestamp', now(), 3600);
        Cache::put('ml_model_training_status', 'completed', 3600);
    }

    // Helper methods for training
    private function extractFormCompletionPatterns(array $trainingData): array { return []; }
    private function extractMappingSuccessPatterns(array $trainingData): array { return []; }
    private function extractPersonalizationPatterns(array $trainingData): array { return []; }
    private function extractWorkflowPatterns(array $trainingData): array { return []; }
} 