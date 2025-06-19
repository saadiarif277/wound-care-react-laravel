import React from 'react';
import { FiCheck } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import PayerSearchInput from '@/Components/PayerSearchInput';

interface Step3Props {
  formData: any;
  updateFormData: (data: any) => void;
  insuranceCarriers: string[];
  errors: Record<string, string>;
}

export default function Step3Insurance({ 
  formData, 
  updateFormData, 
  insuranceCarriers,
  errors 
}: Step3Props) {
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
      {/* Primary Insurance */}
      <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-purple-900/20' : 'bg-purple-50')}>
        <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
          Primary Insurance
        </h3>
        
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Insurance Name <span className="text-red-500">*</span>
              </label>
              {insuranceCarriers.length > 0 ? (
                <select 
                  className={cn(
                    "w-full p-2 rounded border transition-all",
                    theme === 'dark' 
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                    errors.primary_insurance_name && 'border-red-500'
                  )}
                  value={formData.primary_insurance_name}
                  onChange={(e) => {
                    updateFormData({ 
                      primary_insurance_name: e.target.value,
                      primary_payer_phone: getPayerPhone(e.target.value)
                    });
                  }}
                >
                  <option value="">Select insurance...</option>
                  {insuranceCarriers.map(carrier => (
                    <option key={carrier} value={carrier}>{carrier}</option>
                  ))}
                </select>
              ) : (
                <PayerSearchInput
                  value={formData.primary_insurance_name}
                  onChange={(value) => {
                    updateFormData({ 
                      primary_insurance_name: value,
                      primary_payer_phone: getPayerPhone(value)
                    });
                  }}
                  error={errors.primary_insurance_name}
                  placeholder="Search for insurance..."
                />
              )}
              {errors.primary_insurance_name && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_insurance_name}</p>
              )}
            </div>
            
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Member ID <span className="text-red-500">*</span>
              </label>
              <input 
                type="text"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.primary_member_id && 'border-red-500'
                )}
                value={formData.primary_member_id}
                onChange={(e) => updateFormData({ primary_member_id: e.target.value })}
                placeholder="1234567890A"
              />
              {errors.primary_member_id && (
                <p className="mt-1 text-sm text-red-500">{errors.primary_member_id}</p>
              )}
            </div>
            
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Payer Phone {formData.primary_payer_phone && '(Auto-filled)'}
              </label>
              <input 
                type="tel"
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  formData.primary_payer_phone
                    ? theme === 'dark' 
                      ? 'bg-gray-700 border-gray-600 text-gray-300' 
                      : 'bg-gray-100 border-gray-300 text-gray-500'
                    : theme === 'dark' 
                      ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                      : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  formData.primary_payer_phone && "cursor-not-allowed"
                )}
                value={formData.primary_payer_phone || ''}
                onChange={(e) => updateFormData({ primary_payer_phone: e.target.value })}
                readOnly={!!formData.primary_payer_phone}
                placeholder="1-800-555-0100"
              />
            </div>
            
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                Plan Type <span className="text-red-500">*</span>
              </label>
              <select 
                className={cn(
                  "w-full p-2 rounded border transition-all",
                  theme === 'dark' 
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                    : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                  errors.primary_plan_type && 'border-red-500'
                )}
                value={formData.primary_plan_type}
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
        <div className={cn("p-4 border rounded-lg", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
          <label className="flex items-center cursor-pointer">
            <input 
              type="checkbox"
              className="form-checkbox h-4 w-4 text-blue-600 rounded"
              checked={formData.has_secondary_insurance}
              onChange={(e) => updateFormData({ has_secondary_insurance: e.target.checked })}
            />
            <span className={cn("ml-2 font-medium", t.text.primary)}>
              Patient has secondary insurance
            </span>
          </label>
        </div>

        {/* Secondary Insurance Details */}
        {formData.has_secondary_insurance && (
          <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-indigo-900/20' : 'bg-indigo-50')}>
            <h3 className={cn("text-lg font-medium mb-3", t.text.primary)}>
              Secondary Insurance
            </h3>
            
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Insurance Name <span className="text-red-500">*</span>
                  </label>
                  {insuranceCarriers.length > 0 ? (
                    <select 
                      className={cn(
                        "w-full p-2 rounded border transition-all",
                        theme === 'dark' 
                          ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                          : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                        errors.secondary_insurance && 'border-red-500'
                      )}
                      value={formData.secondary_insurance_name || ''}
                      onChange={(e) => updateFormData({ secondary_insurance_name: e.target.value })}
                    >
                      <option value="">Select insurance...</option>
                      {insuranceCarriers.map(carrier => (
                        <option key={carrier} value={carrier}>{carrier}</option>
                      ))}
                    </select>
                  ) : (
                    <PayerSearchInput
                      value={formData.secondary_insurance_name || ''}
                      onChange={(value) => updateFormData({ secondary_insurance_name: value })}
                      error={errors.secondary_insurance}
                      placeholder="Search for insurance..."
                    />
                  )}
                  {errors.secondary_insurance && (
                    <p className="mt-1 text-sm text-red-500">{errors.secondary_insurance}</p>
                  )}
                </div>
                
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Member ID <span className="text-red-500">*</span>
                  </label>
                  <input 
                    type="text"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark' 
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                      errors.secondary_insurance && 'border-red-500'
                    )}
                    value={formData.secondary_member_id || ''}
                    onChange={(e) => updateFormData({ secondary_member_id: e.target.value })}
                    placeholder="Secondary policy number"
                  />
                </div>
                
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Subscriber Name
                  </label>
                  <input 
                    type="text"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark' 
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
                    value={formData.secondary_subscriber_name || ''}
                    onChange={(e) => updateFormData({ secondary_subscriber_name: e.target.value })}
                    placeholder="If different from patient"
                  />
                </div>
                
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Subscriber DOB
                  </label>
                  <input 
                    type="date"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark' 
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
                    value={formData.secondary_subscriber_dob || ''}
                    onChange={(e) => updateFormData({ secondary_subscriber_dob: e.target.value })}
                  />
                </div>
                
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Payer Phone
                  </label>
                  <input 
                    type="tel"
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark' 
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
                    value={formData.secondary_payer_phone || ''}
                    onChange={(e) => updateFormData({ secondary_payer_phone: e.target.value })}
                    placeholder="(800) 555-0100"
                  />
                </div>
                
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Plan Type
                  </label>
                  <select 
                    className={cn(
                      "w-full p-2 rounded border transition-all",
                      theme === 'dark' 
                        ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
                        : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500'
                    )}
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
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark' 
            ? 'bg-green-900/20 border-green-800' 
            : 'bg-green-50 border-green-200'
        )}>
          <label className="flex items-center cursor-pointer">
            <input 
              type="checkbox"
              className="form-checkbox h-4 w-4 text-green-600 rounded"
              checked={formData.prior_auth_permission}
              onChange={(e) => updateFormData({ prior_auth_permission: e.target.checked })}
            />
            <span className={cn("ml-2", t.text.primary)}>
              MSC may initiate/follow up on prior authorization
            </span>
          </label>
          <p className={cn("mt-2 text-sm ml-6", t.text.secondary)}>
            By checking this box, you authorize MSC to handle prior authorization on your behalf
          </p>
        </div>
      </div>

      {/* Insurance Summary */}
      {(formData.primary_insurance_name || formData.insurance_card_auto_filled) && (
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark' 
            ? 'bg-gray-800 border-gray-700' 
            : 'bg-gray-50 border-gray-200'
        )}>
          <h4 className={cn("text-sm font-medium mb-2 flex items-center", t.text.primary)}>
            <FiCheck className="h-4 w-4 mr-2 text-green-500" />
            Insurance Summary
          </h4>
          <div className={cn("space-y-1 text-sm", t.text.secondary)}>
            {formData.primary_insurance_name && (
              <p>Primary: {formData.primary_insurance_name} - {formData.primary_member_id || 'Member ID pending'}</p>
            )}
            {formData.has_secondary_insurance && formData.secondary_insurance_name && (
              <p>Secondary: {formData.secondary_insurance_name} - {formData.secondary_member_id || 'Member ID pending'}</p>
            )}
            {formData.insurance_card_auto_filled && (
              <p className="text-green-600 dark:text-green-400">✓ Information auto-filled from insurance card</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}