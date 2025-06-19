<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\PatientIVRStatus;
use App\Models\Fhir\Facility;
use App\Services\PatientService;
use App\Services\PhiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;

class QuickRequestController extends Controller
{
    protected $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    /**
     * Display the quick request form
     */
    public function create()
    {
        $user = Auth::user()->load([
            'providerProfile',
            'providerCredentials',
            'organizations' => fn($q) => $q->where('organization_user.current', true),
            'facilities'
        ]);

        $currentOrg = $user->organizations->first();
        $primaryFacility = $user->facilities()->where('facility_user.is_primary', true)->first() ?? $user->facilities->first();

        $prefillData = [
            'provider_name' => $user->first_name . ' ' . $user->last_name,
            'provider_npi' => $user->providerCredentials->where('credential_type', 'npi_number')->first()->credential_number ?? null,

            'organization_name' => $currentOrg->name ?? null,
            'organization_tax_id' => $currentOrg->tax_id ?? null,

            'facility_id' => $primaryFacility->id ?? null,
            'facility_name' => $primaryFacility->name ?? null,
            'facility_address' => $primaryFacility->full_address ?? null,
            'facility_phone' => $primaryFacility->phone ?? null,
            'facility_npi' => $primaryFacility->npi ?? null,
            'default_place_of_service' => $primaryFacility->default_place_of_service ?? '11',

            'billing_address' => $currentOrg->billing_address ?? null,
            'billing_city' => $currentOrg->billing_city ?? null,
            'billing_state' => $currentOrg->billing_state ?? null,
            'billing_zip' => $currentOrg->billing_zip ?? null,

            'ap_contact_name' => $currentOrg->ap_contact_name ?? null,
            'ap_contact_email' => $currentOrg->ap_contact_email ?? null,
            'ap_contact_phone' => $currentOrg->ap_contact_phone ?? null,
        ];

        $products = Product::where('is_active', true)
            ->whereNotNull('manufacturer_id')
            ->get(['id', 'name', 'q_code', 'manufacturer_id', 'manufacturer', 'sizes']);

        return Inertia::render('QuickRequest/Create', [
            'facilities' => $user->facilities,
            'products' => $products,
            'prefillData' => $prefillData,
        ]);
    }

