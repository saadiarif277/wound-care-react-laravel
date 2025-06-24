import React, { useState } from 'react';
import { FiUser, FiMapPin, FiBriefcase, FiCalendar, FiUpload, FiTruck, FiCreditCard } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/GhostAiUi/ui/input';
import { Label } from '@/Components/GhostAiUi/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/GhostAiUi/ui/select';

interface Step1Props {
  formData: any;
  updateFormData: (data: any) => void;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  woundTypes: Record<string, string>;
  errors: Record<string, string>;
  prefillData: any;
  providers: Array<{
    id: number;
    name: string;
    credentials?: string;
  }>;
  currentUser: {
    id: number;
    name: string;
    role?: string;
    organization?: {
      id: number;
      name: string;
    };
  };
}

const ReadOnlyField = ({ label, value, icon }: { label: string, value: string, icon: React.ReactNode }) => {
  const { theme } = useTheme();
  const t = themes[theme];
  return (
    <div>
      <Label className={cn("flex items-center text-sm font-medium mb-1", t.text.secondary)}>
        {icon}
        <span className="ml-2">{label}</span>
      </Label>
      <div className={cn("w-full px-3 py-2 rounded-md text-sm", t.text.primary, t.glass.input)}>
        {value || 'N/A'}
      </div>
    </div>
  );
};

