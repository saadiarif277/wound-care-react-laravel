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
     * Send episode to manufacturer via email
     */
    public function sendEpisodeToManufacturer($episode, array $emailData)
    {
        try {
            // Prepare episode details
            $episodeDetails = [
                'episode_id' => $episode->id,
                'manufacturer' => $episode->manufacturer->name ?? 'Unknown Manufacturer',
                'provider_count' => $emailData['orders']->pluck('provider_id')->unique()->count(),
                'order_count' => $emailData['orders']->count(),
                'total_value' => $emailData['orders']->sum('total_order_value'),
                'orders' => $emailData['orders']->map(function($order) {
                    return [
                        'order_number' => $order->request_number,
                        'patient_display_id' => $order->patient_display_id,
                        'service_date' => $order->expected_service_date?->format('Y-m-d') ?? $order->date_of_service,
                        'products' => $order->products->map(function($product) {
                            return [
                                'name' => $product->name,
                                'sku' => $product->sku,
                                'quantity' => $product->pivot->quantity ?? 1,
                                'size' => $product->pivot->size ?? null,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
                'notes' => $emailData['notes'],
                'sent_by' => $emailData['sent_by'],
                'sent_at' => $emailData['sent_at'],
            ];

            // Prepare attachments (IVR documents)
            $attachments = [];
            if ($emailData['include_ivr'] && $episode->docuseal_submission_id) {
                // Fetch IVR document from Docuseal
                    $docusealService = app(\App\Services\DocusealService::class);
        try {
            $submissionStatus = $docusealService->getSubmissionStatus($episode->docuseal_submission_id);
            $ivrDocument = $submissionStatus['completed'] ?? false ? $submissionStatus : null;
            if ($ivrDocument) {
                $attachments[] = [
                    'path' => $ivrDocument['url'],
                    'name' => 'IVR_Document.pdf',
                    'mime' => 'application/pdf'
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch IVR document from Docuseal', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
        }
                // $attachments[] = $this->getDocusealDocument($episode->docuseal_submission_id);
            }

            // Log the email sending
            Log::info('Episode email prepared for manufacturer', [
                'episode_id' => $episode->id,
                'recipients' => $emailData['recipients'],
                'manufacturer' => $episode->manufacturer->name ?? 'Unknown',
                'order_count' => count($episodeDetails['orders']),
            ]);

            // Send the email
            Mail::to($emailData['recipients'])
                ->send(new ManufacturerOrderEmail($episodeDetails, $attachments));

            // Log successful email send
            Log::info('Episode email sent to manufacturer', [
                'episode_id' => $episode->id,
                'recipients' => $emailData['recipients'],
                'attachments_count' => count($attachments),
            ]);

            return [
                'success' => true,
                'message' => 'Episode successfully sent to manufacturer',
                'recipients' => $emailData['recipients'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send episode to manufacturer', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send episode: ' . $e->getMessage(),
            ];
        }
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
