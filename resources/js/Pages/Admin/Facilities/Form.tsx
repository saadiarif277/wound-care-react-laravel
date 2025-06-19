import React from 'react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { ArrowLeft, Building, MapPin, User, Phone, Mail, Clock, Save, X } from 'lucide-react';

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
  const { theme } = useTheme();
  const t = themes[theme];

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
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <button
              onClick={() => router.visit('/admin/facilities')}
              className={cn(
                "inline-flex items-center text-sm transition-colors",
                t.text.secondary,
                "hover:" + t.text.primary
              )}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Facilities
            </button>
            <h1 className={cn("mt-2 text-3xl font-bold", t.text.primary)}>
              {isEdit ? 'Edit Facility' : 'Create New Facility'}
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              {isEdit ? 'Update facility information' : 'Add a new facility to the system'}
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-blue-500/20 mr-3">
                <Building className="w-5 h-5 text-blue-400" />
              </div>
              Basic Information
            </h2>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              {/* Organization Selection */}
              <div className="sm:col-span-2">
                <label htmlFor="organization_id" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Organization <span className="text-red-500">*</span>
                </label>
                <select
                  id="organization_id"
                  name="organization_id"
                  value={formData.organization_id}
                  onChange={handleChange}
                  className={cn(
                    t.input.select || t.input.base,
                    t.input.focus,
                    errors.organization_id ? "border-red-500" : ""
                  )}
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
                <label htmlFor="facility_type" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Facility Type <span className="text-red-500">*</span>
                </label>
                <select
                  id="facility_type"
                  name="facility_type"
                  value={formData.facility_type}
                  onChange={handleChange}
                  className={cn(
                    t.input.select || t.input.base,
                    t.input.focus,
                    errors.facility_type ? "border-red-500" : ""
                  )}
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
                <label htmlFor="name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Facility Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="name"
                  id="name"
                  value={formData.name}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.name ? "border-red-500" : ""
                  )}
                  required
                />
                {errors.name && (
                  <p className="mt-2 text-sm text-red-600">{errors.name}</p>
                )}
              </div>

              {/* NPI Number */}
              <div>
                <label htmlFor="npi" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  NPI Number
                </label>
                <input
                  type="text"
                  name="npi"
                  id="npi"
                  value={formData.npi}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.npi ? "border-red-500" : ""
                  )}
                  placeholder="10-digit NPI"
                  maxLength={10}
                />
                {errors.npi && (
                  <p className="mt-2 text-sm text-red-600">{errors.npi}</p>
                )}
              </div>

              {/* Sales Rep Assignment */}
              <div>
                <label htmlFor="coordinating_sales_rep_id" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Coordinating Sales Rep
                </label>
                <select
                  id="coordinating_sales_rep_id"
                  name="coordinating_sales_rep_id"
                  value={formData.coordinating_sales_rep_id}
                  onChange={handleChange}
                  className={cn(
                    t.input.select || t.input.base,
                    t.input.focus,
                    errors.coordinating_sales_rep_id ? "border-red-500" : ""
                  )}
                >
                  <option value="">Select a sales rep</option>
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
            </div>
          </div>

          {/* Address Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-emerald-500/20 mr-3">
                <MapPin className="w-5 h-5 text-emerald-400" />
              </div>
              Address Information
            </h2>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              {/* Address */}
              <div className="sm:col-span-2">
                <label htmlFor="address" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Street Address <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="address"
                  id="address"
                  value={formData.address}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.address ? "border-red-500" : ""
                  )}
                  required
                />
                {errors.address && (
                  <p className="mt-2 text-sm text-red-600">{errors.address}</p>
                )}
              </div>

              {/* City */}
              <div>
                <label htmlFor="city" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  City <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="city"
                  id="city"
                  value={formData.city}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.city ? "border-red-500" : ""
                  )}
                  required
                />
                {errors.city && (
                  <p className="mt-2 text-sm text-red-600">{errors.city}</p>
                )}
              </div>

              {/* State */}
              <div>
                <label htmlFor="state" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  State <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="state"
                  id="state"
                  value={formData.state}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.state ? "border-red-500" : ""
                  )}
                  maxLength={2}
                  placeholder="ST"
                  required
                />
                {errors.state && (
                  <p className="mt-2 text-sm text-red-600">{errors.state}</p>
                )}
              </div>

              {/* ZIP Code */}
              <div>
                <label htmlFor="zip_code" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  ZIP Code <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="zip_code"
                  id="zip_code"
                  value={formData.zip_code}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.zip_code ? "border-red-500" : ""
                  )}
                  required
                />
                {errors.zip_code && (
                  <p className="mt-2 text-sm text-red-600">{errors.zip_code}</p>
                )}
              </div>
            </div>
          </div>

          {/* Contact Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-purple-500/20 mr-3">
                <User className="w-5 h-5 text-purple-400" />
              </div>
              Contact Information
            </h2>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              {/* Facility Phone */}
              <div>
                <label htmlFor="phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Facility Phone
                </label>
                <input
                  type="tel"
                  name="phone"
                  id="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.phone && (
                  <p className="mt-2 text-sm text-red-600">{errors.phone}</p>
                )}
              </div>

              {/* Facility Email */}
              <div>
                <label htmlFor="email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Facility Email
                </label>
                <input
                  type="email"
                  name="email"
                  id="email"
                  value={formData.email}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.email ? "border-red-500" : ""
                  )}
                  placeholder="facility@example.com"
                />
                {errors.email && (
                  <p className="mt-2 text-sm text-red-600">{errors.email}</p>
                )}
              </div>

              {/* Contact Name */}
              <div>
                <label htmlFor="contact_name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Name
                </label>
                <input
                  type="text"
                  name="contact_name"
                  id="contact_name"
                  value={formData.contact_name}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_name ? "border-red-500" : ""
                  )}
                  placeholder="Primary contact person"
                />
                {errors.contact_name && (
                  <p className="mt-2 text-sm text-red-600">{errors.contact_name}</p>
                )}
              </div>

              {/* Contact Phone */}
              <div>
                <label htmlFor="contact_phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Phone
                </label>
                <input
                  type="tel"
                  name="contact_phone"
                  id="contact_phone"
                  value={formData.contact_phone}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.contact_phone && (
                  <p className="mt-2 text-sm text-red-600">{errors.contact_phone}</p>
                )}
              </div>

              {/* Contact Email */}
              <div>
                <label htmlFor="contact_email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Email
                </label>
                <input
                  type="email"
                  name="contact_email"
                  id="contact_email"
                  value={formData.contact_email}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_email ? "border-red-500" : ""
                  )}
                  placeholder="contact@facility.com"
                />
                {errors.contact_email && (
                  <p className="mt-2 text-sm text-red-600">{errors.contact_email}</p>
                )}
              </div>

              {/* Contact Fax */}
              <div>
                <label htmlFor="contact_fax" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Fax
                </label>
                <input
                  type="tel"
                  name="contact_fax"
                  id="contact_fax"
                  value={formData.contact_fax}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_fax ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.contact_fax && (
                  <p className="mt-2 text-sm text-red-600">{errors.contact_fax}</p>
                )}
              </div>
            </div>
          </div>

          {/* Additional Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-amber-500/20 mr-3">
                <Clock className="w-5 h-5 text-amber-400" />
              </div>
              Additional Information
            </h2>

            <div className="grid grid-cols-1 gap-6">
              {/* Business Hours */}
              <div>
                <label htmlFor="business_hours" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Business Hours
                </label>
                <textarea
                  name="business_hours"
                  id="business_hours"
                  value={formData.business_hours}
                  onChange={handleChange}
                  rows={3}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.business_hours ? "border-red-500" : ""
                  )}
                  placeholder="Monday - Friday: 8:00 AM - 5:00 PM&#10;Saturday: 9:00 AM - 1:00 PM&#10;Sunday: Closed"
                />
                {errors.business_hours && (
                  <p className="mt-2 text-sm text-red-600">{errors.business_hours}</p>
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
                  className="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                />
                <label htmlFor="active" className={cn("ml-2 text-sm", t.text.secondary)}>
                  Facility is active and accepting orders
                </label>
              </div>
            </div>
          </div>

          {/* Submit Buttons */}
          <div className="flex justify-end space-x-3">
            <button
              type="button"
              onClick={() => router.visit('/admin/facilities')}
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all",
                t.button.secondary.base,
                t.button.secondary.hover
              )}
            >
              <X className="w-4 h-4 mr-2" />
              Cancel
            </button>
            <button
              type="submit"
              className={cn(
                "px-6 py-3 rounded-xl font-medium flex items-center gap-2 transition-all",
                t.button.primary.base,
                t.button.primary.hover
              )}
            >
              <Save className="w-4 h-4" />
              {isEdit ? 'Update Facility' : 'Create Facility'}
            </button>
          </div>
        </form>
      </div>
    </MainLayout>
  );
};

export default FacilityForm;
