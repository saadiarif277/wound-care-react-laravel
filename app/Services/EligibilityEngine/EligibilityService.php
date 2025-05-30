<?php

namespace App\Services\EligibilityEngine;

use App\Models\Order\Order;
use App\Models\Order\ProductRequest;
use App\Models\Insurance\PreAuthTask;
use App\Services\EligibilityEngine\AvailityEligibilityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class EligibilityService
{
    private string $apiBaseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->apiBaseUrl = config('eligibility.api_base_url', 'https://sandbox-apigw.optum.com');
        $this->clientId = config('eligibility.client_id');
        $this->clientSecret = config('eligibility.client_secret');
    }

    /**
     * Run eligibility check for an order
     */
    public function runEligibility(int $orderId): array
    {
        $order = Order::with(['orderItems.product', 'facility'])->findOrFail($orderId);

        Log::info('Starting eligibility check', ['order_id' => $orderId]);

        try {
            // Update status to checking
            $order->update([
                'eligibility_status' => 'checking',
                'eligibility_checked_at' => now()
            ]);

            // Build eligibility request payload
            $requestPayload = $this->buildEligibilityRequest($order);

            // Validate payload
            $this->validateEligibilityRequest($requestPayload);

            // Send eligibility request
            $response = $this->sendEligibilityRequest($requestPayload);

            // Process response and update order
            $result = $this->processEligibilityResponse($order, $response);

            // Check if pre-auth is required and trigger if needed
            if ($this->isPreAuthRequired($response)) {
                Log::info('Pre-auth required, triggering coverage discovery', ['order_id' => $orderId]);
                $this->runPreAuth($orderId);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Eligibility check failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $order->update([
                'eligibility_status' => 'error',
                'eligibility_result' => [
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

            throw $e;
        }
    }

    /**
     * Run pre-authorization (coverage discovery) for an order
     */
    public function runPreAuth(int $orderId): array
    {
        $order = Order::findOrFail($orderId);

        Log::info('Starting pre-auth coverage discovery', ['order_id' => $orderId]);

        try {
            // Update pre-auth status
            $order->update([
                'pre_auth_status' => 'pending',
                'pre_auth_requested_at' => now()
            ]);

            // Build coverage discovery payload
            $requestPayload = $this->buildCoverageDiscoveryRequest($order);

            // Send coverage discovery request
            $response = $this->sendCoverageDiscoveryRequest($requestPayload);

            // Process response and create tasks
            $tasks = $this->processCoverageDiscoveryResponse($order, $response);

            // Update order status
            $order->update([
                'pre_auth_status' => 'in_progress'
            ]);

            return [
                'status' => 'in_progress',
                'tasks_created' => count($tasks),
                'tasks' => $tasks
            ];

        } catch (\Exception $e) {
            Log::error('Pre-auth coverage discovery failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            $order->update([
                'pre_auth_status' => 'error',
                'pre_auth_result' => [
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

            throw $e;
        }
    }

    /**
     * Build eligibility request payload from order data
     */
    private function buildEligibilityRequest(Order $order): array
    {
        $requestMapper = new EligibilityRequestMapper();
        return $requestMapper->mapOrderToEligibilityRequest($order);
    }

    /**
     * Build coverage discovery request payload
     */
    private function buildCoverageDiscoveryRequest(Order $order): array
    {
        $eligibilityResult = $order->eligibility_result;

        if (!$eligibilityResult) {
            throw new \Exception('Eligibility result not found for order ' . $order->id);
        }

        $payload = [
            'canonicalEligibilityResponse' => $eligibilityResult['response'] ?? [],
            'dryRun' => config('eligibility.coverage_discovery.dry_run', true)
        ];

        // Add callback URL if configured and not in dry run mode
        if (!$payload['dryRun'] && ($callbackUrl = config('eligibility.coverage_discovery.callback_url'))) {
            $payload['callbackUrl'] = $callbackUrl;
        }

        return $payload;
    }

    /**
     * Send eligibility request to Optum Enhanced Eligibility API
     */
    private function sendEligibilityRequest(array $payload): array
    {
        $token = $this->getAccessToken();
        $correlationId = $this->generateCorrelationId();

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'x-optum-correlation-id' => $correlationId,
        ];

        // Add tenant ID if configured
        if ($tenantId = config('eligibility.headers.tenant_id')) {
            $headers['x-optum-tenant-id'] = $tenantId;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('eligibility.timeout', 30))
            ->post($this->apiBaseUrl . config('eligibility.endpoints.eligibility'), $payload);

        if (!$response->successful()) {
            throw new \Exception('Eligibility API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Send coverage discovery request to Optum API
     */
    private function sendCoverageDiscoveryRequest(array $payload): array
    {
        $token = $this->getAccessToken();
        $correlationId = $this->generateCorrelationId();

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'x-optum-correlation-id' => $correlationId,
        ];

        // Add tenant ID if configured
        if ($tenantId = config('eligibility.headers.tenant_id')) {
            $headers['x-optum-tenant-id'] = $tenantId;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('eligibility.timeout', 30))
            ->post($this->apiBaseUrl . config('eligibility.endpoints.coverage_discovery'), $payload);

        if (!$response->successful()) {
            throw new \Exception('Coverage Discovery API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Process eligibility response and update order
     */
    private function processEligibilityResponse(Order $order, array $response): array
    {
        $status = $this->determineEligibilityStatus($response);

        $order->update([
            'eligibility_status' => $status,
            'eligibility_result' => [
                'response' => $response,
                'processed_at' => now()->toISOString(),
                'api_version' => 'v1'
            ]
        ]);

        return [
            'status' => $status,
            'response' => $response,
            'pre_auth_required' => $this->isPreAuthRequired($response)
        ];
    }

    /**
     * Process coverage discovery response and create tasks
     */
    private function processCoverageDiscoveryResponse(Order $order, array $response): array
    {
        $tasks = [];
        $discoveryPaths = $response['discoveryPaths'] ?? [];

        foreach ($discoveryPaths as $path) {
            $task = PreAuthTask::create([
                'order_id' => $order->id,
                'external_task_id' => $path['taskId'] ?? uniqid(),
                'status' => 'pending',
                'task_name' => $path['taskName'] ?? 'Coverage Discovery',
                'details' => $path
            ]);

            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * Determine eligibility status from API response
     */
    private function determineEligibilityStatus(array $response): string
    {
        // Check transaction status from the official API response format
        $status = $response['status']['value'] ?? null;

        if (!$status) {
            return 'error';
        }

        // Map API status to our internal status using config mapping
        return config('eligibility.status_mappings.' . $status, 'requires_review');
    }

    /**
     * Check if pre-authorization is required
     */
    private function isPreAuthRequired(array $response): bool
    {
        $benefits = $response['benefits'] ?? [];

        foreach ($benefits as $benefit) {
            // Check for pre-auth indicators in benefit details
            $benefitName = strtolower($benefit['name'] ?? '');

            if (in_array($benefitName, ['pre-authorization', 'prior authorization', 'preauth'])) {
                return true;
            }

            // Check benefit qualifier for pre-auth codes
            $benefitQualifier = $benefit['benefitQualifier'] ?? '';
            if (in_array($benefitQualifier, ['AR', 'G1'])) { // AR = Authorization Required, G1 = Prior Authorization Number
                return true;
            }
        }

        return false;
    }

    /**
     * Validate eligibility request payload
     */
    private function validateEligibilityRequest(array $payload): void
    {
        $validator = new EligibilityRequestValidator();
        $validator->validate($payload);
    }

    /**
     * Get OAuth2 access token for Optum API
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception('Optum API credentials not configured. Please set OPTUM_CLIENT_ID and OPTUM_CLIENT_SECRET environment variables.');
        }

        $scopes = implode(' ', config('eligibility.scopes', ['create_txn', 'read_txn']));

        $response = Http::asForm()->post($this->apiBaseUrl . config('eligibility.endpoints.token'), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $scopes
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to obtain access token: ' . $response->status() . ' - ' . $response->body());
        }

        $tokenData = $response->json();
        $this->accessToken = $tokenData['access_token'];

        return $this->accessToken;
    }

    /**
     * Generate correlation ID for request tracking
     */
    private function generateCorrelationId(): string
    {
        $prefix = config('eligibility.headers.correlation_id_prefix', 'MSC');
        return $prefix . '_' . uniqid() . '_' . time();
    }

    /**
     * Check API health status
     */
    public function healthCheck(): array
    {
        try {
            $token = $this->getAccessToken();
            $correlationId = $this->generateCorrelationId();

            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'x-optum-correlation-id' => $correlationId,
            ];

            if ($tenantId = config('eligibility.headers.tenant_id')) {
                $headers['x-optum-tenant-id'] = $tenantId;
            }

            $response = Http::withHeaders($headers)
                ->timeout(config('eligibility.timeout', 30))
                ->get($this->apiBaseUrl . config('eligibility.endpoints.healthcheck'));

            if (!$response->successful()) {
                throw new \Exception('Health check failed: ' . $response->status() . ' - ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('API health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle callback from coverage discovery completion
     */
    public function handleCoverageDiscoveryCallback(string $taskId, array $callbackData): void
    {
        $task = PreAuthTask::where('external_task_id', $taskId)->firstOrFail();

        $task->update([
            'status' => $callbackData['status'] ?? 'completed',
            'details' => array_merge($task->details ?? [], $callbackData),
            'updated_at' => now()
        ]);

        // Check if all tasks for this order are complete
        $this->checkOrderPreAuthCompletion($task->order_id);
    }

    /**
     * Check if all pre-auth tasks for an order are complete
     */
    private function checkOrderPreAuthCompletion(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $pendingTasks = PreAuthTask::where('order_id', $orderId)
            ->where('status', 'pending')
            ->count();

        if ($pendingTasks === 0) {
            // All tasks completed, update order status
            $allTasks = PreAuthTask::where('order_id', $orderId)->get();
            $hasErrors = $allTasks->where('status', 'failed')->count() > 0;

            $order->update([
                'pre_auth_status' => $hasErrors ? 'error' : 'completed',
                'pre_auth_result' => [
                    'tasks' => $allTasks->toArray(),
                    'completed_at' => now()->toISOString(),
                    'total_tasks' => $allTasks->count(),
                    'failed_tasks' => $allTasks->where('status', 'failed')->count()
                ]
            ]);
        }
    }

    /**
     * Check general eligibility (not tied to a specific order)
     */
    public function checkGeneralEligibility(array $patientData, string $payerName, string $serviceDate, array $procedureCodes): array
    {
        Log::info('Starting general eligibility check', [
            'member_id' => $patientData['member_id'],
            'payer_name' => $payerName,
            'service_date' => $serviceDate
        ]);

        try {
            // Build eligibility request payload for general check
            $requestPayload = $this->buildGeneralEligibilityRequest($patientData, $payerName, $serviceDate, $procedureCodes);

            // Validate payload
            $this->validateEligibilityRequest($requestPayload);

            // Send eligibility request
            $response = $this->sendEligibilityRequest($requestPayload);

            // Process response
            $result = $this->processGeneralEligibilityResponse($response);

            return $result;

        } catch (\Exception $e) {
            Log::error('General eligibility check failed', [
                'member_id' => $patientData['member_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Build general eligibility request payload
     */
    private function buildGeneralEligibilityRequest(array $patientData, string $payerName, string $serviceDate, array $procedureCodes): array
    {
        return [
            'subscriber' => [
                'memberId' => $patientData['member_id'],
                'firstName' => $patientData['first_name'],
                'lastName' => $patientData['last_name'],
                'dateOfBirth' => $patientData['dob'],
                'gender' => $patientData['gender'] ?? 'U'
            ],
            'payer' => [
                'name' => $payerName
            ],
            'serviceDate' => $serviceDate,
            'procedureCodes' => $procedureCodes,
            'requestType' => 'eligibility',
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Process general eligibility response
     */
    private function processGeneralEligibilityResponse(array $response): array
    {
        $status = $this->determineEligibilityStatus($response);
        $benefits = $this->extractBenefitsFromResponse($response);
        $priorAuthRequired = $this->isPreAuthRequired($response);

        return [
            'status' => $status,
            'benefits' => $benefits,
            'prior_authorization_required' => $priorAuthRequired,
            'coverage_details' => $this->extractCoverageDetails($response),
            'response' => $response,
            'processed_at' => now()->toISOString()
        ];
    }

    /**
     * Extract benefits information from eligibility response
     */
    private function extractBenefitsFromResponse(array $response): array
    {
        $benefits = [];
        $benefitsData = $response['benefits'] ?? [];

        foreach ($benefitsData as $benefit) {
            $benefitType = $benefit['type'] ?? '';
            $amount = $benefit['amount'] ?? null;

            switch (strtolower($benefitType)) {
                case 'copay':
                case 'copayment':
                    $benefits['copay'] = $amount;
                    break;
                case 'deductible':
                    $benefits['deductible'] = $amount;
                    break;
                case 'coinsurance':
                    $benefits['coinsurance'] = $amount;
                    break;
                case 'out_of_pocket_maximum':
                case 'out-of-pocket maximum':
                    $benefits['out_of_pocket_max'] = $amount;
                    break;
            }
        }

        return $benefits;
    }

    /**
     * Extract coverage details from eligibility response
     */
    private function extractCoverageDetails(array $response): string
    {
        $status = $response['status']['value'] ?? 'unknown';
        $planName = $response['plan']['name'] ?? 'Unknown Plan';

        switch ($status) {
            case 'active':
                return "Coverage is active under {$planName}";
            case 'inactive':
                return "Coverage is inactive";
            case 'terminated':
                return "Coverage has been terminated";
            default:
                return "Coverage status: {$status}";
        }
    }

    /**
     * Check eligibility for a ProductRequest using Availity Coverages API
     */
    public function checkProductRequestEligibility(ProductRequest $productRequest): array
    {
        Log::info('Starting eligibility check for ProductRequest', ['request_id' => $productRequest->id]);

        try {
            // Update status to checking
            $productRequest->update([
                'eligibility_status' => 'pending',
            ]);

            // Use Availity service for eligibility checking
            $availityService = new AvailityEligibilityService();
            $eligibilityResult = $availityService->checkEligibility($productRequest);

            // Update ProductRequest with results
            $productRequest->update([
                'eligibility_results' => $eligibilityResult,
                'eligibility_status' => $eligibilityResult['status'],
                'pre_auth_required_determination' => $eligibilityResult['prior_authorization_required'] ? 'required' : 'not_required',
            ]);

            Log::info('ProductRequest eligibility check completed', [
                'request_id' => $productRequest->id,
                'status' => $eligibilityResult['status']
            ]);

            return $eligibilityResult;

        } catch (\Exception $e) {
            Log::error('ProductRequest eligibility check failed', [
                'request_id' => $productRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $productRequest->update([
                'eligibility_status' => 'error',
                'eligibility_results' => [
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

            throw $e;
        }
    }

    /**
     * Get detailed coverage information by coverage ID
     */
    public function getCoverageDetails(string $coverageId): array
    {
        try {
            $availityService = new AvailityEligibilityService();
            return $availityService->getCoverageById($coverageId);

        } catch (\Exception $e) {
            Log::error('Failed to get coverage details', [
                'coverage_id' => $coverageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
