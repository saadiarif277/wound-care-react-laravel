<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DocuSealFieldFormatterService
{
    /**
     * Format a value based on the field type for DocuSeal
     */
    public function formatFieldValue($value, string $fieldType, array $options = []): mixed
    {
        switch ($fieldType) {
            case 'checkbox':
                return $this->formatCheckbox($value);

            case 'radio':
                return $this->formatRadio($value, $options);

            case 'select':
                return $this->formatSelect($value, $options);

            case 'date':
                return $this->formatDate($value);

            case 'phone':
                return $this->formatPhone($value);

            case 'currency':
                return $this->formatCurrency($value);

            case 'number':
                return $this->formatNumber($value);

            case 'multiselect':
                return $this->formatMultiSelect($value, $options);

            case 'text':
            case 'textarea':
            default:
                return $this->formatText($value);
        }
    }

    /**
     * Format checkbox values for DocuSeal
     * DocuSeal expects: true/false or "checked"/"unchecked" or "Yes"/"No"
     */
    private function formatCheckbox($value): string
    {
        // Handle various input formats
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            // Check for truthy values
            $truthyValues = ['yes', 'y', 'true', '1', 'on', 'checked', 'x'];
            if (in_array($value, $truthyValues)) {
                return 'Yes';
            }

            // Check for falsy values
            $falsyValues = ['no', 'n', 'false', '0', 'off', 'unchecked', ''];
            if (in_array($value, $falsyValues)) {
                return 'No';
            }
        }

        // For numeric values
        if (is_numeric($value)) {
            return $value > 0 ? 'Yes' : 'No';
        }

        // Default to No for null or empty
        return 'No';
    }

    /**
     * Format radio button values
     */
    private function formatRadio($value, array $options): string
    {
        if (empty($value)) {
            return '';
        }

        // If options are provided, ensure the value matches exactly
        if (!empty($options)) {
            foreach ($options as $option) {
                if (strcasecmp($option, $value) === 0) {
                    return $option;
                }
            }
        }

        return (string) $value;
    }

    /**
     * Format select/dropdown values
     */
    private function formatSelect($value, array $options): string
    {
        if (empty($value)) {
            return '';
        }

        // Map common variations
        $valueMappings = [
            // Place of Service mappings
            '11' => 'Physician Office (POS 11)',
            '22' => 'Hospital Outpatient (POS 22)',
            '24' => 'Ambulatory Surgical Center (POS 24)',
            '12' => 'Home (POS 12)',
            '13' => 'Assisted Living Facility (POS 13)',
            '31' => 'Skilled Nursing Facility (POS 31)',
            '32' => 'Nursing Facility (POS 32)',

            // Insurance type mappings
            'medicare' => 'Medicare',
            'medicaid' => 'Medicaid',
            'commercial' => 'Commercial Insurance',
            'self_pay' => 'Self Pay',

            // Network status mappings
            'in_network' => 'In-Network',
            'out_of_network' => 'Out-of-Network',
            'unknown' => 'Not Sure (Please verify)',
        ];

        $normalizedValue = strtolower(trim($value));
        if (isset($valueMappings[$normalizedValue])) {
            return $valueMappings[$normalizedValue];
        }

        // If options are provided, find best match
        if (!empty($options)) {
            foreach ($options as $option) {
                if (strcasecmp($option, $value) === 0) {
                    return $option;
                }
            }
        }

        return (string) $value;
    }

    /**
     * Format date values
     * DocuSeal typically expects MM/DD/YYYY format
     */
    private function formatDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            // Handle various date formats
            if ($value instanceof \DateTime) {
                return $value->format('m/d/Y');
            }

            // Parse the date
            $date = Carbon::parse($value);
            return $date->format('m/d/Y');
        } catch (\Exception $e) {
            // If parsing fails, return original value
            return (string) $value;
        }
    }

    /**
     * Format phone numbers
     * DocuSeal typically expects (XXX) XXX-XXXX format
     */
    private function formatPhone($value): string
    {
        if (empty($value)) {
            return '';
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $value);

        // Format based on length
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        } elseif (strlen($phone) === 11 && $phone[0] === '1') {
            // Remove country code
            $phone = substr($phone, 1);
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }

        // Return original if format doesn't match
        return $value;
    }

    /**
     * Format currency values
     */
    private function formatCurrency($value): string
    {
        if (empty($value) || !is_numeric($value)) {
            return '$0.00';
        }

        return '$' . number_format((float) $value, 2, '.', ',');
    }

    /**
     * Format number values
     */
    private function formatNumber($value): string
    {
        if (empty($value)) {
            return '0';
        }

        // Remove any non-numeric characters except decimal point
        $number = preg_replace('/[^0-9.]/', '', $value);

        return $number ?: '0';
    }

    /**
     * Format multi-select values
     * DocuSeal expects comma-separated values or array
     */
    private function formatMultiSelect($value, array $options): string
    {
        if (empty($value)) {
            return '';
        }

        // If it's already an array
        if (is_array($value)) {
            return implode(', ', $value);
        }

        // If it's a string, it might already be comma-separated
        if (is_string($value)) {
            // Clean up spacing around commas
            return preg_replace('/\s*,\s*/', ', ', trim($value));
        }

        return (string) $value;
    }

    /**
     * Format text values
     */
    private function formatText($value): string
    {
        if (empty($value)) {
            return '';
        }

        // Convert to string and trim
        $text = trim((string) $value);

        // Handle special characters that might cause issues in PDFs
        $text = str_replace(["“", "”", "‘", "’"], ['"', '"', "'", "'"], $text);

        return $text;
    }

    /**
     * Detect field type based on field name and value
     */
    public function detectFieldType(string $fieldName, $value = null): string
    {
        $fieldName = strtolower($fieldName);

        // Check for checkbox indicators
        if (Str::contains($fieldName, ['status', 'is_', 'has_', 'permission', 'required', 'attached'])) {
            return 'checkbox';
        }

        // Check for date fields
        if (Str::contains($fieldName, ['date', 'dob', '_at', 'birth'])) {
            return 'date';
        }

        // Check for phone fields
        if (Str::contains($fieldName, ['phone', 'fax', 'tel'])) {
            return 'phone';
        }

        // Check for currency fields
        if (Str::contains($fieldName, ['price', 'cost', 'amount', 'fee', 'charge'])) {
            return 'currency';
        }

        // Check for number fields
        if (Str::contains($fieldName, ['number', 'count', 'qty', 'quantity', 'days', 'size'])) {
            return 'number';
        }

        // Check for select fields
        if (Str::contains($fieldName, ['type', 'status', 'place_of_service', 'plan_type'])) {
            return 'select';
        }

        // Check for multi-select fields
        if (Str::contains($fieldName, ['codes', 'products', 'services'])) {
            return 'multiselect';
        }

        // Default to text
        return 'text';
    }

    /**
     * Batch format multiple fields
     */
    public function formatFields(array $fields, array $fieldTypes = []): array
    {
        $formatted = [];

        foreach ($fields as $fieldName => $value) {
            // Determine field type
            $fieldType = $fieldTypes[$fieldName] ?? $this->detectFieldType($fieldName, $value);

            // Format the value
            $formatted[$fieldName] = $this->formatFieldValue($value, $fieldType);
        }

        return $formatted;
    }
}
