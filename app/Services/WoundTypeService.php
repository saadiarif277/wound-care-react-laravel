<?php

namespace App\Services;

class WoundTypeService
{
    /**
     * Map old wound type format to new enum format
     */
    private const WOUND_TYPE_MAPPING = [
        'diabetic_foot_ulcer' => 'DFU',
        'venous_leg_ulcer' => 'VLU',
        'pressure_ulcer' => 'PU',
        'traumatic_wound' => 'TW',
        'arterial_ulcer' => 'AU',
        'other' => 'OTHER'
    ];

    /**
     * Map new enum format to old format (for backward compatibility)
     */
    private const REVERSE_WOUND_TYPE_MAPPING = [
        'DFU' => 'diabetic_foot_ulcer',
        'VLU' => 'venous_leg_ulcer',
        'PU' => 'pressure_ulcer',
        'TW' => 'traumatic_wound',
        'AU' => 'arterial_ulcer',
        'OTHER' => 'other'
    ];

    /**
     * Normalize wound type to enum format
     */
    public static function normalizeToEnum(string $woundType): string
    {
        return self::WOUND_TYPE_MAPPING[$woundType] ?? $woundType;
    }

    /**
     * Convert enum format to old format
     */
    public static function convertToOldFormat(string $woundType): string
    {
        return self::REVERSE_WOUND_TYPE_MAPPING[$woundType] ?? $woundType;
    }

    /**
     * Get all valid wound types (both old and new formats)
     */
    public static function getAllValidWoundTypes(): array
    {
        return array_merge(
            array_keys(self::WOUND_TYPE_MAPPING),
            array_keys(self::REVERSE_WOUND_TYPE_MAPPING)
        );
    }

    /**
     * Get only enum wound types
     */
    public static function getEnumWoundTypes(): array
    {
        return array_keys(self::REVERSE_WOUND_TYPE_MAPPING);
    }

    /**
     * Check if wound type is valid
     */
    public static function isValid(string $woundType): bool
    {
        return in_array($woundType, self::getAllValidWoundTypes());
    }

    /**
     * Get display name for wound type
     */
    public static function getDisplayName(string $woundType): string
    {
        $displayNames = [
            'DFU' => 'Diabetic Foot Ulcer',
            'VLU' => 'Venous Leg Ulcer',
            'PU' => 'Pressure Ulcer/Injury',
            'TW' => 'Traumatic Wound',
            'AU' => 'Arterial Ulcer',
            'OTHER' => 'Other',
            'diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
            'venous_leg_ulcer' => 'Venous Leg Ulcer',
            'pressure_ulcer' => 'Pressure Ulcer/Injury',
            'traumatic_wound' => 'Traumatic Wound',
            'arterial_ulcer' => 'Arterial Ulcer',
            'other' => 'Other'
        ];

        return $displayNames[$woundType] ?? $woundType;
    }
}
