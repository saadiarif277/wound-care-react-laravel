<?php

namespace App\Helpers;

class FriendlyTagHelper
{
    /**
     * Generate a friendly patient tag from first and last names.
     * This matches the implementation in PatientService for consistency.
     * Format: First 2 letters of first name + First 2 letters of last name + random number
     * Example: John Smith -> JOSM473
     * 
     * @param string $firstName
     * @param string $lastName
     * @param bool $includeRandom Whether to include random numbers (default: true)
     * @return string
     */
    public static function generate(string $firstName, string $lastName, bool $includeRandom = true): string
    {
        // Extract and clean the first 2 letters of each name
        $first = substr(preg_replace('/[^a-zA-Z]/', '', $firstName), 0, 2);
        $last = substr(preg_replace('/[^a-zA-Z]/', '', $lastName), 0, 2);
        
        // Handle short names - pad with X
        $first = str_pad($first, 2, 'X');
        $last = str_pad($last, 2, 'X');
        
        $tag = strtoupper($first . $last);
        
        // Add random numbers if requested
        if ($includeRandom) {
            $tag .= mt_rand(100, 999);
        }
        
        return $tag;
    }
    
    /**
     * Generate a tag with a specific suffix (useful for order IDs).
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $suffix
     * @return string
     */
    public static function generateWithSuffix(string $firstName, string $lastName, string $suffix): string
    {
        $baseTag = self::generate($firstName, $lastName, false);
        return $baseTag . '-' . strtoupper($suffix);
    }
    
    /**
     * Check if a tag is valid format.
     * 
     * @param string $tag
     * @return bool
     */
    public static function isValid(string $tag): bool
    {
        // Valid formats: ABCD123 or ABCD-XXX
        return preg_match('/^[A-Z]{4}(\d{3}|-.+)?$/', $tag) === 1;
    }
}