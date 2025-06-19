import React, { useState, useRef, useEffect } from 'react';
import { FiCamera, FiRefreshCw, FiAlertCircle, FiFile, FiCheck, FiChevronDown, FiChevronRight, FiUser, FiMapPin, FiBriefcase, FiCalendar } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import PayerSearchInput from '@/Components/PayerSearchInput';
import GoogleAddressAutocomplete from '@/Components/GoogleAddressAutocomplete';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';

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

const ReadOnlyField = ({ label, value, icon }) => {
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

  const [cardFrontPreview, setCardFrontPreview] = useState<string | null>(null);
  const [cardBackPreview, setCardBackPreview] = useState<string | null>(null);
  const [isProcessingCard, setIsProcessingCard] = useState(false);
  const [autoFillSuccess, setAutoFillSuccess] = useState(false);
  const [shippingError, setShippingError] = useState<string | null>(null);
  const [apiError, setApiError] = useState<string | null>(null);
  const [extractedFields, setExtractedFields] = useState<string[]>([]);
  const [debugData, setDebugData] = useState<any>(null);
  const [showDebug, setShowDebug] = useState(false);
  const [showCaregiver, setShowCaregiver] = useState(false);

  const fileInputFrontRef = useRef<HTMLInputElement>(null);
  const fileInputBackRef = useRef<HTMLInputElement>(null);

  // Place of service options
  const placeOfServiceOptions = [
    { value: '11', label: '11 - Office' },
    { value: '12', label: '12 - Home' },
    { value: '13', label: '13 - Assisted Living Facility' },
    { value: '22', label: '22 - Hospital Outpatient' },
    { value: '24', label: '24 - Ambulatory Surgical Center' },
    { value: '31', label: '31 - Skilled Nursing Facility (SNF)' },
    { value: '32', label: '32 - Nursing Facility' },
    { value: '34', label: '34 - Hospice' },
    { value: 'other', label: 'Other' },
  ];

  // Insurance type options
  const insuranceTypeOptions = [
    { value: 'HMO', label: 'HMO' },
    { value: 'PPO', label: 'PPO' },
    { value: 'POS', label: 'POS' },
    { value: 'EPO', label: 'EPO' },
    { value: 'FFS', label: 'FFS' },
    { value: 'medicare_advantage', label: 'Medicare Advantage' },
    { value: 'other', label: 'Other' },
  ];

  // Shipping speed options
  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM)' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day (during office hours)' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
    // Create preview based on file type
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onloadend = () => {
        if (side === 'front') {
          setCardFrontPreview(reader.result as string);
        } else {
          setCardBackPreview(reader.result as string);
        }
      };
      reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
      if (side === 'front') {
        setCardFrontPreview('pdf');
      } else {
        setCardBackPreview('pdf');
      }
    }

    // Store file in form data
    updateFormData({ [`insurance_card_${side}`]: file });

    // Try to process with Azure Document Intelligence
    const frontCard = side === 'front' ? file : formData.insurance_card_front;
    const backCard = side === 'back' ? file : formData.insurance_card_back;

    if (frontCard) {
      setIsProcessingCard(true);
      setAutoFillSuccess(false);
      setApiError(null);
      setExtractedFields([]);

      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        if (backCard) {
          apiFormData.append('insurance_card_back', backCard);
        }


        const response = await fetch('/api/insurance-card/analyze', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: apiFormData,
        });


        if (response.ok) {
          const result = await response.json();

          // Log extracted data details
          if (result.extracted_data) {
            console.log('Extracted data details:', {
              member: result.extracted_data.member,
              insurer: result.extracted_data.insurer,
              payer_id: result.extracted_data.payer_id,
              all_fields: result.extracted_data
            });
          }

          // Store debug data
          setDebugData({
            response: result,
            timestamp: new Date().toISOString(),
            status: response.status,
            currentFormData: formData,
            extracted_raw: result.extracted_data
          });

          if (result.success && result.data) {

            // Track which fields were extracted
            const fieldsExtracted: string[] = [];

            // Update all the fields in a single call
            const updates: any = {};

            // Patient information
            if (result.data.patient_first_name) {
              updates.patient_first_name = result.data.patient_first_name;
              fieldsExtracted.push('First Name');
            }
            if (result.data.patient_last_name) {
              updates.patient_last_name = result.data.patient_last_name;
              fieldsExtracted.push('Last Name');
            }
            if (result.data.patient_dob) {
              updates.patient_dob = result.data.patient_dob;
              fieldsExtracted.push('Date of Birth');
            }
            if (result.data.patient_member_id) {
              updates.patient_member_id = result.data.patient_member_id;
              fieldsExtracted.push('Member ID');
            }

            // Insurance information
            if (result.data.payer_name) {
              updates.payer_name = result.data.payer_name;
              fieldsExtracted.push('Insurance Company');
            }
            if (result.data.payer_id) {
              updates.payer_id = result.data.payer_id;
              fieldsExtracted.push('Payer ID');
            }
            if (result.data.insurance_type) {
              updates.insurance_type = result.data.insurance_type;
              fieldsExtracted.push('Insurance Type');
            }

            // Store extracted data
            updates.insurance_card_auto_filled = true;
            updates.insurance_extracted_data = result.extracted_data;


            // Update debug data with what was extracted
            setDebugData(prev => ({
              ...prev,
              extractedFields: fieldsExtracted,
              updates: updates,
              formDataAfterUpdate: { ...formData, ...updates },
              rawExtractedData: result.extracted_data,
              rawData: result.data
            }));

            // Apply all updates at once
            updateFormData(updates);
            setExtractedFields(fieldsExtracted);
            setAutoFillSuccess(true);

            // Show success message for 5 seconds
            setTimeout(() => {
              setAutoFillSuccess(false);
              setExtractedFields([]);
            }, 5000);
          } else {
            setApiError('No data could be extracted from the insurance card.');
          }
        } else {
          const errorText = await response.text();
          console.error('Failed to analyze insurance card:', response.status, errorText);
          setApiError(`Failed to analyze insurance card (${response.status})`);

          // Store error in debug data
          setDebugData({
            error: errorText,
            status: response.status,
            timestamp: new Date().toISOString()
          });
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
        setApiError(`Error: ${error.message}`);
      } finally {
        setIsProcessingCard(false);
      }
    }
  };

  const handleShippingSpeedChange = (speed: string) => {
    updateFormData({ shipping_speed: speed });
    setShippingError(null);

    if (speed === '1st_am' || speed === 'early_next_day') {
      const now = new Date();
      const currentHour = now.getHours();

      if (currentHour >= 14) {
        setShippingError('Orders placed after 2 PM CST cannot be fulfilled next day. Please select Standard 2 Day shipping.');
        updateFormData({ shipping_speed: 'standard_2_day' });
        return;
      }
      setShippingError('Expected delivery is next day');
    }
  };

  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    updateFormData({ [name]: value });
  };

  const handleSelectChange = (name, value) => {
    updateFormData({ [name]: value });
  };

  return (
    <div className="space-y-8">

      {/* Provider & Facility Information */}
      <Card className={cn(t.glass.card, "overflow-hidden")}>
        <CardHeader className={cn(t.glass.cardHeader, "p-4")}>
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
                            <SelectItem key={facility.id} value={facility.id}>{facility.name}</SelectItem>
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
