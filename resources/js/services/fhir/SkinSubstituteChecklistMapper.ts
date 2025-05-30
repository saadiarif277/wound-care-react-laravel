import { Bundle, Patient, QuestionnaireResponse, Observation, Condition } from 'fhir/r4';

/**
 * Input type for the MSC Skin Substitute Pre-Application Checklist
 * Aligned with the PHP DTO: App\Services\HealthData\DTO\SkinSubstituteChecklistInput
 */
export interface SkinSubstituteChecklistInput {
  // Patient Information from PHP DTO (though likely sourced from elsewhere in main form)
  patientName: string;
  dateOfBirth: string;
  dateOfProcedure: string;

  // Diagnosis from PHP DTO
  hasDiabetes: boolean;
  diabetesType?: '1' | '2'; // Nullable in PHP, optional here
  hasVenousStasisUlcer: boolean;
  hasPressureUlcer: boolean;
  pressureUlcerStage?: string | null;
  location: string; // Corresponds to PHP DTO's 'location' (right/left/bilateral for diagnosis context?)
  ulcerLocation: string; // Corresponds to PHP DTO's 'ulcerLocation' (specific site)

  // Lab Results from PHP DTO
  hba1cResult?: number | null;
  hba1cDate?: string | null;
  albuminResult?: number | null; // PHP DTO has albuminResult, checklist image had albumin_prealbumin_value
  albuminDate?: string | null;
  cbcPerformed?: boolean | null; // Was cbc_ordered, then cbc
  crapResult?: number | null; // Renamed from crp
  hematocritResult?: number | null; // Part of hh_value previously
  // hh_value from PHP DTO (string) needs clarification if it contains both H&H or just one.
  // For now, assuming hematocritResult is separate if available.
  hh?: string; // Kept for now if it's a combined string entry
  cultureDate?: string | null;
  sedRate?: number | null;
  treated: boolean; // Infection treated

  // Wound Description from PHP DTO
  depth: 'full-thickness' | 'partial-thickness'; // Wound depth type
  ulcerDuration: string; // Duration of this specific wound
  exposedStructures?: ('muscle' | 'tendon' | 'bone' | 'none')[]; // Allow empty array
  length: number; // Wound length
  width: number; // Wound width
  woundDepth: number; // Numeric wound depth
  hasInfection: boolean; // Was infectionEvidence
  hasNecroticTissue: boolean; // Was necroticTissue
  hasCharcotDeformity: boolean; // Was activeCharcot
  hasMalignancy: boolean; // Was suspectedMalignancy

  // General Wound Characteristics (previously merged into SSP_WoundDescriptionData, map to new DTO if fields exist)
  // These were: wagner_grade, tissue_type, exudate_amount, exudate_type, infection_signs
  // The PHP DTO for wound description does not explicitly list these.
  // They might be intended to be captured in a more generic way or within the QuestionnaireResponse.
  // For now, omitting them from direct properties here unless PHP DTO is updated.

  // Circulation Testing from PHP DTO
  abiResult?: number | null;
  abiDate?: string | null;
  pedalPulsesResult?: string | null;
  pedalPulsesDate?: string | null;
  tcpo2Result?: number | null;
  tcpo2Date?: string | null;
  hasTriphasicWaveforms: boolean;
  waveformResult?: string | null;
  waveformDate?: string | null;
  imagingType?: ('xray' | 'ct' | 'mri' | 'none') | null; // PHP DTO imagingType is nullable string

  // Conservative Treatment (Past 30 Days) from PHP DTO
  debridementPerformed: boolean;
  moistDressingsApplied: boolean;
  nonWeightBearing: boolean;
  pressureReducingFootwear: boolean; // This was a boolean in PHP DTO, not an object
  footwearType?: string | null; // Added to match PHP DTO if pressureReducingFootwear is true
  standardCompression: boolean; // Was compressionTherapy.used
  // PHP DTO doesn't have notApplicable for compression. Assumed covered by context or other fields.
  currentHbot: boolean;
  smokingStatus: 'smoker' | 'previous-smoker' | 'non-smoker';
  smokingCounselingProvided?: boolean | null;
  receivingRadiationOrChemo: boolean;
  takingImmuneModulators: boolean;
  hasAutoimmuneDiagnosis: boolean;
  pressureUlcerLeadingType?: 'bed' | 'wheelchair-cushion' | null; // PHP DTO is nullable string

