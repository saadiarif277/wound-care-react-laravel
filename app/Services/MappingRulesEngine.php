<?php

namespace App\Services;

use Illuminate\Support\Str;
use Carbon\Carbon;

class MappingRulesEngine
{
    /**
     * Apply transformation rules to a value
     */
    public function applyTransformationRules($value, array $rules)
    {
        $transformedValue = $value;

        foreach ($rules as $rule) {
            $transformedValue = $this->applyRule($transformedValue, $rule);
        }

        return $transformedValue;
    }

    /**
     * Apply a single transformation rule
     */
    private function applyRule($value, array $rule)
    {
        $type = $rule['type'] ?? '';
        $operation = $rule['operation'] ?? '';
        $parameters = $rule['parameters'] ?? [];

        switch ($type) {
            case 'parse':
                return $this->parseOperations($value, $operation, $parameters);
            
            case 'format':
                return $this->formatOperations($value, $operation, $parameters);
            
            case 'convert':
                return $this->convertOperations($value, $operation, $parameters);
            
            case 'normalize':
                return $this->normalizeOperations($value, $operation, $parameters);
            
            default:
                return $value;
        }
    }

    /**
     * Parse operations
     */
    private function parseOperations($value, string $operation, array $parameters)
    {
        switch ($operation) {
            case 'address':
                return $this->parseAddress($value);
            
            case 'name':
                return $this->parseName($value, $parameters);
            
            case 'split':
                $delimiter = $parameters['delimiter'] ?? ',';
                $index = $parameters['index'] ?? 0;
                $parts = explode($delimiter, $value);
                return trim($parts[$index] ?? '');
            
            default:
                return $value;
        }
    }

    /**
     * Format operations
     */
    private function formatOperations($value, string $operation, array $parameters)
    {
        switch ($operation) {
            case 'phone':
                return $this->formatPhoneNumber($value);
            
            case 'date':
                $targetFormat = $parameters['format'] ?? 'Y-m-d';
                return $this->formatDate($value, $targetFormat);
            
            case 'ssn':
                return $this->formatSSN($value);
            
            case 'taxid':
                return $this->formatTaxId($value);
            
            case 'uppercase':
                return strtoupper($value);
            
            case 'lowercase':
                return strtolower($value);
            
            case 'titlecase':
                return Str::title($value);
            
            default:
                return $value;
        }
    }

    /**
     * Convert operations
     */
    private function convertOperations($value, string $operation, array $parameters)
    {
        switch ($operation) {
            case 'boolean':
                return $this->normalizeBoolean($value);
            
            case 'pos_code':
                return $this->normalizePOSCode($value);
            
            case 'state_abbr':
                return $this->convertStateAbbreviation($value);
            
            case 'number':
                return $this->convertToNumber($value);
            
            default:
                return $value;
        }
    }

