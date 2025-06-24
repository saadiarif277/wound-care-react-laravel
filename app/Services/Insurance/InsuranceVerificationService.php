<?php

declare(strict_types=1);

namespace App\Services\Insurance;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InsuranceVerificationService
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.insurance_verification.url', '');
        $this->apiKey = config('services.insurance_verification.api_key', '');
        $this->timeout = config('services.insurance_verification.timeout', 30);
    }

    /**
     * Verify insurance eligibility
     */
    public function verify(array $request): array
    {
        Log::info('Verifying insurance eligibility', [
            'member_id' => $request['member']['member_id'] ?? null,
            'payor_name' => $request['insurance']['payor_name'] ?? null,
        ]);

        try {
            // For demo purposes, return mock response
            // In production, this would call the actual insurance verification API
            return $this->getMockResponse($request);

            /*
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->apiUrl . '/eligibility', $request);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Insurance verification failed: ' . $response->body());
            */
        } catch (\Exception $e) {
            Log::error('Insurance verification error', [
                'error' => $e->getMessage(),
                'request' => $request,
            ]);

            throw $e;
        }
    }

    /**
     * Get mock response for demo
     */
    private function getMockResponse(array $request): array
    {
        $memberName = $request['member']['first_name'] . ' ' . $request['member']['last_name'];
        
        return [
            'status' => 'verified',
            'eligibility_status' => 'active',
            'verification_id' => 'VER-' . uniqid(),
            'response_time' => rand(100, 500) . 'ms',
            'member' => [
                'name' => $memberName,
                'member_id' => $request['member']['member_id'],
                'date_of_birth' => $request['member']['date_of_birth'],
            ],
            'coverage' => [
                'effective_date' => '2024-01-01',
                'termination_date' => '2024-12-31',
                'plan_name' => $request['insurance']['plan_name'] ?? 'Medicare Advantage',
                'plan_type' => 'HMO',
                'group_number' => $request['insurance']['group_number'] ?? 'GRP123456',
            ],
            'benefits' => [
                'dme' => [
                    'covered' => true,
                    'prior_auth_required' => false,
                    'coverage_percentage' => 80,
                ],
                'wound_care' => [
                    'covered' => true,
                    'prior_auth_required' => true,
                    'coverage_percentage' => 80,
                ],
            ],
            'copay' => 20.00,
            'deductible' => [
                'individual' => 500.00,
                'individual_met' => 250.00,
                'individual_remaining' => 250.00,
                'family' => 1500.00,
                'family_met' => 500.00,
                'family_remaining' => 1000.00,
            ],
            'deductible_remaining' => 250.00,
            'out_of_pocket' => [
                'individual_limit' => 5000.00,
                'individual_met' => 1000.00,
                'individual_remaining' => 4000.00,
                'family_limit' => 10000.00,
                'family_met' => 2000.00,
                'family_remaining' => 8000.00,
            ],
            'out_of_pocket_remaining' => 4000.00,
        ];
    }
}