import React, { useState, useEffect } from 'react';
import { FiSearch, FiRefreshCw, FiAlertCircle } from 'react-icons/fi';
import { Facility } from '@/types';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

// Define PatientApiInput locally, matching Create.tsx, if not easily importable
// This should ideally be shared from Create.tsx or a common types file.
interface PatientApiInput {
  first_name: string;
  last_name: string;
  dob: string;
  gender?: 'male' | 'female' | 'other' | 'unknown'; // Changed to optional
  member_id?: string; // Changed to optional
  id?: string;
  // Add address fields
  address_line1?: string;
  address_line2?: string;
  city?: string;
  state?: string;
  zip?: string;
  phone?: string;
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
  // Basic wound information
  wound_location?: string;
  wound_duration_weeks?: number;
  primary_diagnosis_code?: string;
  // Insurance information for eligibility
  insurance_type?: string;
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
  // Theme context
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

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
      <div className={cn("shadow sm:rounded-lg", t.glass.card)}>
        <div className="px-4 py-5 sm:p-6">
          <h3 className={cn("text-lg font-medium leading-6", t.text.primary)}>
            Enter Patient Details
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* First Name */}
            <div className="sm:col-span-3">
              <label htmlFor="first-name" className={cn("block text-sm font-medium", t.text.secondary)}>
                First Name
              </label>
              <input
                type="text"
                id="first-name"
                value={formData.patient_api_input.first_name || ''}
                onChange={(e) => handlePatientDemographicsChange({ first_name: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* Last Name */}
            <div className="sm:col-span-3">
              <label htmlFor="last-name" className={cn("block text-sm font-medium", t.text.secondary)}>
                Last Name
              </label>
              <input
                type="text"
                id="last-name"
                value={formData.patient_api_input.last_name || ''}
                onChange={(e) => handlePatientDemographicsChange({ last_name: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* DOB */}
            <div className="sm:col-span-3">
              <label htmlFor="dob" className={cn("block text-sm font-medium", t.text.secondary)}>
                Date of Birth
              </label>
              <input
                type="date"
                id="dob"
                value={formData.patient_api_input.dob || ''}
                onChange={(e) => handlePatientDemographicsChange({ dob: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* Gender */}
            <div className="sm:col-span-3">
              <label htmlFor="gender" className={cn("block text-sm font-medium", t.text.secondary)}>
                Gender
              </label>
              <select
                id="gender"
                value={formData.patient_api_input.gender || 'unknown'}
                onChange={(e) => handlePatientDemographicsChange({ gender: e.target.value as PatientApiInput['gender'] })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
                <option value="unknown">Unknown</option>
              </select>
            </div>
            {/* Member ID */}
            <div className="sm:col-span-3">
              <label htmlFor="member_id" className={cn("block text-sm font-medium", t.text.secondary)}>
                Member ID (Insurance)
              </label>
              <input
                type="text"
                id="member_id"
                value={formData.patient_api_input.member_id || ''}
                onChange={(e) => handlePatientDemographicsChange({ member_id: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Patient Address Information */}
      <div className={cn("shadow sm:rounded-lg", t.glass.card)}>
        <div className="px-4 py-5 sm:p-6">
          <h3 className={cn("text-lg font-medium leading-6", t.text.primary)}>
            Patient Address
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* Address Line 1 */}
            <div className="sm:col-span-6">
              <label htmlFor="address-line1" className={cn("block text-sm font-medium", t.text.secondary)}>
                Address Line 1
              </label>
              <input
                type="text"
                id="address-line1"
                value={formData.patient_api_input.address_line1 || ''}
                onChange={(e) => handlePatientDemographicsChange({ address_line1: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="Street address"
              />
            </div>
            {/* Address Line 2 */}
            <div className="sm:col-span-6">
              <label htmlFor="address-line2" className={cn("block text-sm font-medium", t.text.secondary)}>
                Address Line 2 (Optional)
              </label>
              <input
                type="text"
                id="address-line2"
                value={formData.patient_api_input.address_line2 || ''}
                onChange={(e) => handlePatientDemographicsChange({ address_line2: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="Apartment, suite, unit, etc."
              />
            </div>
            {/* City */}
            <div className="sm:col-span-2">
              <label htmlFor="city" className={cn("block text-sm font-medium", t.text.secondary)}>
                City
              </label>
              <input
                type="text"
                id="city"
                value={formData.patient_api_input.city || ''}
                onChange={(e) => handlePatientDemographicsChange({ city: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* State */}
            <div className="sm:col-span-2">
              <label htmlFor="state" className={cn("block text-sm font-medium", t.text.secondary)}>
                State
              </label>
              <select
                id="state"
                value={formData.patient_api_input.state || ''}
                onChange={(e) => handlePatientDemographicsChange({ state: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="">Select State</option>
                <option value="AL">Alabama</option>
                <option value="AK">Alaska</option>
                <option value="AZ">Arizona</option>
                <option value="AR">Arkansas</option>
                <option value="CA">California</option>
                <option value="CO">Colorado</option>
                <option value="CT">Connecticut</option>
                <option value="DE">Delaware</option>
                <option value="FL">Florida</option>
                <option value="GA">Georgia</option>
                <option value="HI">Hawaii</option>
                <option value="ID">Idaho</option>
                <option value="IL">Illinois</option>
                <option value="IN">Indiana</option>
                <option value="IA">Iowa</option>
                <option value="KS">Kansas</option>
                <option value="KY">Kentucky</option>
                <option value="LA">Louisiana</option>
                <option value="ME">Maine</option>
                <option value="MD">Maryland</option>
                <option value="MA">Massachusetts</option>
                <option value="MI">Michigan</option>
                <option value="MN">Minnesota</option>
                <option value="MS">Mississippi</option>
                <option value="MO">Missouri</option>
                <option value="MT">Montana</option>
                <option value="NE">Nebraska</option>
                <option value="NV">Nevada</option>
                <option value="NH">New Hampshire</option>
                <option value="NJ">New Jersey</option>
                <option value="NM">New Mexico</option>
                <option value="NY">New York</option>
                <option value="NC">North Carolina</option>
                <option value="ND">North Dakota</option>
                <option value="OH">Ohio</option>
                <option value="OK">Oklahoma</option>
                <option value="OR">Oregon</option>
                <option value="PA">Pennsylvania</option>
                <option value="RI">Rhode Island</option>
                <option value="SC">South Carolina</option>
                <option value="SD">South Dakota</option>
                <option value="TN">Tennessee</option>
                <option value="TX">Texas</option>
                <option value="UT">Utah</option>
                <option value="VT">Vermont</option>
                <option value="VA">Virginia</option>
                <option value="WA">Washington</option>
                <option value="WV">West Virginia</option>
                <option value="WI">Wisconsin</option>
                <option value="WY">Wyoming</option>
              </select>
            </div>
            {/* Zip Code */}
            <div className="sm:col-span-2">
              <label htmlFor="zip" className={cn("block text-sm font-medium", t.text.secondary)}>
                ZIP Code
              </label>
              <input
                type="text"
                id="zip"
                value={formData.patient_api_input.zip || ''}
                onChange={(e) => handlePatientDemographicsChange({ zip: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="12345"
                maxLength={10}
              />
            </div>
            {/* Phone */}
            <div className="sm:col-span-3">
              <label htmlFor="phone" className={cn("block text-sm font-medium", t.text.secondary)}>
                Patient Phone (Optional)
              </label>
              <input
                type="tel"
                id="phone"
                value={formData.patient_api_input.phone || ''}
                onChange={(e) => handlePatientDemographicsChange({ phone: e.target.value })}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="(555) 555-5555"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Service & Payer Information */}
      <div className={cn("shadow sm:rounded-lg", t.glass.card)}>
        <div className="px-4 py-5 sm:p-6">
          <h3 className={cn("text-lg font-medium leading-6", t.text.primary)}>
            Service & Payer Information
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* Place of Service */}
            <div className="sm:col-span-3">
              <label htmlFor="place_of_service" className={cn("block text-sm font-medium", t.text.secondary)}>
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
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
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
                    <label htmlFor="medicare_part_b_authorized" className={cn("ml-2 block text-sm", t.text.secondary)}>
                      Only if Medicare Part B Authorized
                    </label>
                  </div>
                </div>
              )}
            </div>
            {/* Expected Service Date */}
            <div className="sm:col-span-3">
              <label htmlFor="expected_service_date" className={cn("block text-sm font-medium", t.text.secondary)}>
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
                <p className={cn("mt-1 text-sm", theme === 'dark' ? 'text-red-400' : 'text-red-600')}>{error}</p>
              )}
            </div>
            {/* Payer Name */}
            <div className="sm:col-span-3">
              <label htmlFor="payer_name" className={cn("block text-sm font-medium", t.text.secondary)}>
                Payer Name
              </label>
              <input
                type="text"
                id="payer_name"
                value={formData.payer_name || ''}
                onChange={(e) => handleFieldChange('payer_name', e.target.value)}
                placeholder="e.g., Medicare, Aetna"
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* Payer ID */}
            <div className="sm:col-span-3">
              <label htmlFor="payer_id" className={cn("block text-sm font-medium", t.text.secondary)}>
                Payer ID
              </label>
              <input
                type="text"
                id="payer_id"
                value={formData.payer_id || ''}
                onChange={(e) => handleFieldChange('payer_id', e.target.value)}
                placeholder="Payer-specific ID"
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* Second Payer */}
            <div className="sm:col-span-3">
              <label htmlFor="second_payer" className={cn("block text-sm font-medium", t.text.secondary)}>
                Second Payer (Optional)
              </label>
              <input
                type="text"
                id="second_payer"
                value={formData.second_payer || ''}
                onChange={(e) => handleFieldChange('second_payer', e.target.value)}
                placeholder="Secondary insurance provider"
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              />
            </div>
            {/* Insurance Type for Eligibility */}
            <div className="sm:col-span-3">
              <label htmlFor="insurance_type" className={cn("block text-sm font-medium", t.text.secondary)}>
                Insurance Type
              </label>
              <select
                id="insurance_type"
                value={formData.insurance_type || ''}
                onChange={(e) => handleFieldChange('insurance_type', e.target.value)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="">Select Insurance Type</option>
                <option value="medicare">Medicare</option>
                <option value="medicaid">Medicaid</option>
                <option value="medicare_advantage">Medicare Advantage</option>
                <option value="commercial">Commercial</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Basic Wound Information */}
      <div className={cn("shadow sm:rounded-lg", t.glass.card)}>
        <div className="px-4 py-5 sm:p-6">
          <h3 className={cn("text-lg font-medium leading-6", t.text.primary)}>
            Basic Wound Information
          </h3>
          <div className="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            {/* Wound Type */}
            <div className="sm:col-span-3">
              <label htmlFor="wound_type" className={cn("block text-sm font-medium", t.text.secondary)}>
                Wound Type
              </label>
              <select
                id="wound_type"
                value={formData.wound_type || ''}
                onChange={(e) => handleFieldChange('wound_type', e.target.value)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="">Select Wound Type</option>
                {Object.entries(woundTypes).map(([code, name]) => (
                  <option key={code} value={code}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
            {/* Wound Location */}
            <div className="sm:col-span-3">
              <label htmlFor="wound_location" className={cn("block text-sm font-medium", t.text.secondary)}>
                Wound Location
              </label>
              <select
                id="wound_location"
                value={formData.wound_location || ''}
                onChange={(e) => handleFieldChange('wound_location', e.target.value)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="">Select Location</option>
                <option value="right_foot">Right Foot</option>
                <option value="left_foot">Left Foot</option>
                <option value="right_lower_leg">Right Lower Leg</option>
                <option value="left_lower_leg">Left Lower Leg</option>
                <option value="right_upper_leg">Right Upper Leg</option>
                <option value="left_upper_leg">Left Upper Leg</option>
                <option value="sacrum">Sacrum</option>
                <option value="coccyx">Coccyx</option>
                <option value="buttock">Buttock</option>
                <option value="heel">Heel</option>
                <option value="other">Other</option>
              </select>
            </div>
            {/* Wound Duration */}
            <div className="sm:col-span-3">
              <label htmlFor="wound_duration" className={cn("block text-sm font-medium", t.text.secondary)}>
                Wound Duration (weeks)
              </label>
              <input
                type="number"
                id="wound_duration"
                value={formData.wound_duration_weeks || ''}
                onChange={(e) => handleFieldChange('wound_duration_weeks', parseInt(e.target.value) || null)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="Number of weeks"
                min="1"
              />
            </div>
            {/* Primary Diagnosis Code */}
            <div className="sm:col-span-3">
              <label htmlFor="primary_diagnosis" className={cn("block text-sm font-medium", t.text.secondary)}>
                Primary Diagnosis Code (ICD-10)
              </label>
              <input
                type="text"
                id="primary_diagnosis"
                value={formData.primary_diagnosis_code || ''}
                onChange={(e) => handleFieldChange('primary_diagnosis_code', e.target.value)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
                placeholder="e.g., L97.419"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Facility Selection & Shipping */}
      <div className={cn("shadow sm:rounded-lg", t.glass.card)}>
        <div className="px-4 py-5 sm:p-6">
          <h3 className={cn("text-lg font-medium leading-6", t.text.primary)}>
            Facility & Shipping Information
          </h3>
          <div className="mt-6 space-y-6">
            {/* Facility Selection */}
            <div>
              <label htmlFor="facility" className={cn("block text-sm font-medium", t.text.secondary)}>
                Select Facility (Shipping Address)
              </label>
              <select
                id="facility"
                value={formData.facility_id || ''}
                onChange={(e) => handleFieldChange('facility_id', e.target.value ? Number(e.target.value) : null)}
                className={cn("mt-1 block w-full rounded-md shadow-sm sm:text-sm", t.input.base, t.input.focus)}
              >
                <option value="">Select a facility</option>
                {facilities.map((facility) => (
                  <option key={facility.id} value={facility.id}>
                    {facility.name}
                  </option>
                ))}
              </select>
              
              {/* Display selected facility address */}
              {formData.facility_id && (() => {
                const selectedFacility = facilities.find(f => f.id === formData.facility_id);
                return selectedFacility?.address ? (
                  <div className={cn("mt-2 p-3 rounded-md", theme === 'dark' ? 'bg-gray-800' : 'bg-gray-50')}>
                    <p className={cn("text-sm font-medium", t.text.primary)}>Shipping Address:</p>
                    <p className={cn("text-sm", t.text.secondary)}>{selectedFacility.address}</p>
                  </div>
                ) : null;
              })()}
            </div>

            {/* Shipping Speed */}
            <div>
              <label className={cn("block text-sm font-medium mb-3", t.text.secondary)}>
                Shipping Speed
              </label>
              <div className="space-y-3">
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
                    <label htmlFor={option.value} className={cn("ml-3 block text-sm font-medium", t.text.secondary)}>
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
                        ? (theme === 'dark' ? 'text-red-400' : 'text-red-800')
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
      </div>
    </div>
  );
};

export default PatientInformationStep;
