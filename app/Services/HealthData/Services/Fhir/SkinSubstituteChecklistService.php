<?php

namespace App\Services\HealthData\Services\Fhir;

use App\Services\HealthData\Clients\AzureFhirClient;
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRCondition;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRDocumentReference;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRObservation;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRProcedure;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRAnnotation;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRAttachment;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleRequest;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRObservation\FHIRObservationComponent;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRObservation\FHIRObservationReferenceRange;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseItem;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseAnswer;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBoolean;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCode;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCodeableConcept;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCoding;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRDateTime;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRDecimal;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRExtension;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRPeriod;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRQuantity;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRReference;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRString;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRUri;
use App\Services\HealthData\Exceptions\FHIRServiceException;

class SkinSubstituteChecklistService
{
    protected AzureFhirClient $fhirClient;
    protected string $patientFhirId;

    protected const LOINC_SYSTEM = 'http://loinc.org';
    protected const SNOMED_SYSTEM = 'http://snomed.info/sct';
    protected const ICD10_SYSTEM = 'http://hl7.org/fhir/sid/icd-10-cm';
    protected const UNITS_OF_MEASURE_SYSTEM = 'http://unitsofmeasure.org';
    protected const OBSERVATION_CATEGORY_SYSTEM = 'http://terminology.hl7.org/CodeSystem/observation-category';
    protected const CONDITION_CLINICAL_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-clinical';
    protected const CONDITION_VER_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-ver-status';
    protected const CONDITION_CATEGORY_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-category';
    protected const EXT_BASE_URL = 'http://localhost/fhir/StructureDefinition/';

    public function __construct(AzureFhirClient $fhirClient)
    {
        $this->fhirClient = $fhirClient;
    }

    public function createPreApplicationAssessment(
        SkinSubstituteChecklistInput $checklistData,
        string $patientFhirId,
        string $providerId,
        string $facilityId
    ): FHIRBundle {
        $this->patientFhirId = $patientFhirId;
        $bundle = $this->buildFhirBundle($checklistData, $providerId, $facilityId);
        $bundleArray = (array) $bundle->jsonSerialize();
        $responseBundleArray = (array) $this->fhirClient->createBundle($bundleArray);

        $qrEntry = collect((array)$responseBundleArray['entry'] ?? [])
            ->firstWhere(fn ($entry) => ($entry['resource']['resourceType'] ?? null) === 'QuestionnaireResponse');
        if ($qrEntry && !empty($qrEntry['resource']['id'])) {
            return $bundle;
        }
        $docRefEntry = collect($responseBundleArray['entry'] ?? [])
            ->firstWhere(fn ($entry) => ($entry['resource']['resourceType'] ?? null) === 'DocumentReference');
        if ($docRefEntry && isset($docRefEntry['resource']['id'])) {
             return $bundle;
        }
        return $bundle;
    }

    protected function buildFhirBundle(
        SkinSubstituteChecklistInput $data,
        string $providerId,
        string $facilityId
    ): FHIRBundle {
        $bundle = new FHIRBundle([
            'id' => $this->generateId('bundle'),
            'type' => 'transaction',
            'timestamp' => now()->toIso8601String(),
        ]);

        foreach ($this->buildConditionResources($data) as $condition) {
            $bundle->addEntry($this->createBundleEntry($condition));
        }
        $observations = array_merge(
            $this->buildComprehensiveWoundAssessment($data),
            $this->buildPreciseMeasurementObservations($data),
            $this->buildLabObservations($data),
            $this->buildCirculationAssessmentBundle($data),
            $this->buildRiskFactorAssessment($data)
        );
        foreach ($observations as $observation) {
            if ($observation instanceof FHIRObservation) {
                $bundle->addEntry($this->createBundleEntry($observation));
            }
        }
        foreach ($this->buildConservativeTreatmentBundle($data) as $procedureOrObservation) {
            if ($procedureOrObservation instanceof FHIRProcedure ||
                $procedureOrObservation instanceof FHIRObservation) {
                $bundle->addEntry($this->createBundleEntry($procedureOrObservation));
            }
        }
        $qr = $this->buildQuestionnaireResponse($data, $this->patientFhirId, $providerId, $facilityId);
        $bundle->addEntry($this->createBundleEntry($qr));
        $docRef = $this->buildEnhancedDocumentReference($data, $qr->getId()->getValue(), $providerId, $facilityId);
        $bundle->addEntry($this->createBundleEntry($docRef));
        return $bundle;
    }

