import React, { useState } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import { CheckboxInput } from '@/Components/Form/CheckboxInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { FiArrowLeft, FiSave, FiUser, FiMapPin, FiCreditCard, FiMail, FiShield } from 'react-icons/fi';

interface CreateProviderProps {
  organizations: any[];
  states: Array<{ code: string; name: string }>;
}

export default function CreateProvider({ organizations, states }: CreateProviderProps) {
  const { theme } = useTheme();
  const t = themes[theme];

  const { data, setData, post, processing, errors } = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    npi_number: '',
    dea_number: '',
    license_number: '',
    license_state: '',
    license_expiry: '',
    current_organization_id: '',
    is_verified: false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/admin/providers');
  };

  return (
    <MainLayout>
      <Head title="Create Provider" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <Link
              href="/admin/providers"
              className={cn(
                "inline-flex items-center text-sm transition-colors",
                t.text.secondary,
                "hover:" + t.text.primary
              )}
            >
              <FiArrowLeft className="mr-2" />
              Back to Providers
            </Link>
            <h1 className={cn("mt-2 text-3xl font-bold", t.text.primary)}>
              Create Provider
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Add a new healthcare provider to the system
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-blue-500/20 mr-3">
                <FiUser className="w-5 h-5 text-blue-400" />
              </div>
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
                className={cn(t.input.base, t.input.focus)}
              />

              <TextInput
                label="Last Name *"
                value={data.last_name}
                onChange={(e) => setData('last_name', e.target.value)}
                error={errors.last_name}
                required
                placeholder="Doe"
                className={cn(t.input.base, t.input.focus)}
              />

              <TextInput
                label="Email Address *"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
                required
                placeholder="provider@example.com"
                className={cn(t.input.base, t.input.focus)}
              />

              <TextInput
                label="Phone Number"
                type="tel"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
                error={errors.phone}
                placeholder="(555) 123-4567"
                className={cn(t.input.base, t.input.focus)}
              />
            </div>
          </div>

          {/* Account Setup */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-emerald-500/20 mr-3">
                <FiMail className="w-5 h-5 text-emerald-400" />
              </div>
              Account Setup
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <TextInput
                label="Password *"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
                required
                placeholder="Enter a secure password"
                className={cn(t.input.base, t.input.focus)}
              />

              <TextInput
                label="Confirm Password *"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                error={errors.password_confirmation}
                required
                placeholder="Re-enter the password"
                className={cn(t.input.base, t.input.focus)}
              />
            </div>

            <div className={cn("mt-4 p-4 rounded-xl", t.glass.frost)}>
              <p className={cn("text-sm", t.text.secondary)}>
                The provider will receive their login credentials and can manage their profile after account creation.
              </p>
            </div>
          </div>

          {/* Professional Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-purple-500/20 mr-3">
                <FiCreditCard className="w-5 h-5 text-purple-400" />
              </div>
              Professional Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-2">
                <TextInput
                  label="NPI Number"
                  value={data.npi_number}
                  onChange={(e) => setData('npi_number', e.target.value)}
                  error={errors.npi_number}
                  placeholder="1234567890"
                  maxLength={10}
                  className={cn(t.input.base, t.input.focus)}
                />
                <p className={cn("text-xs", t.text.tertiary)}>
                  10-digit National Provider Identifier issued by CMS for healthcare providers
                </p>
              </div>

              <div className="space-y-2">
                <TextInput
                  label="DEA Number"
                  value={data.dea_number}
                  onChange={(e) => setData('dea_number', e.target.value)}
                  error={errors.dea_number}
                  placeholder="AB1234567"
                  className={cn(t.input.base, t.input.focus)}
                />
                <p className={cn("text-xs", t.text.tertiary)}>
                  Drug Enforcement Administration registration for prescribing controlled substances
                </p>
              </div>

              <div className="space-y-2">
                <TextInput
                  label="Medical License Number"
                  value={data.license_number}
                  onChange={(e) => setData('license_number', e.target.value)}
                  error={errors.license_number}
                  placeholder="12345"
                  className={cn(t.input.base, t.input.focus)}
                />
                <p className={cn("text-xs", t.text.tertiary)}>
                  State medical board license number for practicing medicine
                </p>
              </div>

              <div className="space-y-2">
                <SelectInput
                  label="License Issuing State"
                  value={data.license_state}
                  onChange={(e) => setData('license_state', e.target.value)}
                  error={errors.license_state}
                  className={cn(t.input.select || t.input.base, t.input.focus)}
                >
                  <option value="">Select a state...</option>
                  {states.map(state => (
                    <option key={state.code} value={state.code}>{state.name}</option>
                  ))}
                </SelectInput>
                <p className={cn("text-xs", t.text.tertiary)}>
                  State where medical license was issued
                </p>
              </div>

              <div className="space-y-2">
                <TextInput
                  label="License Expiration Date"
                  type="date"
                  value={data.license_expiry}
                  onChange={(e) => setData('license_expiry', e.target.value)}
                  error={errors.license_expiry}
                  className={cn(t.input.base, t.input.focus)}
                />
                <p className={cn("text-xs", t.text.tertiary)}>
                  Date when medical license expires and needs renewal
                </p>
              </div>
            </div>

            <div className={cn("mt-6 p-4 rounded-xl", t.glass.frost)}>
              <div className="flex items-start space-x-3">
                <CheckboxInput
                  label=""
                  checked={data.is_verified}
                  onChange={(e) => setData('is_verified', e.target.checked)}
                  className="mt-1"
                />
                <div>
                  <label className={cn("text-sm font-medium", t.text.primary)}>
                    Mark as verified provider
                  </label>
                  <p className={cn("text-xs mt-1", t.text.secondary)}>
                    Check this if the provider's credentials have been manually verified.
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Organization Assignment */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-amber-500/20 mr-3">
                <FiMapPin className="w-5 h-5 text-amber-400" />
              </div>
              Organization Assignment
            </h2>

            <SelectInput
              label="Current Organization"
              value={data.current_organization_id}
              onChange={(e) => setData('current_organization_id', e.target.value)}
              error={errors.current_organization_id}
              className={cn(t.input.select || t.input.base, t.input.focus)}
            >
              <option value="">Select an organization...</option>
              {organizations.map(org => (
                <option key={org.id} value={org.id}>{org.name}</option>
              ))}
            </SelectInput>
          </div>

          {/* Submit Button */}
          <div className="flex justify-end">
            <LoadingButton
              type="submit"
              loading={processing}
              className={cn(
                "px-8 py-3 rounded-xl font-semibold flex items-center gap-2 transition-all",
                t.button.primary.base,
                t.button.primary.hover
              )}
            >
              <FiSave className="w-5 h-5" />
              Create Provider
            </LoadingButton>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}
