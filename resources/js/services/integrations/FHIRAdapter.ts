// Placeholder types - these would typically come from SDKs or be more fleshed out
export interface PatientFormData {
  first_name: string;
  last_name: string;
  dob: string; // Assuming YYYY-MM-DD format
  member_id?: string; // Optional as it might not always be available
  gender?: string;
  id?: string; // Potentially from eCW search (their internal ID) or FHIR resource ID after creation
  // Add other fields as they are identified from the form
  // [key: string]: any; // Removed for more specific typing
}

// --- START OF NEW FHIR BASE TYPES ---
interface BaseFHIRCodeableConcept {
  coding?: Array<{
    system?: string;
    version?: string;
    code?: string;
    display?: string;
    userSelected?: boolean;
  }>;
  text?: string;
}

interface BaseFHIRReference {
  reference?: string;
  type?: string;
  display?: string;
}

interface BaseFHIRIdentifier {
  use?: 'usual' | 'official' | 'temp' | 'secondary' | 'old';
  type?: BaseFHIRCodeableConcept;
  system?: string;
  value?: string;
}

interface BaseFHIRHumanName {
  use?: 'usual' | 'official' | 'temp' | 'nickname' | 'anonymous' | 'old' | 'maiden';
  text?: string;
  family?: string;
  given?: string[];
  prefix?: string[];
  suffix?: string[];
}

interface BaseFHIRContactPoint {
  system?: 'phone' | 'fax' | 'email' | 'pager' | 'url' | 'sms' | 'other';
  value?: string;
  use?: 'home' | 'work' | 'temp' | 'old' | 'mobile';
  rank?: number;
}

interface BaseFHIRAddress {
  use?: 'home' | 'work' | 'temp' | 'old' | 'billing';
  type?: 'postal' | 'physical' | 'both';
  text?: string;
  line?: string[];
  city?: string;
  district?: string;
  state?: string;
  postalCode?: string;
  country?: string;
}

interface BaseFHIRPatientResource {
  resourceType: 'Patient';
  id?: string;
  identifier?: BaseFHIRIdentifier[];
  active?: boolean;
  name?: BaseFHIRHumanName[];
  telecom?: BaseFHIRContactPoint[];
  gender?: 'male' | 'female' | 'other' | 'unknown';
  birthDate?: string;
  deceasedBoolean?: boolean;
  deceasedDateTime?: string;
  address?: BaseFHIRAddress[];
  maritalStatus?: BaseFHIRCodeableConcept;
  multipleBirthBoolean?: boolean;
  multipleBirthInteger?: number;
  contact?: Array<{
    relationship?: BaseFHIRCodeableConcept[];
    name?: BaseFHIRHumanName;
    telecom?: BaseFHIRContactPoint[];
    address?: BaseFHIRAddress;
    gender?: 'male' | 'female' | 'other' | 'unknown';
    organization?: BaseFHIRReference;
    period?: { start?: string; end?: string; };
  }>;
  communication?: Array<{
    language: BaseFHIRCodeableConcept;
    preferred?: boolean;
  }>;
  generalPractitioner?: BaseFHIRReference[];
  managingOrganization?: BaseFHIRReference;
  link?: Array<{
    other: BaseFHIRReference;
    type: 'replaced-by' | 'replaces' | 'refer' | 'seealso';
  }>;
}

interface BaseFHIRObservationResource {
  resourceType: 'Observation';
  id?: string;
  identifier?: BaseFHIRIdentifier[];
  status: 'registered' | 'preliminary' | 'final' | 'amended' | 'corrected' | 'cancelled' | 'entered-in-error' | 'unknown';
  category?: BaseFHIRCodeableConcept[];
  code: BaseFHIRCodeableConcept;
  subject?: BaseFHIRReference;
  focus?: BaseFHIRReference[];
  encounter?: BaseFHIRReference;
  effectiveDateTime?: string;
  effectivePeriod?: { start?: string; end?: string; };
  effectiveTiming?: any;
  effectiveInstant?: string;
  issued?: string;
  performer?: BaseFHIRReference[];
  valueQuantity?: { value?: number; unit?: string; system?: string; code?: string; };
  valueCodeableConcept?: BaseFHIRCodeableConcept;
  valueString?: string;
  valueBoolean?: boolean;
  valueInteger?: number;
  valueRange?: { low?: {value?: number; unit?: string;}; high?: {value?: number; unit?: string;}; };
  valueRatio?: { numerator?: {value?: number; unit?: string;}; denominator?: {value?: number; unit?: string;}; };
  valueSampledData?: any;
  valueTime?: string;
  valueDateTime?: string;
  valuePeriod?: { start?: string; end?: string; };
  dataAbsentReason?: BaseFHIRCodeableConcept;
  interpretation?: BaseFHIRCodeableConcept[];
  note?: Array<{
    authorReference?: BaseFHIRReference;
    authorString?: string;
    time?: string;
    text: string;
  }>;
  bodySite?: BaseFHIRCodeableConcept;
  method?: BaseFHIRCodeableConcept;
  referenceRange?: Array<{
    low?: {value?: number; unit?: string;};
    high?: {value?: number; unit?: string;};
    type?: BaseFHIRCodeableConcept;
    appliesTo?: BaseFHIRCodeableConcept[];
    age?: { low?: {value?: number; unit?: string;}; high?: {value?: number; unit?: string;}; };
    text?: string;
  }>;
  hasMember?: BaseFHIRReference[];
  derivedFrom?: BaseFHIRReference[];
  component?: Array<{
    code: BaseFHIRCodeableConcept;
    valueQuantity?: { value?: number; unit?: string; system?: string; code?: string; };
    valueCodeableConcept?: BaseFHIRCodeableConcept;
    dataAbsentReason?: BaseFHIRCodeableConcept;
    interpretation?: BaseFHIRCodeableConcept[];
    referenceRange?: any[];
  }>;
}
// --- END OF NEW FHIR BASE TYPES ---

