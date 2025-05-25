import React, { useState, useEffect } from 'react';
import { FiSearch, FiRefreshCw, FiAlertCircle } from 'react-icons/fi';
import EcwConnection from '@/Components/EcwIntegration/EcwConnection';
import { Facility, WoundType } from '@/types';

interface PatientInformationStepProps {
  formData: any;
  updateFormData: (data: any) => void;
  updatePatientData: (data: any) => void;
  woundTypes: WoundType[];
  facilities: Facility[];
}

interface EcwPatient {
  id: string;
  resourceType: string;
  name: [{
    given: string[];
    family: string;
  }];
  birthDate: string;
  gender: string;
  identifier: Array<{
    system: string;
    value: string;
  }>;
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
  updatePatientData,
  woundTypes,
  facilities
}) => {
  const [ecwConnected, setEcwConnected] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState<EcwPatient[]>([]);
  const [selectedPatient, setSelectedPatient] = useState<EcwPatient | null>(null);
  const [patientConditions, setPatientConditions] = useState<EcwCondition[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Search patients in eCW
  const searchPatients = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch(`/api/ecw/patients/search?name=${encodeURIComponent(searchTerm)}`, {
        headers: {
          'Accept': 'application/fhir+json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (response.status === 401) {
        setEcwConnected(false);
        return;
      }

      const data = await response.json();
      setSearchResults(data.entry?.map((e: any) => e.resource) || []);
    } catch (err) {
      setError('Failed to search patients');
      console.error('Patient search failed:', err);
    } finally {
      setLoading(false);
    }
  };

  // Fetch patient conditions from eCW
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
        throw new Error('Failed to fetch conditions');
      }

      const data = await response.json();
      setPatientConditions(data.entry?.map((e: any) => e.resource) || []);
    } catch (err) {
      setError('Failed to fetch patient conditions');
      console.error('Conditions fetch failed:', err);
    } finally {
      setLoading(false);
    }
  };

  // Handle patient selection
  const selectPatient = async (patient: EcwPatient) => {
    setSelectedPatient(patient);

    // Update form data with patient demographics
    updatePatientData({
      first_name: patient.name[0]?.given?.[0] || '',
      last_name: patient.name[0]?.family || '',
      dob: patient.birthDate,
      member_id: patient.identifier?.find(i => i.system === 'urn:oid:2.16.840.1.113883.3.4')?.value || '',
      gender: patient.gender
    });

    // Fetch patient's conditions
    await fetchPatientConditions(patient.id);
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
                      {searchResults.map((patient) => (
                        <li
                          key={patient.id}
                          className="py-3 flex justify-between items-center hover:bg-gray-50 cursor-pointer"
                          onClick={() => selectPatient(patient)}
                        >
                          <div>
                            <p className="text-sm font-medium text-gray-900">
                              {patient.name[0]?.given?.join(' ')} {patient.name[0]?.family}
                            </p>
                            <p className="text-sm text-gray-500">
                              DOB: {patient.birthDate} • ID: {patient.identifier?.[0]?.value}
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

                {/* Error Message */}
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

              {/* Selected Patient Information */}
              {selectedPatient && (
                <div className="mt-6">
                  <h4 className="text-sm font-medium text-gray-900">Selected Patient</h4>
                  <div className="mt-2 bg-gray-50 p-4 rounded-md">
                    <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Name</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                          {selectedPatient.name[0]?.given?.join(' ')} {selectedPatient.name[0]?.family}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
                        <dd className="mt-1 text-sm text-gray-900">{selectedPatient.birthDate}</dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Member ID</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                          {selectedPatient.identifier?.[0]?.value}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Gender</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                          {selectedPatient.gender?.charAt(0).toUpperCase() + selectedPatient.gender?.slice(1)}
                        </dd>
                      </div>
                    </dl>
                  </div>
                </div>
              )}

              {/* Patient Conditions */}
              {patientConditions.length > 0 && (
                <div className="mt-6">
                  <h4 className="text-sm font-medium text-gray-900">Problem List</h4>
                  <div className="mt-2">
                    <ul className="divide-y divide-gray-200">
                      {patientConditions.map((condition) => (
                        <li key={condition.id} className="py-3">
                          <div className="flex items-start">
                            <div className="flex-grow">
                              <p className="text-sm font-medium text-gray-900">
                                {condition.code.coding[0]?.display}
                              </p>
                              <p className="text-sm text-gray-500">
                                Status: {condition.clinicalStatus.coding[0]?.display}
                              </p>
                            </div>
                            <div className="ml-4">
                              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {condition.code.coding[0]?.code}
                              </span>
                            </div>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
              )}
            </>
          )}

          {/* Manual Entry Form (shown when eCW is not connected) */}
          {!ecwConnected && (
            <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
              <div className="sm:col-span-3">
                <label htmlFor="first_name" className="block text-sm font-medium text-gray-700">
                  First name
                </label>
                <div className="mt-1">
                  <input
                    type="text"
                    name="first_name"
                    id="first_name"
                    value={formData.patient_api_input.first_name}
                    onChange={(e) => updatePatientData({ first_name: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                </div>
              </div>

              <div className="sm:col-span-3">
                <label htmlFor="last_name" className="block text-sm font-medium text-gray-700">
                  Last name
                </label>
                <div className="mt-1">
                  <input
                    type="text"
                    name="last_name"
                    id="last_name"
                    value={formData.patient_api_input.last_name}
                    onChange={(e) => updatePatientData({ last_name: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                </div>
              </div>

              <div className="sm:col-span-3">
                <label htmlFor="dob" className="block text-sm font-medium text-gray-700">
                  Date of birth
                </label>
                <div className="mt-1">
                  <input
                    type="date"
                    name="dob"
                    id="dob"
                    value={formData.patient_api_input.dob}
                    onChange={(e) => updatePatientData({ dob: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                </div>
              </div>

              <div className="sm:col-span-3">
                <label htmlFor="member_id" className="block text-sm font-medium text-gray-700">
                  Member ID
                </label>
                <div className="mt-1">
                  <input
                    type="text"
                    name="member_id"
                    id="member_id"
                    value={formData.patient_api_input.member_id}
                    onChange={(e) => updatePatientData({ member_id: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Common Fields (shown regardless of eCW connection) */}
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            <div className="sm:col-span-3">
              <label htmlFor="facility_id" className="block text-sm font-medium text-gray-700">
                Facility
              </label>
              <div className="mt-1">
                <select
                  id="facility_id"
                  name="facility_id"
                  value={formData.facility_id}
                  onChange={(e) => updateFormData({ facility_id: e.target.value })}
                  className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                  <option value="">Select a facility</option>
                  {facilities.map((facility) => (
                    <option key={facility.id} value={facility.id}>
                      {facility.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="sm:col-span-3">
              <label htmlFor="wound_type" className="block text-sm font-medium text-gray-700">
                Wound Type
              </label>
              <div className="mt-1">
                <select
                  id="wound_type"
                  name="wound_type"
                  value={formData.wound_type}
                  onChange={(e) => updateFormData({ wound_type: e.target.value })}
                  className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                  <option value="">Select wound type</option>
                  {woundTypes.map((type) => (
                    <option key={type.id} value={type.id}>
                      {type.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="sm:col-span-3">
              <label htmlFor="expected_service_date" className="block text-sm font-medium text-gray-700">
                Expected Service Date
              </label>
              <div className="mt-1">
                <input
                  type="date"
                  name="expected_service_date"
                  id="expected_service_date"
                  value={formData.expected_service_date}
                  onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
                  className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
              </div>
            </div>

            <div className="sm:col-span-3">
              <label htmlFor="payer_name" className="block text-sm font-medium text-gray-700">
                Payer Name
              </label>
              <div className="mt-1">
                <input
                  type="text"
                  name="payer_name"
                  id="payer_name"
                  value={formData.payer_name}
                  onChange={(e) => updateFormData({ payer_name: e.target.value })}
                  className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PatientInformationStep;
