<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use Illuminate\Support\Facades\Log;

/**
 * Order Status Service
 *
 * Handles order status transitions and automated workflows
 * Ensures proper state management for medical distribution processes
 */
class OrderStatusService
{
    protected FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Transition order to next status with validation
     */
    public function transitionStatus(ProductRequest $order, string $newStatus, ?int $userId = null): array
    {
        $currentStatus = $order->order_status;

        // Validate status transition
        if (!$this->isValidTransition($currentStatus, $newStatus)) {
            return [
                'success' => false,
                'error' => "Invalid status transition from {$currentStatus} to {$newStatus}",
            ];
        }

        // Perform pre-transition actions
        $preTransitionResult = $this->performPreTransitionActions($order, $newStatus);
        if (!$preTransitionResult['success']) {
            return $preTransitionResult;
        }

        // Update order status
        $order->update([
            'order_status' => $newStatus,
            'ivr_status' => $this->mapOrderStatusToIvrStatus($newStatus),
        ]);

        // Perform post-transition actions
        $this->performPostTransitionActions($order, $newStatus, $userId);

        Log::info('Order status transitioned', [
            'order_id' => $order->id,
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'order' => $order,
            'transition' => [
                'from' => $currentStatus,
                'to' => $newStatus,
            ],
        ];
    }

    /**
     * Validate if status transition is allowed
     */
    private function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'pending' => ['submitted_to_manufacturer', 'rejected', 'canceled'],
            'submitted_to_manufacturer' => ['confirmed_by_manufacturer', 'rejected'],
            'confirmed_by_manufacturer' => ['completed'],
            'rejected' => [], // Terminal state
            'canceled' => [], // Terminal state
            'completed' => [], // Terminal state
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Perform actions before status transition
     */
    private function performPreTransitionActions(ProductRequest $order, string $newStatus): array
    {
        switch ($newStatus) {
            case 'submitted_to_manufacturer':
                return $this->validateForManufacturerSubmission($order);
            case 'confirmed_by_manufacturer':
                return $this->validateForManufacturerConfirmation($order);
            default:
                return ['success' => true];
        }
    }

    /**
     * Perform actions after status transition
     */
    private function performPostTransitionActions(ProductRequest $order, string $newStatus, ?int $userId = null): void
    {
        switch ($newStatus) {
            case 'submitted_to_manufacturer':
                $this->handleManufacturerSubmission($order, $userId);
                break;
            case 'confirmed_by_manufacturer':
                $this->handleManufacturerConfirmation($order, $userId);
                break;
        }
    }

    /**
     * Validate order before manufacturer submission
     */
    private function validateForManufacturerSubmission(ProductRequest $order): array
    {
        $errors = [];

        // Check if IVR document exists
        $files = $this->fileService->getOrderFiles($order);
        if (empty($files['ivr']['active_url'])) {
            $errors[] = 'IVR document is required for manufacturer submission';
        }

        // Check if all required fields are present
        if (empty($order->provider_id)) {
            $errors[] = 'Provider information is required';
        }

        if (empty($order->facility_id)) {
            $errors[] = 'Facility information is required';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => 'Validation failed: ' . implode(', ', $errors),
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate order before manufacturer confirmation
     */
    private function validateForManufacturerConfirmation(ProductRequest $order): array
    {
        // Check if order form has been uploaded/updated
        $files = $this->fileService->getOrderFiles($order);
        if (empty($files['order_form']['has_upload'])) {
            return [
                'success' => false,
                'error' => 'Order form must be updated before manufacturer confirmation',
            ];
        }

        return ['success' => true];
    }

    /**
     * Handle manufacturer submission workflow
     */
    private function handleManufacturerSubmission(ProductRequest $order, ?int $userId = null): void
    {
        // Auto-generate IVR if not present
        $files = $this->fileService->getOrderFiles($order);
        if (empty($files['ivr']['active_url'])) {
            // Trigger IVR generation workflow
            Log::info('IVR generation needed for order', ['order_id' => $order->id]);
        }

        // Send notification to manufacturer
        // This would integrate with manufacturer notification service
    }

    /**
     * Handle manufacturer confirmation workflow
     */
    private function handleManufacturerConfirmation(ProductRequest $order, ?int $userId = null): void
    {
        // Update tracking information
        if ($order->carrier && $order->tracking_number) {
            Log::info('Order confirmed with tracking', [
                'order_id' => $order->id,
                'carrier' => $order->carrier,
                'tracking' => $order->tracking_number,
            ]);
        }

        // Trigger shipping workflow
        // This would integrate with shipping service
    }

    /**
     * Map order status to IVR status
     */
    private function mapOrderStatusToIvrStatus(string $orderStatus): string
    {
        $statusMapping = [
            'pending' => 'draft',
            'submitted_to_manufacturer' => 'pending_ivr',
            'confirmed_by_manufacturer' => 'approved',
            'rejected' => 'rejected',
            'canceled' => 'canceled',
            'completed' => 'completed',
        ];

        return $statusMapping[$orderStatus] ?? 'draft';
    }

    /**
     * Get available status transitions for an order
     */
    public function getAvailableTransitions(ProductRequest $order): array
    {
        $currentStatus = $order->order_status;

        $validTransitions = [
            'pending' => ['submitted_to_manufacturer', 'rejected', 'canceled'],
            'submitted_to_manufacturer' => ['confirmed_by_manufacturer', 'rejected'],
            'confirmed_by_manufacturer' => ['completed'],
        ];

        return $validTransitions[$currentStatus] ?? [];
    }
}
