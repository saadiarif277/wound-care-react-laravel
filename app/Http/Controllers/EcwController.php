<?php

namespace App\Http\Controllers;

use App\Services\EcwFhirService;
use App\Services\AzureKeyVaultService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * eClinicalWorks Integration Controller
 *
 * Handles OAuth2 authentication and FHIR data access for eClinicalWorks EHR
 * following their security requirements and HIPAA compliance standards.
 */
class EcwController extends Controller
{
    private EcwFhirService $ecwService;
    private AzureKeyVaultService $keyVault;

    public function __construct(EcwFhirService $ecwService, AzureKeyVaultService $keyVault)
    {
        $this->ecwService = $ecwService;
        $this->keyVault = $keyVault;
        $this->middleware('auth');
    }

    /**
     * Initiate OAuth2 authentication with eClinicalWorks
     *
     * @return RedirectResponse
     */
    public function authenticate(): RedirectResponse
    {
        try {
            // Generate secure state parameter
            $state = Str::random(40);
            Session::put('ecw_oauth_state', $state);

            // Get authorization URL from eCW
            $authUrl = $this->ecwService->getAuthorizationUrl($state);

            Log::info('eCW OAuth2 authentication initiated', [
                'user_id' => Auth::id(),
                'state' => $state
            ]);

            return redirect($authUrl);

        } catch (Exception $e) {
            Log::error('eCW authentication initiation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to initiate eCW authentication');
        }
    }

    /**
     * Handle OAuth2 callback from eClinicalWorks
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $error = $request->input('error');
            $storedState = Session::get('ecw_oauth_state');

            // Clear stored state
            Session::forget('ecw_oauth_state');

            // Check for errors from eCW
            if ($error) {
                Log::warning('eCW OAuth2 error', [
                    'error' => $error,
                    'error_description' => $request->input('error_description'),
                    'user_id' => Auth::id()
                ]);
                return redirect()->route('dashboard')->with('error', 'eCW authentication failed: ' . $error);
            }

            // Validate state parameter (security requirement)
            if (!$state || $state !== $storedState) {
                Log::warning('eCW OAuth2 state mismatch', [
                    'received_state' => $state,
                    'stored_state' => $storedState,
                    'user_id' => Auth::id()
                ]);
                return redirect()->route('dashboard')->with('error', 'Authentication state validation failed');
            }

            // Exchange code for access token
            $tokenData = $this->ecwService->exchangeCodeForToken($code, $state);

            // Store token securely for user
            $this->ecwService->storeUserToken(Auth::id(), $tokenData);

            Log::info('eCW OAuth2 authentication completed', [
                'user_id' => Auth::id(),
                'scope' => $tokenData['scope'] ?? 'unknown'
            ]);

            return redirect()->route('dashboard')->with('success', 'Successfully connected to eClinicalWorks');

        } catch (Exception $e) {
            Log::error('eCW OAuth2 callback failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')->with('error', 'Failed to complete eCW authentication');
        }
    }

    /**
     * Get eCW connection status for current user
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $hasToken = $this->ecwService->hasValidToken(Auth::id());

            return response()->json([
                'connected' => $hasToken,
                'user_id' => Auth::id(),
                'environment' => config('services.ecw.environment')
            ]);

        } catch (Exception $e) {
            Log::error('eCW status check failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Status check failed'], 500);
        }
    }

    /**
     * Search for patients in eCW
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchPatients(Request $request): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            // Validate search parameters
            $request->validate([
                'name' => 'sometimes|string|max:100',
                'family' => 'sometimes|string|max:50',
                'given' => 'sometimes|string|max:50',
                'birthdate' => 'sometimes|date_format:Y-m-d',
                'gender' => 'sometimes|in:male,female,other,unknown',
                'identifier' => 'sometimes|string|max:50',
                '_count' => 'sometimes|integer|min:1|max:100'
            ]);

            $searchParams = $request->only([
                'name', 'family', 'given', 'birthdate',
                'gender', 'identifier', '_count'
            ]);

            $bundle = $this->ecwService->searchPatients($searchParams, $accessToken);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW patient search failed', [
                'user_id' => Auth::id(),
                'params' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Patient search failed'], 500);
        }
    }

    /**
     * Get patient details from eCW
     *
     * @param string $patientId
     * @return JsonResponse
     */
    public function getPatient(string $patientId): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            $patient = $this->ecwService->getPatient($patientId, $accessToken);

