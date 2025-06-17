<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Docuseal\DocusealSubmission;
use App\Models\Docuseal\DocusealFolder;
use Docuseal\Api as DocusealApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client as HttpClient;
use Exception;

class DocusealService
{
    private DocusealApi $docusealApi;

    public function __construct()
    {
        // Configuration validation guard
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

        if (empty($apiKey)) {
            throw new Exception('DocuSeal API key is not configured. Please set DOCUSEAL_API_KEY in your environment.');
        }

        $this->docusealApi = new DocusealApi($apiKey, $apiUrl);
    }

    /**
     * Generate documents for an approved order
     */
    public function generateDocumentsForOrder(Order $order): array
    {
        try {
            $submissions = [];

            // Get PHI data from FHIR service
            $phiData = $this->getOrderPHIData($order);

            // Generate Insurance Verification Form
            $insuranceSubmission = $this->generateInsuranceVerificationForm($order, $phiData);
            if ($insuranceSubmission) {
                $submissions[] = $insuranceSubmission;
            }

            // Generate Order Form
            $orderSubmission = $this->generateOrderForm($order, $phiData);
            if ($orderSubmission) {
                $submissions[] = $orderSubmission;
            }

            // Generate Onboarding Form (if needed)
            $onboardingSubmission = $this->generateOnboardingForm($order, $phiData);
            if ($onboardingSubmission) {
                $submissions[] = $onboardingSubmission;
            }

            // Update order status
            $order->update([
                'docuseal_generation_status' => 'completed',
                'documents_generated_at' => now(),
            ]);

            Log::info('DocuSeal documents generated successfully', [
                'order_id' => $order->id,
                'submissions_count' => count($submissions)
            ]);

            return $submissions;

        } catch (Exception $e) {
            Log::error('Failed to generate DocuSeal documents', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            $order->update([
                'docuseal_generation_status' => 'failed',
            ]);

            throw $e;
        }
    }

    /**
     * Generate Insurance Verification Form
     */
    public function generateInsuranceVerificationForm(Order $order, array $phiData): ?DocusealSubmission
    {
        $template = DocusealTemplate::getDefaultTemplate('InsuranceVerification');

        if (!$template) {
            Log::warning('No default InsuranceVerification template found');
            return null;
        }

        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false, // We'll manage this manually
            'submitters' => [
                [
                    'role' => 'Provider',
                    'email' => 'noreply@mscwound.com', // Placeholder - we'll update with actual provider email
                    'name' => $phiData['provider_name'] ?? 'Provider',
                ]
            ],
            'fields' => $this->mapInsuranceVerificationFields($order, $phiData),
        ];

        return $this->createSubmission($order, $template, 'InsuranceVerification', $submissionData);
    }

    /**
     * Generate Order Form
     */
    public function generateOrderForm(Order $order, array $phiData): ?DocusealSubmission
    {
        $template = DocusealTemplate::getDefaultTemplate('OrderForm');

        if (!$template) {
            Log::warning('No default OrderForm template found');
            return null;
        }

        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false,
            'submitters' => [
                [
                    'role' => 'Provider',
                    'email' => 'noreply@mscwound.com',
                    'name' => $phiData['provider_name'] ?? 'Provider',
                ]
            ],
            'fields' => $this->mapOrderFormFields($order, $phiData),
        ];