    /**
     * Store a new quick request
     * ASHLEY'S REQUIREMENT: Validate that IVR was completed by provider
     */
    public function store(Request $request)
    {
        // ASHLEY'S REQUIREMENT: Validate that IVR was completed by provider
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
            'caregiver_name' => 'nullable|string|max:255',
            'caregiver_relationship' => 'nullable|string|max:255',
            'caregiver_phone' => 'nullable|string|max:20',

            // Product Information
            'product_id' => 'required|exists:products,id',
            'size' => 'required|string|max:50',
            'quantity' => 'required|integer|min:1|max:100',
            'manufacturer_fields' => 'nullable|array',

            // Service Information
            'facility_id' => 'required|exists:facilities,id',
            'payer_name' => 'required|string|max:255',
            'payer_id' => 'nullable|string|max:255',
            'expected_service_date' => 'required|date|after:today',
            'delivery_date' => 'nullable|date',
            'wound_type' => 'required|string|max:255',
            'place_of_service' => 'required|string|max:10',
            'insurance_type' => 'required|string|max:255',
            'shipping_speed' => 'required|string|max:50',

            // Clinical Attestations
            'failed_conservative_treatment' => 'required|boolean',
            'information_accurate' => 'required|boolean',
            'medical_necessity_established' => 'required|boolean',
            'maintain_documentation' => 'required|boolean',
            'authorize_prior_auth' => 'nullable|boolean',

            // Provider Authorization
            'provider_name' => 'nullable|string|max:255',
            'provider_npi' => 'nullable|string|max:20',
            'signature_date' => 'nullable|date',
            'verbal_order' => 'nullable|array',

            // ASHLEY'S REQUIREMENT: IVR must be completed before submission
            'docuseal_submission_id' => 'required|string|min:1',

            // File uploads
            'insurance_card_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'face_sheet' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'clinical_notes' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'wound_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ], [
            'docuseal_submission_id.required' => 'IVR completion is required before submitting your order. Please complete the IVR form in Step 4.',
            'expected_service_date.after' => 'Service date must be in the future.',
            'product_id.required' => 'Please select a product.',
            'facility_id.required' => 'Please select a facility.',
        ]);

        DB::beginTransaction();

        try {
            // Prepare patient data for FHIR
            $patientData = [
                'first_name' => $validated['patient_first_name'],
                'last_name' => $validated['patient_last_name'],
                'date_of_birth' => $validated['patient_dob'],
                'gender' => $validated['patient_gender'] ?? 'unknown',
                'member_id' => $validated['patient_member_id'],
                'address' => [
                    'line1' => $validated['patient_address_line1'],
                    'line2' => $validated['patient_address_line2'],
                    'city' => $validated['patient_city'],
                    'state' => $validated['patient_state'],
                    'postal_code' => $validated['patient_zip'],
                ],
                'phone' => $validated['patient_phone'],
                'caregiver' => [
                    'name' => $validated['caregiver_name'],
                    'relationship' => $validated['caregiver_relationship'],
                    'phone' => $validated['caregiver_phone'],
                ],
            ];

            // Create patient in FHIR and get identifiers
            $patientIdentifiers = $this->patientService->createPatientRecord(
                $patientData,
                $validated['facility_id']
            );

            // Get product information
            $product = Product::find($validated['product_id']);
            $manufacturerId = $product->manufacturer_id ?? $product->manufacturer;

            // ASHLEY'S REQUIREMENT: Find or create episode for patient+manufacturer
            $episode = $this->findOrCreateEpisode(
                $patientIdentifiers['patient_fhir_id'],
                $manufacturerId,
                $patientIdentifiers['patient_display_id'],
                $validated['docuseal_submission_id']
            );

            // Create the product request with provider-generated IVR
            $productRequest = new ProductRequest();
            $productRequest->id = Str::uuid();
            $productRequest->request_number = $this->generateRequestNumber();
            $productRequest->requester_id = Auth::id();
            $productRequest->facility_id = $validated['facility_id'];

            // ASHLEY'S REQUIREMENT: Status indicates provider completed IVR
            $productRequest->order_status = 'ready_for_review'; // Not 'pending_ivr'
            $productRequest->submission_type = 'quick_request';

            // Store only patient identifiers, NOT PHI
            $productRequest->patient_fhir_id = $patientIdentifiers['patient_fhir_id'];
            $productRequest->patient_display_id = $patientIdentifiers['patient_display_id'];

            // Set product information
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
            $productRequest->delivery_date = $validated['delivery_date'] ??
                Carbon::parse($validated['expected_service_date'])->subDay(); // Default to day before
            $productRequest->wound_type = $validated['wound_type'];
            $productRequest->place_of_service = $validated['place_of_service'];
            $productRequest->insurance_type = $validated['insurance_type'];

            // ASHLEY'S REQUIREMENT: Store IVR completion info
            $productRequest->docuseal_submission_id = $validated['docuseal_submission_id'];
            $productRequest->provider_ivr_completed_at = now();
            $productRequest->ivr_status = 'provider_completed';

            // Store metadata
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
                'ivr_submission' => [
                    'docuseal_submission_id' => $validated['docuseal_submission_id'],
                    'completed_at' => now(),
                    'completed_by' => Auth::id(),
                ],
            ];

            // Handle file uploads with PHI protection
            $documentMetadata = [];
            $documentTypes = [
                'insurance_card_front' => 'phi/insurance-cards/',
                'insurance_card_back' => 'phi/insurance-cards/',
                'face_sheet' => 'phi/face-sheets/',
                'clinical_notes' => 'phi/clinical-notes/',
                'wound_photo' => 'phi/wound-photos/',
            ];

            foreach ($documentTypes as $fieldName => $storagePath) {
                if ($request->hasFile($fieldName)) {
                    $path = $request->file($fieldName)->store($storagePath . date('Y/m'), 's3-encrypted');
                    $documentMetadata[$fieldName] = [
                        'path' => $path,
                        'uploaded_at' => now(),
                        'size' => $request->file($fieldName)->getSize(),
                        'mime_type' => $request->file($fieldName)->getMimeType()
                    ];

                    // Audit PHI document upload
                    PhiAuditService::logCreation('Document', $path, [
                        'document_type' => $fieldName,
                        'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                        'product_request_id' => $productRequest->id
                    ]);
                }
            }

            $metadata['documents'] = $documentMetadata;
            $productRequest->metadata = $metadata;

            // Link to episode
            $productRequest->ivr_episode_id = $episode->id;

            $productRequest->save();

            // Update episode status
            $episode->update([
                'status' => 'ready_for_review',
                'ivr_status' => 'provider_completed',
                'last_order_date' => now(),
            ]);

            DB::commit();

            return redirect()->route('admin.order-center.show', $productRequest->id)
                ->with('success', 'Order submitted successfully with IVR completed! Your order is now ready for admin review.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit order: ' . $e->getMessage()]);
        }
    }

    /**
     * ASHLEY'S REQUIREMENT: Find or create episode for patient+manufacturer combination
     */
    private function findOrCreateEpisode($patientFhirId, $manufacturerId, $patientDisplayId, $docusealSubmissionId = null)
    {
        // Find existing episode for this patient+manufacturer combination
        $episode = PatientIVRStatus::where('patient_id', $patientFhirId)
            ->where('manufacturer_id', $manufacturerId)
            ->where(function($q) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', now());
            })
            ->first();

        if (!$episode) {
            $episode = PatientIVRStatus::create([
                'id' => Str::uuid(),
                'patient_id' => $patientFhirId, // FHIR ID for patient
                'patient_display_id' => $patientDisplayId, // De-identified display ID
                'manufacturer_id' => $manufacturerId,
                'status' => 'ready_for_review', // Provider submitted with IVR
                'ivr_status' => 'provider_completed',
                'docuseal_submission_id' => $docusealSubmissionId, // Store DocuSeal ID
                'verification_date' => now(),
                'expiration_date' => now()->addMonths(3), // Default 3-month expiration
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Update existing episode with new DocuSeal submission if provided
            if ($docusealSubmissionId && !$episode->docuseal_submission_id) {
                $episode->update([
                    'docuseal_submission_id' => $docusealSubmissionId,
                    'ivr_status' => 'provider_completed',
                    'verification_date' => now(),
                ]);
            }
        }

        return $episode;
    }

    /**
     * Generate a unique request number
     */
    private function generateRequestNumber()
    {
        $prefix = 'QR';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }
}
