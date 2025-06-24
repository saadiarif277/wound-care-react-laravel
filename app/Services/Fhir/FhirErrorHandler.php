<?php

namespace App\Services\Fhir;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Logging\PhiSafeLogger;

class FhirErrorHandler
{
    private PhiSafeLogger $logger;
    private bool $includeDiagnostics;
    
    public function __construct(PhiSafeLogger $logger)
    {
        $this->logger = $logger;
        $this->includeDiagnostics = config('fhir.errors.include_diagnostics', false);
    }

    /**
     * Handle FHIR error and create OperationOutcome
     */
    public function handleError(\Exception $exception, ?string $resourceType = null): array
    {
        // Log the error
        $this->logError($exception, $resourceType);
        
        // Create OperationOutcome
        $operationOutcome = $this->createOperationOutcome($exception);
        
        return $operationOutcome;
    }

    /**
     * Create OperationOutcome from exception
     */
    public function createOperationOutcome(\Exception $exception): array
    {
        $severity = $this->determineSeverity($exception);
        $code = $this->determineIssueCode($exception);
        $details = $this->getErrorDetails($exception);
        
        $issue = [
            'severity' => $severity,
            'code' => $code,
            'details' => [
                'text' => $details
            ]
        ];
        
        // Add diagnostics in debug mode
        if ($this->includeDiagnostics) {
            $issue['diagnostics'] = $this->getDiagnostics($exception);
        }
        
        // Add expression if validation error
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $issue['expression'] = array_keys($exception->errors());
        }
        
        return [
            'resourceType' => 'OperationOutcome',
            'issue' => [$issue]
        ];
    }

    /**
     * Create HTTP response with OperationOutcome
     */
    public function createErrorResponse(\Exception $exception, int $statusCode = 500): JsonResponse
    {
        $operationOutcome = $this->createOperationOutcome($exception);
        
        return response()->json($operationOutcome, $statusCode, [
            'Content-Type' => 'application/fhir+json'
        ]);
    }

    /**
     * Handle FHIR API response errors
     */
    public function handleApiError(array $response, int $statusCode): array
    {
        // Check if response is already an OperationOutcome
        if (($response['resourceType'] ?? '') === 'OperationOutcome') {
            return $response;
        }
        
        // Create OperationOutcome from API error
        $issue = [
            'severity' => $statusCode >= 500 ? 'error' : 'warning',
            'code' => $this->mapStatusCodeToIssueCode($statusCode),
            'details' => [
                'text' => $response['error'] ?? 'Unknown error'
            ]
        ];
        
        if (isset($response['error_description'])) {
            $issue['diagnostics'] = $response['error_description'];
        }
        
        return [
            'resourceType' => 'OperationOutcome',
            'issue' => [$issue]
        ];
    }

    /**
     * Determine severity from exception
     */
    private function determineSeverity(\Exception $exception): string
    {
        return match(true) {
            $exception instanceof \Illuminate\Validation\ValidationException => 'error',
            $exception instanceof \Illuminate\Auth\AuthenticationException => 'error',
            $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 'error',
            $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException => 
                $exception->getStatusCode() >= 500 ? 'fatal' : 'error',
            default => 'error'
        };
    }

    /**
     * Determine issue code from exception
     */
    private function determineIssueCode(\Exception $exception): string
    {
        return match(true) {
            $exception instanceof \Illuminate\Validation\ValidationException => 'invalid',
            $exception instanceof \Illuminate\Auth\AuthenticationException => 'security',
            $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 'not-found',
            $exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 'not-supported',
            $exception instanceof \App\Exceptions\FhirException => $exception->getIssueCode(),
            $exception instanceof \App\Exceptions\CircuitBreakerOpenException => 'transient',
            default => 'exception'
        };
    }

    /**
     * Get error details message
     */
    private function getErrorDetails(\Exception $exception): string
    {
        // For validation exceptions, format the errors
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[] = "{$field}: " . implode(', ', $messages);
            }
            return 'Validation failed: ' . implode('; ', $errors);
        }
        
        // For HTTP exceptions, use the message
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            return $exception->getMessage() ?: 'HTTP error occurred';
        }
        
        // Default message
        return $exception->getMessage() ?: 'An error occurred processing the request';
    }

    /**
     * Get diagnostics information
     */
    private function getDiagnostics(\Exception $exception): string
    {
        $diagnostics = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ];
        
        // Add request information
        if (request()) {
            $diagnostics['request'] = [
                'method' => request()->method(),
                'path' => request()->path(),
                'ip' => request()->ip()
            ];
        }
        
        return json_encode($diagnostics);
    }

    /**
     * Map HTTP status code to FHIR issue code
     */
    private function mapStatusCodeToIssueCode(int $statusCode): string
    {
        return match($statusCode) {
            400 => 'invalid',
            401 => 'security',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'not-supported',
            409 => 'conflict',
            410 => 'deleted',
            422 => 'invalid',
            429 => 'throttled',
            500 => 'exception',
            502 => 'transient',
            503 => 'transient',
            504 => 'timeout',
            default => 'unknown'
        };
    }

    /**
     * Log error with PHI-safe context
     */
    private function logError(\Exception $exception, ?string $resourceType = null): void
    {
        $context = [
            'exception_class' => get_class($exception),
            'resource_type' => $resourceType,
            'status_code' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500
        ];
        
        // Add request context
        if (request()) {
            $context['request'] = [
                'method' => request()->method(),
                'path' => request()->path(),
                'user_id' => auth()->id()
            ];
        }
        
        $this->logger->error('FHIR operation failed', $context);
    }

    /**
     * Create OperationOutcome for successful operations with warnings
     */
    public function createWarningOutcome(array $warnings): array
    {
        $issues = array_map(function ($warning) {
            return [
                'severity' => 'warning',
                'code' => $warning['code'] ?? 'informational',
                'details' => [
                    'text' => $warning['message'] ?? 'Warning'
                ],
                'expression' => $warning['expression'] ?? []
            ];
        }, $warnings);
        
        return [
            'resourceType' => 'OperationOutcome',
            'issue' => $issues
        ];
    }

    /**
     * Merge OperationOutcomes
     */
    public function mergeOperationOutcomes(array ...$outcomes): array
    {
        $allIssues = [];
        
        foreach ($outcomes as $outcome) {
            if (isset($outcome['issue']) && is_array($outcome['issue'])) {
                $allIssues = array_merge($allIssues, $outcome['issue']);
            }
        }
        
        return [
            'resourceType' => 'OperationOutcome',
            'issue' => $allIssues
        ];
    }

    /**
     * Check if response is an error OperationOutcome
     */
    public function isErrorOutcome(array $response): bool
    {
        if (($response['resourceType'] ?? '') !== 'OperationOutcome') {
            return false;
        }
        
        // Check if any issues are errors or fatal
        foreach ($response['issue'] ?? [] as $issue) {
            if (in_array($issue['severity'] ?? '', ['error', 'fatal'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract error message from OperationOutcome
     */
    public function extractErrorMessage(array $operationOutcome): string
    {
        $messages = [];
        
        foreach ($operationOutcome['issue'] ?? [] as $issue) {
            if (in_array($issue['severity'] ?? '', ['error', 'fatal'])) {
                $text = $issue['details']['text'] ?? $issue['diagnostics'] ?? 'Unknown error';
                $messages[] = $text;
            }
        }
        
        return implode('; ', $messages) ?: 'Unknown error';
    }
}