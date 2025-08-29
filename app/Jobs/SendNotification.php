<?php

namespace App\Jobs;

use App\Models\ProductRequest;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // 1 minute delay between retries

    protected ProductRequest $order;
    protected string $type;
    protected array $params;

    /**
     * Create a new job instance.
     */
    public function __construct(ProductRequest $order, string $type, array $params = [])
    {
        $this->order = $order;
        $this->type = $type;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('Processing notification job', [
            'order_id' => $this->order->id,
            'type' => $this->type,
        ]);

        $success = match($this->type) {
            'ivr_to_manufacturer' => $this->sendIvrToManufacturer($notificationService),
            'provider_notification' => $this->notifyProvider($notificationService),
            'order_approval' => $this->sendOrderApprovalConfirmation($notificationService),
            'manufacturer_submission' => $this->sendManufacturerSubmissionConfirmation($notificationService),
            default => false,
        };

        if (!$success) {
            Log::error('Notification job failed', [
                'order_id' => $this->order->id,
                'type' => $this->type,
            ]);

            throw new \Exception("Failed to send {$this->type} notification");
        }

        Log::info('Notification job completed successfully', [
            'order_id' => $this->order->id,
            'type' => $this->type,
        ]);
    }

    /**
     * Send IVR to manufacturer
     */
    private function sendIvrToManufacturer(NotificationService $notificationService): bool
    {
        return $notificationService->sendImmediateNotification(
            $this->order,
            'ivr_to_manufacturer',
            ['ivr_path' => $this->params['ivr_path'] ?? null]
        );
    }

    /**
     * Notify provider
     */
    private function notifyProvider(NotificationService $notificationService): bool
    {
        return $notificationService->sendImmediateNotification(
            $this->order,
            'provider_notification',
            [
                'status' => $this->params['status'],
                'message' => $this->params['message'] ?? null
            ]
        );
    }

    /**
     * Send order approval confirmation
     */
    private function sendOrderApprovalConfirmation(NotificationService $notificationService): bool
    {
        return $notificationService->sendImmediateNotification(
            $this->order,
            'order_approval'
        );
    }

    /**
     * Send manufacturer submission confirmation
     */
    private function sendManufacturerSubmissionConfirmation(NotificationService $notificationService): bool
    {
        return $notificationService->sendImmediateNotification(
            $this->order,
            'manufacturer_submission'
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed permanently', [
            'order_id' => $this->order->id,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);

        // Could send alert to admin or take other action
    }
}
