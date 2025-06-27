<?php

namespace App\Services\FieldMapping;

use DateTime;
use InvalidArgumentException;

class FieldTransformer
{
    private array $transformers;

    public function __construct()
    {
        $this->initializeTransformers();
    }

    /**
     * Initialize all available transformers
     */
    private function initializeTransformers(): void
    {
        $this->transformers = [
            'date' => [
                'm/d/Y' => fn($value) => $this->convertToMDY($value),
                'Y-m-d' => fn($value) => $this->convertToISO($value),
                'd/m/Y' => fn($value) => $this->convertToDMY($value),
            ],
            'phone' => [
                'US' => fn($value) => $this->formatUSPhone($value),
                'E164' => fn($value) => $this->formatE164Phone($value),
            ],
            'boolean' => [
                'yes_no' => fn($value) => $this->booleanToYesNo($value),
                '1_0' => fn($value) => $this->booleanToNumeric($value),
                'true_false' => fn($value) => $this->booleanToString($value),
            ],
            'address' => [
                'full' => fn($data) => $this->formatFullAddress($data),
                'line' => fn($data) => $this->formatAddressLine($data),
            ],
            'number' => [
                '0' => fn($value) => $this->roundToInteger($value),
                '2' => fn($value) => $this->roundToTwoDecimals($value),
            ],
            'text' => [
                'upper' => fn($value) => strtoupper($value),
                'lower' => fn($value) => strtolower($value),
                'title' => fn($value) => $this->toTitleCase($value),
            ],
        ];
    }

    /**
     * Transform a value using the specified transformer
     */
    public function transform(mixed $value, ?string $transformer): mixed
    {
        if (!$transformer || $value === null || $value === '') {
            return $value;
        }

        $parts = explode(':', $transformer);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid transformer format: {$transformer}. Expected format: type:format");
        }

        [$type, $format] = $parts;
        
        if (!isset($this->transformers[$type][$format])) {
            throw new InvalidArgumentException("Unknown transformer: {$transformer}");
        }

        try {
            return $this->transformers[$type][$format]($value);
        } catch (\Exception $e) {
            \Log::warning("Transformation failed for {$transformer}: {$e->getMessage()}", [
                'value' => $value,
                'transformer' => $transformer
            ]);
            return $value; // Return original value on error
        }
    }

    /**
     * Date transformation methods
     */
    private function convertToMDY($value): string
    {
        if (empty($value)) return '';
        
        try {
            $date = new DateTime($value);
            return $date->format('m/d/Y');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function convertToISO($value): string
    {
        if (empty($value)) return '';
        
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function convertToDMY($value): string
    {
        if (empty($value)) return '';
        
        try {
            $date = new DateTime($value);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Phone transformation methods
     */
    private function formatUSPhone($value): string
    {
        // Remove all non-numeric characters
        $digits = preg_replace('/\D/', '', $value);
        
        // Format based on length
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            // Handle US numbers with country code
            return sprintf('+1 (%s) %s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7)
            );
        }
        
        return $value;
    }

    private function formatE164Phone($value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }
        
        return $value;
    }

    /**
     * Boolean transformation methods
     */
    private function booleanToYesNo($value): string
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['yes', '1', 'true', 'on'])) {
                return 'Yes';
            } elseif (in_array($value, ['no', '0', 'false', 'off', ''])) {
                return 'No';
            }
        }
        
        return $value ? 'Yes' : 'No';
    }

    private function booleanToNumeric($value): int
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['yes', '1', 'true', 'on'])) {
                return 1;
            } elseif (in_array($value, ['no', '0', 'false', 'off', ''])) {
                return 0;
            }
        }
        
        return $value ? 1 : 0;
    }

    private function booleanToString($value): string
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['yes', '1', 'true', 'on'])) {
                return 'true';
            } elseif (in_array($value, ['no', '0', 'false', 'off', ''])) {
                return 'false';
            }
        }
        
        return $value ? 'true' : 'false';
    }

    /**
     * Address transformation methods
     */
    private function formatFullAddress($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        $parts = array_filter([
            $data['line1'] ?? $data['address_line1'] ?? '',
            $data['line2'] ?? $data['address_line2'] ?? '',
        ]);
        
        $cityStateZip = array_filter([
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['zip'] ?? $data['postal_code'] ?? ''
        ]);
        
        if (!empty($cityStateZip)) {
            $parts[] = implode(', ', $cityStateZip);
        }
        
        return implode(', ', $parts);
    }

    private function formatAddressLine($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return trim(
            ($data['line1'] ?? $data['address_line1'] ?? '') . ' ' . 
            ($data['line2'] ?? $data['address_line2'] ?? '')
        );
    }

    /**
     * Number transformation methods
     */
    private function roundToInteger($value): int
    {
        return (int) round((float) $value);
    }

    private function roundToTwoDecimals($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Text transformation methods
     */
    private function toTitleCase($value): string
    {
        return ucwords(strtolower($value));
    }

    /**
     * Special transformations
     */
    public function formatDuration(array $data): string
    {
        $parts = [];
        
        if (!empty($data['wound_duration_years'])) {
            $years = (int) $data['wound_duration_years'];
            $parts[] = $years . ' ' . ($years === 1 ? 'year' : 'years');
        }
        
        if (!empty($data['wound_duration_months'])) {
            $months = (int) $data['wound_duration_months'];
            $parts[] = $months . ' ' . ($months === 1 ? 'month' : 'months');
        }
        
        if (!empty($data['wound_duration_weeks'])) {
            $weeks = (int) $data['wound_duration_weeks'];
            $parts[] = $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
        }
        
        if (!empty($data['wound_duration_days'])) {
            $days = (int) $data['wound_duration_days'];
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }
        
        return !empty($parts) ? implode(', ', $parts) : 'Not specified';
    }
}