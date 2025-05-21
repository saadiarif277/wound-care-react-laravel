<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EligibilityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Eligibility/Index');
    }

    public function verify(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'patient.first_name' => 'required|string',
            'patient.last_name' => 'required|string',
            'patient.date_of_birth' => 'required|date',
            'patient.gender' => 'required|in:male,female,other',
            'patient.member_id' => 'required|string',
            'payer.name' => 'required|string',
            'payer.id' => 'required|string',
            'service_date' => 'required|date',
            'service_codes' => 'required|array',
            'service_codes.*.code' => 'required|string',
            'service_codes.*.type' => 'required|in:cpt,hcpcs',
        ]);

        // Simulate API delay
        sleep(2);

        // Dummy response data
        $response = [
            'eligibility_status' => 'eligible',
            'benefits' => [
                'deductible' => [
                    'individual' => 1500.00,
                    'family' => 3000.00,
                    'remaining' => 750.00,
                ],
                'coinsurance' => [
                    'percentage' => 20,
                    'applies_after_deductible' => true,
                ],
                'copay' => [
                    'office_visit' => 25.00,
                    'specialist' => 40.00,
                ],
                'out_of_pocket' => [
                    'individual' => 5000.00,
                    'family' => 10000.00,
                    'remaining' => 3500.00,
                ],
            ],
            'pre_auth_required' => true,
            'pre_auth_status' => 'not_submitted',
            'cost_estimate' => [
                'total_cost' => 1250.00,
                'insurance_pays' => 1000.00,
                'patient_responsibility' => 250.00,
                'breakdown' => [
                    'deductible_applied' => 250.00,
                    'coinsurance_amount' => 0.00,
                    'copay_amount' => 0.00,
                ],
            ],
            'care_reminders' => [
                [
                    'id' => 1,
                    'type' => 'preventive_care',
                    'description' => 'Annual wellness visit due',
                    'due_date' => now()->addMonths(2)->format('Y-m-d'),
                    'priority' => 'medium',
                ],
                [
                    'id' => 2,
                    'type' => 'medication',
                    'description' => 'Prescription refill reminder',
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'priority' => 'high',
                ],
            ],
            'transaction_id' => 'ELIG-' . strtoupper(uniqid()),
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($response);
    }

    public function submitPriorAuth(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'eligibility_transaction_id' => 'required|string',
            'clinical_data' => 'required|array',
            'clinical_data.diagnoses' => 'required|array',
            'clinical_data.diagnoses.*.code' => 'required|string',
            'clinical_data.diagnoses.*.description' => 'required|string',
            'clinical_data.wound_details' => 'required|array',
            'clinical_data.wound_details.type' => 'required|string',
            'clinical_data.wound_details.location' => 'required|string',
            'clinical_data.wound_details.severity' => 'required|string',
        ]);

        // Simulate API delay
        sleep(3);

        // Dummy response data
        $response = [
            'prior_auth_status' => 'pending',
            'prior_auth_number' => 'PA-' . strtoupper(uniqid()),
            'submission_date' => now()->toIso8601String(),
            'estimated_processing_time' => '2-3 business days',
            'transaction_id' => 'PA-' . strtoupper(uniqid()),
        ];

        return response()->json($response);
    }

    public function checkPriorAuthStatus(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'prior_auth_number' => 'required|string',
        ]);

        // Simulate API delay
        sleep(1);

        // Dummy response data
        $response = [
            'prior_auth_status' => 'approved',
            'prior_auth_number' => $validated['prior_auth_number'],
            'approval_date' => now()->subDays(1)->toIso8601String(),
            'expiration_date' => now()->addMonths(6)->toIso8601String(),
            'approved_services' => [
                [
                    'code' => 'HCPCS-A1234',
                    'description' => 'Wound Care Dressing',
                    'quantity' => 10,
                    'frequency' => 'monthly',
                ],
            ],
            'notes' => 'Approved for standard wound care protocol',
            'last_updated' => now()->toIso8601String(),
        ];

        return response()->json($response);
    }
}