        return $this->createSubmission($order, $template, 'OrderForm', $submissionData);
    }

    /**
     * Generate Onboarding Form
     */
    public function generateOnboardingForm(Order $order, array $phiData): ?DocusealSubmission
    {
        // Only generate onboarding form if it's a new provider
        if (!$this->isNewProvider($order)) {
            return null;
        }

        $template = DocusealTemplate::getDefaultTemplate('OnboardingForm');

        if (!$template) {
            Log::warning('No default OnboardingForm template found');
            return null;
        }

        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false,
            'submitters' => [
                [
                    'role' => 'Provider',
                    'email' => 'noreply@mscwound.com',
                    'name' => $phiData['provider_name'] ?? 'Provider',
                ]
            ],
            'fields' => $this->mapOnboardingFormFields($order, $phiData),
        ];

        return $this->createSubmission($order, $template, 'OnboardingForm', $submissionData);
    }

    /**
     * Get submission status from DocuSeal
     */
    public function getSubmissionStatus(string $docusealSubmissionId): array
    {
        try {
            $response = $this->docusealApi->getSubmission($docusealSubmissionId);
            return $response;
        } catch (Exception $e) {
            Log::error('Failed to get DocuSeal submission status', [
                'submission_id' => $docusealSubmissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Download completed document
     */
    public function downloadDocument(string $docusealSubmissionId): string
    {
        try {
            $documents = $this->docusealApi->getSubmissionDocuments($docusealSubmissionId);

            if (empty($documents)) {
                throw new Exception('No documents found for submission');
            }

            // Return the first document URL
            return $documents[0]['url'] ?? '';
        } catch (Exception $e) {
            Log::error('Failed to download DocuSeal document', [
                'submission_id' => $docusealSubmissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a DocuSeal submission
     */
    private function createSubmission(Order $order, DocusealTemplate $template, string $documentType, array $submissionData): DocusealSubmission
    {
        try {
            // Create submission in DocuSeal
            $response = $this->docusealApi->createSubmission($submissionData);

            // Get folder ID for manufacturer
            $folderId = $this->getManufacturerFolderId($order);

            // Create local submission record
            $submission = DocusealSubmission::create([
                'order_id' => $order->id,
                'docuseal_submission_id' => $response['id'],
                'docuseal_template_id' => $template->docuseal_template_id,
                'document_type' => $documentType,
                'status' => 'pending',
                'folder_id' => $folderId,
                'signing_url' => $response['submitters'][0]['slug'] ?? null,
                'metadata' => [
                    'template_name' => $template->template_name,
                    'created_at' => now()->toISOString(),
                    'submission_id' => $response['id'], // Only store essential non-PHI data
                ],
            ]);

            Log::info('DocuSeal submission created', [
                'order_id' => $order->id,
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $response['id'],
                'document_type' => $documentType,
            ]);

            return $submission;

        } catch (Exception $e) {
            Log::error('Failed to create DocuSeal submission', [
                'order_id' => $order->id,
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get PHI data from FHIR service (placeholder implementation)
     */
    private function getOrderPHIData(Order $order): array
    {
        // TODO: Implement FHIR integration to get actual PHI data
        // This is a placeholder implementation
        return [
            'patient_name' => 'Patient Name', // From FHIR Patient resource
            'patient_dob' => '1980-01-01',
            'patient_address' => '123 Main St, City, State 12345',
            'patient_phone' => '(555) 123-4567',
            'patient_email' => 'patient@example.com',
            'provider_name' => 'Dr. Provider Name',
            'provider_npi' => '1234567890',
            'facility_name' => $order->facility->name ?? 'Unknown Facility',
            'insurance_plan' => 'Insurance Plan Name',
            'member_id' => 'MEMBER123',
        ];
    }

    /**
     * Map fields for Insurance Verification form
     */
    private function mapInsuranceVerificationFields(Order $order, array $phiData): array
    {
        return [
            [
                'name' => 'patient_name',
                'default_value' => $phiData['patient_name'] ?? ''
            ],
            [
                'name' => 'patient_dob',
                'default_value' => $phiData['patient_dob'] ?? ''
            ],
            [
                'name' => 'member_id',
                'default_value' => $phiData['member_id'] ?? ''
            ],
            [
                'name' => 'insurance_plan',
                'default_value' => $phiData['insurance_plan'] ?? ''
            ],
            [
                'name' => 'provider_name',
                'default_value' => $phiData['provider_name'] ?? ''
            ],
            [
                'name' => 'provider_npi',
                'default_value' => $phiData['provider_npi'] ?? ''
            ],
            [
                'name' => 'order_date',
                'default_value' => $order->date_of_service?->format('Y-m-d') ?? ''
            ],
        ];
    }

    /**
     * Map fields for Order form
     */
    private function mapOrderFormFields(Order $order, array $phiData): array
    {
        return [
            [
                'name' => 'order_number',
                'default_value' => $order->order_number ?? ''
            ],
            [
                'name' => 'patient_name',
                'default_value' => $phiData['patient_name'] ?? ''
            ],
            [
                'name' => 'provider_name',
                'default_value' => $phiData['provider_name'] ?? ''
            ],
            [
                'name' => 'facility_name',
                'default_value' => $phiData['facility_name'] ?? ''
            ],
            [
                'name' => 'total_amount',
                'default_value' => '$' . number_format($order->total_amount ?? 0, 2)
            ],
            [
                'name' => 'date_of_service',
                'default_value' => $order->date_of_service?->format('Y-m-d') ?? ''
            ],
        ];
    }

    /**
     * Map fields for Onboarding form
     */
    private function mapOnboardingFormFields(Order $order, array $phiData): array
    {
        return [
            [
                'name' => 'provider_name',
                'default_value' => $phiData['provider_name'] ?? ''
            ],
            [
                'name' => 'provider_npi',
                'default_value' => $phiData['provider_npi'] ?? ''
            ],
            [
                'name' => 'facility_name',
                'default_value' => $phiData['facility_name'] ?? ''
            ],
            [
                'name' => 'facility_address',
                'default_value' => '' // Remove PHI leakage - get from facility data instead
            ],
        ];
    }

    /**
     * Check if this is a new provider
     */
    private function isNewProvider(Order $order): bool
    {
        // TODO: Implement logic to check if provider is new
        // For now, assume all providers need onboarding
        return true;
    }

    /**
     * Get manufacturer folder ID
     */
    private function getManufacturerFolderId(Order $order): string
    {
        // TODO: Determine manufacturer from order items
        // For now, return a default folder ID
        return 'default-folder';
    }

    /**
     * Create a QuickRequest IVR submission using embedded text field tags
     */
    public function createQuickRequestSubmission(string $templateId, array $submissionData): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            throw new Exception('DocuSeal API key not configured. Please set DOCUSEAL_API_KEY in your environment.');
        }
        
        try {
            // Prepare fields for embedded text field tags
            $fields = $this->prepareEmbeddedFields($submissionData['fields'] ?? []);
            
            // Create submission payload for DocuSeal API
            $payload = [
                'template_id' => $templateId,
                'send_email' => $submissionData['send_email'] ?? false,
                'submitters' => [
                    [
                        'role' => 'Provider',
                        'email' => $submissionData['email'],
                        'name' => $submissionData['name'],
                        'send_email' => $submissionData['send_email'] ?? false,
                        'send_sms' => false,
                        // Pre-fill embedded field values
                        'values' => $fields,
                    ]
                ],
                'expire_after' => 7, // Days until expiration
            ];
            
            // Make direct HTTP request to DocuSeal API
            $client = new HttpClient();
            $response = $client->post($apiUrl . '/submissions', [
                'headers' => [
                    'X-Auth-Token' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 30
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!$responseData || !isset($responseData['id'])) {
                throw new Exception('Invalid response from DocuSeal API: ' . $response->getBody());
            }

            // Extract submission ID and signing URL
            $submissionId = $responseData['id'];
            $signingSlug = $responseData['submitters'][0]['slug'] ?? null;

            if (!$submissionId || !$signingSlug) {
                throw new Exception('Missing submission ID or signing slug in DocuSeal response');
            }

            // Convert slug to full URL
            $signingUrl = "https://docuseal.com/s/{$signingSlug}";

            Log::info('QuickRequest DocuSeal submission created with embedded fields', [
                'submission_id' => $submissionId,
                'template_id' => $templateId,
                'fields_count' => count($fields),
                'field_names' => array_keys($fields),
                'signing_url' => $signingUrl,
            ]);

            return [
                'submission_id' => $submissionId,
                'signing_url' => $signingUrl,
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            Log::error('DocuSeal API submission creation failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $errorBody,
                'fields_provided' => array_keys($submissionData['fields'] ?? [])
            ]);
            
            throw new Exception('Failed to create DocuSeal submission: ' . $e->getMessage() . '. Response: ' . $errorBody);
        } catch (Exception $e) {
            Log::error('Failed to create QuickRequest DocuSeal submission', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'fields_provided' => array_keys($submissionData['fields'] ?? [])
            ]);
            throw $e;
        }
    }

    /**
     * Prepare fields for embedded text field tags
     */
    private function prepareEmbeddedFields(array $fields): array
    {
        $prepared = [];
        
        foreach ($fields as $fieldName => $fieldValue) {
            // Clean field name (remove any prefixes if needed)
            $cleanFieldName = $fieldName;
            
            // Convert value to string and handle special cases
            if (is_bool($fieldValue)) {
                $prepared[$cleanFieldName] = $fieldValue ? 'Yes' : 'No';
            } elseif ($fieldValue === 'true' || $fieldValue === true) {
                $prepared[$cleanFieldName] = 'Yes';
            } elseif ($fieldValue === 'false' || $fieldValue === false) {
                $prepared[$cleanFieldName] = 'No';
            } elseif (is_null($fieldValue)) {
                $prepared[$cleanFieldName] = '';
            } else {
                $prepared[$cleanFieldName] = (string) $fieldValue;
            }
        }
        
        Log::debug('Prepared embedded fields for DocuSeal', [
            'field_count' => count($prepared),
            'field_sample' => array_slice($prepared, 0, 5, true) // Log first 5 fields for debugging
        ]);
        
        return $prepared;
    }

    /**
     * Get submission status
     */
    public function getSubmission(string $submissionId): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            throw new Exception('DocuSeal API key not configured. Please set DOCUSEAL_API_KEY in your environment.');
        }
        
        try {
            $client = new HttpClient();
            $response = $client->get($apiUrl . '/submissions/' . $submissionId, [
                'headers' => [
                    'X-Auth-Token' => $apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!$responseData) {
                throw new Exception('Invalid response from DocuSeal API: ' . $response->getBody());
            }
            
            return $responseData;
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            Log::error('DocuSeal API get submission failed', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $errorBody,
            ]);
            
            throw new Exception('Failed to get DocuSeal submission: ' . $e->getMessage() . '. Response: ' . $errorBody);
        } catch (Exception $e) {
            Log::error('Failed to get DocuSeal submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get template information including embedded field tags
     */
    public function getTemplateInfo(string $templateId): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$apiKey) {
            throw new Exception('DocuSeal API key not configured. Please set DOCUSEAL_API_KEY in your environment.');
        }
        
        try {
            $client = new HttpClient();
            $response = $client->get($apiUrl . '/templates/' . $templateId, [
                'headers' => [
                    'X-Auth-Token' => $apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30
            ]);
            
            $template = json_decode($response->getBody()->getContents(), true);
            
            if (!$template) {
                throw new Exception('Invalid response from DocuSeal API: ' . $response->getBody());
            }
            
            Log::info('Retrieved DocuSeal template info', [
                'template_id' => $templateId,
                'template_name' => $template['name'] ?? 'Unknown',
                'fields_count' => count($template['fields'] ?? []),
            ]);
            
            return $template;
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            Log::error('DocuSeal API get template failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $errorBody,
            ]);
            
            throw new Exception('Failed to get DocuSeal template: ' . $e->getMessage() . '. Response: ' . $errorBody);
        } catch (Exception $e) {
            Log::error('Failed to get DocuSeal template info', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate field mapping against template
     */
    public function validateFieldMapping(string $templateId, array $fields): array
    {
        try {
            $template = $this->getTemplateInfo($templateId);
            $templateFields = collect($template['fields'] ?? [])->pluck('name')->toArray();
            
            $providedFields = array_keys($fields);
            $matchedFields = array_intersect($providedFields, $templateFields);
            $unmatchedProvided = array_diff($providedFields, $templateFields);
            $unmatchedTemplate = array_diff($templateFields, $providedFields);
            
            $validation = [
                'template_id' => $templateId,
                'template_fields_count' => count($templateFields),
                'provided_fields_count' => count($providedFields),
                'matched_fields_count' => count($matchedFields),
                'matched_fields' => $matchedFields,
                'unmatched_provided_fields' => $unmatchedProvided,
                'unmatched_template_fields' => $unmatchedTemplate,
                'match_percentage' => count($templateFields) > 0 ? round((count($matchedFields) / count($templateFields)) * 100, 2) : 0,
            ];
            
            Log::info('DocuSeal field mapping validation', $validation);
            
            return $validation;
        } catch (Exception $e) {
            Log::error('Failed to validate DocuSeal field mapping', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Debug submission creation with detailed logging
     */
    public function debugSubmissionCreation(string $templateId, array $submissionData): array
    {
        try {
            Log::info('Starting DocuSeal submission debug', [
                'template_id' => $templateId,
                'submission_data_keys' => array_keys($submissionData),
                'fields_count' => count($submissionData['fields'] ?? []),
            ]);

            // Validate field mapping first
            $validation = $this->validateFieldMapping($templateId, $submissionData['fields'] ?? []);
            
            if ($validation['match_percentage'] < 50) {
                Log::warning('Low field matching percentage detected', [
                    'match_percentage' => $validation['match_percentage'],
                    'unmatched_fields' => $validation['unmatched_provided_fields'],
                ]);
            }

            // Create submission with debugging
            $result = $this->createQuickRequestSubmission($templateId, $submissionData);
            
            Log::info('DocuSeal submission debug completed successfully', [
                'submission_id' => $result['submission_id'],
                'field_validation' => $validation,
            ]);
            
            return array_merge($result, ['field_validation' => $validation]);
            
        } catch (Exception $e) {
            Log::error('DocuSeal submission debug failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
