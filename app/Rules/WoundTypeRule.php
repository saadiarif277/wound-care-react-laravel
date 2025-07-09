<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Wound Type Validation Rule
 * 
 * Validates wound type according to FHIR standards and our system requirements.
 * Supports both legacy format and new standardized codes.
 */
class WoundTypeRule implements ValidationRule
{
    /**
     * Valid wound type codes based on our system
     */
    private const VALID_WOUND_TYPES = [
        'DFU',                    // Diabetic Foot Ulcer
        'VLU',                    // Venous Leg Ulcer  
        'PU',                     // Pressure Ulcer/Injury
        'TW',                     // Traumatic Wound
        'AU',                     // Arterial Ulcer
        'OTHER',                  // Other wound types
        
        // Legacy formats (for backwards compatibility)
        'diabetic_foot_ulcer',
        'venous_leg_ulcer',
        'pressure_ulcer',
        'traumatic_wound',
        'arterial_ulcer',
        'other',
        
        // Full text formats (for form display)
        'Diabetic Foot Ulcer',
        'Venous Leg Ulcer',
        'Pressure Ulcer',
        'Pressure Ulcer/Injury',
        'Traumatic Wound',
        'Arterial Ulcer',
        'Other',
        'SSI',                    // Surgical Site Infection (maps to OTHER)
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        // Normalize the value for comparison
        $normalizedValue = trim($value);
        
        if (empty($normalizedValue)) {
            $fail('The :attribute is required.');
            return;
        }

        // Check against valid wound types (case-insensitive)
        $isValid = false;
        foreach (self::VALID_WOUND_TYPES as $validType) {
            if (strcasecmp($normalizedValue, $validType) === 0) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            $fail('The :attribute must be a valid wound type.');
        }
    }

    /**
     * Get list of valid wound types for frontend
     */
    public static function getValidTypes(): array
    {
        return [
            'DFU' => 'Diabetic Foot Ulcer',
            'VLU' => 'Venous Leg Ulcer',
            'PU' => 'Pressure Ulcer/Injury',
            'TW' => 'Traumatic Wound',
            'AU' => 'Arterial Ulcer',
            'OTHER' => 'Other',
        ];
    }

    /**
     * Normalize wound type to standard format
     */
    public static function normalize(string $woundType): string
    {
        $normalized = trim($woundType);
        
        // Convert legacy formats to standard codes
        $mapping = [
            'diabetic_foot_ulcer' => 'DFU',
            'venous_leg_ulcer' => 'VLU',
            'pressure_ulcer' => 'PU',
            'traumatic_wound' => 'TW',
            'arterial_ulcer' => 'AU',
            'other' => 'OTHER',
            'ssi' => 'OTHER',
            'Diabetic Foot Ulcer' => 'DFU',
            'Venous Leg Ulcer' => 'VLU',
            'Pressure Ulcer' => 'PU',
            'Pressure Ulcer/Injury' => 'PU',
            'Traumatic Wound' => 'TW',
            'Arterial Ulcer' => 'AU',
            'Other' => 'OTHER',
            'SSI' => 'OTHER',
        ];

        return $mapping[strtolower($normalized)] ?? 
               $mapping[$normalized] ?? 
               strtoupper($normalized);
    }
} 