<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class IvrFormExtractionService
{
    private ?string $endpoint;
    private ?string $apiKey;

    public function __construct()
    {
        $this->endpoint = config('services.azure_di.endpoint');
        $this->apiKey = config('services.azure_di.key');
    }

    /**
     * Extract fields from a manufacturer IVR form PDF
     */
    public function extractFieldsFromPdf(string $pdfPath, string $manufacturerId): array
    {
        $result = $this->extractFieldsAndMetadata($pdfPath, $manufacturerId);
        return $result['fields'] ?? [];
    }
    
    /**
     * Extract fields and metadata from a manufacturer IVR form PDF
     */
    public function extractFieldsAndMetadata(string $pdfPath, string $manufacturerId): array
    {
        try {
            // For now, we'll use a mock implementation until Azure DI is configured
            // In production, this would call Azure Document Intelligence API
            
            if (!$this->endpoint || !$this->apiKey) {
                Log::warning('Azure Document Intelligence not configured, using mock extraction');
                return $this->mockExtraction($manufacturerId);
            }

            // Read PDF file
            $pdfContent = file_get_contents($pdfPath);
            
            // Call Azure Document Intelligence API using prebuilt-document model
            $analyzeUrl = "{$this->endpoint}/formrecognizer/documentModels/prebuilt-document:analyze?api-version=2023-07-31";
            
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/octet-stream'
            ])->withBody($pdfContent, 'application/octet-stream')
              ->post($analyzeUrl);

            if (!$response->successful()) {
                throw new Exception('Azure DI API error: ' . $response->body());
            }

            // Get operation location for polling
            $operationLocation = $response->header('Operation-Location');
            
            // Poll for results
            $result = $this->pollForResults($operationLocation);
            
            // Process and structure the extracted data
            return $this->processExtractedDataWithMetadata($result, $manufacturerId);
            
        } catch (Exception $e) {
            Log::error('IVR form extraction failed', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId
            ]);
            
            // Return mock data for development
            return $this->mockExtractionWithMetadata($manufacturerId);
        }
    }

    /**
     * Poll Azure DI for analysis results
     */
    private function pollForResults(string $operationLocation): array
    {
        $maxAttempts = 30;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(1); // Wait 1 second between polls
            
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey
            ])->get($operationLocation);
            
            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['status'] === 'succeeded') {
                    return $result['analyzeResult'];
                } elseif ($result['status'] === 'failed') {
                    throw new Exception('Document analysis failed: ' . ($result['error']['message'] ?? 'Unknown error'));
                }
            }
            
            $attempt++;
        }
        
        throw new Exception('Document analysis timed out');
    }

    /**
     * Process extracted data into structured fields with metadata
     */
    private function processExtractedDataWithMetadata(array $analyzeResult, string $manufacturerId): array
    {
        $metadata = $this->extractFormMetadata($analyzeResult, $manufacturerId);
        $fields = $this->processExtractedData($analyzeResult);
        
        return [
            'fields' => $fields,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Extract form metadata from the document
     */
    private function extractFormMetadata(array $analyzeResult, string $manufacturerId): array
    {
        $metadata = [
            'manufacturer' => $manufacturerId,
            'form_type' => 'IVR',
            'detected_products' => [],
            'form_title' => null,
            'form_version' => null,
            'extraction_date' => now()->toIso8601String()
        ];
        
        // Extract form title from first page
        if (isset($analyzeResult['content'])) {
            $content = $analyzeResult['content'];
            
            // Look for form title patterns
            if (preg_match('/^(.+?)\n/m', $content, $matches)) {
                $metadata['form_title'] = trim($matches[1]);
            }
            
            // Look for form version
            if (preg_match('/(?:Version|Rev|v\.)\s*([\d\.]+)/i', $content, $matches)) {
                $metadata['form_version'] = $matches[1];
            }
        }
        
        // Extract products from checkboxes and content
        $productPattern = '/Q\d{4}/';
        $productNames = [];
        
        if (isset($analyzeResult['keyValuePairs'])) {
            foreach ($analyzeResult['keyValuePairs'] as $kvp) {
                $key = $this->getTextContent($kvp['key'] ?? null);
                if ($key && preg_match($productPattern, $key, $matches)) {
                    $productCode = $matches[0];
                    $productName = $this->cleanFieldName($key);
                    $metadata['detected_products'][] = [
                        'code' => $productCode,
                        'name' => $productName,
                        'confidence' => $kvp['confidence'] ?? 0.0
                    ];
                }
            }
        }
        
        // Detect form type from content
        if (isset($analyzeResult['content'])) {
            $contentLower = strtolower($analyzeResult['content']);
            if (strpos($contentLower, 'invoice verification') !== false) {
                $metadata['form_type'] = 'IVR';
            } elseif (strpos($contentLower, 'order form') !== false) {
                $metadata['form_type'] = 'Order';
            } elseif (strpos($contentLower, 'onboarding') !== false) {
                $metadata['form_type'] = 'Onboarding';
            }
        }
        
        return $metadata;
    }
    
    /**
     * Process extracted data into structured fields
     */
    private function processExtractedData(array $analyzeResult): array
    {
        $fields = [];
        
        // Extract key-value pairs
        if (isset($analyzeResult['keyValuePairs'])) {
            foreach ($analyzeResult['keyValuePairs'] as $kvp) {
                $key = $this->getTextContent($kvp['key'] ?? null);
                $value = $this->getTextContent($kvp['value'] ?? null);
                
                if ($key) {
                    $fields[] = [
                        'field_name' => $this->cleanFieldName($key),
                        'original_text' => $key,
                        'field_type' => $this->detectFieldType($key, $value),
                        'extracted_value' => $value,
                        'confidence' => $kvp['confidence'] ?? 0.0,
                        'category' => $this->categorizeField($key),
                        'is_checkbox' => $this->isCheckboxField($key)
                    ];
                }
            }
        }
        
        // Extract tables (for NPI lists, etc.)
        if (isset($analyzeResult['tables'])) {
            foreach ($analyzeResult['tables'] as $tableIndex => $table) {
                $tableFields = $this->extractTableFields($table, $tableIndex);
                $fields = array_merge($fields, $tableFields);
            }
        }
        
        return $fields;
    }

    /**
     * Get text content from Azure DI field value
     */
    private function getTextContent($field): ?string
    {
        if (!$field) return null;
        
        if (isset($field['content'])) {
            return $field['content'];
        }
        
        return null;
    }

    /**
     * Clean field name for mapping
     */
    private function cleanFieldName(string $fieldName): string
    {
        // Remove checkbox indicators
        $cleaned = preg_replace('/^(Check\s+|☐\s*|☑\s*|\[\s*\]\s*)/', '', $fieldName);
        
        // Remove trailing colons
        $cleaned = rtrim($cleaned, ':');
        
        // Normalize whitespace
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));
        
        return $cleaned;
    }

    /**
     * Detect field type based on name and value
     */
    private function detectFieldType(string $fieldName, ?string $value): string
    {
        $fieldLower = strtolower($fieldName);
        
        // Checkbox detection
        if ($this->isCheckboxField($fieldName)) {
            return 'checkbox';
        }
        
        // Date fields
        if (preg_match('/(date|dob|birth)/i', $fieldLower)) {
            return 'date';
        }
        
        // Phone fields
        if (preg_match('/(phone|fax|tel)/i', $fieldLower)) {
            return 'phone';
        }
        
        // Email fields
        if (preg_match('/email/i', $fieldLower)) {
            return 'email';
        }
        
        // Number fields
        if (preg_match('/(npi|policy|id|number|#|zip|code)/i', $fieldLower)) {
            return 'number';
        }
        
        // Boolean fields
        if (preg_match('/(yes|no)$/i', $fieldLower)) {
            return 'boolean';
        }
        
        return 'text';
    }

    /**
     * Check if field is a checkbox
     */
    private function isCheckboxField(string $fieldName): bool
    {
        return preg_match('/(Check\s+|☐|☑|\[\s*\])/i', $fieldName) || 
               preg_match('/\bQ\d{4}\b/', $fieldName); // Q-codes for products
    }

    /**
     * Categorize field for better organization
     */
    private function categorizeField(string $fieldName): string
    {
        $categories = [
            'product' => '/Q\d{4}|Membrane|Amnio|Shield|maxx|Product/i',
            'provider' => '/Physician|Doctor|Provider|NPI.*Physician|Specialty|DEA|Credentials/i',
            'facility' => '/Facility|Hospital|Office|Center|POS|Place of Service|Site/i',
            'patient' => '/Patient|DOB|Date of Birth|Gender|Sex/i',
            'insurance' => '/Insurance|Policy|Payer|Network|Primary|Secondary|Member/i',
            'clinical' => '/Wound|ICD|Diagnosis|Size|Location|History|Treatment/i',
            'billing' => '/Part A|Hospice|Global|Surgery|CPT|Billing/i',
            'contact' => '/Phone|Email|Fax|Contact|Address|City|State|Zip/i',
            'authorization' => '/Permission|Prior Auth|Authorization|Consent/i'
        ];

        foreach ($categories as $category => $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return $category;
            }
        }
        
        return 'other';
    }

    /**
     * Extract fields from tables
     */
    private function extractTableFields(array $table, int $tableIndex): array
    {
        $fields = [];
        
        // Check if this is an NPI table
        $isNpiTable = false;
        if (isset($table['cells']) && count($table['cells']) > 0) {
            $headerCells = array_filter($table['cells'], fn($cell) => $cell['rowIndex'] === 0);
            foreach ($headerCells as $cell) {
                if (preg_match('/npi|provider|physician/i', $cell['content'] ?? '')) {
                    $isNpiTable = true;
                    break;
                }
            }
        }
        
        if ($isNpiTable) {
            // Extract multiple NPI fields
            $npiCount = 1;
            foreach ($table['cells'] as $cell) {
                if ($cell['rowIndex'] > 0 && preg_match('/^\d{10}$/', $cell['content'] ?? '')) {
                    $fields[] = [
                        'field_name' => "Physician NPI {$npiCount}",
                        'original_text' => "Table NPI Entry {$npiCount}",
                        'field_type' => 'number',
                        'extracted_value' => $cell['content'],
                        'confidence' => 0.9,
                        'category' => 'provider',
                        'is_checkbox' => false,
                        'table_position' => [
                            'table' => $tableIndex,
                            'row' => $cell['rowIndex'],
                            'col' => $cell['columnIndex']
                        ]
                    ];
                    $npiCount++;
                }
            }
        }
        
        return $fields;
    }

    /**
     * Mock extraction with metadata for development/testing
     */
    private function mockExtractionWithMetadata(string $manufacturerId): array
    {
        $fields = $this->mockExtraction($manufacturerId);
        
        $metadata = [
            'manufacturer' => $manufacturerId,
            'form_type' => 'IVR',
            'detected_products' => [
                ['code' => 'Q4205', 'name' => 'Membrane Wrap', 'confidence' => 0.95],
                ['code' => 'Q4239', 'name' => 'Amnio-Maxx', 'confidence' => 0.95],
                ['code' => 'Q4238', 'name' => 'DermACELL AWM', 'confidence' => 0.93],
                ['code' => 'Q4221', 'name' => 'Amniowrap2', 'confidence' => 0.92]
            ],
            'form_title' => $manufacturerId . ' Invoice Verification & Referral Form',
            'form_version' => '2024.1',
            'extraction_date' => now()->toIso8601String()
        ];
        
        return [
            'fields' => $fields,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Mock extraction for development/testing
     */
    private function mockExtraction(string $manufacturerId): array
    {
        // Return sample fields based on the manufacturer
        $mockFields = [
            // Product checkboxes
            [
                'field_name' => 'Membrane Wrap Q4205',
                'original_text' => 'Check Membrane Wrap Q4205',
                'field_type' => 'checkbox',
                'extracted_value' => null,
                'confidence' => 0.95,
                'category' => 'product',
                'is_checkbox' => true
            ],
            [
                'field_name' => 'Amnio-Maxx Q4239',
                'original_text' => 'Check Amnio-Maxx Q4239',
                'field_type' => 'checkbox',
                'extracted_value' => null,
                'confidence' => 0.95,
                'category' => 'product',
                'is_checkbox' => true
            ],
            // Provider fields
            [
                'field_name' => 'Physician Name',
                'original_text' => 'Physician Name:',
                'field_type' => 'text',
                'extracted_value' => null,
                'confidence' => 0.98,
                'category' => 'provider',
                'is_checkbox' => false
            ],
            [
                'field_name' => 'Physician NPI 1',
                'original_text' => 'Physician NPI 1:',
                'field_type' => 'number',
                'extracted_value' => null,
                'confidence' => 0.96,
                'category' => 'provider',
                'is_checkbox' => false
            ],
            [
                'field_name' => 'Physician NPI 2',
                'original_text' => 'Physician NPI 2:',
                'field_type' => 'number',
                'extracted_value' => null,
                'confidence' => 0.96,
                'category' => 'provider',
                'is_checkbox' => false
            ],
            // Facility fields
            [
                'field_name' => 'Facility Name',
                'original_text' => 'Facility Name:',
                'field_type' => 'text',
                'extracted_value' => null,
                'confidence' => 0.98,
                'category' => 'facility',
                'is_checkbox' => false
            ],
            [
                'field_name' => 'Facility NPI 1',
                'original_text' => 'Facility NPI 1:',
                'field_type' => 'number',
                'extracted_value' => null,
                'confidence' => 0.96,
                'category' => 'facility',
                'is_checkbox' => false
            ],
            // Place of Service checkboxes
            [
                'field_name' => 'POS 11 Physician Office',
                'original_text' => '☐ POS 11 Physician Office',
                'field_type' => 'checkbox',
                'extracted_value' => null,
                'confidence' => 0.94,
                'category' => 'facility',
                'is_checkbox' => true
            ],
            [
                'field_name' => 'POS 22 Hospital Outpatient',
                'original_text' => '☐ POS 22 Hospital Outpatient',
                'field_type' => 'checkbox',
                'extracted_value' => null,
                'confidence' => 0.94,
                'category' => 'facility',
                'is_checkbox' => true
            ],
            // Insurance fields
            [
                'field_name' => 'Primary Insurance',
                'original_text' => 'Primary Insurance:',
                'field_type' => 'text',
                'extracted_value' => null,
                'confidence' => 0.97,
                'category' => 'insurance',
                'is_checkbox' => false
            ],
            [
                'field_name' => 'Primary Policy Number',
                'original_text' => 'Primary Policy Number:',
                'field_type' => 'number',
                'extracted_value' => null,
                'confidence' => 0.96,
                'category' => 'insurance',
                'is_checkbox' => false
            ]
        ];
        
        return $mockFields;
    }
}