  // Provider Information (from checklist bottom, likely handled separately in overall form submission)
  // providerSignature: string; // Renamed from provider.signature
  // providerSignatureDate: string; // Renamed from provider.date
}

// The SkinSubstituteChecklistMapper class definition follows...
// Its internal methods will need to be updated to use the new SkinSubstituteChecklistInput field names.

/**
 * Service class to convert MSC checklist data to FHIR Bundle
 * Uses HL7 Skin/Wound Assessment IG profiles where applicable
 */
export class SkinSubstituteChecklistMapper {

  mapToFhirBundle(
    checklistData: SkinSubstituteChecklistInput,
    patientFhirId?: string
  ): Bundle {
    const bundleId = this.generateId();
    const timestamp = new Date().toISOString();

    const bundle: Bundle = {
      resourceType: 'Bundle',
      id: bundleId,
      type: 'collection',
      timestamp,
      entry: []
    };

    // 1. Patient Resource - Use existing patientFhirId, or create from checklist
    let effectivePatientId = patientFhirId;
    if (!effectivePatientId && checklistData.patientName && checklistData.dateOfBirth) {
      const patient = this.createPatientResource(checklistData);
      bundle.entry!.push({
        fullUrl: `Patient/${patient.id}`,
        resource: patient
      });
      effectivePatientId = patient.id!;
    }

    if (!effectivePatientId) {
      console.error("Patient reference (FHIR ID) is required to create dependent FHIR resources.");
      return bundle;
    }

    // Pass effectivePatientId to all resource creation methods
    const conditions = this.createConditionResources(checklistData, effectivePatientId);
    conditions.forEach(condition => {
      if (condition.id) bundle.entry!.push({ fullUrl: `Condition/${condition.id}`, resource: condition });
    });

    const woundObservations = this.createWoundObservations(checklistData, effectivePatientId);
    woundObservations.forEach(obs => {
      if (obs.id) bundle.entry!.push({ fullUrl: `Observation/${obs.id}`, resource: obs });
    });

    const labObservations = this.createLabObservations(checklistData, effectivePatientId);
    labObservations.forEach(obs => {
      if (obs.id) bundle.entry!.push({ fullUrl: `Observation/${obs.id}`, resource: obs });
    });

    // Circulation data is now directly on checklistData, not nested under checklistData.circulation
    const circObservations = this.createCirculationObservations(checklistData, effectivePatientId);
    circObservations.forEach(obs => {
      if (obs.id) bundle.entry!.push({ fullUrl: `Observation/${obs.id}`, resource: obs });
    });

    const questionnaireResponse = this.createQuestionnaireResponse(checklistData, effectivePatientId);
    if (questionnaireResponse.id) bundle.entry!.push({ fullUrl: `QuestionnaireResponse/${questionnaireResponse.id}`, resource: questionnaireResponse });

    return bundle;
  }