            if (!$patient) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            return response()->json($patient)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW patient read failed', [
                'user_id' => Auth::id(),
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Patient read failed'], 500);
        }
    }

    /**
     * Get patient observations from eCW
     *
     * @param string $patientId
     * @param Request $request
     * @return JsonResponse
     */
    public function getPatientObservations(string $patientId, Request $request): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            // Validate filters
            $request->validate([
                'category' => 'sometimes|string|max:100',
                'code' => 'sometimes|string|max:100',
                'date' => 'sometimes|string|max:50'
            ]);

            $filters = $request->only(['category', 'code', 'date']);

            $bundle = $this->ecwService->getPatientObservations($patientId, $accessToken, $filters);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW patient observations read failed', [
                'user_id' => Auth::id(),
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Observations read failed'], 500);
        }
    }

    /**
     * Get patient documents from eCW
     *
     * @param string $patientId
     * @param Request $request
     * @return JsonResponse
     */
    public function getPatientDocuments(string $patientId, Request $request): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            // Validate filters
            $request->validate([
                'type' => 'sometimes|string|max:100',
                'category' => 'sometimes|string|max:100',
                'date' => 'sometimes|string|max:50'
            ]);

            $filters = $request->only(['type', 'category', 'date']);

            $bundle = $this->ecwService->getPatientDocuments($patientId, $accessToken, $filters);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW patient documents read failed', [
                'user_id' => Auth::id(),
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Documents read failed'], 500);
        }
    }

    /**
     * Disconnect from eCW (revoke tokens)
     *
     * @return JsonResponse
     */
    public function disconnect(): JsonResponse
    {
        try {
            // Remove stored tokens
            DB::table('ecw_user_tokens')->where('user_id', Auth::id())->delete();

            Log::info('eCW connection disconnected', ['user_id' => Auth::id()]);

            return response()->json(['message' => 'Successfully disconnected from eClinicalWorks']);

        } catch (Exception $e) {
            Log::error('eCW disconnect failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Disconnect failed'], 500);
        }
    }

    /**
     * Test eCW connection and token validity
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'connected' => false,
                    'error' => 'No valid token found'
                ]);
            }

            // Test token by validating it
            $tokenInfo = $this->ecwService->validateToken($accessToken);

            return response()->json([
                'connected' => true,
                'token_valid' => $tokenInfo['active'] ?? false,
                'scope' => $tokenInfo['scope'] ?? 'unknown',
                'environment' => config('services.ecw.environment')
            ]);

        } catch (Exception $e) {
            Log::error('eCW connection test failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'connected' => false,
                'error' => 'Connection test failed'
            ], 500);
        }
    }

    /**
     * Get patient conditions (problem list) from eCW
     *
     * @param string $patientId
     * @param Request $request
     * @return JsonResponse
     */
    public function getPatientConditions(string $patientId, Request $request): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            // Validate filters
            $request->validate([
                'category' => 'sometimes|string|max:100',
                'clinical-status' => 'sometimes|string|max:50',
                'verification-status' => 'sometimes|string|max:50'
            ]);

            $filters = $request->only(['category', 'clinical-status', 'verification-status']);

            $bundle = $this->ecwService->getPatientConditions($patientId, $accessToken, $filters);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW patient conditions read failed', [
                'user_id' => Auth::id(),
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Conditions read failed'], 500);
        }
    }

    /**
     * Create order summary in eCW
     *
     * @param string $patientId
     * @param Request $request
     * @return JsonResponse
     */
    public function createOrderSummary(string $patientId, Request $request): JsonResponse
    {
        try {
            // Validate user has eCW token
            $accessToken = $this->ecwService->getUserToken(Auth::id());
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Not connected to eClinicalWorks',
                    'requires_auth' => true
                ], 401);
            }

            // Validate request data
            $request->validate([
                'order_data' => 'required|array',
                'order_data.products' => 'required|array',
                'order_data.clinical_summary' => 'required|string',
                'order_data.order_date' => 'required|date'
            ]);

            $orderData = $request->input('order_data');

            $documentReference = $this->ecwService->createOrderSummary($patientId, $accessToken, $orderData);

            return response()->json($documentReference)
                ->header('Content-Type', 'application/fhir+json');

        } catch (Exception $e) {
            Log::error('eCW order summary creation failed', [
                'user_id' => Auth::id(),
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Order summary creation failed'], 500);
        }
    }

    /**
     * Get JWK (JSON Web Key) for eCW integration
     * This endpoint provides the public key that eCW uses to verify JWT signatures
     *
     * @return JsonResponse
     */
    public function getJwk(): JsonResponse
    {
        try {
            // Get keys from Azure Key Vault
            $keys = $this->keyVault->getEcwJwkKeys();

            if (!$keys) {
                return response()->json([
                    'error' => 'JWK keys not found',
                    'message' => 'Please ensure ECW JWK keys are stored in Azure Key Vault'
                ], 404);
            }

            // Convert PEM public key to JWK format manually
            $jwkData = $this->convertPemToJwk($keys['public']);

            // Create JWK Set response
            $jwkSet = [
                'keys' => [$jwkData]
            ];

            return response()->json($jwkSet, 200, [
                'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
                'Content-Type' => 'application/json'
            ]);

        } catch (Exception $e) {
            Log::error('JWK endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate JWK',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Convert PEM public key to JWK format
     */
    private function convertPemToJwk(string $pemKey): array
    {
        $keyResource = openssl_pkey_get_public($pemKey);

        if (!$keyResource) {
            throw new Exception('Invalid PEM public key');
        }

        $keyDetails = openssl_pkey_get_details($keyResource);

        if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new Exception('Only RSA keys are supported');
        }

        $n = $keyDetails['rsa']['n'];
        $e = $keyDetails['rsa']['e'];

        // Generate a unique key ID
        $keyId = 'ecw-' . substr(hash('sha256', $pemKey), 0, 16);

        return [
            'kty' => 'RSA',
            'kid' => $keyId,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($n)), '='),
            'e' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($e)), '=')
        ];
    }
}
