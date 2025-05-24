<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

/**
 * eClinicalWorks FHIR Service
 *
 * Handles integration with eClinicalWorks FHIR API following their
 * security requirements, HIPAA compliance, and developer terms.
 */
class EcwFhirService
{
    private ?string $baseEndpoint;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $redirectUri;
    private ?string $scope;
    private ?string $environment;

    public function __construct()
    {
        $this->clientId = config('services.ecw.client_id');
        $this->clientSecret = config('services.ecw.client_secret');
        $this->redirectUri = config('services.ecw.redirect_uri');
        $this->scope = config('services.ecw.scope');
        $this->environment = config('services.ecw.environment');

        // Validate required configuration
        if (!$this->clientId || !$this->clientSecret) {
            throw new \InvalidArgumentException('eCW client ID and client secret are required');
        }

        if (!$this->redirectUri) {
            throw new \InvalidArgumentException('eCW redirect URI is required');
        }

        $this->baseEndpoint = $this->environment === 'production'
            ? config('services.ecw.production_endpoint')
            : config('services.ecw.sandbox_endpoint');

        if (!$this->baseEndpoint) {
            throw new \InvalidArgumentException('eCW FHIR endpoint not configured');
        }

        // Validate endpoint format
        if (!filter_var($this->baseEndpoint, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid eCW FHIR endpoint URL format');
        }
    }

    /**
     * Generate OAuth2 authorization URL for eCW
     *
     * @param string $state Random state parameter for security
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scope,
            'state' => $state,
            'aud' => $this->baseEndpoint,
        ];

        $authEndpoint = str_replace('/fhir', '/oauth2/authorize', $this->baseEndpoint);

        return $authEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code from eCW
     * @param string $state State parameter for verification
     * @return array Token response with access_token, refresh_token, etc.
     */
    public function exchangeCodeForToken(string $code, string $state): array
    {
        try {
            $tokenEndpoint = str_replace('/fhir', '/oauth2/token', $this->baseEndpoint);

            $response = Http::asForm()->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (!$response->successful()) {
                throw new Exception("eCW token exchange failed: " . $response->body());
            }

            $tokenData = $response->json();

            // Log successful authentication (without sensitive data)
            Log::info('eCW OAuth2 token obtained', [
                'client_id' => $this->clientId,
                'scope' => $tokenData['scope'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown'
            ]);

            return $tokenData;

        } catch (Exception $e) {
            Log::error('eCW token exchange failed', [
                'error' => $e->getMessage(),
                'client_id' => $this->clientId
            ]);
            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshToken Refresh token from previous authentication
     * @return array New token response
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $tokenEndpoint = str_replace('/fhir', '/oauth2/token', $this->baseEndpoint);

            $response = Http::asForm()->post($tokenEndpoint, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (!$response->successful()) {
                throw new Exception("eCW token refresh failed: " . $response->body());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('eCW token refresh failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Patient data from eCW FHIR API
     *
     * @param string $patientId Patient ID in eCW system
     * @param string $accessToken Valid access token
     * @return array|null Patient FHIR resource or null if not found
     */
    public function getPatient(string $patientId, string $accessToken): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'User-Agent' => 'MSC-MVP-FHIR-Client/1.0',
            ])->get("{$this->baseEndpoint}/Patient/{$patientId}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new Exception("eCW API error: " . $response->body());
            }

            $patient = $response->json();

                        // Log access for audit trail (HIPAA requirement)
            $this->logPatientAccess($patientId, 'read', Auth::id());

            return $patient;

        } catch (Exception $e) {
            Log::error('eCW Patient read failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search for Patients in eCW FHIR API
     *
     * @param array $searchParams Search parameters
     * @param string $accessToken Valid access token
     * @return array FHIR Bundle with search results
     */
    public function searchPatients(array $searchParams, string $accessToken): array
    {
        try {
            // Validate search parameters
            $validParams = $this->validateSearchParams($searchParams);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'User-Agent' => 'MSC-MVP-FHIR-Client/1.0',
            ])->get("{$this->baseEndpoint}/Patient", $validParams);

            if (!$response->successful()) {
                throw new Exception("eCW search failed: " . $response->body());
            }

            $bundle = $response->json();

            // Log search for audit trail
            $this->logPatientAccess('multiple', 'search', Auth::id(), $searchParams);

            return $bundle;

        } catch (Exception $e) {
            Log::error('eCW Patient search failed', [
                'params' => $searchParams,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get Observations for a Patient from eCW
     *
     * @param string $patientId Patient ID
     * @param string $accessToken Valid access token
     * @param array $filters Optional filters (category, code, date)
     * @return array FHIR Bundle with observations
     */
    public function getPatientObservations(string $patientId, string $accessToken, array $filters = []): array
    {
        try {
            $params = ['patient' => $patientId];

            // Add optional filters
            if (!empty($filters['category'])) {
                $params['category'] = $filters['category'];
            }
            if (!empty($filters['code'])) {
                $params['code'] = $filters['code'];
            }
            if (!empty($filters['date'])) {
                $params['date'] = $filters['date'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'User-Agent' => 'MSC-MVP-FHIR-Client/1.0',
            ])->get("{$this->baseEndpoint}/Observation", $params);

            if (!$response->successful()) {
                throw new Exception("eCW Observations API error: " . $response->body());
            }

            $bundle = $response->json();

            // Log access for audit trail
            $this->logPatientAccess($patientId, 'observation_read', Auth::id());

            return $bundle;

        } catch (Exception $e) {
            Log::error('eCW Observations read failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get DocumentReferences for a Patient from eCW
     *
     * @param string $patientId Patient ID
     * @param string $accessToken Valid access token
     * @param array $filters Optional filters
     * @return array FHIR Bundle with document references
     */
    public function getPatientDocuments(string $patientId, string $accessToken, array $filters = []): array
    {
        try {
            $params = ['patient' => $patientId];

            // Add optional filters
            if (!empty($filters['type'])) {
                $params['type'] = $filters['type'];
            }
            if (!empty($filters['category'])) {
                $params['category'] = $filters['category'];
            }
            if (!empty($filters['date'])) {
                $params['date'] = $filters['date'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'User-Agent' => 'MSC-MVP-FHIR-Client/1.0',
            ])->get("{$this->baseEndpoint}/DocumentReference", $params);

            if (!$response->successful()) {
                throw new Exception("eCW DocumentReference API error: " . $response->body());
            }

            $bundle = $response->json();

            // Log access for audit trail
            $this->logPatientAccess($patientId, 'document_read', Auth::id());

            return $bundle;

        } catch (Exception $e) {
            Log::error('eCW DocumentReference read failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate token and get user info
     *
     * @param string $accessToken Access token to validate
     * @return array Token info including scope, expiration, etc.
     */
    public function validateToken(string $accessToken): array
    {
        try {
            $introspectEndpoint = str_replace('/fhir', '/oauth2/introspect', $this->baseEndpoint);

            $response = Http::asForm()->post($introspectEndpoint, [
                'token' => $accessToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (!$response->successful()) {
                throw new Exception("Token validation failed: " . $response->body());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('eCW token validation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate and sanitize search parameters
     *
     * @param array $params Raw search parameters
     * @return array Validated parameters
     */
    private function validateSearchParams(array $params): array
    {
        $validParams = [];

        // Allowed search parameters for Patient resource
        $allowedParams = [
            'name', 'family', 'given', 'identifier', 'birthdate',
            'gender', 'phone', 'email', '_count', '_offset'
        ];

        foreach ($params as $key => $value) {
            if (in_array($key, $allowedParams) && !empty($value)) {
                // Sanitize the value
                $validParams[$key] = htmlspecialchars(strip_tags($value));
            }
        }

        // Enforce reasonable limits
        if (isset($validParams['_count'])) {
            $validParams['_count'] = min(100, max(1, (int)$validParams['_count']));
        } else {
            $validParams['_count'] = 20; // Default page size
        }

        return $validParams;
    }

    /**
     * Log patient data access for audit trail (HIPAA requirement)
     *
     * @param string $patientId Patient ID or 'multiple' for searches
     * @param string $action Action performed (read, search, etc.)
     * @param int|null $userId User performing the action
     * @param array $metadata Additional metadata
     */
    private function logPatientAccess(string $patientId, string $action, ?int $userId, array $metadata = []): void
    {
        try {
            // Validate inputs
            if (empty($patientId) || empty($action)) {
                Log::warning('Invalid audit log parameters', ['patient_id' => $patientId, 'action' => $action]);
                return;
            }

            // Use safe defaults for nullable values
            $safeUserId = $userId ?? 0; // Use 0 for system/anonymous access
            $safeIpAddress = request()?->ip() ?? 'unknown';
            $safeUserAgent = request()?->userAgent() ?? 'unknown';

            DB::table('ecw_audit_log')->insert([
                'patient_id' => substr($patientId, 0, 255), // Prevent oversized data
                'action' => substr($action, 0, 100),
                'user_id' => $safeUserId,
                'metadata' => json_encode($metadata),
                'ip_address' => substr($safeIpAddress, 0, 45), // IPv6 max length
                'user_agent' => substr($safeUserAgent, 0, 500),
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            // Don't let audit logging failure break the main functionality
            Log::error('Failed to log eCW audit entry', [
                'patient_id' => $patientId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store encrypted access token for user
     *
     * @param int $userId User ID
     * @param array $tokenData Token data from eCW
     */
    public function storeUserToken(int $userId, array $tokenData): void
    {
        try {
            DB::table('ecw_user_tokens')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'access_token' => encrypt($tokenData['access_token']),
                    'refresh_token' => encrypt($tokenData['refresh_token'] ?? null),
                    'token_type' => $tokenData['token_type'] ?? 'Bearer',
                    'scope' => $tokenData['scope'] ?? '',
                    'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                    'updated_at' => now(),
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to store eCW token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get stored access token for user
     *
     * @param int $userId User ID
     * @return string|null Decrypted access token or null if not found/expired
     */
    public function getUserToken(int $userId): ?string
    {
        try {
            $tokenRecord = DB::table('ecw_user_tokens')
                ->where('user_id', $userId)
                ->where('expires_at', '>', now())
                ->first();

            if (!$tokenRecord) {
                return null;
            }

            return decrypt($tokenRecord->access_token);

        } catch (Exception $e) {
            Log::error('Failed to retrieve eCW token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if user has valid eCW token
     *
     * @param int $userId User ID
     * @return bool True if user has valid token
     */
    public function hasValidToken(int $userId): bool
    {
        return $this->getUserToken($userId) !== null;
    }

    /**
     * Validate API response and content type
     */
    private function validateApiResponse($response, string $expectedContentType = 'application/fhir+json'): void
    {
        if (!$response->successful()) {
            throw new Exception("eCW API error: HTTP {$response->status()} - " . $response->body());
        }

        $contentType = $response->header('Content-Type');
        if ($contentType && !str_contains($contentType, $expectedContentType) && !str_contains($contentType, 'application/json')) {
            throw new Exception("Unexpected content type: {$contentType}. Expected: {$expectedContentType}");
        }

        // Validate JSON structure
        $data = $response->json();
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
    }
}
