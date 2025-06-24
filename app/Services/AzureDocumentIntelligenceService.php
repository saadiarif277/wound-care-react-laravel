<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Services\PhiAuditService;

class AzureDocumentIntelligenceService
{
    private ?string $endpoint;
    private ?string $apiKey;
    private string $apiVersion = '2023-07-31';

    public function __construct()
    {
        $this->endpoint = config('services.azure_di.endpoint');
        $this->apiKey = config('services.azure_di.key');
        $this->apiVersion = config('services.azure_di.api_version', '2023-07-31');

        // Only process endpoint if it's not null
        if ($this->endpoint !== null) {
            $this->endpoint = rtrim($this->endpoint, '/');
        }

        if (empty($this->endpoint) || empty($this->apiKey)) {
            Log::warning('Azure Document Intelligence not configured properly');
        }
    }

    /**
     * Check if the Azure Document Intelligence service is properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->endpoint) && !empty($this->apiKey);
    }

    /**
     * Analyze health insurance card using Azure Document Intelligence
     *
     * @param UploadedFile $frontImage
     * @param UploadedFile|null $backImage
     * @return array
     */
    public function analyzeInsuranceCard(UploadedFile $frontImage, ?UploadedFile $backImage = null): array
    {
        // Check if Azure is configured
        if (!$this->isConfigured()) {
            throw new \Exception('Azure Document Intelligence is not configured. Please set AZURE_DI_ENDPOINT and AZURE_DI_KEY in your .env file.');
        }

        try {
            // Analyze front of card
            $frontResults = $this->analyzeDocument($frontImage, 'prebuilt-healthInsuranceCard.us');

            // Analyze back of card if provided
            $backResults = null;
            if ($backImage) {
                $backResults = $this->analyzeDocument($backImage, 'prebuilt-healthInsuranceCard.us');
            }

            // Extract and merge results
            return $this->extractInsuranceCardData($frontResults, $backResults);

        } catch (\Exception $e) {
            Log::error('Azure Document Intelligence Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze a document using Azure Document Intelligence
     *
     * @param UploadedFile|string $file File object or file path
     * @param string|array $modelId Model ID or analysis options
     * @return array
     */
    public function analyzeDocument($file, $modelIdOrOptions = 'prebuilt-document'): array
    {
        // Check if Azure is configured
        if (!$this->isConfigured()) {
            throw new \Exception('Azure Document Intelligence is not configured. Please set AZURE_DI_ENDPOINT and AZURE_DI_KEY in your .env file.');
        }

        // Handle both string model ID and options array
        if (is_array($modelIdOrOptions)) {
            $modelId = $modelIdOrOptions['model_id'] ?? 'prebuilt-document';
            $features = $modelIdOrOptions['features'] ?? [];
        } else {
            $modelId = $modelIdOrOptions;
            $features = [];
        }

        $url = "{$this->endpoint}/documentintelligence/documentModels/{$modelId}:analyze?api-version={$this->apiVersion}";

        // Add features parameter if provided
        if (!empty($features)) {
            $url .= '&features=' . implode(',', $features);
        }

        // Handle both UploadedFile and file path
        if ($file instanceof UploadedFile) {
            $fileContent = $file->get();
            $mimeType = $file->getMimeType();
        } else {
            // Assume it's a file path
            $fileContent = file_get_contents($file);
            $mimeType = mime_content_type($file) ?: 'application/pdf';
        }

        // Start the analysis
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Content-Type' => $mimeType,
        ])->withBody($fileContent, $mimeType)->post($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to start document analysis: ' . $response->body());
        }

        // Get the operation location from headers
        $operationLocation = $response->header('Operation-Location');

        if (!$operationLocation) {
            throw new \Exception('No operation location returned from API');
        }

        // Poll for results
        return $this->pollForResults($operationLocation);
    }

    /**
     * Poll for analysis results
     *
     * @param string $operationLocation
     * @return array
     */
    private function pollForResults(string $operationLocation): array
    {
        // Check if Azure is configured
        if (!$this->isConfigured()) {
            throw new \Exception('Azure Document Intelligence is not configured. Please set AZURE_DI_KEY in your .env file.');
        }

        $maxAttempts = 30;
        $delaySeconds = 2;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($delaySeconds);

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
            ])->get($operationLocation);

            if (!$response->successful()) {
                throw new \Exception('Failed to get analysis results: ' . $response->body());
            }