  private createPatientResource(data: SkinSubstituteChecklistInput): Patient {
    const nameParts = data.patientName.trim().split(/\s+/);
    const family = nameParts.pop() || 'Unknown';
    const given = nameParts.length > 0 ? nameParts : ['Unknown'];

    return {
      resourceType: 'Patient',
      id: this.generateId(),
      name: [{ use: 'official', family, given }],
      birthDate: data.dateOfBirth,
      extension: [
        { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-patient-consent', valueBoolean: true },
        { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-platform-status', valueString: 'active' }
      ]
    };
  }

  private createConditionResources(data: SkinSubstituteChecklistInput, patientId: string): Condition[] {
    const conditions: Condition[] = [];
    const baseConditionProps = {
      resourceType: 'Condition' as 'Condition',
      clinicalStatus: { coding: [{ system: 'http://terminology.hl7.org/CodeSystem/condition-clinical', code: 'active' }] },
      verificationStatus: { coding: [{ system: 'http://terminology.hl7.org/CodeSystem/condition-ver-status', code: 'confirmed' }] },
      category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/condition-category', code: 'problem-list-item' }] }],
      subject: { reference: `Patient/${patientId}` },
      recordedDate: data.dateOfProcedure,
      extension: [] as any[],
      bodySite: data.ulcerLocation ? [{ text: data.ulcerLocation }] : undefined, // Use ulcerLocation for bodySite
    };

    if (data.hasDiabetes) {
      const diabetesCondition: Condition = {
        ...baseConditionProps,
        id: this.generateId(),
        code: {
          coding: [{
            system: 'http://hl7.org/fhir/sid/icd-10-cm',
            code: data.diabetesType === '1' ? 'E10.621' : 'E11.621',
            display: `Type ${data.diabetesType} diabetes mellitus with foot ulcer`
          }]
        },
        extension: [...baseConditionProps.extension, {
          url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type',
          valueString: 'DFU'
        }]
      };
      // Add laterality to bodySite if available (assuming data.location refers to laterality here)
      if (data.location && diabetesCondition.bodySite && diabetesCondition.bodySite[0]?.text) {
         diabetesCondition.bodySite[0].text = `${data.location} ${diabetesCondition.bodySite[0].text}`.trim();
      } else if (data.location) {
         diabetesCondition.bodySite = [{text: data.location}];
      }
      conditions.push(diabetesCondition);
    }
    if (data.hasVenousStasisUlcer) {
      conditions.push({
        ...baseConditionProps,
        id: this.generateId(),
        code: { coding: [{ system: 'http://hl7.org/fhir/sid/icd-10-cm', code: 'I87.2', display: 'Venous insufficiency (chronic) (peripheral)' }] },
        extension: [...baseConditionProps.extension, { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type', valueString: 'VLU' }]
      });
    }
    if (data.hasPressureUlcer) {
      const puCondition: Condition = {
        ...baseConditionProps,
        id: this.generateId(),
        code: { coding: [{ system: 'http://hl7.org/fhir/sid/icd-10-cm', code: 'L89.90', display: 'Pressure ulcer of unspecified site, unspecified stage' }] },
        extension: [...baseConditionProps.extension, { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-type', valueString: 'PU' }]
      };
      if (data.pressureUlcerStage) {
        puCondition.extension!.push({
          url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-wound-stage',
          valueString: `Stage ${data.pressureUlcerStage}`
        });
      }
      conditions.push(puCondition);
    }
    return conditions;
  }

  private createWoundObservations(data: SkinSubstituteChecklistInput, patientId: string): Observation[] {
    const observations: Observation[] = [];
    const effectiveDateTime = data.dateOfProcedure;
    // Use data.ulcerLocation for wound specific observations
    const bodySite = data.ulcerLocation ? [{ text: data.ulcerLocation }] : undefined;

    const woundSize: Observation = {
      resourceType: 'Observation',
      id: this.generateId(),
      meta: { profile: ['http://hl7.org/fhir/us/skin-wound-assessment/StructureDefinition/WoundSize'] },
      status: 'final',
      category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'exam' }] }],
      code: { coding: [{ system: 'http://loinc.org', code: '39125-6', display: 'Wound area' }] },
      subject: { reference: `Patient/${patientId}` },
      effectiveDateTime,
      bodySite,
      component: [
        { code: { coding: [{ system: 'http://loinc.org', code: '8341-0', display: 'Wound length' }] }, valueQuantity: { value: data.length, unit: 'cm', system: 'http://unitsofmeasure.org', code: 'cm' } },
        { code: { coding: [{ system: 'http://loinc.org', code: '8340-2', display: 'Wound width' }] }, valueQuantity: { value: data.width, unit: 'cm', system: 'http://unitsofmeasure.org', code: 'cm' } },
        { code: { coding: [{ system: 'http://loinc.org', code: '8333-7', display: 'Wound depth' }] }, valueQuantity: { value: data.woundDepth, unit: 'cm', system: 'http://unitsofmeasure.org', code: 'cm' } }
      ],
      // totalArea would be calculated: data.length * data.width
      valueQuantity: { value: data.length * data.width, unit: 'cm2', system: 'http://unitsofmeasure.org', code: 'cm2' }
    };
    observations.push(woundSize);

    const woundDepthObs: Observation = {
      resourceType: 'Observation', id: this.generateId(), status: 'final',
      category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'exam' }] }],
      code: { coding: [{ system: 'http://snomed.info/sct', code: '386053000', display: 'Wound depth' }] },
      subject: { reference: `Patient/${patientId}` }, effectiveDateTime, bodySite,
      valueCodeableConcept: { coding: [{
        system: 'http://snomed.info/sct',
        code: data.depth === 'full-thickness' ? '255554000' : '255536004',
        display: data.depth === 'full-thickness' ? 'Full thickness wound' : 'Partial thickness wound'
      }] }
    };
    observations.push(woundDepthObs);

    if (data.exposedStructures && data.exposedStructures.length > 0) {
      observations.push({
        resourceType: 'Observation', id: this.generateId(), status: 'final',
        category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'exam' }] }],
        code: { text: 'Exposed Structures' },
        subject: { reference: `Patient/${patientId}` }, effectiveDateTime, bodySite,
        component: data.exposedStructures.map(structure => ({
          code: { text: structure },
          valueBoolean: true
        }))
      });
    }
    return observations;
  }

  private createLabObservations(data: SkinSubstituteChecklistInput, patientId: string): Observation[] {
    const observations: Observation[] = [];
    if (data.hba1cResult !== null && data.hba1cResult !== undefined && data.hba1cDate) {
      observations.push({
        resourceType: 'Observation', id: this.generateId(), status: 'final',
        category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'laboratory' }] }],
        code: { coding: [{ system: 'http://loinc.org', code: '4548-4', display: 'Hemoglobin A1c/Hemoglobin.total in Blood' }] },
        subject: { reference: `Patient/${patientId}` }, effectiveDateTime: data.hba1cDate,
        valueQuantity: { value: data.hba1cResult, unit: '%', system: 'http://unitsofmeasure.org', code: '%' }
      });
    }
    if (data.albuminResult !== null && data.albuminResult !== undefined && data.albuminDate) {
      observations.push({
        resourceType: 'Observation', id: this.generateId(), status: 'final',
        category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'laboratory' }] }],
        code: { coding: [{ system: 'http://loinc.org', code: '1751-7', display: 'Albumin [Mass/volume] in Serum or Plasma' }] },
        subject: { reference: `Patient/${patientId}` }, effectiveDateTime: data.albuminDate,
        valueQuantity: { value: data.albuminResult, unit: 'g/dL', system: 'http://unitsofmeasure.org', code: 'g/dL' }
      });
    }
    return observations;
  }

  private createCirculationObservations(data: SkinSubstituteChecklistInput, patientId: string): Observation[] {
    const observations: Observation[] = [];
    if (data.abiResult !== null && data.abiResult !== undefined && data.abiDate) {
      observations.push({
        resourceType: 'Observation', id: this.generateId(), status: 'final',
        category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'exam' }] }],
        code: { coding: [{ system: 'http://loinc.org', code: '88073-4', display: 'Ankle brachial pressure index' }] },
        subject: { reference: `Patient/${patientId}` }, effectiveDateTime: data.abiDate,
        valueQuantity: { value: data.abiResult, system: 'http://unitsofmeasure.org', code: '1' }
      });
    }
    if (data.tcpo2Result !== null && data.tcpo2Result !== undefined && data.tcpo2Date) {
      observations.push({
        resourceType: 'Observation', id: this.generateId(), status: 'final',
        category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/observation-category', code: 'exam' }] }],
        code: { coding: [{ system: 'http://loinc.org', code: '19223-7', display: 'Transcutaneous oxygen measurement' }] },
        subject: { reference: `Patient/${patientId}` }, effectiveDateTime: data.tcpo2Date,
        valueQuantity: { value: data.tcpo2Result, unit: 'mmHg', system: 'http://unitsofmeasure.org', code: 'mm[Hg]' }
      });
    }
    return observations;
  }

  private createQuestionnaireResponse(data: SkinSubstituteChecklistInput, patientId: string): QuestionnaireResponse {
    const items: QuestionnaireResponse['item'] = [];

    items.push({linkId: 'patient-name', text: 'Patient Name', answer: [{valueString: data.patientName}]});
    items.push({linkId: 'date-of-birth', text: 'Date of Birth', answer: [{valueDate: data.dateOfBirth}]});
    items.push({linkId: 'date-of-procedure', text: 'Date of Procedure', answer: [{valueDate: data.dateOfProcedure}]});

    const diagnosisItems: QuestionnaireResponse['item'] = [];
    if(data.hasDiabetes !== undefined) diagnosisItems.push({linkId: 'diag-diabetes-present', text: 'Diabetes Present', answer: [{valueBoolean: data.hasDiabetes}]});
    if(data.diabetesType) diagnosisItems.push({linkId: 'diag-diabetes-type', text: 'Diabetes Type', answer: [{valueString: data.diabetesType}]});
    if(data.hasVenousStasisUlcer !== undefined) diagnosisItems.push({linkId: 'diag-vsu', text: 'Venous Stasis Ulcer', answer: [{valueBoolean: data.hasVenousStasisUlcer}]});
    if(data.hasPressureUlcer !== undefined) diagnosisItems.push({linkId: 'diag-pu-present', text: 'Pressure Ulcer Present', answer: [{valueBoolean: data.hasPressureUlcer}]});
    if(data.pressureUlcerStage) diagnosisItems.push({linkId: 'diag-pu-stage', text: 'Pressure Ulcer Stage', answer: [{valueString: data.pressureUlcerStage}]});
    if(data.location) diagnosisItems.push({linkId: 'diag-location', text: 'Diagnosis Location/Laterality', answer: [{valueString: data.location}]}); // General location/laterality for diagnosis
    if(diagnosisItems.length > 0) items.push({linkId: 'diagnosis-section', text: 'Diagnosis', item: diagnosisItems});

    const woundItems: QuestionnaireResponse['item'] = [];
    woundItems.push({linkId: 'wound-loc-specific', text: 'Specific Ulcer Location', answer: [{valueString: data.ulcerLocation}]});
    woundItems.push({linkId: 'wound-depth-type', text: 'Wound Depth Classification', answer: [{valueString: data.depth}]});
    if(data.ulcerDuration) woundItems.push({linkId: 'wound-duration', text: 'Ulcer Duration', answer: [{valueString: data.ulcerDuration}]});
    // ... Add all other checklist fields to QuestionnaireResponse items dynamically ...
    if(woundItems.length > 0) items.push({linkId: 'wound-section', text: 'Wound Description', item: woundItems});

    return {
      resourceType: 'QuestionnaireResponse',
      id: this.generateId(),
      questionnaire: 'Questionnaire/skin-substitute-preapp',
      status: 'completed',
      subject: { reference: `Patient/${patientId}` },
      authored: data.dateOfProcedure, // Or provider.date from checklist if available and more suitable
      author: { /* TODO: Reference to Practitioner or PractitionerRole */ },
      source: { reference: `Patient/${patientId}` },
      item: items,
      extension: [
        { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-order-checklist-type', valueString: 'skin-substitute-preapp' },
        { url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-order-checklist-version', valueString: 'v1.2' } // Updated version example
      ]
    };
  }

  private generateId(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
  }
}
