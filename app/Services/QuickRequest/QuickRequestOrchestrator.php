<?php

namespace App\Services\QuickRequest;

use App\Models\Episode;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\DB;
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
    public function startEpisode(array $data): Episode
    {
        $this->logger->info('Starting new Quick Request episode');
        
        DB::beginTransaction();
        
        try {
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
            
            // Step 5: Create insurance coverage
            $coverageId = $this->insuranceHandler->createCoverage([
                'patient_id' => $patientFhirId,
                'insurance' => $data['insurance']
            ]);
            
            // Step 6: Create local episode record
            $episode = Episode::create([
                'patient_fhir_id' => $patientFhirId,
                'practitioner_fhir_id' => $providerFhirId,
                'organization_fhir_id' => $organizationFhirId,
                'episode_of_care_fhir_id' => $clinicalResources['episode_of_care_id'],
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => 'draft',
                'metadata' => [
                    'condition_id' => $clinicalResources['condition_id'],
                    'coverage_id' => $coverageId,
                    'created_by' => auth()->id()
                ]
            ]);
            
            // Step 7: Create initial order
            $order = $this->orderHandler->createInitialOrder($episode, $data['order_details']);
            
            DB::commit();
            
            $this->logger->info('Quick Request episode created successfully', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);
            
            return $episode->load('orders');
            
        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logger->error('Failed to create Quick Request episode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Add follow-up order to existing episode
     */
    public function addFollowUpOrder(Episode $episode, array $orderData): Order
    {
        $this->logger->info('Adding follow-up order to episode', [
            'episode_id' => $episode->id
        ]);
        
        DB::beginTransaction();
        
        try {
            // Validate episode can accept new orders
            if (!in_array($episode->status, ['draft', 'active'])) {
                throw new Exception("Episode is not accepting new orders. Current status: {$episode->status}");
            }
            
            // Create follow-up order
            $order = $this->orderHandler->createFollowUpOrder($episode, $orderData);
            
            // Update episode status if needed
            if ($episode->status === 'draft') {
                $episode->update(['status' => 'pending_review']);
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
            
            throw $e;
        }
    }

    /**
     * Approve episode and notify manufacturer
     */
    public function approveEpisode(Episode $episode): void
    {
        $this->logger->info('Approving episode', [
            'episode_id' => $episode->id
        ]);
        
        DB::beginTransaction();
        
        try {
            // Update episode status
            $episode->update([
                'status' => 'manufacturer_review',
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id()
            ]);
            
            // Update FHIR Task status
            if ($episode->task_fhir_id) {
                $this->clinicalHandler->updateTaskStatus(
                    $episode->task_fhir_id,
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
            
            throw $e;
        }
    }

    /**
     * Complete episode after manufacturer approval
     */
    public function completeEpisode(Episode $episode, array $manufacturerResponse): void
    {
        $this->logger->info('Completing episode', [
            'episode_id' => $episode->id
        ]);
        
        DB::beginTransaction();
        
        try {
            // Update episode with manufacturer response
            $episode->update([
                'status' => 'completed',
                'completed_at' => now(),
                'manufacturer_response' => $manufacturerResponse
            ]);
            
            // Update all pending orders
            $episode->orders()
                ->where('status', 'pending')
                ->update(['status' => 'approved']);
            
            // Update FHIR resources
            $this->clinicalHandler->completeEpisodeOfCare($episode->episode_of_care_fhir_id);
            
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
            
            throw $e;
        }
    }
}