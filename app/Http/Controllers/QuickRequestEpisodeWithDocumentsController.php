<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Fhir\Facility;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QuickRequestEpisodeWithDocumentsController extends Controller
{
    protected $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    /**
     * Create episode with document processing and AI extraction
     */
    public function createEpisodeWithDocuments(Request $request)
    {
        $validated = $request->validate([
            "provider_id" => "required|exists:users,id",
            "facility_id" => "required|exists:facilities,id",
            "patient_name" => "required|string|max:255",
            "request_type" => "required|string|in:new_request,reverification,additional_applications",
            "documents.*" => "file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240", // 10MB max
        ]);

        DB::beginTransaction();
        
        try {
            // Get provider and facility information
            $provider = User::find($validated["provider_id"]);
            $facility = Facility::find($validated["facility_id"]);
            
            if (!$provider || !$facility) {
                return response()->json([
                    "success" => false,
                    "message" => "Invalid provider or facility"
                ], 422);
            }

            // Generate patient display ID for episode tracking
            $patientDisplayId = $this->generatePatientDisplayId($validated["patient_name"]);

            // Create FHIR Patient resource first
            $patientResult = $this->createFhirPatient($validated["patient_name"], $facility);
            
            if (!$patientResult["success"]) {
                throw new \Exception("Failed to create FHIR patient: " . $patientResult["message"]);
            }

            $patientFhirId = $patientResult["patient_fhir_id"];

            // Process uploaded documents if any
            $extractedData = [];
            $documentUrls = [];
            
            if ($request->hasFile("documents")) {
                $documentResult = $this->processDocuments($request->file("documents"), $patientDisplayId);
                $extractedData = $documentResult["extracted_data"];
                $documentUrls = $documentResult["document_urls"];
            }

            // Create the episode with initial status
            $episode = PatientManufacturerIVREpisode::create([
                "id" => Str::uuid(),
                "patient_id" => $patientFhirId,
                "patient_fhir_id" => $patientFhirId,
                "patient_display_id" => $patientDisplayId,
                "manufacturer_id" => null, // Will be set when product is selected
                "status" => "draft", // Draft status until product selection
                "ivr_status" => "pending",
                "created_by" => Auth::id(),
                "metadata" => [
                    "provider_id" => $validated["provider_id"],
                    "facility_id" => $validated["facility_id"],
                    "request_type" => $validated["request_type"],
                    "patient_name" => $validated["patient_name"],
                    "document_urls" => $documentUrls,
                    "extracted_data" => $extractedData,
                    "created_from" => "quick_request_episode_workflow",
                    "workflow_version" => "2.0",
                ]
            ]);

            DB::commit();

            Log::info("Created episode with document processing", [
                "episode_id" => $episode->id,
                "patient_display_id" => $patientDisplayId,
                "patient_fhir_id" => $patientFhirId,
                "provider_id" => $validated["provider_id"],
                "facility_id" => $validated["facility_id"],
                "documents_count" => count($documentUrls),
                "extracted_fields_count" => count($extractedData),
            ]);

            return response()->json([
                "success" => true,
                "episode_id" => $episode->id,
                "patient_fhir_id" => $patientFhirId,
                "patient_display_id" => $patientDisplayId,
                "extracted_data" => $this->formatExtractedDataForForm($extractedData, $provider, $facility),
                "document_urls" => $documentUrls,
                "message" => "Episode created successfully with " . count($documentUrls) . " documents processed"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to create episode with documents", [
                "error" => $e->getMessage(),
                "provider_id" => $validated["provider_id"],
                "facility_id" => $validated["facility_id"],
                "patient_name" => $validated["patient_name"],
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to create episode: " . $e->getMessage(),
            ], 500);
        }
    }

    private function generatePatientDisplayId(string $patientName): string
    {
        $nameParts = explode(" ", trim($patientName));
        $initials = "";
        
        foreach ($nameParts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        
        $date = now()->format("ymd");
        $random = strtoupper(Str::random(4));
        
        return "PAT-{$initials}-{$date}-{$random}";
    }

    private function createFhirPatient(string $patientName, $facility): array
    {
        try {
            $nameParts = explode(" ", trim($patientName));
            $firstName = $nameParts[0] ?? "";
            $lastName = count($nameParts) > 1 ? end($nameParts) : "";
            
            $patientData = [
                "first_name" => $firstName,
                "last_name" => $lastName,
                "date_of_birth" => null,
                "gender" => "unknown",
                "phone" => null,
                "email" => null,
                "address_line_1" => null,
                "address_line_2" => null,
                "city" => null,
                "state" => null,
                "zip_code" => null,
            ];

            $result = $this->patientService->createPatient($patientData);
            
            if ($result["success"]) {
                return [
                    "success" => true,
                    "patient_fhir_id" => $result["patient_fhir_id"]
                ];
            } else {
                return [
                    "success" => false,
                    "message" => $result["message"] ?? "Unknown error creating patient"
                ];
            }
            
        } catch (\Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    private function processDocuments(array $files, string $patientDisplayId): array
    {
        $documentUrls = [];
        $extractedData = [];

        foreach ($files as $index => $file) {
            try {
                $filename = $patientDisplayId . "_" . $index . "_" . time() . "." . $file->getClientOriginalExtension();
                $path = $file->storeAs("episodes/documents", $filename, "private");
                $documentUrls[] = [
                    "original_name" => $file->getClientOriginalName(),
                    "stored_path" => $path,
                    "file_type" => $file->getClientMimeType(),
                    "file_size" => $file->getSize(),
                ];

                $extractedData = array_merge($extractedData, $this->simulateDocumentExtraction($file));

            } catch (\Exception $e) {
                Log::error("Failed to process document", [
                    "filename" => $file->getClientOriginalName(),
                    "error" => $e->getMessage()
                ]);
            }
        }

        return [
            "document_urls" => $documentUrls,
            "extracted_data" => $extractedData
        ];
    }

    private function simulateDocumentExtraction($file): array
    {
        $filename = strtolower($file->getClientOriginalName());
        $extractedData = [];
        
        if (str_contains($filename, "insurance") || str_contains($filename, "card")) {
            $extractedData = [
                "primary_insurance_name" => "Sample Insurance Co.",
                "primary_member_id" => "INS123456789",
            ];
        } elseif (str_contains($filename, "face") || str_contains($filename, "demo")) {
            $extractedData = [
                "patient_dob" => "1980-01-15",
                "patient_gender" => "male",
                "patient_phone" => "(555) 123-4567",
                "patient_address_line1" => "123 Main St",
                "patient_city" => "Anytown",
                "patient_state" => "CA",
                "patient_zip" => "90210",
            ];
        }
        
        return $extractedData;
    }

    private function formatExtractedDataForForm(array $extractedData, $provider, $facility): array
    {
        $formData = [
            "provider_name" => $provider->first_name . " " . $provider->last_name,
            "provider_npi" => $provider->npi_number,
            "facility_name" => $facility->name,
            "facility_address" => $facility->full_address,
        ];

        if (!empty($extractedData)) {
            foreach ($extractedData as $key => $value) {
                $formData[$key] = $value;
            }
        }

        return $formData;
    }
}
