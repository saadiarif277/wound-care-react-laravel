<?php

namespace App\Services;

use App\Models\ProductRequest;
use App\Models\Users\Provider\ProviderProfile;
use App\Models\Organization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\IvrSubmissionToManufacturer;
use App\Mail\ProviderNotification;
use App\Mail\OrderApprovalConfirmation;
use App\Mail\ManufacturerSubmissionConfirmation;
use App\Jobs\SendNotification;

class NotificationService
{
    /**
     * Send IVR PDF to manufacturer
     */
    public function sendIvrToManufacturer(ProductRequest $order, string $ivrPdfPath = null): bool
    {
        try {
            $manufacturer = $order->manufacturer;
            if (!$manufacturer || !$manufacturer->email) {
                Log::warning('No manufacturer email found for order', ['order_id' => $order->id]);
                return false;
            }

            // Get IVR PDF from storage or generate if not provided
            $pdfPath = $ivrPdfPath ?: $this->generateIvrPdf($order);

            if (!$pdfPath || !Storage::exists($pdfPath)) {
                Log::error('IVR PDF not found', ['order_id' => $order->id, 'path' => $pdfPath]);
                return false;
            }

            // Queue the email for processing
            SendNotification::dispatch($order, 'ivr_to_manufacturer', [
                'ivr_path' => $pdfPath
            ]);

            Log::info('IVR notification queued for manufacturer', [
                'order_id' => $order->id,
                'manufacturer_email' => $manufacturer->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to queue IVR to manufacturer', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Notify provider of order status change
     */
    public function notifyProvider(ProductRequest $order, string $status, string $message = null): bool
    {
        try {
            $provider = $order->provider;
            if (!$provider || !$provider->email) {
                Log::warning('No provider email found for order', ['order_id' => $order->id]);
                return false;
            }

            // Queue the notification
            SendNotification::dispatch($order, 'provider_notification', [
                'status' => $status,
                'message' => $message
            ]);

            Log::info('Provider notification queued', [
                'order_id' => $order->id,
                'provider_email' => $provider->email,
                'status' => $status
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to queue provider notification', [
                'order_id' => $order->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send order approval confirmation
     */
    public function sendOrderApprovalConfirmation(ProductRequest $order): bool
    {
        try {
            $provider = $order->provider;
            if (!$provider || !$provider->email) {
                Log::warning('No provider email found for order approval', ['order_id' => $order->id]);
                return false;
            }

            // Queue the confirmation
            SendNotification::dispatch($order, 'order_approval');

            Log::info('Order approval confirmation queued', [
                'order_id' => $order->id,
                'provider_email' => $provider->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to queue order approval confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send manufacturer submission confirmation
     */
    public function sendManufacturerSubmissionConfirmation(ProductRequest $order): bool
    {
        try {
            $manufacturer = $order->manufacturer;
            if (!$manufacturer || !$manufacturer->email) {
                Log::warning('No manufacturer email found for submission confirmation', ['order_id' => $order->id]);
                return false;
            }

            // Queue the confirmation
            SendNotification::dispatch($order, 'manufacturer_submission');

            Log::info('Manufacturer submission confirmation queued', [
                'order_id' => $order->id,
                'manufacturer_email' => $manufacturer->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to queue manufacturer submission confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send bulk notifications for multiple orders
     */
    public function sendBulkNotifications(array $notifications): array
    {
        $results = [];

        foreach ($notifications as $notification) {
            $type = $notification['type'];
            $order = $notification['order'];
            $params = $notification['params'] ?? [];

            switch ($type) {
                case 'ivr_to_manufacturer':
                    $results[] = $this->sendIvrToManufacturer($order, $params['ivr_path'] ?? null);
                    break;
                case 'provider_notification':
                    $results[] = $this->notifyProvider($order, $params['status'], $params['message'] ?? null);
                    break;
                case 'order_approval':
                    $results[] = $this->sendOrderApprovalConfirmation($order);
                    break;
                case 'manufacturer_submission':
                    $results[] = $this->sendManufacturerSubmissionConfirmation($order);
                    break;
            }
        }

        return $results;
    }

    /**
     * Send immediate notification (not queued)
     */
    public function sendImmediateNotification(ProductRequest $order, string $type, array $params = []): bool
    {
        try {
            switch ($type) {
                case 'ivr_to_manufacturer':
                    return $this->sendIvrToManufacturerImmediate($order, $params['ivr_path'] ?? null);
                case 'provider_notification':
                    return $this->notifyProviderImmediate($order, $params['status'], $params['message'] ?? null);
                case 'order_approval':
                    return $this->sendOrderApprovalConfirmationImmediate($order);
                case 'manufacturer_submission':
                    return $this->sendManufacturerSubmissionConfirmationImmediate($order);
                default:
                    Log::warning('Unknown notification type', ['type' => $type]);
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send immediate notification', [
                'order_id' => $order->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send IVR to manufacturer immediately
     */
    private function sendIvrToManufacturerImmediate(ProductRequest $order, string $ivrPdfPath = null): bool
    {
        $manufacturer = $order->manufacturer;
        if (!$manufacturer || !$manufacturer->email) {
            return false;
        }

        $pdfPath = $ivrPdfPath ?: $this->generateIvrPdf($order);
        if (!$pdfPath || !Storage::exists($pdfPath)) {
            return false;
        }

        Mail::to($manufacturer->email)
            ->cc($manufacturer->secondary_email ?? null)
            ->send(new IvrSubmissionToManufacturer($order, $pdfPath));

        Log::info('IVR sent to manufacturer successfully', [
            'order_id' => $order->id,
            'manufacturer_email' => $manufacturer->email
        ]);

        return true;
    }

    /**
     * Notify provider immediately
     */
    private function notifyProviderImmediate(ProductRequest $order, string $status, string $message = null): bool
    {
        $provider = $order->provider;
        if (!$provider || !$provider->email) {
            return false;
        }

        Mail::to($provider->email)
            ->send(new ProviderNotification($order, $status, $message));

        Log::info('Provider notification sent successfully', [
            'order_id' => $order->id,
            'provider_email' => $provider->email,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Send order approval confirmation immediately
     */
    private function sendOrderApprovalConfirmationImmediate(ProductRequest $order): bool
    {
        $provider = $order->provider;
        if (!$provider || !$provider->email) {
            return false;
        }

        Mail::to($provider->email)
            ->send(new OrderApprovalConfirmation($order));

        Log::info('Order approval confirmation sent', [
            'order_id' => $order->id,
            'provider_email' => $provider->email
        ]);

        return true;
    }

    /**
     * Send manufacturer submission confirmation immediately
     */
    private function sendManufacturerSubmissionConfirmationImmediate(ProductRequest $order): bool
    {
        $manufacturer = $order->manufacturer;
        if (!$manufacturer || !$manufacturer->email) {
            return false;
        }

        Mail::to($manufacturer->email)
            ->cc($manufacturer->secondary_email ?? null)
            ->send(new ManufacturerSubmissionConfirmation($order));

        Log::info('Manufacturer submission confirmation sent', [
            'order_id' => $order->id,
            'manufacturer_email' => $manufacturer->email
        ]);

        return true;
    }

    /**
     * Generate IVR PDF (placeholder - implement based on your IVR generation logic)
     */
    private function generateIvrPdf(ProductRequest $order): ?string
    {
        // This should integrate with your existing IVR generation service
        // For now, return a placeholder path
        return "ivr/{$order->id}/insurance_verification.pdf";
    }
}
