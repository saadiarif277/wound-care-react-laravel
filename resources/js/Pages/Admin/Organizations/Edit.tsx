import React from 'react';
import { useForm, Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ArrowLeft, Building, MapPin, User, CreditCard, Save, X } from 'lucide-react';

interface Organization {
  id: string;
  name: string;
  type: string;
  status: string;
  contact_email: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zip_code?: string;
  billing_address?: string;
  billing_city?: string;
  billing_state?: string;
  billing_zip?: string;
  ap_contact_name?: string;
  ap_contact_phone?: string;
  ap_contact_email?: string;
  biller_contact_name?: string;
  biller_contact_phone?: string;
}

interface Props {
  organization: Organization;
}

export default function OrganizationEdit({ organization }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const { data, setData, put, processing, errors } = useForm({
    name: organization.name || '',
    type: organization.type || 'healthcare',
    status: organization.status || 'active',
    contact_email: organization.contact_email || '',
    phone: organization.phone || '',
    address: organization.address || '',
    city: organization.city || '',
    state: organization.state || '',
    zip_code: organization.zip_code || '',
    billing_address: organization.billing_address || '',
    billing_city: organization.billing_city || '',
    billing_state: organization.billing_state || '',
    billing_zip: organization.billing_zip || '',
    ap_contact_name: organization.ap_contact_name || '',
    ap_contact_phone: organization.ap_contact_phone || '',
    ap_contact_email: organization.ap_contact_email || '',
    biller_contact_name: organization.biller_contact_name || '',
    biller_contact_phone: organization.biller_contact_phone || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('admin.organizations.update', organization.id));
  };

  return (
    <MainLayout>
      <Head title={`Edit ${organization.name}`} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <Link
              href={route('admin.organizations.index')}
              className={cn(
                "inline-flex items-center text-sm transition-colors",
                t.text.secondary,
                "hover:" + t.text.primary
              )}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Organizations
            </Link>
            <h1 className={cn("mt-2 text-3xl font-bold", t.text.primary)}>
              Edit Organization
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Update the details for {organization.name}
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
              Primary Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="md:col-span-2">
                <label htmlFor="name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Organization Name <span className="text-red-500">*</span>
                </label>
                <input
                  id="name"
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.name ? "border-red-500" : ""
                  )}
                  placeholder="Enter organization name"
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
              </div>

              <div>
                <label htmlFor="type" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Organization Type <span className="text-red-500">*</span>
                </label>
                <select
                  id="type"
                  value={data.type}
                  onChange={(e) => setData('type', e.target.value)}
                  className={cn(
                    t.input.select || t.input.base,
                    t.input.focus,
                    errors.type ? "border-red-500" : ""
                  )}
                >
                  <option value="healthcare">Healthcare</option>
                  <option value="clinic">Clinic</option>
                  <option value="hospital">Hospital</option>
                  <option value="other">Other</option>
                </select>
                {errors.type && <p className="text-red-500 text-xs mt-1">{errors.type}</p>}
              </div>

              <div>
                <label htmlFor="status" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Status <span className="text-red-500">*</span>
                </label>
                <select
                  id="status"
                  value={data.status}
                  onChange={(e) => setData('status', e.target.value)}
                  className={cn(
                    t.input.select || t.input.base,
                    t.input.focus,
                    errors.status ? "border-red-500" : ""
                  )}
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="pending">Pending</option>
                </select>
                {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
              </div>

              <div>
                <label htmlFor="contact_email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Email <span className="text-red-500">*</span>
                </label>
                <input
                  id="contact_email"
                  type="email"
                  value={data.contact_email}
                  onChange={(e) => setData('contact_email', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_email ? "border-red-500" : ""
                  )}
                  placeholder="contact@organization.com"
                />
                {errors.contact_email && <p className="text-red-500 text-xs mt-1">{errors.contact_email}</p>}
              </div>

              <div>
                <label htmlFor="phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Phone Number
                </label>
                <input
                  id="phone"
                  type="tel"
                  value={data.phone}
                  onChange={(e) => setData('phone', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone}</p>}
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

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="md:col-span-2">
                <label htmlFor="address" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Street Address
                </label>
                <input
                  id="address"
                  type="text"
                  value={data.address}
                  onChange={(e) => setData('address', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.address ? "border-red-500" : ""
                  )}
                  placeholder="123 Main Street"
                />
                {errors.address && <p className="text-red-500 text-xs mt-1">{errors.address}</p>}
              </div>

              <div>
                <label htmlFor="city" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  City
                </label>
                <input
                  id="city"
                  type="text"
                  value={data.city}
                  onChange={(e) => setData('city', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.city ? "border-red-500" : ""
                  )}
                  placeholder="City"
                />
                {errors.city && <p className="text-red-500 text-xs mt-1">{errors.city}</p>}
              </div>

              <div>
                <label htmlFor="state" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  State
                </label>
                <input
                  id="state"
                  type="text"
                  value={data.state}
                  onChange={(e) => setData('state', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.state ? "border-red-500" : ""
                  )}
                  placeholder="ST"
                  maxLength={2}
                />
                {errors.state && <p className="text-red-500 text-xs mt-1">{errors.state}</p>}
              </div>

              <div>
                <label htmlFor="zip_code" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  ZIP Code
                </label>
                <input
                  id="zip_code"
                  type="text"
                  value={data.zip_code}
                  onChange={(e) => setData('zip_code', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.zip_code ? "border-red-500" : ""
                  )}
                  placeholder="12345"
                />
                {errors.zip_code && <p className="text-red-500 text-xs mt-1">{errors.zip_code}</p>}
              </div>
            </div>
          </div>

          {/* Billing Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-purple-500/20 mr-3">
                <CreditCard className="w-5 h-5 text-purple-400" />
              </div>
              Billing Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="md:col-span-2">
                <label htmlFor="billing_address" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing Address
                </label>
                <input
                  id="billing_address"
                  type="text"
                  value={data.billing_address}
                  onChange={(e) => setData('billing_address', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_address ? "border-red-500" : ""
                  )}
                  placeholder="Same as physical address or enter different billing address"
                />
                {errors.billing_address && <p className="text-red-500 text-xs mt-1">{errors.billing_address}</p>}
              </div>

              <div>
                <label htmlFor="billing_city" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing City
                </label>
                <input
                  id="billing_city"
                  type="text"
                  value={data.billing_city}
                  onChange={(e) => setData('billing_city', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_city ? "border-red-500" : ""
                  )}
                  placeholder="City"
                />
                {errors.billing_city && <p className="text-red-500 text-xs mt-1">{errors.billing_city}</p>}
              </div>

              <div>
                <label htmlFor="billing_state" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing State
                </label>
                <input
                  id="billing_state"
                  type="text"
                  value={data.billing_state}
                  onChange={(e) => setData('billing_state', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_state ? "border-red-500" : ""
                  )}
                  placeholder="ST"
                  maxLength={2}
                />
                {errors.billing_state && <p className="text-red-500 text-xs mt-1">{errors.billing_state}</p>}
              </div>

              <div>
                <label htmlFor="billing_zip" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing ZIP
                </label>
                <input
                  id="billing_zip"
                  type="text"
                  value={data.billing_zip}
                  onChange={(e) => setData('billing_zip', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_zip ? "border-red-500" : ""
                  )}
                  placeholder="12345"
                />
                {errors.billing_zip && <p className="text-red-500 text-xs mt-1">{errors.billing_zip}</p>}
              </div>
            </div>
          </div>

          {/* Accounts Payable Contact */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-amber-500/20 mr-3">
                <User className="w-5 h-5 text-amber-400" />
              </div>
              Accounts Payable Contact
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="ap_contact_name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Name
                </label>
                <input
                  id="ap_contact_name"
                  type="text"
                  value={data.ap_contact_name}
                  onChange={(e) => setData('ap_contact_name', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_name ? "border-red-500" : ""
                  )}
                  placeholder="John Doe"
                />
                {errors.ap_contact_name && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_name}</p>}
              </div>

              <div>
                <label htmlFor="ap_contact_phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Phone
                </label>
                <input
                  id="ap_contact_phone"
                  type="tel"
                  value={data.ap_contact_phone}
                  onChange={(e) => setData('ap_contact_phone', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.ap_contact_phone && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_phone}</p>}
              </div>

              <div>
                <label htmlFor="ap_contact_email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Email
                </label>
                <input
                  id="ap_contact_email"
                  type="email"
                  value={data.ap_contact_email}
                  onChange={(e) => setData('ap_contact_email', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_email ? "border-red-500" : ""
                  )}
                  placeholder="ap@organization.com"
                />
                {errors.ap_contact_email && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_email}</p>}
              </div>
            </div>
          </div>

          {/* Biller Contact Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-indigo-500/20 mr-3">
                <User className="w-5 h-5 text-indigo-400" />
              </div>
              Biller Contact Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="biller_contact_name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Biller Contact Name
                </label>
                <input
                  id="biller_contact_name"
                  type="text"
                  value={data.biller_contact_name}
                  onChange={(e) => setData('biller_contact_name', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.biller_contact_name ? "border-red-500" : ""
                  )}
                  placeholder="Jane Smith"
                />
                {errors.biller_contact_name && <p className="text-red-500 text-xs mt-1">{errors.biller_contact_name}</p>}
              </div>

              <div>
                <label htmlFor="biller_contact_phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Biller Contact Phone
                </label>
                <input
                  id="biller_contact_phone"
                  type="tel"
                  value={data.biller_contact_phone}
                  onChange={(e) => setData('biller_contact_phone', e.target.value)}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.biller_contact_phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 987-6543"
                />
                {errors.biller_contact_phone && <p className="text-red-500 text-xs mt-1">{errors.biller_contact_phone}</p>}
              </div>
            </div>
          </div>

          {/* Submit Buttons */}
          <div className="flex justify-end space-x-3">
            <Link
              href={route('admin.organizations.index')}
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all inline-flex items-center",
                t.button.secondary.base,
                t.button.secondary.hover
              )}
            >
              <X className="w-4 h-4 mr-2" />
              Cancel
            </Link>
            <button
              type="submit"
              disabled={processing}
              className={cn(
                "px-6 py-3 rounded-xl font-medium flex items-center gap-2 transition-all",
                t.button.primary.base,
                t.button.primary.hover,
                processing && "opacity-50 cursor-not-allowed"
              )}
            >
              <Save className="w-4 h-4" />
              {processing ? 'Updating...' : 'Update Organization'}
            </button>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}
