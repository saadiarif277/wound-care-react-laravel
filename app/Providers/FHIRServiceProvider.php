<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FhirService;
use App\Services\HealthData\Clients\AzureFhirClient;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use App\Services\HealthData\Services\ChecklistValidationService;
use App\Services\Fhir\FhirCircuitBreaker;
use App\Services\Fhir\FhirTransactionManager;
use App\Services\Fhir\FhirResourceValidator;
use App\Services\Fhir\FhirResponseTransformer;
use App\Services\Fhir\FhirErrorHandler;
use App\Services\Fhir\FhirSearchBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class FhirServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Azure FHIR client with proper error handling
        $this->app->singleton(AzureFhirClient::class, function ($app) {
            // Skip authentication during console commands to avoid errors
            if (app()->runningInConsole()) {
                return new AzureFhirClient('http://localhost', 'dummy-token');
            }

            $baseUrl = config('services.azure.fhir.base_url');

            if (!$baseUrl) {
                throw new \RuntimeException('Azure FHIR base URL is not configured. Please set AZURE_FHIR_URL in your .env file.');
            }

            try {
                return new AzureFhirClient($baseUrl, $this->getAzureFhirAccessToken());
            } catch (\Exception $e) {
                throw $e;
            }
        });

        // Register circuit breaker for FHIR calls
        $this->app->singleton(FhirCircuitBreaker::class, function ($app) {
            return new FhirCircuitBreaker(
                'fhir',
                config('fhir.circuit_breaker.failure_threshold', 5),
                config('fhir.circuit_breaker.recovery_timeout', 60),
                config('fhir.circuit_breaker.success_threshold', 2)
            );
        });

        // Register transaction manager - commented out until class is created
        // $this->app->singleton(FhirTransactionManager::class, function ($app) {
        //     return new FhirTransactionManager(
        //         $app->make(FhirService::class)
        //     );
        // });

        // Register resource validator
        $this->app->singleton(FhirResourceValidator::class, function ($app) {
            return new FhirResourceValidator(
                config('fhir.validation.profiles', []),
                config('fhir.validation.strict_mode', false)
            );
        });

        // Register response transformer
        $this->app->singleton(FhirResponseTransformer::class);

        // Register error handler
        $this->app->singleton(FhirErrorHandler::class);

        // Register search builder
        $this->app->singleton(FhirSearchBuilder::class);

        // Register main FHIR service with circuit breaker
        $this->app->singleton(FhirService::class, function ($app) {
            $client = $app->make(AzureFhirClient::class);
            $circuitBreaker = $app->make(FhirCircuitBreaker::class);

            // Create FhirService with circuit breaker protection
            $fhirService = new FhirService($client);

            // Wrap service methods with circuit breaker
            return new class($fhirService, $circuitBreaker) extends FhirService {
                private FhirService $service;
                private FhirCircuitBreaker $circuitBreaker;

                public function __construct(FhirService $service, FhirCircuitBreaker $circuitBreaker)
                {
                    $this->service = $service;
                    $this->circuitBreaker = $circuitBreaker;
                }

                public function __call($method, $arguments)
                {
                    return $this->circuitBreaker->call(
                        fn() => $this->service->$method(...$arguments)
                    );
                }
            };
        });

        // Register existing services
        $this->app->bind(SkinSubstituteChecklistService::class, function ($app) {
            return new SkinSubstituteChecklistService(
                $app->make(AzureFhirClient::class)
            );
        });

        $this->app->bind(ChecklistValidationService::class, function ($app) {
            return new ChecklistValidationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/fhir.php' => config_path('fhir.php'),
        ], 'fhir-config');

        // Register HTTP macros for FHIR
        $this->registerHttpMacros();

        // Register custom validation rules
        $this->registerFhirValidationRules();

        // Register middleware - commented out until classes are created
        // $this->app['router']->aliasMiddleware('fhir.validate', ValidateFhirRequest::class);
        // $this->app['router']->aliasMiddleware('fhir.transform', TransformFhirResponse::class);
    }

    /**
     * Get Azure FHIR access token with proper caching
     */
    private function getAzureFhirAccessToken(): string
    {
        return Cache::remember('azure_fhir_token', 3500, function () {
            return $this->requestNewToken();
        });
    }

    /**
     * Request new token from Azure AD
     */
    private function requestNewToken(): string
    {
        $tenantId = config('services.azure.fhir.tenant_id') ?? config('services.azure.tenant_id');
        $clientId = config('services.azure.fhir.client_id') ?? config('services.azure.client_id');
        $clientSecret = config('services.azure.fhir.client_secret') ?? config('services.azure.client_secret');
        $resource = config('services.azure.fhir.resource', 'https://azurehealthcareapis.com');

        if (!$tenantId || !$clientId || !$clientSecret) {
            throw new \RuntimeException('Azure AD credentials for FHIR are not configured. Please check your .env file.');
        }

        try {
            $response = Http::timeout(10)
                ->retry(3, 1000)
                ->asForm()
                ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $resource . '/.default'
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'Failed to obtain Azure FHIR access token: ' . $response->body()
                );
            }

            $token = $response->json('access_token');

            if (!$token) {
                throw new \RuntimeException('No access token in Azure AD response');
            }

            return $token;

        } catch (\Exception $e) {
            logger()->error('Failed to request Azure FHIR token', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new \RuntimeException(
                'Unable to authenticate with Azure FHIR: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Register HTTP macros for FHIR
     */
    protected function registerHttpMacros(): void
    {
        Http::macro('fhir', function () {
            return Http::withHeaders([
                'Accept' => 'application/fhir+json',
                'Content-Type' => 'application/fhir+json',
                'Prefer' => 'return=representation'
            ])
            ->timeout(30)
            ->retry(3, 1000);
        });

        Http::macro('fhirBundle', function () {
            return Http::fhir()->withHeaders([
                'Content-Type' => 'application/fhir+json'
            ]);
        });
    }

    /**
     * Register FHIR-specific validation rules
     */
    protected function registerFhirValidationRules(): void
    {
        // FHIR date validation
        Validator::extend('fhir_date', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $value);
        }, 'The :attribute must be a valid FHIR date format (YYYY, YYYY-MM, or YYYY-MM-DD).');

        // FHIR datetime validation
        Validator::extend('fhir_datetime', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?(Z|[+-]\d{2}:\d{2})$/', $value);
        }, 'The :attribute must be a valid FHIR datetime format.');

        // FHIR reference validation
        Validator::extend('fhir_reference', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^(https?:\/\/[^\s\/]+\/)?[A-Z][a-zA-Z]+\/[A-Za-z0-9\-\.]{1,64}$/', $value);
        }, 'The :attribute must be a valid FHIR reference.');

        // FHIR resource type validation
        Validator::extend('fhir_resource_type', function ($attribute, $value, $parameters, $validator) {
            $validTypes = [
                'Patient', 'Practitioner', 'Organization', 'Condition',
                'EpisodeOfCare', 'Coverage', 'Encounter', 'QuestionnaireResponse',
                'DeviceRequest', 'Task', 'Bundle', 'DocumentReference',
                'Observation', 'Procedure', 'MedicationRequest', 'DiagnosticReport'
            ];
            return in_array($value, $validTypes);
        }, 'The :attribute must be a valid FHIR resource type.');

        // FHIR identifier validation
        Validator::extend('fhir_identifier', function ($attribute, $value, $parameters, $validator) {
            if (!is_array($value)) {
                return false;
            }

            return isset($value['system']) &&
                   isset($value['value']) &&
                   is_string($value['system']) &&
                   is_string($value['value']);
        }, 'The :attribute must be a valid FHIR identifier with system and value.');
    }
}
