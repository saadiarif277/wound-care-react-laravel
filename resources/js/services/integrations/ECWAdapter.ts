import { PatientFormData } from './FHIRAdapter'; // Import the correct type

// Placeholder types - these would typically come from SDKs or be more fleshed out

interface ClinicalData {
  // Define based on expected clinical data structure from ECW, returned by Laravel backend
  [key: string]: any;
}

// Base URL for your Laravel backend API. This should ideally come from an environment variable.
// For Inertia.js, calls are typically made to the same domain, so relative paths are common.
const LARAVEL_API_BASE_URL = '/api/v1'; // Adjust if your API routes have a different prefix

export class ECWAdapter {
  // Constructor can be used for injecting a pre-configured HTTP client if needed
  constructor() {}

  // This transformation might still be useful if the data from Laravel API
  // needs shaping before being used as PatientFormData in the UI.
  private transformECWApiResponseToFormData(ecwData: any): PatientFormData {
    console.log('Transforming ECW API response to FormData:', ecwData);
    // Actual transformation logic here if needed
    return ecwData as PatientFormData; // Assuming Laravel API returns data compatible with PatientFormData
  }

  async importPatientData(ecwPatientId: string): Promise<PatientFormData> {
    // Assumes an endpoint like GET /api/v1/ecw/patient/{ecwPatientId}
    // which is handled by EcwController.php's getPatient method.
    const response = await fetch(`${LARAVEL_API_BASE_URL}/ecw/patient/${ecwPatientId}`, {
      headers: {
        'Accept': 'application/json',
        // Add X-CSRF-TOKEN header for Laravel if needed
        // Add any necessary auth headers (e.g., for user-specific eCW token)
      },
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error importing patient data from ECW: ${errorData.message || response.statusText}`);
    }

    const ecwPatientData = await response.json();
    return this.transformECWApiResponseToFormData(ecwPatientData);
  }

  // This transformation might be useful if Laravel API returns raw clinical documents
  // that need processing to extract specific wound assessment data.
  private extractWoundAssessmentDataFromApiResponse(documentation: any): ClinicalData {
    console.log('Extracting wound assessment data from ECW API response:', documentation);
    // Actual extraction logic here, e.g., finding specific document sections or observations
    // return { woundDetails: documentation.find(doc => doc.type === 'wound-assessment')?.text || '' };
    return documentation as ClinicalData; // Assuming Laravel API returns data compatible with ClinicalData
  }

  async importClinicalDocumentation(ecwPatientId: string): Promise<ClinicalData> {
    // Assumes an endpoint like GET /api/v1/ecw/patient/{ecwPatientId}/documents?documentType=wound-assessment
    // which is handled by EcwController.php's getPatientDocuments method.
    const response = await fetch(
      `${LARAVEL_API_BASE_URL}/ecw/patient/${ecwPatientId}/documents?documentType=wound-assessment`,
      {
        headers: {
          'Accept': 'application/json',
          // Add X-CSRF-TOKEN header for Laravel if needed
          // Add any necessary auth headers
        },
      }
    );

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error importing clinical documentation from ECW: ${errorData.message || response.statusText}`);
    }

    const ecwClinicalDocs = await response.json();
    return this.extractWoundAssessmentDataFromApiResponse(ecwClinicalDocs);
  }

  private transformECWSearchApiResponseToPatientFormDataArray(apiResponse: any): PatientFormData[] {
    console.log('Transforming ECW search API response to PatientFormData[]:', apiResponse);
    // Assuming the API returns a FHIR bundle-like structure or an array of patient-like objects
    const patients = apiResponse?.entry?.map((e: any) => e.resource) || apiResponse || [];
    return patients.map((patient: any) => ({
      first_name: patient.name?.[0]?.given?.[0] || '',
      last_name: patient.name?.[0]?.family || '',
      dob: patient.birthDate || '',
      member_id: patient.identifier?.find((i: any) => i.system === 'urn:oid:2.16.840.1.113883.3.4')?.value || // Example system for member_id
                 patient.identifier?.[0]?.value || '', // Fallback to first identifier value
      gender: patient.gender || '',
      // Include ecwId or fhirId if available and needed for subsequent fetches
      id: patient.id, // Assuming the patient object from search has an id
      // Add any other relevant fields from the search result that map to PatientFormData
    }));
  }

  async searchPatientsByName(name: string): Promise<PatientFormData[]> {
    const response = await fetch(`${LARAVEL_API_BASE_URL}/ecw/patients/search?name=${encodeURIComponent(name)}`, {
      headers: {
        'Accept': 'application/fhir+json', // Or application/json depending on your backend
        'X-Requested-With': 'XMLHttpRequest',
        // Add X-CSRF-TOKEN header for Laravel if needed
        // Add any necessary auth headers
      },
    });

    if (!response.ok) {
      // Consider specific error handling for 401 (eCW not connected)
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error searching patients in ECW: ${errorData.message || response.statusText}`);
    }

    const searchData = await response.json();
    return this.transformECWSearchApiResponseToPatientFormDataArray(searchData);
  }
}