    protected function createBundleEntry($resource): FHIRBundleEntry {
        $idValue = $resource->getId() ? $resource->getId()->getValue() : $this->generateId(strtolower($resource->getResourceType()));

        $entry = new FHIRBundleEntry([
            'fullUrl' => new FHIRUri($resource->getResourceType() . '/' . $idValue),
            'resource' => $resource,
            'request' => new FHIRBundleRequest([
                'method' => new FHIRCode('POST'),
                'url' => new FHIRUri($resource->getResourceType())
            ])
        ]);
        return $entry;
    }

    protected function generateId(string $prefix = ''): string {
        return ($prefix ? $prefix . '-' : '') . Str::uuid()->toString();
    }

    protected function createCodeableConcept(?string $system, ?string $code, ?string $display = null): FHIRCodeableConcept {
        $coding = new FHIRCoding();
        if ($system) $coding->setSystem(new FHIRString($system));
        if ($code) $coding->setCode(new FHIRString($code));
        if ($display) $coding->setDisplay(new FHIRString($display));
        else if ($code) $coding->setDisplay(new FHIRString($code));
        $concept = new FHIRCodeableConcept();
        if ($code || $system) $concept->addCoding($coding);
        if ($display) $concept->setText(new FHIRString($display));
        else if ($code) $concept->setText(new FHIRString($code));
        return $concept;
    }

    protected function createQuantity($value, string $unit, string $system, ?string $code = null): FHIRQuantity {
        return new FHIRQuantity([
            'value' => (float)$value,
            'unit' => $unit,
            'system' => $system,
            'code' => $code ?? $unit,
        ]);
    }

    protected function createExtension(string $url, string $valueType, $value): FHIRExtension {
        $extension = new FHIRExtension(['url' => new FHIRUri($url)]);

        if ($value === null) return $extension;

        switch (strtolower($valueType)) {
            case 'string':
                $extension->setValueString($value instanceof FHIRString ? $value : new FHIRString($value));
                break;
            case 'boolean':
                $extension->setValueBoolean($value instanceof FHIRBoolean ? $value : new FHIRBoolean($value));
                break;
            case 'decimal':
                $extension->setValueDecimal($value instanceof FHIRDecimal ? $value : new FHIRDecimal($value));
                break;
            case 'integer':
                $extension->setValueInteger(is_int($value) ? $value : (int)$value);
                break;
            case 'datetime':
                $extension->setValueDateTime($value instanceof FHIRDateTime ? $value : new FHIRDateTime($value));
                break;
            default:
                $methodName = 'setValue' . ucfirst($valueType);
                if (method_exists($extension, $methodName)) {
                    $extension->{$methodName}($value);
                } else {
                    Log::warning("Unsupported valueType '{$valueType}' for FHIRExtension.");
                }
                break;
        }
        return $extension;
    }

    protected function createReference(string $referenceString, ?string $type = null, ?string $display = null): FHIRReference {
        $ref = new FHIRReference(['reference' => new FHIRString($referenceString)]);
        if ($type) $ref->setType(new FHIRString($type));
        if ($display) $ref->setDisplay(new FHIRString($display));
        return $ref;
    }

    protected function createComponent(FHIRCodeableConcept $code, string $valueType, $value): FHIRObservationComponent {
        $methodName = 'setValue' . ucfirst($valueType);
        $component = new FHIRObservationComponent(['code' => $code]);
        if (method_exists($component, $methodName) && $value !== null) {
            if ($valueType === 'Boolean' && !($value instanceof FHIRBoolean)) $value = new FHIRBoolean($value);
            if ($valueType === 'String' && !($value instanceof FHIRString)) $value = new FHIRString($value);
            $component->{$methodName}($value);
        }
        return $component;
    }

