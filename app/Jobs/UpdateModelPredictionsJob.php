<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Learning\ModelPrediction;
use App\Models\Learning\MLModel;
use Illuminate\Support\Facades\Log;

class UpdateModelPredictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $predictionId;
    protected bool $wasAccurate;
    protected array $feedback;

    /**
     * Create a new job instance.
     */
    public function __construct(int $predictionId, bool $wasAccurate, array $feedback = [])
    {
        $this->predictionId = $predictionId;
        $this->wasAccurate = $wasAccurate;
        $this->feedback = $feedback;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $prediction = ModelPrediction::find($this->predictionId);
            
            if (!$prediction) {
                Log::warning('Prediction not found for feedback update', [
                    'prediction_id' => $this->predictionId
                ]);
                return;
            }

            // Update prediction with feedback
            $prediction->update([
                'actual_outcome' => $this->wasAccurate,
                'user_feedback' => $this->feedback,
                'feedback_received_at' => now(),
            ]);

            // Update model accuracy metrics
            $this->updateModelAccuracy($prediction->model);

            // Log the feedback
            Log::info('Model prediction feedback processed', [
                'prediction_id' => $this->predictionId,
                'model_id' => $prediction->model_id,
                'model_type' => $prediction->model->model_type,
                'was_accurate' => $this->wasAccurate,
                'confidence' => $prediction->confidence,
                'feedback_keys' => array_keys($this->feedback),
            ]);

            // Check if model needs retraining due to poor performance
            if ($this->shouldTriggerRetraining($prediction->model)) {
                $this->scheduleModelRetraining($prediction->model);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update model prediction', [
                'prediction_id' => $this->predictionId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Update model accuracy based on all predictions with feedback
     */
    private function updateModelAccuracy(MLModel $model): void
    {
        $predictions = $model->predictions()->whereNotNull('actual_outcome')->get();
        
        if ($predictions->isEmpty()) {
            return;
        }

        $totalPredictions = $predictions->count();
        $accuratePredictions = $predictions->where('actual_outcome', true)->count();
        $newAccuracy = $accuratePredictions / $totalPredictions;

        // Calculate recent accuracy (last 7 days)
        $recentPredictions = $predictions->where('created_at', '>=', now()->subDays(7));
        $recentAccuracy = $recentPredictions->isEmpty() ? null : 
            $recentPredictions->where('actual_outcome', true)->count() / $recentPredictions->count();

        // Update model with new metrics
        $model->update([
            'accuracy' => $newAccuracy,
            'total_predictions' => $totalPredictions,
            'last_prediction_at' => now(),
            'performance_metrics' => array_merge(
                $model->performance_metrics ?? [],
                [
                    'recent_accuracy' => $recentAccuracy,
                    'confidence_avg' => $predictions->avg('confidence'),
                    'last_updated' => now()->toISOString(),
                ]
            )
        ]);

        Log::info('Model accuracy updated', [
            'model_id' => $model->id,
            'model_type' => $model->model_type,
            'accuracy' => $newAccuracy,
            'recent_accuracy' => $recentAccuracy,
            'total_predictions' => $totalPredictions,
        ]);
    }

    /**
     * Check if model needs retraining based on performance
     */
    private function shouldTriggerRetraining(MLModel $model): bool
    {
        // Get recent predictions for performance assessment
        $recentPredictions = $model->predictions()
            ->whereNotNull('actual_outcome')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        // Need minimum sample size for reliable assessment
        if ($recentPredictions->count() < 20) {
            return false;
        }

        $recentAccuracy = $recentPredictions->where('actual_outcome', true)->count() / $recentPredictions->count();

        // Trigger retraining if accuracy drops below threshold
        if ($recentAccuracy < 0.6) {
            Log::warning('Model performance degraded, scheduling retraining', [
                'model_id' => $model->id,
                'model_type' => $model->model_type,
                'recent_accuracy' => $recentAccuracy,
                'threshold' => 0.6,
            ]);
            return true;
        }

        // Check if confidence is consistently low
        $avgConfidence = $recentPredictions->avg('confidence');
        if ($avgConfidence < 0.5) {
            Log::warning('Model confidence low, scheduling retraining', [
                'model_id' => $model->id,
                'model_type' => $model->model_type,
                'avg_confidence' => $avgConfidence,
                'threshold' => 0.5,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Schedule model retraining
     */
    private function scheduleModelRetraining(MLModel $model): void
    {
        // Dispatch training job with delay to allow for more data collection
        TrainModelJob::dispatch($model->model_type, [], null)
            ->delay(now()->addMinutes(30));

        Log::info('Model retraining scheduled', [
            'model_id' => $model->id,
            'model_type' => $model->model_type,
            'scheduled_for' => now()->addMinutes(30)->toISOString(),
        ]);
    }
} 