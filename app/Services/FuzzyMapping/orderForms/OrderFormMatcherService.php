<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Order Form Fuzzy Matching Service
 * 
 * Intelligently extracts and matches data from various manufacturer order forms
 * with support for OCR text, PDF extraction, and manual input
 */
class OrderFormFuzzyMatchingService
{
    /**
     * Manufacturer patterns and field mappings
     */
    private const MANUFACTURER_PATTERNS = [
        'MedLife Solutions' => [
            'identifiers' => ['MEDLIFE', 'AmnioAMP-MP', 'medlifesol.com'],
            'products' => ['AmnioAMP-MP'],
            'fieldMappings' => [
                'facility_name' => ['Company/Facility'],
                'contact_name' => ['Contact Name'],
                'contact_phone' => ['Contact Phone'],
                'shipping_address' => ['Address'],
                'email' => ['customerservice@medlifesol.com'],
            ],
        ],
        'Extremity Care' => [
            'identifiers' => ['ExtremityCare', 'Restorigin', 'completeFT', 'extremitycare.com', 'Q4191', 'Q4271'],
            'products' => ['Restorigin™', 'completeFT™'],
            'fieldMappings' => [
                'facility_name' => ['Facility Name'],
                'requesting_provider' => ['Requesting Provider'],
                'order_date' => ['Order Date'],
                'provider_phone' => ['Provider Phone'],
                'patient_name' => ['Patient Name/Case ID'],
                'email' => ['Email'],
                'date_of_service' => ['Date of Service'],
                'npi_number' => ['NPI Number'],
            ],
        ],
        'ACZ Distribution' => [
            'identifiers' => ['ACZ DISTRIBUTION', 'ACZandAssociates.com', 'ACZ & Associates'],
            'products' => [],
            'fieldMappings' => [
                'account_name' => ['Account Name'],
                'contact_name' => ['Contact Name'],
                'contact_email' => ['Contact e-mail'],
                'contact_phone' => ['Contact Number'],
                'order_date' => ['Date of Order'],
                'anticipated_date' => ['Anticipated Application Date'],
                'po_number' => ['PO#'],
                'patient_id' => ['Patient ID'],
            ],
        ],
        'Advanced Solution' => [
            'identifiers' => ['ADVANCED SOLUTION', 'AdvancedSolution.Health'],
            'products' => [],
            'fieldMappings' => [
                'facility_name' => ['Facility Name'],
                'shipping_contact' => ['Shipping Contact Name'],
                'billing_contact' => ['Billing Contact Name'],
                'shipping_address' => ['Shipping Address'],
                'phone_number' => ['Phone Number'],
                'fax_number' => ['Fax Number'],
                'email' => ['Email Address'],
                'case_date' => ['Date of Case'],
                'arrival_date' => ['Product Arrival Date & Time'],
                'po_number' => ['Purchase Order Number'],
            ],
        ],
        'BioWound Solutions' => [
            'identifiers' => ['BioWound Solutions', 'biowound.com'],
            'products' => [],
            'fieldMappings' => [
                'po_number' => ['PO#'],
                'order_date' => ['DATE'],
                'bill_to' => ['BILL TO'],
                'ship_to' => ['SHIP TO'],
                'salesperson' => ['SALESPERSON'],
                'contact_email' => ['CONTACT EMAIL'],
                'contact_phone' => ['CONTACT PHONE'],
                'delivery_date' => ['REQUESTED DELIVERY DATE'],
                'net_terms' => ['NET TERMS'],
            ],
        ],
        'Imbed Biosciences' => [
            'identifiers' => ['Imbed', 'BIOSCIENCES', 'Microlyte'],
            'products' => ['Microlyte'],
            'fieldMappings' => [
                'facility_name' => ['Facility Name'],
                'address' => ['Address'],
                'email' => ['Email'],
                'phone' => ['Phone'],
                'billing_address' => ['Billing Address'],
                'billing_email' => ['Billing Contact Email'],
                'order_date' => ['Order Date'],
            ],
        ],
        'Skye Biologics' => [
            'identifiers' => ['SKYE', 'skyebiologics.com', 'WoundPlus', 'Q4277'],
            'products' => ['WoundPlus™'],
            'fieldMappings' => [
                'facility_name' => ['Facility name of where procedure will be performed'],
                'physician_name' => ['Physician Name'],
                'patient_name' => ['Patient Name'],
                'date_of_birth' => ['Date of Birth'],
                'npi' => ['NPI'],
                'tin' => ['TIN'],
                'sales_rep' => ['Skye Sales Rep'],
            ],
        ],
    ];