export default function Step1PatientInformation({
  formData,
  updateFormData,
  facilities,
  woundTypes,
  errors,
  prefillData,
  providers,
  currentUser
}: Step1Props) {
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

  const [insuranceCardFiles, setInsuranceCardFiles] = useState<{
    front: File | null;
    back: File | null;
  }>({
    front: null,
    back: null
  });

  const planTypes = [
    { value: '', label: 'Select plan type...' },
    { value: 'hmo', label: 'HMO' },
    { value: 'ppo', label: 'PPO' },
    { value: 'ffs', label: 'Fee for Service' },
    { value: 'medicare', label: 'Medicare' },
    { value: 'medicaid', label: 'Medicaid' },
    { value: 'other', label: 'Other' }
  ];

  const shippingOptions = [
    { value: 'standard', label: 'Standard (3-5 business days)', description: 'Free shipping' },
    { value: 'expedited', label: 'Expedited (2-3 business days)', description: 'Additional charges may apply' },
    { value: 'next_day', label: 'Next Day', description: 'Additional charges may apply' },
    { value: 'same_day', label: 'Same Day (where available)', description: 'Additional charges may apply' }
  ];

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    updateFormData({ [name]: value });
  };

  const handleSelectChange = (name: string, value: string) => {
    updateFormData({ [name]: value });
  };

  const handleInsuranceCardUpload = (side: 'front' | 'back', file: File) => {
    setInsuranceCardFiles(prev => ({ ...prev, [side]: file }));
    updateFormData({ [`insurance_card_${side}`]: file });

    // TODO: Add AI extraction logic here
    if (side === 'front') {
      // Simulate insurance card extraction
      setTimeout(() => {
        updateFormData({
          insurance_card_auto_filled: true,
          primary_insurance_name: 'Sample Insurance Co.',
          primary_member_id: '123456789',
          primary_plan_type: 'ppo'
        });
      }, 1000);
    }
  };

  // Calculate minimum service date (next business day)
  const getMinServiceDate = () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    // Skip weekends
    while (tomorrow.getDay() === 0 || tomorrow.getDay() === 6) {
      tomorrow.setDate(tomorrow.getDate() + 1);
    }

    return tomorrow.toISOString().split('T')[0];
  };

  return (
    <div className="space-y-8">
      {/* Provider & Facility Information */}
      <Card className={cn(t.glass.card, "overflow-hidden")}>
        <CardHeader className={cn(t.glass.card, "p-4")}>
          <CardTitle className="text-lg">Provider &amp; Facility Information</CardTitle>
        </CardHeader>
        <CardContent className="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <ReadOnlyField label="Provider Name" value={prefillData?.provider_name || ''} icon={<FiUser />} />
            <ReadOnlyField label="Provider NPI" value={prefillData?.provider_npi || ''} icon={<FiUser />} />
            <ReadOnlyField label="Facility" value={prefillData?.facility_name || ''} icon={<FiMapPin />} />
            <ReadOnlyField label="Facility NPI" value={prefillData?.facility_npi || ''} icon={<FiMapPin />} />
            <ReadOnlyField label="Facility Address" value={prefillData?.facility_address || ''} icon={<FiMapPin />} />
            <ReadOnlyField label="Place of Service" value={prefillData?.default_place_of_service || ''} icon={<FiMapPin />} />
            <ReadOnlyField label="Organization" value={prefillData?.organization_name || ''} icon={<FiBriefcase />} />
        </CardContent>
      </Card>

      {/* Patient Information */}
      <div className="space-y-4">
        <h3 className={cn("text-lg font-medium", t.text.primary)}>Patient Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <Label htmlFor="patient_first_name" className={cn(t.text.secondary)}>First Name</Label>
                <Input name="patient_first_name" value={formData.patient_first_name || ''} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.patient_first_name && <p className="text-red-500 text-xs mt-1">{errors.patient_first_name}</p>}
            </div>
            <div>
                <Label htmlFor="patient_last_name" className={cn(t.text.secondary)}>Last Name</Label>
                <Input name="patient_last_name" value={formData.patient_last_name || ''} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.patient_last_name && <p className="text-red-500 text-xs mt-1">{errors.patient_last_name}</p>}
            </div>
            <div>
                <Label htmlFor="patient_dob" className={cn(t.text.secondary)}>Date of Birth</Label>
                <Input type="date" name="patient_dob" value={formData.patient_dob || ''} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.patient_dob && <p className="text-red-500 text-xs mt-1">{errors.patient_dob}</p>}
            </div>
        </div>
      </div>

      {/* Service & Payer Information */}
      <div className="space-y-4">
        <h3 className={cn("text-lg font-medium", t.text.primary)}>Service & Payer Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <Label htmlFor="expected_service_date" className={cn("flex items-center", t.text.secondary)}>
                    <FiCalendar className="mr-2" /> Expected Service Date
                </Label>
                <Input type="date" name="expected_service_date" value={formData.expected_service_date} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.expected_service_date && <p className="text-red-500 text-xs mt-1">{errors.expected_service_date}</p>}
            </div>
            <div>
                <Label htmlFor="facility_id" className={cn(t.text.secondary)}>Service Facility</Label>
                <Select name="facility_id" value={formData.facility_id} onValueChange={(value) => handleSelectChange('facility_id', value)}>
                    <SelectTrigger className={cn(t.glass.input)}>
                        <SelectValue placeholder="Select a facility" />
                    </SelectTrigger>
                    <SelectContent>
                        {facilities.map(facility => (
                            <SelectItem key={facility.id} value={facility.id.toString()}>{facility.name}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.facility_id && <p className="text-red-500 text-xs mt-1">{errors.facility_id}</p>}
            </div>
            <div>
                <Label htmlFor="payer_name" className={cn(t.text.secondary)}>Payer Name</Label>
                <Input name="payer_name" value={formData.payer_name || ''} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.payer_name && <p className="text-red-500 text-xs mt-1">{errors.payer_name}</p>}
            </div>
             <div>
                <Label htmlFor="wound_type" className={cn(t.text.secondary)}>Wound Type</Label>
                <Select name="wound_type" value={formData.wound_type} onValueChange={(value) => handleSelectChange('wound_type', value)}>
                    <SelectTrigger className={cn(t.glass.input)}>
                        <SelectValue placeholder="Select wound type" />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(woundTypes).map(([key, value]) => (
                            <SelectItem key={key} value={key}>{value}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.wound_type && <p className="text-red-500 text-xs mt-1">{errors.wound_type}</p>}
            </div>
        </div>
      </div>

      {/* Insurance Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4 flex items-center", t.text.primary)}>
          <FiCreditCard className="w-5 h-5 mr-2" />
          Insurance Information
        </h3>

        {/* Insurance Card Upload */}
        <div className="mb-6">
          <h4 className={cn("text-sm font-medium mb-3", t.text.secondary)}>
            Upload Insurance Cards (Optional - helps auto-fill information)
          </h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {['front', 'back'].map((side) => (
              <div key={side}>
                <label className={cn("block text-xs font-medium mb-2", t.text.tertiary)}>
                  {side === 'front' ? 'Front of Card' : 'Back of Card'}
                </label>
                <div className={cn(
                  "border-2 border-dashed rounded-lg p-4 text-center transition-colors",
                  theme === 'dark' ? 'border-gray-600 hover:border-gray-500' : 'border-gray-300 hover:border-gray-400'
                )}>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) handleInsuranceCardUpload(side as 'front' | 'back', file);
                    }}
                    className="hidden"
                    id={`insurance-${side}`}
                  />
                  <label htmlFor={`insurance-${side}`} className="cursor-pointer">
                    <FiUpload className={cn("w-8 h-8 mx-auto mb-2", t.text.tertiary)} />
                    <p className={cn("text-sm", t.text.secondary)}>
                      {insuranceCardFiles[side as 'front' | 'back']
                        ? insuranceCardFiles[side as 'front' | 'back']?.name
                        : 'Click to upload or drag and drop'
                      }
                    </p>
                  </label>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Primary Insurance Details */}
        <div className="space-y-4">
          <h4 className={cn("text-sm font-medium", t.text.secondary)}>Primary Insurance</h4>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Insurance Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                className={cn("w-full", t.input.base, t.input.focus, errors.primary_insurance_name && 'border-red-500')}
                value={formData.primary_insurance_name || ''}
                onChange={(e) => updateFormData({ primary_insurance_name: e.target.value })}
                placeholder="Blue Cross Blue Shield"
              />
              {errors.primary_insurance_name && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_insurance_name}</p>
              )}
            </div>

            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Member ID <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                className={cn("w-full", t.input.base, t.input.focus, errors.primary_member_id && 'border-red-500')}
                value={formData.primary_member_id || ''}
                onChange={(e) => updateFormData({ primary_member_id: e.target.value })}
                placeholder="123456789"
              />
              {errors.primary_member_id && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_member_id}</p>
              )}
            </div>

            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Plan Type <span className="text-red-500">*</span>
              </label>
              <select
                className={cn("w-full", t.input.base, t.input.focus, errors.primary_plan_type && 'border-red-500')}
                value={formData.primary_plan_type || ''}
                onChange={(e) => updateFormData({ primary_plan_type: e.target.value })}
              >
                {planTypes.map(type => (
                  <option key={type.value} value={type.value}>{type.label}</option>
                ))}
              </select>
              {errors.primary_plan_type && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_plan_type}</p>
              )}
            </div>
          </div>
        </div>

        {/* Secondary Insurance Toggle */}
        <div className="mt-6">
          <label className="flex items-center cursor-pointer">
            <input
              type="checkbox"
              className="form-checkbox h-4 w-4 text-blue-600 rounded"
              checked={formData.has_secondary_insurance || false}
              onChange={(e) => updateFormData({ has_secondary_insurance: e.target.checked })}
            />
            <span className={cn("ml-2 text-sm font-medium", t.text.secondary)}>
              Patient has secondary insurance
            </span>
          </label>
        </div>

        {/* Secondary Insurance Details */}
        {formData.has_secondary_insurance && (
          <div className="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <h4 className={cn("text-sm font-medium mb-3", t.text.secondary)}>Secondary Insurance</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Insurance Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  className={cn("w-full", t.input.base, t.input.focus)}
                  value={formData.secondary_insurance_name || ''}
                  onChange={(e) => updateFormData({ secondary_insurance_name: e.target.value })}
                  placeholder="Secondary insurance name"
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Member ID <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  className={cn("w-full", t.input.base, t.input.focus)}
                  value={formData.secondary_member_id || ''}
                  onChange={(e) => updateFormData({ secondary_member_id: e.target.value })}
                  placeholder="Secondary member ID"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Service & Shipping Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4 flex items-center", t.text.primary)}>
          <FiCalendar className="w-5 h-5 mr-2" />
          Service & Shipping
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Expected Service Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              className={cn("w-full", t.input.base, t.input.focus, errors.expected_service_date && 'border-red-500')}
              value={formData.expected_service_date || ''}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              min={getMinServiceDate()}
            />
            {errors.expected_service_date && (
              <p className="mt-1 text-sm text-red-500">{errors.expected_service_date}</p>
            )}
            <p className={cn("mt-1 text-xs", t.text.tertiary)}>
              Earliest available: {getMinServiceDate()}
            </p>
          </div>
        </div>

        <div className="mt-4">
          <label className={cn("block text-sm font-medium mb-3", t.text.secondary)}>
            Shipping Speed <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            {shippingOptions.map(option => (
              <label key={option.value} className="flex items-start cursor-pointer">
                <input
                  type="radio"
                  name="shipping_speed"
                  value={option.value}
                  checked={formData.shipping_speed === option.value}
                  onChange={(e) => updateFormData({ shipping_speed: e.target.value })}
                  className="form-radio h-4 w-4 text-blue-600 mt-0.5"
                />
                <div className="ml-3">
                  <div className={cn("text-sm font-medium", t.text.secondary)}>
                    {option.label}
                  </div>
                  <div className={cn("text-xs", t.text.tertiary)}>
                    {option.description}
                  </div>
                </div>
              </label>
            ))}
          </div>
          {errors.shipping_speed && (
            <p className="mt-1 text-sm text-red-500">{errors.shipping_speed}</p>
          )}
        </div>
      </div>

      {/* Prior Authorization Consent */}
      <div className={cn("p-4 rounded-lg border",
        theme === 'dark' ? 'bg-green-900/20 border-green-800' : 'bg-green-50 border-green-200'
      )}>
        <label className="flex items-center cursor-pointer">
          <input
            type="checkbox"
            className="form-checkbox h-4 w-4 text-green-600 rounded"
            checked={formData.prior_auth_permission || false}
            onChange={(e) => updateFormData({ prior_auth_permission: e.target.checked })}
          />
          <span className={cn("ml-2 text-sm", t.text.secondary)}>
            I consent to prior authorization processing
          </span>
        </label>
      </div>
    </div>
  );
}
