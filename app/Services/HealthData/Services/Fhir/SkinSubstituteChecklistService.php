<?php

namespace App\Services\HealthData\Services\Fhir;

use App\Services\HealthData\Clients\AzureFhirClient;
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SkinSubstituteChecklistService
{
    private AzureFhirClient $fhirClient;

    public function __construct(AzureFhirClient $fhirClient)
    {
        $this->fhirClient = $fhirClient;
    }

    /**
     * Create a pre-application assessment in FHIR
     *
     * @throws \Exception
     */
    public function createPreApplicationAssessment(
        SkinSubstituteChecklistInput $checklistData,
        string $providerId,
        string $facilityId
    ): string {
        $bundle = $this->buildFhirBundle($checklistData, $providerId, $facilityId);
        $response = $this->fhirClient->createBundle($bundle);
        
        // Extract the DocumentReference ID which serves as azure_order_checklist_fhir_id
        $documentRefEntry = collect($bundle['entry'] ?? [])
            ->firstWhere('resource.resourceType', 'DocumentReference');
        
        return $documentRefEntry['resource']['id'] ?? $response['id'];
    }

    /**
     * Build FHIR Bundle from checklist data
     */
    private function buildFhirBundle(
        SkinSubstituteChecklistInput $data,
        string $providerId,
        string $facilityId
    ): array {
        $bundleId = 'checklist-' . now()->timestamp;
        
        $entries = [];
        
        // 1. Patient Resource
        $entries[] = [
            'resource' => $this->buildPatientResource($data)
        ];
        
        // 2. Primary Condition Resources
        $entries = array_merge($entries, $this->buildConditionResources($data));
        
        // 3. Wound Assessment Observations
        $entries = array_merge($entries, $this->buildWoundObservations($data));
        
        // 4. Lab Result Observations
        $entries = array_merge($entries, $this->buildLabObservations($data));
        
        // 5. Circulation Assessment Observations
        $entries = array_merge($entries, $this->buildCirculationObservations($data));
        
        // 6. Conservative Treatment Observations
        $entries = array_merge($entries, $this->buildTreatmentObservations($data));
        
        // 7. QuestionnaireResponse
        $entries[] = [
            'resource' => $this->buildQuestionnaireResponse($data, $bundleId)
        ];
        
        // 8. DocumentReference
        $entries[] = [
            'resource' => $this->buildDocumentReference($data, $bundleId, $providerId)
        ];
        
        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'type' => 'collection',
            'timestamp' => now()->toIso8601String(),
            'entry' => $entries
        ];
    }

    /**
     * Build Patient FHIR Resource
     */
    private function buildPatientResource(SkinSubstituteChecklistInput $data): array
    {
        $nameParts = explode(' ', $data->patientName);
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);
        
        return [
            'resourceType' => 'Patient',
            'id' => 'patient-' . now()->timestamp,
            'name' => [
                [
                    'use' => 'official',
                    'family' => $lastName,
                    'given' => [$firstName]
                ]
            ],
            'birthDate' => $data->dateOfBirth,
            'extension' => [
                [
                    'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-patient-consent',
                    'valueBoolean' => true
                ]
            ]
        ];
    }

    /**
     * Build Condition FHIR Resources
     */
    private function buildConditionResources(SkinSubstituteChecklistInput $data): array
    {
        $conditions = [];
        
        // Diabetes Condition
        if ($data->hasDiabetes) {
            $diabetesCode = $data->diabetesType === '1' ? 'E10.621' : 'E11.621';
            $conditions[] = [
                'resource' => [
                    'resourceType' => 'Condition',
                    'id' => 'condition-diabetes-' . now()->timestamp,
                    'clinicalStatus' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                                'code' => 'active'
                            ]
                        ]
                    ],
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/sid/icd-10-cm',
                                'code' => $diabetesCode,
                                'display' => "Type {$data->diabetesType} diabetes mellitus with foot ulcer"
                            ]
                        ]
                    ],
                    'extension' => [
                        [
                            'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type',
                            'valueString' => 'DFU'
                        ]
                    ]
                ]
            ];
        }
        
        // Venous Stasis Ulcer
        if ($data->hasVenousStasisUlcer) {
            $conditions[] = [
                'resource' => [
                    'resourceType' => 'Condition',
                    'id' => 'condition-vsu-' . now()->timestamp,
                    'clinicalStatus' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                                'code' => 'active'
                            ]
                        ]
                    ],
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/sid/icd-10-cm',
                                'code' => 'I87.2',
                                'display' => 'Venous insufficiency (chronic) (peripheral)'
                            ]
                        ]
                    ],
                    'extension' => [
                        [
                            'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type',
                            'valueString' => 'VLU'
                        ]
                    ]
                ]
            ];
        }
        
        // Pressure Ulcer
        if ($data->hasPressureUlcer) {
            $conditions[] = [
                'resource' => [
                    'resourceType' => 'Condition',
                    'id' => 'condition-pu-' . now()->timestamp,
                    'clinicalStatus' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                                'code' => 'active'
                            ]
                        ]
                    ],
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/sid/icd-10-cm',
                                'code' => 'L89.90',
                                'display' => 'Pressure ulcer of unspecified site, unspecified stage'
                            ]
                        ]
                    ],
                    'extension' => [
                        [
                            'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type',
                            'valueString' => 'PU'
                        ],
                        [
                            'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-stage',
                            'valueString' => $data->pressureUlcerStage ?? 'Unknown'
                        ]
                    ]
                ]
            ];
        }
        
        return $conditions;
    }

    /**
     * Build Wound Observation Resources
     */
    private function buildWoundObservations(SkinSubstituteChecklistInput $data): array
    {
        $observations = [];
        
        // Wound Size Observation
        $observations[] = [
            'resource' => [
                'resourceType' => 'Observation',
                'id' => 'obs-wound-size-' . now()->timestamp,
                'status' => 'final',
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                                'code' => 'exam',
                                'display' => 'Physical Exam'
                            ]
                        ]
                    ]
                ],
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '39125-6',
                            'display' => 'Wound area'
                        ]
                    ]
                ],
                'component' => [
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://loinc.org',
                                    'code' => '8341-0',
                                    'display' => 'Wound length'
                                ]
                            ]
                        ],
                        'valueQuantity' => [
                            'value' => $data->length,
                            'unit' => 'cm',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'cm'
                        ]
                    ],
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://loinc.org',
                                    'code' => '8340-2',
                                    'display' => 'Wound width'
                                ]
                            ]
                        ],
                        'valueQuantity' => [
                            'value' => $data->width,
                            'unit' => 'cm',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'cm'
                        ]
                    ],
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://loinc.org',
                                    'code' => '8333-7',
                                    'display' => 'Wound depth'
                                ]
                            ]
                        ],
                        'valueQuantity' => [
                            'value' => $data->woundDepth,
                            'unit' => 'cm',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'cm'
                        ]
                    ]
                ],
                'extension' => [
                    [
                        'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-measurement-technique',
                        'valueString' => 'Ruler'
                    ]
                ]
            ]
        ];
        
        // Wound Depth Classification
        $observations[] = [
            'resource' => [
                'resourceType' => 'Observation',
                'id' => 'obs-wound-depth-' . now()->timestamp,
                'status' => 'final',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '425094009',
                            'display' => 'Depth of wound'
                        ]
                    ]
                ],
                'valueCodeableConcept' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => $data->depth === 'full-thickness' ? '261179002' : '24484000',
                            'display' => $data->depth === 'full-thickness' ? 'Full thickness' : 'Partial thickness'
                        ]
                    ]
                ]
            ]
        ];
        
        // Exposed Structures
        if (!empty($data->exposedStructures)) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => 'obs-exposed-structures-' . now()->timestamp,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '364537001',
                                'display' => 'Visible anatomic structure'
                            ]
                        ]
                    ],
                    'valueCodeableConcept' => [
                        'text' => implode(', ', $data->exposedStructures)
                    ]
                ]
            ];
        }
        
        return $observations;
    }

    /**
     * Build Lab Observation Resources
     */
    private function buildLabObservations(SkinSubstituteChecklistInput $data): array
    {
        $observations = [];
        
        // HbA1c
        if ($data->hba1cResult !== null) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => 'obs-hba1c-' . now()->timestamp,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '4548-4',
                                'display' => 'Hemoglobin A1c'
                            ]
                        ]
                    ],
                    'valueQuantity' => [
                        'value' => $data->hba1cResult,
                        'unit' => '%',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => '%'
                    ],
                    'effectiveDateTime' => $data->hba1cDate
                ]
            ];
        }
        
        // Albumin
        if ($data->albuminResult !== null) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => 'obs-albumin-' . now()->timestamp,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '1751-7',
                                'display' => 'Albumin'
                            ]
                        ]
                    ],
                    'valueQuantity' => [
                        'value' => $data->albuminResult,
                        'unit' => 'g/dL',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'g/dL'
                    ],
                    'effectiveDateTime' => $data->albuminDate
                ]
            ];
        }
        
        return $observations;
    }

    /**
     * Build Circulation Assessment Observation Resources
     */
    private function buildCirculationObservations(SkinSubstituteChecklistInput $data): array
    {
        $observations = [];
        
        // ABI
        if ($data->abiResult !== null) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => 'obs-abi-' . now()->timestamp,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '88073-4',
                                'display' => 'Ankle brachial index'
                            ]
                        ]
                    ],
                    'valueQuantity' => [
                        'value' => $data->abiResult,
                        'system' => 'http://unitsofmeasure.org',
                        'code' => '1'
                    ],
                    'effectiveDateTime' => $data->abiDate
                ]
            ];
        }
        
        // TcPO2
        if ($data->tcpo2Result !== null) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => 'obs-tcpo2-' . now()->timestamp,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '19223-7',
                                'display' => 'Transcutaneous oxygen measurement'
                            ]
                        ]
                    ],
                    'valueQuantity' => [
                        'value' => $data->tcpo2Result,
                        'unit' => 'mmHg',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'mm[Hg]'
                    ],
                    'effectiveDateTime' => $data->tcpo2Date
                ]
            ];
        }
        
        return $observations;
    }

    /**
     * Build Treatment Observation Resources
     */
    private function buildTreatmentObservations(SkinSubstituteChecklistInput $data): array
    {
        $observations = [];
        
        // Conservative Treatment Summary
        $observations[] = [
            'resource' => [
                'resourceType' => 'Observation',
                'id' => 'obs-conservative-treatment-' . now()->timestamp,
                'status' => 'final',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '133918004',
                            'display' => 'Conservative treatment'
                        ]
                    ]
                ],
                'component' => [
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://snomed.info/sct',
                                    'code' => '36777000',
                                    'display' => 'Debridement'
                                ]
                            ]
                        ],
                        'valueBoolean' => $data->debridementPerformed
                    ],
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://snomed.info/sct',
                                    'code' => '182531007',
                                    'display' => 'Dressing applied'
                                ]
                            ]
                        ],
                        'valueBoolean' => $data->moistDressingsApplied
                    ],
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system' => 'http://snomed.info/sct',
                                    'code' => '225358003',
                                    'display' => 'Pressure relief'
                                ]
                            ]
                        ],
                        'valueBoolean' => $data->nonWeightBearing || $data->pressureReducingFootwear
                    ]
                ]
            ]
        ];
        
        return $observations;
    }

    /**
     * Build QuestionnaireResponse Resource
     */
    private function buildQuestionnaireResponse(
        SkinSubstituteChecklistInput $data,
        string $bundleId
    ): array {
        $items = [
            [
                'linkId' => 'patient-info',
                'text' => 'Patient Information',
                'item' => [
                    [
                        'linkId' => 'patient-name',
                        'text' => 'Patient Name',
                        'answer' => [['valueString' => $data->patientName]]
                    ],
                    [
                        'linkId' => 'dob',
                        'text' => 'Date of Birth',
                        'answer' => [['valueDate' => $data->dateOfBirth]]
                    ],
                    [
                        'linkId' => 'procedure-date',
                        'text' => 'Date of Procedure',
                        'answer' => [['valueDate' => $data->dateOfProcedure]]
                    ]
                ]
            ],
            [
                'linkId' => 'diagnosis',
                'text' => 'Diagnosis',
                'item' => array_filter([
                    [
                        'linkId' => 'diabetes',
                        'text' => 'Diabetes',
                        'answer' => [['valueBoolean' => $data->hasDiabetes]]
                    ],
                    $data->diabetesType ? [
                        'linkId' => 'diabetes-type',
                        'text' => 'Diabetes Type',
                        'answer' => [['valueString' => $data->diabetesType]]
                    ] : null
                ])
            ]
        ];
        
        return [
            'resourceType' => 'QuestionnaireResponse',
            'id' => 'qr-checklist-' . now()->timestamp,
            'questionnaire' => 'https://msc-mvp.com/fhir/Questionnaire/skin-substitute-preapp',
            'status' => 'completed',
            'authored' => now()->toIso8601String(),
            'item' => $items
        ];
    }

    /**
     * Build DocumentReference Resource
     */
    private function buildDocumentReference(
        SkinSubstituteChecklistInput $data,
        string $bundleId,
        string $providerId
    ): array {
        $checklistJson = json_encode($data->toArray(), JSON_PRETTY_PRINT);
        $encodedContent = base64_encode($checklistJson);
        
        return [
            'resourceType' => 'DocumentReference',
            'id' => 'doc-checklist-' . now()->timestamp,
            'status' => 'current',
            'docStatus' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '34117-2',
                        'display' => 'Wound assessment form'
                    ]
                ]
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://hl7.org/fhir/us/core/CodeSystem/us-core-documentreference-category',
                            'code' => 'clinical-note',
                            'display' => 'Clinical Note'
                        ]
                    ]
                ]
            ],
            'date' => now()->toIso8601String(),
            'description' => 'Skin Substitute Pre-Application Checklist',
            'content' => [
                [
                    'attachment' => [
                        'contentType' => 'application/json',
                        'language' => 'en-US',
                        'data' => $encodedContent,
                        'title' => 'Skin Substitute Pre-Application Checklist',
                        'creation' => now()->toIso8601String()
                    ]
                ]
            ],
            'extension' => [
                [
                    'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-order-checklist-type',
                    'valueString' => 'SkinSubstitutePreApp'
                ],
                [
                    'url' => 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-order-checklist-version',
                    'valueString' => 'v1.0'
                ]
            ]
        ];
    }
}