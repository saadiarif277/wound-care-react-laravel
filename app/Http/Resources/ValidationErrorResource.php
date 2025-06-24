<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class ValidationErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exception = $this->resource;
        
        if (!($exception instanceof ValidationException)) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [],
            ];
        }

        return [
            'success' => false,
            'message' => $exception->getMessage(),
            'errors' => $this->formatErrors($exception->errors()),
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
                'path' => $request->path(),
                'method' => $request->method(),
            ],
        ];
    }

    /**
     * Format validation errors
     */
    private function formatErrors(array $errors): array
    {
        $formatted = [];
        
        foreach ($errors as $field => $messages) {
            $formatted[] = [
                'field' => $field,
                'messages' => $messages,
                'code' => $this->getErrorCode($field, $messages[0] ?? ''),
            ];
        }
        
        return $formatted;
    }

    /**
     * Get error code from validation message
     */
    private function getErrorCode(string $field, string $message): string
    {
        // Map common validation messages to error codes
        $patterns = [
            '/required/' => 'required',
            '/must be a string/' => 'invalid_type',
            '/must be a number/' => 'invalid_type',
            '/must be an integer/' => 'invalid_type',
            '/must be a date/' => 'invalid_date',
            '/must be a valid email/' => 'invalid_email',
            '/must be at least/' => 'too_short',
            '/may not be greater than/' => 'too_long',
            '/must be unique/' => 'not_unique',
            '/does not exist/' => 'not_found',
            '/format is invalid/' => 'invalid_format',
            '/must match/' => 'mismatch',
            '/NPI/' => 'invalid_npi',
            '/ICD/' => 'invalid_diagnosis_code',
        ];
        
        foreach ($patterns as $pattern => $code) {
            if (preg_match($pattern, $message)) {
                return $code;
            }
        }
        
        return 'validation_failed';
    }
}