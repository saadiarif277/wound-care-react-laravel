<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PhiSafeLogger
{
    /**
     * PHI fields that should never be logged
     */
    private const PHI_FIELDS = [
        'ssn',
        'social_security_number',
        'date_of_birth',
        'dob',
        'diagnosis',
        'diagnosis_code',
        'diagnosis_description',
        'medical_record_number',
        'mrn',
        'member_id',
        'insurance_id',
        'patient_id',
        'patient_name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip',
        'clinical_notes',
        'wound_details',
        'treatment_details',
    ];

    /**
     * Log a message with PHI fields filtered out
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $safeContext = $this->sanitizeContext($context);
        
        Log::channel('daily')->$level($message, $safeContext);
        
        // Also log to audit channel if PHI was accessed
        if ($this->containedPhi($context, $safeContext)) {
            $this->logPhiAccess($message, $context);
        }
    }

    /**
     * Log PHI access for audit purposes (without the actual PHI data)
     */
    public function logPhiAccess(string $action, array $context = []): void
    {
        $auditData = [
            'action' => $action,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'accessed_fields' => array_keys($context),
            'timestamp' => now()->toIso8601String(),
            'session_id' => session()->getId(),
        ];
        
        Log::channel('phi_audit')->info('PHI Access', $auditData);
    }

    /**
     * Remove PHI fields from context array
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            if ($this->isPhiField($key)) {
                $sanitized[$key] = '[REDACTED-PHI]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $sanitized[$key] = $this->sanitizeContext($value->toArray());
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Check if a field name contains PHI
     */
    private function isPhiField(string $field): bool
    {
        $field = Str::snake($field);
        
        foreach (self::PHI_FIELDS as $phiField) {
            if (Str::contains($field, $phiField)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if PHI was filtered from the context
     */
    private function containedPhi(array $original, array $sanitized): bool
    {
        return json_encode($original) !== json_encode($sanitized);
    }

    /**
     * Convenience methods for different log levels
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}