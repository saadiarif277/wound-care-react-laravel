import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ReviewAndSubmitStepProps {
  formData?: any;
  onSubmit?: () => void;
  isSubmitting?: boolean;
  errors?: Record<string, string>;
}

const ReviewAndSubmitStep: React.FC<ReviewAndSubmitStepProps> = ({
  formData,
  onSubmit,
  isSubmitting = false,
  errors = {}
}) => {
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

  return (
    <div className="space-y-6">
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Review and Submit
        </h3>

        <div className={cn("text-sm", t.text.secondary)}>
          <p className="mb-4">
            Please review your product request details before submitting.
          </p>

          {/* Form Data Summary */}
          {formData && (
            <div className="space-y-2">
              <p><strong>Patient:</strong> {formData.patient_name || 'Not specified'}</p>
              <p><strong>Product:</strong> {formData.product_name || 'Not specified'}</p>
              <p><strong>Facility:</strong> {formData.facility_name || 'Not specified'}</p>
            </div>
          )}

          {/* Errors */}
          {Object.keys(errors).length > 0 && (
            <div className={cn("mt-4 p-4 rounded border",
              theme === 'dark' ? 'bg-red-900/20 border-red-800' : 'bg-red-50 border-red-200'
            )}>
              <h4 className={cn("font-medium mb-2",
                theme === 'dark' ? 'text-red-300' : 'text-red-800'
              )}>
                Please fix the following errors:
              </h4>
              <ul className={cn("text-sm space-y-1",
                theme === 'dark' ? 'text-red-400' : 'text-red-700'
              )}>
                {Object.entries(errors).map(([field, message]) => (
                  <li key={field}>• {message}</li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {/* Submit Button */}
        {onSubmit && (
          <div className="mt-6">
            <button
              onClick={onSubmit}
              disabled={isSubmitting}
              className={cn(
                "px-6 py-3 rounded-lg font-medium transition-all",
                isSubmitting
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:shadow-lg",
                "bg-gradient-to-r from-green-500 to-green-600 text-white"
              )}
            >
              {isSubmitting ? 'Submitting...' : 'Submit Product Request'}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default ReviewAndSubmitStep;
