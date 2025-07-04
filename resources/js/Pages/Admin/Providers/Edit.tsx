import React, { useState, useEffect } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { FiArrowLeft, FiSave, FiUser, FiMapPin, FiCreditCard, FiPhone, FiPrinter, FiShield } from 'react-icons/fi';
import axios from 'axios';

interface EditProviderProps {
  provider: any;
  organizations: any[];
  states: Array<{ code: string; name: string }>;
}

export default function EditProvider({ provider, organizations, states }: EditProviderProps) {
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const { data, setData, put, processing, errors, setError, clearErrors } = useForm({
    // Basic Info
    first_name: provider.first_name || '',
    last_name: provider.last_name || '',
    credentials: provider.credentials || '',
    email: provider.email || '',

    // Professional Info
    npi_number: provider.npi_number || '',
    license_number: provider.license_number || '',
    license_state: provider.license_state || '',
    license_expiry: provider.license_expiry || '',

    // Provider Profile Info (including new fields)
    specialty: provider.provider_profile?.specialty || '',
    tax_id: provider.provider_profile?.tax_id || '',
    ptan: provider.provider_profile?.ptan || '',
    medicaid_number: provider.provider_profile?.medicaid_number || '',
    phone: provider.provider_profile?.phone || '',
    fax: provider.provider_profile?.fax || '',

    // Organization
    current_organization_id: provider.current_organization_id || '',
    is_verified: provider.is_verified || false,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    clearErrors();

    try {
      // First, refresh the CSRF token
      await axios.get('/sanctum/csrf-cookie');
      
      // Then submit the form
      put(`/admin/providers/${provider.id}`, {
        preserveScroll: true,
        onError: (errors) => {
          // Handle CSRF token expiration
          if (errors.message && errors.message.includes('419')) {
            // Refresh the page to get a new CSRF token
            window.location.reload();
          }
        },
        onSuccess: () => {
          // Optionally show a success message
          router.visit('/admin/providers', {
            preserveScroll: true,
          });
        }
      });
    } catch (error) {
      console.error('Error submitting form:', error);
      setError('submit', 'An error occurred. Please try again.');
    }
  };

  // Refresh CSRF token periodically to prevent expiration
  useEffect(() => {
    // Refresh CSRF token every 20 minutes (session lifetime is 30 minutes)
    const refreshToken = async () => {
      try {
        await axios.get('/sanctum/csrf-cookie');
      } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
      }
    };

    // Initial refresh
    refreshToken();

    // Set up interval
    const interval = setInterval(refreshToken, 20 * 60 * 1000); // 20 minutes

    return () => clearInterval(interval);
  }, []);

  return (
    <MainLayout>
      <Head title={`Edit Provider - ${provider.name}`} />

      <div className="max-w-4xl mx-auto p-6">
        {/* Header */}
        <div className="mb-8">
          <Link
            href="/admin/providers"
            className={cn(
              "inline-flex items-center text-sm mb-4",
              t.text.secondary,
              "hover:text-opacity-80 transition-colors"
            )}
          >
            <FiArrowLeft className="mr-2" />
            Back to Providers
          </Link>
          <h1 className={cn("text-3xl font-bold", t.text.primary)}>Edit Provider</h1>
          <p className={cn("mt-1", t.text.secondary)}>Update provider information</p>
        </div>

        {/* Global Error Message */}
        {errors.submit && (
          <div className={cn(
            "mb-6 p-4 rounded-lg border",
            t.status.error,
            "bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800"
          )}>
            <p className="text-sm font-medium">Error: {errors.submit}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <div className={cn("p-6 rounded-lg", t.glass.card)}>
            <h2 className={cn("text-lg font-semibold mb-4 flex items-center", t.text.primary)}>
              <FiUser className="w-5 h-5 mr-2" />
              Basic Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  First Name *
                </label>
                <input
                  type="text"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.first_name && t.input.error
                  )}
                  required
                  placeholder="John"
                />
                {errors.first_name && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.first_name}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Last Name *
                </label>
                <input
                  type="text"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.last_name && t.input.error
                  )}
                  required
                  placeholder="Doe"
                />
                {errors.last_name && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.last_name}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Credentials
                </label>
                <input
                  type="text"
                  value={data.credentials}
                  onChange={(e) => setData('credentials', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.credentials && t.input.error
                  )}
                  placeholder="MD, DO, DPM, NP, etc."
                />
                {errors.credentials && (
                  <p className={cn("mt-1 text-sm", t.status.error)}>{errors.credentials}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Professional credentials (e.g., MD, DO, DPM, NP, PA)
                </p>
              </div>

              <div className="md:col-span-2">
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Email Address *
                </label>
                <input
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.email && t.input.error
                  )}
                  required
                  placeholder="provider@example.com"
                />
                {errors.email && (
                  <p className={cn("mt-1 text-sm", t.status.error)}>{errors.email}</p>
                )}
              </div>
            </div>
          </div>

          {/* Contact Information (NEW SECTION) */}
          <div className={cn("p-6 rounded-lg", t.glass.card)}>
            <h2 className={cn("text-lg font-semibold mb-4 flex items-center", t.text.primary)}>
              <FiPhone className="w-5 h-5 mr-2" />
              Contact Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Phone Number
                </label>
                <input
                  type="tel"
                  value={data.phone}
                  onChange={(e) => setData('phone', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.phone && t.input.error
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.phone && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.phone}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Provider's direct phone number
                </p>
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  <FiPrinter className="inline w-4 h-4 mr-1" />
                  Fax Number
                </label>
                <input
                  type="tel"
                  value={data.fax}
                  onChange={(e) => setData('fax', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.fax && t.input.error
                  )}
                  placeholder="(555) 123-4568"
                />
                {errors.fax && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.fax}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Provider's fax number for documents
                </p>
              </div>
            </div>
          </div>

          {/* Professional Information */}
          <div className={cn("p-6 rounded-lg", t.glass.card)}>
            <h2 className={cn("text-lg font-semibold mb-4 flex items-center", t.text.primary)}>
              <FiCreditCard className="w-5 h-5 mr-2" />
              Professional Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Medical Specialty
                </label>
                <input
                  type="text"
                  value={data.specialty}
                  onChange={(e) => setData('specialty', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.specialty && t.input.error
                  )}
                  placeholder="Wound Care Specialist"
                />
                {errors.specialty && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.specialty}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Physician NPI
                </label>
                <input
                  type="text"
                  value={data.npi_number}
                  onChange={(e) => setData('npi_number', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.npi_number && t.input.error
                  )}
                  placeholder="1234567890"
                />
                {errors.npi_number && (
                  <p className={cn("mt-1 text-sm", t.status.error)}>{errors.npi_number}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  10-digit National Provider Identifier
                </p>
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Medical License Number
                </label>
                <input
                  type="text"
                  value={data.license_number}
                  onChange={(e) => setData('license_number', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.license_number && t.input.error
                  )}
                  placeholder="12345"
                />
                {errors.license_number && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.license_number}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  License Issuing State
                </label>
                <select
                  value={data.license_state}
                  onChange={(e) => setData('license_state', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.license_state && t.input.error
                  )}
                >
                  <option value="">Select a state...</option>
                  {states.map(state => (
                    <option key={state.code} value={state.code}>{state.name}</option>
                  ))}
                </select>
                {errors.license_state && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.license_state}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  License Expiration Date
                </label>
                <input
                  type="date"
                  value={data.license_expiry}
                  onChange={(e) => setData('license_expiry', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.license_expiry && t.input.error
                  )}
                />
                {errors.license_expiry && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.license_expiry}</p>
                )}
              </div>
            </div>
          </div>

          {/* Billing Information (NEW SECTION) */}
          <div className={cn("p-6 rounded-lg", t.glass.card)}>
            <h2 className={cn("text-lg font-semibold mb-4 flex items-center", t.text.primary)}>
              <FiShield className="w-5 h-5 mr-2" />
              Billing & Insurance Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Tax ID / EIN
                </label>
                <input
                  type="text"
                  value={data.tax_id}
                  onChange={(e) => setData('tax_id', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.tax_id && t.input.error
                  )}
                  placeholder="12-3456789"
                />
                {errors.tax_id && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.tax_id}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Provider's Tax Identification Number
                </p>
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Physician PTAN
                </label>
                <input
                  type="text"
                  value={data.ptan}
                  onChange={(e) => setData('ptan', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.ptan && t.input.error
                  )}
                  placeholder="A12345"
                />
                {errors.ptan && (
                  <p className={cn("mt-1 text-sm", t.status.error)}>{errors.ptan}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Provider Transaction Access Number for Medicare
                </p>
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Medicaid Number
                </label>
                <input
                  type="text"
                  value={data.medicaid_number}
                  onChange={(e) => setData('medicaid_number', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.medicaid_number && t.input.error
                  )}
                  placeholder="MED123456"
                />
                {errors.medicaid_number && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.medicaid_number}</p>
                )}
                <p className={cn("mt-1 text-xs", t.text.secondary)}>
                  Provider's Medicaid identification number
                </p>
              </div>
            </div>
          </div>

          {/* Organization Assignment */}
          <div className={cn("p-6 rounded-lg", t.glass.card)}>
            <h2 className={cn("text-lg font-semibold mb-4 flex items-center", t.text.primary)}>
              <FiMapPin className="w-5 h-5 mr-2" />
              Organization Assignment
            </h2>

            <div className="space-y-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Current Organization
                </label>
                <select
                  value={data.current_organization_id}
                  onChange={(e) => setData('current_organization_id', e.target.value)}
                  className={cn(
                    "w-full px-3 py-2 rounded-lg",
                    t.input.base,
                    t.input.border,
                    errors.current_organization_id && t.input.error
                  )}
                >
                  <option value="">Select an organization...</option>
                  {organizations.map(org => (
                    <option key={org.id} value={org.id}>{org.name}</option>
                  ))}
                </select>
                {errors.current_organization_id && (
                  <p className={cn("mt-1 text-sm", t.text.error)}>{errors.current_organization_id}</p>
                )}
              </div>

              <div className="flex items-center">
                <input
                  type="checkbox"
                  id="is_verified"
                  checked={data.is_verified}
                  onChange={(e) => setData('is_verified', e.target.checked)}
                  className={cn(
                    "w-4 h-4 rounded",
                    t.checkbox.base,
                    t.checkbox.checked
                  )}
                />
                <label
                  htmlFor="is_verified"
                  className={cn("ml-2 text-sm", t.text.primary)}
                >
                  Mark as verified provider
                </label>
              </div>
              <p className={cn("text-sm", t.text.secondary)}>
                Check this if the provider's credentials have been manually verified.
              </p>
            </div>
          </div>

          {/* Submit Button */}
          <div className="flex justify-end">
            <button
              type="submit"
              disabled={processing}
              className={cn(
                "inline-flex items-center px-6 py-3 rounded-lg font-medium",
                "transition-all duration-200",
                t.button.primary.base,
                t.button.primary.hover,
                processing && "opacity-50 cursor-not-allowed"
              )}
            >
              {processing ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Saving...
                </>
              ) : (
                <>
                  <FiSave className="w-4 h-4 mr-2" />
                  Save Changes
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}