<?php

namespace App\Services\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class QuickRequestOrchestrator
{
    public function __construct(
        protected PatientHandler $patientHandler,
        protected ProviderHandler $providerHandler,
        protected ClinicalHandler $clinicalHandler,
        protected InsuranceHandler $insuranceHandler,
        protected OrderHandler $orderHandler,
        protected NotificationHandler $notificationHandler,
        protected PhiSafeLogger $logger
    ) {}

        /**
     * Start a new episode with initial order
     */
    public function startEpisode(array $data): PatientManufacturerIVREpisode
    {
        try {
            $this->logger->info('Starting new Quick Request episode');

            DB::beginTransaction();
            // Step 1: Create or find patient in FHIR
            $patientFhirId = $this->patientHandler->createOrUpdatePatient($data['patient']);

            // Step 2: Create or find provider in FHIR
            $providerFhirId = $this->providerHandler->createOrUpdateProvider($data['provider']);

            // Step 3: Create or find organization/facility in FHIR
            $organizationFhirId = $this->providerHandler->createOrUpdateOrganization($data['facility']);

            // Step 4: Create clinical resources (Condition, EpisodeOfCare)
            $clinicalResources = $this->clinicalHandler->createClinicalResources([
                'patient_id' => $patientFhirId,
                'provider_id' => $providerFhirId,
                'organization_id' => $organizationFhirId,
                'clinical' => $data['clinical']
            ]);

            // Step 5: Create insurance coverage(s)
            $coverageIds = $this->insuranceHandler->createMultipleCoverages($data['insurance'], $patientFhirId);

            // Get primary coverage ID for episode metadata
            $primaryCoverageId = $coverageIds['primary'] ?? array_values($coverageIds)[0] ?? null;

            // Step 6: Create local episode record
            $episode = PatientManufacturerIVREpisode::create([
                'patient_id' => $data['patient']['id'] ?? uniqid('patient-'),
                'patient_fhir_id' => $patientFhirId,
                'patient_display_id' => $data['patient']['display_id'] ?? null,
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
                'created_by' => Auth::id(),
                'metadata' => [
                    'practitioner_fhir_id' => $providerFhirId,
                    'organization_fhir_id' => $organizationFhirId,
                    'episode_of_care_fhir_id' => $clinicalResources['episode_of_care_id'],
                    'condition_id' => $clinicalResources['condition_id'],
                    'coverage_ids' => $coverageIds,
                    'primary_coverage_id' => $primaryCoverageId,
                    'clinical_data' => $data['clinical'],
                    'provider_data' => $data['provider'],
                    'facility_data' => $data['facility'],
                    'insurance_data' => $data['insurance'],
                    'order_details' => $data['order_details'],
                    'created_by' => Auth::id()
                ]
            ]);

            // Step 7: Create initial order
            $this->logger->info('About to create initial order', [
                'episode_id' => $episode->id,
                'order_details_count' => count($data['order_details'] ?? []),
                'has_products' => isset($data['order_details']['products'])
            ]);
            
            $order = $this->orderHandler->createInitialOrder($episode, $data['order_details']);
            
            $this->logger->info('Order created, checking if saved', [
                'order_id' => $order->id ?? 'no_id',
                'order_exists' => $order->exists,
                'episode_id' => $episode->id
            ]);

            DB::commit();
            
            // Verify order was saved after commit
            $orderCount = Order::where('episode_id', $episode->id)->count();
            $this->logger->info('After commit - order count check', [
                'episode_id' => $episode->id,
                'order_count' => $orderCount,
                'order_id' => $order->id ?? 'no_id'
            ]);

            $this->logger->info('Quick Request episode created successfully', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);

            return $episode->load('orders');

        } catch (Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to create Quick Request episode', [
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'manufacturer_id' => $data['manufacturer_id'] ?? 'unknown',
                'step' => 'Unknown - check trace'
            ]);

            throw new \Exception('Failed to create Quick Request episode: ' . $e->getMessage());
        }
    }

    /**
     * Add follow-up order to existing episode
     */
    public function addFollowUpOrder(PatientManufacturerIVREpisode $episode, array $orderData): Order
    {
        $this->logger->info('Adding follow-up order to episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Validate episode can accept new orders
            if (!in_array($episode->status, [PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW, PatientManufacturerIVREpisode::STATUS_IVR_SENT])) {
                throw new Exception("Episode is not accepting new orders. Current status: {$episode->status}");
            }

            // Create follow-up order
            $order = $this->orderHandler->createFollowUpOrder($episode, $orderData);

            // Update episode status if needed
            if ($episode->status === PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW) {
                $episode->update(['status' => PatientManufacturerIVREpisode::STATUS_IVR_SENT]);
            }

            DB::commit();

            $this->logger->info('Follow-up order added successfully', [
                'episode_id' => $episode->id,
                'order_id' => $order->id
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to add follow-up order', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to add follow-up order: ' . $e->getMessage());
        }
    }

    /**
     * Approve episode and notify manufacturer
     */
    public function approveEpisode(PatientManufacturerIVREpisode $episode): void
    {
        $this->logger->info('Approving episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Update episode status
            $episode->update([
                'status' => PatientManufacturerIVREpisode::STATUS_SENT_TO_MANUFACTURER,
                'metadata' => array_merge($episode->metadata ?? [], [
                    'reviewed_at' => now()->toIso8601String(),
                    'reviewed_by' => Auth::id()
                ])
            ]);

            // Update FHIR Task status
            $taskFhirId = $episode->metadata['task_fhir_id'] ?? null;
            if ($taskFhirId) {
                $this->clinicalHandler->updateTaskStatus(
                    $taskFhirId,
                    'completed',
                    'Approved for manufacturer review'
                );
            }

            // Send notification to manufacturer
            $this->notificationHandler->notifyManufacturerApproval($episode);

            DB::commit();

            $this->logger->info('Episode approved successfully', [
                'episode_id' => $episode->id,
                'new_status' => $episode->status
            ]);

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to approve episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to approve episode: ' . $e->getMessage());
        }
    }

    /**
     * Complete episode after manufacturer approval
     */
    public function completeEpisode(PatientManufacturerIVREpisode $episode, array $manufacturerResponse): void
    {
        $this->logger->info('Completing episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Update episode with manufacturer response
            $episode->update([
                'status' => PatientManufacturerIVREpisode::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => array_merge($episode->metadata ?? [], [
                    'manufacturer_response' => $manufacturerResponse
                ])
            ]);

            // Update all pending orders
            $episode->orders()
                ->where('status', 'pending')
                ->update(['status' => 'approved']);

            // Update FHIR resources
            $episodeOfCareFhirId = $episode->metadata['episode_of_care_fhir_id'] ?? null;
            if ($episodeOfCareFhirId) {
                $this->clinicalHandler->completeEpisodeOfCare($episodeOfCareFhirId);
            }

            // Send completion notifications
            $this->notificationHandler->notifyEpisodeCompletion($episode);

            DB::commit();

            $this->logger->info('Episode completed successfully', [
                'episode_id' => $episode->id
            ]);

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to complete episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to complete episode: ' . $e->getMessage());
        }
    }
}
