<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DocusealTemplate;
use App\Models\DocusealSubmission;
use App\Models\DocusealFolder;
use Docuseal\Api as DocusealApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class DocusealService
{
    private DocusealApi $docusealApi;

    public function __construct()
    {
        $this->docusealApi = new DocusealApi(
            config('services.docuseal.api_key'),
            config('services.docuseal.api_url', 'https://api.docuseal.com')
        );
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
                    'response' => $response,
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
                'default_value' => $order->date_of_service ? $order->date_of_service->format('Y-m-d') : ''
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
                'default_value' => $order->date_of_service ? $order->date_of_service->format('Y-m-d') : ''
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
                'default_value' => $phiData['provider_name']
            ],
            [
                'name' => 'provider_npi',
                'default_value' => $phiData['provider_npi']
            ],
            [
                'name' => 'facility_name',
                'default_value' => $phiData['facility_name']
            ],
            [
                'name' => 'facility_address',
                'default_value' => $phiData['patient_address'] // Placeholder
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
} 