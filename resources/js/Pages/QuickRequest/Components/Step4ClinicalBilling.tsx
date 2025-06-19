import React from 'react';
import { FiAlertCircle, FiInfo } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Step4Props {
  formData: any;
  updateFormData: (data: any) => void;
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

  // Wound type options
  const woundTypes = [
    { value: 'diabetic_foot_ulcer', label: 'Diabetic Foot Ulcer' },
    { value: 'venous_leg_ulcer', label: 'Venous Leg Ulcer' },
    { value: 'pressure_ulcer', label: 'Pressure Ulcer' },
    { value: 'surgical_wound', label: 'Surgical Wound' },
    { value: 'traumatic_wound', label: 'Traumatic Wound' },
    { value: 'arterial_ulcer', label: 'Arterial Ulcer' },
    { value: 'other', label: 'Other' },
  ];

  // Default diagnosis codes if not provided
  const yellowCodes = diagnosisCodes?.yellow || [
    { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer' },
    { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer' },
    { code: 'E10.621', description: 'Type 1 diabetes mellitus with foot ulcer' },
  ];

  const orangeCodes = diagnosisCodes?.orange || [
    { code: 'L97.411', description: 'Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin' },
    { code: 'L97.412', description: 'Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed' },
    { code: 'L97.511', description: 'Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin' },
  ];

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
    { value: '15271', label: '15271 - First 25 sq cm trunk/arms/legs', group: 'trunk' },
    { value: '15272', label: '15272 - Each additional 25 sq cm trunk/arms/legs', group: 'trunk' },
    { value: '15273', label: '15273 - First 100 sq cm trunk/arms/legs', group: 'trunk' },
    { value: '15274', label: '15274 - Each additional 100 sq cm trunk/arms/legs', group: 'trunk' },
    { value: '15275', label: '15275 - First 25 sq cm feet/hands/head', group: 'extremity' },
    { value: '15276', label: '15276 - Each additional 25 sq cm feet/hands/head', group: 'extremity' },
    { value: '15277', label: '15277 - First 100 sq cm feet/hands/head', group: 'extremity' },
    { value: '15278', label: '15278 - Each additional 100 sq cm feet/hands/head', group: 'extremity' },
  ];

  const suggestedCodes = getSuggestedCPTCodes();

  const handleWoundTypeToggle = (type: string) => {
    const currentTypes = formData.wound_types || [];
    if (currentTypes.includes(type)) {
      updateFormData({ wound_types: currentTypes.filter((t: string) => t !== type) });
    } else {
      updateFormData({ wound_types: [...currentTypes, type] });
    }
  };

  const handleCPTCodeToggle = (code: string) => {
    const currentCodes = formData.application_cpt_codes || [];
    if (currentCodes.includes(code)) {
      updateFormData({ application_cpt_codes: currentCodes.filter((c: string) => c !== code) });
    } else {
      updateFormData({ application_cpt_codes: [...currentCodes, code] });
    }
  };

  return (
    <div className="space-y-6">
      {/* Wound Information */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-red-900/20' : 'bg-red-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Wound Information
        </h3>
        
        <div className="space-y-4">
          {/* Wound Type */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              Wound Type (Select all that apply) <span className="text-red-500">*</span>
            </label>
            <div className="space-y-2">
              {woundTypes.map(type => (
                <label key={type.value} className="flex items-center">
                  <input 
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-blue-600 rounded"
                    checked={formData.wound_types?.includes(type.value) || false}
                    onChange={() => handleWoundTypeToggle(type.value)}
                  />
                  <span className={cn("ml-2 text-sm", t.text.primary)}>{type.label}</span>
                </label>
              ))}
            </div>
            {errors.wound_types && (
              <p className="mt-1 text-sm text-red-500">{errors.wound_types}</p>
            )}
            
            {formData.wound_types?.includes('other') && (
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
          </div>

          {/* Diagnosis Code Requirements Alert */}
          {(formData.wound_types?.includes('diabetic_foot_ulcer') || formData.wound_types?.includes('venous_leg_ulcer')) && (
            <div className={cn(
              "p-3 rounded-lg border-l-4",
              theme === 'dark' 
                ? 'bg-yellow-900/20 border-yellow-500' 
                : 'bg-yellow-100 border-yellow-500'
            )}>
              <div className="flex items-start">
                <FiAlertCircle className={cn(
                  "h-5 w-5 mt-0.5 flex-shrink-0",
                  theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
                )} />
                <div className="ml-3">
                  <h4 className={cn(
                    "text-sm font-medium",
                    theme === 'dark' ? 'text-yellow-300' : 'text-yellow-800'
                  )}>
                    Diagnosis Codes Required
                  </h4>
                  <p className={cn(
                    "mt-1 text-sm",
                    theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'
                  )}>
                    {formData.wound_types?.includes('diabetic_foot_ulcer') && 
                      'Diabetic Foot Ulcer requires 1 Yellow (Diabetes) AND 1 Orange (Chronic Ulcer) diagnosis code'}
                    {formData.wound_types?.includes('diabetic_foot_ulcer') && formData.wound_types?.includes('venous_leg_ulcer') && 
                      ' | '}
                    {formData.wound_types?.includes('venous_leg_ulcer') && 
                      'Venous Leg Ulcer requires appropriate venous insufficiency codes'}
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Diagnosis Codes for DFU */}
          {formData.wound_types?.includes('diabetic_foot_ulcer') && (
            <>
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Yellow Diagnosis Code (Diabetes) <span className="text-red-500">*</span>
                </label>
                <select 
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark' 
                      ? 'bg-yellow-900/20 border-yellow-700 text-white focus:border-yellow-500' 
                      : 'bg-yellow-50 border-yellow-400 text-gray-900 focus:border-yellow-500',
                    errors.yellow_diagnosis && 'border-red-500'
                  )}
                  value={formData.yellow_diagnosis_code || ''}
                  onChange={(e) => updateFormData({ yellow_diagnosis_code: e.target.value })}
                >
                  <option value="">Select yellow code...</option>
                  {yellowCodes.map(code => (
                    <option key={code.code} value={code.code}>
                      {code.code} - {code.description}
                    </option>
                  ))}
                </select>
                {errors.yellow_diagnosis && (
                  <p className="mt-1 text-sm text-red-500">{errors.yellow_diagnosis}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                  Orange Diagnosis Code (Chronic Ulcer) <span className="text-red-500">*</span>
                </label>
                <select 
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark' 
                      ? 'bg-orange-900/20 border-orange-700 text-white focus:border-orange-500' 
                      : 'bg-orange-50 border-orange-400 text-gray-900 focus:border-orange-500',
                    errors.orange_diagnosis && 'border-red-500'
                  )}
                  value={formData.orange_diagnosis_code || ''}
                  onChange={(e) => updateFormData({ orange_diagnosis_code: e.target.value })}
                >
                  <option value="">Select orange code...</option>
                  {orangeCodes.map(code => (
                    <option key={code.code} value={code.code}>
                      {code.code} - {code.description}
                    </option>
                  ))}
                </select>
                {errors.orange_diagnosis && (
                  <p className="mt-1 text-sm text-red-500">{errors.orange_diagnosis}</p>
                )}
              </div>
            </>
          )}

          {/* Wound Location */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Wound Location <span className="text-red-500">*</span>
            </label>
            <select 
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.wound_location && 'border-red-500'
              )}
              value={formData.wound_location || ''}
              onChange={(e) => updateFormData({ wound_location: e.target.value })}
            >
              <option value="">Select location...</option>
              {woundLocations.map(loc => (
                <option key={loc.value} value={loc.value}>{loc.label}</option>
              ))}
            </select>
            {errors.wound_location && (
              <p className="mt-1 text-sm text-red-500">{errors.wound_location}</p>
            )}
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Specific Wound Location (Optional)
            </label>
            <input 
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.wound_location_details || ''}
              onChange={(e) => updateFormData({ wound_location_details: e.target.value })}
              placeholder="e.g., Right foot, plantar surface, 1st metatarsal"
            />
          </div>

          {/* Wound Size */}
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

          {/* Additional Wound Info */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Wound Duration
            </label>
            <input 
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              value={formData.wound_duration || ''}
              onChange={(e) => updateFormData({ wound_duration: e.target.value })}
              placeholder="e.g., 6 weeks, 3 months"
            />
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Previously Used Therapies
            </label>
            <textarea 
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
              )}
              rows={3}
              value={formData.previous_treatments || ''}
              onChange={(e) => updateFormData({ previous_treatments: e.target.value })}
              placeholder="List previous treatments attempted..."
            />
          </div>
        </div>
      </div>

      {/* Procedure Information */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Procedure Information
        </h3>
        
        <div className="space-y-4">
          {/* CPT Codes */}
          <div>
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              Application CPT Codes <span className="text-red-500">*</span>
            </label>
            
            {formData.wound_location && woundArea !== '0' && (
              <div className={cn(
                "p-3 rounded mb-3",
                theme === 'dark' ? 'bg-blue-800/30' : 'bg-blue-100'
              )}>
                <p className={cn("text-sm", theme === 'dark' ? 'text-blue-300' : 'text-blue-800')}>
                  <strong>Auto-selected based on:</strong> {formData.wound_location.includes('trunk_arms_legs') ? 'Trunk/Arms/Legs' : 'Feet/Hands/Head'} | {woundArea} sq cm
                </p>
              </div>
            )}
            
            <div className="space-y-2">
              {cptOptions.map(option => (
                <label key={option.value} className="flex items-center">
                  <input 
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-blue-600 rounded"
                    checked={formData.application_cpt_codes?.includes(option.value) || suggestedCodes.includes(option.value)}
                    onChange={() => handleCPTCodeToggle(option.value)}
                  />
                  <span className={cn(
                    "ml-2 text-sm",
                    suggestedCodes.includes(option.value) 
                      ? theme === 'dark' ? 'font-medium text-blue-400' : 'font-medium text-blue-700' 
                      : t.text.primary
                  )}>
                    {option.label}
                    {suggestedCodes.includes(option.value) && (
                      <span className={cn(
                        "ml-2 text-xs px-2 py-1 rounded",
                        theme === 'dark' ? 'bg-blue-800' : 'bg-blue-200'
                      )}>
                        Recommended
                      </span>
                    )}
                  </span>
                </label>
              ))}
            </div>
            {errors.cpt_codes && (
              <p className="mt-1 text-sm text-red-500">{errors.cpt_codes}</p>
            )}
            
            <p className={cn("text-xs mt-3 italic", t.text.secondary)}>
              Note: CPT code recommendations are based on wound size and location. Final billing code selection is the provider's responsibility.
            </p>
          </div>

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

      {/* Facility & Billing Status */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-yellow-900/20' : 'bg-yellow-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Facility & Billing Status
        </h3>
        
        <div className="space-y-4">
          {/* Place of Service */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              Place of Service <span className="text-red-500">*</span>
            </label>
            <select 
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark' 
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                errors.place_of_service && 'border-red-500'
              )}
              value={formData.place_of_service || '11'}
              onChange={(e) => updateFormData({ place_of_service: e.target.value })}
            >
              {placeOfServiceOptions.map(option => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
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
    </div>
  );
}