import React, { useState } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card } from '@/Components/ui/card';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import { CheckboxInput } from '@/Components/Form/CheckboxInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { FiArrowLeft, FiSave, FiUser, FiMapPin, FiCreditCard } from 'react-icons/fi';

interface EditProviderProps {
  provider: any;
  organizations: any[];
  states: Array<{ code: string; name: string }>;
}

export default function EditProvider({ provider, organizations, states }: EditProviderProps) {
  const { data, setData, put, processing, errors } = useForm({
    first_name: provider.first_name || '',
    last_name: provider.last_name || '',
    email: provider.email || '',
    npi_number: provider.npi_number || '',
    dea_number: provider.dea_number || '',
    license_number: provider.license_number || '',
    license_state: provider.license_state || '',
    license_expiry: provider.license_expiry || '',
    current_organization_id: provider.current_organization_id || '',
    is_verified: provider.is_verified || false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/admin/providers/${provider.id}`);
  };

  return (
    <MainLayout>
      <Head title={`Edit Provider - ${provider.name}`} />

      <div className="max-w-4xl mx-auto">
        <div className="mb-8 flex items-center justify-between">
          <div>
            <Link
              href="/admin/providers"
              className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
            >
              <FiArrowLeft className="mr-2" />
              Back to Providers
            </Link>
            <h1 className="mt-2 text-3xl font-bold text-gray-900">Edit Provider</h1>
            <p className="mt-1 text-gray-600">Update provider information</p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <Card>
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center">
                <FiUser className="w-5 h-5 mr-2" />
                Basic Information
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TextInput
                  label="First Name *"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  error={errors.first_name}
                  required
                  placeholder="John"
                />

                <TextInput
                  label="Last Name *"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  error={errors.last_name}
                  required
                  placeholder="Doe"
                />

                <TextInput
                  label="Email Address *"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  error={errors.email}
                  required
                  placeholder="provider@example.com"
                />
              </div>
            </div>
          </Card>

          {/* Professional Information */}
          <Card>
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center">
                <FiCreditCard className="w-5 h-5 mr-2" />
                Professional Information
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <TextInput
                    label="NPI Number"
                    value={data.npi_number}
                    onChange={(e) => setData('npi_number', e.target.value)}
                    error={errors.npi_number}
                    placeholder="1234567890"
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    10-digit National Provider Identifier issued by CMS for healthcare providers
                  </p>
                </div>

                <div>
                  <TextInput
                    label="DEA Number"
                    value={data.dea_number}
                    onChange={(e) => setData('dea_number', e.target.value)}
                    error={errors.dea_number}
                    placeholder="AB1234567"
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Drug Enforcement Administration registration for prescribing controlled substances
                  </p>
                </div>

                <div>
                  <TextInput
                    label="Medical License Number"
                    value={data.license_number}
                    onChange={(e) => setData('license_number', e.target.value)}
                    error={errors.license_number}
                    placeholder="12345"
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    State medical board license number for practicing medicine
                  </p>
                </div>

                <div>
                  <SelectInput
                    label="License Issuing State"
                    value={data.license_state}
                    onChange={(e) => setData('license_state', e.target.value)}
                    error={errors.license_state}
                  >
                    <option value="">Select a state...</option>
                    {states.map(state => (
                      <option key={state.code} value={state.code}>{state.name}</option>
                    ))}
                  </SelectInput>
                  <p className="mt-1 text-xs text-gray-500">
                    State where medical license was issued
                  </p>
                </div>

                <div>
                  <TextInput
                    label="License Expiration Date"
                    type="date"
                    value={data.license_expiry}
                    onChange={(e) => setData('license_expiry', e.target.value)}
                    error={errors.license_expiry}
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Date when medical license expires and needs renewal
                  </p>
                </div>
              </div>

              <div className="mt-4">
                <CheckboxInput
                  label="Mark as verified provider"
                  checked={data.is_verified}
                  onChange={(e) => setData('is_verified', e.target.checked)}
                />
                <p className="mt-2 text-sm text-gray-600">
                  Check this if the provider's credentials have been manually verified.
                </p>
              </div>
            </div>
          </Card>

          {/* Organization Assignment */}
          <Card>
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center">
                <FiMapPin className="w-5 h-5 mr-2" />
                Organization Assignment
              </h2>

              <SelectInput
                label="Current Organization"
                value={data.current_organization_id}
                onChange={(e) => setData('current_organization_id', e.target.value)}
                error={errors.current_organization_id}
              >
                <option value="">Select an organization...</option>
                {organizations.map(org => (
                  <option key={org.id} value={org.id}>{org.name}</option>
                ))}
              </SelectInput>
            </div>
          </Card>

          {/* Submit Button */}
          <div className="flex justify-end">
            <LoadingButton
              type="submit"
              loading={processing}
              className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"
            >
              <FiSave className="w-4 h-4 mr-2" />
              Save Changes
            </LoadingButton>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}
