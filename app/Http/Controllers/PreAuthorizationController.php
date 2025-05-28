<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use App\Models\PreAuthorization;
use App\Jobs\SubmitPreAuthorizationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PreAuthorizationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:manage-pre-authorization']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Build base query for pre-authorization requests
        $query = PreAuthorization::with([
            'productRequest.provider:id,first_name,last_name,email',
            'productRequest.facility:id,name,city,state'
        ])
        ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('authorization_number', 'like', "%{$search}%")
                  ->orWhere('patient_id', 'like', "%{$search}%")
                  ->orWhereHas('productRequest', function ($q) use ($search) {
                      $q->where('request_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payer')) {
            $query->where('payer_name', 'like', "%{$request->input('payer')}%");
        }

        // Get paginated results
        $preAuths = $query->paginate(20)->withQueryString();

        // Transform for frontend with null safety
        $preAuths->getCollection()->transform(function ($preAuth) {
            // Guard against missing relations
            $productRequest = $preAuth->productRequest;
            $provider = $productRequest?->provider;
            $facility = $productRequest?->facility;

            return [
                'id' => $preAuth->id,
                'authorization_number' => $preAuth->authorization_number,
                'status' => $preAuth->status,
                'payer_name' => $preAuth->payer_name,
                'patient_id' => $preAuth->patient_id,
                'submitted_at' => $preAuth->submitted_at?->format('M j, Y H:i'),
                'expires_at' => $preAuth->expires_at?->format('M j, Y'),
                'estimated_approval_date' => $preAuth->estimated_approval_date?->format('M j, Y'),
                'product_request' => $productRequest ? [
                    'id' => $productRequest->id,
                    'request_number' => $productRequest->request_number,
                    'provider_name' => $provider ?
                        ($provider->first_name . ' ' . $provider->last_name) :
                        'Unknown Provider',
                    'facility_name' => $facility?->name ?? 'Unknown Facility',
                ] : null,
                'days_since_submission' => $preAuth->submitted_at ? $preAuth->submitted_at->diffInDays(now()) : 0,
                'priority' => $this->calculatePriority($preAuth),
            ];
        });

        // Get status counts
        $statusCounts = PreAuthorization::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return Inertia::render('PreAuthorization/Index', [
            'preAuths' => $preAuths,
            'filters' => $request->only(['search', 'status', 'payer']),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'product_request_id' => 'required|exists:product_requests,id',
            'payer_name' => 'required|string|max:255',
            'patient_id' => 'required|string|max:100',
            'diagnosis_codes' => 'required|array|min:1',
            'diagnosis_codes.*' => 'required|string|regex:/^[A-Z]\d{2}(\.\d{1,4})?$/', // ICD-10 format
            'procedure_codes' => 'required|array|min:1',
            'procedure_codes.*' => 'required|string|regex:/^\d{5}(-\d{2})?$/', // CPT format
            'clinical_documentation' => 'required|string',
            'urgency' => 'required|string|in:routine,urgent,expedited',
        ]);

        $productRequest = ProductRequest::findOrFail($validated['product_request_id']);

        DB::beginTransaction();
        try {
            // Create pre-authorization record with improved authorization number generation
            $preAuth = PreAuthorization::create([
                'product_request_id' => $productRequest->id,
                'authorization_number' => $this->generateAuthorizationNumber(),
                'payer_name' => $validated['payer_name'],
                'patient_id' => $validated['patient_id'],
                'diagnosis_codes' => $validated['diagnosis_codes'],
                'procedure_codes' => $validated['procedure_codes'],
                'clinical_documentation' => $validated['clinical_documentation'],
                'urgency' => $validated['urgency'],
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'estimated_approval_date' => $this->calculateEstimatedApprovalDate($validated['urgency']),
            ]);

            // Dispatch proper job class instead of closure
            SubmitPreAuthorizationJob::dispatch($preAuth->id);

            // Update product request status
            $productRequest->update([
                'pre_auth_status' => 'submitted',
                'pre_auth_submitted_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prior authorization submitted successfully.',
                'authorization_number' => $preAuth->authorization_number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit pre-authorization', [
                'error' => $e->getMessage(),
                'product_request_id' => $validated['product_request_id'],
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit prior authorization.',
            ], 500);
        }
    }

    public function status(Request $request)
    {
        $validated = $request->validate([
            'authorization_number' => 'required|string',
        ]);

        $preAuth = PreAuthorization::where('authorization_number', $validated['authorization_number'])
            ->with(['productRequest.provider', 'productRequest.facility'])
            ->firstOrFail();

        // Check status with payer system - only mock in non-production environments
        if (!app()->environment('production')) {
            $statusUpdate = $this->checkPayerSystemStatusMock($preAuth);
        } else {
            $statusUpdate = $this->checkPayerSystemStatus($preAuth);
        }

        if ($statusUpdate) {
            $preAuth->update($statusUpdate);
        }

        return response()->json([
            'authorization_number' => $preAuth->authorization_number,
            'status' => $preAuth->status,
            'payer_response' => $preAuth->payer_response,
            'approved_amount' => $preAuth->approved_amount,
            'expires_at' => $preAuth->expires_at?->format('Y-m-d'),
            'estimated_approval_date' => $preAuth->estimated_approval_date?->format('Y-m-d'),
            'last_updated' => $preAuth->updated_at->format('Y-m-d H:i:s'),
        ]);
    }

    // Private helper methods

    private function generateAuthorizationNumber(): string
    {
        // Use UUID-based approach to avoid database locks and deadlocks
        $datePrefix = now()->format('Ymd');
        $uniqueId = strtoupper(Str::random(8));
        
        return "PA-{$datePrefix}-{$uniqueId}";
    }

    private function calculateEstimatedApprovalDate(string $urgency): \Carbon\Carbon
    {
        $businessDays = match ($urgency) {
            'expedited' => 1,
            'urgent' => 3,
            'routine' => 7,
            default => 7,
        };

        return now()->addWeekdays($businessDays);
    }

    private function calculatePriority(PreAuthorization $preAuth): string
    {
        $daysSince = $preAuth->submitted_at ? $preAuth->submitted_at->diffInDays(now()) : 0;

        if ($preAuth->urgency === 'expedited' || $daysSince > 10) {
            return 'high';
        } elseif ($preAuth->urgency === 'urgent' || $daysSince > 5) {
            return 'medium';
        }

        return 'low';
    }

    private function checkPayerSystemStatus(PreAuthorization $preAuth): ?array
    {
        // Production implementation - check actual payer system status

        if (!$preAuth->payer_transaction_id) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get(config('payers.status_endpoint'), [
                'transaction_id' => $preAuth->payer_transaction_id,
            ]);

            if ($response->successful()) {
                $statusData = $response->json();

                $updates = [
                    'status' => $statusData['status'],
                    'last_status_check' => now(),
                    'payer_response' => $statusData,
                ];

                if ($statusData['status'] === 'approved') {
                    $updates['approved_at'] = now();
                    $updates['approved_amount'] = $statusData['approved_amount'] ?? null;
                    $updates['expires_at'] = $statusData['expires_at'] ?
                        \Carbon\Carbon::parse($statusData['expires_at']) : null;
                } elseif ($statusData['status'] === 'denied') {
                    $updates['denied_at'] = now();
                    $updates['denial_reason'] = $statusData['denial_reason'] ?? 'No reason provided';
                }

                return $updates;
            }
        } catch (\Exception $e) {
            Log::error('Failed to check payer system status', [
                'pre_auth_id' => $preAuth->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function checkPayerSystemStatusMock(PreAuthorization $preAuth): ?array
    {
        // Mock implementation for development/testing only

        if (!$preAuth->payer_transaction_id || $preAuth->status !== 'processing') {
            return null;
        }

        // Only update if enough time has passed to simulate realistic processing
        if ($preAuth->updated_at->diffInMinutes(now()) < 1) {
            return null;
        }

        // Simulate status progression based on urgency and time
        $minutesSinceSubmission = $preAuth->submitted_at->diffInMinutes(now());
        $mockStatus = 'processing';

        if ($preAuth->urgency === 'expedited' && $minutesSinceSubmission > 5) {
            $mockStatus = 'approved';
        } elseif ($preAuth->urgency === 'urgent' && $minutesSinceSubmission > 15) {
            $mockStatus = rand(1, 10) <= 8 ? 'approved' : 'denied'; // 80% approval rate
        } elseif ($preAuth->urgency === 'routine' && $minutesSinceSubmission > 30) {
            $mockStatus = rand(1, 10) <= 7 ? 'approved' : 'denied'; // 70% approval rate
        }

        if ($mockStatus === $preAuth->status) {
            return null; // No change
        }

        $updates = [
            'status' => $mockStatus,
            'last_status_check' => now(),
        ];

        if ($mockStatus === 'approved') {
            $updates['approved_at'] = now();
            $updates['approved_amount'] = rand(500, 2000);
            $updates['expires_at'] = now()->addMonths(6);
        } elseif ($mockStatus === 'denied') {
            $updates['denied_at'] = now();
            $updates['denial_reason'] = 'Insufficient clinical documentation';
        }

        return $updates;
    }
}
