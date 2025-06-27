import React from 'react';
import { Home, MapPin } from 'lucide-react';
import type { 
  ProviderRegistrationData, 
  FacilityData,
  ProviderInvitationData 
} from '@/types/provider';

interface FacilitySelectionStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  facilities: FacilityData[];
  invitation: ProviderInvitationData;
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

export default function FacilitySelectionStep({ 
  data, 
  errors,
  facilities,
  invitation,
  onChange 
}: FacilitySelectionStepProps) {
  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
          <Home className="h-8 w-8 text-blue-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Select Your Facility</h1>
        <p className="text-gray-600">
          You're joining <strong>{invitation.organization_name}</strong>. 
          Please select your primary practice location from the list below.
        </p>
      </div>

      <div className="space-y-4" role="radiogroup" aria-label="Select facility">
        {facilities.map(facility => (
          <label key={facility.id} className="block">
            <input
              type="radio"
              name="facility_id"
              value={facility.id.toString()}
              checked={data.facility_id === facility.id}
              onChange={(e) => onChange('facility_id', parseInt(e.target.value))}
              className="sr-only peer"
              aria-describedby={`facility-${facility.id}-desc`}
            />
            <div className={`p-6 border-2 rounded-lg cursor-pointer transition-all 
              peer-checked:border-blue-500 peer-checked:bg-blue-50 
              peer-focus:ring-2 peer-focus:ring-blue-300
              hover:border-gray-300 border-gray-200`}>
              <h3 className="text-lg font-medium text-gray-900 mb-1">
                {facility.name}
              </h3>
              <p id={`facility-${facility.id}-desc`} 
                 className="text-sm text-gray-600 flex items-center gap-2">
                <MapPin className="h-4 w-4 text-gray-400 flex-shrink-0" />
                {facility.full_address}
              </p>
              {facility.npi && (
                <p className="text-xs text-gray-500 mt-2">
                  NPI: {facility.npi}
                </p>
              )}
            </div>
          </label>
        ))}
      </div>

      {errors.facility_id && (
        <p className="text-sm text-red-600 mt-2" role="alert">
          {errors.facility_id}
        </p>
      )}

      {facilities.length === 0 && (
        <div className="text-center py-8 text-gray-500">
          No facilities available for selection. Please contact your organization administrator.
        </div>
      )}
    </div>
  );
}