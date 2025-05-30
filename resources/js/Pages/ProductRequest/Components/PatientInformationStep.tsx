import React, { useState, useEffect } from 'react';
import { FiSearch, FiRefreshCw, FiAlertCircle } from 'react-icons/fi';
import EcwConnection from '@/Components/EcwIntegration/EcwConnection';
import { Facility, WoundType } from '@/types';
import { ECWAdapter } from '@/services/integrations/ECWAdapter';

// Define PatientApiInput locally, matching Create.tsx, if not easily importable
// This should ideally be shared from Create.tsx or a common types file.
interface PatientApiInput {
  first_name: string;
  last_name: string;
  dob: string;
  gender?: 'male' | 'female' | 'other' | 'unknown'; // Changed to optional
  member_id?: string; // Changed to optional
  id?: string;
}

// This is the structure of the formData prop passed from Create.tsx
// We'll use `any` for props.formData for now to avoid type import complexities,
// but treat it as if it has this shape.
interface ParentFormData {
  patient_api_input: PatientApiInput;
  facility_id: number | null;
  expected_service_date: string;
  payer_name: string;
  payer_id: string;
  wound_type: string;
  // other fields from Create.tsx FormData might be present
  [key: string]: any; // Allow other fields
}

interface PatientInformationStepProps {
  formData: ParentFormData; // Represents FormData from Create.tsx
  updateFormData: (data: Partial<ParentFormData>) => void;
  woundTypes: WoundType[];
  facilities: Facility[];
}

interface EcwCondition {
  id: string;
  resourceType: string;
  code: {
    coding: Array<{
      system: string;
      code: string;
      display: string;
    }>;
  };
  clinicalStatus: {
    coding: Array<{
      code: string;
      display: string;
    }>;
  };
}

