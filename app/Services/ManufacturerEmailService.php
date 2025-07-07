<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Services\SmartEmailSender;
use App\Services\AzureCommunicationsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ManufacturerOrderEmail;

class ManufacturerEmailService
{
    private SmartEmailSender $smartEmailSender;
    private AzureCommunicationsService $azureCommunications;

    public function __construct(
        SmartEmailSender $smartEmailSender,
        AzureCommunicationsService $azureCommunications
    ) {
        $this->smartEmailSender = $smartEmailSender;
        $this->azureCommunications = $azureCommunications;
    }

    /**
     * Send order to manufacturer via email using Smart Email Sender
     */
    public function sendOrderToManufacturer(ProductRequest $order, array $recipients, $attachments = [])
    {
        try {
            // Prepare order details
            $orderDetails = $this->prepareOrderDetails($order);
            $manufacturerName = $orderDetails['manufacturer'];

            // Prepare email content
            $subject = "New Order Request - {$orderDetails['order_number']} - {$orderDetails['patient']['display_id']}";
            $emailContent = $this->generateEmailContent($orderDetails);

            // Send using Smart Email Sender with manufacturer context
            $result = $this->smartEmailSender->sendManufacturerEmail(
                $manufacturerName,
                $recipients,
                $subject,
                $emailContent,
                $attachments
            );

            if ($result['success']) {
                Log::info('Order email sent to manufacturer via Smart Email Sender', [
                    'order_id' => $order->id,
                    'recipients' => $result['recipients'],
                    'sender_used' => $result['sender']->email_address ?? 'unknown',
                    'method' => $result['method'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Order successfully sent to manufacturer',
                    'recipients' => $result['recipients'],
                    'sender_used' => $result['sender']->email_address ?? null,
                    'method' => $result['method'],
                ];
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error from Smart Email Sender');
            }

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
            'ivr_status' => $order->pdf_document_id ? 'Completed' : 'Pending',
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
            // TODO: Implement new form service IVR document retrieval
            if ($emailData['include_ivr']) {
                Log::info('IVR document inclusion requested but form service not yet implemented', [
                    'episode_id' => $episode->id
                ]);
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

    /**
     * Generate email content from order details
     */
    private function generateEmailContent(array $orderDetails): string
    {
        return view('emails.manufacturer-order-text', [
            'order' => $orderDetails,
        ])->render();
    }

    /**
     * Send order notification with PDF using Azure Communications
     */
    public function sendOrderNotificationWithPDF(
        ProductRequest $order,
        string $pdfPath,
        array $recipients = []
    ): array {
        try {
            // Prepare order data
            $orderData = [
                'order_number' => $order->request_number,
                'patient_name' => $order->patient_display_id, // Use display ID for privacy
                'provider_name' => $order->provider->full_name,
                'facility_name' => $order->facility->name,
                'product_name' => $order->products->first()->name ?? 'Multiple Products',
                'product_code' => $order->products->first()->sku ?? '',
                'quantity' => $order->products->sum('pivot.quantity'),
                'expected_service_date' => $order->expected_service_date?->format('Y-m-d') ?? $order->date_of_service,
            ];

            // Get manufacturer name
            $manufacturerName = $order->manufacturer->name ?? 'Unknown';

            // Get recipients if not provided
            if (empty($recipients)) {
                $recipients = $this->getDefaultRecipients($manufacturerName);
            }

            // Send notification using Azure Communications Service
            return $this->azureCommunications->sendManufacturerNotification(
                $manufacturerName,
                $recipients,
                $orderData,
                $pdfPath
            );

        } catch (\Exception $e) {
            Log::error('Failed to send order notification with PDF', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send order status SMS notification
     */
    public function sendOrderStatusSms(ProductRequest $order, string $status): array
    {
        // Get patient phone number (would need to be retrieved from FHIR)
        $phoneNumber = null; // TODO: Retrieve from FHIR patient resource

        if (!$phoneNumber) {
            Log::warning('No phone number available for SMS notification', [
                'order_id' => $order->id,
            ]);
            return [
                'success' => false,
                'error' => 'No phone number available',
            ];
        }

        $additionalInfo = [];
        if ($status === 'shipped' && $order->tracking_number) {
            $additionalInfo['tracking_number'] = $order->tracking_number;
        }

        return $this->azureCommunications->sendOrderStatusSms(
            $phoneNumber,
            $order->request_number,
            $status,
            $additionalInfo
        );
    }
}
