import React from 'react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { ArrowLeft } from 'lucide-react';

interface Props {
  facility?: {
    id: number;
    name: string;
    facility_type: string;
    address: string;
    city: string;
    state: string;
    zip_code: string;
    phone?: string;
    email?: string;
    npi?: string;
    business_hours?: string;
    active: boolean;
    coordinating_sales_rep_id?: number;
    organization_id: number;
    contact_name?: string;
    contact_phone?: string;
    contact_email?: string;
    contact_fax?: string;
  };
  organizations: Array<{
    id: number;
    name: string;
  }>;
  salesReps?: Array<{
    id: number;
    name: string;
  }>;
  isEdit?: boolean;
}

const FacilityForm: React.FC<Props> = ({ facility, organizations, salesReps = [], isEdit = false }) => {
  const [formData, setFormData] = React.useState({
    name: facility?.name || '',
    facility_type: facility?.facility_type || '',
    address: facility?.address || '',
    city: facility?.city || '',
    state: facility?.state || '',
    zip_code: facility?.zip_code || '',
    phone: facility?.phone || '',
    email: facility?.email || '',
    npi: facility?.npi || '',
    business_hours: facility?.business_hours || '',
    active: facility?.active ?? true,
    coordinating_sales_rep_id: facility?.coordinating_sales_rep_id || '',
    organization_id: facility?.organization_id || '',
    contact_name: facility?.contact_name || '',
    contact_phone: facility?.contact_phone || '',
    contact_email: facility?.contact_email || '',
    contact_fax: facility?.contact_fax || '',
  });

  const [errors, setErrors] = React.useState<Record<string, string>>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});

    if (isEdit && facility) {
      router.put(`/admin/facilities/${facility.id}`, formData, {
        onSuccess: () => {
          router.visit('/admin/facilities');
        },
        onError: (errors) => {
          setErrors(errors as Record<string, string>);
        }
      });
    } else {
      router.post('/admin/facilities', formData, {
        onSuccess: () => {
          router.visit('/admin/facilities');
        },
        onError: (errors) => {
          setErrors(errors as Record<string, string>);
        }
      });
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  return (
    <MainLayout title={isEdit ? 'Edit Facility' : 'Create Facility'}>
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-6">
            <button
              onClick={() => router.visit('/admin/facilities')}
              className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Facilities
            </button>
          </div>

          <div className="bg-white shadow rounded-lg">
            <div className="px-4 py-5 sm:p-6">
              <h3 className="text-lg leading-6 font-medium text-gray-900 mb-6">
                {isEdit ? 'Edit Facility' : 'Create New Facility'}
              </h3>

              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  {/* Organization Selection */}
                  <div>
                    <label htmlFor="organization_id" className="block text-sm font-medium text-gray-700">
                      Organization
                    </label>
                    <select
                      id="organization_id"
                      name="organization_id"
                      value={formData.organization_id}
                      onChange={handleChange}
                      className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border ${
                        errors.organization_id ? 'border-red-300' : 'border-gray-300'
                      } focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md`}
                      required
                    >
                      <option value="">Select an organization</option>
                      {organizations.map(org => (
                        <option key={org.id} value={org.id}>
                          {org.name}
                        </option>
                      ))}
                    </select>
                    {errors.organization_id && (
                      <p className="mt-2 text-sm text-red-600">{errors.organization_id}</p>
                    )}
                  </div>

                  {/* Facility Type */}
                  <div>
                    <label htmlFor="facility_type" className="block text-sm font-medium text-gray-700">
                      Facility Type
                    </label>
                    <select
                      id="facility_type"
                      name="facility_type"
                      value={formData.facility_type}
                      onChange={handleChange}
                      className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border ${
                        errors.facility_type ? 'border-red-300' : 'border-gray-300'
                      } focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md`}
                      required
                    >
                      <option value="">Select a facility type</option>
                      <option value="clinic">Clinic</option>
                      <option value="hospital_outpatient">Hospital Outpatient</option>
                      <option value="wound_center">Wound Center</option>
                      <option value="asc">Ambulatory Surgery Center</option>
                    </select>
                    {errors.facility_type && (
                      <p className="mt-2 text-sm text-red-600">{errors.facility_type}</p>
                    )}
                  </div>

                  {/* Facility Name */}
                  <div>
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                      Facility Name
                    </label>
                    <input
                      type="text"
                      name="name"
                      id="name"
                      value={formData.name}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.name ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                      required
                    />
                    {errors.name && (
                      <p className="mt-2 text-sm text-red-600">{errors.name}</p>
                    )}
                  </div>

                  {/* NPI Number */}
                  <div>
                    <label htmlFor="npi" className="block text-sm font-medium text-gray-700">
                      NPI Number
                    </label>
                    <input
                      type="text"
                      name="npi"
                      id="npi"
                      value={formData.npi}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.npi ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.npi && (
                      <p className="mt-2 text-sm text-red-600">{errors.npi}</p>
                    )}
                  </div>

                  {/* Address */}
                  <div className="sm:col-span-2">
                    <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                      Street Address
                    </label>
                    <input
                      type="text"
                      name="address"
                      id="address"
                      value={formData.address}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.address ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                      required
                    />
                    {errors.address && (
                      <p className="mt-2 text-sm text-red-600">{errors.address}</p>
                    )}
                  </div>

                  {/* City */}
                  <div>
                    <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                      City
                    </label>
                    <input
                      type="text"
                      name="city"
                      id="city"
                      value={formData.city}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.city ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                      required
                    />
                    {errors.city && (
                      <p className="mt-2 text-sm text-red-600">{errors.city}</p>
                    )}
                  </div>

                  {/* State */}
                  <div>
                    <label htmlFor="state" className="block text-sm font-medium text-gray-700">
                      State
                    </label>
                    <input
                      type="text"
                      name="state"
                      id="state"
                      value={formData.state}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.state ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                      required
                    />
                    {errors.state && (
                      <p className="mt-2 text-sm text-red-600">{errors.state}</p>
                    )}
                  </div>

                  {/* Zip Code */}
                  <div>
                    <label htmlFor="zip_code" className="block text-sm font-medium text-gray-700">
                      Zip Code
                    </label>
                    <input
                      type="text"
                      name="zip_code"
                      id="zip_code"
                      value={formData.zip_code}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.zip_code ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                      required
                    />
                    {errors.zip_code && (
                      <p className="mt-2 text-sm text-red-600">{errors.zip_code}</p>
                    )}
                  </div>

                  {/* Phone */}
                  <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                      Phone
                    </label>
                    <input
                      type="tel"
                      name="phone"
                      id="phone"
                      value={formData.phone}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.phone ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.phone && (
                      <p className="mt-2 text-sm text-red-600">{errors.phone}</p>
                    )}
                  </div>

                  {/* Email */}
                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                      Email
                    </label>
                    <input
                      type="email"
                      name="email"
                      id="email"
                      value={formData.email}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.email ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.email && (
                      <p className="mt-2 text-sm text-red-600">{errors.email}</p>
                    )}
                  </div>

                  {/* Contact Name */}
                  <div>
                    <label htmlFor="contact_name" className="block text-sm font-medium text-gray-700">
                      Contact Name
                    </label>
                    <input
                      type="text"
                      name="contact_name"
                      id="contact_name"
                      value={formData.contact_name}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.contact_name ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.contact_name && (
                      <p className="mt-2 text-sm text-red-600">{errors.contact_name}</p>
                    )}
                  </div>

                  {/* Contact Phone */}
                  <div>
                    <label htmlFor="contact_phone" className="block text-sm font-medium text-gray-700">
                      Contact Phone
                    </label>
                    <input
                      type="tel"
                      name="contact_phone"
                      id="contact_phone"
                      value={formData.contact_phone}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.contact_phone ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.contact_phone && (
                      <p className="mt-2 text-sm text-red-600">{errors.contact_phone}</p>
                    )}
                  </div>

                  {/* Contact Email */}
                  <div>
                    <label htmlFor="contact_email" className="block text-sm font-medium text-gray-700">
                      Contact Email
                    </label>
                    <input
                      type="email"
                      name="contact_email"
                      id="contact_email"
                      value={formData.contact_email}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.contact_email ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.contact_email && (
                      <p className="mt-2 text-sm text-red-600">{errors.contact_email}</p>
                    )}
                  </div>

                  {/* Contact Fax */}
                  <div>
                    <label htmlFor="contact_fax" className="block text-sm font-medium text-gray-700">
                      Contact Fax
                    </label>
                    <input
                      type="tel"
                      name="contact_fax"
                      id="contact_fax"
                      value={formData.contact_fax}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.contact_fax ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.contact_fax && (
                      <p className="mt-2 text-sm text-red-600">{errors.contact_fax}</p>
                    )}
                  </div>

                  {/* Business Hours */}
                  <div className="sm:col-span-2">
                    <label htmlFor="business_hours" className="block text-sm font-medium text-gray-700">
                      Business Hours
                    </label>
                    <textarea
                      name="business_hours"
                      id="business_hours"
                      rows={3}
                      value={formData.business_hours}
                      onChange={handleChange}
                      className={`mt-1 block w-full border ${
                        errors.business_hours ? 'border-red-300' : 'border-gray-300'
                      } rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm`}
                    />
                    {errors.business_hours && (
                      <p className="mt-2 text-sm text-red-600">{errors.business_hours}</p>
                    )}
                  </div>

                  {/* Coordinating Sales Rep */}
                  <div>
                    <label htmlFor="coordinating_sales_rep_id" className="block text-sm font-medium text-gray-700">
                      Coordinating Sales Representative
                    </label>
                    <select
                      id="coordinating_sales_rep_id"
                      name="coordinating_sales_rep_id"
                      value={formData.coordinating_sales_rep_id}
                      onChange={handleChange}
                      className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border ${
                        errors.coordinating_sales_rep_id ? 'border-red-300' : 'border-gray-300'
                      } focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md`}
                    >
                      <option value="">Select a sales representative</option>
                      {salesReps.map(rep => (
                        <option key={rep.id} value={rep.id}>
                          {rep.name}
                        </option>
                      ))}
                    </select>
                    {errors.coordinating_sales_rep_id && (
                      <p className="mt-2 text-sm text-red-600">{errors.coordinating_sales_rep_id}</p>
                    )}
                  </div>

                  {/* Active Status */}
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      name="active"
                      id="active"
                      checked={formData.active}
                      onChange={(e) => setFormData(prev => ({ ...prev, active: e.target.checked }))}
                      className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label htmlFor="active" className="ml-2 block text-sm text-gray-900">
                      Active
                    </label>
                  </div>
                </div>

                <div className="flex justify-end">
                  <button
                    type="button"
                    onClick={() => router.visit('/admin/facilities')}
                    className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    {isEdit ? 'Update Facility' : 'Create Facility'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default FacilityForm;
