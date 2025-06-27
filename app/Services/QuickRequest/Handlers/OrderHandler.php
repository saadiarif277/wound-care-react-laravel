<?php

namespace App\Services\QuickRequest\Handlers;

use App\Models\Order\Order;
use App\Models\Episode;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class OrderHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger
    ) {}

    /**
     * Create initial order for episode
     */
    public function createInitialOrder(Episode $episode, array $orderDetails): Order
    {
        try {
            $this->logger->info('Creating initial order for episode', [
                'episode_id' => $episode->id
            ]);

            // Create FHIR DeviceRequest
            $deviceRequestId = $this->createDeviceRequest($episode, $orderDetails);

            // Create local order record
            $order = Order::create([
                'id' => Str::uuid(),
                'episode_id' => $episode->id,
                'type' => 'initial',
                'details' => $orderDetails,
                'status' => 'pending',
                'fhir_device_request_id' => $deviceRequestId,
                'metadata' => [
                    'created_by' => Auth::id(),
                    'created_at' => now()->toIso8601String()
                ]
            ]);

            $this->logger->info('Initial order created successfully', [
                'order_id' => $order->id,
                'episode_id' => $episode->id,
                'fhir_device_request_id' => $deviceRequestId
            ]);

            return $order;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create initial order', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id
            ]);
            throw new \Exception('Failed to create initial order: ' . $e->getMessage());
        }
    }

    /**
     * Create a follow-up order for the given episode.
     *
     * @param Episode $episode
     * @param array $orderDetails
     * @return Order
     */
    public function createFollowUpOrder(Episode $episode, array $orderDetails): Order
    {
        try {
            $this->logger->info('Creating follow-up order for episode', [
                'episode_id' => $episode->id
            ]);

            // Get the most recent order to link as parent
            $parentOrder = $episode->orders()
                ->latest()
                ->first();

            // Create FHIR DeviceRequest
            $deviceRequestId = $this->createDeviceRequest($episode, $orderDetails, $parentOrder);

            // Create local order record
            $order = Order::create([
                'id' => Str::uuid(),
                'episode_id' => $episode->id,
                'based_on' => $parentOrder?->id,
                'type' => 'follow_up',
                'details' => $orderDetails,
                'status' => 'pending',
                'fhir_device_request_id' => $deviceRequestId,
                'metadata' => [
                    'created_by' => Auth::id(),
                    'created_at' => now()->toIso8601String(),
                    'follow_up_reason' => $orderDetails['reason'] ?? 'routine'
                ]
            ]);

            $this->logger->info('Follow-up order created successfully', [
                'order_id' => $order->id,
                'episode_id' => $episode->id,
                'based_on' => $parentOrder?->id,
                'fhir_device_request_id' => $deviceRequestId
            ]);

            return $order;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create follow-up order', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
                'parent_order_id' => $parentOrder?->id ?? null
            ]);
            throw new \Exception('Failed to create follow-up order: ' . $e->getMessage());
        }
    }

    /**
     * Create FHIR DeviceRequest
     */
    private function createDeviceRequest(Episode $episode, array $orderDetails, ?Order $parentOrder = null): string
    {
        $deviceRequestData = [
            'resourceType' => 'DeviceRequest',
            'status' => 'active',
            'intent' => 'order',
            'priority' => $orderDetails['priority'] ?? 'routine',
            'codeReference' => $this->mapProductsToDevices($orderDetails['products']),
            'subject' => [
                'reference' => "Patient/{$episode->patient_fhir_id}"
            ],
            'encounter' => [
                'reference' => "Encounter/{$episode->encounter_fhir_id}"
            ],
            'authoredOn' => now()->toIso8601String(),
            'requester' => [
                'reference' => "Practitioner/{$episode->practitioner_fhir_id}"
            ],
            'reasonReference' => [
                [
                    'reference' => "Condition/{$episode->condition_fhir_id}"
                ]
            ],
            'note' => !empty($orderDetails['special_instructions']) ? [
                [
                    'text' => $orderDetails['special_instructions']
                ]
            ] : null,
            'relevantHistory' => [
                [
                    'reference' => "EpisodeOfCare/{$episode->episode_of_care_fhir_id}",
                    'display' => 'Related Episode of Care'
                ]
            ]
        ];

        // Add parent reference for follow-up orders
        if ($parentOrder && $parentOrder->fhir_device_request_id) {
            $deviceRequestData['basedOn'] = [
                [
                    'reference' => "DeviceRequest/{$parentOrder->fhir_device_request_id}"
                ]
            ];
        }

        // Add quantity and duration
        if (!empty($orderDetails['products'])) {
            $firstProduct = $orderDetails['products'][0];

            if (!empty($firstProduct['quantity'])) {
                $deviceRequestData['quantity'] = [
                    'value' => $firstProduct['quantity'],
                    'unit' => 'units'
                ];
            }

            // Add occurrence period for supplies
            $deviceRequestData['occurrencePeriod'] = [
                'start' => now()->toIso8601String(),
                'end' => now()->addDays(30)->toIso8601String()
            ];
        }

        $response = $this->fhirService->create('DeviceRequest', $deviceRequestData);

        return $response['id'];
    }

    /**
     * Map products to FHIR device references
     */
    private function mapProductsToDevices(array $products): array
    {
        return [
            'concept' => [
                'coding' => array_map(function ($product) {
                    return [
                        'system' => 'http://mscwoundcare.com/products',
                        'code' => (string)$product['id'],
                        'display' => $this->getProductDisplay($product)
                    ];
                }, $products)
            ]
        ];
    }

    /**
     * Get product display name
     */
    private function getProductDisplay(array $product): string
    {
        // This would normally lookup from database
        $productMap = [
            1 => 'Collagen Wound Dressing',
            2 => 'Antimicrobial Silver Dressing',
            3 => 'Hydrocolloid Dressing',
            4 => 'Foam Dressing',
            5 => 'Alginate Dressing'
        ];

        $display = $productMap[$product['id']] ?? "Product {$product['id']}";

        if (!empty($product['size'])) {
            $display .= " - {$product['size']}";
        }

        return $display;
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Order $order, string $status, array $metadata = []): void
    {
        try {
            $order->update([
                'status' => $status,
                'metadata' => array_merge($order->metadata ?? [], $metadata, [
                    'status_updated_at' => now()->toIso8601String(),
                    'status_updated_by' => Auth::id()
                ])
            ]);

            // Update FHIR DeviceRequest status
            if ($order->fhir_device_request_id) {
                $fhirStatus = $this->mapOrderStatusToFhir($status);

                $this->fhirService->update('DeviceRequest', $order->fhir_device_request_id, [
                    'status' => $fhirStatus
                ]);
            }

            $this->logger->info('Order status updated successfully', [
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $status,
                'fhir_updated' => !empty($order->fhir_device_request_id)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update order status', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'attempted_status' => $status
            ]);
            throw new \Exception('Failed to update order status: ' . $e->getMessage());
        }
    }

    /**
     * Map order status to FHIR status
     */
    private function mapOrderStatusToFhir(string $status): string
    {
        $statusMap = [
            'pending' => 'active',
            'approved' => 'active',
            'processing' => 'active',
            'shipped' => 'completed',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            'rejected' => 'entered-in-error'
        ];

        return $statusMap[$status] ?? 'unknown';
    }

    /**
     * Calculate order totals
     */
    public function calculateOrderTotals(array $products): array
    {
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($products as $product) {
            // This would normally fetch pricing from database
            $unitPrice = $this->getProductPrice($product['id'], $product['size'] ?? 'medium');
            $quantity = $product['quantity'] ?? 1;

            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        $tax = $subtotal * 0.08; // 8% tax rate
        $shipping = $totalQuantity > 50 ? 0 : 15.00; // Free shipping over 50 units
        $total = $subtotal + $tax + $shipping;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round($total, 2),
            'total_quantity' => $totalQuantity
        ];
    }

    /**
     * Get product price
     */
    private function getProductPrice(int $productId, string $size): float
    {
        // This would normally be from database
        $basePrices = [
            1 => 25.00,
            2 => 35.00,
            3 => 20.00,
            4 => 15.00,
            5 => 30.00
        ];

        $sizeMultipliers = [
            'small' => 0.8,
            'medium' => 1.0,
            'large' => 1.3,
            'extra-large' => 1.5
        ];

        $basePrice = $basePrices[$productId] ?? 20.00;
        $multiplier = $sizeMultipliers[$size] ?? 1.0;

        return $basePrice * $multiplier;
    }
}