import { useState } from 'react';
import { FiAlertCircle } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import DiagnosisCodeSelector from '@/Components/DiagnosisCode/DiagnosisCodeSelector';
import Select from '@/Components/ui/Select';
import DocumentUploadCard from '@/Components/DocumentUploadCard';
import api from '@/lib/api';

interface FormData {
  // Clinical Information
  wound_type?: string; // Changed from array to single string
  wound_other_specify?: string;
  wound_location?: string;
  wound_location_details?: string;
  primary_diagnosis_code?: string; // For dual-coding wounds
  secondary_diagnosis_code?: string; // For dual-coding wounds
  diagnosis_code?: string; // For single-code wounds
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  // Duration fields - NEW
  wound_duration_days?: string;
  wound_duration_weeks?: string;
  wound_duration_months?: string;
  wound_duration_years?: string;
  previous_treatments?: string;

  // Procedure Information
  application_cpt_codes?: string[];
  prior_applications?: string;
  prior_application_product?: string; // NEW - Product used if > 1
  prior_application_within_12_months?: boolean; // NEW - Within 12 months checkbox
  anticipated_applications?: string;

  // Facility Information (renamed from Billing Status)
  place_of_service?: string;
  medicare_part_b_authorized?: boolean;
  snf_days?: string;
  hospice_status?: boolean;
  hospice_family_consent?: boolean; // NEW field
  hospice_clinically_necessary?: boolean; // NEW field
  part_a_status?: boolean;
  global_period_status?: boolean;
  global_period_cpt?: string;
  global_period_surgery_date?: string;

  [key: string]: any;
}

interface Step4Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  diagnosisCodes?: {
    yellow: Array<{ code: string; description: string }>;
    orange: Array<{ code: string; description: string }>;
  };
  woundArea: string;
  errors: Record<string, string>;
}