    /**
     * Product size patterns for extraction
     */
    private const SIZE_PATTERNS = [
        '/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*(cm|mm)/i',
        '/(\d+(?:\.\d+)?)\s*(cm|mm)\s*[xX]\s*(\d+(?:\.\d+)?)\s*(cm|mm)/i',
        '/(\d+)\s*sq\s*cm/i',
        '/(\d+(?:\.\d+)?)(cm|mm)\s*(?:disc|Disc)/i',
    ];

    /**
     * Extract and parse order form data
     */
    public function extractOrderFormData(string $text): array
    {
        $data = [
            'manufacturer' => null,
            'confidence_score' => 0,
            'extracted_fields' => [],
            'products' => [],
            'warnings' => [],
        ];

        // Identify manufacturer
        $manufacturerResult = $this->identifyManufacturer($text);
        if ($manufacturerResult) {
            $data['manufacturer'] = $manufacturerResult['name'];
            $data['confidence_score'] = $manufacturerResult['confidence'];
            
            // Extract fields based on manufacturer
            $data['extracted_fields'] = $this->extractFieldsForManufacturer(
                $text, 
                $manufacturerResult['name']
            );
            
            // Extract products
            $data['products'] = $this->extractProducts($text, $manufacturerResult['name']);
        } else {
            // Try generic extraction
            $data['extracted_fields'] = $this->extractGenericFields($text);
            $data['products'] = $this->extractGenericProducts($text);
            $data['warnings'][] = 'Manufacturer could not be identified with confidence';
        }

        // Validate and clean data
        $data = $this->validateAndCleanData($data);

        return $data;
    }

    /**
     * Identify the manufacturer from text
     */
    private function identifyManufacturer(string $text): ?array
    {
        $normalizedText = strtoupper($text);
        $scores = [];

        foreach (self::MANUFACTURER_PATTERNS as $manufacturer => $config) {
            $score = 0;
            $matchCount = 0;

            foreach ($config['identifiers'] as $identifier) {
                if (stripos($normalizedText, strtoupper($identifier)) !== false) {
                    $score += 10;
                    $matchCount++;
                }
            }

            // Check for product mentions
            foreach ($config['products'] ?? [] as $product) {
                if (stripos($normalizedText, strtoupper($product)) !== false) {
                    $score += 5;
                }
            }

            if ($score > 0) {
                $scores[$manufacturer] = [
                    'score' => $score,
                    'confidence' => min(100, $score * 10)
                ];
            }
        }

        if (empty($scores)) {
            return null;
        }

        // Get highest scoring manufacturer
        arsort($scores);
        $topManufacturer = array_key_first($scores);

        return [
            'name' => $topManufacturer,
            'confidence' => $scores[$topManufacturer]['confidence']
        ];
    }

    /**
     * Extract fields for a specific manufacturer
     */
    private function extractFieldsForManufacturer(string $text, string $manufacturer): array
    {
        $fields = [];
        $config = self::MANUFACTURER_PATTERNS[$manufacturer] ?? null;

        if (!$config) {
            return $fields;
        }

        foreach ($config['fieldMappings'] as $fieldKey => $patterns) {
            $value = $this->extractFieldValue($text, $patterns);
            if ($value !== null) {
                $fields[$fieldKey] = $value;
            }
        }

        return $fields;
    }

