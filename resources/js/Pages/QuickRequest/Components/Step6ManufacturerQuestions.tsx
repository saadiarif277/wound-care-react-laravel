import React from 'react';
import { FiInfo, FiAlertCircle } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { getManufacturerByProduct, ManufacturerField, ManufacturerConfig } from '../manufacturerFields';

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface FormData {
  selected_products?: SelectedProduct[];
  manufacturer_fields?: Record<string, any>;
  [key: string]: any;
}

interface Step6Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
  }>;
  errors: Record<string, string>;
}

export default function Step6ManufacturerQuestions({
  formData,
  updateFormData,
  products,
  errors
}: Step6Props) {
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

  // Get the selected product
  const getSelectedProduct = () => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return null;
    }

    const selectedProductId = formData.selected_products[0]?.product_id;
    return products.find(p => p.id === selectedProductId);
  };

  const selectedProduct = getSelectedProduct();

  // Get manufacturer config for the selected product
  const getManufacturerConfig = (): ManufacturerConfig | null => {
    if (!selectedProduct) return null;
    return getManufacturerByProduct(selectedProduct.name) || null;
  };

  const manufacturerConfig = getManufacturerConfig();

  // Update manufacturer field value
  const updateManufacturerField = (fieldName: string, value: any) => {
    updateFormData({
      manufacturer_fields: {
        ...(formData.manufacturer_fields || {}),
        [fieldName]: value
      }
    });
  };

  // Initialize manufacturer fields if needed
  React.useEffect(() => {
    if (manufacturerConfig && !formData.manufacturer_fields) {
      const initialFields: Record<string, any> = {};
      manufacturerConfig.fields.forEach(field => {
        initialFields[field.name] = field.type === 'checkbox' ? false : '';
      });
      updateFormData({ manufacturer_fields: initialFields });
    }
  }, [manufacturerConfig?.name]);

  // Render field based on type
  const renderField = (field: ManufacturerField) => {
    // Check if this field should be shown based on conditional
    if (field.conditionalOn) {
      const dependentValue = formData.manufacturer_fields?.[field.conditionalOn.field];
      if (dependentValue !== field.conditionalOn.value) {
        return null;
      }
    }

    const fieldValue = formData.manufacturer_fields?.[field.name] || '';
    const fieldError = errors[`manufacturer_${field.name}`];

    switch (field.type) {
      case 'checkbox':
        return (
          <div key={field.name} className="mb-4">
            <label className="flex items-start">
              <input
                type="checkbox"
                className="form-checkbox h-4 w-4 text-blue-600 rounded mt-1"
                checked={fieldValue || false}
                onChange={(e) => updateManufacturerField(field.name, e.target.checked)}
              />
              <div className="ml-3">
                <span className={cn("block", t.text.primary)}>
                  {field.label}
                  {field.required && <span className="text-red-500 ml-1">*</span>}
                </span>
                {field.description && (
                  <p className={cn("text-xs mt-1", t.text.secondary)}>
                    {field.description}
                  </p>
                )}
              </div>
            </label>
            {fieldError && (
              <p className="mt-1 ml-7 text-sm text-red-500">{fieldError}</p>
            )}
          </div>
        );

      case 'text':
        return (
          <div key={field.name} className="mb-4">
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <input
              type="text"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                fieldError && 'border-red-500'
              )}
              value={fieldValue}
              onChange={(e) => updateManufacturerField(field.name, e.target.value)}
              placeholder={field.placeholder}
              aria-label={field.label}
            />
            {field.description && (
              <p className={cn("text-xs mt-1", t.text.secondary)}>
                {field.description}
              </p>
            )}
            {fieldError && (
              <p className="mt-1 text-sm text-red-500">{fieldError}</p>
            )}
          </div>
        );

      case 'radio':
        return (
          <div key={field.name} className="mb-4">
            <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <div className="space-y-2">
              {field.options?.map(option => (
                <label key={option.value} className="flex items-center">
                  <input
                    type="radio"
                    name={field.name}
                    className="form-radio text-blue-600"
                    value={option.value}
                    checked={fieldValue === option.value}
                    onChange={(e) => updateManufacturerField(field.name, e.target.value)}
                  />
                  <span className={cn("ml-2", t.text.primary)}>{option.label}</span>
                </label>
              ))}
            </div>
            {field.description && (
              <p className={cn("text-xs mt-1", t.text.secondary)}>
                {field.description}
              </p>
            )}
            {fieldError && (
              <p className="mt-1 text-sm text-red-500">{fieldError}</p>
            )}
          </div>
        );

      case 'select':
        return (
          <div key={field.name} className="mb-4">
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <select
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                fieldError && 'border-red-500'
              )}
              value={fieldValue}
              onChange={(e) => updateManufacturerField(field.name, e.target.value)}
              aria-label={field.label}
            >
              <option value="">Select...</option>
              {field.options?.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            {field.description && (
              <p className={cn("text-xs mt-1", t.text.secondary)}>
                {field.description}
              </p>
            )}
            {fieldError && (
              <p className="mt-1 text-sm text-red-500">{fieldError}</p>
            )}
          </div>
        );

      case 'date':
        return (
          <div key={field.name} className="mb-4">
            <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <input
              type="date"
              className={cn(
                "w-full p-2 rounded border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
                  : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
                fieldError && 'border-red-500'
              )}
              value={fieldValue}
              onChange={(e) => updateManufacturerField(field.name, e.target.value)}
              aria-label={field.label}
            />
            {field.description && (
              <p className={cn("text-xs mt-1", t.text.secondary)}>
                {field.description}
              </p>
            )}
            {fieldError && (
              <p className="mt-1 text-sm text-red-500">{fieldError}</p>
            )}
          </div>
        );

      default:
        return null;
    }
  };

  // No product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <p>Please select a product first</p>
      </div>
    );
  }

  // No manufacturer-specific fields required
  if (!manufacturerConfig || manufacturerConfig.fields.length === 0) {
    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <FiInfo className={cn("h-12 w-12 mx-auto mb-4", t.text.secondary)} />
        <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
          No Additional Requirements
        </h3>
        <p className={cn("text-sm", t.text.secondary)}>
          {selectedProduct.name} does not require any manufacturer-specific information.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Product Info */}
      <div className={cn("p-4 rounded-lg", t.glass.card)}>
        <div className="flex items-start">
          <FiInfo className={cn("h-5 w-5 mt-0.5 flex-shrink-0 mr-3", t.text.secondary)} />
          <div>
            <h3 className={cn("text-sm font-medium", t.text.primary)}>
              Selected Product
            </h3>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              {selectedProduct.name} • {selectedProduct.code} • {selectedProduct.manufacturer}
            </p>
          </div>
        </div>
      </div>

      {/* Manufacturer Requirements */}
      <div className={cn("p-6 rounded-lg", theme === 'dark' ? 'bg-purple-900/20' : 'bg-purple-50')}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          {manufacturerConfig.name} Specific Requirements
          {manufacturerConfig.signatureRequired && (
            <span className={cn("text-sm font-normal ml-2", t.text.secondary)}>
              (Electronic Signature Required)
            </span>
          )}
        </h3>

        {/* Important Notice */}
        {manufacturerConfig.signatureRequired && (
          <div className={cn(
            "mb-6 p-4 rounded-lg border flex items-start",
            theme === 'dark'
              ? 'bg-yellow-900/20 border-yellow-800'
              : 'bg-yellow-50 border-yellow-200'
          )}>
            <FiAlertCircle className={cn(
              "h-5 w-5 mt-0.5 flex-shrink-0 mr-3",
              theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
            )} />
            <div>
              <h4 className={cn(
                "text-sm font-medium",
                theme === 'dark' ? 'text-yellow-300' : 'text-yellow-900'
              )}>
                Important: Electronic Signature Required
              </h4>
              <p className={cn(
                "text-sm mt-1",
                theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'
              )}>
                This manufacturer requires an electronic signature on their IVR (Independent Verification Request) form.
                After completing these questions, you'll be presented with the IVR form to sign electronically.
              </p>
            </div>
          </div>
        )}

        <div className="space-y-4">
          {manufacturerConfig.fields.map(field => renderField(field))}
        </div>

        {/* General Errors */}
        {errors.manufacturer_fields && (
          <div className={cn(
            "mt-4 p-4 rounded-lg border",
            theme === 'dark'
              ? 'bg-red-900/20 border-red-800'
              : 'bg-red-50 border-red-200'
          )}>
            <p className={cn(
              "text-sm",
              theme === 'dark' ? 'text-red-400' : 'text-red-600'
            )}>
              {errors.manufacturer_fields}
            </p>
          </div>
        )}
      </div>

      {/* Documentation Notice */}
      <div className={cn(
        "p-4 rounded-lg border",
        theme === 'dark'
          ? 'bg-gray-800 border-gray-700'
          : 'bg-gray-50 border-gray-200'
      )}>
        <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
          📄 Required Documentation
        </h4>
        <p className={cn("text-sm", t.text.secondary)}>
          Based on your product selection, you will need to provide:
        </p>
        <ul className={cn("mt-2 space-y-1 text-sm", t.text.secondary)}>
          <li>• Face sheet or patient demographics</li>
          <li>• Clinical notes supporting medical necessity</li>
          <li>• Wound photo (if available)</li>
          {manufacturerConfig.signatureRequired && (
            <li>• Electronic signature on IVR form</li>
          )}
          {manufacturerConfig.name === 'BioWound' && (
            <li>• Non-HOPD certification (California facilities only)</li>
          )}
          {manufacturerConfig.name === 'Advanced Health' && formData.manufacturer_fields?.previous_use && (
            <li>• Documentation of previous product use</li>
          )}
          {manufacturerConfig.name === 'Extremity Care' && formData.manufacturer_fields?.order_type === 'standing' && (
            <li>• Quarterly standing order documentation</li>
          )}
        </ul>
      </div>
    </div>
  );
}