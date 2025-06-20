import { useState, useRef } from 'react';
import { FiCamera, FiRefreshCw, FiAlertCircle, FiFile, FiCheck, FiInfo } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GoogleAddressAutocompleteSimple from '@/Components/GoogleAddressAutocompleteSimple';
import PayerSearchInput from '@/Components/PayerSearchInput';

interface Step2Props {
  formData: any;
  updateFormData: (data: any) => void;
  errors: Record<string, string>;
}

export default function Step2PatientInsurance({
  formData,
  updateFormData,
  errors
}: Step2Props) {
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

  const [cardFrontPreview, setCardFrontPreview] = useState<string | null>(null);
  const [cardBackPreview, setCardBackPreview] = useState<string | null>(null);
  const [isProcessingCard, setIsProcessingCard] = useState(false);
  const [autoFillSuccess, setAutoFillSuccess] = useState(false);
  const [showCaregiver, setShowCaregiver] = useState(!formData.patient_is_subscriber);

  const fileInputFrontRef = useRef<HTMLInputElement>(null);
  const fileInputBackRef = useRef<HTMLInputElement>(null);

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

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
          {/* Front of card */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Front of Insurance Card
            </label>
            <div
              onClick={() => fileInputFrontRef.current?.click()}
              className="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center cursor-pointer transition-all hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              {cardFrontPreview ? (
                cardFrontPreview === 'pdf' ? (
                  <div className="flex flex-col items-center">
                    <FiFile className="h-12 w-12 text-blue-500 mb-2" />
                    <p className="text-sm text-gray-600 dark:text-gray-400">PDF uploaded</p>
                  </div>
                ) : (
                  <img src={cardFrontPreview} alt="Insurance card front" className="mx-auto max-h-32" />
                )
              ) : (
                <>
                  <FiCamera className="mx-auto h-12 w-12 mb-2 text-gray-400" />
                  <p className="text-sm text-gray-600 dark:text-gray-400">Click to upload front</p>
                </>
              )}
            </div>
            <input
              ref={fileInputFrontRef}
              type="file"
              accept="image/*,application/pdf"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) handleInsuranceCardUpload(file, 'front');
              }}
            />
          </div>

          {/* Back of card */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Back of Insurance Card
            </label>
            <div
              onClick={() => fileInputBackRef.current?.click()}
              className="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center cursor-pointer transition-all hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              {cardBackPreview ? (
                cardBackPreview === 'pdf' ? (
                  <div className="flex flex-col items-center">
                    <FiFile className="h-12 w-12 text-blue-500 mb-2" />
                    <p className="text-sm text-gray-600 dark:text-gray-400">PDF uploaded</p>
                  </div>
                ) : (
                  <img src={cardBackPreview} alt="Insurance card back" className="mx-auto max-h-32" />
                )
              ) : (
                <>
                  <FiCamera className="mx-auto h-12 w-12 mb-2 text-gray-400" />
                  <p className="text-sm text-gray-600 dark:text-gray-400">Click to upload back</p>
                </>
              )}
            </div>
            <input
              ref={fileInputBackRef}
              type="file"
              accept="image/*,application/pdf"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) handleInsuranceCardUpload(file, 'back');
              }}
            />
          </div>
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
      <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h3 className="font-medium text-blue-900 dark:text-blue-300 mb-3">Patient Information</h3>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              First Name <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.patient_first_name || ''}
              onChange={(e) => updateFormData({ patient_first_name: e.target.value })}
            />
            {errors.patient_first_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_first_name}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Last Name <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.patient_last_name || ''}
              onChange={(e) => updateFormData({ patient_last_name: e.target.value })}
            />
            {errors.patient_last_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_last_name}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Date of Birth <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.patient_dob || ''}
              onChange={(e) => updateFormData({ patient_dob: e.target.value })}
            />
            {errors.patient_dob && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_dob}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Gender
            </label>
            <select
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.patient_gender || ''}
              onChange={(e) => updateFormData({ patient_gender: e.target.value })}
            >
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
              <option value="unknown">Prefer not to say</option>
            </select>
          </div>
        </div>

        {/* Address */}
        <div className="mt-4 grid grid-cols-1 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Address Line 1
            </label>
            <GoogleAddressAutocompleteSimple
              onPlaceSelect={handleAddressSelect}
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
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                State
              </label>
              <select
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                value={formData.patient_state || ''}
                onChange={(e) => updateFormData({ patient_state: e.target.value })}
              >
                <option value="">Select...</option>
                {states.map(state => (
                  <option key={state} value={state}>{state}</option>
                ))}
              </select>
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
                Phone Number
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
                Email Address (Optional)
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

        {/* Patient is subscriber checkbox */}
        <div className="mt-4">
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Is the patient the insurance subscriber?
          </label>
          <div className="space-x-4">
            <label className="inline-flex items-center">
              <input
                type="radio"
                className="form-radio text-blue-600"
                name="subscriber"
                value="yes"
                checked={formData.patient_is_subscriber === true}
                onChange={(e) => {
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
                onChange={(e) => {
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
          <div className="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
            <h4 className="text-sm font-medium text-yellow-900 dark:text-yellow-300 mb-2">
              Caregiver/Responsible Party Information
            </h4>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Caregiver Name <span className="text-red-500">*</span>
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
                  Relationship <span className="text-red-500">*</span>
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
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Shipping Speed <span className="text-red-500">*</span>
            </label>
            <select
              className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              value={formData.shipping_speed || ''}
              onChange={(e) => updateFormData({ shipping_speed: e.target.value })}
            >
              {shippingOptions.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
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
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                Payer Phone {formData.primary_payer_phone && '(Auto-filled)'}
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

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Plan Type <span className="text-red-500">*</span>
              </label>
              <select
                className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
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
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                      secondary_payer_id: payer.id
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
                    Subscriber Name
                  </label>
                  <input
                    type="text"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_subscriber_name || ''}
                    onChange={(e) => updateFormData({ secondary_subscriber_name: e.target.value })}
                    placeholder="If different from patient"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Subscriber DOB
                  </label>
                  <input
                    type="date"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_subscriber_dob || ''}
                    onChange={(e) => updateFormData({ secondary_subscriber_dob: e.target.value })}
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Payer Phone
                  </label>
                  <input
                    type="tel"
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_payer_phone || ''}
                    onChange={(e) => updateFormData({ secondary_payer_phone: e.target.value })}
                    placeholder="(800) 555-0100"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Plan Type
                  </label>
                  <select
                    className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    value={formData.secondary_plan_type || ''}
                    onChange={(e) => updateFormData({ secondary_plan_type: e.target.value })}
                  >
                    <option value="">Select plan type...</option>
                    <option value="hmo">HMO</option>
                    <option value="ppo">PPO</option>
                    <option value="medicare_supplement">Medicare Supplement</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Prior Authorization Permission */}
        <div className="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
          <label className="flex items-center cursor-pointer">
            <input
              type="checkbox"
              className="form-checkbox h-4 w-4 text-green-600 rounded"
              checked={formData.prior_auth_permission || false}
              onChange={(e) => updateFormData({ prior_auth_permission: e.target.checked })}
            />
            <span className="ml-2 text-gray-700 dark:text-gray-300">
              MSC may initiate/follow up on prior authorization
            </span>
          </label>
        </div>
      </div>
    </div>
  );
}
