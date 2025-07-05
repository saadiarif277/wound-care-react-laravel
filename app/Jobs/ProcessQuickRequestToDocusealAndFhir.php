<?php

namespace App\Jobs;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use App\Services\FieldMapping\DataExtractor;
use App\Services\FhirService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessQuickRequestToDocusealAndFhir implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $episodeId;
    protected $quickRequestData;

    /**
     * Create a new job instance.
     *
     * @param int|null $episodeId
     * @param array|null $quickRequestData
     */
    public function __construct(?int $episodeId = null, ?array $quickRequestData = null)
    {
        $this->episodeId = $episodeId;
        $this->quickRequestData = $quickRequestData;
    }

    /**
     * Execute the job.
     */
    public function handle(
        DocusealService $docusealService,
        UnifiedFieldMappingService $fieldMappingService,
        DataExtractor $dataExtractor,
        FhirService $fhirService
    ) {
        try {
            // 1. Retrieve or create the episode
            $episode = null;
            if ($this->episodeId) {
                $episode = PatientManufacturerIVREpisode::find($this->episodeId);
            }
            if (!$episode && $this->quickRequestData) {
                // Optionally, create the episode if not found
                $episode = PatientManufacturerIVREpisode::create($this->quickRequestData);
            }
            if (!$episode) {
                Log::error('ProcessQuickRequestToDocusealAndFhir: No episode found or created.', [
                    'episode_id' => $this->episodeId,
                    'quickRequestData' => $this->quickRequestData,
                ]);
                return;
            }

            // 2. Map all fields using robust mapping logic
            $manufacturerName = $episode->manufacturer->name ?? null;
            $episodeId = $episode->id;
            $mappingResult = null;
            if ($manufacturerName) {
                $mappingResult = $fieldMappingService->mapEpisodeToTemplate(
                    $episodeId,
                    $manufacturerName
                );
                $mappedData = $mappingResult['data'] ?? [];

                // --- Enhanced Logging for Mapping Completeness ---
                $expectedFields = array_keys($mappingResult['manufacturer']['fields'] ?? []);
                $missingFields = array_diff($expectedFields, array_keys($mappedData));
                if (!empty($missingFields)) {
                    Log::warning('DocuSeal mapping: missing fields', [
                        'episode_id' => $episodeId,
                        'manufacturer' => $manufacturerName,
                        'missing_fields' => $missingFields,
                        'all_mapped_fields' => array_keys($mappedData),
                    ]);
                    // AI Hook: Here you could call an AI service to suggest mappings for missing fields
                    // Example: dispatch(new SuggestFieldMappingsJob($manufacturerName, $missingFields, $mappedData));
                }

                // --- End Enhanced Logging ---

                // 3. Submit to Docuseal using robust service logic
                $docusealService->createOrUpdateSubmission(
                    $episodeId,
                    $manufacturerName,
                    $mappedData
                );
            } else {
                Log::warning('ProcessQuickRequestToDocusealAndFhir: Manufacturer missing for episode.', [
                    'episode_id' => $episodeId
                ]);
                $mappedData = [];
            }

            // 4. Push to FHIR server (customize as needed)
            // Example: If you want to create/update a FHIR Patient resource
            if (!empty($mappingResult['data']['fhir_patient_id']) && !empty($mappingResult['data']['patient'])) {
                // $fhirService->updatePatient($mappingResult['data']['fhir_patient_id'], $mappingResult['data']['patient']);
                // Add more FHIR resource pushes as needed
            }

            Log::info('ProcessQuickRequestToDocusealAndFhir: Completed successfully.', [
                'episode_id' => $episodeId
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessQuickRequestToDocusealAndFhir: Exception occurred.', [
                'error' => $e->getMessage(),
                'episode_id' => $this->episodeId,
            ]);
        }
    }
}
