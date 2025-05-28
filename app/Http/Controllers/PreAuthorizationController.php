<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use App\Models\PreAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

        // Transform for frontend
        $preAuths->getCollection()->transform(function ($preAuth) {
            return [
                'id' => $preAuth->id,
                'authorization_number' => $preAuth->authorization_number,
                'status' => $preAuth->status,
                'payer_name' => $preAuth->payer_name,
                'patient_id' => $preAuth->patient_id,
                'submitted_at' => $preAuth->submitted_at?->format('M j, Y H:i'),
                'expires_at' => $preAuth->expires_at?->format('M j, Y'),
                'estimated_approval_date' => $preAuth->estimated_approval_date?->format('M j, Y'),
                'product_request' => [
                    'id' => $preAuth->productRequest->id,
                    'request_number' => $preAuth->productRequest->request_number,
                    'provider_name' => $preAuth->productRequest->provider->first_name . ' ' . $preAuth->productRequest->provider->last_name,
                    'facility_name' => $preAuth->productRequest->facility->name,
                ],
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
            'diagnosis_codes' => 'required|array',
            'procedure_codes' => 'required|array',
            'clinical_documentation' => 'required|string',
            'urgency' => 'required|string|in:routine,urgent,expedited',
        ]);

        $productRequest = ProductRequest::findOrFail($validated['product_request_id']);

        // Create pre-authorization record
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

        // Submit to external payer system (mock implementation)
        $this->submitToPayerSystem($preAuth);

        // Update product request status
        $productRequest->update([
            'pre_auth_status' => 'submitted',
            'pre_auth_submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prior authorization submitted successfully.',
            'authorization_number' => $preAuth->authorization_number,
        ]);
    }

    public function status(Request $request)
    {
        $validated = $request->validate([
            'authorization_number' => 'required|string',
        ]);

        $preAuth = PreAuthorization::where('authorization_number', $validated['authorization_number'])
            ->with(['productRequest.provider', 'productRequest.facility'])
            ->firstOrFail();

        // Check status with payer system (mock implementation)
        $statusUpdate = $this->checkPayerSystemStatus($preAuth);

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
        return 'PA-' . now()->format('Ymd') . '-' . str_pad(PreAuthorization::count() + 1, 4, '0', STR_PAD_LEFT);
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

    private function submitToPayerSystem(PreAuthorization $preAuth): array
    {
        // Mock implementation - would integrate with actual payer APIs
        // Examples: Change Healthcare, Availity, etc.
        
        try {
            // Simulate API call delay
            sleep(1);
            
            // Mock response
            $response = [
                'transaction_id' => 'TXN-' . uniqid(),
                'confirmation_number' => 'CONF-' . uniqid(),
                'status' => 'received',
                'estimated_response_date' => $preAuth->estimated_approval_date->format('Y-m-d'),
            ];

            $preAuth->update([
                'payer_transaction_id' => $response['transaction_id'],
                'payer_confirmation' => $response['confirmation_number'],
                'status' => 'processing',
            ]);

            return $response;
        } catch (\Exception $e) {
            $preAuth->update([
                'status' => 'submission_failed',
                'payer_response' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    private function checkPayerSystemStatus(PreAuthorization $preAuth): ?array
    {
        // Mock implementation - would check actual payer system status
        
        if (!$preAuth->payer_transaction_id) {
            return null;
        }

        // Simulate random status updates for demo
        $statuses = ['processing', 'approved', 'denied', 'pending_info'];
        $randomStatus = $statuses[array_rand($statuses)];

        if ($preAuth->status !== 'processing') {
            return null; // No update needed
        }

        $updates = [
            'status' => $randomStatus,
            'last_status_check' => now(),
        ];

        if ($randomStatus === 'approved') {
            $updates['approved_at'] = now();
            $updates['approved_amount'] = rand(500, 2000);
            $updates['expires_at'] = now()->addMonths(6);
        } elseif ($randomStatus === 'denied') {
            $updates['denied_at'] = now();
            $updates['denial_reason'] = 'Insufficient clinical documentation';
        }

        return $updates;
    }
} 