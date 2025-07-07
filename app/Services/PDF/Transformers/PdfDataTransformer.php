<?php

namespace App\Services\PDF\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Data transformation functions for PDF field mapping
 */
class PdfDataTransformer
{
    /**
     * Format date for PDF
     */
    public static function formatDate($value, string $format = 'm/d/Y'): string
    {
        if (empty($value)) {
            return '';
        }
        
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Format date as MM/DD/YYYY
     */
    public static function formatDateMMDDYYYY($value): string
    {
        return self::formatDate($value, 'm/d/Y');
    }
    
    /**
     * Format date as YYYY-MM-DD
     */
    public static function formatDateISO($value): string
    {
        return self::formatDate($value, 'Y-m-d');
    }
    
    /**
     * Combine multiple fields with separator
     */
    public static function combineFields(array $data, string $fields, string $separator = ' '): string
    {
        $fieldList = explode(',', $fields);
        $values = [];
        
        foreach ($fieldList as $field) {
            $field = trim($field);
            $value = data_get($data, $field, '');
            if (!empty($value)) {
                $values[] = $value;
            }
        }
        
        return implode($separator, $values);
    }
    
    /**
     * Format full name from parts
     */
    public static function formatFullName(array $data, string $pattern = 'first_name,middle_name,last_name'): string
    {
        return self::combineFields($data, $pattern, ' ');
    }
    
    /**
     * Format address from components
     */
    public static function formatAddress(array $data, string $baseField = 'address'): string
    {
        $parts = [];
        
        // Line 1 and 2
        $line1 = data_get($data, $baseField . '.line1') ?? data_get($data, $baseField . '_line1', '');
        $line2 = data_get($data, $baseField . '.line2') ?? data_get($data, $baseField . '_line2', '');
        
        if ($line1) $parts[] = $line1;
        if ($line2) $parts[] = $line2;
        
        // City, State, ZIP
        $city = data_get($data, $baseField . '.city') ?? data_get($data, $baseField . '_city', '');
        $state = data_get($data, $baseField . '.state') ?? data_get($data, $baseField . '_state', '');
        $zip = data_get($data, $baseField . '.zip') ?? data_get($data, $baseField . '_zip', '');
        
        $cityStateZip = trim("$city, $state $zip");
        if (strlen($cityStateZip) > 3) { // More than just ", "
            $parts[] = $cityStateZip;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Format phone number
     */
    public static function formatPhone($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $value);
        
        // Format as (XXX) XXX-XXXX if 10 digits
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        return $value;
    }
    
    /**
     * Map diagnosis codes to manufacturer format
     */
    public static function mapDiagnosisCodes($codes): string
    {
        if (!is_array($codes)) {
            return is_string($codes) ? $codes : '';
        }
        
        // Extract just the code values if they're objects
        $codeStrings = array_map(function($code) {
            if (is_string($code)) {
                return $code;
            }
            if (is_array($code) && isset($code['code'])) {
                return $code['code'];
            }
            if (is_object($code) && isset($code->code)) {
                return $code->code;
            }
            return '';
        }, $codes);
        
        // Filter empty values and take first 3
        $codeStrings = array_filter($codeStrings);
        $codeStrings = array_slice($codeStrings, 0, 3);
        
        return implode(', ', $codeStrings);
    }
    
    /**
     * Format product details for display
     */
    public static function formatProductDetails($products): string
    {
        if (!is_array($products)) {
            return '';
        }
        
        $details = [];
        
        foreach ($products as $product) {
            $name = $product['name'] ?? $product['product']['name'] ?? 'Unknown Product';
            $size = $product['size'] ?? 'Standard';
            $quantity = $product['quantity'] ?? 1;
            
            $details[] = sprintf("%s - Size: %s, Qty: %d", $name, $size, $quantity);
        }
        
        return implode("\n", $details);
    }
    
    /**
     * Convert boolean to Yes/No
     */
    public static function booleanToYesNo($value): string
    {
        return $value ? 'Yes' : 'No';
    }
    
    /**
     * Convert boolean to checkbox value
     */
    public static function booleanToCheckbox($value): string
    {
        return $value ? 'Yes' : 'Off';
    }
    
    /**
     * Format currency
     */
    public static function formatCurrency($value): string
    {
        if (!is_numeric($value)) {
            return '$0.00';
        }
        
        return '$' . number_format((float)$value, 2);
    }
    
    /**
     * Format percentage
     */
    public static function formatPercentage($value): string
    {
        if (!is_numeric($value)) {
            return '0%';
        }
        
        return number_format((float)$value, 0) . '%';
    }
    
    /**
     * Calculate wound size total
     */
    public static function calculateWoundSize(array $data): string
    {
        $length = floatval(data_get($data, 'wound_size_length', 0));
        $width = floatval(data_get($data, 'wound_size_width', 0));
        
        if ($length > 0 && $width > 0) {
            return number_format($length * $width, 2);
        }
        
        return '0';
    }
    
    /**
     * Get current date
     */
    public static function currentDate(string $format = 'm/d/Y'): string
    {
        return now()->format($format);
    }
    
    /**
     * Convert string to uppercase
     */
    public static function toUpperCase($value): string
    {
        return strtoupper($value ?? '');
    }
    
    /**
     * Convert string to lowercase
     */
    public static function toLowerCase($value): string
    {
        return strtolower($value ?? '');
    }
    
    /**
     * Convert string to title case
     */
    public static function toTitleCase($value): string
    {
        return Str::title($value ?? '');
    }
    
    /**
     * Truncate string to length
     */
    public static function truncate($value, int $length = 50): string
    {
        return Str::limit($value ?? '', $length, '');
    }
    
    /**
     * Extract first name from full name
     */
    public static function extractFirstName($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        $parts = explode(' ', trim($value));
        return $parts[0] ?? '';
    }
    
    /**
     * Extract last name from full name
     */
    public static function extractLastName($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        $parts = explode(' ', trim($value));
        return count($parts) > 1 ? end($parts) : '';
    }
    
    /**
     * Map wound type to checkboxes
     */
    public static function mapWoundTypeCheckbox($woundType, string $checkboxType): string
    {
        $woundType = strtolower($woundType ?? '');
        $checkboxType = strtolower($checkboxType);
        
        $mappings = [
            'dfu' => ['dfu', 'diabetic foot ulcer'],
            'vlu' => ['vlu', 'venous leg ulcer', 'venous ulcer'],
            'chronic_ulcer' => ['chronic ulcer', 'chronic'],
            'dehisced_surgical' => ['dehisced', 'surgical', 'dehisced surgical'],
            'mohs_surgical' => ['mohs', 'mohs surgical']
        ];
        
        foreach ($mappings[$checkboxType] ?? [] as $match) {
            if (str_contains($woundType, $match)) {
                return 'Yes';
            }
        }
        
        return 'Off';
    }
    
    /**
     * Map product code to checkbox
     */
    public static function mapProductCodeCheckbox($productCodes, string $targetCode): string
    {
        if (!is_array($productCodes)) {
            $productCodes = [$productCodes];
        }
        
        return in_array($targetCode, $productCodes) ? 'Yes' : 'Off';
    }
    
    /**
     * Get age from date of birth
     */
    public static function calculateAge($dob): string
    {
        if (empty($dob)) {
            return '';
        }
        
        try {
            return (string) Carbon::parse($dob)->age;
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Format SSN with dashes
     */
    public static function formatSSN($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Remove all non-numeric characters
        $ssn = preg_replace('/[^0-9]/', '', $value);
        
        // Format as XXX-XX-XXXX if 9 digits
        if (strlen($ssn) === 9) {
            return sprintf('%s-%s-%s',
                substr($ssn, 0, 3),
                substr($ssn, 3, 2),
                substr($ssn, 5, 4)
            );
        }
        
        return $value;
    }
}