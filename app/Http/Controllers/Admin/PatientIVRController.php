<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientIVRStatus;
use App\Models\Order\Manufacturer;
use App\Services\FhirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PatientIVRController extends Controller
{
    private FhirService $fhirService;

    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    /**
     * Display the patient IVR status dashboard
     */
    public function index(Request $request)
    {
        $query = PatientIVRStatus::with('manufacturer');

        // Apply filters
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            // Search by patient name requires FHIR lookup, so we'll search by display ID for now
            $query->where('patient_fhir_id', 'like', "%{$search}%");
        }

        if ($request->has('status') && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('manufacturer') && $request->input('manufacturer')) {
            $query->where('manufacturer_id', $request->input('manufacturer'));
        }

        if ($request->has('expiring_soon') && $request->input('expiring_soon')) {
            $query->whereBetween('expiration_date', [now(), now()->addDays(30)]);
        }

        // Get all IVRs
        $patientIVRs = $query->orderBy('expiration_date')->get();

        // Get expiring IVRs (within 30 days)
        $expiringIVRs = PatientIVRStatus::getExpiringIVRs(30);

        // Get manufacturers for filter
        $manufacturers = Manufacturer::select('id', 'name')
            ->orderBy('name')
            ->get();

        // Transform IVRs to include patient names
        $transformedIVRs = $patientIVRs->map(function ($ivr) {
            return [
                'id' => $ivr->id,
                'patient_fhir_id' => $ivr->patient_fhir_id,
                'patient_name' => $this->getPatientName($ivr->patient_fhir_id),
                'patient_display_id' => $this->getPatientDisplayId($ivr->patient_fhir_id),
                'manufacturer' => [
                    'id' => $ivr->manufacturer->id,
                    'name' => $ivr->manufacturer->name,
                ],
                'last_verified_date' => $ivr->last_verified_date?->format('Y-m-d'),
                'expiration_date' => $ivr->expiration_date?->format('Y-m-d'),
                'frequency' => $ivr->frequency,
                'status' => $ivr->status,
                'latest_docuseal_submission_id' => $ivr->latest_docuseal_submission_id,
                'notes' => $ivr->notes,
            ];
        });

        // Transform expiring IVRs
        $transformedExpiringIVRs = $expiringIVRs->map(function ($ivr) {
            return [
                'id' => $ivr->id,
                'patient_fhir_id' => $ivr->patient_fhir_id,
                'patient_name' => $this->getPatientName($ivr->patient_fhir_id),
                'patient_display_id' => $this->getPatientDisplayId($ivr->patient_fhir_id),
                'manufacturer' => [
                    'id' => $ivr->manufacturer->id,
                    'name' => $ivr->manufacturer->name,
                ],
                'expiration_date' => $ivr->expiration_date?->format('Y-m-d'),
            ];
        });

        return Inertia::render('Admin/Patients/IVRStatus', [
            'patientIVRs' => $transformedIVRs,
            'expiringIVRs' => $transformedExpiringIVRs,
            'filters' => $request->only(['search', 'status', 'manufacturer', 'expiring_soon']),
            'manufacturers' => $manufacturers,
        ]);
    }

    /**
     * Update IVR status when a new IVR is verified
     */
    public function updateStatus(Request $request, $patientFhirId, $manufacturerId)
    {
        $request->validate([
            'docuseal_submission_id' => 'nullable|string',
            'frequency' => 'nullable|in:weekly,monthly,quarterly,yearly',
        ]);

        try {
            $ivrStatus = PatientIVRStatus::firstOrCreate(
                [
                    'patient_fhir_id' => $patientFhirId,
                    'manufacturer_id' => $manufacturerId,
                ],
                [
                    'frequency' => $request->input('frequency', 'quarterly'),
                    'status' => 'pending',
                ]
            );

            // Mark as verified
            $ivrStatus->markAsVerified($request->input('docuseal_submission_id'));

            return response()->json([
                'success' => true,
                'message' => 'IVR status updated successfully',
                'ivr_status' => $ivrStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update IVR status', [
                'patient_fhir_id' => $patientFhirId,
                'manufacturer_id' => $manufacturerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update IVR status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get patient name from FHIR
     */
    private function getPatientName(?string $patientFhirId): string
    {
        if (!$patientFhirId) {
            return 'Unknown Patient';
        }

        // Cache patient names for 1 hour to improve performance
        return Cache::remember("patient_name_{$patientFhirId}", 3600, function () use ($patientFhirId) {
            try {
                // Extract the FHIR ID from the reference (format: "Patient/123")
                $fhirId = str_replace('Patient/', '', $patientFhirId);

                // Fetch patient from FHIR
                $patient = $this->fhirService->getPatientById($fhirId);

                if (!$patient) {
                    Log::warning('Patient not found in FHIR', ['fhir_id' => $fhirId]);
                    return 'Patient Not Found';
                }

                // Extract name from FHIR resource
                if (isset($patient['name']) && is_array($patient['name']) && count($patient['name']) > 0) {
                    $name = $patient['name'][0];
                    $firstName = isset($name['given']) && is_array($name['given']) ? implode(' ', $name['given']) : '';
                    $lastName = $name['family'] ?? '';

                    $fullName = trim($firstName . ' ' . $lastName);

                    return $fullName ?: 'Unknown Patient';
                }

                return 'Unknown Patient';
            } catch (\Exception $e) {
                Log::error('Failed to fetch patient name from FHIR', [
                    'patient_fhir_id' => $patientFhirId,
                    'error' => $e->getMessage()
                ]);

                // Return a fallback name to avoid breaking the UI
                return 'Patient (Error)';
            }
        });
    }

    /**
     * Get patient display ID from database
     */
    private function getPatientDisplayId(?string $patientFhirId): string
    {
        if (!$patientFhirId) {
            return 'Unknown';
        }

        // Cache display IDs for 1 hour
        return Cache::remember("patient_display_id_{$patientFhirId}", 3600, function () use ($patientFhirId) {
            // Get the most recent product request for this patient
            $productRequest = \App\Models\Order\ProductRequest::where('patient_fhir_id', $patientFhirId)
                ->select('patient_display_id')
                ->first();

            return $productRequest ? $productRequest->patient_display_id : 'Unknown';
        });
    }
}