    /**
     * Extract a field value using fuzzy matching
     */
    private function extractFieldValue(string $text, array $patterns): ?string
    {
        $lines = explode("\n", $text);
        
        foreach ($patterns as $pattern) {
            // Try exact match first
            $value = $this->findValueAfterLabel($lines, $pattern);
            if ($value) {
                return $value;
            }

            // Try fuzzy match
            $value = $this->fuzzyFindValueAfterLabel($lines, $pattern);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Find value after a label (exact match)
     */
    private function findValueAfterLabel(array $lines, string $label): ?string
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, $label) !== false) {
                // Check same line
                $parts = preg_split('/:\s*/', $line, 2);
                if (count($parts) > 1 && trim($parts[1])) {
                    return trim($parts[1]);
                }

                // Check next line
                if (isset($lines[$i + 1]) && trim($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    if (!$this->looksLikeLabel($nextLine)) {
                        return $nextLine;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fuzzy find value after a label
     */
    private function fuzzyFindValueAfterLabel(array $lines, string $label): ?string
    {
        $labelWords = str_word_count(strtolower($label), 1);
        $threshold = 0.7; // 70% match threshold

        foreach ($lines as $i => $line) {
            $lineWords = str_word_count(strtolower($line), 1);
            
            // Calculate similarity
            $matchCount = 0;
            foreach ($labelWords as $labelWord) {
                foreach ($lineWords as $lineWord) {
                    similar_text($labelWord, $lineWord, $percent);
                    if ($percent >= 80) {
                        $matchCount++;
                        break;
                    }
                }
            }

            $similarity = $matchCount / count($labelWords);
            
            if ($similarity >= $threshold) {
                // Extract value similar to exact match
                $parts = preg_split('/[:_\-\s]+/', $line, 2);
                if (count($parts) > 1 && trim($parts[1])) {
                    return trim($parts[1]);
                }

                if (isset($lines[$i + 1]) && trim($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    if (!$this->looksLikeLabel($nextLine)) {
                        return $nextLine;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if a line looks like a label
     */
    private function looksLikeLabel(string $line): bool
    {
        $labelIndicators = [':', 'Name', 'Date', 'Phone', 'Email', 'Address', 'Number', '?'];
        
        foreach ($labelIndicators as $indicator) {
            if (stripos($line, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract products from text
     */
    private function extractProducts(string $text, string $manufacturer): array
    {
        $products = [];
        
        // Try manufacturer-specific extraction first
        $manufacturerProducts = $this->extractManufacturerSpecificProducts($text, $manufacturer);
        if (!empty($manufacturerProducts)) {
            return $manufacturerProducts;
        }

        // Fall back to generic extraction
        return $this->extractGenericProducts($text);
    }

    /**
     * Extract manufacturer-specific products
     */
    private function extractManufacturerSpecificProducts(string $text, string $manufacturer): array
    {
        $products = [];

        switch ($manufacturer) {
            case 'Extremity Care':
                // Pattern: SKU Product Description Size Units Price
                $pattern = '/([A-Z\-\d]+)\s+(Restorigin™|completeFT™)[^\n]*?(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm)?)\s+(\d+)\s+\$?([\d,]+(?:\.\d+)?)/';
                break;
                
            case 'MedLife Solutions':
                // Pattern: AmnioAMP-MP Graft Size Quantity
                $pattern = '/AmnioAMP-MP.*?(\d+)\s*sq\s*cm\s+(\d+x\d+)\s*cm/';
                break;
                
            default:
                return [];
        }

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $products[] = $this->parseProductMatch($match, $manufacturer);
        }

        return $products;
    }

    /**
     * Extract generic products
     */
    private function extractGenericProducts(string $text): array
    {
        $products = [];
        
        // Generic patterns for product extraction
        $patterns = [
            // Pattern 1: Product name, size, quantity, price
            '/([A-Za-z\s\-™®]+?)\s+(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm)?)\s+(\d+)\s+\$?([\d,]+(?:\.\d+)?)/m',
            // Pattern 2: Table format with columns
            '/^([^\t\|]+)[\t\|]\s*([^\t\|]+)[\t\|]\s*(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm)?)[\t\|]\s*(\d+)[\t\|]\s*\$?([\d,]+(?:\.\d+)?)/m',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $product = [
                    'name' => trim($match[1]),
                    'size' => trim($match[2] ?? ''),
                    'quantity' => intval($match[3] ?? 0),
                    'unit_price' => $this->parsePrice($match[4] ?? '0'),
                ];
                
                if ($product['quantity'] > 0) {
                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    /**
     * Parse product match based on manufacturer
     */
    private function parseProductMatch(array $match, string $manufacturer): array
    {
        $product = [
            'sku' => '',
            'name' => '',
            'size' => '',
            'quantity' => 0,
            'unit_price' => 0,
        ];

        switch ($manufacturer) {
            case 'Extremity Care':
                $product['sku'] = $match[1] ?? '';
                $product['name'] = $match[2] ?? '';
                $product['size'] = $match[3] ?? '';
                $product['quantity'] = intval($match[4] ?? 0);
                $product['unit_price'] = $this->parsePrice($match[5] ?? '0');
                break;
                
            case 'MedLife Solutions':
                $product['name'] = 'AmnioAMP-MP';
                $product['size'] = ($match[1] ?? '') . ' sq cm / ' . ($match[2] ?? '');
                break;
        }

        return $product;
    }

    /**
     * Extract generic fields when manufacturer is unknown
     */
    private function extractGenericFields(string $text): array
    {
        $fields = [];
        
        // Common field patterns
        $commonFields = [
            'facility_name' => ['Facility Name', 'Company', 'Account Name', 'Clinic Name'],
            'contact_name' => ['Contact Name', 'Contact', 'Name', 'Requesting Provider'],
            'phone' => ['Phone', 'Phone Number', 'Contact Phone', 'Tel'],
            'email' => ['Email', 'E-mail', 'Email Address'],
            'order_date' => ['Order Date', 'Date', 'Date of Order'],
            'po_number' => ['PO#', 'PO Number', 'Purchase Order', 'Order Number'],
            'address' => ['Address', 'Shipping Address', 'Ship To'],
        ];

        foreach ($commonFields as $fieldKey => $patterns) {
            $value = $this->extractFieldValue($text, $patterns);
            if ($value !== null) {
                $fields[$fieldKey] = $value;
            }
        }

        // Extract email using regex
        if (!isset($fields['email'])) {
            preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $emailMatch);
            if ($emailMatch) {
                $fields['email'] = $emailMatch[1];
            }
        }

        // Extract phone using regex
        if (!isset($fields['phone'])) {
            preg_match('/(\+?1?[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4})/', $text, $phoneMatch);
            if ($phoneMatch) {
                $fields['phone'] = $this->normalizePhone($phoneMatch[1]);
            }
        }

        return $fields;
    }

    /**
     * Parse price string to float
     */
    private function parsePrice(string $price): float
    {
        $price = preg_replace('/[^0-9.]/', '', $price);
        return floatval($price);
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }
        
        return $phone;
    }

    /**
     * Validate and clean extracted data
     */
    private function validateAndCleanData(array $data): array
    {
        // Clean extracted fields
        foreach ($data['extracted_fields'] as $key => $value) {
            $data['extracted_fields'][$key] = $this->cleanFieldValue($value);
        }

        // Validate products
        $data['products'] = array_filter($data['products'], function ($product) {
            return !empty($product['name']) && $product['quantity'] > 0;
        });

        // Add validation warnings
        if (empty($data['extracted_fields']['facility_name'])) {
            $data['warnings'][] = 'Facility name not found';
        }

        if (empty($data['products'])) {
            $data['warnings'][] = 'No products extracted';
        }

        return $data;
    }

    /**
     * Clean field value
     */
    private function cleanFieldValue(string $value): string
    {
        // Remove extra whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Remove trailing punctuation
        $value = rtrim($value, '.,;:');
        
        // Trim
        return trim($value);
    }

    /**
     * Compare two order forms for similarity
     */
    public function calculateSimilarity(array $form1, array $form2): float
    {
        $score = 0;
        $totalComparisons = 0;

        // Compare manufacturers
        if ($form1['manufacturer'] && $form2['manufacturer']) {
            $totalComparisons++;
            if ($form1['manufacturer'] === $form2['manufacturer']) {
                $score += 1;
            }
        }

        // Compare extracted fields
        $commonFields = array_intersect_key(
            $form1['extracted_fields'], 
            $form2['extracted_fields']
        );

        foreach ($commonFields as $field => $value1) {
            $totalComparisons++;
            $value2 = $form2['extracted_fields'][$field];
            
            similar_text(strtolower($value1), strtolower($value2), $percent);
            $score += $percent / 100;
        }

        // Compare products
        if (!empty($form1['products']) && !empty($form2['products'])) {
            $totalComparisons++;
            $productScore = $this->compareProducts($form1['products'], $form2['products']);
            $score += $productScore;
        }

        return $totalComparisons > 0 ? $score / $totalComparisons : 0;
    }

    /**
     * Compare product lists
     */
    private function compareProducts(array $products1, array $products2): float
    {
        $matchScore = 0;
        $comparisons = 0;

        foreach ($products1 as $product1) {
            $bestMatch = 0;
            
            foreach ($products2 as $product2) {
                $productScore = 0;
                $fields = 0;

                // Compare product name
                if ($product1['name'] && $product2['name']) {
                    $fields++;
                    similar_text(
                        strtolower($product1['name']), 
                        strtolower($product2['name']), 
                        $percent
                    );
                    $productScore += $percent / 100;
                }

                // Compare size
                if ($product1['size'] === $product2['size']) {
                    $fields++;
                    $productScore += 1;
                }

                // Compare quantity
                if ($product1['quantity'] === $product2['quantity']) {
                    $fields++;
                    $productScore += 1;
                }

                if ($fields > 0) {
                    $bestMatch = max($bestMatch, $productScore / $fields);
                }
            }

            $matchScore += $bestMatch;
            $comparisons++;
        }

        return $comparisons > 0 ? $matchScore / $comparisons : 0;
    }

    /**
     * Map extracted data to order model
     */
    public function mapToOrderData(array $extractedData): array
    {
        $orderData = [
            'manufacturer_name' => $extractedData['manufacturer'],
            'confidence_score' => $extractedData['confidence_score'],
            'extracted_at' => now(),
        ];

        // Map common fields
        $fieldMapping = [
            'facility_name' => 'facility_name',
            'contact_name' => 'contact_name',
            'phone' => 'contact_phone',
            'email' => 'contact_email',
            'order_date' => 'order_date',
            'po_number' => 'purchase_order_number',
            'address' => 'shipping_address',
            'requesting_provider' => 'provider_name',
            'npi_number' => 'provider_npi',
            'patient_name' => 'patient_name',
            'patient_id' => 'patient_identifier',
        ];

        foreach ($fieldMapping as $extractedField => $orderField) {
            if (isset($extractedData['extracted_fields'][$extractedField])) {
                $orderData[$orderField] = $extractedData['extracted_fields'][$extractedField];
            }
        }

        // Map products
        if (!empty($extractedData['products'])) {
            $orderData['line_items'] = collect($extractedData['products'])->map(function ($product) {
                return [
                    'product_sku' => $product['sku'] ?? null,
                    'product_name' => $product['name'],
                    'size' => $product['size'],
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'] ?? 0,
                    'total_price' => ($product['quantity'] * ($product['unit_price'] ?? 0)),
                ];
            })->toArray();
        }

        return $orderData;
    }
}