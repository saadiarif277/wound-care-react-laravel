<?php

namespace App\Jobs\QuickRequest;

use App\Models\Episode;
use App\Models\Document;
use App\Services\DocusealService;
use App\Services\Fhir\FhirService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDocuSealPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Episode $episode,
        private string $templateId,
        private array $formData,
        private array $signatures,
        private string $documentType = 'insurance_verification'
    ) {
        $this->onQueue('document-generation');
    }

    /**
     * Execute the job.
     */
    public function handle(
        DocusealService $docusealService,
        FhirService $fhirService
    ): void {
        Log::info('Generating DocuSeal PDF', [
            'episode_id' => $this->episode->id,
            'template_id' => $this->templateId,
            'document_type' => $this->documentType,
        ]);

        try {
            // Enrich form data with FHIR data if needed
            $enrichedData = $this->enrichFormData($fhirService);

            // Generate PDF
            $result = $docusealService->generatePdf(
                $this->templateId,
                $enrichedData,
                $this->signatures
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to generate PDF');
            }

            // Download and store PDF
            $pdfContent = $result['pdf_content'];
            $fileName = $this->generateFileName();
            $path = $this->storePdf($pdfContent, $fileName);

            // Create document record
            $document = Document::create([
                'documentable_type' => Episode::class,
                'documentable_id' => $this->episode->id,
                'type' => $this->documentType,
                'name' => $fileName,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen($pdfContent),
                'metadata' => [
                    'template_id' => $this->templateId,
                    'docuseal_document_id' => $result['document_id'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                    'signatures_included' => !empty($this->signatures),
                ],
            ]);

            // Create FHIR DocumentReference
            $this->createFhirDocumentReference($fhirService, $document);

            // Update episode metadata
            $this->episode->update([
                'metadata' => array_merge($this->episode->metadata ?? [], [
                    'documents' => array_merge($this->episode->metadata['documents'] ?? [], [
                        $this->documentType => [
                            'document_id' => $document->id,
                            'generated_at' => now()->toIso8601String(),
                        ],
                    ]),
                ]),
            ]);

            Log::info('DocuSeal PDF generated successfully', [
                'episode_id' => $this->episode->id,
                'document_id' => $document->id,
                'path' => $path,
            ]);

            // Trigger next steps if insurance verification
            if ($this->documentType === 'insurance_verification') {
                $this->dispatch(new VerifyInsuranceEligibility(
                    $this->episode,
                    $this->episode->insurance_data['primary'],
                    'primary'
                ));
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate DocuSeal PDF', [
                'episode_id' => $this->episode->id,
                'template_id' => $this->templateId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Enrich form data with additional FHIR data
     */
    private function enrichFormData(FhirService $fhirService): array
    {
        $data = $this->formData;

        try {
            // Add patient demographics if not present
            if (!isset($data['patientAge']) && $this->episode->patient_fhir_id) {
                $patient = $fhirService->read('Patient', $this->episode->patient_fhir_id);
                $birthDate = $patient['birthDate'] ?? null;
                if ($birthDate) {
                    $data['patientAge'] = \Carbon\Carbon::parse($birthDate)->age;
                }
            }

            // Add provider details if not present
            if (!isset($data['providerPhone']) && $this->episode->practitioner_fhir_id) {
                $practitioner = $fhirService->read('Practitioner', $this->episode->practitioner_fhir_id);
                $telecom = $practitioner['telecom'] ?? [];
                foreach ($telecom as $contact) {
                    if ($contact['system'] === 'phone' && !isset($data['providerPhone'])) {
                        $data['providerPhone'] = $contact['value'];
                    }
                    if ($contact['system'] === 'fax' && !isset($data['providerFax'])) {
                        $data['providerFax'] = $contact['value'];
                    }
                }
            }

            // Add current date/time fields
            $data['currentDate'] = now()->format('m/d/Y');
            $data['currentTime'] = now()->format('h:i A');

        } catch (\Exception $e) {
            Log::warning('Failed to enrich form data from FHIR', [
                'error' => $e->getMessage(),
            ]);
        }

        return $data;
    }

    /**
     * Generate file name for the PDF
     */
    private function generateFileName(): string
    {
        $timestamp = now()->format('Ymd_His');
        $patientDisplay = Str::slug($this->episode->patient_display);
        $type = Str::slug($this->documentType);
        
        return "{$patientDisplay}_{$type}_{$timestamp}.pdf";
    }

    /**
     * Store PDF file
     */
    private function storePdf(string $content, string $fileName): string
    {
        $directory = "insurance-verifications/{$this->episode->id}";
        $path = "{$directory}/{$fileName}";
        
        // Store in S3 or local storage based on config
        Storage::disk('documents')->put($path, $content);
        
        return $path;
    }

    /**
     * Create FHIR DocumentReference
     */
    private function createFhirDocumentReference(FhirService $fhirService, Document $document): void
    {
        try {
            $documentReference = [
                'resourceType' => 'DocumentReference',
                'status' => 'current',
                'docStatus' => 'final',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => $this->getLoincCode(),
                            'display' => $this->getDocumentTypeDisplay(),
                        ],
                    ],
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/us/core/CodeSystem/us-core-documentreference-category',
                                'code' => 'clinical-note',
                                'display' => 'Clinical Note',
                            ],
                        ],
                    ],
                ],
                'subject' => [
                    'reference' => "Patient/{$this->episode->patient_fhir_id}",
                ],
                'date' => now()->toIso8601String(),
                'author' => [
                    [
                        'reference' => "Practitioner/{$this->episode->practitioner_fhir_id}",
                    ],
                ],
                'custodian' => [
                    'reference' => "Organization/{$this->episode->organization_fhir_id}",
                ],
                'content' => [
                    [
                        'attachment' => [
                            'contentType' => 'application/pdf',
                            'title' => $document->name,
                            'creation' => now()->toIso8601String(),
                            'size' => $document->size,
                        ],
                    ],
                ],
                'context' => [
                    'encounter' => [
                        [
                            'reference' => "EpisodeOfCare/{$this->episode->episode_of_care_fhir_id}",
                        ],
                    ],
                    'related' => [
                        [
                            'reference' => "EpisodeOfCare/{$this->episode->episode_of_care_fhir_id}",
                        ],
                    ],
                ],
            ];

            $fhirResponse = $fhirService->create('DocumentReference', $documentReference);
            
            // Update document with FHIR ID
            $document->update([
                'fhir_id' => $fhirResponse['id'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to create FHIR DocumentReference', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get LOINC code for document type
     */
    private function getLoincCode(): string
    {
        return match ($this->documentType) {
            'insurance_verification' => '64290-0', // Health insurance card
            'prescription' => '64288-4', // Prescription for medical equipment or product
            'order_form' => '64289-2', // Medical equipment or product order
            'consent' => '64293-4', // Consent form
            default => '64298-3', // Unspecified medical document
        };
    }

    /**
     * Get display name for document type
     */
    private function getDocumentTypeDisplay(): string
    {
        return match ($this->documentType) {
            'insurance_verification' => 'Insurance Verification Form',
            'prescription' => 'Medical Equipment Prescription',
            'order_form' => 'Product Order Form',
            'consent' => 'Patient Consent Form',
            default => 'Medical Document',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('DocuSeal PDF generation job failed', [
            'episode_id' => $this->episode->id,
            'template_id' => $this->templateId,
            'document_type' => $this->documentType,
            'error' => $exception->getMessage(),
        ]);

        // Update episode metadata
        $this->episode->update([
            'metadata' => array_merge($this->episode->metadata ?? [], [
                'document_generation_failed' => [
                    $this->documentType => [
                        'error' => $exception->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                        'attempts' => $this->attempts(),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'docuseal',
            'pdf-generation',
            'episode:' . $this->episode->id,
            'type:' . $this->documentType,
        ];
    }
}