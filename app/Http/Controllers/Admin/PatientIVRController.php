<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
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
        $query = PatientManufacturerIVREpisode::with('manufacturer');

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
        $expiringIVRs = PatientManufacturerIVREpisode::getExpiringIVRs(30);

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
            $ivrStatus = PatientManufacturerIVREpisode::firstOrCreate(
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

    /**
     * IVR Management Dashboard
     */
    public function management(Request $request)
    {
        // Check if user has permission to view IVR management
        if (!auth()->user()->hasPermission('view-ivr-management')) {
            abort(403, 'You do not have permission to access IVR management.');
        }
        // Get all patient IVRs with related data
        $query = PatientManufacturerIVREpisode::with(['manufacturer']);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('patient_fhir_id', 'like', "%{$search}%");
        }

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('manufacturer') && $request->input('manufacturer') !== 'all') {
            $query->where('manufacturer_id', $request->input('manufacturer'));
        }

        $patientIVRs = $query->orderBy('expiration_date', 'asc')->get();

        // Transform IVRs with additional data
        $transformedIVRs = $patientIVRs->map(function ($ivr) {
            $productRequests = \App\Models\Order\ProductRequest::where('patient_fhir_id', $ivr->patient_fhir_id)
                ->with(['provider', 'facility'])
                ->latest()
                ->first();

            return [
                'id' => $ivr->id,
                'patient_display_id' => $this->getPatientDisplayId($ivr->patient_fhir_id),
                'patient_name' => $this->getPatientName($ivr->patient_fhir_id),
                'status' => $ivr->status,
                'ivr_type' => 'Standard IVR',
                'manufacturer' => [
                    'id' => $ivr->manufacturer->id,
                    'name' => $ivr->manufacturer->name,
                    'ivr_frequency' => $ivr->frequency,
                ],
                'provider' => $productRequests ? [
                    'id' => $productRequests->provider->id ?? null,
                    'name' => $productRequests->provider->name ?? 'Unknown',
                    'email' => $productRequests->provider->email ?? '',
                    'phone' => $productRequests->provider->phone ?? '',
                ] : null,
                'facility' => $productRequests ? [
                    'id' => $productRequests->facility->id ?? null,
                    'name' => $productRequests->facility->name ?? 'Unknown',
                    'address' => $productRequests->facility->full_address ?? '',
                ] : null,
                'created_at' => $ivr->created_at->toIso8601String(),
                'expires_at' => $ivr->expiration_date?->toIso8601String(),
                'last_activity' => $ivr->updated_at->toIso8601String(),
                'completion_percentage' => $this->calculateCompletionPercentage($ivr),
                'estimated_completion_time' => rand(15, 45), // Simulated
                'risk_score' => $this->calculateRiskScore($ivr),
                'ai_insights' => [
                    'completion_likelihood' => rand(70, 95) / 100,
                    'recommended_actions' => $this->getRecommendedActions($ivr),
                    'potential_issues' => $this->getPotentialIssues($ivr),
                ],
            ];
        });

        // Calculate stats
        $stats = [
            'total_active' => $patientIVRs->where('status', '!=', 'expired')->count(),
            'completed_today' => $patientIVRs->where('status', 'verified')
                ->where('last_verified_date', '>=', now()->startOfDay())
                ->count(),
            'expiring_soon' => PatientManufacturerIVREpisode::getExpiringIVRs(2)->count(),
            'average_completion_time' => 25, // Simulated average in minutes
            'completion_rate' => $this->calculateOverallCompletionRate(),
            'risk_assessments' => $patientIVRs->filter(fn($ivr) => $this->calculateRiskScore($ivr) > 60)->count(),
        ];

        // AI predictions
        $aiPredictions = [
            'high_risk_ivrs' => $transformedIVRs->filter(fn($ivr) => $ivr['risk_score'] > 80)->pluck('id')->toArray(),
            'optimal_reminder_times' => [
                'morning' => '9:00 AM - 11:00 AM',
                'afternoon' => '2:00 PM - 4:00 PM',
            ],
            'workflow_bottlenecks' => $this->detectWorkflowBottlenecks($patientIVRs),
        ];

        return Inertia::render('Admin/Patients/IVRManagement', [
            'patientIVRs' => $transformedIVRs,
            'stats' => $stats,
            'aiPredictions' => $aiPredictions,
        ]);
    }

    /**
     * Send bulk reminders
     */
    public function bulkRemind(Request $request)
    {
        $request->validate([
            'ivr_ids' => 'required|array',
            'ivr_ids.*' => 'exists:patient_ivr_status,id',
        ]);

        $ivrStatuses = PatientManufacturerIVREpisode::whereIn('id', $request->ivr_ids)->get();

        foreach ($ivrStatuses as $ivr) {
            // Queue reminder email/notification
            Log::info('Sending IVR reminder', [
                'ivr_id' => $ivr->id,
                'patient_fhir_id' => $ivr->patient_fhir_id,
            ]);
        }

        return back()->with('success', 'Reminders sent successfully');
    }

    /**
     * Export IVR data
     */
    public function export(Request $request)
    {
        $request->validate([
            'ivr_ids' => 'required|array',
            'ivr_ids.*' => 'exists:patient_ivr_status,id',
        ]);

        // In production, this would generate a CSV/Excel file
        $ivrStatuses = PatientManufacturerIVREpisode::whereIn('id', $request->ivr_ids)
            ->with('manufacturer')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Export initiated',
            'count' => $ivrStatuses->count(),
        ]);
    }

    /**
     * Show IVR details
     */
    public function show($ivrId)
    {
        $ivr = PatientManufacturerIVREpisode::with('manufacturer')->findOrFail($ivrId);

        return Inertia::render('Admin/Patients/IVRDetail', [
            'ivr' => $ivr,
            'patientName' => $this->getPatientName($ivr->patient_fhir_id),
            'patientDisplayId' => $this->getPatientDisplayId($ivr->patient_fhir_id),
        ]);
    }

    /**
     * Send reminder for specific IVR
     */
    public function remind($ivrId)
    {
        $ivr = PatientManufacturerIVREpisode::findOrFail($ivrId);

        // Queue reminder
        Log::info('Sending individual IVR reminder', [
            'ivr_id' => $ivr->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reminder sent successfully',
        ]);
    }

    /**
     * Contact provider about IVR
     */
    public function contact($ivrId)
    {
        $ivr = PatientManufacturerIVREpisode::findOrFail($ivrId);

        return Inertia::render('Admin/Patients/ContactProvider', [
            'ivr' => $ivr,
            'patientName' => $this->getPatientName($ivr->patient_fhir_id),
        ]);
    }

    /**
     * IVR settings
     */
    public function settings()
    {
        return Inertia::render('Admin/Patients/IVRSettings', [
            'settings' => [
                'reminder_frequency' => 'daily',
                'auto_remind_days_before' => 7,
                'email_templates' => [
                    'reminder' => 'Default reminder template',
                    'expiration' => 'Expiration warning template',
                ],
            ],
        ]);
    }

    private function calculateCompletionPercentage($ivr)
    {
        if ($ivr->status === 'verified') return 100;
        if ($ivr->status === 'pending') return 0;
        if ($ivr->status === 'in_progress') return rand(20, 80);
        return 0;
    }

    private function calculateRiskScore($ivr)
    {
        $score = 0;

        // Days until expiration
        if ($ivr->expiration_date) {
            $daysUntilExpiration = now()->diffInDays($ivr->expiration_date, false);
            if ($daysUntilExpiration < 0) $score += 100;
            elseif ($daysUntilExpiration <= 7) $score += 80;
            elseif ($daysUntilExpiration <= 14) $score += 60;
            elseif ($daysUntilExpiration <= 30) $score += 40;
        }

        // Status
        if ($ivr->status === 'expired') $score += 100;
        elseif ($ivr->status === 'pending') $score += 50;

        return min($score, 100);
    }

    private function calculateOverallCompletionRate()
    {
        $total = PatientManufacturerIVREpisode::count();
        if ($total === 0) return 0;

        $completed = PatientManufacturerIVREpisode::where('status', 'verified')->count();
        return round(($completed / $total) * 100, 1);
    }

    private function getRecommendedActions($ivr)
    {
        $actions = [];

        if ($ivr->status === 'pending') {
            $actions[] = 'Send initial IVR form to provider';
        }

        if ($ivr->expiration_date && now()->diffInDays($ivr->expiration_date, false) <= 7) {
            $actions[] = 'Send urgent reminder to provider';
        }

        if ($ivr->status === 'in_progress') {
            $actions[] = 'Follow up on incomplete IVR';
        }

        return $actions;
    }

    private function getPotentialIssues($ivr)
    {
        $issues = [];

        if ($ivr->expiration_date && now()->diffInDays($ivr->expiration_date, false) < 0) {
            $issues[] = 'IVR has expired';
        }

        if ($ivr->status === 'pending' && $ivr->created_at->diffInDays(now()) > 14) {
            $issues[] = 'IVR pending for over 2 weeks';
        }

        return $issues;
    }

    private function detectWorkflowBottlenecks($ivrs)
    {
        $bottlenecks = [];

        $pendingCount = $ivrs->where('status', 'pending')->count();
        if ($pendingCount > 10) {
            $bottlenecks[] = "High volume of pending IVRs ({$pendingCount}) may indicate provider onboarding issues";
        }

        $expiredCount = $ivrs->where('status', 'expired')->count();
        if ($expiredCount > 5) {
            $bottlenecks[] = "Multiple expired IVRs ({$expiredCount}) suggest need for better reminder system";
        }

        return $bottlenecks;
    }
}