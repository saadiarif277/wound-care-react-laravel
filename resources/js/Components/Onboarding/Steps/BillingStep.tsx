import React from 'react';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import TextAreaInput from '@/Components/Form/TextAreaInput';
import { Button } from '@/Components/Button';
import { CreditCard, Copy } from 'lucide-react';
import { formatPhoneNumber } from '@/utils/providerValidation';
import type { ProviderRegistrationData, StateOption } from '@/types/provider';

interface BillingStepProps {
  data: Partial<ProviderRegistrationData>;
  errors: Record<string, string>;
  states: StateOption[];
  onChange: <K extends keyof ProviderRegistrationData>(
    field: K, 
    value: ProviderRegistrationData[K]
  ) => void;
}

export default function BillingStep({ 
  data, 
  errors,
  states,
  onChange 
}: BillingStepProps) {
  const copyFacilityToBilling = () => {
    // Type guard to ensure we have the right data
    if ('facility_address' in data) {
      onChange('billing_address', data.facility_address || '');
      onChange('billing_city', data.facility_city || '');
      onChange('billing_state', data.facility_state || '');
      onChange('billing_zip', data.facility_zip || '');
    }
  };

  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
          <CreditCard className="h-8 w-8 text-green-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Billing Information</h1>
        <p className="text-gray-600">Where invoices should be sent (can be different from shipping address)</p>
      </div>

      <div className="space-y-6">
        {/* Copy from Facility Button */}
        <div className="bg-blue-50 p-4 rounded-lg">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={copyFacilityToBilling}
            className="flex items-center gap-2"
          >
            <Copy className="h-4 w-4" />
            Copy facility address to billing address
          </Button>
        </div>

        {/* Billing Address */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Bill-To Address</h3>
          
          <TextAreaInput
            label="Billing Address"
            name="billing_address"
            value={data.billing_address || ''}
            onChange={(e) => onChange('billing_address', e.target.value)}
            error={errors.billing_address}
            rows={2}
            placeholder="Billing address (can be different from facility address)"
          />

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <TextInput
              label="City"
              name="billing_city"
              value={data.billing_city || ''}
              onChange={(e) => onChange('billing_city', e.target.value)}
              error={errors.billing_city}
            />

            <SelectInput
              label="State"
              name="billing_state"
              value={data.billing_state || ''}
              onChange={(e) => onChange('billing_state', e.target.value)}
              error={errors.billing_state}
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
              name="billing_zip"
              value={data.billing_zip || ''}
              onChange={(e) => onChange('billing_zip', e.target.value)}
              error={errors.billing_zip}
              placeholder="12345 or 12345-6789"
            />
          </div>
        </div>

        {/* Accounts Payable Contact */}
        <div className="space-y-4 border-t pt-6">
          <h3 className="text-lg font-medium text-gray-900">Accounts Payable Contact</h3>
          <p className="text-sm text-gray-600">Who should we contact for payment-related matters?</p>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <TextInput
              label="AP Contact Name"
              name="ap_contact_name"
              value={data.ap_contact_name || ''}
              onChange={(e) => onChange('ap_contact_name', e.target.value)}
              placeholder="Person who handles payments"
            />

            <TextInput
              label="AP Contact Phone"
              name="ap_contact_phone"
              type="tel"
              value={data.ap_contact_phone || ''}
              onChange={(e) => {
                const formatted = formatPhoneNumber(e.target.value);
                onChange('ap_contact_phone', formatted);
              }}
              placeholder="(555) 123-4567"
            />

            <TextInput
              label="AP Contact Email"
              name="ap_contact_email"
              type="email"
              value={data.ap_contact_email || ''}
              onChange={(e) => onChange('ap_contact_email', e.target.value)}
              placeholder="billing@example.com"
            />
          </div>
        </div>

        {/* Additional Information */}
        <div className="bg-gray-50 p-4 rounded-lg">
          <h4 className="text-sm font-medium text-gray-700 mb-2">Billing Information</h4>
          <ul className="text-sm text-gray-600 space-y-1">
            <li>• Standard payment terms are Net 30</li>
            <li>• Invoices will be sent electronically to the AP contact email</li>
            <li>• You can update billing information anytime from your account settings</li>
            <li>• For questions about billing, contact our accounts team</li>
          </ul>
        </div>
      </div>
    </div>
  );
}