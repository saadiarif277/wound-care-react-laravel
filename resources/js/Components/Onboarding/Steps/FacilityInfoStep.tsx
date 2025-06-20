import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import { Building2 } from 'lucide-react';
import { formatPhoneNumber, formatNPI } from '@/utils/providerValidation';
import type { ProviderRegistrationData, StateOption } from '@/types/provider';

interface FacilityInfoStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  states: StateOption[];
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

const facilityTypes = [
  { value: 'Private Practice', label: 'Private Practice' },
  { value: 'Clinic', label: 'Clinic' },
  { value: 'Hospital', label: 'Hospital' },
  { value: 'Surgery Center', label: 'Surgery Center' },
  { value: 'Wound Care Center', label: 'Wound Care Center' },
  { value: 'Emergency Department', label: 'Emergency Department' },
  { value: 'Urgent Care', label: 'Urgent Care' },
  { value: 'Specialty Clinic', label: 'Specialty Clinic' },
  { value: 'Other', label: 'Other' },
];

export default function FacilityInfoStep({ 
  data, 
  errors,
  states,
  onChange 
}: FacilityInfoStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 mb-4">
          <Building2 className="h-8 w-8 text-indigo-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Facility Information</h1>
        <p className="text-gray-600">Provide your primary practice location details</p>
      </div>

      <div className="space-y-6">
        {/* Basic Facility Info */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="Facility Name"
            name="facility_name"
            value={data.facility_name || ''}
            onChange={(e) => onChange('facility_name', e.target.value)}
            error={errors.facility_name}
            required
            placeholder="e.g., Main Street Clinic"
          />

          <SelectInput
            label="Facility Type"
            name="facility_type"
            value={data.facility_type || ''}
            onChange={(e) => onChange('facility_type', e.target.value)}
            error={errors.facility_type}
            required
            options={facilityTypes}
          />
        </div>

        {/* Address Information */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Facility Address</h3>
          
          <TextInput
            label="Street Address"
            name="facility_address"
            value={data.facility_address || ''}
            onChange={(e) => onChange('facility_address', e.target.value)}
            error={errors.facility_address}
            required
            placeholder="123 Main Street, Suite 100"
          />

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <TextInput
              label="City"
              name="facility_city"
              value={data.facility_city || ''}
              onChange={(e) => onChange('facility_city', e.target.value)}
              error={errors.facility_city}
              required
            />

            <SelectInput
              label="State"
              name="facility_state"
              value={data.facility_state || ''}
              onChange={(e) => onChange('facility_state', e.target.value)}
              error={errors.facility_state}
              required
            >
              <option value="">Select state</option>
              {states.map((state) => (
                <option key={state.code} value={state.code}>
                  {state.name}
                </option>
              ))}
            </SelectInput>

            <TextInput
              label="ZIP Code"
              name="facility_zip"
              value={data.facility_zip || ''}
              onChange={(e) => onChange('facility_zip', e.target.value)}
              error={errors.facility_zip}
              required
              placeholder="12345"
            />
          </div>
        </div>

        {/* Contact Information */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Contact Information</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <TextInput
              label="Facility Phone"
              name="facility_phone"
              type="tel"
              value={data.facility_phone || ''}
              onChange={(e) => {
                const formatted = formatPhoneNumber(e.target.value);
                onChange('facility_phone', formatted);
              }}
              error={errors.facility_phone}
              placeholder="(555) 123-4567"
            />

            <TextInput
              label="Facility Email"
              name="facility_email"
              type="email"
              value={data.facility_email || ''}
              onChange={(e) => onChange('facility_email', e.target.value)}
              error={errors.facility_email}
              placeholder="contact@facility.com"
            />
          </div>
        </div>

        {/* Additional Identifiers */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Additional Information</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <TextInput
              label="Group NPI"
              name="group_npi"
              value={data.group_npi || ''}
              onChange={(e) => {
                const formatted = formatNPI(e.target.value);
                onChange('group_npi', formatted);
              }}
              error={errors.group_npi}
              placeholder="1234 5678 90"
            />

            <TextInput
              label="Facility Tax ID"
              name="facility_tax_id"
              value={data.facility_tax_id || ''}
              onChange={(e) => onChange('facility_tax_id', e.target.value)}
              placeholder="Optional"
            />

            <TextInput
              label="PTAN (Provider Transaction Access Number)"
              name="facility_ptan"
              value={data.facility_ptan || ''}
              onChange={(e) => onChange('facility_ptan', e.target.value)}
              placeholder="Optional"
            />
          </div>
        </div>
      </div>
    </div>
  );
}