const PatientInformationStep: React.FC<PatientInformationStepProps> = ({
  formData,
  updateFormData,
  woundTypes,
  facilities,
}) => {
  const [ecwConnected, setEcwConnected] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState<PatientApiInput[]>([]);
  const [selectedPatient, setSelectedPatient] = useState<PatientApiInput | null>(null);
  const [patientConditions, setPatientConditions] = useState<EcwCondition[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ecwAdapter = new ECWAdapter();

  const handlePatientDemographicsChange = (demographicsUpdate: Partial<PatientApiInput>) => {
    updateFormData({
      patient_api_input: {
        ...formData.patient_api_input,
        ...demographicsUpdate,
      },
    });
  };

  const searchPatients = async () => {
    if (!searchTerm.trim()) {
      setSearchResults([]);
      return;
    }
    try {
      setLoading(true);
      setError(null);
      // ECWAdapter.searchPatientsByName returns PatientFormData[] which needs mapping
      // to ensure gender values match PatientApiInput requirements
      const rawResults = await ecwAdapter.searchPatientsByName(searchTerm);
      const results: PatientApiInput[] = rawResults.map(patient => ({
        ...patient,
        gender: patient.gender as "male" | "female" | "other" | "unknown" | undefined
      }));
      setSearchResults(results);
    } catch (err: any) {
      setError(err.message || 'Failed to search patients');
      console.error('Patient search failed:', err);
      if (err.message && err.message.includes('401')) {
        setEcwConnected(false);
      }
    } finally {
      setLoading(false);
    }
  };

  const fetchPatientConditions = async (patientId: string) => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`/api/ecw/patients/${patientId}/conditions`, {
        headers: {
          'Accept': 'application/fhir+json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ message: 'Failed to fetch conditions, server response unreadable' }));
        throw new Error(errorData.message || `Failed to fetch conditions. Status: ${response.status}`);
      }
      const data = await response.json();
      setPatientConditions(data.entry?.map((e: any) => e.resource) || []);
    } catch (err:any) {
      setError(err.message || 'Failed to fetch patient conditions');
      console.error('Conditions fetch failed:', err);
    } finally {
      setLoading(false);
    }
  };

  const selectPatient = async (patient: PatientApiInput) => {
    setSelectedPatient(patient);
    // Directly update patient_api_input in the parent's formData
    updateFormData({
      patient_api_input: patient,
    });

    if (patient.id) {
      await fetchPatientConditions(patient.id);
    } else {
      console.warn('Selected patient does not have an ID to fetch conditions.');
      setPatientConditions([]);
    }
  };

  // Generic handler for top-level formData fields specific to this step in Create.tsx
  const handleFieldChange = (fieldName: keyof ParentFormData, value: any) => {
    updateFormData({
      [fieldName]: value,
    } as Partial<ParentFormData>);
  };

  return (
    <div className="space-y-6">
      <div className="bg-white shadow sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Patient Information
          </h3>

          {/* eCW Connection Status */}
          <div className="mt-4">
            <EcwConnection onConnectionChange={setEcwConnected} />
          </div>

          {ecwConnected && (
            <>
              {/* Patient Search */}
              <div className="mt-6">
                <div className="flex gap-4">
                  <div className="flex-grow">
                    <input
                      type="text"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      placeholder="Search patient by name..."
                      className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                  </div>
                  <button
                    type="button"
                    onClick={searchPatients}
                    disabled={loading}
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                  >
                    {loading ? (
                      <FiRefreshCw className="animate-spin h-5 w-5" />
                    ) : (
                      <FiSearch className="h-5 w-5" />
                    )}
                    <span className="ml-2">Search</span>
                  </button>
                </div>

                {/* Search Results */}
                {searchResults.length > 0 && (
                  <div className="mt-4">
                    <h4 className="text-sm font-medium text-gray-900">Search Results</h4>
                    <ul className="mt-2 divide-y divide-gray-200">
                      {searchResults.map((patient, index) => (
                        <li
                          key={patient.id || index} // Use patient.id if available, otherwise index as fallback
                          className="py-3 flex justify-between items-center hover:bg-gray-50 cursor-pointer"
                          onClick={() => selectPatient(patient)}
                        >
                          <div>
                            <p className="text-sm font-medium text-gray-900">
                              {patient.first_name} {patient.last_name}
                            </p>
                            <p className="text-sm text-gray-500">
                              DOB: {patient.dob} {patient.member_id ? `• ID: ${patient.member_id}` : ''}
                            </p>
                          </div>
                          <button
                            type="button"
                            className="ml-4 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-full text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                          >
                            Select
                          </button>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {/* Error Message Handling remains the same */}
                {error && (
                  <div className="mt-4 bg-red-50 border border-red-200 rounded-md p-4">
                    <div className="flex">
                      <FiAlertCircle className="h-5 w-5 text-red-400" />
                      <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">Error</h3>
                        <div className="mt-2 text-sm text-red-700">
                          <p>{error}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Selected Patient Information (displays data from formData.patient_api_input if selectedPatient is set) */}
              {selectedPatient && (
                <div className="mt-6">
                  <h4 className="text-sm font-medium text-gray-900">Selected Patient Details</h4>
                  <div className="mt-2 bg-gray-50 p-4 rounded-md">
                    <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Name</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                          {formData.patient_api_input.first_name} {formData.patient_api_input.last_name}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
                        <dd className="mt-1 text-sm text-gray-900">{formData.patient_api_input.dob}</dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Gender</dt>
                        <dd className="mt-1 text-sm text-gray-900 capitalize">{formData.patient_api_input.gender}</dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Member ID</dt>
                        <dd className="mt-1 text-sm text-gray-900">{formData.patient_api_input.member_id}</dd>
                      </div>
                      {formData.patient_api_input.id && (
                         <div>
                           <dt className="text-sm font-medium text-gray-500">eCW ID</dt>
                           <dd className="mt-1 text-sm text-gray-900">{formData.patient_api_input.id}</dd>
                         </div>
                       )}
                    </dl>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Manual Patient Data Input / Verification */}
      <div className="bg-white shadow sm:rounded-lg mt-6">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            {selectedPatient ? 'Verify or Update Patient Details' : 'Enter Patient Details Manually'}
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* First Name */}
            <div className="sm:col-span-3">
              <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                First Name
              </label>
              <input
                type="text"
                id="first-name"
                value={formData.patient_api_input.first_name || ''}
                onChange={(e) => handlePatientDemographicsChange({ first_name: e.target.value })}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* Last Name */}
            <div className="sm:col-span-3">
              <label htmlFor="last-name" className="block text-sm font-medium text-gray-700">
                Last Name
              </label>
              <input
                type="text"
                id="last-name"
                value={formData.patient_api_input.last_name || ''}
                onChange={(e) => handlePatientDemographicsChange({ last_name: e.target.value })}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* DOB */}
            <div className="sm:col-span-3">
              <label htmlFor="dob" className="block text-sm font-medium text-gray-700">
                Date of Birth
              </label>
              <input
                type="date"
                id="dob"
                value={formData.patient_api_input.dob || ''}
                onChange={(e) => handlePatientDemographicsChange({ dob: e.target.value })}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* Gender */}
            <div className="sm:col-span-3">
              <label htmlFor="gender" className="block text-sm font-medium text-gray-700">
                Gender
              </label>
              <select
                id="gender"
                value={formData.patient_api_input.gender || 'unknown'} // Default to 'unknown' or handle no selection
                onChange={(e) => handlePatientDemographicsChange({ gender: e.target.value as PatientApiInput['gender'] })}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
                <option value="unknown">Unknown</option>
              </select>
            </div>
            {/* Member ID */}
            <div className="sm:col-span-3">
              <label htmlFor="member_id" className="block text-sm font-medium text-gray-700">
                Member ID (Insurance)
              </label>
              <input
                type="text"
                id="member_id"
                value={formData.patient_api_input.member_id || ''}
                onChange={(e) => handlePatientDemographicsChange({ member_id: e.target.value })}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
             {/* eCW ID (if exists, display only, not editable here as it comes from selection) */}
            {formData.patient_api_input.id && (
              <div className="sm:col-span-3">
                <label htmlFor="ecw_id" className="block text-sm font-medium text-gray-700">
                  eCW ID
                </label>
                <input
                  type="text"
                  id="ecw_id"
                  value={formData.patient_api_input.id}
                  readOnly
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-gray-100"
                />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Service & Payer Information */}
      <div className="bg-white shadow sm:rounded-lg mt-6">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Service & Payer Information
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* Facility */}
            <div className="sm:col-span-3">
              <label htmlFor="facility_id" className="block text-sm font-medium text-gray-700">
                Facility
              </label>
              <select
                id="facility_id"
                value={formData.facility_id || ''}
                onChange={(e) => handleFieldChange('facility_id', e.target.value ? parseInt(e.target.value) : null)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Select Facility</option>
                {facilities.map((facility) => (
                  <option key={facility.id} value={facility.id}>
                    {facility.name}
                  </option>
                ))}
              </select>
            </div>
            {/* Expected Service Date */}
            <div className="sm:col-span-3">
              <label htmlFor="expected_service_date" className="block text-sm font-medium text-gray-700">
                Expected Service Date
              </label>
              <input
                type="date"
                id="expected_service_date"
                value={formData.expected_service_date || ''}
                onChange={(e) => handleFieldChange('expected_service_date', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* Payer Name */}
            <div className="sm:col-span-3">
              <label htmlFor="payer_name" className="block text-sm font-medium text-gray-700">
                Payer Name
              </label>
              <input
                type="text"
                id="payer_name"
                value={formData.payer_name || ''}
                onChange={(e) => handleFieldChange('payer_name', e.target.value)}
                placeholder="e.g., Medicare, Aetna"
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* Payer ID */}
            <div className="sm:col-span-3">
              <label htmlFor="payer_id" className="block text-sm font-medium text-gray-700">
                Payer ID
              </label>
              <input
                type="text"
                id="payer_id"
                value={formData.payer_id || ''}
                onChange={(e) => handleFieldChange('payer_id', e.target.value)}
                placeholder="Payer-specific ID"
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
            {/* Wound Type */}
            <div className="sm:col-span-3">
              <label htmlFor="wound_type" className="block text-sm font-medium text-gray-700">
                Wound Type
              </label>
              <select
                id="wound_type"
                value={formData.wound_type || ''}
                onChange={(e) => handleFieldChange('wound_type', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Select Wound Type</option>
                {woundTypes.map((wt) => (
                  <option key={wt.code} value={wt.code}>
                    {wt.display_name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>
      {/* Conditions Display - if any */}
      {patientConditions.length > 0 && (
        <div className="bg-white shadow sm:rounded-lg mt-6">
          <div className="px-4 py-5 sm:p-6">
            <h3 className="text-lg font-medium leading-6 text-gray-900">Patient Conditions (from eCW)</h3>
            <ul className="mt-4 divide-y divide-gray-200">
              {patientConditions.map((condition) => (
                <li key={condition.id} className="py-3">
                  <p className="text-sm font-medium text-gray-900">
                    {condition.code?.coding?.[0]?.display || 'N/A'} (Code: {condition.code?.coding?.[0]?.code || 'N/A'})
                  </p>
                  <p className="text-sm text-gray-500">
                    Status: {condition.clinicalStatus?.coding?.[0]?.display || 'N/A'}
                  </p>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
};

export default PatientInformationStep;
