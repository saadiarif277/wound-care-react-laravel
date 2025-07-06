<?php

namespace App\Services;

use App\Models\Order\OrderStatusChange;
use App\Models\Order\ProductRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

class StatusChangeService
{
    protected EmailNotificationService $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Change order status and trigger notifications
     */
    public function changeOrderStatus(
        ProductRequest $order,
        string $newStatus,
        ?string $notes = null,
        ?array $metadata = null
    ): bool {
        try {
            $previousStatus = $order->order_status ?? 'none';
            $changedBy = $this->getChangedBy();

            // Update the order status
            $order->update(['order_status' => $newStatus]);

            // Log the status change
            $this->logStatusChange($order, $previousStatus, $newStatus, $changedBy, $notes, $metadata);

            // Send email notification if status changed
            if ($previousStatus !== $newStatus) {
                $this->emailService->sendStatusChangeNotification(
                    $order,
                    $previousStatus,
                    $newStatus,
                    $changedBy,
                    $notes,
                    $metadata['notification_documents'] ?? null
                );
            }

            Log::info('Order status changed successfully', [
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to change order status', [
                'order_id' => $order->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Log status change
     */
    private function logStatusChange(
        ProductRequest $order,
        ?string $previousStatus,
        string $newStatus,
        string $changedBy,
        ?string $notes = null,
        ?array $metadata = null
    ): void {
        OrderStatusChange::create([
            'order_id' => $order->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get who made the change
     */
    private function getChangedBy(): string
    {
        if (Auth::check()) {
            $user = Auth::user();
            return $user->name ?? $user->email ?? 'System';
        }

        return 'System';
    }

    /**
     * Get status change history for an order
     */
    public function getStatusHistory(ProductRequest $order, int $limit = 10): array
    {
        $changes = OrderStatusChange::forOrder($order->id)
            ->with('changedByUser')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $changes->map(function ($change) {
            return [
                'id' => $change->id,
                'previous_status' => $change->previous_status,
                'new_status' => $change->new_status,
                'changed_by' => $change->changedByUser ? $change->changedByUser->name : $change->changed_by,
                'notes' => $change->notes,
                'created_at' => $change->created_at->toISOString(),
                'is_significant' => $change->isSignificantChange(),
                'description' => $change->getStatusChangeDescription(),
            ];
        })->toArray();
    }

    /**
     * Get recent status changes across all orders
     */
    public function getRecentStatusChanges(int $limit = 20): array
    {
        $changes = OrderStatusChange::with(['order', 'changedByUser'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $changes->map(function ($change) {
            return [
                'id' => $change->id,
                'order_id' => $change->order_id,
                'order_number' => $change->order->request_number ?? $change->order->id,
                'patient_name' => $change->order->patient_display_id ?? 'Unknown Patient',
                'previous_status' => $change->previous_status,
                'new_status' => $change->new_status,
                'changed_by' => $change->changedByUser ? $change->changedByUser->name : $change->changed_by,
                'notes' => $change->notes,
                'created_at' => $change->created_at->toISOString(),
                'is_significant' => $change->isSignificantChange(),
                'description' => $change->getStatusChangeDescription(),
            ];
        })->toArray();
    }

    /**
     * Get status change statistics
     */
    public function getStatusChangeStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $changes = OrderStatusChange::where('created_at', '>=', $startDate)->get();

        $stats = [
            'total_changes' => $changes->count(),
            'significant_changes' => $changes->filter(fn($c) => $c->isSignificantChange())->count(),
            'changes_by_status' => [],
            'changes_by_user' => [],
            'daily_changes' => [],
        ];

        // Group by new status
        foreach ($changes as $change) {
            $newStatus = $change->new_status;
            if (!isset($stats['changes_by_status'][$newStatus])) {
                $stats['changes_by_status'][$newStatus] = 0;
            }
            $stats['changes_by_status'][$newStatus]++;
        }

        // Group by user
        foreach ($changes as $change) {
            $user = $change->changed_by;
            if (!isset($stats['changes_by_user'][$user])) {
                $stats['changes_by_user'][$user] = 0;
            }
            $stats['changes_by_user'][$user]++;
        }

        // Group by day
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayChanges = $changes->filter(fn($c) => $c->created_at->format('Y-m-d') === $date);
            $stats['daily_changes'][$date] = $dayChanges->count();
        }

        return $stats;
    }

    /**
     * Approve order
     */
    public function approveOrder(ProductRequest $order, ?string $notes = null): bool
    {
        return $this->changeOrderStatus($order, 'approved', $notes, [
            'action' => 'approval',
            'approved_at' => now()->toISOString(),
        ]);
    }

    /**
     * Deny order
     */
    public function denyOrder(ProductRequest $order, ?string $notes = null): bool
    {
        return $this->changeOrderStatus($order, 'denied', $notes, [
            'action' => 'denial',
            'denied_at' => now()->toISOString(),
        ]);
    }

    /**
     * Send order back for revision
     */
    public function sendOrderBack(ProductRequest $order, ?string $notes = null): bool
    {
        return $this->changeOrderStatus($order, 'sent_back', $notes, [
            'action' => 'revision_request',
            'sent_back_at' => now()->toISOString(),
        ]);
    }

    /**
     * Mark order as shipped
     */
    public function markOrderShipped(ProductRequest $order, ?string $trackingNumber = null): bool
    {
        $metadata = [
            'action' => 'shipping',
            'shipped_at' => now()->toISOString(),
        ];

        if ($trackingNumber) {
            $metadata['tracking_number'] = $trackingNumber;
        }

        return $this->changeOrderStatus($order, 'shipped', null, $metadata);
    }

    /**
     * Mark order as delivered
     */
    public function markOrderDelivered(ProductRequest $order): bool
    {
        return $this->changeOrderStatus($order, 'delivered', null, [
            'action' => 'delivery',
            'delivered_at' => now()->toISOString(),
        ]);
    }

    /**
     * Cancel order
     */
    public function cancelOrder(ProductRequest $order, ?string $notes = null): bool
    {
        return $this->changeOrderStatus($order, 'cancelled', $notes, [
            'action' => 'cancellation',
            'cancelled_at' => now()->toISOString(),
        ]);
    }

    /**
     * Submit order to manufacturer
     */
    public function submitToManufacturer(ProductRequest $order, ?string $notes = null): bool
    {
        return $this->changeOrderStatus($order, 'submitted_to_manufacturer', $notes, [
            'action' => 'manufacturer_submission',
            'submitted_at' => now()->toISOString(),
        ]);
    }
}
