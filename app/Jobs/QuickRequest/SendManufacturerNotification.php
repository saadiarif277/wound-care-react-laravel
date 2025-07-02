<?php

namespace App\Jobs\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Mail\ManufacturerOrderNotification;
use App\Services\DocusealService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendManufacturerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300]; // Exponential backoff

    /**
     * Create a new job instance.
     */
    public function __construct(
        private PatientManufacturerIVREpisode $episode,
        private ProductRequest $order,
        private string $notificationType = 'new_order'
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(DocusealService $docusealService): void
    {
        Log::info('Sending manufacturer notification', [
            'episode_id' => $this->episode->id,
            'order_id' => $this->order->id,
            'notification_type' => $this->notificationType,
        ]);

        try {
            $manufacturer = $this->episode->manufacturer;

            if (!$manufacturer) {
                throw new \Exception('Manufacturer not found for episode');
            }

            // Get manufacturer contacts
            $contacts = $this->getManufacturerContacts($manufacturer);

            if (empty($contacts)) {
                Log::warning('No contacts found for manufacturer', [
                    'manufacturer_id' => $manufacturer->id,
                ]);
                return;
            }

            // Prepare order details
            $orderDetails = $this->prepareOrderDetails();

            // Generate PDF documents if needed
            $attachments = [];
            if ($this->shouldIncludeDocuments()) {
                $attachments = $this->prepareDocumentAttachments($docusealService);
            }

            // Send notifications to each contact
            foreach ($contacts as $contact) {
                Mail::to($contact['email'])
                    ->cc($this->getCcRecipients())
                    ->queue(new ManufacturerOrderNotification(
                        $this->episode,
                        $this->order,
                        $orderDetails,
                        $this->notificationType,
                        $attachments
                    ));

                Log::info('Manufacturer notification queued', [
                    'recipient' => $contact['email'],
                    'notification_type' => $this->notificationType,
                ]);
            }

            // Update order metadata
            $this->order->update([
                'metadata' => array_merge($this->order->metadata ?? [], [
                    'manufacturer_notified' => [
                        'type' => $this->notificationType,
                        'sent_at' => now()->toIso8601String(),
                        'recipients' => array_column($contacts, 'email'),
                    ],
                ]),
            ]);

            // Create notification record
            \App\Models\Notification::create([
                'notifiable_type' => \App\Models\Manufacturer::class,
                'notifiable_id' => $manufacturer->id,
                'type' => 'App\\Notifications\\ManufacturerOrderNotification',
                'data' => [
                    'episode_id' => $this->episode->id,
                    'order_id' => $this->order->id,
                    'notification_type' => $this->notificationType,
                    'sent_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send manufacturer notification', [
                'episode_id' => $this->episode->id,
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get manufacturer contacts based on notification type
     */
    private function getManufacturerContacts($manufacturer): array
    {
        $contacts = [];

        switch ($this->notificationType) {
            case 'new_order':
            case 'order_approved':
                // Get order processing contacts
                $contacts = $manufacturer->contacts()
                    ->where('type', 'order_processing')
                    ->where('active', true)
                    ->get()
                    ->map(function ($contact) {
                        return [
                            'email' => $contact->email,
                            'name' => $contact->name,
                        ];
                    })
                    ->toArray();
                break;

            case 'urgent_order':
                // Get urgent order contacts
                $contacts = $manufacturer->contacts()
                    ->whereIn('type', ['order_processing', 'urgent'])
                    ->where('active', true)
                    ->get()
                    ->map(function ($contact) {
                        return [
                            'email' => $contact->email,
                            'name' => $contact->name,
                        ];
                    })
                    ->toArray();
                break;

            case 'order_modification':
            case 'order_cancellation':
                // Get customer service contacts
                $contacts = $manufacturer->contacts()
                    ->whereIn('type', ['customer_service', 'order_processing'])
                    ->where('active', true)
                    ->get()
                    ->map(function ($contact) {
                        return [
                            'email' => $contact->email,
                            'name' => $contact->name,
                        ];
                    })
                    ->toArray();
                break;
        }

        // Add default contact if no specific contacts found
        if (empty($contacts) && $manufacturer->default_email) {
            $contacts[] = [
                'email' => $manufacturer->default_email,
                'name' => $manufacturer->name,
            ];
        }

        return $contacts;
    }

    /**
     * Prepare order details for notification
     */
    private function prepareOrderDetails(): array
    {
        $products = $this->order->details['products'] ?? [];

        return [
            'order_number' => $this->order->id,
            'order_type' => $this->order->type,
            'patient_display' => $this->episode->patient_display,
            'provider' => [
                'name' => $this->episode->practitioner->name ?? 'Unknown',
                'npi' => $this->episode->practitioner->npi ?? '',
            ],
            'facility' => [
                'name' => $this->episode->facility->name ?? 'Unknown',
                'address' => $this->episode->facility->address ?? [],
                'phone' => $this->episode->facility->phone ?? '',
            ],
            'products' => array_map(function ($product) {
                return [
                    'name' => $product['name'],
                    'code' => $product['code'],
                    'quantity' => $product['quantity'],
                    'frequency' => $product['frequency'],
                    'sizes' => array_filter($product['sizes'] ?? [], fn($size) => $size['quantity'] > 0),
                ];
            }, $products),
            'delivery' => $this->order->details['delivery_info'] ?? [],
            'clinical' => [
                'diagnosis' => $this->order->details['clinical_info']['diagnosis']['primary'] ?? [],
                'wound_type' => $this->order->details['clinical_info']['woundDetails']['woundType'] ?? '',
                'wound_location' => $this->order->details['clinical_info']['woundDetails']['woundLocation'] ?? '',
            ],
            'special_instructions' => $this->extractSpecialInstructions(),
            'priority' => $this->determinePriority(),
        ];
    }

    /**
     * Determine if documents should be included
     */
    private function shouldIncludeDocuments(): bool
    {
        return in_array($this->notificationType, ['new_order', 'order_approved']);
    }

    /**
     * Prepare document attachments
     */
    private function prepareDocumentAttachments(DocusealService $docusealService): array
    {
        $attachments = [];

        try {
            // Get insurance verification document
            $insuranceDoc = $this->episode->documents()
                ->where('type', 'insurance_verification')
                ->latest()
                ->first();

            if ($insuranceDoc && $insuranceDoc->path) {
                $attachments[] = [
                    'path' => storage_path('app/' . $insuranceDoc->path),
                    'name' => 'Insurance_Verification.pdf',
                    'mime' => 'application/pdf',
                ];
            }

            // Get prescription/order form if required
            if ($this->episode->manufacturer->requires_prescription) {
                $prescriptionDoc = $this->episode->documents()
                    ->where('type', 'prescription')
                    ->latest()
                    ->first();

                if ($prescriptionDoc && $prescriptionDoc->path) {
                    $attachments[] = [
                        'path' => storage_path('app/' . $prescriptionDoc->path),
                        'name' => 'Prescription.pdf',
                        'mime' => 'application/pdf',
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to prepare document attachments', [
                'episode_id' => $this->episode->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $attachments;
    }

    /**
     * Get CC recipients for the notification
     */
    private function getCcRecipients(): array
    {
        $ccRecipients = [];

        // CC the provider if requested
        if ($this->episode->provider->preferences->copy_on_orders ?? false) {
            $ccRecipients[] = $this->episode->provider->email;
        }

        // CC the office manager
        if ($this->episode->facility->office_manager_email) {
            $ccRecipients[] = $this->episode->facility->office_manager_email;
        }

        return array_filter($ccRecipients);
    }

    /**
     * Extract special instructions from products
     */
    private function extractSpecialInstructions(): array
    {
        $instructions = [];

        foreach ($this->order->details['products'] ?? [] as $product) {
            if (!empty($product['special_instructions'])) {
                $instructions[] = $product['name'] . ': ' . $product['special_instructions'];
            }
        }

        if (!empty($this->order->details['delivery_info']['specialInstructions'])) {
            $instructions[] = 'Delivery: ' . $this->order->details['delivery_info']['specialInstructions'];
        }

        return $instructions;
    }

    /**
     * Determine order priority
     */
    private function determinePriority(): string
    {
        $deliveryMethod = $this->order->details['delivery_info']['method'] ?? 'standard';

        return match ($deliveryMethod) {
            'overnight' => 'urgent',
            'expedited' => 'high',
            default => 'normal',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Manufacturer notification job failed', [
            'episode_id' => $this->episode->id,
            'order_id' => $this->order->id,
            'notification_type' => $this->notificationType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update order metadata
        $this->order->update([
            'metadata' => array_merge($this->order->metadata ?? [], [
                'manufacturer_notification_failed' => [
                    'type' => $this->notificationType,
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                    'attempts' => $this->attempts(),
                ],
            ]),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'manufacturer-notification',
            'episode:' . $this->episode->id,
            'order:' . $this->order->id,
            'type:' . $this->notificationType,
            'manufacturer:' . $this->episode->manufacturer_id,
        ];
    }
}
