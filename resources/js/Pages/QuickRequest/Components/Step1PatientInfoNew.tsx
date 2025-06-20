import React, { useState } from 'react';
import { FiUser, FiMapPin, FiBriefcase, FiCalendar } from 'react-icons/fi';
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

export default function Step1PatientInfoNew({
  formData,
  updateFormData,
  facilities,
  woundTypes,
  errors,
  prefillData
}: Step1Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    updateFormData({ [name]: value });
  };

  const handleSelectChange = (name: string, value: string) => {
    updateFormData({ [name]: value });
  };

  return (
    <div className="space-y-8">

      {/* Provider & Facility Information */}
      <Card className={cn(t.glass.card, "overflow-hidden")}>
        <CardHeader className={cn(t.glass.card, "p-4")}>
          <CardTitle className="text-lg">Provider &amp; Facility Information</CardTitle>
        </CardHeader>
        <CardContent className="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <ReadOnlyField label="Provider Name" value={prefillData.provider_name} icon={<FiUser />} />
            <ReadOnlyField label="Provider NPI" value={prefillData.provider_npi} icon={<FiUser />} />
            <ReadOnlyField label="Facility" value={prefillData.facility_name} icon={<FiMapPin />} />
            <ReadOnlyField label="Facility NPI" value={prefillData.facility_npi} icon={<FiMapPin />} />
            <ReadOnlyField label="Facility Address" value={prefillData.facility_address} icon={<FiMapPin />} />
            <ReadOnlyField label="Place of Service" value={prefillData.default_place_of_service} icon={<FiMapPin />} />
            <ReadOnlyField label="Organization" value={prefillData.organization_name} icon={<FiBriefcase />} />
        </CardContent>
      </Card>

      {/* Patient Information */}
      <div className="space-y-4">
        <h3 className={cn("text-lg font-medium", t.text.primary)}>Patient Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <Label htmlFor="patient_first_name" className={cn(t.text.secondary)}>First Name</Label>
                <Input name="patient_first_name" value={formData.patient_first_name} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.patient_first_name && <p className="text-red-500 text-xs mt-1">{errors.patient_first_name}</p>}
            </div>
            <div>
                <Label htmlFor="patient_last_name" className={cn(t.text.secondary)}>Last Name</Label>
                <Input name="patient_last_name" value={formData.patient_last_name} onChange={handleInputChange} className={cn(t.glass.input)} />
                {errors.patient_last_name && <p className="text-red-500 text-xs mt-1">{errors.patient_last_name}</p>}
            </div>
            <div>
                <Label htmlFor="patient_dob" className={cn(t.text.secondary)}>Date of Birth</Label>
                <Input type="date" name="patient_dob" value={formData.patient_dob} onChange={handleInputChange} className={cn(t.glass.input)} />
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
                <Input name="payer_name" value={formData.payer_name} onChange={handleInputChange} className={cn(t.glass.input)} />
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

    </div>
  );
}
