import { useState, useRef } from 'react';
import { FiCreditCard, FiRefreshCw, FiCheck, FiInfo, FiUpload } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import GoogleAddressAutocompleteSimple from '@/Components/GoogleAddressAutocompleteSimple';
import GoogleAddressAutocompleteWithFallback from '@/Components/GoogleAddressAutocompleteWithFallback';
import PayerSearchInput from '@/Components/PayerSearchInput';
import FormInputWithIndicator from '@/Components/ui/FormInputWithIndicator';
import Select from '@/Components/ui/Select';

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
  const [saveToPatientResource, setSaveToPatientResource] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  // Shipping speed options
  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM) - Next business day' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  // Plan type options
  const planTypes = [
    { value: 'ffs', label: 'FFS (Fee for Service)' },
    { value: 'hmo', label: 'HMO' },
    { value: 'ppo', label: 'PPO' },
    { value: 'pos', label: 'POS' },
    { value: 'epo', label: 'EPO' },
    { value: 'medicare_advantage', label: 'Medicare Advantage' },
    { value: 'other', label: 'Other' },
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

        const response = await fetch('/api/insurance-card/analyze', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: apiFormData,
        });

        if (response.ok) {
          const result = await response.json();

          if (result.success && result.data) {
            const updates: any = {};

            // Patient information
            if (result.data.patient_first_name) updates.patient_first_name = result.data.patient_first_name;
            if (result.data.patient_last_name) updates.patient_last_name = result.data.patient_last_name;
            if (result.data.patient_dob) updates.patient_dob = result.data.patient_dob;
            if (result.data.patient_member_id) updates.patient_member_id = result.data.patient_member_id;

            // Insurance information
            if (result.data.payer_name) updates.primary_insurance_name = result.data.payer_name;
            if (result.data.payer_id) updates.primary_member_id = result.data.payer_id;
            if (result.data.insurance_type) updates.primary_plan_type = result.data.insurance_type;

            updates.insurance_card_auto_filled = true;

            updateFormData(updates);
            setAutoFillSuccess(true);

            setTimeout(() => {
              setAutoFillSuccess(false);
            }, 5000);
          }
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
      } finally {
        setIsProcessingCard(false);
      }
    }
  };

  const handleAddressSelect = (place: any) => {
    const addressComponents = place.address_components || [];
    const updates: any = {};

    addressComponents.forEach((component: any) => {
      const types = component.types;
      if (types.includes('street_number')) {
        updates.patient_address_line1 = component.long_name;
      } else if (types.includes('route')) {
        updates.patient_address_line1 = (updates.patient_address_line1 || '') + ' ' + component.long_name;
      } else if (types.includes('locality')) {
        updates.patient_city = component.long_name;
      } else if (types.includes('administrative_area_level_1')) {
        updates.patient_state = component.short_name;
      } else if (types.includes('postal_code')) {
        updates.patient_zip = component.long_name;
      }
    });

    updateFormData(updates);

    // TODO: Optional enhancement - Create FHIR patient record early if enough data is available
    // This would provide patient_fhir_id sooner in the process
    // if (hasMinimalPatientData(formData, updates)) {
    //   createFhirPatientEarly(formData, updates);
    // }
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
              options={[
                { value: '', label: 'Select a provider...' },
                ...providers.map(p => ({
                  value: p.id,
                  label: `${p.name}${p.credentials ? `, ${p.credentials}` : ''} ${p.npi ? `(NPI: ${p.npi})` : ''}`
                }))
              ]}
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
              options={[
                { value: '', label: 'Select a facility...' },
                ...facilities.map(f => ({
                  value: f.id,
                  label: `${f.name} ${f.address ? `(${f.address})` : ''}`
                }))
              ]}
              error={errors.facility_id}
              required
            />
            {errors.facility_id && (
              <p className="mt-1 text-sm text-red-500">{errors.facility_id}</p>
            )}
          </div>
        </div>
      </div>

      {/* Insurance Card Upload Section */}
      <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <div className="flex items-start">
          <FiInfo className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0 mr-3" />
          <div className="flex-1">
            <h3 className="text-sm font-medium text-blue-900 dark:text-blue-300 mb-1">
              Quick Auto-Fill with Insurance Card
            </h3>
            <p className="text-sm text-blue-700 dark:text-blue-400">
              Upload insurance card to automatically populate patient and insurance information
            </p>
          </div>
        </div>

        {/* Single upload area for both front and back */}
        <div className="mt-4">
          <div
            onClick={() => fileInputRef.current?.click()}
            className="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-4 text-center cursor-pointer transition-all hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800"
          >
            <FiCreditCard className="mx-auto h-10 w-10 mb-2 text-gray-400" />
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Upload Insurance Card
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
              You can upload front and back images or a single PDF
            </p>
            
            {/* Show uploaded files */}
            {(cardFrontPreview || cardBackPreview) && (
              <div className="mt-4 space-y-2">
                {cardFrontPreview && (
                  <div className="flex items-center justify-center space-x-2 text-sm">
                    <FiCheck className="h-4 w-4 text-green-500" />
                    <span className="text-gray-600 dark:text-gray-400">Front uploaded</span>
                  </div>
                )}
                {cardBackPreview && (
                  <div className="flex items-center justify-center space-x-2 text-sm">
                    <FiCheck className="h-4 w-4 text-green-500" />
                    <span className="text-gray-600 dark:text-gray-400">Back uploaded</span>
                  </div>
                )}
              </div>
            )}
            
            <button
              type="button"
              className="mt-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <FiUpload className="h-4 w-4 mr-2" />
              Choose Files
            </button>
          </div>
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*,application/pdf"
            className="hidden"
            multiple
            onChange={(e) => {
              const files = Array.from(e.target.files || []);
              files.forEach((file, index) => {
                // First file is front, second is back
                handleInsuranceCardUpload(file, index === 0 ? 'front' : 'back');
              });
            }}
          />
        </div>

        {/* Save to patient resource checkbox */}
        <div className="mt-4 flex items-center">
          <input
            type="checkbox"
            id="saveToPatientResource"
            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            checked={saveToPatientResource}
            onChange={(e) => {
              setSaveToPatientResource(e.target.checked);
              updateFormData({ save_card_to_patient_resource: e.target.checked });
            }}
          />
          <label htmlFor="saveToPatientResource" className="ml-2 block text-sm text-gray-700 dark:text-gray-300">
            Save insurance card to patient resource for future use
          </label>
        </div>

        {/* Processing status */}
        {isProcessingCard && (
          <div className="mt-4 flex items-center justify-center">
            <FiRefreshCw className="animate-spin h-5 w-5 mr-2 text-blue-500" />
            <span className="text-sm text-gray-600 dark:text-gray-400">Processing insurance card...</span>
          </div>
        )}

        {autoFillSuccess && (
          <div className="mt-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg flex items-center">
            <FiCheck className="h-5 w-5 mr-2 text-green-500" />
            <span className="text-sm text-green-700 dark:text-green-400">
              Successfully extracted information from insurance card!
            </span>
          </div>
        )}
      </div>

      {/* Patient Information */}
      <div className="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
        <h3 className="font-medium text-blue-900 dark:text-blue-300 mb-2">Patient Information</h3>

        <div className="grid grid-cols-2 gap-4">
          <FormInputWithIndicator
            label="First Name"
            value={formData.patient_first_name || ''}
            onChange={(value) => updateFormData({ patient_first_name: value })}
            required={true}
            error={errors.patient_first_name}
            isExtracted={formData.patient_first_name_extracted}
            placeholder="Enter first name"
          />

          <FormInputWithIndicator
            label="Last Name"
            value={formData.patient_last_name || ''}
            onChange={(value) => updateFormData({ patient_last_name: value })}
            required={true}
            error={errors.patient_last_name}
            isExtracted={formData.patient_last_name_extracted}
            placeholder="Enter last name"
          />

          <FormInputWithIndicator
            label="Date of Birth"
            type="date"
            value={formData.patient_dob || ''}
            onChange={(value) => updateFormData({ patient_dob: value })}
            required={true}
            error={errors.patient_dob}
            isExtracted={formData.patient_dob_extracted}
          />

          <div>
            <Select
              label="Gender"
              value={formData.patient_gender || ''}
              onChange={(e) => updateFormData({ patient_gender: e.target.value })}
              options={[
                { value: 'male', label: 'Male' },
                { value: 'female', label: 'Female' },
                { value: 'other', label: 'Other' },
                { value: 'unknown', label: 'Prefer not to say' }
              ]}
            />
          </div>
        </div>

        {/* Address */}
        <div className="mt-4 grid grid-cols-1 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Address Line 1
            </label>
            <GoogleAddressAutocompleteWithFallback
              onPlaceSelect={handleAddressSelect}
              value={formData.patient_address_line1}
              onChange={(value) => updateFormData({ patient_address_line1: value })}
              defaultValue={formData.patient_address_line1}
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              placeholder="Start typing address..."
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Address Line 2
            </label>
            <input
              type="text"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.patient_address_line2 || ''}
              onChange={(e) => updateFormData({ patient_address_line2: e.target.value })}
              placeholder="Apartment, suite, etc. (optional)"
            />
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                City
              </label>
              <input
                type="text"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_city || ''}
                onChange={(e) => updateFormData({ patient_city: e.target.value })}
              />
            </div>

            <div>
              <Select
                label="State"
                value={formData.patient_state || ''}
                onChange={(e) => updateFormData({ patient_state: e.target.value })}
                options={[
                  { value: '', label: 'Select...' },
                  ...states.map(state => ({ value: state, label: state }))
                ]}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                ZIP Code
              </label>
              <input
                type="text"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_zip || ''}
                onChange={(e) => updateFormData({ patient_zip: e.target.value })}
                placeholder="12345"
                maxLength={10}
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Phone Number <span className="text-gray-500">(Optional)</span>
              </label>
              <input
                type="tel"
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_phone || ''}
                onChange={(e) => updateFormData({ patient_phone: e.target.value })}
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
                onChange={(e) => updateFormData({ patient_email: e.target.value })}
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

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Delivery Date (Auto-calculated)
            </label>
            <input
              type="date"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
              value={formData.delivery_date || ''}
              readOnly
            />
          </div>
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

          {/* Plan Type on separate row */}
          <div>
            <Select
              label="Plan Type"
              value={formData.primary_plan_type || ''}
              onChange={(e) => updateFormData({ primary_plan_type: e.target.value })}
              options={planTypes}
              error={errors.primary_plan_type}
              required
            />
            {errors.primary_plan_type && (
              <p className="mt-1 text-sm text-red-500">{errors.primary_plan_type}</p>
            )}
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
                      checked={formData.secondary_patient_is_subscriber === true}
                      onChange={() => {
                        updateFormData({ secondary_patient_is_subscriber: true });
                        setShowSecondaryCaregiver(false);
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
                        setShowSecondaryCaregiver(true);
                      }}
                    />
                    <span className="ml-2 text-gray-700 dark:text-gray-300">No</span>
                  </label>
                </div>
              </div>

              {/* Secondary Subscriber Information - Only show if patient is not subscriber */}
              {showSecondaryCaregiver && (
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

              {/* Plan Type on separate row */}
              <div>
                <Select
                  label="Plan Type"
                  value={formData.secondary_plan_type || ''}
                  onChange={(e) => updateFormData({ secondary_plan_type: e.target.value })}
                  options={[
                    { value: '', label: 'Select plan type...' },
                    ...planTypes
                  ]}
                />
              </div>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}

export default Step2PatientInsurance;