    protected function createQuantityComponent(string $system, string $code, string $display, $value, string $unit, string $valueSystem): FHIRObservationComponent {
        return new FHIRObservationComponent([
            'code' => $this->createCodeableConcept($system, $code, $display),
            'valueQuantity' => $this->createQuantity($value, $unit, $valueSystem)
        ]);
    }

    protected function createAnnotation(string $text): FHIRAnnotation {
        return new FHIRAnnotation(['text' => new FHIRString($text)]);
    }

    protected function createReferenceRange($low, $high, string $text): FHIRObservationReferenceRange {
        $range = new FHIRObservationReferenceRange(['text' => new FHIRString($text)]);
        if ($low !== null) $range->setLow($this->createQuantity($low, '', ''));
        if ($high !== null) $range->setHigh($this->createQuantity($high, '', ''));
        return $range;
    }

    protected function buildConditionResources(SkinSubstituteChecklistInput $data): array {
        $conditions = [];
        $baseConditionArgs = [
            'clinicalStatus' => $this->createCodeableConcept(self::CONDITION_CLINICAL_SYSTEM, 'active', 'Active'),
            'verificationStatus' => $this->createCodeableConcept(self::CONDITION_VER_STATUS_SYSTEM, 'confirmed', 'Confirmed'),
            'category' => [$this->createCodeableConcept(self::CONDITION_CATEGORY_SYSTEM, 'problem-list-item', 'Problem List Item')],
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'recordedDate' => new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String()),
        ];
        $bodySiteText = $data->ulcerLocation ?: $data->location;
        if ($bodySiteText) {
            $baseConditionArgs['bodySite'] = [$this->mapAnatomicalLocation($bodySiteText)];
        }
        if ($data->hasDiabetes) {
            $icdCode = $data->diabetesType === '1' ? 'E10.621' : 'E11.621';
            $display = "Type {$data->diabetesType} diabetes mellitus with foot ulcer";
            if (strpos(strtolower($data->ulcerLocation ?? ''), 'foot') === false && strpos(strtolower($data->ulcerLocation ?? ''), 'toe') === false ){
                 $icdCode = $data->diabetesType === '1' ? 'E10.9' : 'E11.9';
                 $display = "Type {$data->diabetesType} diabetes mellitus without complications";
            }
            $conditions[] = new FHIRCondition(array_merge($baseConditionArgs, [
                'id' => $this->generateId('condition-diabetes'),
                'code' => $this->createCodeableConcept(self::ICD10_SYSTEM, $icdCode, $display),
                'extension' => [$this->createExtension(self::EXT_BASE_URL . 'woundcare-wound-type', 'String', new FHIRString('DFU'))]
            ]));
        }
        if ($data->hasVenousStasisUlcer) {
            $conditions[] = new FHIRCondition(array_merge($baseConditionArgs, [
                'id' => $this->generateId('condition-vsu'),
                'code' => $this->createCodeableConcept(self::ICD10_SYSTEM, 'I83.009', 'Varicose veins of unspecified lower extremity with ulcer of unspecified site'),
                'extension' => [$this->createExtension(self::EXT_BASE_URL . 'woundcare-wound-type', 'String', new FHIRString('VLU'))]
            ]));
        }
        if ($data->hasPressureUlcer) {
            $extensions = [
                $this->createExtension(self::EXT_BASE_URL . 'woundcare-wound-type', 'String', new FHIRString('PU'))
            ];
            if ($data->pressureUlcerStage) {
                $extensions[] = $this->createExtension(self::EXT_BASE_URL . 'woundcare-wound-stage', 'String', new FHIRString("Stage {$data->pressureUlcerStage}"));
            }
            $conditions[] = new FHIRCondition(array_merge($baseConditionArgs, [
                'id' => $this->generateId('condition-pu'),
                'code' => $this->createCodeableConcept(self::ICD10_SYSTEM, 'L89.-', 'Pressure ulcer (stage varies)'),
                'extension' => $extensions
            ]));
        }
        return $conditions;
    }

    protected function buildComprehensiveWoundAssessment(SkinSubstituteChecklistInput $data): array {
        $observations = [];
        $effectiveDateTime = new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String());
        $bodySite = $data->ulcerLocation ? $this->mapAnatomicalLocation($data->ulcerLocation) : null;

        $woundAssertion = new FHIRObservation([
            'id' => $this->generateId('wound-assertion'),
            'status' => 'final',
            'category' => [$this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'exam', 'Exam')],
            'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '39135-5', 'Wound observed'),
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'effectiveDateTime' => $effectiveDateTime,
            'valueCodeableConcept' => $this->createCodeableConcept(self::SNOMED_SYSTEM, '420824004', 'Wound present'),
        ]);
        if($bodySite) $woundAssertion->setBodySite($bodySite);
        $observations[] = $woundAssertion;

        $observations[] = $this->createWoundBedObservation($data, $effectiveDateTime, $bodySite);
        return $observations;
    }

    protected function createWoundBedObservation(SkinSubstituteChecklistInput $data, FHIRDateTime $effectiveDateTime, ?FHIRCodeableConcept $bodySite): FHIRObservation {
        $observation = new FHIRObservation([
            'id' => $this->generateId('wound-bed'),
            'status' => 'final',
            'category' => [$this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'exam', 'Exam')],
            'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '72371-8', 'Wound bed appearance'),
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'effectiveDateTime' => $effectiveDateTime,
        ]);
        if($bodySite) $observation->setBodySite($bodySite);
        $components = [];
        if (isset($data->hasNecroticTissue)) {
            $components[] = $this->createComponent(
                $this->createCodeableConcept(self::LOINC_SYSTEM, '89259-6', 'Presence of necrotic tissue in wound bed'),
                'Boolean', new FHIRBoolean($data->hasNecroticTissue)
            );
        }
        if (!empty($components)) {
            foreach ($components as $component) {
                $observation->addComponent($component);
            }
        }
        return $observation;
    }

    protected function buildPreciseMeasurementObservations(SkinSubstituteChecklistInput $data): array {
        if (!isset($data->length) || !is_numeric($data->length) || !isset($data->width) || !is_numeric($data->width)) return [];
        $totalArea = round((float)$data->length * (float)$data->width, 2);
        $measurementPanel = new FHIRObservation([
            'id' => $this->generateId('wound-size-panel'),
            'status' => 'final',
            'meta' => ['profile' => ['http://hl7.org/fhir/us/skin-wound-assessment/StructureDefinition/WoundSize']],
            'category' => [$this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'exam', 'Exam')],
            'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '72287-6', 'Wound size panel'),
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'effectiveDateTime' => new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String()),
            'bodySite' => $data->ulcerLocation ? $this->mapAnatomicalLocation($data->ulcerLocation) : null,
            'valueQuantity' => $this->createQuantity($totalArea, 'cm2', self::UNITS_OF_MEASURE_SYSTEM)
        ]);
        $components = [
            $this->createQuantityComponent(self::LOINC_SYSTEM, '8341-0', 'Wound length', $data->length, 'cm', self::UNITS_OF_MEASURE_SYSTEM),
            $this->createQuantityComponent(self::LOINC_SYSTEM, '8340-2', 'Wound width', $data->width, 'cm', self::UNITS_OF_MEASURE_SYSTEM),
        ];
        if (isset($data->woundDepth) && is_numeric($data->woundDepth)) {
            $components[] = $this->createQuantityComponent(self::LOINC_SYSTEM, '8333-7', 'Wound depth', $data->woundDepth, 'cm', self::UNITS_OF_MEASURE_SYSTEM);
        }
        if(!empty($components)) {
            foreach ($components as $component) {
                $measurementPanel->addComponent($component);
            }
        }
        return [$measurementPanel];
    }

    protected function buildLabObservations(SkinSubstituteChecklistInput $data): array {
        $observations = [];
        $categoryLab = $this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'laboratory', 'Laboratory');
        $defaultEffectiveDate = new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String());

        if ($data->hba1cResult !== null && $data->hba1cDate) {
            $observations[] = new FHIRObservation([
                'id' => $this->generateId('obs-hba1c'), 'status' => 'final', 'category' => [$categoryLab],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '4548-4', 'Hemoglobin A1c/Hemoglobin.total in Blood'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->hba1cDate),
                'valueQuantity' => $this->createQuantity($data->hba1cResult, '%', self::UNITS_OF_MEASURE_SYSTEM)
            ]);
        }
        if ($data->albuminResult !== null && $data->albuminDate) {
            $observations[] = new FHIRObservation([
                'id' => $this->generateId('obs-albumin'), 'status' => 'final', 'category' => [$categoryLab],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '1751-7', 'Albumin [Mass/volume] in Serum or Plasma'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->albuminDate),
                'valueQuantity' => $this->createQuantity($data->albuminResult, 'g/dL', self::UNITS_OF_MEASURE_SYSTEM)
            ]);
        }
        if ($data->cbcPerformed === true) {
             $observations[] = $this->buildBooleanObservation('CBC Performed', '26604-1', $data->cbcPerformed, $defaultEffectiveDate, null, $categoryLab);
        }
        $crpVal = $data->crapResult ?? null;
        if ($crpVal !== null && $data->cultureDate) {
            $observations[] = new FHIRObservation([
                'id' => $this->generateId('obs-crp'), 'status' => 'final', 'category' => [$categoryLab],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '1988-5', 'C reactive protein [Mass/volume] in Serum or Plasma'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->cultureDate),
                'valueQuantity' => $this->createQuantity($crpVal, 'mg/L', self::UNITS_OF_MEASURE_SYSTEM)
            ]);
        }
        if ($data->sedRate !== null && $data->cultureDate) {
             $observations[] = new FHIRObservation([
                'id' => $this->generateId('obs-sedrate'), 'status' => 'final', 'category' => [$categoryLab],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '4537-7', 'Erythrocyte sedimentation rate by Westergren method'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->cultureDate),
                'valueQuantity' => $this->createQuantity($data->sedRate, 'mm/h', self::UNITS_OF_MEASURE_SYSTEM)
            ]);
        }
        if (isset($data->treated) && $data->cultureDate) {
             $observations[] = $this->buildBooleanObservation('Wound Infection Treated Post Culture', 'ASSERTION', $data->treated, new FHIRDateTime($data->cultureDate), null, $categoryLab);
        }
        return $observations;
    }

    protected function buildCirculationAssessmentBundle(SkinSubstituteChecklistInput $data): array {
        $observations = [];
        $categoryExam = $this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'exam', 'Exam');
        $defaultEffectiveDate = new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String());

        if ($data->abiResult !== null && $data->abiDate) {
            $abiObs = new FHIRObservation([
                'id' => $this->generateId('abi-assessment'), 'status' => 'final', 'category' => [$categoryExam],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '41979-6', 'Ankle-brachial index Panel'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->abiDate),
                'valueQuantity' => $this->createQuantity($data->abiResult, '', self::UNITS_OF_MEASURE_SYSTEM, '1')
            ]);
            $abiObs->addInterpretation($this->interpretABIResult((float)$data->abiResult));
            $observations[] = $abiObs;
        }
        if ($data->tcpo2Result !== null && $data->tcpo2Date) {
            $tcpo2Obs = new FHIRObservation([
                'id' => $this->generateId('tcpo2-assessment'), 'status' => 'final', 'category' => [$categoryExam],
                'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '2703-7', 'Oxygen partial pressure in Capillary blood'),
                'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => new FHIRDateTime($data->tcpo2Date),
                'valueQuantity' => $this->createQuantity($data->tcpo2Result, 'mm[Hg]', self::UNITS_OF_MEASURE_SYSTEM)
            ]);
            $tcpo2Obs->addReferenceRange($this->createReferenceRange(30, null, 'Adequate perfusion threshold > 30 mmHg'));
            $observations[] = $tcpo2Obs;
        }
        if (isset($data->hasTriphasicWaveforms)) {
            $effectiveDateTimeForDoppler = $data->waveformDate ? new FHIRDateTime($data->waveformDate) : $defaultEffectiveDate;
            $dopplerObs = $this->buildBooleanObservation('Doppler Waveforms Triphasic/Biphasic at Ankle', 'ASSERTION', $data->hasTriphasicWaveforms, $effectiveDateTimeForDoppler, null, $categoryExam );
            if(!empty($data->waveformResult)) $dopplerObs->addNote($this->createAnnotation("Doppler Result Notes: " . $data->waveformResult));
            $observations[] = $dopplerObs;
        }
        if (!empty($data->imagingType) && $data->imagingType !== 'none') {
            $observations[] = new FHIRObservation([
                 'id' => $this->generateId('imaging-performed'), 'status' => 'final', 'category' => [$categoryExam],
                 'code' => $this->createCodeableConcept(self::SNOMED_SYSTEM, '363679005', 'Imaging procedure'),
                 'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => ($data->waveformDate ? new FHIRDateTime($data->waveformDate) : $defaultEffectiveDate),
                 'valueString' => new FHIRString(ucfirst($data->imagingType) . " performed")
            ]);
        }
        return $observations;
    }

    protected function buildConservativeTreatmentBundle(SkinSubstituteChecklistInput $data): array {
        $procedures = [];
        $effectivePeriod = new FHIRPeriod(['start' => new FHIRDateTime(now()->subDays(30)->toDateString()), 'end' => new FHIRDateTime(now()->toDateString())]);
        if ($data->debridementPerformed) {
            $procedures[] = $this->createProcedureResource('36043009', 'Debridement of wound', $effectivePeriod);
        }
        if ($data->moistDressingsApplied) {
            $procedures[] = $this->createProcedureResource('225860007', 'Application of moisture retentive dressing', $effectivePeriod);
        }
        return $procedures;
    }

    protected function buildRiskFactorAssessment(SkinSubstituteChecklistInput $data): array {
        $riskObservations = [];
        $effectiveDateTime = new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String());
        $categorySocialHistory = $this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'social-history', 'Social History');

        if (!empty($data->smokingStatus)) {
            $smokingCode = match($data->smokingStatus) {
                'smoker' => '449868002',
                'previous-smoker' => '8517006',
                'non-smoker' => '266919005',
                default => null
            };
            if ($smokingCode) {
                $smokingObs = new FHIRObservation([
                    'id' => $this->generateId('smoking-status'), 'status' => 'final', 'category' => [$categorySocialHistory],
                    'code' => $this->createCodeableConcept(self::LOINC_SYSTEM, '72166-2', 'Tobacco smoking status'),
                    'subject' => $this->createReference("Patient/{$this->patientFhirId}"), 'effectiveDateTime' => $effectiveDateTime,
                    'valueCodeableConcept' => $this->createCodeableConcept(self::SNOMED_SYSTEM, $smokingCode, ucfirst(str_replace('-', ' ', $data->smokingStatus)))
                ]);
                if ($data->smokingStatus === 'smoker' && $data->smokingCounselingProvided !== null) {
                    $smokingObs->addNote($this->createAnnotation('Smoking cessation counseling provided: ' . ($data->smokingCounselingProvided ? 'Yes' : 'No')));
                }
                $riskObservations[] = $smokingObs;
            }
        }
        return $riskObservations;
    }

    protected function buildQuestionnaireResponse(SkinSubstituteChecklistInput $data, string $patientFhirId, string $providerId, string $facilityId): FHIRQuestionnaireResponse {
        $qrItems = [];
        $dtoVars = $data->toArray();

        foreach ($dtoVars as $key => $value) {
            if ($value === null || (is_array($value) && empty($value) && $key !== 'exposedStructures')) continue;

            $item = new FHIRQuestionnaireResponseItem([
                'linkId' => new FHIRString(Str::kebab($key)),
                'text' => new FHIRString(Str::title(str_replace('_', ' ', Str::snake($key))))
            ]);

            $valuesToProcess = is_array($value) ? $value : [$value];

            foreach ($valuesToProcess as $singleValue) {
                if ($singleValue === null) continue;

                $answer = new FHIRQuestionnaireResponseAnswer();

                // Fixed regex pattern - removed escaped quote
                if (is_bool($singleValue)) {
                    $answer->setValueBoolean(new FHIRBoolean($singleValue));
                } elseif (is_float($singleValue)) {
                    $answer->setValueDecimal($singleValue);
                } elseif (is_int($singleValue)) {
                    $answer->setValueInteger($singleValue);
                } elseif (is_string($singleValue) && $this->isValidFhirDateTime($singleValue)) {
                    $answer->setValueDateTime(new FHIRDateTime($singleValue));
                } elseif (is_string($singleValue)) {
                    $answer->setValueString(new FHIRString($singleValue));
                }

                if ($this->hasAnswerValue($answer)) {
                    $item->addAnswer($answer);
                }
            }

            if (!empty($item->getAnswer())) {
                $qrItems[] = $item;
            }
        }

        return new FHIRQuestionnaireResponse([
            'id' => $this->generateId('qr-checklist'),
            'questionnaire' => 'Questionnaire/skin-substitute-preapp',
            'status' => 'completed',
            'subject' => $this->createReference("Patient/{$patientFhirId}"),
            'authored' => new FHIRDateTime($data->dateOfProcedure ?: now()->toIso8601String()),
            'author' => $this->createReference("Practitioner/{$providerId}"),
            'item' => $qrItems,
            'extension' => [
                $this->createExtension(self::EXT_BASE_URL . 'woundcare-order-checklist-type', 'String', new FHIRString('SkinSubstitutePreApp')),
                $this->createExtension(self::EXT_BASE_URL . 'woundcare-order-checklist-version', 'String', new FHIRString('1.3')),
            ],
        ]);
    }

    /**
     * Validate FHIR DateTime format
     */
    protected function isValidFhirDateTime(string $value): bool {
        return preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?)?$/', $value) === 1;
    }

    /**
     * Check if QuestionnaireResponse answer has any value set
     */
    protected function hasAnswerValue(FHIRQuestionnaireResponseAnswer $answer): bool {
        return $answer->getValueBoolean() !== null ||
               $answer->getValueDecimal() !== null ||
               $answer->getValueInteger() !== null ||
               $answer->getValueDateTime() !== null ||
               $answer->getValueDate() !== null ||
               $answer->getValueString() !== null ||
               $answer->getValueTime() !== null ||
               $answer->getValueUri() !== null ||
               $answer->getValueAttachment() !== null ||
               $answer->getValueCoding() !== null ||
               $answer->getValueQuantity() !== null ||
               $answer->getValueReference() !== null;
    }

    protected function buildEnhancedDocumentReference(SkinSubstituteChecklistInput $data, string $questionnaireResponseId, string $providerId, string $facilityId): FHIRDocumentReference
    {
        $checklistJson = json_encode($data->toArray(), JSON_PRETTY_PRINT);
        $encodedContent = base64_encode($checklistJson);

        return new FHIRDocumentReference([
            'id' => $this->generateId('doc-checklist'),
            'status' => 'current',
            'type' => $this->createCodeableConcept(self::LOINC_SYSTEM, '34117-2', 'Wound assessment form'),
            'category' => [
                $this->createCodeableConcept('http://hl7.org/fhir/us/core/CodeSystem/us-core-documentreference-category', 'clinical-note', 'Clinical Note'),
            ],
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'date' => new FHIRDateTime(now()->toIso8601String()),
            'author' => [
                $this->createReference("Practitioner/{$providerId}"),
                $this->createReference("Organization/{$facilityId}"),
            ],
            'description' => new FHIRString('Skin Substitute Pre-Application Checklist Data'),
            'content' => [
                [
                    'attachment' => new FHIRAttachment([
                        'contentType' => 'application/json',
                        'language' => 'en-US',
                        'data' => $encodedContent,
                        'title' => new FHIRString('Skin Substitute Pre-Application Checklist JSON'),
                        'creation' => new FHIRDateTime(now()->toIso8601String()),
                    ]),
                ],
            ],
            'context' => [
                'related' => [
                    $this->createReference("QuestionnaireResponse/{$questionnaireResponseId}"),
                ],
            ],
        ]);
    }

    protected function mapAnatomicalLocation(string $location): FHIRCodeableConcept {
        return $this->createCodeableConcept(null, null, $location);
    }
    protected function determineWoundEtiology(SkinSubstituteChecklistInput $data): ?FHIRCodeableConcept { return null; }
    protected function createWoundEdgeObservation(SkinSubstituteChecklistInput $data, FHIRDateTime $effectiveDateTime, ?FHIRCodeableConcept $bodySite): FHIRObservation { return new FHIRObservation(['id' => $this->generateId('wound-edge')]); }
    protected function hasComplexWoundStructure(SkinSubstituteChecklistInput $data): bool { return false; }
    protected function createTunnelingObservation(SkinSubstituteChecklistInput $data, FHIRDateTime $effectiveDateTime, ?FHIRCodeableConcept $bodySite): FHIRObservation { return new FHIRObservation(['id' => $this->generateId('tunneling')]); }
    protected function createPeriwoundObservation(SkinSubstituteChecklistInput $data, FHIRDateTime $effectiveDateTime, ?FHIRCodeableConcept $bodySite): FHIRObservation { return new FHIRObservation(['id' => $this->generateId('periwound')]); }
    protected function mapDopplerWaveformCode(string $waveformType): string { return ''; }

    protected function createProcedureResource(string $snomedCode, string $display, FHIRPeriod $performedPeriod): FHIRProcedure {
        return new FHIRProcedure([
            'id' => $this->generateId('proc-'.Str::kebab($display)),
            'status' => 'completed',
            'code' => $this->createCodeableConcept(self::SNOMED_SYSTEM, $snomedCode, $display),
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'performedPeriod' => $performedPeriod
        ]);
    }
    protected function interpretABIResult(float $abi): FHIRCodeableConcept {
        $code = 'N'; $display = 'Normal';
        if ($abi > 0.9 && $abi <= 1.3) { $code = 'N'; $display = 'Normal'; }
        elseif ($abi >= 0.7 && $abi <= 0.9) { $code = 'A'; $display = 'Mild obstruction'; }
        elseif ($abi >= 0.4 && $abi < 0.7) { $code = 'A'; $display = 'Moderate obstruction'; }
        elseif ($abi < 0.4) { $code = 'A'; $display = 'Severe obstruction'; }
        elseif ($abi > 1.3) { $code = 'H'; $display = 'High (suggests non-compressible vessels)'; }
        if ($abi > 1.3) { return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'HH', 'Critically high');}
        if ($abi >= 0.91 && $abi <= 1.3) { return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'N', 'Normal');}
        if ($abi >= 0.7 && $abi <= 0.9) { return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'A', 'Mild PAD');}
        if ($abi >= 0.4 && $abi < 0.7) { return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'L', 'Moderate PAD');}
        if ($abi < 0.4) { return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'LL', 'Severe PAD (Critically Low)');}
        return $this->createCodeableConcept('http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'IND', 'Indeterminate');
    }

    protected function buildBooleanObservation(string $displayText, string $codeValue, bool $value, FHIRDateTime $effectiveDateTime, ?FHIRCodeableConcept $bodySite = null, ?FHIRCodeableConcept $category = null, string $codeSystem = self::SNOMED_SYSTEM): FHIRObservation
    {
        $obs = new FHIRObservation([
            'id' => $this->generateId('obs-'.Str::kebab($displayText)),
            'status' => 'final',
            'category' => $category ? [$category] : [$this->createCodeableConcept(self::OBSERVATION_CATEGORY_SYSTEM, 'exam', 'Exam')],
            'code' => $this->createCodeableConcept($codeSystem, $codeValue, $displayText),
            'subject' => $this->createReference("Patient/{$this->patientFhirId}"),
            'effectiveDateTime' => $effectiveDateTime,
            'valueBoolean' => new FHIRBoolean($value)
        ]);
        if ($bodySite) {
            $obs->setBodySite($bodySite);
        }
        return $obs;
    }
}