interface FHIRPatient {
  fhirId: string;
  resource: BaseFHIRPatientResource; // FHIR Patient Resource returned by Laravel backend
}

// type ClinicalAssessment = BaseFHIRObservationResource;
// Define based on expected assessment structure returned by Laravel backend
// For now, keeping it flexible as the backend might return a simplified or specific structure
// rather than a full FHIR Observation bundle entry directly for each assessment.
// If the backend returns full FHIR Observation resources, then ClinicalAssessment = BaseFHIRObservationResource is appropriate.
interface ClinicalAssessment {
  [key: string]: any;
}

// Base URL for your Laravel backend API. This should ideally come from an environment variable.
// For Inertia.js, calls are typically made to the same domain, so relative paths are common.
const LARAVEL_API_BASE_URL = '/api/v1'; // Adjust if your API routes have a different prefix

export class FHIRAdapter {
  // Constructor can be used for injecting a pre-configured HTTP client if needed
  constructor() {}

  // This transformation might still be useful if the frontend form data
  // needs shaping before sending to the Laravel backend.
  // Or, Laravel might expect raw form data.
  private transformToFHIRPatientAPIPayload(patientData: PatientFormData): any {
    console.log('Transforming PatientFormData to API payload:', patientData);
    // Actual transformation logic here if needed, or just return patientData
    return patientData; // Assuming Laravel API will handle FHIR conversion
  }

  async createPatientResource(patientData: PatientFormData): Promise<FHIRPatient> {
    const payload = this.transformToFHIRPatientAPIPayload(patientData);

    // In Inertia, you might use its routing or a global fetch instance
    const response = await fetch(`${LARAVEL_API_BASE_URL}/fhir/patient`, { // Matches FhirController.php route
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add X-CSRF-TOKEN header for Laravel if not handled automatically by your HTTP client
        // 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
      },
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error creating patient: ${errorData.message || response.statusText}`);
    }

    const fhirPatientResource = await response.json();
    // Assuming Laravel returns the created FHIR patient resource with its ID
    return {
      fhirId: fhirPatientResource.id, // Adjust if ID is in a different property
      resource: fhirPatientResource,
    };
  }

  // This transformation might be useful if Laravel returns a FHIR bundle
  // that needs to be processed into a simpler ClinicalAssessment[] array on the frontend.
  // Or, Laravel API might return the already transformed assessments.
  private transformAPIResponseToAssessments(apiResponse: any): ClinicalAssessment[] {
    console.log('Transforming API response to assessments:', apiResponse);
    // If Laravel returns a FHIR bundle (e.g., from searchPatients in FhirService.php):
    // return apiResponse?.entry?.map((entry: any) => entry.resource as ClinicalAssessment) || [];
    // If Laravel API for clinical data returns already transformed assessments or a specific structure:
    return apiResponse as ClinicalAssessment[]; // Adjust based on actual API response
  }

  async retrieveClinicalData(patientFhirId: string): Promise<ClinicalAssessment[]> {
    // This needs a corresponding endpoint in Laravel's FhirController or a new specific controller
    // to fetch Observations categorized as 'wound-assessment' for a patient.
    // Example: GET /api/v1/fhir/patient/{patientFhirId}/wound-assessments
    // Or: GET /api/v1/fhir/observation?patient={patientFhirId}&category=wound-assessment
    const response = await fetch(
      `${LARAVEL_API_BASE_URL}/fhir/observation?patient=${patientFhirId}&category=wound-assessment`,
      {
        headers: {
          'Accept': 'application/json',
          // Add X-CSRF-TOKEN header for Laravel if needed
        },
      }
    );

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error retrieving clinical data: ${errorData.message || response.statusText}`);
    }

    const data = await response.json();
    // The searchPatients in FhirController returns a FHIR bundle.
    // So, if that endpoint is used, transformation is needed.
    return this.transformAPIResponseToAssessments(data?.entry?.map((e: any) => e.resource) || []);
  }
}
