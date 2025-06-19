import React, { useState, useRef } from 'react';
import { FiCamera, FiRefreshCw, FiAlertCircle, FiFile, FiCheck, FiChevronDown } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GoogleAddressAutocompleteSimple from '@/Components/GoogleAddressAutocompleteSimple';

interface Step2Props {
  formData: any;
  updateFormData: (data: any) => void;
  errors: Record<string, string>;
}

export default function Step2PatientShipping({ 
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
  const [shippingError, setShippingError] = useState<string | null>(null);
  const [showCaregiver, setShowCaregiver] = useState(!formData.patient_is_subscriber);
  
  const fileInputFrontRef = useRef<HTMLInputElement>(null);
  const fileInputBackRef = useRef<HTMLInputElement>(null);

  // Shipping speed options
  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM) - Next business day' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
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
            
            // Insurance information (will be used in next step)
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
    }
  };

  const handleAddressSelect = (place: google.maps.places.PlaceResult) => {
    const addressComponents = place.address_components || [];
    const updates: any = {};
    
    addressComponents.forEach(component => {
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
  };

  return (
    <div className="space-y-6">
      {/* Insurance Card Upload Section */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Insurance Card Upload (Optional - Auto-fills patient info)
        </h3>
        
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {/* Front of card */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              Front of Insurance Card
            </label>
            <div 
              onClick={() => fileInputFrontRef.current?.click()}
              className={cn(
                "border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-all hover:border-blue-500",
                theme === 'dark' ? 'border-gray-700 hover:bg-gray-800' : 'border-gray-300 hover:bg-gray-50'
              )}
            >
              {cardFrontPreview ? (
                cardFrontPreview === 'pdf' ? (
                  <div className="flex flex-col items-center">
                    <FiFile className="h-12 w-12 text-blue-500 mb-2" />
                    <p className="text-sm">PDF uploaded</p>
                  </div>
                ) : (
                  <img src={cardFrontPreview} alt="Insurance card front" className="mx-auto max-h-32" />
                )
              ) : (
                <>
                  <FiCamera className={cn("mx-auto h-12 w-12 mb-2", t.text.secondary)} />
                  <p className={cn("text-sm", t.text.secondary)}>Click to upload front</p>
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
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              Back of Insurance Card
            </label>
            <div 
              onClick={() => fileInputBackRef.current?.click()}
              className={cn(
                "border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-all hover:border-blue-500",
                theme === 'dark' ? 'border-gray-700 hover:bg-gray-800' : 'border-gray-300 hover:bg-gray-50'
              )}
            >
              {cardBackPreview ? (
                cardBackPreview === 'pdf' ? (
                  <div className="flex flex-col items-center">
                    <FiFile className="h-12 w-12 text-blue-500 mb-2" />
                    <p className="text-sm">PDF uploaded</p>
                  </div>
                ) : (
                  <img src={cardBackPreview} alt="Insurance card back" className="mx-auto max-h-32" />
                )
              ) : (
                <>
                  <FiCamera className={cn("mx-auto h-12 w-12 mb-2", t.text.secondary)} />
                  <p className={cn("text-sm", t.text.secondary)}>Click to upload back</p>
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
            <span className={cn("text-sm", t.text.secondary)}>Processing insurance card...</span>
          </div>
        )}

        {autoFillSuccess && (
          <div className={cn(
            "mt-4 p-3 rounded-lg flex items-center",
            theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
          )}>
            <FiCheck className="h-5 w-5 mr-2 text-green-500" />
            <span className={cn("text-sm", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
              Successfully extracted patient information from insurance card!
            </span>
          </div>
        )}
      </div>

      {/* Patient Information */}
      <div className={cn("p-4 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Patient Information
        </h3>
        
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              First Name <span className="text-red-500">*</span>
            </label>
            <input 
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.patient_first_name && 'border-red-500'
              )}
              value={formData.patient_first_name}
              onChange={(e) => updateFormData({ patient_first_name: e.target.value })}
            />
            {errors.patient_first_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_first_name}</p>
            )}
          </div>
          
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Last Name <span className="text-red-500">*</span>
            </label>
            <input 
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.patient_last_name && 'border-red-500'
              )}
              value={formData.patient_last_name}
              onChange={(e) => updateFormData({ patient_last_name: e.target.value })}
            />
            {errors.patient_last_name && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_last_name}</p>
            )}
          </div>
          
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Date of Birth <span className="text-red-500">*</span>
            </label>
            <input 
              type="date"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.patient_dob && 'border-red-500'
              )}
              value={formData.patient_dob}
              onChange={(e) => updateFormData({ patient_dob: e.target.value })}
            />
            {errors.patient_dob && (
              <p className="mt-1 text-sm text-red-500">{errors.patient_dob}</p>
            )}
          </div>
          
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Gender
            </label>
            <select 
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.patient_gender}
              onChange={(e) => updateFormData({ patient_gender: e.target.value })}
            >
              <option value="unknown">Prefer not to say</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        {/* Address */}
        <div className="mt-4 space-y-4">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Address (Start typing to search)
            </label>
            <GoogleAddressAutocompleteSimple
              onPlaceSelect={handleAddressSelect}
              defaultValue={formData.patient_address_line1}
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              placeholder="Start typing address..."
            />
          </div>
          
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Address Line 2
            </label>
            <input 
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.patient_address_line2 || ''}
              onChange={(e) => updateFormData({ patient_address_line2: e.target.value })}
              placeholder="Apartment, suite, etc. (optional)"
            />
          </div>
          
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="md:col-span-2">
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                City
              </label>
              <input 
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.patient_city || ''}
                onChange={(e) => updateFormData({ patient_city: e.target.value })}
              />
            </div>
            
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                State
              </label>
              <select 
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
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
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                ZIP Code
              </label>
              <input 
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.patient_zip || ''}
                onChange={(e) => updateFormData({ patient_zip: e.target.value })}
                placeholder="12345"
                maxLength={10}
              />
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Phone Number
              </label>
              <input 
                type="tel"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.patient_phone || ''}
                onChange={(e) => updateFormData({ patient_phone: e.target.value })}
                placeholder="(555) 123-4567"
              />
            </div>
            
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Email Address (Optional)
              </label>
              <input 
                type="email"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.patient_email || ''}
                onChange={(e) => updateFormData({ patient_email: e.target.value })}
                placeholder="patient@email.com"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Service Date & Shipping */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Service Date & Shipping
        </h3>
        
        <div className="space-y-4">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Service Date <span className="text-red-500">*</span>
            </label>
            <input 
              type="date"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.expected_service_date && 'border-red-500'
              )}
              value={formData.expected_service_date}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              min={new Date().toISOString().split('T')[0]}
            />
            {errors.expected_service_date && (
              <p className="mt-1 text-sm text-red-500">{errors.expected_service_date}</p>
            )}
          </div>
          
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Shipping Speed <span className="text-red-500">*</span>
            </label>
            <select 
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.shipping_speed && 'border-red-500'
              )}
              value={formData.shipping_speed}
              onChange={(e) => handleShippingSpeedChange(e.target.value)}
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
          
          {formData.delivery_date && (
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Expected Delivery Date (Auto-calculated)
              </label>
              <input 
                type="date"
                className={cn(
                  "w-full p-2 rounded border",
                  theme === 'dark' 
                    ? 'bg-gray-700 border-gray-600 text-gray-300' 
                    : 'bg-gray-100 border-gray-300 text-gray-500',
                  "cursor-not-allowed"
                )}
                value={formData.delivery_date}
                readOnly
              />
            </div>
          )}
          
          {shippingError && (
            <div className={cn(
              "p-3 rounded flex items-start",
              theme === 'dark' ? 'bg-yellow-900/20' : 'bg-yellow-50'
            )}>
              <FiAlertCircle className={cn(
                "h-5 w-5 mr-2 flex-shrink-0 mt-0.5",
                theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
              )} />
              <span className={cn(
                "text-sm",
                theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'
              )}>
                {shippingError}
              </span>
            </div>
          )}
        </div>
      </div>

      {/* Patient Subscriber Question */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
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
              onChange={() => {
                updateFormData({ patient_is_subscriber: true });
                setShowCaregiver(false);
              }}
            />
            <span className={cn("ml-2", t.text.primary)}>Yes</span>
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
            <span className={cn("ml-2", t.text.primary)}>No</span>
          </label>
        </div>
      </div>

      {/* Caregiver Information (if patient is not subscriber) */}
      {showCaregiver && (
        <div className={cn("p-4 rounded-lg", t.glass.panel)}>
          <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
            Primary Caregiver / Subscriber Information
          </h3>
          
          <div className="space-y-4">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Caregiver Name <span className="text-red-500">*</span>
              </label>
              <input 
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.caregiver_name && 'border-red-500'
                )}
                value={formData.caregiver_name || ''}
                onChange={(e) => updateFormData({ caregiver_name: e.target.value })}
                placeholder="Full name"
              />
              {errors.caregiver_name && (
                <p className="mt-1 text-sm text-red-500">{errors.caregiver_name}</p>
              )}
            </div>
            
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Relationship to Patient <span className="text-red-500">*</span>
                </label>
                <select 
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark' 
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    errors.caregiver_relationship && 'border-red-500'
                  )}
                  value={formData.caregiver_relationship || ''}
                  onChange={(e) => updateFormData({ caregiver_relationship: e.target.value })}
                >
                  <option value="">Select relationship...</option>
                  <option value="spouse">Spouse</option>
                  <option value="parent">Parent</option>
                  <option value="child">Child</option>
                  <option value="sibling">Sibling</option>
                  <option value="guardian">Legal Guardian</option>
                  <option value="other">Other</option>
                </select>
                {errors.caregiver_relationship && (
                  <p className="mt-1 text-sm text-red-500">{errors.caregiver_relationship}</p>
                )}
              </div>
              
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Caregiver Phone
                </label>
                <input 
                  type="tel"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark' 
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                  )}
                  value={formData.caregiver_phone || ''}
                  onChange={(e) => updateFormData({ caregiver_phone: e.target.value })}
                  placeholder="(555) 123-4567"
                />
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}