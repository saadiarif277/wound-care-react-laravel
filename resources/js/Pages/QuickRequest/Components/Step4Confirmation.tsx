import React, { useState, useEffect } from 'react';
import { FiCheck, FiAlertCircle, FiExternalLink } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { getManufacturerConfig } from './manufacturerFields';

interface Step4Props {
  formData: any;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    sizes?: string[];
  }>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  onSubmit: () => void;
  isSubmitting: boolean;
}

export default function Step4Confirmation({ 
  formData, 
  products,
  facilities,
  onSubmit,
  isSubmitting 
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
  
  const [showIVRFrame, setShowIVRFrame] = useState(false);
  const [ivrSigned, setIvrSigned] = useState(false);
  const [signatureRequired, setSignatureRequired] = useState(false);
  
  const selectedProduct = products.find(p => p.id === formData.product_id);
  const selectedFacility = facilities.find(f => f.id === formData.facility_id);
  const manufacturerConfig = selectedProduct ? getManufacturerConfig(selectedProduct.manufacturer) : null;

  useEffect(() => {
    // Check if signature is required based on manufacturer
    if (manufacturerConfig) {
      setSignatureRequired(manufacturerConfig.signatureRequired);
    }
  }, [manufacturerConfig]);

  const handleIVRSign = () => {
    setShowIVRFrame(true);
    // TODO: Integrate with actual DocuSeal IVR
    // For now, simulate signing after 3 seconds
    setTimeout(() => {
      setIvrSigned(true);
      setShowIVRFrame(false);
    }, 3000);
  };

  const formatPatientDisplay = () => {
    const firstName = formData.patient_first_name || '';
    const lastName = formData.patient_last_name || '';
    const firstTwo = firstName.substring(0, 2).toUpperCase();
    const lastTwo = lastName.substring(0, 2).toUpperCase();
    const randomNum = Math.floor(Math.random() * 9000) + 1000;
    return `${firstTwo}${lastTwo}${randomNum}`;
  };

  const canSubmit = !signatureRequired || ivrSigned;

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Step 4: Review & Confirmation
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Review your order details and complete signature if required
        </p>
      </div>

      {/* Order Summary */}
      <div className={cn("p-6 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Order Summary
        </h3>
        
        <div className="space-y-4">
          {/* Patient Information */}
          <div>
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Patient Information
            </h4>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Display ID:</span>
                <span className={cn("ml-2", t.text.primary)}>{formatPatientDisplay()}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>DOB:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.patient_dob}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Member ID:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.patient_member_id || 'N/A'}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Payer:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.payer_name}</span>
              </div>
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Product Details
            </h4>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Product:</span>
                <span className={cn("ml-2", t.text.primary)}>
                  {selectedProduct?.code} - {selectedProduct?.name}
                </span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Manufacturer:</span>
                <span className={cn("ml-2", t.text.primary)}>{selectedProduct?.manufacturer}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Size:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.size}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Quantity:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.quantity}</span>
              </div>
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Shipping Information
            </h4>
            <div className="text-sm">
              <div className="mb-2">
                <span className={cn("font-medium", t.text.tertiary)}>Facility:</span>
                <span className={cn("ml-2", t.text.primary)}>{selectedFacility?.name}</span>
              </div>
              {selectedFacility?.address && (
                <div>
                  <span className={cn("font-medium", t.text.tertiary)}>Address:</span>
                  <span className={cn("ml-2", t.text.primary)}>{selectedFacility.address}</span>
                </div>
              )}
              <div className="mt-2">
                <span className={cn("font-medium", t.text.tertiary)}>Shipping Speed:</span>
                <span className={cn("ml-2", t.text.primary)}>
                  {formData.shipping_speed?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </span>
              </div>
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Service Details
            </h4>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Expected Service Date:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.expected_service_date}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Wound Type:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.wound_type}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Signature Requirement */}
      {signatureRequired && (
        <div className={cn("p-6 rounded-lg", t.glass.panel)}>
          <h3 className={cn("text-lg font-medium mb-4 flex items-center", t.text.primary)}>
            <FiAlertCircle className="mr-2 text-orange-500" />
            Signature Required
          </h3>
          
          <div className={cn("p-4 rounded-lg mb-4", 
            theme === 'dark' ? 'bg-orange-900/20' : 'bg-orange-50'
          )}>
            <p className={cn("text-sm", theme === 'dark' ? 'text-orange-400' : 'text-orange-700')}>
              {manufacturerConfig?.name} requires a signature for this product. 
              Please complete the IVR signature process before submitting.
            </p>
          </div>

          {!ivrSigned ? (
            <button
              onClick={handleIVRSign}
              className={cn(
                "w-full px-6 py-3 rounded-lg font-medium transition-all hover:shadow-lg flex items-center justify-center",
                "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
              )}
            >
              Complete IVR Signature
              <FiExternalLink className="ml-2" />
            </button>
          ) : (
            <div className={cn("p-4 rounded-lg flex items-center", 
              theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
            )}>
              <FiCheck className="h-5 w-5 text-green-500 mr-2" />
              <p className={cn("text-sm", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                IVR signature completed successfully
              </p>
            </div>
          )}

          {showIVRFrame && (
            <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
              <div className={cn("w-full max-w-4xl h-3/4 rounded-lg", t.glass.card)}>
                <div className="p-4 border-b">
                  <h3 className={cn("text-lg font-medium", t.text.primary)}>
                    DocuSeal IVR Signature
                  </h3>
                </div>
                <div className="p-8 flex items-center justify-center h-full">
                  <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                    <p className={cn("text-sm", t.text.secondary)}>
                      Loading DocuSeal signature interface...
                    </p>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Attestations Summary */}
      <div className={cn("p-6 rounded-lg", t.glass.panel)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Attestations & Authorizations
        </h3>
        
        <div className="space-y-2">
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Clinical attestations confirmed
            </span>
          </div>
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Prior authorization consent provided
            </span>
          </div>
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Provider authorization completed
            </span>
          </div>
          {signatureRequired && (
            <div className="flex items-center">
              {ivrSigned ? (
                <FiCheck className="h-4 w-4 text-green-500 mr-2" />
              ) : (
                <div className="h-4 w-4 rounded-full border-2 border-gray-300 mr-2" />
              )}
              <span className={cn("text-sm", t.text.secondary)}>
                Manufacturer signature requirement
              </span>
            </div>
          )}
        </div>
      </div>

      {/* Submit Section */}
      <div className={cn("p-6 rounded-lg text-center", t.glass.panel)}>
        <p className={cn("mb-4", t.text.secondary)}>
          By submitting this order, you confirm that all information is accurate and complete.
        </p>
        
        <button
          onClick={onSubmit}
          disabled={!canSubmit || isSubmitting}
          className={cn(
            "px-8 py-3 rounded-lg font-medium transition-all inline-flex items-center",
            canSubmit && !isSubmitting
              ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          {isSubmitting ? (
            <>
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              Submitting Order...
            </>
          ) : (
            <>
              <FiCheck className="mr-2" />
              Submit Quick Request
            </>
          )}
        </button>
        
        {!canSubmit && (
          <p className="mt-2 text-sm text-orange-500">
            Please complete the IVR signature before submitting
          </p>
        )}
      </div>
    </div>
  );
}