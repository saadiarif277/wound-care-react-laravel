import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import { Key, User, CreditCard, Printer } from 'lucide-react';
import { formatPhoneNumber } from '@/utils/providerValidation';
import type { ProviderRegistrationData } from '@/types/provider';

interface PersonalInfoStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K,
    value: ProviderRegistrationData[K]
  ) => void;
}

export default function PersonalInfoStep({
  data,
  errors,
  onChange
}: PersonalInfoStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
          <User className="h-8 w-8 text-purple-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Personal Information</h1>
        <p className="text-gray-600">Create your account and provide your personal details</p>
      </div>

      {/* Basic Information */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900 border-b pb-2">Basic Information</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="First Name"
            name="first_name"
            value={data.first_name || ''}
            onChange={(e) => onChange('first_name', e.target.value)}
            error={errors.first_name}
            required
          />

          <TextInput
            label="Last Name"
            name="last_name"
            value={data.last_name || ''}
            onChange={(e) => onChange('last_name', e.target.value)}
            error={errors.last_name}
            required
          />

          <TextInput
            label="Credentials"
            name="credentials"
            value={data.credentials || ''}
            onChange={(e) => onChange('credentials', e.target.value)}
            placeholder="MD, DO, DPM, NP, PA, etc."
            error={errors.credentials}
          />

          <TextInput
            label="Title/Position"
            name="title"
            value={data.title || ''}
            onChange={(e) => onChange('title', e.target.value)}
            placeholder="e.g., Physician, Nurse Practitioner"
          />

          <div className="md:col-span-2">
            <TextInput
              label="Email Address"
              name="email"
              type="email"
              value={data.email || ''}
              disabled
              className="bg-gray-50"
            />
          </div>
        </div>
      </div>

      {/* Contact Information */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900 border-b pb-2 flex items-center">
          <Key className="h-5 w-5 mr-2 text-gray-600" />
          Contact Information
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="Phone Number"
            name="phone"
            type="tel"
            value={data.phone || ''}
            onChange={(e) => {
              const formatted = formatPhoneNumber(e.target.value);
              onChange('phone', formatted);
            }}
            error={errors.phone}
            placeholder="(555) 123-4567"
          />

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              <Printer className="inline h-4 w-4 mr-1" />
              Fax Number
            </label>
            <TextInput
              name="fax"
              type="tel"
              value={data.fax || ''}
              onChange={(e) => {
                const formatted = formatPhoneNumber(e.target.value);
                onChange('fax', formatted);
              }}
              error={errors.fax}
              placeholder="(555) 123-4568"
            />
          </div>
        </div>
      </div>

      {/* Financial Information */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900 border-b pb-2 flex items-center">
          <CreditCard className="h-5 w-5 mr-2 text-gray-600" />
          Financial Information
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="Tax ID / SSN"
            name="tax_id"
            value={data.tax_id || ''}
            onChange={(e) => onChange('tax_id', e.target.value)}
            error={errors.tax_id}
            placeholder="12-3456789 or XXX-XX-XXXX"
          />

          <TextInput
            label="Medicaid Number"
            name="medicaid_number"
            value={data.medicaid_number || ''}
            onChange={(e) => onChange('medicaid_number', e.target.value)}
            error={errors.medicaid_number}
            placeholder="MED123456"
          />
        </div>

        <p className="text-sm text-gray-600">
          Your Tax ID is required for 1099 reporting. Medicaid number is optional and state-specific.
        </p>
      </div>

      {/* Account Security */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900 border-b pb-2">Account Security</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <TextInput
            label="Password"
            name="password"
            type="password"
            value={data.password || ''}
            onChange={(e) => onChange('password', e.target.value)}
            error={errors.password}
            required
          />

          <TextInput
            label="Confirm Password"
            name="password_confirmation"
            type="password"
            value={data.password_confirmation || ''}
            onChange={(e) => onChange('password_confirmation', e.target.value)}
            error={errors.password_confirmation}
            required
          />
        </div>

        <p className="text-sm text-gray-600">
          Password must be at least 8 characters long.
        </p>
      </div>
    </div>
  );
}