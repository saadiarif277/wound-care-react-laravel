<?php

namespace App\Services\FhirDataLake;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRAuditEvent;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRAuditEvent\FHIRAuditEventAgent;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRAuditEvent\FHIRAuditEventEntity;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRAuditEvent\FHIRAuditEventDetail;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCoding;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRReference;
use App\Models\FhirAuditLog;
use App\Services\FhirService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FhirAuditEventService
{
    private $fhirService;
    
    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    /**
     * Create immutable audit event for any insurance-related action
     */
    public function createAuditEvent(
        string $eventType,
        string $eventSubtype,
        array $entities,
        array $details = []
    ): FhirAuditLog {
        
        $auditEvent = new FHIRAuditEvent();
        
        // Set event type (what happened)
        $auditEvent->setType($this->getEventCoding($eventType));
        $auditEvent->addSubtype($this->getSubtypeCoding($eventSubtype));
        
        // Set when it happened
        $auditEvent->setRecorded(new \DateTime());
        
        // Set outcome
        $auditEvent->setOutcome($details['outcome'] ?? '0'); // 0 = success
        
        // Set who did it (agent)
        $agent = new FHIRAuditEventAgent();
        $agent->setWho($this->getCurrentUserReference());
        $agent->setRequestor(true);
        $auditEvent->addAgent($agent);
        
        // Set what was involved (entities)
        foreach ($entities as $entityData) {
            $entity = new FHIRAuditEventEntity();
            $entity->setWhat($this->createReference($entityData));
            $entity->setType($this->getEntityTypeCoding($entityData['type']));
            
            // Add entity details
            if (isset($entityData['detail'])) {
                foreach ($entityData['detail'] as $key => $value) {
                    $detail = new FHIRAuditEventDetail();
                    $detail->setType($key);
                    $detail->setValueString((string)$value);
                    $entity->addDetail($detail);
                }
            }
            
            $auditEvent->addEntity($entity);
        }
        
        // Store in local database
        $log = FhirAuditLog::create([
            'event_type' => $eventType,
            'event_subtype' => $eventSubtype,
            'user_id' => Auth::id(),
            'fhir_resource' => $auditEvent->jsonSerialize(),
            'entities' => $entities,
            'details' => $details,
            'recorded_at' => now(),
        ]);
        
        // Also send to FHIR server for immutability
        try {
            $azureFhirId = $this->sendToFhirServer($auditEvent);
            $log->update(['azure_fhir_id' => $azureFhirId]);
        } catch (\Exception $e) {
            Log::error('Failed to send audit event to FHIR server', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
                'event_subtype' => $eventSubtype
            ]);
        }
        
        return $log;
    }
    
    /**
     * Insurance card scan event
     */
    public function logInsuranceCardScan(
        string $patientId,
        array $extractedData,
        string $scanMethod = 'azure_ocr'
    ): void {
        $this->createAuditEvent(
            'insurance_verification',
            'card_scan',
            [
                [
                    'type' => 'patient',
                    'reference' => "Patient/{$patientId}",
                ],
                [
                    'type' => 'document',
                    'reference' => "DocumentReference/{$extractedData['document_id']}",
                    'detail' => [
                        'scan_method' => $scanMethod,
                        'confidence_score' => $extractedData['confidence'] ?? null,
                        'fields_extracted' => count($extractedData['fields'] ?? [])
                    ]
                ]
            ],
            [
                'extracted_data_hash' => hash('sha256', json_encode($extractedData)),
                'scan_duration_ms' => $extractedData['duration'] ?? null
            ]
        );
    }
    
    /**
     * IVR completion event
     */
    public function logIVRCompletion(
        string $episodeId,
        string $submissionId,
        float $prefillPercentage
    ): void {
        $this->createAuditEvent(
            'insurance_verification',
            'ivr_completed',
            [
                [
                    'type' => 'episode',
                    'reference' => "Episode/{$episodeId}",
                    'detail' => [
                        'submission_id' => $submissionId,
                        'prefill_percentage' => (string)$prefillPercentage,
                        'completion_method' => 'docuseal'
                    ]
                ]
            ]
        );
    }
    
    /**
     * Eligibility check event
     */
    public function logEligibilityCheck(
        string $coverageId,
        string $provider,
        array $request,
        array $response
    ): void {
        $this->createAuditEvent(
            'insurance_verification',
            'eligibility_check',
            [
                [
                    'type' => 'coverage',
                    'reference' => "Coverage/{$coverageId}",
                ],
                [
                    'type' => 'eligibility_request',
                    'reference' => "CoverageEligibilityRequest/{$request['id']}",
                    'detail' => [
                        'provider' => $provider,
                        'service_codes' => implode(',', $request['service_codes'] ?? []),
                        'response_time_ms' => $response['duration'] ?? null
                    ]
                ]
            ],
            [
                'outcome' => $response['eligible'] ? '0' : '8', // 0=success, 8=failure
                'request_hash' => hash('sha256', json_encode($request)),
                'response_hash' => hash('sha256', json_encode($response))
            ]
        );
    }
    
    /**
     * Get event type coding
     */
    private function getEventCoding(string $eventType): FHIRCoding
    {
        $coding = new FHIRCoding();
        $coding->setSystem('http://mscwoundcare.com/fhir/audit-event-type');
        $coding->setCode($eventType);
        $coding->setDisplay($this->getEventTypeDisplay($eventType));
        return $coding;
    }
    
    /**
     * Get event subtype coding
     */
    private function getSubtypeCoding(string $eventSubtype): FHIRCoding
    {
        $coding = new FHIRCoding();
        $coding->setSystem('http://mscwoundcare.com/fhir/audit-event-subtype');
        $coding->setCode($eventSubtype);
        $coding->setDisplay($this->getEventSubtypeDisplay($eventSubtype));
        return $coding;
    }
    
    /**
     * Get entity type coding
     */
    private function getEntityTypeCoding(string $entityType): FHIRCoding
    {
        $coding = new FHIRCoding();
        $coding->setSystem('http://terminology.hl7.org/CodeSystem/audit-entity-type');
        $coding->setCode($this->mapEntityTypeToCode($entityType));
        return $coding;
    }
    
    /**
     * Create reference for entity
     */
    private function createReference(array $entityData): FHIRReference
    {
        $reference = new FHIRReference();
        $reference->setReference($entityData['reference'] ?? null);
        $reference->setDisplay($entityData['display'] ?? null);
        return $reference;
    }
    
    /**
     * Get current user reference
     */
    private function getCurrentUserReference(): FHIRReference
    {
        $reference = new FHIRReference();
        if (Auth::check()) {
            $user = Auth::user();
            $reference->setReference("Practitioner/{$user->practitioner_fhir_id}");
            $reference->setDisplay($user->name);
        } else {
            $reference->setReference("Device/system");
            $reference->setDisplay("System Process");
        }
        return $reference;
    }
    
    /**
     * Send to FHIR server
     */
    private function sendToFhirServer(FHIRAuditEvent $auditEvent): ?string
    {
        try {
            // This would integrate with your Azure FHIR service
            // For now, returning null
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to send audit event to FHIR server', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Map entity type to FHIR code
     */
    private function mapEntityTypeToCode(string $entityType): string
    {
        $mapping = [
            'patient' => '1',
            'person' => '1',
            'coverage' => '2',
            'document' => '3',
            'episode' => '4',
            'eligibility_request' => '4',
        ];
        
        return $mapping[$entityType] ?? '4';
    }
    
    /**
     * Get event type display text
     */
    private function getEventTypeDisplay(string $eventType): string
    {
        $displays = [
            'insurance_verification' => 'Insurance Verification',
            'coverage_update' => 'Coverage Update',
            'eligibility_determination' => 'Eligibility Determination',
        ];
        
        return $displays[$eventType] ?? $eventType;
    }
    
    /**
     * Get event subtype display text
     */
    private function getEventSubtypeDisplay(string $eventSubtype): string
    {
        $displays = [
            'card_scan' => 'Insurance Card Scan',
            'ivr_completed' => 'IVR Form Completed',
            'eligibility_check' => 'Eligibility Check Performed',
            'coverage_created' => 'Coverage Record Created',
            'coverage_updated' => 'Coverage Record Updated',
        ];
        
        return $displays[$eventSubtype] ?? $eventSubtype;
    }
}