export default function Step4ClinicalBilling({
  formData,
  updateFormData,
  diagnosisCodes,
  woundArea,
  errors
}: Step4Props) {
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

  // State for document upload functionality
  const [autoFillSuccess, setAutoFillSuccess] = useState(false);

  // Insurance card upload handler
  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
    // Store file in form data
    updateFormData({ [`insurance_card_${side}`]: file });

    // Try to process with Azure Document Intelligence
    const frontCard = side === 'front' ? file : formData.insurance_card_front;
    const backCard = side === 'back' ? file : formData.insurance_card_back;

    if (frontCard) {
      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        if (backCard) {
          apiFormData.append('insurance_card_back', backCard);
        }

        // Use the enhanced fetch function with automatic CSRF token handling
        const response = await api.post('/api/insurance-card/analyze', apiFormData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        const data = response.data;
        
        if (data.success && data.data) {
          const updates: any = {};

          // Patient information
          if (data.data.patient_first_name) updates.patient_first_name = data.data.patient_first_name;
          if (data.data.patient_last_name) updates.patient_last_name = data.data.patient_last_name;
          if (data.data.patient_dob) updates.patient_dob = data.data.patient_dob;
          if (data.data.patient_member_id) updates.patient_member_id = data.data.patient_member_id;

          // Insurance information
          if (data.data.payer_name) updates.primary_insurance_name = data.data.payer_name;
          if (data.data.payer_id) updates.primary_member_id = data.data.payer_id;
          if (data.data.insurance_type) updates.primary_plan_type = data.data.insurance_type;

          updates.insurance_card_auto_filled = true;
          updateFormData(updates);
          setAutoFillSuccess(true);

          setTimeout(() => {
            setAutoFillSuccess(false);
          }, 5000);
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
      }
    }
  };

  // Wound location options
  const woundLocations = [
    { value: 'trunk_arms_legs_small', label: 'Legs/Arms/Trunk ≤ 100 sq cm' },
    { value: 'trunk_arms_legs_large', label: 'Legs/Arms/Trunk > 100 sq cm' },
    { value: 'hands_feet_head_small', label: 'Feet/Hands/Head ≤ 100 sq cm' },
    { value: 'hands_feet_head_large', label: 'Feet/Hands/Head > 100 sq cm' },
  ];

  // Place of service options
  const placeOfServiceOptions = [
    { value: '11', label: '11 - Office' },
    { value: '12', label: '12 - Home' },
    { value: '22', label: '22 - Hospital Outpatient' },
    { value: '31', label: '31 - Skilled Nursing Facility (SNF)' },
    { value: '32', label: '32 - Nursing Facility' },
    { value: '34', label: '34 - Hospice' },
  ];

  // Get suggested CPT codes based on wound location and area
  const getSuggestedCPTCodes = () => {
    const area = parseFloat(woundArea) || 0;
    const isExtremity = formData.wound_location?.includes('hands_feet_head');
    let suggestedCodes: string[] = [];

    if (area > 0) {
      if (isExtremity) {
        if (area <= 25) suggestedCodes = ['15275'];
        else if (area <= 100) suggestedCodes = ['15275', '15276'];
        else suggestedCodes = ['15277', '15278'];
      } else {
        if (area <= 25) suggestedCodes = ['15271'];
        else if (area <= 100) suggestedCodes = ['15271', '15272'];
        else suggestedCodes = ['15273', '15274'];
      }
    }

    return suggestedCodes;
  };

  const cptOptions = [
    { code: '15271', label: '15271' },
    { code: '15272', label: '15272' },
    { code: '15273', label: '15273' },
    { code: '15274', label: '15274' },
    { code: '15275', label: '15275' },
    { code: '15276', label: '15276' },
    { code: '15277', label: '15277' },
    { code: '15278', label: '15278' },
  ];



  const handleDiagnosisChange = (selection: {
    wound_type?: string;
    primary_diagnosis_code?: string;
    secondary_diagnosis_code?: string;
    diagnosis_code?: string;
  }) => {
    updateFormData(selection);
  };

  // Validate at least one duration field is filled
  const isDurationValid = () => {
    return !!(
      formData.wound_duration_days ||
      formData.wound_duration_weeks ||
      formData.wound_duration_months ||
      formData.wound_duration_years
    );
  };

  return (
    <div className="space-y-6">
      {/* Wound Information - Full Width */}
      <div className={cn("p-4 rounded-lg w-full", theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Wound Information
        </h3>

        <div className="space-y-4">
          {/* Diagnosis Code Selector - Includes wound type selection */}
          <DiagnosisCodeSelector
            value={{
              wound_type: formData.wound_type,
              primary_diagnosis_code: formData.primary_diagnosis_code,
              secondary_diagnosis_code: formData.secondary_diagnosis_code,
              diagnosis_code: formData.diagnosis_code
            }}
            onChange={handleDiagnosisChange}
            errors={{
              wound_type: errors.wound_type,
              diagnosis: errors.diagnosis_code || errors.primary_diagnosis_code || errors.secondary_diagnosis_code
            }}
            diagnosisCodes={diagnosisCodes}
          />

          {/* Wound Location Dropdown */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Wound Location <span className="text-red-500">*</span>
            </label>
            <Select
              options={woundLocations}
              value={formData.wound_location || ''}
              onChange={(e: React.ChangeEvent<HTMLSelectElement>) => updateFormData({ wound_location: e.target.value })}
              placeholder="Please Select Wound Location"
              error={errors.wound_location}
              required
            />
            {errors.wound_location && (
              <p className="mt-1 text-sm text-red-500">{errors.wound_location}</p>
            )}
          </div>

          {/* Other wound type specification */}
          {formData.wound_type === 'other' && (
            <div className="mt-3">
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Specify Other Wound Type <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark'
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.wound_other_specify && 'border-red-500'
                )}
                value={formData.wound_other_specify || ''}
                onChange={(e) => updateFormData({ wound_other_specify: e.target.value })}
                placeholder="Please specify..."
              />
              {errors.wound_other_specify && (
                <p className="mt-1 text-sm text-red-500">{errors.wound_other_specify}</p>
              )}
            </div>
          )}

          {/* Wound Measurements */}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Length (cm) <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                step="0.1"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark'
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.wound_size && 'border-red-500'
                )}
                value={formData.wound_size_length || ''}
                onChange={(e) => updateFormData({ wound_size_length: e.target.value })}
              />
            </div>
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Width (cm) <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                step="0.1"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark'
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.wound_size && 'border-red-500'
                )}
                value={formData.wound_size_width || ''}
                onChange={(e) => updateFormData({ wound_size_width: e.target.value })}
              />
            </div>
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Depth (cm)
              </label>
              <input
                type="number"
                step="0.1"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark'
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.wound_size_depth || ''}
                onChange={(e) => updateFormData({ wound_size_depth: e.target.value })}
              />
            </div>
          </div>
          {errors.wound_size && (
            <p className="text-sm text-red-500">{errors.wound_size}</p>
          )}

          {/* Total Wound Area */}
          <div className={cn(
            "p-3 rounded",
            theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
          )}>
            <p className={cn("text-sm font-medium", t.text.primary)}>
              Total Wound Area: <span className="text-lg font-bold text-blue-600">{woundArea} sq cm</span>
            </p>
          </div>

          {/* CPT Codes Selection - Redesigned as checkboxes */}
          <div>
            <label className={cn("block text-sm font-medium mb-3", t.text.primary)}>
              Application CPT(s) <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-4 gap-3 mb-3">
              {cptOptions.map(option => {
                const isSelected = (formData.application_cpt_codes || []).includes(option.code);
                const suggestedCodes = getSuggestedCPTCodes();
                const isSuggested = suggestedCodes.includes(option.code);
                
                return (
                  <label key={option.code} className="flex items-center space-x-2 cursor-pointer">
                    <input
                      type="checkbox"
                      className="form-checkbox h-4 w-4 text-blue-600 rounded"
                      checked={isSelected}
                      onChange={(e) => {
                        const current = formData.application_cpt_codes || [];
                        if (e.target.checked) {
                          updateFormData({ application_cpt_codes: [...current, option.code] });
                        } else {
                          updateFormData({ application_cpt_codes: current.filter((c: string) => c !== option.code) });
                        }
                      }}
                    />
                    <span className={cn("text-sm flex items-center gap-2", t.text.primary)}>
                      {option.label}
                      {isSuggested && (
                        <span className={cn(
                          "text-xs px-2 py-0.5 rounded-full font-medium",
                          theme === 'dark' 
                            ? 'bg-blue-900/50 text-blue-300' 
                            : 'bg-blue-100 text-blue-700'
                        )}>
                          suggested
                        </span>
                      )}
                    </span>
                  </label>
                );
              })}
            </div>
            
            {/* Other CPT codes text input */}
            <div className="mt-3">
              <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                Additional CPT Codes
              </label>
              <input
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark'
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                )}
                value={formData.application_cpt_codes_other || ''}
                onChange={(e) => updateFormData({ application_cpt_codes_other: e.target.value })}
                placeholder="Enter additional CPT codes..."
              />
            </div>
            
            {errors.cpt_codes && (
              <p className="mt-1 text-sm text-red-500">{errors.cpt_codes}</p>
            )}
          </div>

          {/* Wound Duration - NEW FIELDS */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              Wound Duration <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-4 gap-3">
              <div>
                <label className={cn("block text-xs mb-1", t.text.secondary)}>Years</label>
                <input
                  type="number"
                  min="0"
                  max="10"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    !isDurationValid() && errors.wound_duration && 'border-red-500'
                  )}
                  value={formData.wound_duration_years || ''}
                  onChange={(e) => updateFormData({ wound_duration_years: e.target.value })}
                  placeholder="0"
                />
              </div>
              <div>
                <label className={cn("block text-xs mb-1", t.text.secondary)}>Months</label>
                <input
                  type="number"
                  min="0"
                  max="12"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    !isDurationValid() && errors.wound_duration && 'border-red-500'
                  )}
                  value={formData.wound_duration_months || ''}
                  onChange={(e) => updateFormData({ wound_duration_months: e.target.value })}
                  placeholder="0"
                />
              </div>
              <div>
                <label className={cn("block text-xs mb-1", t.text.secondary)}>Weeks</label>
                <input
                  type="number"
                  min="0"
                  max="52"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    !isDurationValid() && errors.wound_duration && 'border-red-500'
                  )}
                  value={formData.wound_duration_weeks || ''}
                  onChange={(e) => updateFormData({ wound_duration_weeks: e.target.value })}
                  placeholder="0"
                />
              </div>
              <div>
                <label className={cn("block text-xs mb-1", t.text.secondary)}>Days</label>
                <input
                  type="number"
                  min="0"
                  max="30"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    !isDurationValid() && errors.wound_duration && 'border-red-500'
                  )}
                  value={formData.wound_duration_days || ''}
                  onChange={(e) => updateFormData({ wound_duration_days: e.target.value })}
                  placeholder="0"
                />
              </div>
            </div>
            {!isDurationValid() && errors.wound_duration && (
              <p className="mt-1 text-sm text-red-500">{errors.wound_duration}</p>
            )}
            <p className={cn("text-xs mt-1", t.text.secondary)}>
              Enter at least one duration value
            </p>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-3", t.text.primary)}>
              Previously Used Therapies
            </label>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              {[
                'Wound Bed Preparation (WBP)',
                'Infection Control',
                'Moisture Balance (Dressings)',
                'Offloading / Pressure Redistribution',
                'Edema and Circulation Management',
                'Nutritional Optimization',
                'Blood Glucose & Comorbidity Control',
                'Negative Pressure Wound Therapy (NPWT)',
                'Adjunctive Modalities'
              ].map(treatment => {
                const treatmentKey = treatment.toLowerCase().replace(/[^a-z0-9]/g, '_');
                const isSelected = formData.previous_treatments_selected?.[treatmentKey] || false;
                return (
                  <label key={treatmentKey} className="flex items-start space-x-2 cursor-pointer">
                    <input
                      type="checkbox"
                      className="form-checkbox h-4 w-4 text-blue-600 rounded mt-0.5 flex-shrink-0"
                      checked={isSelected}
                      onChange={(e) => {
                        const currentSelections = formData.previous_treatments_selected || {};
                        updateFormData({
                          previous_treatments_selected: {
                            ...currentSelections,
                            [treatmentKey]: e.target.checked
                          }
                        });
                      }}
                    />
                    <span className={cn("text-sm leading-tight", t.text.primary)}>{treatment}</span>
                  </label>
                );
              })}
            </div>
          </div>
        </div>
      </div>

      {/* Application History - Full Width */}
      <div className={cn("p-4 rounded-lg w-full", theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Application History
        </h3>

        <div className="space-y-4">{/* CPT Codes moved to Wound Information section */}

          {/* Prior Applications */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Number of Prior Applications
            </label>
            <input
              type="number"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.prior_applications || ''}
              onChange={(e) => updateFormData({ prior_applications: e.target.value })}
              min="0"
              max="20"
              placeholder="0"
            />
            <p className={cn("text-xs mt-1", t.text.secondary)}>
              Number of times this product has been previously applied
            </p>
          </div>

          {/* NEW: If prior applications > 1, show additional fields */}
          {parseInt(formData.prior_applications || '0') > 0 && (
            <div className={cn(
              "ml-4 p-3 rounded-lg border-l-4 space-y-3",
              theme === 'dark'
                ? 'bg-gray-800 border-blue-500'
                : 'bg-gray-50 border-blue-500'
            )}>
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Which product was previously used?
                </label>
                <input
                  type="text"
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                  )}
                  value={formData.prior_application_product || ''}
                  onChange={(e) => updateFormData({ prior_application_product: e.target.value })}
                  placeholder="Product name..."
                />
              </div>
              <label className="flex items-center">
                <input
                  type="checkbox"
                  className="form-checkbox h-4 w-4 text-blue-600 rounded"
                  checked={formData.prior_application_within_12_months || false}
                  onChange={(e) => updateFormData({ prior_application_within_12_months: e.target.checked })}
                />
                <span className={cn("ml-2 text-sm", t.text.primary)}>
                  Applied within the last 12 months
                </span>
              </label>
            </div>
          )}

          {/* Anticipated Applications */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Number of Anticipated Applications
            </label>
            <input
              type="number"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.anticipated_applications || ''}
              onChange={(e) => updateFormData({ anticipated_applications: e.target.value })}
              min="1"
              max="10"
              placeholder="1"
            />
            <p className={cn("text-xs mt-1", t.text.secondary)}>
              Expected number of future applications needed
            </p>
          </div>
        </div>
      </div>

      {/* Facility Information - Full Width */}
      <div className={cn("p-4 rounded-lg w-full", theme === 'dark' ? 'bg-yellow-900/20' : 'bg-yellow-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Facility Information
        </h3>

        <div className="space-y-4">
          {/* Place of Service */}
          <div>
            <Select
              label="Place of Service"
              value={formData.place_of_service || '11'}
              onChange={(e) => updateFormData({ place_of_service: e.target.value })}
              options={placeOfServiceOptions}
              placeholder="Please Select Place of Service"
              error={errors.place_of_service}
              required
            />
            {errors.place_of_service && (
              <p className="mt-1 text-sm text-red-500">{errors.place_of_service}</p>
            )}
          </div>

          {/* SNF/Nursing Home Medicare Authorization */}
          {(formData.place_of_service === '31' || formData.place_of_service === '32') && (
            <div className={cn(
              "p-4 rounded-lg border",
              theme === 'dark'
                ? 'bg-red-900/20 border-red-800'
                : 'bg-red-50 border-red-200'
            )}>
              <div className="flex items-start">
                <FiAlertCircle className={cn(
                  "h-5 w-5 mt-0.5 flex-shrink-0",
                  theme === 'dark' ? 'text-red-400' : 'text-red-600'
                )} />
                <div className="ml-3">
                  <h4 className={cn(
                    "text-sm font-medium",
                    theme === 'dark' ? 'text-red-300' : 'text-red-900'
                  )}>
                    Medicare Part B Authorization Required
                  </h4>
                  <p className={cn(
                    "mt-1 text-sm",
                    theme === 'dark' ? 'text-red-400' : 'text-red-700'
                  )}>
                    {formData.place_of_service === '31' ? 'Skilled Nursing Facility' : 'Nursing Home'} requires special Medicare authorization
                  </p>

                  <div className="mt-3">
                    <label className="flex items-center">
                      <input
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-red-600 rounded"
                        checked={formData.medicare_part_b_authorized || false}
                        onChange={(e) => updateFormData({ medicare_part_b_authorized: e.target.checked })}
                      />
                      <span className={cn("ml-2 text-sm font-medium", t.text.primary)}>
                        Medicare Part B is authorized for this {formData.place_of_service === '31' ? 'SNF' : 'Nursing Home'} stay
                      </span>
                    </label>
                  </div>

                  {formData.medicare_part_b_authorized && (
                    <div className="mt-3 space-y-3">
                      <div>
                        <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                          Days in Facility
                        </label>
                        <input
                          type="number"
                          className={cn(
                            "w-full p-2 rounded border transition-all",
                            theme === 'dark'
                              ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                              : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                          )}
                          value={formData.snf_days || ''}
                          onChange={(e) => updateFormData({ snf_days: e.target.value })}
                          placeholder="Number of days"
                          min="0"
                          max="999"
                        />
                      </div>

                      {parseInt(formData.snf_days || '0') > 100 && (
                        <div className={cn(
                          "p-3 rounded-lg border-l-4",
                          theme === 'dark'
                            ? 'bg-yellow-900/20 border-yellow-500'
                            : 'bg-yellow-100 border-yellow-500'
                        )}>
                          <p className={cn(
                            "text-sm",
                            theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'
                          )}>
                            <strong>Warning:</strong> Medicare coverage may be affected after 100 days in facility
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Additional Billing Status */}
          <div className="space-y-2">
            <label className="flex items-center">
              <input
                type="checkbox"
                className="form-checkbox h-4 w-4 text-blue-600 rounded"
                checked={formData.hospice_status || false}
                onChange={(e) => updateFormData({ hospice_status: e.target.checked })}
              />
              <span className={cn("ml-2 text-sm", t.text.primary)}>Patient is in Hospice</span>
            </label>

            {/* NEW: Hospice consent fields */}
            {formData.hospice_status && (
              <div className={cn(
                "ml-6 p-3 rounded-lg space-y-2",
                theme === 'dark' ? 'bg-gray-800' : 'bg-gray-50'
              )}>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-blue-600 rounded"
                    checked={formData.hospice_family_consent || false}
                    onChange={(e) => updateFormData({ hospice_family_consent: e.target.checked })}
                  />
                  <span className={cn("ml-2 text-sm", t.text.primary)}>
                    Family consent obtained
                  </span>
                </label>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-blue-600 rounded"
                    checked={formData.hospice_clinically_necessary || false}
                    onChange={(e) => updateFormData({ hospice_clinically_necessary: e.target.checked })}
                  />
                  <span className={cn("ml-2 text-sm", t.text.primary)}>
                    Clinically necessary per hospice guidelines
                  </span>
                </label>
              </div>
            )}

            <label className="flex items-center">
              <input
                type="checkbox"
                className="form-checkbox h-4 w-4 text-blue-600 rounded"
                checked={formData.part_a_status || false}
                onChange={(e) => updateFormData({ part_a_status: e.target.checked })}
              />
              <span className={cn("ml-2 text-sm", t.text.primary)}>Patient is under Medicare Part A stay</span>
            </label>

            <label className="flex items-center">
              <input
                type="checkbox"
                className="form-checkbox h-4 w-4 text-blue-600 rounded"
                checked={formData.global_period_status || false}
                onChange={(e) => updateFormData({ global_period_status: e.target.checked })}
              />
              <span className={cn("ml-2 text-sm", t.text.primary)}>Patient under post-op global period</span>
            </label>

            {formData.global_period_status && (
              <div className={cn(
                "ml-6 mt-2 p-3 rounded space-y-2",
                theme === 'dark' ? 'bg-gray-800' : 'bg-gray-50'
              )}>
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Previous Surgery CPT
                  </label>
                  <input
                    type="text"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
                    value={formData.global_period_cpt || ''}
                    onChange={(e) => updateFormData({ global_period_cpt: e.target.value })}
                    placeholder="5-digit CPT code"
                    maxLength={5}
                  />
                </div>
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Surgery Date
                  </label>
                  <input
                    type="date"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
                    value={formData.global_period_surgery_date || ''}
                    onChange={(e) => updateFormData({ global_period_surgery_date: e.target.value })}
                  />
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Document Upload Section */}
      <DocumentUploadCard
        title="Document Upload"
        description="Upload insurance cards, clinical notes, demographics, and other supporting documents"
        onDocumentsChange={async (documents) => {
          // Handle document uploads
          const updates: any = {};
          
          for (const doc of documents) {
            if (doc.type === 'insurance_card') {
              // Process insurance cards with OCR
              if (doc.files.primary?.file) {
                await handleInsuranceCardUpload(doc.files.primary.file, 'front');
              }
              if (doc.files.secondary?.file) {
                await handleInsuranceCardUpload(doc.files.secondary.file, 'back');
              }
            } else if (doc.type === 'demographics') {
              updates.face_sheet = doc.files.primary?.file;
            } else if (doc.type === 'clinical_notes') {
              updates.clinical_notes = doc.files.primary?.file;
            } else if (doc.type === 'other') {
              updates.wound_photo = doc.files.primary?.file;
            }
          }
          
          updateFormData(updates);
        }}
        onInsuranceDataExtracted={(data) => {
          // Handle extracted insurance data
          if (data) {
            const updates: any = {};
            
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
        }}
        className="mt-6"
      />
    </div>
  );
}
