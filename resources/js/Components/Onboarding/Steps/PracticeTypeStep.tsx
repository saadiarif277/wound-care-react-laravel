import React from 'react';
import { Building2 } from 'lucide-react';
import type { ProviderRegistrationData, PracticeType } from '@/types/provider';

interface PracticeTypeStepProps {
  data: Partial<ProviderRegistrationData>;
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

const practiceTypes: Array<{
  value: PracticeType;
  title: string;
  description: string;
}> = [
  {
    value: 'solo_practitioner',
    title: 'Solo Practitioner',
    description: `You're the primary provider and need to set up both organization and facility information. 
    Perfect for individual practices where you are both the provider and practice owner.`,
  },
  {
    value: 'group_practice',
    title: 'Group Practice',
    description: `You're joining a new group practice that needs to be set up in our system. 
    We'll collect organization, facility, and your individual provider information.`,
  },
  {
    value: 'existing_organization',
    title: 'Joining Existing Organization',
    description: `You're joining an organization that's already set up in our system. 
    We'll focus on collecting your individual provider credentials and facility assignment.`,
  },
];

export default function PracticeTypeStep({ 
  data, 
  onChange 
}: PracticeTypeStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
          <Building2 className="h-8 w-8 text-green-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Practice Setup</h1>
        <p className="text-gray-600">Tell us about your practice structure</p>
      </div>

      <div className="space-y-4">
        {practiceTypes.map((type) => (
          <label key={type.value} className="block">
            <input
              type="radio"
              name="practice_type"
              value={type.value}
              checked={data.practice_type === type.value}
              onChange={(e) => onChange('practice_type', e.target.value as PracticeType)}
              className="sr-only peer"
            />
            <div className={`p-6 border-2 rounded-lg cursor-pointer transition-all 
              peer-checked:border-blue-500 peer-checked:bg-blue-50 
              peer-focus:ring-2 peer-focus:ring-blue-300
              hover:border-gray-300 border-gray-200`}>
              <h3 className="text-lg font-medium text-gray-900 mb-2">{type.title}</h3>
              <p className="text-sm text-gray-600">{type.description}</p>
            </div>
          </label>
        ))}
      </div>
    </div>
  );
}