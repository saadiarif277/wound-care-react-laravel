import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  Building,
  Mail,
  Phone,
  MapPin,
  ArrowLeft,
  Save,
  X,
  User,
  CreditCard
} from 'lucide-react';

export default function OrganizationsCreate() {
  const { theme } = useTheme();
  const t = themes[theme];

  const [formData, setFormData] = useState({
    name: '',
    type: 'healthcare',
    contact_email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    billing_address: '',
    billing_city: '',
    billing_state: '',
    billing_zip: '',
    ap_contact_name: '',
    ap_contact_phone: '',
    ap_contact_email: '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [processing, setProcessing] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post(route('admin.organizations.store'), formData, {
      preserveScroll: true,
      onError: (errors) => {
        setErrors(errors);
        setProcessing(false);
      },
      onSuccess: () => {
        setProcessing(false);
      }
    });
  };

  const handleCancel = () => {
    router.visit(route('admin.organizations.index'));
  };

  return (
    <MainLayout>
      <Head title="Create Organization" />

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
              <ArrowLeft className="w-4 h-4 mr-1" />
              Back to Organizations
            </Link>
            <h1 className={cn("mt-2 text-3xl font-bold", t.text.primary)}>
              Create New Organization
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Add a new organization to the system
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

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="md:col-span-2">
                <label htmlFor="name" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Organization Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.name ? "border-red-500" : ""
                  )}
                  placeholder="Enter organization name"
                />
                {errors.name && (
                  <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                )}
              </div>

              <div>
                <label htmlFor="type" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Organization Type <span className="text-red-500">*</span>
                </label>
                <select
                  id="type"
                  name="type"
                  value={formData.type}
                  onChange={handleChange}
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
                {errors.type && (
                  <p className="mt-1 text-sm text-red-600">{errors.type}</p>
                )}
              </div>

              <div>
                <label htmlFor="contact_email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Email <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  id="contact_email"
                  name="contact_email"
                  value={formData.contact_email}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.contact_email ? "border-red-500" : ""
                  )}
                  placeholder="contact@organization.com"
                />
                {errors.contact_email && (
                  <p className="mt-1 text-sm text-red-600">{errors.contact_email}</p>
                )}
              </div>

              <div>
                <label htmlFor="phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Phone Number
                </label>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
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
                  <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
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

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="md:col-span-2">
                <label htmlFor="address" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Street Address
                </label>
                <input
                  type="text"
                  id="address"
                  name="address"
                  value={formData.address}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.address ? "border-red-500" : ""
                  )}
                  placeholder="123 Main Street"
                />
                {errors.address && (
                  <p className="mt-1 text-sm text-red-600">{errors.address}</p>
                )}
              </div>

              <div>
                <label htmlFor="city" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  City
                </label>
                <input
                  type="text"
                  id="city"
                  name="city"
                  value={formData.city}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.city ? "border-red-500" : ""
                  )}
                  placeholder="City"
                />
                {errors.city && (
                  <p className="mt-1 text-sm text-red-600">{errors.city}</p>
                )}
              </div>

              <div>
                <label htmlFor="state" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  State
                </label>
                <input
                  type="text"
                  id="state"
                  name="state"
                  value={formData.state}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.state ? "border-red-500" : ""
                  )}
                  placeholder="ST"
                  maxLength={2}
                />
                {errors.state && (
                  <p className="mt-1 text-sm text-red-600">{errors.state}</p>
                )}
              </div>

              <div>
                <label htmlFor="zip_code" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  ZIP Code
                </label>
                <input
                  type="text"
                  id="zip_code"
                  name="zip_code"
                  value={formData.zip_code}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.zip_code ? "border-red-500" : ""
                  )}
                  placeholder="12345"
                />
                {errors.zip_code && (
                  <p className="mt-1 text-sm text-red-600">{errors.zip_code}</p>
                )}
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
                  type="text"
                  id="billing_address"
                  name="billing_address"
                  value={formData.billing_address}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_address ? "border-red-500" : ""
                  )}
                  placeholder="Same as physical address or enter different billing address"
                />
                {errors.billing_address && (
                  <p className="mt-1 text-sm text-red-600">{errors.billing_address}</p>
                )}
              </div>

              <div>
                <label htmlFor="billing_city" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing City
                </label>
                <input
                  type="text"
                  id="billing_city"
                  name="billing_city"
                  value={formData.billing_city}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_city ? "border-red-500" : ""
                  )}
                  placeholder="City"
                />
                {errors.billing_city && (
                  <p className="mt-1 text-sm text-red-600">{errors.billing_city}</p>
                )}
              </div>

              <div>
                <label htmlFor="billing_state" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing State
                </label>
                <input
                  type="text"
                  id="billing_state"
                  name="billing_state"
                  value={formData.billing_state}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_state ? "border-red-500" : ""
                  )}
                  placeholder="ST"
                  maxLength={2}
                />
                {errors.billing_state && (
                  <p className="mt-1 text-sm text-red-600">{errors.billing_state}</p>
                )}
              </div>

              <div>
                <label htmlFor="billing_zip" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Billing ZIP
                </label>
                <input
                  type="text"
                  id="billing_zip"
                  name="billing_zip"
                  value={formData.billing_zip}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.billing_zip ? "border-red-500" : ""
                  )}
                  placeholder="12345"
                />
                {errors.billing_zip && (
                  <p className="mt-1 text-sm text-red-600">{errors.billing_zip}</p>
                )}
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
                  type="text"
                  id="ap_contact_name"
                  name="ap_contact_name"
                  value={formData.ap_contact_name}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_name ? "border-red-500" : ""
                  )}
                  placeholder="John Doe"
                />
                {errors.ap_contact_name && (
                  <p className="mt-1 text-sm text-red-600">{errors.ap_contact_name}</p>
                )}
              </div>

              <div>
                <label htmlFor="ap_contact_phone" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Phone
                </label>
                <input
                  type="tel"
                  id="ap_contact_phone"
                  name="ap_contact_phone"
                  value={formData.ap_contact_phone}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_phone ? "border-red-500" : ""
                  )}
                  placeholder="(555) 123-4567"
                />
                {errors.ap_contact_phone && (
                  <p className="mt-1 text-sm text-red-600">{errors.ap_contact_phone}</p>
                )}
              </div>

              <div>
                <label htmlFor="ap_contact_email" className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                  Contact Email
                </label>
                <input
                  type="email"
                  id="ap_contact_email"
                  name="ap_contact_email"
                  value={formData.ap_contact_email}
                  onChange={handleChange}
                  className={cn(
                    t.input.base,
                    t.input.focus,
                    errors.ap_contact_email ? "border-red-500" : ""
                  )}
                  placeholder="ap@organization.com"
                />
                {errors.ap_contact_email && (
                  <p className="mt-1 text-sm text-red-600">{errors.ap_contact_email}</p>
                )}
              </div>
            </div>
          </div>

          {/* Submit Buttons */}
          <div className="flex justify-end space-x-3">
            <button
              type="button"
              onClick={handleCancel}
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
              disabled={processing}
              className={cn(
                "px-6 py-3 rounded-xl font-medium flex items-center gap-2 transition-all",
                t.button.primary.base,
                t.button.primary.hover,
                processing && "opacity-50 cursor-not-allowed"
              )}
            >
              <Save className="w-4 h-4" />
              {processing ? 'Creating...' : 'Create Organization'}
            </button>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}
