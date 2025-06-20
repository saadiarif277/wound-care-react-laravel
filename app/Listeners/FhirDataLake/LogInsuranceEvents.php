<?php

namespace App\Listeners\FhirDataLake;

use App\Services\FhirDataLake\FhirAuditEventService;
use App\Events\InsuranceCardScanned;
use App\Events\IVRCompleted;
use App\Events\EligibilityChecked;
use App\Events\CoverageUpdated;
use App\Events\QuickRequestCreated;

class LogInsuranceEvents
{
    private FhirAuditEventService $auditService;
    
    public function __construct(FhirAuditEventService $auditService)
    {
        $this->auditService = $auditService;
    }
    
    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            InsuranceCardScanned::class => 'handleCardScan',
            IVRCompleted::class => 'handleIVRCompletion',
            EligibilityChecked::class => 'handleEligibilityCheck',
            CoverageUpdated::class => 'handleCoverageUpdate',
            QuickRequestCreated::class => 'handleQuickRequestCreated',
        ];
    }
    
    public function handleCardScan($event): void
    {
        $this->auditService->logInsuranceCardScan(
            $event->patientId,
            $event->extractedData,
            $event->scanMethod ?? 'azure_ocr'
        );
    }
    
    public function handleIVRCompletion($event): void
    {
        $this->auditService->logIVRCompletion(
            $event->episodeId,
            $event->submissionId,
            $event->prefillPercentage ?? 0.0
        );
    }
    
    public function handleEligibilityCheck($event): void
    {
        $this->auditService->logEligibilityCheck(
            $event->coverageId,
            $event->provider,
            $event->request,
            $event->response
        );
    }
    
    public function handleCoverageUpdate($event): void
    {
        $this->auditService->createAuditEvent(
            'coverage_management',
            'coverage_updated',
            [
                [
                    'type' => 'coverage',
                    'reference' => "Coverage/{$event->coverage->id}",
                    'detail' => [
                        'update_source' => $event->source ?? 'manual',
                        'fields_updated' => implode(',', array_keys($event->changes ?? []))
                    ]
                ]
            ]
        );
    }
    
    public function handleQuickRequestCreated($event): void
    {
        $this->auditService->createAuditEvent(
            'insurance_verification',
            'quick_request_created',
            [
                [
                    'type' => 'episode',
                    'reference' => "Episode/{$event->episode->id}",
                    'detail' => [
                        'manufacturer' => $event->episode->manufacturer->name ?? 'Unknown',
                        'form_completion_time' => $event->completionTime ?? null,
                        'auto_filled_fields' => $event->autoFilledCount ?? 0
                    ]
                ]
            ]
        );
    }
}
