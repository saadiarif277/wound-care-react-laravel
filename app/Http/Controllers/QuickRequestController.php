<?php

namespace App\Http\Controllers;

use App\Models\Order\Product;
use App\Models\Fhir\Facility;
use App\Models\User;
use App\Models\Order\ProductRequest;
use App\Services\AzureDocumentIntelligenceService;
use App\Services\PayerService;
use App\Services\PatientService;
use App\Services\PhiAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuickRequestController extends Controller
{
    protected PatientService $patientService;
    
    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }
    public function create()
    {
        $user = Auth::user();
        
        // Get facilities based on user role
        $facilities = $this->getUserFacilities($user);
        
        // Get all active products
        $products = Product::where('is_active', true)
            ->select('id', 'q_code as code', 'name', 'manufacturer', 'available_sizes as sizes')
            ->get();
        
        // Get providers for the user's organization
        $providers = $this->getOrganizationProviders($user);
        
        // Wound types
        $woundTypes = [
            'surgical' => 'Surgical Wound',
            'traumatic' => 'Traumatic Wound',
            'diabetic_foot' => 'Diabetic Foot Ulcer',
            'pressure' => 'Pressure Ulcer',
            'venous' => 'Venous Stasis Ulcer',
            'arterial' => 'Arterial Ulcer',
            'burn' => 'Burn',
            'other' => 'Other'
        ];
        
        return Inertia::render('QuickRequest/Create', [
            'facilities' => $facilities,
            'providers' => $providers,
            'products' => $products,
            'woundTypes' => $woundTypes,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'npi' => $user->npi_number,
            ],
        ]);
    }
    
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            // Patient Information
            'patient_first_name' => 'required|string|max:255',
            'patient_last_name' => 'required|string|max:255',
            'patient_dob' => 'required|date',
            'patient_gender' => 'nullable|in:male,female,other,unknown',
            'patient_member_id' => 'nullable|string|max:255',
            'patient_address_line1' => 'nullable|string|max:255',
            'patient_address_line2' => 'nullable|string|max:255',
            'patient_city' => 'nullable|string|max:255',
            'patient_state' => 'nullable|string|max:2',
            'patient_zip' => 'nullable|string|max:10',
            'patient_phone' => 'nullable|string|max:20',
            
            // Product Information
            'product_id' => 'required|exists:msc_products,id',
            'size' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'manufacturer_fields' => 'nullable|array',
            
            // Service Information
            'facility_id' => 'required|exists:facilities,id',
            'payer_name' => 'required|string|max:255',
            'payer_id' => 'nullable|string|max:255',
            'expected_service_date' => 'required|date|after_or_equal:today',
            'wound_type' => 'required|string',
            'shipping_speed' => 'nullable|string',
            'place_of_service' => 'nullable|string',
            'insurance_type' => 'nullable|string',
            
            // Documentation
            'insurance_card_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'face_sheet' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'clinical_notes' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'wound_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            
            // Attestations
            'failed_conservative_treatment' => 'required|boolean|accepted',
            'information_accurate' => 'required|boolean|accepted',
            'medical_necessity_established' => 'required|boolean|accepted',
            'maintain_documentation' => 'required|boolean|accepted',
            'authorize_prior_auth' => 'nullable|boolean',
            
            // Provider Authorization
            'provider_name' => 'nullable|string|max:255',
            'provider_npi' => 'nullable|string|max:10',
            'signature_date' => 'nullable|date',
            'verbal_order' => 'nullable|array',
            
            // DocuSeal Integration
            'docuseal_submission_id' => 'nullable|string|max:255',
        ]);
        
        DB::beginTransaction();
        
        try {
            // Create patient record in FHIR (PHI-safe approach)
            $patientData = [
                'first_name' => $validated['patient_first_name'],
                'last_name' => $validated['patient_last_name'],
                'date_of_birth' => $validated['patient_dob'],
                'gender' => $validated['patient_gender'] ?? 'unknown',
                'member_id' => $validated['patient_member_id'],
                'phone' => $validated['patient_phone'],
                'address' => [
                    'line1' => $validated['patient_address_line1'],
                    'line2' => $validated['patient_address_line2'],
                    'city' => $validated['patient_city'],
                    'state' => $validated['patient_state'],
                    'zip' => $validated['patient_zip']
                ]
            ];
            
            // Create patient in FHIR and get identifiers
            $patientIdentifiers = $this->patientService->createPatientRecord(
                $patientData,
                $validated['facility_id']
            );
            
            // Create the product request with only non-PHI data
            $productRequest = new ProductRequest();
            $productRequest->id = Str::uuid();
            $productRequest->request_number = $this->generateRequestNumber();
            $productRequest->requester_id = Auth::id();
            $productRequest->facility_id = $validated['facility_id'];
            $productRequest->order_status = 'pending_review';
            $productRequest->submission_type = 'quick_request';
            
            // Store only patient identifiers, NOT PHI
            $productRequest->patient_fhir_id = $patientIdentifiers['patient_fhir_id'];
            $productRequest->patient_display_id = $patientIdentifiers['patient_display_id'];
            
            // Set product information
            $product = Product::find($validated['product_id']);
            $productRequest->product_id = $validated['product_id'];
            $productRequest->product_name = $product->name;
            $productRequest->product_code = $product->q_code;
            $productRequest->manufacturer = $product->manufacturer;
            $productRequest->size = $validated['size'];
            $productRequest->quantity = $validated['quantity'];
            
            // Set service information
            $productRequest->payer_name = $validated['payer_name'];
            $productRequest->payer_id = $validated['payer_id'];
            $productRequest->expected_service_date = $validated['expected_service_date'];
            $productRequest->wound_type = $validated['wound_type'];
            $productRequest->place_of_service = $validated['place_of_service'];
            $productRequest->insurance_type = $validated['insurance_type'];
            
            // Store manufacturer-specific fields in metadata
            $metadata = [
                'manufacturer_fields' => $validated['manufacturer_fields'] ?? [],
                'shipping_speed' => $validated['shipping_speed'],
                'attestations' => [
                    'failed_conservative_treatment' => $validated['failed_conservative_treatment'],
                    'information_accurate' => $validated['information_accurate'],
                    'medical_necessity_established' => $validated['medical_necessity_established'],
                    'maintain_documentation' => $validated['maintain_documentation'],
                    'authorize_prior_auth' => $validated['authorize_prior_auth'] ?? false,
                ],
                'provider_authorization' => [
                    'provider_name' => $validated['provider_name'] ?? Auth::user()->first_name . ' ' . Auth::user()->last_name,
                    'provider_npi' => $validated['provider_npi'] ?? Auth::user()->npi_number,
                    'signature_date' => $validated['signature_date'] ?? now()->format('Y-m-d'),
                    'verbal_order' => $validated['verbal_order'] ?? null,
                ],
                'docuseal_submission_id' => $validated['docuseal_submission_id'] ?? null,
            ];
            
            $productRequest->metadata = $metadata;
            
            // Handle file uploads with PHI protection
            $documentMetadata = [];
            
            if ($request->hasFile('insurance_card_front')) {
                $path = $request->file('insurance_card_front')->store('phi/insurance-cards/' . date('Y/m'), 's3-encrypted');
                $documentMetadata['insurance_card_front'] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file('insurance_card_front')->getSize(),
                    'mime_type' => $request->file('insurance_card_front')->getMimeType()
                ];
                
                // Audit PHI document upload
                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => 'insurance_card_front',
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'product_request_id' => $productRequest->id
                ]);
            }
            
            if ($request->hasFile('insurance_card_back')) {
                $path = $request->file('insurance_card_back')->store('phi/insurance-cards/' . date('Y/m'), 's3-encrypted');
                $documentMetadata['insurance_card_back'] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file('insurance_card_back')->getSize(),
                    'mime_type' => $request->file('insurance_card_back')->getMimeType()
                ];
                
                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => 'insurance_card_back',
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'product_request_id' => $productRequest->id
                ]);
            }
            
            if ($request->hasFile('face_sheet')) {
                $path = $request->file('face_sheet')->store('phi/face-sheets/' . date('Y/m'), 's3-encrypted');
                $documentMetadata['face_sheet'] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file('face_sheet')->getSize(),
                    'mime_type' => $request->file('face_sheet')->getMimeType()
                ];
                
                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => 'face_sheet',
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'product_request_id' => $productRequest->id
                ]);
            }
            
            if ($request->hasFile('clinical_notes')) {
                $path = $request->file('clinical_notes')->store('phi/clinical-notes/' . date('Y/m'), 's3-encrypted');
                $documentMetadata['clinical_notes'] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file('clinical_notes')->getSize(),
                    'mime_type' => $request->file('clinical_notes')->getMimeType()
                ];
                
                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => 'clinical_notes',
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'product_request_id' => $productRequest->id
                ]);
            }
            
            if ($request->hasFile('wound_photo')) {
                $path = $request->file('wound_photo')->store('phi/wound-photos/' . date('Y/m'), 's3-encrypted');
                $documentMetadata['wound_photo'] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file('wound_photo')->getSize(),
                    'mime_type' => $request->file('wound_photo')->getMimeType()
                ];
                
                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => 'wound_photo',
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'product_request_id' => $productRequest->id
                ]);
            }
            
            // Store document metadata in the metadata field
            $metadata['documents'] = $documentMetadata;
            
            $productRequest->save();
            
            DB::commit();
            
            return redirect()->route('admin.orders.show', $productRequest->id)
                ->with('success', 'Quick request submitted successfully!');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit quick request: ' . $e->getMessage()]);
        }
    }
    
    private function getUserFacilities($user)
    {
        $query = null;
        
        // If admin, return all facilities
        if ($user->hasRole('msc-admin')) {
            $query = Facility::with('organization');
        }
        // If office manager, return their facility
        elseif ($user->hasRole('office-manager')) {
            $query = Facility::where('office_manager_id', $user->id)
                ->with('organization');
        }
        // If provider, return facilities they're associated with
        elseif ($user->hasRole('provider')) {
            $facilities = $user->facilities()
                ->with('organization')
                ->orderBy('name')
                ->get();
                
            // If provider has no facilities, try to get facilities from their organization
            if ($facilities->isEmpty() && $user->current_organization_id) {
                $query = Facility::where('organization_id', $user->current_organization_id)
                    ->with('organization');
            } else {
                // Format and return the facilities we already have
                return $facilities->map(function ($facility) {
                    return $this->formatFacilityData($facility);
                });
            }
        }
        // For any other role, try to get facilities from their organization
        elseif ($user->current_organization_id) {
            $query = Facility::where('organization_id', $user->current_organization_id)
                ->with('organization');
        }
        
        if ($query) {
            return $query->orderBy('name')->get()->map(function ($facility) {
                return $this->formatFacilityData($facility);
            });
        }
        
        return collect();
    }
    
    /**
     * Format facility data to ensure address is available
     */
    private function formatFacilityData($facility)
    {
        // Build address string from components
        $addressParts = [];
        
        if (!empty($facility->address)) {
            // If address is already a string, use it
            $facility->address = $facility->address;
        } else {
            // Build address from components
            if (!empty($facility->address_line_1)) {
                $addressParts[] = $facility->address_line_1;
            }
            if (!empty($facility->address_line_2)) {
                $addressParts[] = $facility->address_line_2;
            }
            if (!empty($facility->city) && !empty($facility->state)) {
                $addressParts[] = $facility->city . ', ' . $facility->state;
                if (!empty($facility->zip_code)) {
                    $addressParts[count($addressParts) - 1] .= ' ' . $facility->zip_code;
                }
            }
            
            $facility->address = implode(', ', $addressParts);
        }
        
        return $facility;
    }
    
    private function getOrganizationProviders($user)
    {
        // Get providers based on user's organization
        $query = User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })
            ->select('id', 'first_name', 'last_name', 'npi_number');
        
        if ($user->current_organization_id) {
            $query->where('current_organization_id', $user->current_organization_id);
        }
        
        return $query->get()->map(function ($provider) {
            return [
                'id' => $provider->id,
                'name' => $provider->first_name . ' ' . $provider->last_name,
                'npi' => $provider->npi_number,
            ];
        });
    }
    
    private function generateRequestNumber()
    {
        $prefix = 'QR'; // Quick Request prefix
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        
        return "{$prefix}-{$date}-{$random}";
    }
    
    /**
     * Analyze insurance card using Azure Document Intelligence
     */
    public function analyzeInsuranceCard(Request $request)
    {
        $request->validate([
            'insurance_card_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        
        try {
            $azureService = new AzureDocumentIntelligenceService();
            
            // Analyze the insurance card(s)
            $extractedData = $azureService->analyzeInsuranceCard(
                $request->file('insurance_card_front'),
                $request->file('insurance_card_back')
            );
            
            // Map to patient form fields
            $formData = $azureService->mapToPatientForm($extractedData);
            
            // Audit PHI extraction from insurance card
            PhiAuditService::logRead('InsuranceCard', 'Azure-Analysis-' . uniqid(), [
                'purpose' => 'Insurance card OCR for patient registration',
                'card_types' => [
                    'front' => $request->hasFile('insurance_card_front'),
                    'back' => $request->hasFile('insurance_card_back')
                ],
                'extraction_successful' => !empty($formData)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $formData,
                'extracted_data' => $extractedData,
                'message' => 'Insurance card analyzed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Insurance card analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze insurance card: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check Azure Document Intelligence configuration status
     */
    public function checkAzureStatus()
    {
        try {
            $endpoint = config('services.azure_di.endpoint');
            $key = config('services.azure_di.key');
            
            return response()->json([
                'configured' => !empty($endpoint) && !empty($key),
                'endpoint_set' => !empty($endpoint),
                'key_set' => !empty($key),
                'api_version' => config('services.azure_di.api_version', '2024-02-29-preview'),
                'message' => (!empty($endpoint) && !empty($key)) 
                    ? 'Azure Document Intelligence is configured' 
                    : 'Azure Document Intelligence is not configured. Please set AZURE_DI_ENDPOINT and AZURE_DI_KEY in your .env file.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'configured' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Debug insurance card analysis - returns raw Azure response
     */
    public function debugInsuranceCard(Request $request)
    {
        $request->validate([
            'insurance_card_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        
        try {
            $azureService = new AzureDocumentIntelligenceService();
            
            // Analyze the card to trigger logging
            $extractedData = $azureService->analyzeInsuranceCard(
                $request->file('insurance_card_front')
            );
            
            // Map to patient form fields
            $formData = $azureService->mapToPatientForm($extractedData);
            
            // Return detailed debug information
            return response()->json([
                'success' => true,
                'message' => 'Check Laravel logs for detailed Azure response',
                'hint' => 'Look for "Azure Document Intelligence" entries in storage/logs/laravel.log',
                'extracted_member_id' => $formData['patient_member_id'] ?? 'NOT FOUND',
                'extracted_data_summary' => [
                    'member' => $extractedData['member'] ?? [],
                    'insurer' => $extractedData['insurer'] ?? null,
                    'payer_id' => $extractedData['payer_id'] ?? null,
                ],
                'mapped_form_fields' => array_keys($formData),
                'log_file' => 'storage/logs/laravel-' . date('Y-m-d') . '.log'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}