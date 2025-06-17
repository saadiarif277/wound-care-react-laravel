import React, { useState, useRef, useEffect } from 'react';
import { FiCamera, FiRefreshCw, FiAlertCircle, FiFile, FiCheck, FiChevronDown, FiChevronRight } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import PayerSearchInput from '@/Components/PayerSearchInput';

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
}

export default function Step1PatientInfoNew({ 
  formData, 
  updateFormData, 
  facilities, 
  woundTypes,
  errors 
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
        
        console.log('Sending insurance card for analysis...');
        
        const response = await fetch('/api/insurance-card/analyze', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: apiFormData,
        });
        
        console.log('Response status:', response.status);
        
        if (response.ok) {
          const result = await response.json();
          console.log('Full API response:', result);
          
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
            console.log('Auto-fill data received:', result.data);
            
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
              console.log('Extracted Member ID:', result.data.patient_member_id);
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
            
            console.log('Updates to apply:', updates);
            console.log('Fields extracted:', fieldsExtracted);
            
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
            console.log('No data in response:', result);
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

  return (
    <div className="space-y-4">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-xl font-bold", t.text.primary)}>
          Patient & Insurance Information
        </h2>
        <p className={cn("mt-1 text-sm", t.text.secondary)}>
          Upload insurance cards for auto-fill or enter manually
        </p>
      </div>

      {/* Insurance Card Capture Section */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-md font-medium mb-3", t.text.primary)}>
          Insurance Card Upload
        </h3>
        
        {/* Card Upload */}
        <div className="mb-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {/* Front of Card */}
            <div>
              <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                Front of Insurance Card
              </label>
              <div 
                className={cn(
                  "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                  theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                  cardFrontPreview && 'border-green-500'
                )}
                onClick={() => fileInputFrontRef.current?.click()}
              >
                <input
                  ref={fileInputFrontRef}
                  type="file"
                  accept="image/*,.pdf"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleInsuranceCardUpload(file, 'front');
                  }}
                />
                {cardFrontPreview ? (
                  <div>
                    {cardFrontPreview === 'pdf' ? (
                      <div className="flex flex-col items-center">
                        <FiFile className="h-10 w-10 text-indigo-500 mb-1" />
                        <p className={cn("text-xs font-medium", t.text.primary)}>PDF Uploaded</p>
                      </div>
                    ) : (
                      <img 
                        src={cardFrontPreview} 
                        alt="Insurance card front" 
                        className="mx-auto h-20 object-contain mb-1" 
                      />
                    )}
                    <p className={cn("text-xs", t.text.secondary)}>Click to replace</p>
                  </div>
                ) : (
                  <div>
                    <FiCamera className="mx-auto h-8 w-8 text-gray-400 mb-1" />
                    <p className={cn("text-xs", t.text.secondary)}>
                      Click to upload
                    </p>
                  </div>
                )}
              </div>
            </div>

            {/* Back of Card */}
            <div>
              <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
                Back of Insurance Card
              </label>
              <div 
                className={cn(
                  "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                  theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                  cardBackPreview && 'border-green-500'
                )}
                onClick={() => fileInputBackRef.current?.click()}
              >
                <input
                  ref={fileInputBackRef}
                  type="file"
                  accept="image/*,.pdf"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleInsuranceCardUpload(file, 'back');
                  }}
                />
                {cardBackPreview ? (
                  <div>
                    {cardBackPreview === 'pdf' ? (
                      <div className="flex flex-col items-center">
                        <FiFile className="h-10 w-10 text-indigo-500 mb-1" />
                        <p className={cn("text-xs font-medium", t.text.primary)}>PDF Uploaded</p>
                      </div>
                    ) : (
                      <img 
                        src={cardBackPreview} 
                        alt="Insurance card back" 
                        className="mx-auto h-20 object-contain mb-1" 
                      />
                    )}
                    <p className={cn("text-xs", t.text.secondary)}>Click to replace</p>
                  </div>
                ) : (
                  <div>
                    <FiCamera className="mx-auto h-8 w-8 text-gray-400 mb-1" />
                    <p className={cn("text-xs", t.text.secondary)}>
                      Click to upload
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Processing indicator */}
          {isProcessingCard && (
            <div className={cn("mt-3 p-2 rounded-md flex items-center", 
              theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
            )}>
              <FiRefreshCw className="animate-spin h-3 w-3 mr-2 text-blue-600" />
              <p className={cn("text-xs", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')}>
                Processing insurance cards...
              </p>
            </div>
          )}

          {/* Success message */}
          {autoFillSuccess && (
            <div className={cn("mt-3 p-2 rounded-md", 
              theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
            )}>
              <div className="flex items-center">
                <FiCheck className="h-3 w-3 mr-2 text-green-600" />
                <p className={cn("text-xs font-medium", theme === 'dark' ? 'text-green-400' : 'text-green-600')}>
                  Card processed! {extractedFields.length > 0 && `(${extractedFields.join(', ')})`}
                </p>
              </div>
            </div>
          )}

          {/* Error message */}
          {apiError && (
            <div className={cn("mt-3 p-2 rounded-md flex items-center", 
              theme === 'dark' ? 'bg-red-900/20' : 'bg-red-50'
            )}>
              <FiAlertCircle className="h-3 w-3 mr-2 text-red-600" />
              <p className={cn("text-xs", theme === 'dark' ? 'text-red-400' : 'text-red-600')}>
                {apiError}
              </p>
            </div>
          )}
        </div>

        {/* Insurance Details */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Insurance Payer *
            </label>
            <PayerSearchInput
              value={{
                name: formData.payer_name || '',
                id: formData.payer_id || ''
              }}
              onChange={(payer) => {
                updateFormData({
                  payer_name: payer.name,
                  payer_id: payer.id
                });
              }}
              placeholder="Search by payer name or ID..."
              error={errors.payer_name}
              required={true}
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Second Payer (if applicable)
            </label>
            <input
              type="text"
              value={formData.second_payer || ''}
              onChange={(e) => updateFormData({ second_payer: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="Secondary insurance"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Insurance Type
            </label>
            <select
              value={formData.insurance_type || ''}
              onChange={(e) => updateFormData({ insurance_type: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select Insurance Type</option>
              {insuranceTypeOptions.map(option => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Subscriber Information */}
        <div className="mt-4 space-y-4">
          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_patient_subscriber"
              checked={formData.is_patient_subscriber !== false}
              onChange={(e) => updateFormData({ is_patient_subscriber: e.target.checked })}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <label htmlFor="is_patient_subscriber" className={cn("ml-2 text-sm", t.text.secondary)}>
              Patient is the insurance subscriber
            </label>
          </div>

          {!formData.is_patient_subscriber && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Subscriber Name
                </label>
                <input
                  type="text"
                  value={formData.subscriber_name || ''}
                  onChange={(e) => updateFormData({ subscriber_name: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="Subscriber full name"
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Subscriber DOB
                </label>
                <input
                  type="date"
                  value={formData.subscriber_dob || ''}
                  onChange={(e) => updateFormData({ subscriber_dob: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Relationship to Patient
                </label>
                <select
                  value={formData.subscriber_relationship || ''}
                  onChange={(e) => updateFormData({ subscriber_relationship: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                >
                  <option value="">Select Relationship</option>
                  <option value="spouse">Spouse</option>
                  <option value="parent">Parent</option>
                  <option value="child">Child</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Patient Details */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-md font-medium mb-3", t.text.primary)}>
          Patient Details
        </h3>
        
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              First Name *
            </label>
            <input
              type="text"
              value={formData.patient_first_name || ''}
              onChange={(e) => updateFormData({ patient_first_name: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus, 
                errors.patient_first_name && "border-red-500"
              )}
              placeholder="John"
            />
            {errors.patient_first_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_first_name}</p>
            )}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Last Name *
            </label>
            <input
              type="text"
              value={formData.patient_last_name || ''}
              onChange={(e) => updateFormData({ patient_last_name: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus,
                errors.patient_last_name && "border-red-500"
              )}
              placeholder="Doe"
            />
            {errors.patient_last_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_last_name}</p>
            )}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Date of Birth *
            </label>
            <input
              type="date"
              value={formData.patient_dob || ''}
              onChange={(e) => updateFormData({ patient_dob: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus,
                errors.patient_dob && "border-red-500"
              )}
            />
            {errors.patient_dob && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_dob}</p>
            )}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Gender
            </label>
            <select
              value={formData.patient_gender || ''}
              onChange={(e) => updateFormData({ patient_gender: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
              <option value="Unknown">Unknown</option>
            </select>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Member ID
            </label>
            <input
              type="text"
              value={formData.patient_member_id || ''}
              onChange={(e) => updateFormData({ patient_member_id: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="Insurance Member ID"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Phone Number
            </label>
            <input
              type="tel"
              value={formData.patient_phone || ''}
              onChange={(e) => updateFormData({ patient_phone: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="(555) 555-5555"
            />
          </div>
        </div>

        {/* Address Information */}
        <div className="mt-4 space-y-3">
          <h4 className={cn("text-sm font-medium", t.text.primary)}>Address</h4>
          
          <div className="grid grid-cols-1 gap-3">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Address Line 1
              </label>
              <input
                type="text"
                value={formData.patient_address_line1 || ''}
                onChange={(e) => updateFormData({ patient_address_line1: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus)}
                placeholder="123 Main Street"
              />
            </div>

            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Address Line 2
              </label>
              <input
                type="text"
                value={formData.patient_address_line2 || ''}
                onChange={(e) => updateFormData({ patient_address_line2: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus)}
                placeholder="Apartment, suite, unit, etc."
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                City
              </label>
              <input
                type="text"
                value={formData.patient_city || ''}
                onChange={(e) => updateFormData({ patient_city: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus)}
                placeholder="New York"
              />
            </div>

            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                State
              </label>
              <select
                value={formData.patient_state || ''}
                onChange={(e) => updateFormData({ patient_state: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus)}
              >
                <option value="">Select State</option>
                {states.map(state => (
                  <option key={state} value={state}>{state}</option>
                ))}
              </select>
            </div>

            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                ZIP Code
              </label>
              <input
                type="text"
                value={formData.patient_zip || ''}
                onChange={(e) => updateFormData({ patient_zip: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus)}
                placeholder="12345"
                maxLength={10}
              />
            </div>
          </div>
        </div>

        {/* Caregiver Information */}
        <div className="mt-4 space-y-3">
          <div className="flex items-center">
            <input
              type="checkbox"
              id="has_caregiver"
              checked={showCaregiver}
              onChange={(e) => {
                setShowCaregiver(e.target.checked);
                if (!e.target.checked) {
                  // Clear caregiver data when unchecked
                  updateFormData({
                    caregiver_name: '',
                    caregiver_relationship: '',
                    caregiver_phone: ''
                  });
                }
              }}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <label htmlFor="has_caregiver" className={cn("ml-2 text-sm font-medium", t.text.primary)}>
              Patient has a caregiver
            </label>
          </div>
          
          {showCaregiver && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Caregiver Name
                </label>
                <input
                  type="text"
                  value={formData.caregiver_name || ''}
                  onChange={(e) => updateFormData({ caregiver_name: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="Caregiver full name"
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Relationship to Patient
                </label>
                <input
                  type="text"
                  value={formData.caregiver_relationship || ''}
                  onChange={(e) => updateFormData({ caregiver_relationship: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="e.g., Spouse, Child, etc."
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Caregiver Phone
                </label>
                <input
                  type="tel"
                  value={formData.caregiver_phone || ''}
                  onChange={(e) => updateFormData({ caregiver_phone: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="(555) 555-5555"
                />
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Service & Payer Information */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-md font-medium mb-3", t.text.primary)}>
          Service Information
        </h3>
        
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Place of Service
            </label>
            <select
              value={formData.place_of_service || ''}
              onChange={(e) => updateFormData({ place_of_service: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select Place of Service</option>
              {placeOfServiceOptions.map(option => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Expected Service Date *
            </label>
            <input
              type="date"
              value={formData.expected_service_date || ''}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              min={new Date().toISOString().split('T')[0]}
              className={cn("w-full", t.input.base, t.input.focus,
                errors.expected_service_date && "border-red-500"
              )}
            />
            {errors.expected_service_date && (
              <p className="mt-1 text-sm text-red-500">{errors.expected_service_date}</p>
            )}
          </div>
        </div>

        {/* SNF-specific fields */}
        {formData.place_of_service === '31' && (
          <div className="mt-4 space-y-4">
            <div className="flex items-center">
              <input
                type="checkbox"
                id="in_snf"
                checked={formData.in_snf || false}
                onChange={(e) => updateFormData({ in_snf: e.target.checked })}
                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label htmlFor="in_snf" className={cn("ml-2 text-sm", t.text.secondary)}>
                Patient is currently in SNF
              </label>
            </div>

            {formData.in_snf && (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                    SNF Admission Date
                  </label>
                  <input
                    type="date"
                    value={formData.snf_admission_date || ''}
                    onChange={(e) => updateFormData({ snf_admission_date: e.target.value })}
                    className={cn("w-full", t.input.base, t.input.focus)}
                  />
                </div>

                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                    Days in SNF
                  </label>
                  <input
                    type="number"
                    value={formData.snf_days || ''}
                    onChange={(e) => updateFormData({ snf_days: parseInt(e.target.value) || 0 })}
                    className={cn("w-full", t.input.base, t.input.focus)}
                    min="0"
                  />
                </div>

                <div className="sm:col-span-2">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="over_100_days"
                      checked={formData.over_100_days || false}
                      onChange={(e) => updateFormData({ over_100_days: e.target.checked })}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label htmlFor="over_100_days" className={cn("ml-2 text-sm", t.text.secondary)}>
                      Over 100 days in SNF
                    </label>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Global Period */}
        <div className="mt-4 space-y-4">
          <div className="flex items-center">
            <input
              type="checkbox"
              id="in_global_period"
              checked={formData.in_global_period || false}
              onChange={(e) => updateFormData({ in_global_period: e.target.checked })}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <label htmlFor="in_global_period" className={cn("ml-2 text-sm", t.text.secondary)}>
              Within global period of previous surgery
            </label>
          </div>

          {formData.in_global_period && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Previous Surgery CPT Code
                </label>
                <input
                  type="text"
                  value={formData.previous_surgery_cpt || ''}
                  onChange={(e) => updateFormData({ previous_surgery_cpt: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="e.g., 29881"
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Surgery Date
                </label>
                <input
                  type="date"
                  value={formData.surgery_date || ''}
                  onChange={(e) => updateFormData({ surgery_date: e.target.value })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                />
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Basic Wound Information */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-md font-medium mb-3", t.text.primary)}>
          Wound Information
        </h3>
        
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Wound Type
            </label>
            <select
              value={formData.wound_type || ''}
              onChange={(e) => updateFormData({ wound_type: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select Wound Type</option>
              {Object.entries(woundTypes).map(([code, name]) => (
                <option key={code} value={code}>{name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Wound Location
            </label>
            <select
              value={formData.wound_location || ''}
              onChange={(e) => updateFormData({ wound_location: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select Location</option>
              <option value="right_foot">Right Foot</option>
              <option value="left_foot">Left Foot</option>
              <option value="right_lower_leg">Right Lower Leg</option>
              <option value="left_lower_leg">Left Lower Leg</option>
              <option value="right_upper_leg">Right Upper Leg</option>
              <option value="left_upper_leg">Left Upper Leg</option>
              <option value="sacrum">Sacrum</option>
              <option value="coccyx">Coccyx</option>
              <option value="buttock">Buttock</option>
              <option value="heel">Heel</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Wound Duration (weeks)
            </label>
            <input
              type="number"
              value={formData.wound_duration_weeks || ''}
              onChange={(e) => updateFormData({ wound_duration_weeks: parseInt(e.target.value) || null })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="Number of weeks"
              min="1"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Length (cm)
            </label>
            <input
              type="number"
              value={formData.wound_length_cm || ''}
              onChange={(e) => updateFormData({ wound_length_cm: parseFloat(e.target.value) || null })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="0.0"
              step="0.1"
              min="0"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Width (cm)
            </label>
            <input
              type="number"
              value={formData.wound_width_cm || ''}
              onChange={(e) => updateFormData({ wound_width_cm: parseFloat(e.target.value) || null })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="0.0"
              step="0.1"
              min="0"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Primary Diagnosis Code (ICD-10)
            </label>
            <input
              type="text"
              value={formData.primary_icd10 || ''}
              onChange={(e) => updateFormData({ primary_icd10: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="e.g., L97.419"
            />
          </div>
        </div>
      </div>

      {/* Facility & Shipping */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-md font-medium mb-3", t.text.primary)}>
          Facility & Shipping
        </h3>
        
        <div className="space-y-4">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Select Facility (Shipping Address)
            </label>
            <select
              value={formData.facility_id || ''}
              onChange={(e) => updateFormData({ facility_id: e.target.value ? Number(e.target.value) : null })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select a facility</option>
              {facilities.map((facility) => (
                <option key={facility.id} value={facility.id}>
                  {facility.name}
                </option>
              ))}
            </select>
            
            {formData.facility_id && (() => {
              const selectedFacility = facilities.find(f => f.id === formData.facility_id);
              return selectedFacility?.address ? (
                <div className={cn("mt-2 p-3 rounded-md", 
                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-50'
                )}>
                  <p className={cn("text-sm font-medium", t.text.primary)}>
                    Shipping Address:
                  </p>
                  <p className={cn("text-sm", t.text.secondary)}>
                    {selectedFacility.address}
                  </p>
                </div>
              ) : null;
            })()}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
              Shipping Speed
            </label>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              {shippingOptions.map((option) => (
                <label 
                  key={option.value}
                  className={cn(
                    "relative flex items-center justify-center p-3 rounded-lg border-2 cursor-pointer transition-all",
                    formData.shipping_speed === option.value
                      ? "border-indigo-500 bg-indigo-50/10"
                      : theme === 'dark' ? "border-gray-700 hover:border-gray-600" : "border-gray-300 hover:border-gray-400"
                  )}
                >
                  <input
                    type="radio"
                    name="shipping_speed"
                    value={option.value}
                    checked={formData.shipping_speed === option.value}
                    onChange={(e) => handleShippingSpeedChange(e.target.value)}
                    className="sr-only"
                  />
                  <div className="text-center">
                    <div className={cn(
                      "text-xs font-medium",
                      formData.shipping_speed === option.value ? t.text.primary : t.text.secondary
                    )}>
                      {option.value === '1st_am' && '1st AM'}
                      {option.value === 'early_next_day' && 'Early Next'}
                      {option.value === 'standard_next_day' && 'Next Day'}
                      {option.value === 'standard_2_day' && '2 Day'}
                    </div>
                    <div className={cn("text-xs mt-1", t.text.muted)}>
                      {option.value === '1st_am' && 'Before 9AM'}
                      {option.value === 'early_next_day' && '9AM-12PM'}
                      {option.value === 'standard_next_day' && 'Office hrs'}
                      {option.value === 'standard_2_day' && 'Standard'}
                    </div>
                  </div>
                </label>
              ))}
            </div>
            
            {shippingError && (
              <div className={cn(
                "mt-2 p-2 rounded-md",
                shippingError.includes('cannot be fulfilled')
                  ? 'bg-red-50 border border-red-200'
                  : 'bg-blue-50 border border-blue-200'
              )}>
                <p className={cn(
                  "text-xs",
                  shippingError.includes('cannot be fulfilled')
                    ? (theme === 'dark' ? 'text-red-400' : 'text-red-800')
                    : 'text-blue-800'
                )}>
                  {shippingError}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Debug Panel */}
      <div className={cn("mt-4 p-3 rounded-lg", t.glass.panel)}>
        <div className="flex items-center justify-between mb-2">
          <h3 className={cn("text-sm font-medium", t.text.primary)}>
            🔧 Debug Tools
          </h3>
          <button
            onClick={async () => {
              try {
                const response = await fetch('/api/insurance-card/status');
                const data = await response.json();
                setDebugData({
                  azureStatus: data,
                  timestamp: new Date().toISOString()
                });
                setShowDebug(true);
              } catch (error) {
                setDebugData({
                  azureStatus: { error: error.message },
                  timestamp: new Date().toISOString()
                });
                setShowDebug(true);
              }
            }}
            className={cn(
              "px-3 py-1 rounded text-xs font-medium",
              t.button.secondary
            )}
          >
            Check Azure Status
          </button>
        </div>
        
        {debugData && (
          <div>
            <div 
              className="flex items-center justify-between cursor-pointer"
              onClick={() => setShowDebug(!showDebug)}
            >
              <h4 className={cn("text-md font-medium", t.text.primary)}>
                Debug Information
              </h4>
              {showDebug ? <FiChevronDown /> : <FiChevronRight />}
            </div>
          
          {showDebug && (
            <div className="mt-4 space-y-4">
              {/* Azure Configuration Status */}
              {debugData.azureStatus && (
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                    Azure Document Intelligence Configuration
                  </h4>
                  <div className={cn("p-3 rounded-md", 
                    theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                  )}>
                    <div className="space-y-2">
                      <div className="flex items-center gap-2">
                        <span className={cn(
                          "w-3 h-3 rounded-full",
                          debugData.azureStatus.configured ? "bg-green-500" : "bg-red-500"
                        )} />
                        <span className={cn("text-sm", t.text.primary)}>
                          Status: {debugData.azureStatus.configured ? 'Configured' : 'Not Configured'}
                        </span>
                      </div>
                      <p className={cn("text-sm", t.text.secondary)}>
                        {debugData.azureStatus.message}
                      </p>
                      {!debugData.azureStatus.configured && (
                        <div className={cn("mt-2 p-2 rounded", "bg-yellow-500/10")}>
                          <p className={cn("text-xs", t.text.secondary)}>
                            Add these to your .env file:
                          </p>
                          <pre className={cn("text-xs mt-1", t.text.primary)}>
                            AZURE_DI_ENDPOINT=https://your-resource.cognitiveservices.azure.com/
                            AZURE_DI_KEY=your-api-key
                          </pre>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              )}

              {/* API Response Status */}
              <div>
                <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                  API Response Status
                </h4>
                <div className={cn("p-3 rounded-md", 
                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                )}>
                  <p className={cn("text-sm font-mono", t.text.primary)}>
                    Status: {debugData.status || 'N/A'}
                  </p>
                  <p className={cn("text-sm font-mono", t.text.secondary)}>
                    Timestamp: {debugData.timestamp}
                  </p>
                </div>
              </div>

              {/* Extracted Fields */}
              {debugData.extractedFields && (
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                    Fields Extracted ({debugData.extractedFields.length})
                  </h4>
                  <div className={cn("p-3 rounded-md", 
                    theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                  )}>
                    {debugData.extractedFields.length > 0 ? (
                      <ul className="list-disc list-inside">
                        {debugData.extractedFields.map((field: string, idx: number) => (
                          <li key={idx} className={cn("text-sm", t.text.primary)}>
                            {field}
                            {field === 'Member ID' && debugData.updates?.patient_member_id && (
                              <span className={cn("ml-2 text-xs", t.text.secondary)}>
                                ({debugData.updates.patient_member_id})
                              </span>
                            )}
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <p className={cn("text-sm", t.text.secondary)}>No fields extracted</p>
                    )}
                  </div>
                </div>
              )}
              
              {/* Raw Extracted Data */}
              {debugData.rawExtractedData && (
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                    Raw Extracted Data from Azure
                  </h4>
                  <div className={cn("p-3 rounded-md overflow-x-auto", 
                    theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                  )}>
                    <pre className={cn("text-xs font-mono", t.text.primary)}>
                      {JSON.stringify({
                        member: debugData.rawExtractedData?.member,
                        insurer: debugData.rawExtractedData?.insurer,
                        payer_id: debugData.rawExtractedData?.payer_id,
                        plan: debugData.rawExtractedData?.plan,
                        group: debugData.rawExtractedData?.group,
                        copays: debugData.rawExtractedData?.copays
                      }, null, 2)}
                    </pre>
                  </div>
                </div>
              )}
              
              {/* Raw Form Data from Azure */}
              {debugData.rawData && (
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                    Mapped Form Data from Azure
                  </h4>
                  <div className={cn("p-3 rounded-md overflow-x-auto", 
                    theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                  )}>
                    <pre className={cn("text-xs font-mono", t.text.primary)}>
                      {JSON.stringify(debugData.rawData, null, 2)}
                    </pre>
                  </div>
                </div>
              )}

              {/* Applied Updates */}
              {debugData.updates && (
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                    Form Updates Applied
                  </h4>
                  <div className={cn("p-3 rounded-md overflow-x-auto", 
                    theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                  )}>
                    <pre className={cn("text-xs font-mono", t.text.primary)}>
                      {JSON.stringify(debugData.updates, null, 2)}
                    </pre>
                  </div>
                </div>
              )}

              {/* Full API Response */}
              <div>
                <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                  Full API Response
                </h4>
                <div className={cn("p-3 rounded-md overflow-x-auto", 
                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                )}>
                  <pre className={cn("text-xs font-mono", t.text.primary)}>
                    {JSON.stringify(debugData.response || debugData.error || debugData, null, 2)}
                  </pre>
                </div>
              </div>

              {/* Current Form Data */}
              <div>
                <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                  Current Form Data (Patient & Insurance Fields)
                </h4>
                <div className={cn("p-3 rounded-md overflow-x-auto", 
                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                )}>
                  <pre className={cn("text-xs font-mono", t.text.primary)}>
                    {JSON.stringify({
                      patient_first_name: formData.patient_first_name,
                      patient_last_name: formData.patient_last_name,
                      patient_dob: formData.patient_dob,
                      patient_member_id: formData.patient_member_id,
                      payer_name: formData.payer_name,
                      payer_id: formData.payer_id,
                      insurance_type: formData.insurance_type,
                      insurance_card_auto_filled: formData.insurance_card_auto_filled
                    }, null, 2)}
                  </pre>
                </div>
              </div>
            </div>
          )}
          </div>
        )}
      </div>
    </div>
  );
}