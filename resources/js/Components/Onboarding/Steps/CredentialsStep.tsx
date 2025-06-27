import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import CheckboxInput from '@/Components/Form/CheckboxInput';
import { Shield } from 'lucide-react';
import { formatNPI } from '@/utils/providerValidation';
import type { ProviderRegistrationData, StateOption } from '@/types/provider';

interface CredentialsStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  states: StateOption[];
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

const specialties = [
  { value: 'wound_care', label: 'Wound Care' },
  { value: 'family_medicine', label: 'Family Medicine' },
  { value: 'internal_medicine', label: 'Internal Medicine' },
  { value: 'emergency_medicine', label: 'Emergency Medicine' },
  { value: 'surgery', label: 'Surgery' },
  { value: 'dermatology', label: 'Dermatology' },
  { value: 'podiatry', label: 'Podiatry' },
  { value: 'nursing', label: 'Nursing' },
  { value: 'physician_assistant', label: 'Physician Assistant' },
  { value: 'nurse_practitioner', label: 'Nurse Practitioner' },
  { value: 'other', label: 'Other' },
];

export default function CredentialsStep({ 
  data, 
  errors,
  states,
  onChange 
}: CredentialsStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
          <Shield className="h-8 w-8 text-purple-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Professional Credentials</h1>
        <p className="text-gray-600">Provide your professional credentials for verification</p>
      </div>

      <div className="space-y-6">
        {/* NPI and Specialty */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="Individual NPI Number"
            name="individual_npi"
            value={data.individual_npi || ''}
            onChange={(e) => {
              const formatted = formatNPI(e.target.value);
              onChange('individual_npi', formatted);
            }}
            error={errors.individual_npi}
            placeholder="1234 5678 90"
          />

          <SelectInput
            label="Primary Specialty"
            name="specialty"
            value={data.specialty || ''}
            onChange={(e) => onChange('specialty', e.target.value)}
            error={errors.specialty}
            options={specialties}
          />
        </div>

        {/* License Information */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Medical License</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <TextInput
              label="License Number"
              name="license_number"
              value={data.license_number || ''}
              onChange={(e) => onChange('license_number', e.target.value)}
              error={errors.license_number}
              placeholder="e.g., MD123456"
            />

            <SelectInput
              label="License State"
              name="license_state"
              value={data.license_state || ''}
              onChange={(e) => onChange('license_state', e.target.value)}
              error={errors.license_state}
            >
              <option value="">Select state</option>
              {states.map((state) => (
                <option key={state.code} value={state.code}>
                  {state.name}
                </option>
              ))}
            </SelectInput>
          </div>
        </div>

        {/* Additional Identifiers */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Additional Identifiers</h3>
          
          <TextInput
            label="PTAN (Provider Transaction Access Number)"
            name="ptan"
            value={data.ptan || ''}
            onChange={(e) => onChange('ptan', e.target.value)}
            placeholder="Optional - Used for Medicare billing"
          />
        </div>

        {/* Terms and Conditions */}
        <div className="space-y-4 border-t pt-6">
          <h3 className="text-lg font-medium text-gray-900">Terms & Conditions</h3>
          
          <div className="bg-gray-50 p-4 rounded-lg">
            <CheckboxInput
              label={
                <span className="text-sm text-gray-700">
                  I agree to the{' '}
                  <a href="/terms" target="_blank" className="text-blue-600 hover:underline">
                    Terms of Service
                  </a>{' '}
                  and{' '}
                  <a href="/privacy" target="_blank" className="text-blue-600 hover:underline">
                    Privacy Policy
                  </a>
                  . I understand that my credentials will be verified before account activation.
                </span>
              }
              name="accept_terms"
              checked={data.accept_terms || false}
              onChange={(e) => onChange('accept_terms', e.target.checked)}
              error={errors.accept_terms}
            />
          </div>
        </div>

        {/* Verification Notice */}
        <div className="bg-blue-50 p-4 rounded-lg">
          <h4 className="text-sm font-medium text-blue-800 mb-2">Verification Process</h4>
          <ul className="text-sm text-blue-700 space-y-1">
            <li>• Your credentials will be verified within 1-2 business days</li>
            <li>• We may contact you for additional documentation if needed</li>
            <li>• You'll receive email notification once verification is complete</li>
            <li>• All information is securely stored and HIPAA compliant</li>
          </ul>
        </div>
      </div>
    </div>
  );
}