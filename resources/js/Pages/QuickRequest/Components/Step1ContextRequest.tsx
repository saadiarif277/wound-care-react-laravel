import React from 'react';
import { FiInfo } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Step1Props {
  formData: any;
  updateFormData: (data: any) => void;
  providers: Array<{
    id: number;
    name: string;
    credentials?: string;
    npi?: string;
  }>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  currentUser: {
    id: number;
    name: string;
    role?: string;
  };
  errors: Record<string, string>;
}

export default function Step1ContextRequest({ 
  formData, 
  updateFormData, 
  providers,
  facilities,
  currentUser,
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

  // Pre-select provider if user is a provider
  React.useEffect(() => {
    if (currentUser.role === 'provider' && !formData.provider_id) {
      updateFormData({ provider_id: currentUser.id });
    }
  }, [currentUser]);

  return (
    <div className="space-y-6">
      {/* Request Type */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
          Request Type
        </label>
        <select 
          className={cn(
            "w-full p-3 rounded-lg border transition-all",
            theme === 'dark' 
              ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
              : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
            errors.request_type && 'border-red-500'
          )}
          value={formData.request_type}
          onChange={(e) => updateFormData({ request_type: e.target.value })}
        >
          <option value="new_request">New Request</option>
          <option value="reverification">Re-verification</option>
          <option value="additional_applications">Additional Applications</option>
        </select>
        {errors.request_type && (
          <p className="mt-1 text-sm text-red-500">{errors.request_type}</p>
        )}
      </div>

      {/* Provider Selection */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
          Provider <span className="text-red-500">*</span>
        </label>
        <select 
          className={cn(
            "w-full p-3 rounded-lg border transition-all",
            theme === 'dark' 
              ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
              : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
            errors.provider_id && 'border-red-500'
          )}
          value={formData.provider_id || ''}
          onChange={(e) => updateFormData({ provider_id: parseInt(e.target.value) })}
          disabled={currentUser.role === 'provider'}
        >
          <option value="">Select a provider...</option>
          {providers.map(p => (
            <option key={p.id} value={p.id}>
              {p.name}{p.credentials ? `, ${p.credentials}` : ''} {p.npi ? `(NPI: ${p.npi})` : ''}
            </option>
          ))}
        </select>
        {errors.provider_id && (
          <p className="mt-1 text-sm text-red-500">{errors.provider_id}</p>
        )}
      </div>

      {/* Facility Selection */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
          Facility <span className="text-red-500">*</span>
        </label>
        <select 
          className={cn(
            "w-full p-3 rounded-lg border transition-all",
            theme === 'dark' 
              ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500' 
              : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
            errors.facility_id && 'border-red-500'
          )}
          value={formData.facility_id || ''}
          onChange={(e) => updateFormData({ facility_id: parseInt(e.target.value) })}
        >
          <option value="">Select a facility...</option>
          {facilities.map(f => (
            <option key={f.id} value={f.id}>
              {f.name} {f.address ? `(${f.address})` : ''}
            </option>
          ))}
        </select>
        {errors.facility_id && (
          <p className="mt-1 text-sm text-red-500">{errors.facility_id}</p>
        )}
      </div>

      {/* Sales Representative (Auto-filled) */}
      {formData.sales_rep_id && (
        <div>
          <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
            Sales Representative
          </label>
          <input 
            type="text"
            className={cn(
              "w-full p-3 rounded-lg border",
              theme === 'dark' 
                ? 'bg-gray-700 border-gray-600 text-gray-300' 
                : 'bg-gray-100 border-gray-300 text-gray-500',
              "cursor-not-allowed"
            )}
            value={formData.sales_rep_id}
            readOnly
          />
          <p className={cn("mt-1 text-xs", t.text.secondary)}>
            Automatically assigned based on your account
          </p>
        </div>
      )}

      {/* Info Box */}
      <div className={cn(
        "p-4 rounded-lg border",
        theme === 'dark' 
          ? 'bg-blue-900/20 border-blue-800' 
          : 'bg-blue-50 border-blue-200'
      )}>
        <div className="flex items-start">
          <FiInfo className={cn(
            "w-5 h-5 mr-2 flex-shrink-0 mt-0.5",
            theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
          )} />
          <div>
            <h4 className={cn(
              "text-sm font-medium",
              theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
            )}>
              Quick Tip
            </h4>
            <p className={cn(
              "mt-1 text-sm",
              theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
            )}>
              Select the appropriate request type based on your patient's needs. 
              New requests are for first-time orders, while re-verification is for 
              existing patients requiring insurance updates.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}