            $result = $response->json();

            if ($result['status'] === 'succeeded') {
                return $result['analyzeResult'];
            } elseif ($result['status'] === 'failed') {
                throw new \Exception('Document analysis failed: ' . ($result['error']['message'] ?? 'Unknown error'));
            }
        }

        throw new \Exception('Document analysis timed out');
    }

    /**
     * Extract insurance card data from analysis results
     *
     * @param array $frontResults
     * @param array|null $backResults
     * @return array
     */
    private function extractInsuranceCardData(array $frontResults, ?array $backResults): array
    {
        $data = [
            'insurer' => null,
            'member' => [
                'name' => null,
                'id' => null,
                'date_of_birth' => null,
            ],
            'group' => [
                'number' => null,
                'name' => null,
            ],
            'prescription' => [
                'bin' => null,
                'pcn' => null,
                'grp' => null,
            ],
            'plan' => [
                'number' => null,
                'name' => null,
                'type' => null,
            ],
            'copays' => [],
            'payer_id' => null,
            'claims_address' => null,
            'service_numbers' => [],
        ];

        // Process front results
        if (!empty($frontResults['documents'][0]['fields'])) {
            $fields = $frontResults['documents'][0]['fields'];

            // Log all available fields for debugging
            Log::info('Azure Document Intelligence - Available fields', [
                'field_names' => array_keys($fields),
                'full_fields' => $fields // Log complete field structure
            ]);

            // Also log the raw OCR content
            if (isset($frontResults['content'])) {
                Log::info('Azure Document Intelligence - Raw OCR Content', [
                    'content_length' => strlen($frontResults['content']),
                    'content_preview' => substr($frontResults['content'], 0, 500),
                    'full_content' => $frontResults['content']
                ]);
            }

            // Extract insurer information
            if (isset($fields['Insurer'])) {
                $data['insurer'] = $this->getFieldContent($fields['Insurer']);
            }

            // Extract member information
            if (isset($fields['Member'])) {
                $memberFields = $fields['Member']['valueObject'] ?? [];

                Log::info('Azure - Member fields available', [
                    'field_names' => array_keys($memberFields)
                ]);

                if (isset($memberFields['Name'])) {
                    $data['member']['name'] = $this->getFieldContent($memberFields['Name']);
                }

                // Try multiple possible field names for member ID
                $memberIdFields = ['Id', 'ID', 'MemberId', 'MemberID', 'Number'];
                foreach ($memberIdFields as $fieldName) {
                    if (isset($memberFields[$fieldName])) {
                        $data['member']['id'] = $this->getFieldContent($memberFields[$fieldName]);
                        Log::info('Extracted Member ID from Azure', [
                            'field_name' => $fieldName,
                            'raw_field' => $memberFields[$fieldName],
                            'extracted_value' => $data['member']['id']
                        ]);
                        break;
                    }
                }

                if (isset($memberFields['DateOfBirth'])) {
                    $data['member']['date_of_birth'] = $this->getFieldContent($memberFields['DateOfBirth']);
                }
            }

            // Also check for standalone MemberId field
            if (isset($fields['MemberId']) && empty($data['member']['id'])) {
                $data['member']['id'] = $this->getFieldContent($fields['MemberId']);
                Log::info('Extracted Member ID from standalone field', [
                    'value' => $data['member']['id']
                ]);
            }

            // Fallback: Search all fields for member ID patterns
            if (empty($data['member']['id'])) {
                // Check all top-level fields
                $potentialIdFields = ['Id', 'ID', 'MemberId', 'MemberID', 'MemberNumber', 'SubscriberId', 'SubscriberID', 'CardNumber'];
                foreach ($potentialIdFields as $fieldName) {
                    if (isset($fields[$fieldName])) {
                        $content = $this->getFieldContent($fields[$fieldName]);
                        if ($content && !empty($content)) {
                            $data['member']['id'] = $content;
                            Log::info('Extracted Member ID from top-level field', [
                                'field_name' => $fieldName,
                                'value' => $content
                            ]);
                            break;
                        }
                    }
                }

                // If still not found, check all fields with pattern matching
                if (empty($data['member']['id'])) {
                    foreach ($fields as $fieldName => $fieldValue) {
                        // Check if field name contains member/id variations
                        if (preg_match('/member.*id|id.*member|subscriber.*id|card.*number/i', $fieldName)) {
                            $content = $this->getFieldContent($fieldValue);
                            if ($content && !empty($content)) {
                                $data['member']['id'] = $content;
                                Log::info('Extracted Member ID from pattern match', [
                                    'field_name' => $fieldName,
                                    'value' => $content
                                ]);
                                break;
                            }
                        }
                    }
                }

                // Also check the extracted text for MemberID: pattern
                if (empty($data['member']['id']) && isset($frontResults['content'])) {
                    // Try multiple patterns for member ID
                    $patterns = [
                        '/MemberID:\s*([A-Z0-9]+)/i',
                        '/Member\s*ID:\s*([A-Z0-9]+)/i',
                        '/Member\s*#:\s*([A-Z0-9]+)/i',
                        '/ID:\s*([A-Z0-9]+)/i',
                        '/\bID\s+([A-Z0-9]{8,})\b/i',  // ID followed by 8+ alphanumeric chars
                        '/Member.*?([A-Z]\d{8,})/i',     // Member followed by letter + 8+ digits
                        '/MemberID.*?([A-Z0-9]{8,})/i',  // MemberID followed by 8+ chars
                        '/Member[^:]*:\s*([A-Z0-9]{8,})/i', // Member<anything>: followed by ID
                        '/(?:Member|ID)[^A-Z0-9]*([A-Z]\d{8,})/i', // Member or ID followed by letter+digits
                    ];

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $frontResults['content'], $matches)) {
                            $data['member']['id'] = $matches[1];
                            Log::info('Extracted Member ID from text pattern', [
                                'pattern' => $pattern,
                                'value' => $matches[1],
                                'full_match' => $matches[0]
                            ]);
                            break;
                        }
                    }
                }
            }

            // Additional fallback: Check for PolicyNumber field
            if (empty($data['member']['id']) && isset($fields['PolicyNumber'])) {
                $policyNumber = $this->getFieldContent($fields['PolicyNumber']);
                // Sometimes member ID is labeled as policy number
                if ($policyNumber && preg_match('/^[A-Z]?\d{8,}$/i', $policyNumber)) {
                    $data['member']['id'] = $policyNumber;
                    Log::info('Using PolicyNumber as Member ID', [
                        'value' => $policyNumber
                    ]);
                }
            }

            // Extract group information
            if (isset($fields['Group'])) {
                $groupFields = $fields['Group']['valueObject'] ?? [];

                if (isset($groupFields['Number'])) {
                    $data['group']['number'] = $this->getFieldContent($groupFields['Number']);
                }

                if (isset($groupFields['Name'])) {
                    $data['group']['name'] = $this->getFieldContent($groupFields['Name']);
                }
            }

            // Extract prescription information
            if (isset($fields['Prescription'])) {
                $rxFields = $fields['Prescription']['valueObject'] ?? [];

                if (isset($rxFields['BIN'])) {
                    $data['prescription']['bin'] = $this->getFieldContent($rxFields['BIN']);
                }

                if (isset($rxFields['PCN'])) {
                    $data['prescription']['pcn'] = $this->getFieldContent($rxFields['PCN']);
                }

                if (isset($rxFields['GRP'])) {
                    $data['prescription']['grp'] = $this->getFieldContent($rxFields['GRP']);
                }
            }

            // Extract plan information
            if (isset($fields['Plan'])) {
                $planFields = $fields['Plan']['valueObject'] ?? [];

                if (isset($planFields['Number'])) {
                    $data['plan']['number'] = $this->getFieldContent($planFields['Number']);
                }

                if (isset($planFields['Name'])) {
                    $data['plan']['name'] = $this->getFieldContent($planFields['Name']);
                }

                if (isset($planFields['Type'])) {
                    $data['plan']['type'] = $this->getFieldContent($planFields['Type']);
                }
            }

            // Extract copays
            if (isset($fields['Copays']) && $fields['Copays']['valueArray']) {
                foreach ($fields['Copays']['valueArray'] as $copay) {
                    if (isset($copay['valueObject'])) {
                        $copayData = [
                            'type' => $this->getFieldContent($copay['valueObject']['Type'] ?? null),
                            'amount' => $this->getFieldContent($copay['valueObject']['Amount'] ?? null),
                        ];

                        if ($copayData['type'] || $copayData['amount']) {
                            $data['copays'][] = $copayData;
                        }
                    }
                }
            }

            // Extract payer ID
            if (isset($fields['PayerId'])) {
                $data['payer_id'] = $this->getFieldContent($fields['PayerId']);
            }
        }

        // Process back results if available
        if ($backResults && !empty($backResults['documents'][0]['fields'])) {
            $backFields = $backResults['documents'][0]['fields'];

            // Extract claims address
            if (isset($backFields['ClaimsAddress'])) {
                $addressFields = $backFields['ClaimsAddress']['valueObject'] ?? [];
                $addressParts = [];

                if (isset($addressFields['StreetAddress'])) {
                    $addressParts[] = $this->getFieldContent($addressFields['StreetAddress']);
                }

                if (isset($addressFields['City'])) {
                    $addressParts[] = $this->getFieldContent($addressFields['City']);
                }

                if (isset($addressFields['State'])) {
                    $addressParts[] = $this->getFieldContent($addressFields['State']);
                }

                if (isset($addressFields['ZipCode'])) {
                    $addressParts[] = $this->getFieldContent($addressFields['ZipCode']);
                }

                $data['claims_address'] = implode(', ', array_filter($addressParts));
            }

            // Extract service numbers
            if (isset($backFields['ServiceNumbers']) && $backFields['ServiceNumbers']['valueArray']) {
                foreach ($backFields['ServiceNumbers']['valueArray'] as $serviceNumber) {
                    if (isset($serviceNumber['valueObject'])) {
                        $numberData = [
                            'type' => $this->getFieldContent($serviceNumber['valueObject']['Type'] ?? null),
                            'number' => $this->getFieldContent($serviceNumber['valueObject']['Number'] ?? null),
                        ];

                        if ($numberData['type'] || $numberData['number']) {
                            $data['service_numbers'][] = $numberData;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get field content from Azure response
     *
     * @param mixed $field
     * @return string|null
     */
    private function getFieldContent($field): ?string
    {
        if (!$field) {
            return null;
        }

        if (isset($field['content'])) {
            return $field['content'];
        }

        if (isset($field['valueString'])) {
            return $field['valueString'];
        }

        if (isset($field['valueDate'])) {
            return $field['valueDate'];
        }

        if (isset($field['valueNumber'])) {
            return (string) $field['valueNumber'];
        }

        return null;
    }

    /**
     * Map extracted data to patient form fields
     *
     * @param array $extractedData
     * @return array
     */
    public function mapToPatientForm(array $extractedData): array
    {
        $formData = [];

        // Map member name to first and last name
        if (!empty($extractedData['member']['name'])) {
            $nameParts = explode(' ', $extractedData['member']['name'], 2);
            $formData['patient_first_name'] = $nameParts[0] ?? '';
            $formData['patient_last_name'] = $nameParts[1] ?? '';
        }

        // Map member ID
        if (!empty($extractedData['member']['id'])) {
            $formData['patient_member_id'] = $extractedData['member']['id'];
        }

        // Map date of birth
        if (!empty($extractedData['member']['date_of_birth'])) {
            $formData['patient_dob'] = $extractedData['member']['date_of_birth'];
        }

        // Map insurer
        if (!empty($extractedData['insurer'])) {
            $formData['payer_name'] = $extractedData['insurer'];
        }

        // Map payer ID
        if (!empty($extractedData['payer_id'])) {
            $formData['payer_id'] = $extractedData['payer_id'];
        }

        // Map insurance type based on plan type
        if (!empty($extractedData['plan']['type'])) {
            $planType = strtolower($extractedData['plan']['type']);

            if (strpos($planType, 'medicare') !== false) {
                if (strpos($planType, 'advantage') !== false) {
                    $formData['insurance_type'] = 'medicare_advantage';
                } else {
                    $formData['insurance_type'] = 'medicare';
                }
            } elseif (strpos($planType, 'medicaid') !== false) {
                $formData['insurance_type'] = 'medicaid';
            } else {
                $formData['insurance_type'] = 'commercial';
            }
        }

        // Store additional extracted data for reference
        $formData['insurance_extracted_data'] = $extractedData;

        // Log the final mapped data
        Log::info('Final mapped form data', [
            'member_id' => $formData['patient_member_id'] ?? 'NOT FOUND',
            'payer_name' => $formData['payer_name'] ?? 'NOT FOUND',
            'payer_id' => $formData['payer_id'] ?? 'NOT FOUND',
            'all_data' => $formData
        ]);

        return $formData;
    }
}
