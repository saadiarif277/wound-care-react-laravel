<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ManufacturerOrderEmail;

class ManufacturerEmailService
{
    /**
     * Send order to manufacturer via email
     */
    public function sendOrderToManufacturer(ProductRequest $order, array $recipients, $attachments = [])
    {
        try {
            // Prepare order details
            $orderDetails = $this->prepareOrderDetails($order);
            
            // Log the email sending (for now, we're not actually sending emails)
            Log::info('Order email prepared for manufacturer', [
                'order_id' => $order->id,
                'order_number' => $order->request_number,
                'recipients' => $recipients,
                'manufacturer' => $orderDetails['manufacturer'],
                'products' => $orderDetails['products'],
            ]);

            // Send the email
            Mail::to($recipients)
                ->send(new ManufacturerOrderEmail($orderDetails, $attachments));
            
            // Log successful email send
            Log::info('Order email sent to manufacturer', [
                'order_id' => $order->id,
                'recipients' => $recipients,
                'attachments_count' => count($attachments),
            ]);

            return [
                'success' => true,
                'message' => 'Order successfully sent to manufacturer',
                'recipients' => $recipients,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send order to manufacturer', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send order: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare order details for email
     */
    private function prepareOrderDetails(ProductRequest $order): array
    {
        return [
            'order_number' => $order->request_number,
            'submitted_date' => $order->created_at->format('Y-m-d H:i:s'),
            'service_date' => $order->expected_service_date?->format('Y-m-d') ?? $order->date_of_service,
            'patient' => [
                'display_id' => $order->patient_display_id,
                'name' => 'Protected - See IVR Document', // We don't expose patient names in emails
            ],
            'provider' => [
                'name' => $order->provider->full_name,
                'npi' => $order->provider->npi_number,
                'email' => $order->provider->email,
                'phone' => $order->provider->phone,
            ],
            'facility' => [
                'name' => $order->facility->name,
                'address' => $order->facility->address,
                'city' => $order->facility->city,
                'state' => $order->facility->state,
                'zip' => $order->facility->zip_code,
                'phone' => $order->facility->phone,
            ],
            'products' => $order->products->map(function($product) {
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $product->pivot->quantity ?? 1,
                    'size' => $product->pivot->size ?? null,
                ];
            })->toArray(),
            'manufacturer' => $order->getManufacturer(),
            'ivr_status' => $order->docuseal_submission_id ? 'Completed' : 'Pending',
            'notes' => $order->clinical_notes,
        ];
    }

    /**
     * Get default recipients for a manufacturer
     */
    public function getDefaultRecipients($manufacturerName): array
    {
        // Get from configuration instead of hardcoding
        $defaultRecipients = config('manufacturers.email_recipients', []);
        
        return $defaultRecipients[$manufacturerName] ?? [];
    }
}