    /**
     * Normalize operations
     */
    private function normalizeOperations($value, string $operation, array $parameters)
    {
        switch ($operation) {
            case 'whitespace':
                return trim(preg_replace('/\s+/', ' ', $value));
            
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', $value);
            
            case 'numeric':
                return preg_replace('/[^0-9]/', '', $value);
            
            case 'remove_special':
                return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $value);
            
            default:
                return $value;
        }
    }

    /**
     * Parse address from combined format
     * Example: "123 Main St, New York, NY 10001" -> ["street" => "123 Main St", "city" => "New York", "state" => "NY", "zip" => "10001"]
     */
    public function parseAddress($combinedAddress): array
    {
        $parts = array_map('trim', explode(',', $combinedAddress));
        
        if (count($parts) >= 3) {
            // Extract state and zip from last part
            $stateZip = trim(end($parts));
            preg_match('/^([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $stateZip, $matches);
            
            return [
                'street' => $parts[0] ?? '',
                'city' => $parts[count($parts) - 2] ?? '',
                'state' => $matches[1] ?? '',
                'zip' => $matches[2] ?? '',
            ];
        }
        
        return [
            'street' => $combinedAddress,
            'city' => '',
            'state' => '',
            'zip' => '',
        ];
    }

    /**
     * Parse name into components
     */
    public function parseName($fullName, array $parameters = []): array
    {
        $format = $parameters['format'] ?? 'first_last';
        $parts = array_filter(array_map('trim', explode(' ', $fullName)));
        
        switch ($format) {
            case 'first_middle_last':
                return [
                    'first' => $parts[0] ?? '',
                    'middle' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '',
                    'last' => end($parts) ?: '',
                ];
            
            case 'last_first':
                if (str_contains($fullName, ',')) {
                    $commaParts = array_map('trim', explode(',', $fullName));
                    return [
                        'first' => $commaParts[1] ?? '',
                        'last' => $commaParts[0] ?? '',
                    ];
                }
                // Fall through to default
            
            default: // first_last
                return [
                    'first' => $parts[0] ?? '',
                    'last' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '',
                ];
        }
    }

    /**
     * Normalize boolean values
     */
    public function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['yes', 'y', 'true', '1', 'on', 'checked', 'x'], true);
    }

    /**
     * Format phone number to (XXX) XXX-XXXX
     */
    public function formatPhoneNumber($phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure we have 10 digits
        if (strlen($phone) !== 10) {
            return $phone; // Return original if not valid
        }

        return sprintf('(%s) %s-%s', 
            substr($phone, 0, 3),
            substr($phone, 3, 3),
            substr($phone, 6)
        );
    }

    /**
     * Normalize Place of Service codes
     */
    public function normalizePOSCode($value): string
    {
        $posMap = [
            'office' => '11',
            'home' => '12',
            'assisted living' => '13',
            'group home' => '14',
            'mobile unit' => '15',
            'temporary lodging' => '16',
            'walk-in retail' => '17',
            'place of employment' => '18',
            'off campus outpatient' => '19',
            'urgent care' => '20',
            'inpatient hospital' => '21',
            'on campus outpatient' => '22',
            'emergency room' => '23',
            'ambulatory surgical center' => '24',
            'birthing center' => '25',
            'military treatment' => '26',
            'skilled nursing' => '31',
            'nursing facility' => '32',
            'custodial care' => '33',
            'hospice' => '34',
            'ambulance land' => '41',
            'ambulance air' => '42',
            'independent clinic' => '49',
            'federally qualified' => '50',
            'inpatient psychiatric' => '51',
            'psychiatric facility' => '52',
            'community mental health' => '53',
            'intermediate care' => '54',
            'residential substance' => '55',
            'psychiatric residential' => '56',
            'non-residential substance' => '57',
            'mass immunization' => '60',
            'comprehensive inpatient' => '61',
            'comprehensive outpatient' => '62',
            'end stage renal' => '65',
            'state or local public' => '71',
            'rural health' => '72',
            'independent laboratory' => '81',
            'other' => '99',
        ];

        // If already a valid POS code, return it
        if (preg_match('/^\d{2}$/', $value)) {
            return $value;
        }

        // Try to match against known descriptions
        $lowercaseValue = strtolower(trim($value));
        foreach ($posMap as $description => $code) {
            if (str_contains($lowercaseValue, $description)) {
                return $code;
            }
        }

        // Default mappings for common scenarios
        if (str_contains($lowercaseValue, 'snf') || str_contains($lowercaseValue, 'skilled')) {
            return '31';
        }
        if (str_contains($lowercaseValue, 'hospital')) {
            return '21';
        }
        if (str_contains($lowercaseValue, 'office') || str_contains($lowercaseValue, 'clinic')) {
            return '11';
        }

        return '99'; // Other
    }

    /**
     * Format date to ISO format
     */
    public function formatDate($date, $targetFormat = 'Y-m-d'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            // Try to parse the date
            $carbonDate = Carbon::parse($date);
            return $carbonDate->format($targetFormat);
        } catch (\Exception $e) {
            // Try common date formats
            $formats = [
                'm/d/Y',
                'm-d-Y',
                'Y-m-d',
                'd/m/Y',
                'd-m-Y',
                'M d, Y',
                'F d, Y',
                'm/d/y',
                'd/m/y',
            ];

            foreach ($formats as $format) {
                try {
                    $carbonDate = Carbon::createFromFormat($format, $date);
                    return $carbonDate->format($targetFormat);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Return original if parsing fails
            return $date;
        }
    }

    /**
     * Validate NPI (10 digits)
     */
    public function validateNPI($npi): bool
    {
        $npi = preg_replace('/[^0-9]/', '', $npi);
        return strlen($npi) === 10;
    }

    /**
     * Validate and format Tax ID (XX-XXXXXXX)
     */
    public function formatTaxId($taxId): string
    {
        $taxId = preg_replace('/[^0-9]/', '', $taxId);
        
        if (strlen($taxId) !== 9) {
            return $taxId; // Return original if not valid
        }

        return substr($taxId, 0, 2) . '-' . substr($taxId, 2);
    }

    /**
     * Format SSN
     */
    public function formatSSN($ssn): string
    {
        $ssn = preg_replace('/[^0-9]/', '', $ssn);
        
        if (strlen($ssn) !== 9) {
            return $ssn;
        }

        return substr($ssn, 0, 3) . '-' . substr($ssn, 3, 2) . '-' . substr($ssn, 5);
    }

    /**
     * Convert state name to abbreviation
     */
    public function convertStateAbbreviation($state): string
    {
        $states = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
            'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
            'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
            'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
            'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
            'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
            'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
            'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
            'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
        ];

        // If already an abbreviation, return it
        if (strlen($state) === 2 && ctype_alpha($state)) {
            return strtoupper($state);
        }

        // Convert full name to abbreviation
        $stateLower = strtolower(trim($state));
        return $states[$stateLower] ?? $state;
    }

    /**
     * Convert value to number
     */
    public function convertToNumber($value)
    {
        if (is_numeric($value)) {
            return $value;
        }

        // Remove common non-numeric characters
        $value = preg_replace('/[$,\s]/', '', $value);
        
        // Handle percentages
        if (str_contains($value, '%')) {
            $value = str_replace('%', '', $value);
            return floatval($value) / 100;
        }

        return is_numeric($value) ? floatval($value) : 0;
    }

    /**
     * Get available transformation rules
     */
    public static function getAvailableRules(): array
    {
        return [
            'parse' => [
                'address' => 'Parse combined address into components',
                'name' => 'Parse full name into first/last components',
                'split' => 'Split value by delimiter',
            ],
            'format' => [
                'phone' => 'Format phone number as (XXX) XXX-XXXX',
                'date' => 'Format date to specified format',
                'ssn' => 'Format SSN as XXX-XX-XXXX',
                'taxid' => 'Format Tax ID as XX-XXXXXXX',
                'uppercase' => 'Convert to uppercase',
                'lowercase' => 'Convert to lowercase',
                'titlecase' => 'Convert to title case',
            ],
            'convert' => [
                'boolean' => 'Convert to boolean true/false',
                'pos_code' => 'Convert to Place of Service code',
                'state_abbr' => 'Convert state name to abbreviation',
                'number' => 'Convert to numeric value',
            ],
            'normalize' => [
                'whitespace' => 'Normalize whitespace',
                'alphanumeric' => 'Keep only alphanumeric characters',
                'numeric' => 'Keep only numeric characters',
                'remove_special' => 'Remove special characters',
            ],
        ];
    }
}