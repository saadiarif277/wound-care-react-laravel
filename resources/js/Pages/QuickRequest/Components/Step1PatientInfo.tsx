import React, { useState, useRef } from 'react';
import { FiCamera, FiRefreshCw, FiAlertCircle, FiFile } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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

export default function Step1PatientInfo({
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
  const [shippingError, setShippingError] = useState<string | null>(null);

  const fileInputFrontRef = useRef<HTMLInputElement>(null);
  const fileInputBackRef = useRef<HTMLInputElement>(null);

  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
    // Create preview based on file type
    if (file.type.startsWith('image/')) {
      // For images, create a data URL preview
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
      // For PDFs, just store a placeholder
      if (side === 'front') {
        setCardFrontPreview('pdf');
      } else {
        setCardBackPreview('pdf');
      }
    }

    // Store file in form data
    updateFormData({ [`insurance_card_${side}`]: file });

    // If both cards are uploaded, attempt to auto-fill
    const frontCard = side === 'front' ? file : formData.insurance_card_front;
    const backCard = side === 'back' ? file : formData.insurance_card_back;

    if (frontCard && backCard) {
      setIsProcessingCard(true);
      try {
        // Create FormData for API call
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        apiFormData.append('insurance_card_back', backCard);

        // Call Azure Document Intelligence API
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
            // Update form with extracted data
            const extractedData = result.data;

            // Update patient information
            if (extractedData.patient_first_name) {
              updateFormData({ patient_first_name: extractedData.patient_first_name });
            }
            if (extractedData.patient_last_name) {
              updateFormData({ patient_last_name: extractedData.patient_last_name });
            }
            if (extractedData.patient_dob) {
              updateFormData({ patient_dob: extractedData.patient_dob });
            }
            if (extractedData.patient_member_id) {
              updateFormData({ patient_member_id: extractedData.patient_member_id });
            }

            // Update insurance information
            if (extractedData.payer_name) {
              updateFormData({ payer_name: extractedData.payer_name });
            }
            if (extractedData.payer_id) {
              updateFormData({ payer_id: extractedData.payer_id });
            }
            if (extractedData.insurance_type) {
              updateFormData({ insurance_type: extractedData.insurance_type });
            }

            // Set flag that auto-fill was successful
            updateFormData({
              insurance_card_auto_filled: true,
              insurance_extracted_data: result.extracted_data
            });
          }
        } else {
          console.error('Failed to analyze insurance card');
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
      } finally {
        setIsProcessingCard(false);
      }
    } else if (frontCard && !backCard) {
      // Process just the front card if only front is uploaded
      setIsProcessingCard(true);
      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);

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
            const extractedData = result.data;

            // Update form with extracted data (same as above)
            if (extractedData.patient_first_name) {
              updateFormData({ patient_first_name: extractedData.patient_first_name });
            }
            if (extractedData.patient_last_name) {
              updateFormData({ patient_last_name: extractedData.patient_last_name });
            }
            if (extractedData.patient_dob) {
              updateFormData({ patient_dob: extractedData.patient_dob });
            }
            if (extractedData.patient_member_id) {
              updateFormData({ patient_member_id: extractedData.patient_member_id });
            }
            if (extractedData.payer_name) {
              updateFormData({ payer_name: extractedData.payer_name });
            }
            if (extractedData.payer_id) {
              updateFormData({ payer_id: extractedData.payer_id });
            }
            if (extractedData.insurance_type) {
              updateFormData({ insurance_type: extractedData.insurance_type });
            }

            updateFormData({
              insurance_card_auto_filled: true,
              insurance_extracted_data: result.extracted_data
            });
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
      setShippingError('Expected delivery is next day');
    }
  };

  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM)' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day (during office hours)' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Step 1: Patient Information
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Upload insurance cards and enter patient details
        </p>
      </div>

      {/* Insurance Card Capture */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-1", t.text.primary)}>
          📸 Insurance Card Capture
        </h3>
        <p className={cn("text-sm mb-4", t.text.secondary)}>
          Please upload or photograph:
        </p>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {/* Front of Card */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
              ☐ Front of Insurance Card
            </label>
            <div
              className={cn(
                "border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                cardFrontPreview ? 'border-green-500' : false
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
                      <FiFile className="h-16 w-16 text-indigo-500 mb-2" />
                      <p className={cn("text-sm font-medium", t.text.primary)}>PDF Uploaded</p>
                    </div>
                  ) : (
                    <img
                      src={cardFrontPreview}
                      alt="Insurance card front"
                      className="mx-auto h-32 object-contain mb-2"
                    />
                  )}
                  <p className={cn("text-sm", t.text.secondary)}>Click to replace</p>
                </div>
              ) : (
                <div>
                  <FiCamera className="mx-auto h-12 w-12 text-gray-400 mb-2" />
                  <p className={cn("text-sm", t.text.secondary)}>
                    Click to upload image or PDF
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Back of Card */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
              ☐ Back of Insurance Card
            </label>
            <div
              className={cn(
                "border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                cardBackPreview ? 'border-green-500' : false
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
                      <FiFile className="h-16 w-16 text-indigo-500 mb-2" />
                      <p className={cn("text-sm font-medium", t.text.primary)}>PDF Uploaded</p>
                    </div>
                  ) : (
                    <img
                      src={cardBackPreview}
                      alt="Insurance card back"
                      className="mx-auto h-32 object-contain mb-2"
                    />
                  )}
                  <p className={cn("text-sm", t.text.secondary)}>Click to replace</p>
                </div>
              ) : (
                <div>
                  <FiCamera className="mx-auto h-12 w-12 text-gray-400 mb-2" />
                  <p className={cn("text-sm", t.text.secondary)}>
                    Click to upload image or PDF
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>

        {isProcessingCard && (
          <div className={cn("mt-4 p-3 rounded-md flex items-center",
            theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
          )}>
            <FiRefreshCw className="animate-spin h-4 w-4 mr-2 text-blue-600" />
            <p className={cn("text-sm", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')}>
              Processing insurance cards to auto-fill information...
            </p>
          </div>
        )}

        <div className={cn("mt-4 p-3 rounded-md",
          theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
        )}>
          <p className={cn("text-sm flex items-center",
            theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
          )}>
            💡 Tip: We can auto-fill patient and insurance information from the card
          </p>
        </div>
      </div>

      {/* Patient Demographics */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Patient Demographics
        </h3>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
              value={formData.patient_gender || 'unknown'}
              onChange={(e) => updateFormData({ patient_gender: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
              <option value="unknown">Unknown</option>
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
      </div>

      {/* Patient Address */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Patient Address
        </h3>

        <div className="space-y-4">
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
      </div>

      {/* Service & Payer Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Service & Payer Information
        </h3>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Payer Name *
            </label>
            <input
              type="text"
              value={formData.payer_name || ''}
              onChange={(e) => updateFormData({ payer_name: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus,
                errors.payer_name && "border-red-500"
              )}
              placeholder="Medicare, Aetna, etc."
            />
            {errors.payer_name && (
              <p className="mt-1 text-sm text-red-500">{errors.payer_name}</p>
            )}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Payer ID
            </label>
            <input
              type="text"
              value={formData.payer_id || ''}
              onChange={(e) => updateFormData({ payer_id: e.target.value })}
              className={cn("w-full", t.input.base, t.input.focus)}
              placeholder="Payer-specific ID"
            />
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
              <option value="11">(11) Office</option>
              <option value="12">(12) Home</option>
              <option value="32">(32) Nursing Home</option>
              <option value="31">(31) Skilled Nursing</option>
            </select>
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
              <option value="medicare">Medicare</option>
              <option value="medicaid">Medicaid</option>
              <option value="medicare_advantage">Medicare Advantage</option>
              <option value="commercial">Commercial</option>
              <option value="other">Other</option>
            </select>
          </div>

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
        </div>
      </div>

      {/* Facility & Shipping */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Facility & Shipping Information
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
            <label className={cn("block text-sm font-medium mb-3", t.text.secondary)}>
              Shipping Speed
            </label>
            <div className="space-y-3">
              {shippingOptions.map((option) => (
                <div key={option.value} className="flex items-center">
                  <input
                    type="radio"
                    id={option.value}
                    name="shipping_speed"
                    value={option.value}
                    checked={formData.shipping_speed === option.value}
                    onChange={(e) => handleShippingSpeedChange(e.target.value)}
                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                  />
                  <label
                    htmlFor={option.value}
                    className={cn("ml-3 block text-sm font-medium", t.text.secondary)}
                  >
                    {option.label}
                  </label>
                </div>
              ))}

              {shippingError && (
                <div className={cn(
                  "mt-3 p-3 rounded-md",
                  shippingError.includes('cannot be fulfilled')
                    ? 'bg-red-50 border border-red-200'
                    : 'bg-blue-50 border border-blue-200'
                )}>
                  <p className={cn(
                    "text-sm",
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
      </div>
    </div>
  );
}