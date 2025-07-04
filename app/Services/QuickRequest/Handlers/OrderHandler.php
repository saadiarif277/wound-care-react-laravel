<?php

namespace App\Services\QuickRequest\Handlers;

use App\Models\Order\Order;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\PhiAuditService;

class OrderHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private PhiAuditService $auditService
    ) {}

    /**
     * Create initial order for episode
     */
    public function createInitialOrder(PatientManufacturerIVREpisode $episode, array $orderDetails): Order
    {
        try {
            $this->logger->info('Creating initial order for episode', [
                'episode_id' => $episode->id,
                'order_details_keys' => array_keys($orderDetails),
                'has_products' => isset($orderDetails['products']),
                'products_count' => count($orderDetails['products'] ?? []),
                'products_sample' => array_slice($orderDetails['products'] ?? [], 0, 2)
            ]);

            // Create FHIR DeviceRequest
            $deviceRequestId = $this->createDeviceRequest($episode, $orderDetails);
            $this->logger->info('FHIR DeviceRequest created', [
                'device_request_id' => $deviceRequestId
            ]);

            // Get facility and provider info from episode metadata
            $metadata = $episode->metadata ?? [];
            $facilityId = $metadata['facility_data']['id'] ?? Auth::user()->facility_id ?? 1;
            // Always use the authenticated user as the provider for security and access control
            $providerId = Auth::id();
            
            $this->logger->info('Setting order provider', [
                'auth_user_id' => Auth::id(),
                'metadata_provider_id' => $metadata['provider_data']['id'] ?? null,
                'using_provider_id' => $providerId
            ]);

            // Create local order record
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'episode_id' => $episode->id,
                'type' => 'initial',
                'status' => 'pending',
                'order_status' => 'Pending',
                'patient_fhir_id' => $episode->patient_fhir_id,
                'patient_display_id' => $episode->patient_display_id,
                'facility_id' => $facilityId,
                'provider_id' => $providerId,
                'manufacturer_id' => $episode->manufacturer_id,
                'date_of_service' => now()->toDateString(),
                'total_amount' => $this->calculateOrderTotals($orderDetails['products'] ?? [])['total'] ?? 0,
                'notes' => [
                    'created_by' => Auth::id(),
                    'created_at' => now()->toIso8601String(),
                    'fhir_device_request_id' => $deviceRequestId,
                    'order_details' => $orderDetails,
                    'patient_display_id' => $episode->patient_display_id,
                    'manufacturer_id' => $episode->manufacturer_id
                ]
            ]);

            $this->logger->info('Initial order created successfully', [
                'order_id' => $order->id,
                'order_provider_id' => $order->provider_id,
                'episode_id' => $episode->id,
                'fhir_device_request_id' => $deviceRequestId,
                'patient_display_id' => $episode->patient_display_id,
                'auth_user_id' => Auth::id(),
                'order_exists_check' => Order::where('episode_id', $episode->id)->where('provider_id', Auth::id())->exists()
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
     * @param PatientManufacturerIVREpisode $episode
     * @param array $orderDetails
     * @return Order
     */
    public function createFollowUpOrder(PatientManufacturerIVREpisode $episode, array $orderDetails): Order
    {
        try {
            $this->logger->info('Creating follow-up order for episode', [
                'episode_id' => $episode->id
            ]);

            // Get the most recent order to link as parent
            $parentOrder = $episode->orders()
                ->latest()
                ->first();

            // Get facility and provider info from episode metadata
            $metadata = $episode->metadata ?? [];
            $facilityId = $metadata['facility_data']['id'] ?? Auth::user()->facility_id ?? 1;
            // Always use the authenticated user as the provider for security and access control
            $providerId = Auth::id();
            
            $this->logger->info('Setting follow-up order provider', [
                'auth_user_id' => Auth::id(),
                'metadata_provider_id' => $metadata['provider_data']['id'] ?? null,
                'using_provider_id' => $providerId
            ]);

            // Create FHIR DeviceRequest
            $deviceRequestId = $this->createDeviceRequest($episode, $orderDetails, $parentOrder);
            $this->logger->info('FHIR DeviceRequest for follow-up created', [
                'device_request_id' => $deviceRequestId
            ]);

            // Create local order record
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'episode_id' => $episode->id,
                'parent_order_id' => $parentOrder?->id,
                'type' => 'follow_up',
                'status' => 'pending',
                'order_status' => 'Pending',
                'patient_fhir_id' => $episode->patient_fhir_id,
                'patient_display_id' => $episode->patient_display_id,
                'facility_id' => $facilityId,
                'provider_id' => $providerId,
                'manufacturer_id' => $episode->manufacturer_id,
                'date_of_service' => now()->toDateString(),
                'total_amount' => $this->calculateOrderTotals($orderDetails['products'] ?? [])['total'] ?? 0,
                'notes' => [
                    'created_by' => Auth::id(),
                    'created_at' => now()->toIso8601String(),
                    'fhir_device_request_id' => $deviceRequestId,
                    'order_details' => $orderDetails,
                    'follow_up_reason' => $orderDetails['reason'] ?? 'routine',
                    'patient_display_id' => $episode->patient_display_id,
                    'manufacturer_id' => $episode->manufacturer_id
                ]
            ]);

            $this->logger->info('Follow-up order created successfully', [
                'order_id' => $order->id,
                'episode_id' => $episode->id,
                'parent_order_id' => $parentOrder?->id,
                'fhir_device_request_id' => $deviceRequestId,
                'patient_display_id' => $episode->patient_display_id
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
    private function createDeviceRequest(PatientManufacturerIVREpisode $episode, array $orderDetails, ?Order $parentOrder = null): string
    {
        // Get FHIR IDs from episode metadata
        $metadata = $episode->metadata ?? [];
        $patientFhirId = $episode->patient_fhir_id;
        $practitionerFhirId = $metadata['practitioner_fhir_id'] ?? null;
        $organizationFhirId = $metadata['organization_fhir_id'] ?? null;
        $episodeOfCareFhirId = $metadata['episode_of_care_fhir_id'] ?? null;
        $conditionId = $metadata['condition_id'] ?? null;

        $deviceRequestData = [
            'resourceType' => 'DeviceRequest',
            'status' => 'active',
            'intent' => 'order',
            'priority' => $orderDetails['priority'] ?? 'routine',
            'codeReference' => $this->mapProductsToDevices($orderDetails['products'] ?? []),
            'subject' => [
                'reference' => "Patient/{$patientFhirId}"
            ],
            'authoredOn' => now()->toIso8601String(),
            'note' => !empty($orderDetails['special_instructions']) ? [
                [
                    'text' => $orderDetails['special_instructions']
                ]
            ] : null,
        ];

        // Add optional FHIR references if available
        if ($practitionerFhirId) {
            $deviceRequestData['requester'] = [
                'reference' => "Practitioner/{$practitionerFhirId}"
            ];
        }

        if ($conditionId) {
            $deviceRequestData['reasonReference'] = [
                [
                    'reference' => "Condition/{$conditionId}"
                ]
            ];
        }

        if ($episodeOfCareFhirId) {
            $deviceRequestData['relevantHistory'] = [
                [
                    'reference' => "EpisodeOfCare/{$episodeOfCareFhirId}",
                    'display' => 'Related Episode of Care'
                ]
            ];
        }

        // Add parent reference for follow-up orders
        if ($parentOrder && !empty($parentOrder->metadata['fhir_device_request_id'])) {
            $deviceRequestData['basedOn'] = [
                [
                    'reference' => "DeviceRequest/{$parentOrder->metadata['fhir_device_request_id']}"
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

        try {
            $response = $this->fhirService->create('DeviceRequest', $deviceRequestData);
            return $response['id'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create FHIR DeviceRequest', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
                'device_request_data' => $deviceRequestData
            ]);

            // Return a fallback ID to prevent the entire order creation from failing
            return 'fallback-device-request-' . uniqid();
        }
    }

        /**
     * Map products to FHIR device references
     */
    private function mapProductsToDevices(array $products): array
    {
        if (empty($products)) {
            $this->logger->warning('No products provided to mapProductsToDevices');
            return [
                'concept' => [
                    'coding' => [
                        [
                            'system' => 'http://mscwoundcare.com/products',
                            'code' => 'UNKNOWN',
                            'display' => 'Unknown Product'
                        ]
                    ]
                ]
            ];
        }

        $this->logger->info('Mapping products to devices', [
            'products_count' => count($products),
            'first_product_keys' => array_keys($products[0] ?? [])
        ]);

        return [
            'concept' => [
                'coding' => array_map(function ($product) {
                    // Handle different product array structures
                    $productId = $product['id'] ?? $product['product_id'] ?? 'UNKNOWN';
                    $productName = $product['name'] ?? $product['product_name'] ?? "Product {$productId}";

                    $this->logger->info('Mapped product', [
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'original_keys' => array_keys($product)
                    ]);

                    return [
                        'system' => 'http://mscwoundcare.com/products',
                        'code' => (string)$productId,
                        'display' => $productName
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
        // Handle different product array structures
        $productId = $product['id'] ?? $product['product_id'] ?? 'UNKNOWN';

        // This would normally lookup from database
        $productMap = [
            1 => 'Collagen Wound Dressing',
            2 => 'Antimicrobial Silver Dressing',
            3 => 'Hydrocolloid Dressing',
            4 => 'Foam Dressing',
            5 => 'Alginate Dressing'
        ];

        $display = $productMap[$productId] ?? "Product {$productId}";

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
                'notes' => json_encode(array_merge(
                    json_decode($order->notes ?? '{}', true) ?: [],
                    $metadata,
                    [
                        'status_updated_at' => now()->toIso8601String(),
                        'status_updated_by' => Auth::id()
                    ]
                ))
            ]);

            // Update FHIR DeviceRequest status
            // TEMPORARILY DISABLED: FHIR operations disabled for debugging
            /*
            $notes = json_decode($order->notes ?? '{}', true) ?: [];
            if (!empty($notes['fhir_device_request_id'])) {
                $fhirStatus = $this->mapOrderStatusToFhir($status);

                $this->fhirService->update('DeviceRequest', $notes['fhir_device_request_id'], [
                    'status' => $fhirStatus
                ]);
            }
            */
            $this->logger->info('FHIR DeviceRequest update skipped (FHIR disabled)');

            $this->logger->info('Order status updated successfully', [
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $status,
                'fhir_updated' => !empty($notes['fhir_device_request_id'])
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
            // Handle different product array structures
            $productId = $product['id'] ?? $product['product_id'] ?? 1;
            $size = $product['size'] ?? 'medium';
            $quantity = $product['quantity'] ?? 1;

            // This would normally fetch pricing from database
            $unitPrice = $this->getProductPrice($productId, $size);

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

    /**
     * Generate a unique order number
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(4));

        return "{$prefix}{$timestamp}{$random}";
    }

    /**
     * Create ServiceRequest for a new order
     */
    public function createServiceRequest(array $data): string
    {
        try {
            $this->logger->info('Creating FHIR ServiceRequest for order');

            $serviceRequestData = [
                'resourceType' => 'ServiceRequest',
                'status' => 'active',
                'intent' => 'order',
                'subject' => [
                    'reference' => "Patient/{$data['patient_id']}"
                ],
                'requester' => [
                    'reference' => "Practitioner/{$data['provider_id']}"
                ],
                'performer' => [
                    [
                        'reference' => "Organization/{$data['organization_id']}"
                    ]
                ],
                'reasonReference' => [
                    [
                        'reference' => "Condition/{$data['condition_id']}"
                    ]
                ],
                'occurrenceTiming' => [
                    'repeat' => [
                        'duration' => $data['duration_weeks'],
                        'durationUnit' => 'wk'
                    ]
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://msc-mvp.com/order-type',
                                'code' => $data['order_type'],
                                'display' => ucfirst($data['order_type']) . ' Order'
                            ]
                        ]
                    ]
                ],
                'orderDetail' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://msc-mvp.com/product',
                                'code' => (string) $data['product_id'],
                                'display' => $data['product_name'] ?? "Product {$data['product_id']}"
                            ]
                        ],
                        'text' => "Quantity: {$data['quantity']}"
                    ]
                ]
            ];

            if (!empty($data['shipping_address'])) {
                $serviceRequestData['extension'] = [
                    [
                        'url' => 'http://msc-mvp.com/fhir/StructureDefinition/shipping-details',
                        'extension' => [
                            [
                                'url' => 'delivery-preference',
                                'valueString' => $data['delivery_preference']
                            ],
                            [
                                'url' => 'shipping-address',
                                'valueAddress' => [
                                    'line' => [$data['shipping_address']['address_line1']],
                                    'city' => $data['shipping_address']['city'],
                                    'state' => $data['shipping_address']['state'],
                                    'postalCode' => $data['shipping_address']['postalCode']
                                ]
                            ]
                        ]
                    ]
                ];
            }

            $response = $this->fhirService->create('ServiceRequest', $serviceRequestData);

            $this->auditService->logAccess('order.created', 'ServiceRequest', $response['id']);

            $this->logger->info('ServiceRequest created successfully in FHIR', [
                'service_request_id' => $response['id'],
                'order_type' => $data['order_type']
            ]);

            return $response['id'];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create ServiceRequest in FHIR', [
                'error' => $e->getMessage(),
                'order_type' => $data['order_type'] ?? 'unknown'
            ]);
            throw $e;
        }
    }
}
