import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import { Key } from 'lucide-react';
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
          <Key className="h-8 w-8 text-purple-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Personal Information</h1>
        <p className="text-gray-600">Create your account and provide basic information</p>
      </div>

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

        <TextInput
          label="Title/Position"
          name="title"
          value={data.title || ''}
          onChange={(e) => onChange('title', e.target.value)}
          placeholder="e.g., Physician, Nurse Practitioner"
        />

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
    </div>
  );
}