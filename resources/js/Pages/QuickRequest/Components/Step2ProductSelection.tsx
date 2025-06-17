import React, { useState, useEffect } from 'react';
import { FiPackage, FiAlertCircle } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { getManufacturerConfig, getManufacturerByProduct, ManufacturerConfig, ManufacturerField } from './manufacturerFields';
import ProductSelector from '@/Components/ProductCatalog/ProductSelector';

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface Step2Props {
  formData: any;
  updateFormData: (data: any) => void;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    sizes?: string[];
  }>;
  errors: Record<string, string>;
  facilities: Array<any>;
  woundTypes: Record<string, string>;
  userRole?: string;
}

export default function Step2ProductSelection({ 
  formData, 
  updateFormData, 
  products,
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
  
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  const [manufacturerConfig, setManufacturerConfig] = useState<ManufacturerConfig | null>(null);

  useEffect(() => {
    if (formData.product_id) {
      const product = products.find(p => p.id === formData.product_id);
      if (product) {
        setSelectedProduct(product);
        const config = getManufacturerConfig(product.manufacturer) || 
                      getManufacturerByProduct(product.name);
        setManufacturerConfig(config || null);
      }
    }
  }, [formData.product_id, products]);

  const handleProductChange = (productId: number) => {
    const product = products.find(p => p.id === productId);
    if (product) {
      updateFormData({
        product_id: productId,
        product_code: product.code,
        product_name: product.name,
        manufacturer: product.manufacturer,
        size: '', // Reset size when product changes
        manufacturer_fields: {}, // Reset manufacturer fields
      });
    }
  };

  const handleManufacturerFieldChange = (fieldName: string, value: any) => {
    updateFormData({
      manufacturer_fields: {
        ...formData.manufacturer_fields,
        [fieldName]: value,
      },
    });
  };

  const renderManufacturerField = (field: ManufacturerField) => {
    // Check if field should be shown based on conditional
    if (field.conditionalOn) {
      const conditionalValue = formData.manufacturer_fields?.[field.conditionalOn.field];
      if (conditionalValue !== field.conditionalOn.value) {
        return null;
      }
    }

    const fieldValue = formData.manufacturer_fields?.[field.name] || '';

    switch (field.type) {
      case 'checkbox':
        return (
          <div key={field.name} className="flex items-start">
            <input
              type="checkbox"
              id={field.name}
              checked={fieldValue === true}
              onChange={(e) => handleManufacturerFieldChange(field.name, e.target.checked)}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-1"
            />
            <label 
              htmlFor={field.name} 
              className={cn("ml-3 text-sm", t.text.secondary)}
            >
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
          </div>
        );

      case 'text':
        return (
          <div key={field.name}>
            <label 
              htmlFor={field.name}
              className={cn("block text-sm font-medium mb-1", t.text.secondary)}
            >
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <input
              type="text"
              id={field.name}
              value={fieldValue}
              onChange={(e) => handleManufacturerFieldChange(field.name, e.target.value)}
              placeholder={field.placeholder}
              className={cn("w-full", t.input.base, t.input.focus)}
            />
            {field.description && (
              <p className={cn("mt-1 text-xs", t.text.tertiary)}>
                {field.description}
              </p>
            )}
          </div>
        );

      case 'radio':
        return (
          <div key={field.name}>
            <label className={cn("block text-sm font-medium mb-2", t.text.secondary)}>
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <div className="space-y-2">
              {field.options?.map(option => (
                <div key={option.value} className="flex items-center">
                  <input
                    type="radio"
                    id={`${field.name}_${option.value}`}
                    name={field.name}
                    value={option.value}
                    checked={fieldValue === option.value}
                    onChange={(e) => handleManufacturerFieldChange(field.name, e.target.value)}
                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                  />
                  <label 
                    htmlFor={`${field.name}_${option.value}`}
                    className={cn("ml-2 text-sm", t.text.secondary)}
                  >
                    {option.label}
                  </label>
                </div>
              ))}
            </div>
            {field.description && (
              <p className={cn("mt-1 text-xs", t.text.tertiary)}>
                {field.description}
              </p>
            )}
          </div>
        );

      case 'select':
        return (
          <div key={field.name}>
            <label 
              htmlFor={field.name}
              className={cn("block text-sm font-medium mb-1", t.text.secondary)}
            >
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <select
              id={field.name}
              value={fieldValue}
              onChange={(e) => handleManufacturerFieldChange(field.name, e.target.value)}
              className={cn("w-full", t.input.base, t.input.focus)}
            >
              <option value="">Select {field.label}</option>
              {field.options?.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        );

      case 'date':
        return (
          <div key={field.name}>
            <label 
              htmlFor={field.name}
              className={cn("block text-sm font-medium mb-1", t.text.secondary)}
            >
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <input
              type="date"
              id={field.name}
              value={fieldValue}
              onChange={(e) => handleManufacturerFieldChange(field.name, e.target.value)}
              className={cn("w-full", t.input.base, t.input.focus)}
            />
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Step 2: Product Selection & Manufacturer-Specific Fields
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Select your product and complete manufacturer requirements
        </p>
      </div>

      {/* Product Selection */}
      <div className={cn("p-6 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-lg font-medium mb-4 flex items-center", t.text.primary)}>
          <FiPackage className="mr-2" />
          Product Selection
        </h3>
        
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Select Product *
            </label>
            <select
              value={formData.product_id || ''}
              onChange={(e) => handleProductChange(Number(e.target.value))}
              className={cn("w-full", t.input.base, t.input.focus,
                errors.product_id && "border-red-500"
              )}
            >
              <option value="">Select a product</option>
              {products.map(product => (
                <option key={product.id} value={product.id}>
                  {product.code} - {product.name} ({product.manufacturer})
                </option>
              ))}
            </select>
            {errors.product_id && (
              <p className="mt-1 text-sm text-red-500">{errors.product_id}</p>
            )}
          </div>

          {selectedProduct && selectedProduct.sizes && (
            <div>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Size *
              </label>
              <select
                value={formData.size || ''}
                onChange={(e) => updateFormData({ size: e.target.value })}
                className={cn("w-full", t.input.base, t.input.focus,
                  errors.size && "border-red-500"
                )}
              >
                <option value="">Select size</option>
                {selectedProduct.sizes.map((size: string) => (
                  <option key={size} value={size}>{size}</option>
                ))}
              </select>
              {errors.size && (
                <p className="mt-1 text-sm text-red-500">{errors.size}</p>
              )}
            </div>
          )}

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Quantity
            </label>
            <input
              type="number"
              value={formData.quantity || 1}
              onChange={(e) => updateFormData({ quantity: parseInt(e.target.value) || 1 })}
              min="1"
              className={cn("w-full", t.input.base, t.input.focus)}
            />
          </div>
        </div>
      </div>

      {/* Manufacturer-Specific Requirements */}
      {manufacturerConfig && (
        <div className={cn("p-6 rounded-lg", t.glass.panel)}>
          <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
            🏭 Manufacturer-Specific Requirements
          </h3>
          
          {/* Manufacturer Name and Signature Requirement */}
          <div className={cn("mb-4 p-3 rounded-md", 
            theme === 'dark' ? 'bg-gray-800' : 'bg-gray-50'
          )}>
            <p className={cn("font-medium", t.text.primary)}>
              {manufacturerConfig.name}
            </p>
            <p className={cn("text-sm mt-1", 
              manufacturerConfig.signatureRequired ? 'text-orange-500' : t.text.secondary
            )}>
              {manufacturerConfig.signatureRequired ? '✍️ SIGNATURE REQUIRED' : '⚠️ NO SIGNATURE REQUIRED'}
            </p>
          </div>

          {/* Manufacturer Fields */}
          <div className="space-y-4">
            {manufacturerConfig.fields.map(field => renderManufacturerField(field))}
          </div>

          {/* Validation Errors for Manufacturer Fields */}
          {errors.manufacturer_fields && (
            <div className={cn("mt-4 p-3 rounded-md flex items-start", 
              "bg-red-50 border border-red-200"
            )}>
              <FiAlertCircle className="h-5 w-5 text-red-400 mt-0.5 mr-2 flex-shrink-0" />
              <p className="text-sm text-red-600">
                Please complete all required manufacturer fields
              </p>
            </div>
          )}
        </div>
      )}

      {/* Product Summary */}
      {selectedProduct && (
        <div className={cn("p-6 rounded-lg", t.glass.panel)}>
          <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
            Product Summary
          </h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Product Code</p>
              <p className={cn("text-sm", t.text.primary)}>{selectedProduct.code}</p>
            </div>
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Product Name</p>
              <p className={cn("text-sm", t.text.primary)}>{selectedProduct.name}</p>
            </div>
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Manufacturer</p>
              <p className={cn("text-sm", t.text.primary)}>{selectedProduct.manufacturer}</p>
            </div>
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Selected Size</p>
              <p className={cn("text-sm", t.text.primary)}>{formData.size || 'Not selected'}</p>
            </div>
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Quantity</p>
              <p className={cn("text-sm", t.text.primary)}>{formData.quantity}</p>
            </div>
            <div>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Signature Required</p>
              <p className={cn("text-sm", t.text.primary)}>
                {manufacturerConfig?.signatureRequired ? 'Yes' : 'No'}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}