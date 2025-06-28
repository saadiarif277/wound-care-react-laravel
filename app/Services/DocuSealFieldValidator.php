<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DocuSealFieldValidator
{
    /**
     * Validate and clean fields before sending to DocuSeal
     * 
     * @param array $fields Array of fields in DocuSeal format [{name: '', default_value: ''}]
     * @param array $templateFields Valid field names from DocuSeal template
     * @return array Cleaned fields that are guaranteed to work with DocuSeal
     */
    public static function validateAndCleanFields(array $fields, array $templateFields): array
    {
        $validatedFields = [];
        $invalidFields = [];
        $fuzzyMatched = [];
        
        // Create lowercase map for fuzzy matching
        $templateFieldsLower = [];
        foreach ($templateFields as $fieldName => $fieldInfo) {
            $templateFieldsLower[strtolower($fieldName)] = $fieldName;
        }
        
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldValue = $field['default_value'] ?? '';
            
            // Skip empty field names
            if (empty($fieldName)) {
                continue;
            }
            
            // Exact match - field is valid
            if (isset($templateFields[$fieldName])) {
                $validatedFields[] = $field;
                continue;
            }
            
            // Try case-insensitive match
            $fieldNameLower = strtolower($fieldName);
            if (isset($templateFieldsLower[$fieldNameLower])) {
                $correctFieldName = $templateFieldsLower[$fieldNameLower];
                $validatedFields[] = [
                    'name' => $correctFieldName,
                    'default_value' => $fieldValue
                ];
                $fuzzyMatched[] = "$fieldName -> $correctFieldName (case correction)";
                continue;
            }
            
            // Try common transformations
            $transformed = self::tryCommonTransformations($fieldName, $templateFields);
            if ($transformed) {
                $validatedFields[] = [
                    'name' => $transformed,
                    'default_value' => $fieldValue
                ];
                $fuzzyMatched[] = "$fieldName -> $transformed (transformation)";
                continue;
            }
            
            // Try fuzzy matching
            $bestMatch = self::findBestFuzzyMatch($fieldName, array_keys($templateFields));
            if ($bestMatch && $bestMatch['score'] > 0.8) {
                $validatedFields[] = [
                    'name' => $bestMatch['field'],
                    'default_value' => $fieldValue
                ];
                $fuzzyMatched[] = "$fieldName -> {$bestMatch['field']} (fuzzy match: {$bestMatch['score']})";
                continue;
            }
            
            // Field is invalid - record it
            $invalidFields[] = $fieldName;
        }
        
        // Log validation results
        if (!empty($fuzzyMatched)) {
            Log::info('🔄 DocuSeal field fuzzy matching applied', [
                'matches' => $fuzzyMatched
            ]);
        }
        
        if (!empty($invalidFields)) {
            Log::warning('❌ Invalid DocuSeal fields removed', [
                'invalid_count' => count($invalidFields),
                'invalid_fields' => $invalidFields
            ]);
        }
        
        Log::info('✅ DocuSeal field validation complete', [
            'input_count' => count($fields),
            'valid_count' => count($validatedFields),
            'fuzzy_matched' => count($fuzzyMatched),
            'removed_count' => count($invalidFields)
        ]);
        
        return $validatedFields;
    }
    
    /**
     * Try common field name transformations
     */
    private static function tryCommonTransformations(string $fieldName, array $templateFields): ?string
    {
        // Common transformations to try
        $transformations = [
            // provider -> physician
            'provider_npi' => 'Physician NPI',
            'provider_name' => 'Physician Name',
            'provider_ptan' => 'Physician PTAN',
            
            // facility -> practice
            'facility_name' => 'Practice Name',
            'facility_npi' => 'Practice NPI',
            'facility_ptan' => 'Practice PTAN',
            
            // Common variations
            'patient_dob' => 'Patient DOB',
            'patient_name' => 'Patient Name',
            'member_id' => 'Member ID',
            'primary_insurance' => 'Primary Insurance',
            'secondary_insurance' => 'Secondary Insurance',
            
            // Underscore to space
            str_replace('_', ' ', $fieldName) => null,
            
            // Title case variations
            ucwords(str_replace('_', ' ', $fieldName)) => null,
        ];
        
        foreach ($transformations as $from => $to) {
            if (strcasecmp($fieldName, $from) === 0) {
                if ($to && isset($templateFields[$to])) {
                    return $to;
                }
            }
            
            // Try the transformation as a pattern
            if ($to === null) {
                $transformed = $from;
                if (isset($templateFields[$transformed])) {
                    return $transformed;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find best fuzzy match for a field name
     */
    private static function findBestFuzzyMatch(string $needle, array $haystack): ?array
    {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($haystack as $candidate) {
            // Calculate similarity score
            $score = 0;
            
            // Levenshtein distance (normalized)
            $lev = levenshtein(strtolower($needle), strtolower($candidate));
            $maxLen = max(strlen($needle), strlen($candidate));
            $levScore = 1 - ($lev / $maxLen);
            
            // Similar text percentage
            similar_text(strtolower($needle), strtolower($candidate), $percent);
            $similarScore = $percent / 100;
            
            // Combine scores
            $score = ($levScore + $similarScore) / 2;
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'field' => $candidate,
                    'score' => round($score, 2)
                ];
            }
        }
        
        return $bestMatch;
    }
    
    /**
     * Get common field mapping rules
     */
    public static function getCommonMappingRules(): array
    {
        return [
            // Provider fields
            'provider_npi' => ['Physician NPI', 'Provider NPI', 'NPI'],
            'provider_name' => ['Physician Name', 'Provider Name', 'Doctor Name'],
            'provider_credentials' => ['Physician Credentials', 'Credentials'],
            'provider_tax_id' => ['TAX ID', 'Tax ID', 'TIN'],
            'provider_ptan' => ['Physician PTAN', 'PTAN'],
            
            // Facility fields
            'facility_name' => ['Practice Name', 'Facility Name', 'Clinic Name'],
            'facility_address' => ['Practice Address', 'Facility Address'],
            'facility_npi' => ['Practice NPI', 'Facility NPI'],
            'facility_ptan' => ['Practice PTAN', 'Facility PTAN'],
            
            // Patient fields
            'patient_name' => ['Patient Name', 'Patient Full Name'],
            'patient_first_name' => ['Patient First Name', 'First Name'],
            'patient_last_name' => ['Patient Last Name', 'Last Name'],
            'patient_dob' => ['Patient DOB', 'Date of Birth', 'DOB'],
            'patient_gender' => ['Patient Gender', 'Gender', 'Male Female'],
            'patient_phone' => ['Patient Phone', 'Phone Number'],
            'patient_member_id' => ['Member ID', 'Patient Member ID', 'Insurance ID'],
            
            // Insurance fields
            'primary_insurance_name' => ['Primary Insurance', 'Insurance Name'],
            'primary_member_id' => ['Member ID', 'Policy Number'],
            'secondary_insurance_name' => ['Secondary Insurance'],
            'secondary_member_id' => ['Secondary Member ID', 'Secondary Policy Number'],
            
            // Clinical fields
            'wound_location' => ['Wound Location', 'Location', 'Wound Site'],
            'wound_type' => ['Wound Type', 'Type of Wound'],
            'diagnosis_code' => ['ICD-10', 'Diagnosis Code', 'DX Code'],
            'procedure_date' => ['Procedure Date', 'Service Date', 'DOS'],
            
            // Contact fields
            'office_contact_name' => ['Office Contact Name', 'Contact Name'],
            'office_contact_email' => ['Office Contact Email', 'Contact Email'],
            'sales_rep_name' => ['Distributor/Company', 'Sales Rep Name', 'Representative'],
        ];
    }
}