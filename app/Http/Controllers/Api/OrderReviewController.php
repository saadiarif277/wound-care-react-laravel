<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Services\FhirService;
use App\Services\Compliance\PhiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderReviewController extends Controller
{
    public function __construct(
        private FhirService $fhirService,
        private PhiAuditService $auditService
    ) {}

    /**
     * Get complete order data for review
     */
    public function getOrderReview(string $orderId)
    {
        try {
            $order = Order::with([
                'episode',
                'episode.provider',
                'episode.facility',
                'episode.organization',
                'products',
                'notes.user',
                'documents'
            ])->findOrFail($orderId);

            // Check permissions
            $this->authorize('view', $order);

            // Audit PHI access
            $this->auditService->logAccess(
                'order.review.accessed',
                'Order',
                $orderId,
                ['user_id' => Auth::id()]
            );

            // Get FHIR data
            $episode = $order->episode;
            
            // Ensure we have an Episode instance, not a BelongsTo relationship
            if (!$episode instanceof Episode) {
                throw new \Exception('Episode not found for order');
            }
            
            $patientData = $this->getPatientData($episode);
            $insuranceData = $this->getInsuranceData($episode);
            $clinicalData = $this->getClinicalData($episode);

            // Build response based on user role
            $user = Auth::user();
            $response = [
                'orderId' => $order->id,
                'status' => $this->mapOrderStatus($order),
                'patient' => $this->formatPatientSection($patientData, $insuranceData, $episode),
                'provider' => $this->formatProviderSection($episode),
                'clinical' => $this->formatClinicalSection($clinicalData, $order),
                'products' => $this->formatProductsSection($order),
                'forms' => $this->formatFormsSection($order),
                'shipping' => $this->formatShippingSection($order),
                'notes' => $this->formatNotesSection($order)
            ];

            // Add pricing for non-OMs
            if ($user->role !== 'order_manager') {
                $response['pricing'] = $this->formatPricingSection($order);
            }

            // Add audit log for admins
            if ($user->role === 'admin') {
                $response['audit'] = $this->formatAuditSection($order);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error retrieving order review', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve order details'
            ], 500);
        }
    }

    /**
     * Validate order completeness
     */
    public function validateOrder(string $orderId)
    {
        try {
            $order = \App\Models\Order\Order::with(['episode', 'products', 'documents'])->findOrFail($orderId);
            
            $this->authorize('update', $order);

            // Ensure we have an Episode instance, not a BelongsTo relationship
            $episode = $order->episode;
            if (!$episode instanceof Episode) {
                throw new \Exception('Episode not found for order');
            }

            $validationResults = [];
            $isComplete = true;

            // Validate patient information
            $patientValidation = $this->validatePatientSection($episode);
            $validationResults['patient'] = $patientValidation;
            if (!$patientValidation['valid']) $isComplete = false;

            // Validate clinical information
            $clinicalValidation = $this->validateClinicalSection($order);
            $validationResults['clinical'] = $clinicalValidation;
            if (!$clinicalValidation['valid']) $isComplete = false;

            // Validate products
            $productValidation = $this->validateProductSection($order);
            $validationResults['products'] = $productValidation;
            if (!$productValidation['valid']) $isComplete = false;

            // Validate forms
            $formsValidation = $this->validateFormsSection($order);
            $validationResults['forms'] = $formsValidation;
            if (!$formsValidation['valid']) $isComplete = false;

            return response()->json([
                'isComplete' => $isComplete,
                'sections' => $validationResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to validate order'
            ], 500);
        }
    }

    /**
     * Submit order for processing
     */
    public function submitOrder(Request $request, string $orderId)
    {
        $request->validate([
            'confirmation' => 'required|boolean|accepted',
            'note' => 'nullable|string|max:500'
        ]);

        try {
            $order = Order::with(['episode'])->findOrFail($orderId);
            
            $this->authorize('submit', $order);

            DB::beginTransaction();

            // Validate order is complete
            $validation = $this->validateOrder($orderId);
            if (!$validation->getData()->isComplete) {
                return response()->json([
                    'error' => 'Order is not complete',
                    'validation' => $validation->getData()
                ], 422);
            }

            // Update order status
            $order->status = 'submitted';
            $order->submitted_at = now();
            $order->submitted_by = Auth::id();
            $order->save();

            // Update episode status if needed
            if ($order->episode->status === 'draft') {
                $order->episode->status = 'pending_review';
                $order->episode->save();
            }

            // Add submission note if provided
            if ($request->filled('note')) {
                $order->notes()->create([
                    'user_id' => Auth::id(),
                    'note' => $request->note,
                    'type' => 'submission'
                ]);
            }

            // Create audit entry
            $this->auditService->logAccess(
                'order.submitted',
                'Order',
                $orderId,
                [
                    'user_id' => Auth::id(),
                    'confirmation' => true,
                    'note' => $request->note
                ]
            );

            // Send notifications
            $this->sendSubmissionNotifications($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order submitted successfully',
                'orderId' => $order->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error submitting order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to submit order'
            ], 500);
        }
    }

    /**
     * Add internal note to order
     */
    public function addNote(Request $request, string $orderId)
    {
        $request->validate([
            'note' => 'required|string|max:1000'
        ]);

        try {
            $order = Order::findOrFail($orderId);
            
            $this->authorize('addNote', $order);

            $note = $order->notes()->create([
                'user_id' => Auth::id(),
                'note' => $request->note,
                'type' => 'internal'
            ]);

            // Audit the action
            $this->auditService->logAccess(
                'order.note.added',
                'Order',
                $orderId,
                ['note_id' => $note->id]
            );

            return response()->json([
                'success' => true,
                'note' => [
                    'id' => $note->id,
                    'timestamp' => $note->created_at->toISOString(),
                    'user' => Auth::user()->name,
                    'note' => $note->note
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding note to order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to add note'
            ], 500);
        }
    }

    /**
     * Get patient data from FHIR
     */
    private function getPatientData(Episode $episode)
    {
        if (!$episode->patient_fhir_id) {
            return null;
        }

        try {
            return $this->fhirService->getPatient($episode->patient_fhir_id);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch patient from FHIR', [
                'fhir_id' => $episode->patient_fhir_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get insurance data from FHIR
     */
    private function getInsuranceData(Episode $episode)
    {
        if (!$episode->patient_fhir_id) {
            return null;
        }

        try {
            $coverages = $this->fhirService->searchCoverage([
                'patient' => $episode->patient_fhir_id,
                'status' => 'active'
            ]);

            return $this->processCoverageData($coverages);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch coverage from FHIR', [
                'patient_fhir_id' => $episode->patient_fhir_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get clinical data from FHIR
     */
    private function getClinicalData(Episode $episode)
    {
        $clinicalData = [];

        try {
            // Get conditions
            if ($episode->patient_fhir_id) {
                $conditions = $this->fhirService->searchConditions([
                    'patient' => $episode->patient_fhir_id,
                    'clinical-status' => 'active'
                ]);
                $clinicalData['conditions'] = $conditions;
            }

            // Get episode of care
            if ($episode->episode_of_care_fhir_id) {
                $episodeOfCare = $this->fhirService->getEpisodeOfCare($episode->episode_of_care_fhir_id);
                $clinicalData['episodeOfCare'] = $episodeOfCare;
            }

            return $clinicalData;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch clinical data from FHIR', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format patient section for response
     */
    private function formatPatientSection($patientData, $insuranceData, Episode $episode)
    {
        $demographics = [
            'firstName' => '',
            'lastName' => '',
            'middleName' => '',
            'suffix' => '',
            'dateOfBirth' => '',
            'gender' => 'unknown',
            'phone' => '',
            'email' => '',
            'address' => [
                'line1' => '',
                'line2' => '',
                'city' => '',
                'state' => '',
                'zip' => ''
            ]
        ];

        // Extract from FHIR patient resource
        if ($patientData) {
            if (isset($patientData['name'][0])) {
                $name = $patientData['name'][0];
                $demographics['firstName'] = $name['given'][0] ?? '';
                $demographics['lastName'] = $name['family'] ?? '';
                $demographics['middleName'] = $name['given'][1] ?? '';
                $demographics['suffix'] = $name['suffix'][0] ?? '';
            }

            $demographics['dateOfBirth'] = $patientData['birthDate'] ?? '';
            $demographics['gender'] = $patientData['gender'] ?? 'unknown';

            // Extract contact info
            if (isset($patientData['telecom'])) {
                foreach ($patientData['telecom'] as $telecom) {
                    if ($telecom['system'] === 'phone' && !$demographics['phone']) {
                        $demographics['phone'] = $telecom['value'];
                    }
                    if ($telecom['system'] === 'email' && !$demographics['email']) {
                        $demographics['email'] = $telecom['value'];
                    }
                }
            }

            // Extract address
            if (isset($patientData['address'][0])) {
                $addr = $patientData['address'][0];
                $demographics['address'] = [
                    'line1' => $addr['line'][0] ?? '',
                    'line2' => $addr['line'][1] ?? '',
                    'city' => $addr['city'] ?? '',
                    'state' => $addr['state'] ?? '',
                    'zip' => $addr['postalCode'] ?? ''
                ];
            }
        }

        return [
            'fhirId' => $episode->patient_fhir_id ?? '',
            'displayId' => $episode->patient_display_id ?? '',
            'demographics' => $demographics,
            'insurance' => $insuranceData ?? [
                'primary' => [
                    'payerName' => '',
                    'payerId' => '',
                    'planName' => '',
                    'policyNumber' => '',
                    'groupNumber' => ''
                ]
            ]
        ];
    }

    /**
     * Format provider section
     */
    private function formatProviderSection(Episode $episode)
    {
        $provider = $episode->provider;
        
        return [
            'name' => $provider->name ?? '',
            'credentials' => $provider->credentials ?? '',
            'npi' => $provider->npi ?? '',
            'organization' => $episode->organization->name ?? '',
            'department' => $provider->department ?? '',
            'contact' => [
                'phone' => $provider->phone ?? '',
                'email' => $provider->email ?? ''
            ]
        ];
    }

    /**
     * Format clinical section
     */
    private function formatClinicalSection($clinicalData, Order $order)
    {
        // Extract wound details from order
        $woundDetails = $order->details['wound'] ?? [];
        
        // Extract diagnoses from FHIR conditions
        $diagnoses = [
            'primary' => ['code' => '', 'description' => ''],
            'secondary' => []
        ];

        if (isset($clinicalData['conditions']['entry'])) {
            foreach ($clinicalData['conditions']['entry'] as $index => $entry) {
                $condition = $entry['resource'];
                $code = $condition['code']['coding'][0]['code'] ?? '';
                $description = $condition['code']['text'] ?? $condition['code']['coding'][0]['display'] ?? '';
                
                if ($index === 0) {
                    $diagnoses['primary'] = compact('code', 'description');
                } else {
                    $diagnoses['secondary'][] = compact('code', 'description');
                }
            }
        }

        return [
            'wound' => [
                'type' => $woundDetails['type'] ?? '',
                'description' => $woundDetails['description'] ?? '',
                'size' => [
                    'length' => (float)($woundDetails['size']['length'] ?? 0),
                    'width' => (float)($woundDetails['size']['width'] ?? 0),
                    'depth' => (float)($woundDetails['size']['depth'] ?? 0)
                ],
                'location' => $woundDetails['location'] ?? '',
                'duration' => $woundDetails['duration'] ?? ''
            ],
            'diagnoses' => $diagnoses,
            'clinicalJustification' => $order->details['clinical_justification'] ?? '',
            'treatmentHistory' => [
                'priorApplications' => (int)($order->details['prior_applications'] ?? 0),
                'anticipatedApplications' => (int)($order->details['anticipated_applications'] ?? 0),
                'conservativeCare' => [
                    'duration' => $order->details['conservative_care_duration'] ?? '',
                    'types' => $order->details['conservative_care_types'] ?? []
                ]
            ],
            'documents' => $order->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'name' => $doc->name,
                    'size' => $doc->size,
                    'uploadDate' => $doc->created_at->toISOString(),
                    'url' => route('api.orders.documents.view', [$doc->order_id, $doc->id])
                ];
            })->toArray()
        ];
    }

    /**
     * Format products section
     */
    private function formatProductsSection(Order $order)
    {
        return $order->products->map(function ($product) {
            return [
                'name' => $product->name,
                'manufacturer' => $product->manufacturer->name ?? '',
                'sku' => $product->sku,
                'sizes' => $product->pivot->sizes ?? [],
                'coverageAlerts' => [] // TODO: Implement coverage checking
            ];
        })->toArray();
    }

    /**
     * Format forms section
     */
    private function formatFormsSection(Order $order)
    {
        $ivr = $order->documents()->where('type', 'ivr_form')->latest()->first();
        $orderForm = $order->documents()->where('type', 'order_form')->latest()->first();

        return [
            'ivr' => [
                'status' => $this->getFormStatus($ivr),
                'completionDate' => $ivr?->completed_at ? \Carbon\Carbon::parse($ivr->completed_at)->toISOString() : null,
                'expirationDate' => $ivr?->expires_at ? \Carbon\Carbon::parse($ivr->expires_at)->toISOString() : null,
                'documentUrl' => $ivr ? route('api.orders.documents.view', [$order->id, $ivr->id]) : null,
                'templateId' => $order->products->first()?->docuseal_template_id
            ],
            'order' => [
                'status' => $this->getFormStatus($orderForm),
                'completionDate' => $orderForm?->completed_at ? \Carbon\Carbon::parse($orderForm->completed_at)->toISOString() : null,
                'documentUrl' => $orderForm ? route('api.orders.documents.view', [$order->id, $orderForm->id]) : null
            ]
        ];
    }

    /**
     * Format pricing section
     */
    private function formatPricingSection(Order $order)
    {
        $pricing = $order->pricing ?? [];
        
        return [
            'aspTotal' => (float)($pricing['asp_total'] ?? 0),
            'discount' => (float)($pricing['discount'] ?? 0),
            'netPrice' => (float)($pricing['net_price'] ?? 0),
            'patientResponsibility' => (float)($pricing['patient_responsibility'] ?? 0)
        ];
    }

    /**
     * Format shipping section
     */
    private function formatShippingSection(Order $order)
    {
        $shipping = $order->details['shipping'] ?? [];
        
        return [
            'sameAsPatient' => $shipping['same_as_patient'] ?? true,
            'address' => $shipping['address'] ?? null,
            'expectedDate' => $order->expected_delivery_date?->toDateString() ?? '',
            'method' => $shipping['method'] ?? 'Standard',
            'trackingNumber' => $order->tracking_number,
            'specialInstructions' => $shipping['special_instructions'] ?? ''
        ];
    }

    /**
     * Format notes section
     */
    private function formatNotesSection(Order $order)
    {
        return $order->notes->map(function ($note) {
            return [
                'id' => $note->id,
                'timestamp' => $note->created_at->toISOString(),
                'user' => $note->user->name,
                'note' => $note->note
            ];
        })->toArray();
    }

    /**
     * Format audit section
     */
    private function formatAuditSection(Order $order)
    {
        $auditLogs = $this->auditService->getAuditLogs('Order', $order->id, 50);
        
        return $auditLogs->map(function ($log) {
            return [
                'timestamp' => $log->created_at->toISOString(),
                'user' => $log->user->name ?? 'System',
                'action' => $log->action,
                'details' => $log->details
            ];
        })->toArray();
    }

    /**
     * Get form status
     */
    private function getFormStatus($document): string
    {
        if (!$document) {
            return 'not_started';
        }
        
        if ($document->expires_at && $document->expires_at->isPast()) {
            return 'expired';
        }
        
        if ($document->completed_at) {
            return 'complete';
        }
        
        return 'in_progress';
    }

    /**
     * Map order status
     */
    private function mapOrderStatus(Order $order): string
    {
        // Map internal statuses to display statuses
        $statusMap = [
            'draft' => 'draft',
            'pending_review' => 'ready_for_review',
            'submitted' => 'submitted',
            'admin_review' => 'under_admin_review',
            'sent_to_manufacturer' => 'sent_to_manufacturer',
            'manufacturing' => 'in_production',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'hold' => 'on_hold'
        ];

        return $statusMap[$order->status] ?? $order->status;
    }

    /**
     * Process coverage data from FHIR
     */
    private function processCoverageData($coverageBundle)
    {
        $primary = null;
        $secondary = null;

        if (isset($coverageBundle['entry'])) {
            foreach ($coverageBundle['entry'] as $entry) {
                $coverage = $entry['resource'];
                $order = $coverage['order'] ?? 1;
                
                $processed = [
                    'payerName' => $coverage['payor'][0]['display'] ?? '',
                    'payerId' => $coverage['payor'][0]['identifier']['value'] ?? '',
                    'planName' => $coverage['type']['text'] ?? '',
                    'policyNumber' => $coverage['identifier'][0]['value'] ?? '',
                    'groupNumber' => $coverage['class'][0]['value'] ?? null,
                    'subscriberName' => $coverage['subscriber']['display'] ?? null,
                    'subscriberRelationship' => $coverage['relationship']['coding'][0]['display'] ?? null
                ];

                if ($order === 1) {
                    $primary = $processed;
                } else {
                    $secondary = $processed;
                }
            }
        }

        return [
            'primary' => $primary ?? [
                'payerName' => '',
                'payerId' => '',
                'planName' => '',
                'policyNumber' => '',
                'groupNumber' => ''
            ],
            'secondary' => $secondary
        ];
    }

    /**
     * Validate patient section
     */
    private function validatePatientSection(Episode $episode): array
    {
        $errors = [];
        
        if (!$episode->patient_fhir_id) {
            $errors[] = 'Patient not created in FHIR';
        }
        
        // Check if we have basic patient data
        $patientData = $this->getPatientData($episode);
        if ($patientData) {
            if (empty($patientData['name'][0]['family'])) {
                $errors[] = 'Patient last name missing';
            }
            if (empty($patientData['birthDate'])) {
                $errors[] = 'Patient date of birth missing';
            }
        }
        
        // Check insurance
        $insuranceData = $this->getInsuranceData($episode);
        if (!$insuranceData || empty($insuranceData['primary']['policyNumber'])) {
            $errors[] = 'Primary insurance information missing';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate clinical section
     */
    private function validateClinicalSection(Order $order): array
    {
        $errors = [];
        $details = $order->details;
        
        if (empty($details['wound']['type'])) {
            $errors[] = 'Wound type not specified';
        }
        
        if (empty($details['wound']['location'])) {
            $errors[] = 'Wound location not specified';
        }
        
        if (empty($details['wound']['size'])) {
            $errors[] = 'Wound dimensions not provided';
        }
        
        // Check for diagnosis codes - ensure we have an Episode instance
        $episode = $order->episode;
        if (!$episode instanceof Episode) {
            $errors[] = 'Episode not found for order';
        } else {
            $clinicalData = $this->getClinicalData($episode);
            if (empty($clinicalData['conditions']['entry'])) {
                $errors[] = 'No diagnosis codes provided';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate product section
     */
    private function validateProductSection(Order $order): array
    {
        $errors = [];
        
        if ($order->products->isEmpty()) {
            $errors[] = 'No products selected';
        }
        
        foreach ($order->products as $product) {
            if (empty($product->pivot->sizes)) {
                $errors[] = "No size selected for {$product->name}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate forms section
     */
    private function validateFormsSection(Order $order): array
    {
        $errors = [];
        
        // Check if IVR form is required
        $requiresIvr = $order->products->contains(function ($product) {
            return $product->signature_required;
        });
        
        if ($requiresIvr) {
            $ivr = $order->documents()->where('type', 'ivr_form')->latest()->first();
            if (!$ivr || !$ivr->completed_at) {
                $errors[] = 'IVR form not completed';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Send submission notifications
     */
    private function sendSubmissionNotifications(Order $order)
    {
        // TODO: Implement notification system
        // - Notify admin team
        // - Send confirmation to provider
        // - Queue manufacturer notification if auto-send enabled
    }
}
