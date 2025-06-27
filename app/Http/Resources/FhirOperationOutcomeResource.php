<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FhirOperationOutcomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $operationOutcome = $this->resource;
        
        // Ensure it's a valid OperationOutcome structure
        if (!is_array($operationOutcome) || !isset($operationOutcome['resourceType'])) {
            $operationOutcome = $this->createDefaultOperationOutcome();
        }
        
        return $operationOutcome;
    }

    /**
     * Create a default OperationOutcome structure
     */
    private function createDefaultOperationOutcome(): array
    {
        return [
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => 'exception',
                    'details' => [
                        'text' => 'An unexpected error occurred',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create OperationOutcome from exception
     */
    public static function fromException(\Exception $exception): self
    {
        $operationOutcome = [
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => static::getIssueCode($exception),
                    'details' => [
                        'text' => $exception->getMessage(),
                    ],
                    'diagnostics' => config('app.debug') ? $exception->getTraceAsString() : null,
                ],
            ],
        ];
        
        return new self($operationOutcome);
    }

    /**
     * Create OperationOutcome from validation errors
     */
    public static function fromValidationErrors(array $errors): self
    {
        $issues = [];
        
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'invalid',
                    'details' => [
                        'text' => $message,
                    ],
                    'expression' => [$field],
                ];
            }
        }
        
        $operationOutcome = [
            'resourceType' => 'OperationOutcome',
            'issue' => $issues,
        ];
        
        return new self($operationOutcome);
    }

    /**
     * Get issue code from exception type
     */
    private static function getIssueCode(\Exception $exception): string
    {
        return match (get_class($exception)) {
            \Illuminate\Validation\ValidationException::class => 'invalid',
            \Illuminate\Auth\AuthenticationException::class => 'security',
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => 'not-found',
            \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class => 'not-supported',
            \App\Exceptions\FhirException::class => $exception->getIssueCode(),
            default => 'exception',
        };
    }

    /**
     * Customize the response headers
     */
    public function withResponse(Request $request, \Illuminate\Http\JsonResponse $response): void
    {
        $response->header('Content-Type', 'application/fhir+json');
    }
}