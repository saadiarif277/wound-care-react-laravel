import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import { Key } from 'lucide-react';
import { formatTaxId } from '@/utils/providerValidation';
import type { ProviderRegistrationData } from '@/types/provider';

interface OrganizationStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

const organizationTypes = [
  { value: 'healthcare_provider', label: 'Healthcare Provider' },
  { value: 'medical_practice', label: 'Medical Practice' },
  { value: 'clinic', label: 'Clinic' },
  { value: 'hospital_system', label: 'Hospital System' },
  { value: 'specialty_group', label: 'Specialty Group' },
  { value: 'other', label: 'Other' },
];

export default function OrganizationStep({ 
  data, 
  errors, 
  onChange 
}: OrganizationStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
          <Key className="h-8 w-8 text-purple-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Organization Information</h1>
        <p className="text-gray-600">Provide your organization details</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <TextInput
          label="Organization Name"
          name="organization_name"
          value={data.organization_name || ''}
          onChange={(e) => onChange('organization_name', e.target.value)}
          error={errors.organization_name}
          required
          placeholder="e.g., Smith Medical Associates"
        />

        <TextInput
          label="Tax ID"
          name="organization_tax_id"
          value={data.organization_tax_id || ''}
          onChange={(e) => {
            const formatted = formatTaxId(e.target.value);
            onChange('organization_tax_id', formatted);
          }}
          error={errors.organization_tax_id}
          placeholder="XX-XXXXXXX"
        />

        <div className="md:col-span-2">
          <SelectInput
            label="Organization Type"
            name="organization_type"
            value={data.organization_type || ''}
            onChange={(e) => onChange('organization_type', e.target.value)}
            error={errors.organization_type}
            options={organizationTypes}
          />
        </div>
      </div>

      <div className="bg-blue-50 p-4 rounded-lg">
        <h4 className="text-sm font-medium text-blue-800 mb-2">Important Information</h4>
        <ul className="text-sm text-blue-700 space-y-1">
          <li>• Organization name should match your legal business entity</li>
          <li>• Tax ID is required for billing and compliance purposes</li>
          <li>• This information will be used for all legal agreements</li>
        </ul>
      </div>
    </div>
  );
}