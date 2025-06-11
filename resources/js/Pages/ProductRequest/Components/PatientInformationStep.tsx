import React, { useState, useEffect } from 'react';
import { FiSearch, FiRefreshCw, FiAlertCircle } from 'react-icons/fi';
import { Facility } from '@/types';

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
  place_of_service?: string;
  medicare_part_b_authorized?: boolean;
  expected_service_date: string;
  payer_name: string;
  payer_id: string;
  second_payer?: string; // New field
  shipping_speed?: string; // New field
  wound_type: string;
  // other fields from Create.tsx FormData might be present
  [key: string]: any; // Allow other fields
}

interface PatientInformationStepProps {
  formData: ParentFormData; // Represents FormData from Create.tsx
  updateFormData: (data: Partial<ParentFormData>) => void;
  woundTypes: Record<string, string>; // Updated to match backend format
  facilities: Facility[];
}

const PatientInformationStep: React.FC<PatientInformationStepProps> = ({
  formData,
  updateFormData,
  woundTypes,
  facilities,
}) => {
  const [error, setError] = useState<string | null>(null);
  const [shippingError, setShippingError] = useState<string | null>(null);

  const handlePatientDemographicsChange = (demographicsUpdate: Partial<PatientApiInput>) => {
    updateFormData({
      patient_api_input: {
        ...formData.patient_api_input,
        ...demographicsUpdate,
      },
    });
  };

  // Generic handler for top-level formData fields specific to this step in Create.tsx
  const handleFieldChange = (fieldName: keyof ParentFormData, value: any) => {
    updateFormData({
      [fieldName]: value,
    } as Partial<ParentFormData>);
  };

  // Handle shipping speed selection with validation
  const handleShippingSpeedChange = (speed: string) => {
    handleFieldChange('shipping_speed', speed);

    // Clear previous shipping errors
    setShippingError(null);

    // Check if user selected options 1 or 2 (1st AM or Early Next Day)
    if (speed === '1st_am' || speed === 'early_next_day') {
      const now = new Date();
      const currentHour = now.getHours();
      const currentMinute = now.getMinutes();

      // Check if it's after 2 PM CST (14:00)
      if (currentHour >= 14) {
        setShippingError('Orders placed after 2 PM CST cannot be fulfilled next day. Please select Standard 2 Day shipping.');
        // Automatically change to Standard 2 Day
        handleFieldChange('shipping_speed', 'standard_2_day');
        return;
      }

      // Show expected delivery message
      setShippingError('Expected delivery is next day');
    }
  };

  // Shipping speed options
  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM)' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day (during office hours)' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  return (
    <div className="space-y-6">
      {/* Manual Patient Data Input */}
      <div className="bg-white shadow sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Enter Patient Details
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
                value={formData.patient_api_input.gender || 'unknown'}
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
          </div>
        </div>
      </div>

      {/* Service & Payer Information */}
      <div className="bg-white shadow sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Service & Payer Information
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* Place of Service */}
            <div className="sm:col-span-3">
              <label htmlFor="place_of_service" className="block text-sm font-medium text-gray-700">
                Place of Service
              </label>
              <select
                id="place_of_service"
                value={formData.place_of_service || ''}
                onChange={(e) => {
                  handleFieldChange('place_of_service', e.target.value);
                  // Reset Medicare Part B authorization if not skilled nursing
                  if (e.target.value !== '31') {
                    handleFieldChange('medicare_part_b_authorized', false);
                  }
                }}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="">Select Place of Service</option>
                <option value="11">(11) Office</option>
                <option value="12">(12) Home</option>
                <option value="32">(32) Nursing Home</option>
                <option value="31">(31) Skilled Nursing</option>
              </select>
              {/* Medicare Part B Authorization Checkbox - Only show for Skilled Nursing */}
              {formData.place_of_service === '31' && (
                <div className="mt-2">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="medicare_part_b_authorized"
                      checked={formData.medicare_part_b_authorized || false}
                      onChange={(e) => handleFieldChange('medicare_part_b_authorized', e.target.checked)}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label htmlFor="medicare_part_b_authorized" className="ml-2 block text-sm text-gray-700">
                      Only if Medicare Part B Authorized
                    </label>
                  </div>
                </div>
              )}
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
                onChange={(e) => {
                  const selectedDate = new Date(e.target.value);
                  const today = new Date();
                  today.setHours(0, 0, 0, 0); // Reset time to start of day

                  if (selectedDate < today) {
                    // If selected date is in the past, show error and don't update
                    setError('Expected service date must be a future date');
                    return;
                  }
                  setError(null);
                  handleFieldChange('expected_service_date', e.target.value);
                }}
                min={new Date().toISOString().split('T')[0]} // Set minimum date to today
                className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                  error ? 'border-red-300' : 'border-gray-300'
                }`}
              />
              {error && (
                <p className="mt-1 text-sm text-red-600">{error}</p>
              )}
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
            {/* Second Payer */}
            <div className="sm:col-span-3">
              <label htmlFor="second_payer" className="block text-sm font-medium text-gray-700">
                Second Payer (Optional)
              </label>
              <input
                type="text"
                id="second_payer"
                value={formData.second_payer || ''}
                onChange={(e) => handleFieldChange('second_payer', e.target.value)}
                placeholder="Secondary insurance provider"
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
                {Object.entries(woundTypes).map(([code, name]) => (
                  <option key={code} value={code}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Shipping Speed Selection */}
      <div className="bg-white shadow sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Shipping Speed
          </h3>
          <div className="mt-6 space-y-4">
            {shippingOptions.map((option) => (
              <div key={option.value} className="flex items-center">
                <input
                  type="radio"
                  id={option.value}
                  name="shipping_speed"
                  value={option.value}
                  checked={formData.shipping_speed === option.value}
                  onChange={(e) => handleShippingSpeedChange(e.target.value)}
                  className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                />
                <label htmlFor={option.value} className="ml-3 block text-sm font-medium text-gray-700">
                  {option.label}
                </label>
              </div>
            ))}
            {shippingError && (
              <div className={`mt-3 p-3 rounded-md ${
                shippingError.includes('cannot be fulfilled')
                  ? 'bg-red-50 border border-red-200'
                  : 'bg-blue-50 border border-blue-200'
              }`}>
                <p className={`text-sm ${
                  shippingError.includes('cannot be fulfilled')
                    ? 'text-red-800'
                    : 'text-blue-800'
                }`}>
                  {shippingError}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default PatientInformationStep;
