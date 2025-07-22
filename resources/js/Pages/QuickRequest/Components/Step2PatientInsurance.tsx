import { useState, useRef } from 'react';
import { FiCreditCard, FiRefreshCw, FiCheck, FiInfo, FiUpload } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import GoogleAddressAutocompleteWithFallback, { ParsedAddressComponents } from '@/Components/GoogleAddressAutocompleteWithFallback';
import PayerSearchInput from '@/Components/PayerSearchInput';
import FormInputWithIndicator from '@/Components/ui/FormInputWithIndicator';
import Select from '@/Components/ui/Select';
import api from '@/lib/api';
import axios from 'axios';
import { themes } from '@/theme/glass-theme';


// Define response interfaces
interface ClinicalDocResponse {
  success: boolean;
  structured_data: Record<string, any>;
}

interface Step2Props {
  formData: any;
  updateFormData: (data: any) => void;
  errors: Record<string, string>;
  facilities?: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  providers?: Array<{
    id: number;
    name: string;
    credentials?: string;
    npi?: string;
  }>;
  currentUser?: {
    role?: string;
    id?: number;
  };
}

function Step2PatientInsurance({
  formData,
  updateFormData,
  errors,
  facilities = [],
  providers = [],
  currentUser
}: Step2Props) {


  const [cardFrontPreview, setCardFrontPreview] = useState<string | null>(null);
  const [cardBackPreview, setCardBackPreview] = useState<string | null>(null);
  const [isProcessingCard, setIsProcessingCard] = useState(false);
  const [autoFillSuccess, setAutoFillSuccess] = useState(false);
  const [showCaregiver, setShowCaregiver] = useState(!formData.patient_is_subscriber);
  const [showSecondaryCaregiver, setShowSecondaryCaregiver] = useState(!formData.secondary_patient_is_subscriber);

  // New state for clinical documents
  const [clinicalDocPreview, setClinicalDocPreview] = useState<string | null>(null);
  const [isProcessingClinicalDoc, setIsProcessingClinicalDoc] = useState(false);
  const [clinicalDocSuccess, setClinicalDocSuccess] = useState(false);


  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  // Shipping speed options
  const shippingOptions = [
    { value: '', label: 'Please Select' },
    { value: '1st_am', label: '1st AM (before 9AM) - Next business day' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
    { value: 'choose_delivery_date', label: 'Choose Delivery Date' },
  ];

  // Plan type options
  const planTypes = [
    { value: '', label: 'Please Select' },
    { value: 'ffs', label: 'FFS (Fee for Service)' },
    { value: 'hmo', label: 'HMO' },
    { value: 'ppo', label: 'PPO' },
    { value: 'pos', label: 'POS' },
    { value: 'epo', label: 'EPO' },
    { value: 'medicare_advantage', label: 'Medicare Advantage' },
    { value: 'other', label: 'Other' },
  ];

  // Provider network status options
  const networkStatusOptions = [
    { value: '', label: 'Please Select' },
    { value: 'in_network', label: 'In-Network' },
    { value: 'out_of_network', label: 'Out-of-Network' },
    { value: 'not_sure', label: 'Not Sure' },
  ];

  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
    // Create preview
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

      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        if (backCard) {
          apiFormData.append('insurance_card_back', backCard);
        }

        const response = await api.post('/api/insurance-card/analyze', apiFormData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        if (response.data.success) {
          const updates: any = {};
          const { data } = response.data;

          // Patient information
          if (data.patient_first_name) updates.patient_first_name = data.patient_first_name;
          if (data.patient_last_name) updates.patient_last_name = data.patient_last_name;
          if (data.patient_dob) updates.patient_dob = data.patient_dob;
          if (data.patient_member_id) updates.patient_member_id = data.patient_member_id;

          // Insurance information
          if (data.payer_name) updates.primary_insurance_name = data.payer_name;
          if (data.payer_id) updates.primary_member_id = data.payer_id;
          if (data.insurance_type) updates.primary_plan_type = data.insurance_type;

          updates.insurance_card_auto_filled = true;

          updateFormData(updates);
          setAutoFillSuccess(true);

          setTimeout(() => {
            setAutoFillSuccess(false);
          }, 5000);
        }
      } catch (error: any) {
        if (axios.isAxiosError(error)) {
            console.error('Error processing insurance card:', error.response?.data || error.message);
        } else {
            console.error('Error processing insurance card:', error);
        }
      } finally {
        setIsProcessingCard(false);
      }
    }
  };

  const handleClinicalDocumentUpload = async (file: File) => {
    // Create preview
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onloadend = () => {
            setClinicalDocPreview(reader.result as string);
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        setClinicalDocPreview('pdf');
    }

    // Store file in form data
    updateFormData({ clinical_document: file });

    setIsProcessingClinicalDoc(true);
    setClinicalDocSuccess(false);

    try {
        const apiFormData = new FormData();
        apiFormData.append('document', file);
        apiFormData.append('document_type', 'clinical_note'); // Or detect dynamically

        const response = await api.post<ClinicalDocResponse>('/api/document/analyze', apiFormData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data.success) {
            const updates: any = { ...response.data.structured_data };
            
            // Map clinical document fields to form fields for ACZ & Associates
            const mappedUpdates: any = {};
            
            // Patient information
            if (updates.patient_name) {
                const nameParts = updates.patient_name.split(' ');
                if (nameParts.length >= 2) {
                    mappedUpdates.patient_first_name = nameParts[0];
                    mappedUpdates.patient_last_name = nameParts.slice(1).join(' ');
                }
                mappedUpdates.patient_name = updates.patient_name;
            }
            if (updates.patient_dob || updates.date_of_birth) {
                mappedUpdates.patient_dob = updates.patient_dob || updates.date_of_birth;
            }
            if (updates.patient_address) {
                mappedUpdates.patient_address_line1 = updates.patient_address;
            }
            if (updates.patient_phone || updates.phone) {
                mappedUpdates.patient_phone = updates.patient_phone || updates.phone;
            }
            
            // Clinical information
            if (updates.diagnosis || updates.primary_diagnosis) {
                mappedUpdates.primary_diagnosis_code = updates.diagnosis || updates.primary_diagnosis;
            }
            if (updates.icd_10_codes || updates.diagnosis_codes) {
                mappedUpdates.icd_10_codes = updates.icd_10_codes || updates.diagnosis_codes;
            }
            if (updates.wound_location) {
                mappedUpdates.wound_location = updates.wound_location;
            }
            if (updates.wound_size || updates.wound_measurements) {
                mappedUpdates.total_wound_size = updates.wound_size || updates.wound_measurements;
            }
            if (updates.medical_history) {
                mappedUpdates.medical_history = updates.medical_history;
            }
            if (updates.medications) {
                mappedUpdates.current_medications = updates.medications;
            }
            if (updates.wound_type) {
                mappedUpdates.wound_type = updates.wound_type;
            }
            
            // Provider information
            if (updates.provider_name || updates.physician_name) {
                mappedUpdates.provider_name = updates.provider_name || updates.physician_name;
            }
            if (updates.provider_npi || updates.physician_npi) {
                mappedUpdates.provider_npi = updates.provider_npi || updates.physician_npi;
            }
            
            // Facility information
            if (updates.facility_name) {
                mappedUpdates.facility_name = updates.facility_name;
            }
            if (updates.facility_npi) {
                mappedUpdates.facility_npi = updates.facility_npi;
            }
            
            // Merge original updates with mapped updates
            const finalUpdates = { ...updates, ...mappedUpdates };
            
            // Log for debugging
            console.log('Clinical document OCR results:', {
                original: updates,
                mapped: mappedUpdates,
                final: finalUpdates
            });

            updateFormData(finalUpdates);
            setClinicalDocSuccess(true);
            setTimeout(() => setClinicalDocSuccess(false), 5000);
        }
    } catch (error: any) {
        if (axios.isAxiosError(error)) {
            console.error('Error processing clinical document:', error.response?.data || error.message);
        } else {
            console.error('Error processing clinical document:', error);
        }
    } finally {
        setIsProcessingClinicalDoc(false);
    }
  };


  const handleAddressSelect = (place: any, parsedAddress?: ParsedAddressComponents) => {
    if (!parsedAddress) return;

    // Create FHIR-compliant address structure
    const fhirAddress = {
      text: place.formatted_address || '',
      line: [] as string[],
      city: parsedAddress.city || '',
      state: parsedAddress.stateAbbreviation || '',
      postalCode: parsedAddress.zipCode || '',
      country: parsedAddress.countryCode || 'US'
    };

    // Build address line array
    if (parsedAddress.streetAddress) {
      fhirAddress.line.push(parsedAddress.streetAddress);
    }

    // Update form with FHIR-compliant structure
    updateFormData({
      patient: {
        ...formData.patient,
        address: fhirAddress
      }
    });

    console.log('📝 Updated patient address in FHIR format:', fhirAddress);
  };

  // Auto-populate payer phone based on insurance selection
  const getPayerPhone = (insuranceName: string) => {
    const phoneMap: Record<string, string> = {
      'Medicare Part B': '1-800-MEDICARE',
      'Blue Cross Blue Shield': '1-800-262-2583',
      'Aetna': '1-800-872-3862',
      'United Healthcare': '1-866-414-1959',
      'Humana': '1-800-457-4708',
    };
    return phoneMap[insuranceName] || '';
  };

  return (
    <div className="space-y-6">
      {/* Provider & Facility Selection */}
      <div className="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg">
        <h3 className="font-medium text-orange-900 dark:text-orange-300 mb-2">Provider & Facility Information</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Select
              label="Provider"
              value={formData.provider_id || ''}
              onChange={(e) => updateFormData({ provider_id: parseInt(e.target.value) })}
              disabled={currentUser?.role === 'provider'}
              options={providers.map(p => ({
                value: p.id,
                label: `${p.name}${p.credentials ? `, ${p.credentials}` : ''} ${p.npi ? `(NPI: ${p.npi})` : ''}`
              }))}
              placeholder="Please Select Provider"
              error={errors.provider_id}
              required
            />
            {errors.provider_id && (
              <p className="mt-1 text-sm text-red-500">{errors.provider_id}</p>
            )}
          </div>

          <div>
            <Select
              label="Facility"
              value={formData.facility_id || ''}
              onChange={(e) => updateFormData({ facility_id: parseInt(e.target.value) })}
              options={facilities.map(f => ({
                value: f.id,
                label: `${f.name} ${f.address ? `(${f.address})` : ''}`
              }))}
              placeholder="Please Select Facility"
              error={errors.facility_id}
              required
            />
            {errors.facility_id && (
              <p className="mt-1 text-sm text-red-500">{errors.facility_id}</p>
            )}
          </div>
        </div>
      </div>


      {/* Document Upload Section */}
      <div className="bg-gray-50 dark:bg-gray-900/20 p-3 rounded-lg">
        <h3 className="font-medium text-gray-900 dark:text-gray-300 mb-2">Document Upload (Optional)</h3>
        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Upload insurance cards or clinical documents to auto-fill patient and insurance information.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Insurance Card Upload */}
          <div className="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center">
            <h4 className="font-medium mb-2">Insurance Card</h4>
            <div className="grid grid-cols-2 gap-2">
              <FileUpload
                  label="Front"
                  onFileUpload={(file) => handleInsuranceCardUpload(file, 'front')}
                  isProcessing={isProcessingCard}
                  isSuccess={autoFillSuccess}
                  preview={cardFrontPreview}
              />
              <FileUpload
                  label="Back"
                  onFileUpload={(file) => handleInsuranceCardUpload(file, 'back')}
                  isProcessing={isProcessingCard}
                  isSuccess={autoFillSuccess}
                  preview={cardBackPreview}
              />
            </div>
          </div>
          
          {/* Clinical Document Upload */}
          <div className="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center">
            <h4 className="font-medium mb-2">Clinical Notes / Demographics</h4>
            <FileUpload
                label="Upload Document"
                onFileUpload={handleClinicalDocumentUpload}
                isProcessing={isProcessingClinicalDoc}
                isSuccess={clinicalDocSuccess}
                preview={clinicalDocPreview}
            />
          </div>
        </div>
      </div>


      {/* Patient Information */}
      <div className="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
        <h3 className="font-medium text-blue-900 dark:text-blue-300 mb-2">Patient Information</h3>

        <div className="space-y-4">
          {/* Name and Demographics */}
          <div className="grid grid-cols-2 gap-4">
            <FormInputWithIndicator
              label="First Name"
              value={formData.patient_first_name || ''}
              onChange={(value) => updateFormData({ 
                patient_first_name: value,
                patient: { ...formData.patient, first_name: value }
              })}
              required={true}
              error={errors.patient_first_name}
              isExtracted={formData.patient_first_name_extracted}
              placeholder="Enter first name"
            />

            <FormInputWithIndicator
              label="Last Name"
              value={formData.patient_last_name || ''}
              onChange={(value) => updateFormData({ 
                patient_last_name: value,
                patient: { ...formData.patient, last_name: value }
              })}
              required={true}
              error={errors.patient_last_name}
              isExtracted={formData.patient_last_name_extracted}
              placeholder="Enter last name"
            />

            <FormInputWithIndicator
              label="Date of Birth"
              type="date"
              value={formData.patient_dob || ''}
              onChange={(value) => updateFormData({ 
                patient_dob: value,
                patient: { ...formData.patient, dob: value }
              })}
              required={true}
              error={errors.patient_dob}
              isExtracted={formData.patient_dob_extracted}
            />

            <Select
              label="Gender"
              value={formData.patient_gender || ''}
              onChange={(e) => updateFormData({ 
                patient_gender: e.target.value,
                patient: { ...formData.patient, gender: e.target.value }
              })}
              options={[
                { value: 'male', label: 'Male' },
                { value: 'female', label: 'Female' },
                { value: 'other', label: 'Other' },
                { value: 'unknown', label: 'Prefer not to say' }
              ]}
              placeholder="Please Select"
              error={errors.patient_gender}
            />
          </div>

          {/* Address - Single Google Autocomplete Input */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Patient Address <span className="text-red-500">*</span>
            </label>
            <GoogleAddressAutocompleteWithFallback
              onPlaceSelect={handleAddressSelect}
              value={formData.patient?.address?.text || ''}
              onChange={(value) => updateFormData({
                patient: {
                  ...formData.patient,
                  address: {
                    ...formData.patient?.address,
                    text: value
                  }
                }
              })}
              defaultValue={formData.patient?.address?.text || ''}
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              placeholder="Start typing address..."
              required
            />
            {errors.patient_address && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_address}</p>
            )}
          </div>

          {/* Contact Information */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Phone Number <span className="text-gray-500">(Optional)</span>
              </label>
              <input
                type="tel"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_phone || ''}
                onChange={(e) => updateFormData({ 
                  patient_phone: e.target.value,
                  patient: { ...formData.patient, phone: e.target.value }
                })}
                placeholder="(555) 123-4567"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Email Address <span className="text-gray-500">(Optional)</span>
              </label>
              <input
                type="email"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_email || ''}
                onChange={(e) => updateFormData({ 
                  patient_email: e.target.value,
                  patient: { ...formData.patient, email: e.target.value }
                })}
                placeholder="patient@email.com"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Service Date & Shipping */}
      <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
        <h3 className="font-medium text-green-900 dark:text-green-300 mb-3">Service Date & Shipping</h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Expected Service Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.expected_service_date || ''}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              placeholder="MM/DD/YYYY"
            />
            {errors.expected_service_date && (
              <p className="mt-1 text-sm text-red-500">{errors.expected_service_date}</p>
            )}
          </div>

          <div>
            <Select
              label="Shipping Speed"
              value={formData.shipping_speed || ''}
              onChange={(e) => updateFormData({ shipping_speed: e.target.value })}
              options={shippingOptions}
              error={errors.shipping_speed}
              required
            />
            {errors.shipping_speed && (
              <p className="mt-1 text-sm text-red-500">{errors.shipping_speed}</p>
            )}
          </div>

          {formData.shipping_speed === 'choose_delivery_date' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Delivery Date
              </label>
              <input
                type="date"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.delivery_date || ''}
                onChange={(e) => updateFormData({ delivery_date: e.target.value })}
                min={new Date().toISOString().split('T')[0]}
              />
            </div>
          )}

          {/* Time-based validation warning */}
          {(() => {
            const today = new Date();
            const serviceDate = new Date(formData.expected_service_date || '');
            const tomorrow = new Date(today);
            tomorrow.setDate(today.getDate() + 1);
            
            // Check if service date is within 24 hours
            const timeDiff = serviceDate.getTime() - today.getTime();
            const hoursDiff = timeDiff / (1000 * 60 * 60);
            const isWithin24Hours = hoursDiff > 0 && hoursDiff <= 24;
            
            // Check if service date is tomorrow and current time is after 2 PM CST
            const isTomorrow = serviceDate.toDateString() === tomorrow.toDateString();
            const currentHourCST = new Date().toLocaleString("en-US", {timeZone: "America/Chicago"});
            const currentTimeCST = new Date(currentHourCST);
            const cutoffTime = new Date(currentTimeCST);
            cutoffTime.setHours(14, 0, 0, 0); // 2 PM CST
            
            const isPastCutoff = currentTimeCST > cutoffTime;
            
            if (isTomorrow && isPastCutoff) {
              return (
                <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                  <div className="flex items-start">
                    <svg className="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    <div>
                      <h4 className="text-sm font-medium text-amber-800 dark:text-amber-300">
                        Late Order Warning
                      </h4>
                      <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Service date is tomorrow and it's after 2 PM CST. Contact us via Support@mscwoundcare.com or call to see if possible.
                      </p>
                    </div>
                  </div>
                </div>
              );
            } else if (isWithin24Hours && !isTomorrow) {
              return (
                <div className="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                  <div className="flex items-start">
                    <svg className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                    </svg>
                    <div>
                      <h4 className="text-sm font-medium text-blue-800 dark:text-blue-300">
                        24-Hour Notice
                      </h4>
                      <p className="mt-1 text-sm text-blue-700 dark:text-blue-400">
                        Service date is within 24 hours, contact Administration before placing.
                      </p>
                    </div>
                  </div>
                </div>
              );
            }
            return null;
          })()}
        </div>
      </div>

      {/* Primary Insurance */}
      <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
        <h3 className="font-medium text-purple-900 dark:text-purple-300 mb-3">Primary Insurance</h3>

        <div className="space-y-4">
          {/* Patient is subscriber question */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Is the patient the insurance subscriber? <span className="text-red-500">*</span>
            </label>
            <div className="space-x-4">
              <label className="inline-flex items-center">
                <input
                  type="radio"
                  className="form-radio text-blue-600"
                  name="subscriber"
                  value="yes"
                  checked={formData.patient_is_subscriber === true}
                  onChange={() => {
                    updateFormData({ patient_is_subscriber: true });
                    setShowCaregiver(false);
                  }}
                />
                <span className="ml-2 text-gray-700 dark:text-gray-300">Yes</span>
              </label>
              <label className="inline-flex items-center">
                <input
                  type="radio"
                  className="form-radio text-blue-600"
                  name="subscriber"
                  value="no"
                  checked={formData.patient_is_subscriber === false}
                  onChange={() => {
                    updateFormData({ patient_is_subscriber: false });
                    setShowCaregiver(true);
                  }}
                />
                <span className="ml-2 text-gray-700 dark:text-gray-300">No</span>
              </label>
            </div>
          </div>

          {/* Caregiver Information */}
          {showCaregiver && (
            <div className="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
              <h4 className="text-sm font-medium text-yellow-900 dark:text-yellow-300 mb-2">
                Subscriber/Responsible Party Information
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Subscriber Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.caregiver_name || ''}
                    onChange={(e) => updateFormData({ caregiver_name: e.target.value })}
                  />
                  {errors.caregiver_name && (
                    <p className="mt-1 text-sm text-red-500">{errors.caregiver_name}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Relationship to Patient <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.caregiver_relationship || ''}
                    onChange={(e) => updateFormData({ caregiver_relationship: e.target.value })}
                    placeholder="Spouse, Parent, etc."
                  />
                  {errors.caregiver_relationship && (
                    <p className="mt-1 text-sm text-red-500">{errors.caregiver_relationship}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Phone Number
                  </label>
                  <input
                    type="tel"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.caregiver_phone || ''}
                    onChange={(e) => updateFormData({ caregiver_phone: e.target.value })}
                    placeholder="(555) 123-4567"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Primary insurance fields on one line */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Insurance Name <span className="text-red-500">*</span>
              </label>
              <PayerSearchInput
                value={{
                  name: formData.primary_insurance_name || '',
                  id: formData.primary_payer_id || ''
                }}
                onChange={(payer) => {
                  updateFormData({
                    primary_insurance_name: payer.name,
                    primary_payer_id: payer.id,
                    primary_payer_phone: getPayerPhone(payer.name)
                  });
                }}
                error={errors.primary_insurance_name}
                placeholder="Search for insurance..."
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Member ID <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.primary_member_id || ''}
                onChange={(e) => updateFormData({ primary_member_id: e.target.value })}
                placeholder="1234567890A"
              />
              {errors.primary_member_id && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_member_id}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Payer Phone <span className="text-gray-500">(Optional)</span> {formData.primary_payer_phone && '(Auto-filled)'}
              </label>
              <input
                type="tel"
                className={cn(
                  "w-full p-2 border rounded focus:ring-2 focus:ring-blue-500",
                  formData.primary_payer_phone
                    ? "bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed"
                    : "bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:border-blue-500"
                )}
                value={formData.primary_payer_phone || ''}
                onChange={(e) => updateFormData({ primary_payer_phone: e.target.value })}
                readOnly={!!formData.primary_payer_phone}
                placeholder="1-800-555-0100"
              />
            </div>
          </div>

          {/* Plan Type and Network Status on same row */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Select
                label="Plan Type"
                value={formData.primary_plan_type || ''}
                onChange={(e) => updateFormData({ primary_plan_type: e.target.value })}
                options={planTypes.slice(1)} // Remove the placeholder from options since we'll use the placeholder prop
                placeholder="Please Select Plan Type"
                error={errors.primary_plan_type}
                required
              />
              {errors.primary_plan_type && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_plan_type}</p>
              )}
            </div>

            <div>
              <Select
                label="Physician Status With Primary"
                value={formData.primary_physician_network_status || ''}
                onChange={(e) => updateFormData({ primary_physician_network_status: e.target.value })}
                options={networkStatusOptions.slice(1)} // Remove the placeholder from options since we'll use the placeholder prop
                placeholder="Please Select Network Status"
                error={errors.primary_physician_network_status}
                required
              />
              {errors.primary_physician_network_status && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_physician_network_status}</p>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Secondary Insurance Toggle */}
      <div className="space-y-4">
        <div className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
          <label className="flex items-center cursor-pointer">
            <input
              type="checkbox"
              className="form-checkbox h-4 w-4 text-blue-600 rounded"
              checked={formData.has_secondary_insurance || false}
              onChange={(e) => updateFormData({ has_secondary_insurance: e.target.checked })}
            />
            <span className="ml-2 text-gray-700 dark:text-gray-300 font-medium">
              Patient has secondary insurance
            </span>
          </label>
        </div>

        {/* Secondary Insurance Details */}
        {formData.has_secondary_insurance && (
          <div className="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
            <h3 className="font-medium text-indigo-900 dark:text-indigo-300 mb-3">Secondary Insurance</h3>

            <div className="space-y-4">
              {/* Secondary subscriber question */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Is the patient the secondary insurance subscriber?
                </label>
                <div className="space-x-4">
                  <label className="inline-flex items-center">
                    <input
                      type="radio"
                      className="form-radio text-blue-600"
                      name="secondary_subscriber"
                      value="yes"
                      checked={formData.secondary_patient_is_subscriber === true || formData.secondary_patient_is_subscriber === undefined}
                      onChange={() => {
                        updateFormData({ secondary_patient_is_subscriber: true });
                      }}
                    />
                    <span className="ml-2 text-gray-700 dark:text-gray-300">Yes</span>
                  </label>
                  <label className="inline-flex items-center">
                    <input
                      type="radio"
                      className="form-radio text-blue-600"
                      name="secondary_subscriber"
                      value="no"
                      checked={formData.secondary_patient_is_subscriber === false}
                      onChange={() => {
                        updateFormData({ secondary_patient_is_subscriber: false });
                      }}
                    />
                    <span className="ml-2 text-gray-700 dark:text-gray-300">No</span>
                  </label>
                </div>
              </div>

              {/* Secondary Subscriber Information - Only show if patient is not subscriber */}
              {formData.secondary_patient_is_subscriber === false && (
                <div className="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                  <h4 className="text-sm font-medium text-yellow-900 dark:text-yellow-300 mb-2">
                    Secondary Insurance Subscriber Information
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Subscriber Name <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        value={formData.secondary_subscriber_name || ''}
                        onChange={(e) => updateFormData({ secondary_subscriber_name: e.target.value })}
                        placeholder="Subscriber's full name"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Relationship to Patient <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        value={formData.secondary_subscriber_relationship || ''}
                        onChange={(e) => updateFormData({ secondary_subscriber_relationship: e.target.value })}
                        placeholder="Spouse, Parent, etc."
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Insurance details on one line */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Insurance Name <span className="text-red-500">*</span>
                  </label>
                  <PayerSearchInput
                    value={{
                      name: formData.secondary_insurance_name || '',
                      id: formData.secondary_payer_id || ''
                    }}
                    onChange={(payer) => updateFormData({
                      secondary_insurance_name: payer.name,
                      secondary_payer_id: payer.id,
                      secondary_payer_phone: getPayerPhone(payer.name)
                    })}
                    error={errors.secondary_insurance}
                    placeholder="Search for insurance..."
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Member ID <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_member_id || ''}
                    onChange={(e) => updateFormData({ secondary_member_id: e.target.value })}
                    placeholder="Secondary policy number"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Payer Phone <span className="text-gray-500">(Optional)</span>
                  </label>
                  <input
                    type="tel"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_payer_phone || ''}
                    onChange={(e) => updateFormData({ secondary_payer_phone: e.target.value })}
                    placeholder="(800) 555-0100"
                  />
                </div>
              </div>

              {/* Plan Type and Network Status on same row */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Select
                    label="Plan Type"
                    value={formData.secondary_plan_type || ''}
                    onChange={(e) => updateFormData({ secondary_plan_type: e.target.value })}
                    options={planTypes.slice(1)} // Remove the placeholder from options since we'll use the placeholder prop
                    placeholder="Please Select Plan Type"
                  />
                </div>

                <div>
                  <Select
                    label="Physician Status With Secondary"
                    value={formData.secondary_physician_network_status || ''}
                    onChange={(e) => updateFormData({ secondary_physician_network_status: e.target.value })}
                    options={networkStatusOptions.slice(1)} // Remove the placeholder from options since we'll use the placeholder prop
                    placeholder="Please Select Network Status"
                    error={errors.secondary_physician_network_status}
                  />
                  {errors.secondary_physician_network_status && (
                    <p className="mt-1 text-sm text-red-500">{errors.secondary_physician_network_status}</p>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

      </div>


    </div>
  );
}

// Simple File Upload Component
interface FileUploadProps {
  label: string;
  onFileUpload: (file: File) => void;
  isProcessing: boolean;
  isSuccess: boolean;
  preview: string | null;
}

const FileUpload: React.FC<FileUploadProps> = ({ label, onFileUpload, isProcessing, isSuccess, preview }) => {
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      onFileUpload(e.target.files[0]);
    }
  };

  return (
    <div
      className="flex flex-col items-center justify-center p-2 bg-white dark:bg-gray-800 rounded-md cursor-pointer h-full"
      onClick={() => inputRef.current?.click()}
    >
      <input type="file" ref={inputRef} onChange={handleFileChange} className="hidden" accept="image/*,application/pdf" />
      {preview ? (
        preview === 'pdf' ? (
          <div className="text-center">
            <FiCheck className="mx-auto h-6 w-6 text-green-500" />
            <p className="text-xs mt-1">PDF Uploaded</p>
          </div>
        ) : (
          <img src={preview} alt="preview" className="h-16 w-full object-contain rounded-md" />
        )
      ) : (
        <FiUpload className="h-6 w-6 text-gray-400" />
      )}
      <span className="text-xs mt-2 text-center">{label}</span>
      {isProcessing && <FiRefreshCw className="h-4 w-4 text-blue-500 animate-spin absolute" />}
      {isSuccess && <FiCheck className="h-4 w-4 text-green-500 absolute" />}
    </div>
  );
};


export default Step2PatientInsurance;