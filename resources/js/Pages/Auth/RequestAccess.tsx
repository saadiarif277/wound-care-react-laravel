import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Logo from '@/Components/Logo/Logo';
import {
  FiUser,
  FiMail,
  FiPhone,
  FiFileText,
  FiArrowLeft,
  FiSend,
  FiCheckCircle,
  FiAlertCircle
} from 'react-icons/fi';

interface RoleField {
  label: string;
  required: boolean;
  type: 'text' | 'email' | 'textarea' | 'number' | 'select';
}

interface Props {
  roles: Record<string, string>;
}

export default function RequestAccessPage({ roles }: Props) {
  const [roleFields, setRoleFields] = useState<Record<string, RoleField>>({});
  const [loadingFields, setLoadingFields] = useState(false);

  const { data, setData, errors, post, processing } = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    requested_role: '',
    request_notes: '',

    // Provider fields
    npi_number: '',
    medical_license: '',
    license_state: '',
    specialization: '',
    facility_name: '',
    facility_address: '',

    // Office Manager fields
    manager_name: '',
    manager_email: '',

    // MSC Rep fields
    territory: '',
    manager_contact: '',
    experience_years: '',

    // MSC SubRep fields
    main_rep_name: '',
    main_rep_email: '',

    // MSC Admin fields
    department: '',
    supervisor_name: '',
    supervisor_email: '',
  });

  // Fetch role-specific fields when role changes
  useEffect(() => {
    if (data.requested_role) {
      setLoadingFields(true);
      fetch(`/api/access-requests/role-fields?role=${data.requested_role}`)
        .then(response => response.json())
        .then(result => {
          setRoleFields(result.fields || {});
        })
        .catch(error => {
          console.error('Error fetching role fields:', error);
        })
        .finally(() => {
          setLoadingFields(false);
        });
    } else {
      setRoleFields({});
    }
  }, [data.requested_role]);

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post(route('access-requests.store'));
  }

  const US_STATES = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  const renderField = (fieldName: string, field: RoleField) => {
    const fieldError = errors[fieldName as keyof typeof errors];
    const fieldValue = data[fieldName as keyof typeof data];

    const baseClasses = `w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
      fieldError
        ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
        : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
    }`;

    switch (field.type) {
      case 'textarea':
        return (
          <textarea
            id={fieldName}
            value={fieldValue as string}
            onChange={(e) => setData(fieldName as any, e.target.value)}
            placeholder={field.label}
            required={field.required}
            disabled={processing}
            className={baseClasses}
            rows={3}
          />
        );

      case 'number':
        return (
          <input
            id={fieldName}
            type="number"
            value={fieldValue as string}
            onChange={(e) => setData(fieldName as any, e.target.value)}
            placeholder={field.label}
            required={field.required}
            disabled={processing}
            className={baseClasses}
            min="0"
            max="50"
          />
        );

      case 'select':
        if (fieldName === 'license_state') {
          return (
            <select
              id={fieldName}
              value={fieldValue as string}
              onChange={(e) => setData(fieldName as any, e.target.value)}
              required={field.required}
              disabled={processing}
              className={baseClasses}
            >
              <option value="">Select State</option>
              {US_STATES.map(state => (
                <option key={state} value={state}>{state}</option>
              ))}
            </select>
          );
        }
        return (
          <input
            id={fieldName}
            type="text"
            value={fieldValue as string}
            onChange={(e) => setData(fieldName as any, e.target.value)}
            placeholder={field.label}
            required={field.required}
            disabled={processing}
            className={baseClasses}
          />
        );

      default:
        return (
          <input
            id={fieldName}
            type={field.type}
            value={fieldValue as string}
            onChange={(e) => setData(fieldName as any, e.target.value)}
            placeholder={field.label}
            required={field.required}
            disabled={processing}
            className={baseClasses}
          />
        );
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center px-4 py-8" style={{
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
    }}>
      <Head title="MSC Wound Portal - Request Access" />

      <div className="w-full max-w-2xl">
        <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
          {/* Header */}
          <div className="px-8 pt-8 pb-6 text-center bg-gradient-to-br from-white to-gray-50">
            <div className="flex justify-center mb-4">
              <Logo className="h-12 w-auto" />
            </div>
            <h1 className="text-2xl font-bold text-gray-900">
              Request Access
            </h1>
            <p className="text-sm text-gray-600 mt-1">
              Complete this form to request access to MSC Wound Portal
            </p>
          </div>

          {/* Form */}
          <div className="px-8 pb-8">
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Personal Information */}
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                  <FiUser className="mr-2 text-blue-600" />
                  Personal Information
                </h3>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="first_name" className="block text-sm font-medium text-gray-700 mb-2">
                      First Name *
                    </label>
                    <input
                      id="first_name"
                      type="text"
                      className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                        errors.first_name
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                      value={data.first_name}
                      onChange={(e) => setData('first_name', e.target.value)}
                      placeholder="Enter your first name"
                      disabled={processing}
                      required
                    />
                    {errors.first_name && (
                      <p className="mt-2 text-sm text-red-600 flex items-center">
                        <FiAlertCircle className="mr-1" />
                        {errors.first_name}
                      </p>
                    )}
                  </div>

                  <div>
                    <label htmlFor="last_name" className="block text-sm font-medium text-gray-700 mb-2">
                      Last Name *
                    </label>
                    <input
                      id="last_name"
                      type="text"
                      className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                        errors.last_name
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                      }`}
                      value={data.last_name}
                      onChange={(e) => setData('last_name', e.target.value)}
                      placeholder="Enter your last name"
                      disabled={processing}
                      required
                    />
                    {errors.last_name && (
                      <p className="mt-2 text-sm text-red-600 flex items-center">
                        <FiAlertCircle className="mr-1" />
                        {errors.last_name}
                      </p>
                    )}
                  </div>

                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                      Email Address *
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <FiMail className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        id="email"
                        type="email"
                        className={`w-full pl-10 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                          errors.email
                            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                            : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                        }`}
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="Enter your email"
                        disabled={processing}
                        required
                      />
                    </div>
                    {errors.email && (
                      <p className="mt-2 text-sm text-red-600 flex items-center">
                        <FiAlertCircle className="mr-1" />
                        {errors.email}
                      </p>
                    )}
                  </div>

                  <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-2">
                      Phone Number
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <FiPhone className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        id="phone"
                        type="tel"
                        className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        placeholder="Enter your phone number"
                        disabled={processing}
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Role Selection */}
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                  <FiCheckCircle className="mr-2 text-blue-600" />
                  Role Selection
                </h3>

                <div>
                  <label htmlFor="requested_role" className="block text-sm font-medium text-gray-700 mb-2">
                    Requested Role *
                  </label>
                  <select
                    id="requested_role"
                    className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                      errors.requested_role
                        ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                    }`}
                    value={data.requested_role}
                    onChange={(e) => setData('requested_role', e.target.value)}
                    disabled={processing}
                    required
                  >
                    <option value="">Select your role</option>
                    {Object.entries(roles).map(([key, label]) => (
                      <option key={key} value={key}>{label}</option>
                    ))}
                  </select>
                  {errors.requested_role && (
                    <p className="mt-2 text-sm text-red-600 flex items-center">
                      <FiAlertCircle className="mr-1" />
                      {errors.requested_role}
                    </p>
                  )}
                </div>
              </div>

              {/* Role-Specific Fields */}
              {data.requested_role && (
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4">
                    {roles[data.requested_role]} Information
                  </h3>

                  {loadingFields ? (
                    <div className="text-center py-8">
                      <div className="inline-flex items-center">
                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading role-specific fields...
                      </div>
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {Object.entries(roleFields).map(([fieldName, field]) => (
                        <div key={fieldName} className={field.type === 'textarea' ? 'md:col-span-2' : ''}>
                          <label htmlFor={fieldName} className="block text-sm font-medium text-gray-700 mb-2">
                            {field.label} {field.required && '*'}
                          </label>
                          {renderField(fieldName, field)}
                          {errors[fieldName as keyof typeof errors] && (
                            <p className="mt-2 text-sm text-red-600 flex items-center">
                              <FiAlertCircle className="mr-1" />
                              {errors[fieldName as keyof typeof errors]}
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Additional Notes */}
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                  <FiFileText className="mr-2 text-blue-600" />
                  Additional Information
                </h3>

                <div>
                  <label htmlFor="request_notes" className="block text-sm font-medium text-gray-700 mb-2">
                    Additional Notes (Optional)
                  </label>
                  <textarea
                    id="request_notes"
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    value={data.request_notes}
                    onChange={(e) => setData('request_notes', e.target.value)}
                    placeholder="Any additional information that would help us process your request..."
                    disabled={processing}
                    rows={3}
                  />
                </div>
              </div>

              {/* Submit Button */}
              <div className="flex flex-col sm:flex-row gap-4 pt-4">
                <a
                  href={route('login')}
                  className="flex items-center justify-center px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold text-sm hover:bg-gray-50 transition-colors"
                >
                  <FiArrowLeft className="mr-2 h-5 w-5" />
                  Back to Login
                </a>

                <button
                  type="submit"
                  disabled={processing || !data.requested_role}
                  className="flex-1 py-3 px-6 rounded-lg text-white font-semibold text-sm transition-all duration-200 disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center focus:outline-none focus:ring-2 focus:ring-offset-2"
                  style={{
                    backgroundColor: '#1822cf',
                    '--tw-ring-color': '#1822cf'
                  } as React.CSSProperties}
                >
                  {processing ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Submitting Request...
                    </>
                  ) : (
                    <>
                      <FiSend className="mr-2 h-5 w-5" />
                      Submit Access Request
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}
