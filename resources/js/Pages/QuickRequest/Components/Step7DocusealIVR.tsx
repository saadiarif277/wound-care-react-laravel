import React, { useState, useEffect, useMemo } from 'react';
import { CheckCircle, ArrowRight, AlertCircle, FileText, Upload, X, Eye } from 'lucide-react';
import { Button } from '@/Components/Button';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { useManufacturers } from '@/Hooks/useManufacturers';
import api from '@/lib/api';

interface FormData {
  // Core fields
  patient_first_name?: string;
  patient_last_name?: string;
  patient_dob?: string;
  patient_phone?: string;
  patient_email?: string;
  provider_name?: string;
  provider_email?: string;
  selected_products?: Array<{
    product_id: number;
    product?: any;
  }>;
  
  // IVR completion tracking
  docuseal_submission_id?: string;
  episode_id?: string;
  
  // Document uploads
  insurance_card_front?: File;
  insurance_card_back?: File;
  clinical_document?: File;
  
  [key: string]: any;
}

interface Step7Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  products: Array<{
    id: number;
    code: string;
    q_code?: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
  }>;
  errors: Record<string, string>;
  onNext?: () => void;
}

interface DocumentUpload {
  insurance_card_front?: File;
  insurance_card_back?: File;
  clinical_document?: File;
}

/**
 * Modern Step 7 DocuSeal IVR Component - 2025 Edition
 * 
 * Clean, focused implementation for IVR form generation with:
 * - Simple state management
 * - Modern React patterns
 * - Clean error handling
 * - Optional document uploads
 * - Streamlined user experience
 */
export default function Step7DocusealIVR({
  formData,
  updateFormData,
  products,
  errors,
  onNext
}: Step7Props) {
  // Simple state management
  const [uploadedDocs, setUploadedDocs] = useState<DocumentUpload>({});
  const [showDocUpload, setShowDocUpload] = useState(false);
  const [ivrError, setIvrError] = useState<string | null>(null);

  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();

  // Get selected product and manufacturer config
  const selectedProduct = useMemo(() => {
    if (!formData.selected_products?.length) return null;
    const firstProduct = formData.selected_products[0];
    return products.find(p => p.id === firstProduct.product_id) || firstProduct.product;
  }, [formData.selected_products, products]);

  const manufacturerConfig = useMemo(() => {
    if (manufacturersLoading || !selectedProduct?.manufacturer) return null;
    return getManufacturerByName(selectedProduct.manufacturer);
  }, [manufacturersLoading, selectedProduct?.manufacturer, getManufacturerByName]);

  const templateId = manufacturerConfig?.docuseal_template_id;

  // Handle document uploads
  const handleDocumentUpload = async (file: File, type: keyof DocumentUpload) => {
    setUploadedDocs(prev => ({ ...prev, [type]: file }));
    updateFormData({ [type]: file });

    // Process insurance cards with AI if applicable
    if (type.includes('insurance')) {
      try {
        const formData = new FormData();
        formData.append(type, file);

        const response = await api.post('/api/insurance-card/analyze', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (response.success && response.data) {
          const extractedData: any = {};
          if (response.data.patient_first_name) extractedData.patient_first_name = response.data.patient_first_name;
          if (response.data.patient_last_name) extractedData.patient_last_name = response.data.patient_last_name;
          if (response.data.patient_dob) extractedData.patient_dob = response.data.patient_dob;
          if (response.data.patient_member_id) extractedData.patient_member_id = response.data.patient_member_id;
          if (response.data.payer_name) extractedData.primary_insurance_name = response.data.payer_name;
          
          updateFormData(extractedData);
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
      }
    }
  };

  // Handle IVR form completion
  const handleIvrComplete = (data: any) => {
    console.log('✅ IVR form completed:', data);
    
    const submissionId = data.slug || data.submission_id || data.id;
    
    updateFormData({
      docuseal_submission_id: submissionId,
      ivr_completed: true,
      docuseal_completed_at: new Date().toISOString(),
      final_submission_data: data
    });

    setIvrError(null);
  };

  // Handle IVR errors
  const handleIvrError = (error: string) => {
    console.error('❌ IVR form error:', error);
    setIvrError(error);
  };

  // Set NO_IVR_REQUIRED when manufacturer doesn't require signature
  useEffect(() => {
    if (manufacturerConfig && !manufacturerConfig.signature_required && !formData.docuseal_submission_id) {
      updateFormData({ docuseal_submission_id: 'NO_IVR_REQUIRED' });
    }
  }, [manufacturerConfig, formData.docuseal_submission_id, updateFormData]);

  // Loading state
  if (manufacturersLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-4" />
          <p className="text-gray-600">Loading manufacturer configuration...</p>
        </div>
      </div>
    );
  }

  // No product selected
  if (!selectedProduct) {
    return (
      <div className="text-center py-12 text-gray-500">
        <FileText className="w-12 h-12 mx-auto mb-4" />
        <p>Please select a product first</p>
      </div>
    );
  }

  // No IVR required
  if (!templateId) {
    return (
      <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-8 text-center">
        <CheckCircle className="w-12 h-12 text-green-600 dark:text-green-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-green-900 dark:text-green-100 mb-2">
          No IVR Required
        </h3>
        <p className="text-green-700 dark:text-green-300 mb-6">
          {selectedProduct.name} does not require an insurance verification form.
        </p>
        <Button onClick={() => onNext?.()} className="inline-flex items-center gap-2">
          Continue to Final Review
          <ArrowRight className="w-4 h-4" />
        </Button>
      </div>
    );
  }

  const isCompleted = formData.docuseal_submission_id && formData.docuseal_submission_id !== 'NO_IVR_REQUIRED';

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
          Insurance Verification Request (IVR)
        </h2>
        <p className="text-gray-600 dark:text-gray-400">
          Complete the manufacturer's insurance verification form for {selectedProduct.name}
        </p>
      </div>

      {/* Document Upload Section */}
      {!isCompleted && (
        <div className="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-medium text-gray-900 dark:text-white">
              Upload Documents (Optional)
            </h3>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowDocUpload(!showDocUpload)}
            >
              {showDocUpload ? <X className="w-4 h-4" /> : <Upload className="w-4 h-4" />}
            </Button>
          </div>
          
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Upload insurance cards or clinical documents to auto-fill form fields
          </p>

          {showDocUpload && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <DocumentUploadBox
                title="Insurance Card (Front)"
                type="insurance_card_front"
                file={uploadedDocs.insurance_card_front}
                onUpload={(file) => handleDocumentUpload(file, 'insurance_card_front')}
              />
              <DocumentUploadBox
                title="Insurance Card (Back)"
                type="insurance_card_back"
                file={uploadedDocs.insurance_card_back}
                onUpload={(file) => handleDocumentUpload(file, 'insurance_card_back')}
              />
              <DocumentUploadBox
                title="Clinical Notes"
                type="clinical_document"
                file={uploadedDocs.clinical_document}
                onUpload={(file) => handleDocumentUpload(file, 'clinical_document')}
              />
            </div>
          )}
        </div>
      )}

      {/* IVR Form Section */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        {!isCompleted ? (
          <div className="p-6">
            <div className="mb-6">
              <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
                Complete IVR Form
              </h3>
              {formData.episode_id && (
                <div className="inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full mb-4">
                  FHIR Enhanced - Pre-filled with your healthcare data
                </div>
              )}
            </div>

            {ivrError ? (
              <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                <div className="flex items-center gap-3">
                  <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" />
                  <div>
                    <h4 className="font-medium text-red-900 dark:text-red-100">
                      Failed to Load IVR Form
                    </h4>
                    <p className="text-red-700 dark:text-red-300 text-sm mt-1">
                      {ivrError}
                    </p>
                  </div>
                </div>
                <div className="mt-4">
                  <Button 
                    variant="outline" 
                    onClick={() => {
                      setIvrError(null);
                      window.location.reload();
                    }}
                  >
                    Try Again
                  </Button>
                </div>
              </div>
            ) : (
              <DocusealEmbed
                manufacturerId={String(manufacturerConfig?.id || selectedProduct?.manufacturer_id || '')}
                templateId={templateId}
                productCode={selectedProduct?.q_code || selectedProduct?.code || ''}
                documentType="IVR"
                formData={formData}
                episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
                onComplete={handleIvrComplete}
                onError={handleIvrError}
                debug={true}
              />
            )}
          </div>
        ) : (
          // Completion State
          <div className="p-8 text-center">
            <CheckCircle className="w-16 h-16 text-green-600 dark:text-green-400 mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-green-900 dark:text-green-100 mb-2">
              IVR Form Completed Successfully!
            </h3>
            <p className="text-green-700 dark:text-green-300 mb-2">
              Submission ID: {formData.docuseal_submission_id}
            </p>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
              Your insurance verification request has been submitted and saved.
            </p>
            <Button onClick={() => onNext?.()} className="inline-flex items-center gap-2">
              Continue to Final Review
              <ArrowRight className="w-4 h-4" />
            </Button>
          </div>
        )}
      </div>

      {/* Validation Errors */}
      {errors.docuseal && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-red-700 dark:text-red-300">{errors.docuseal}</p>
        </div>
      )}
    </div>
  );
}

// Document Upload Component
interface DocumentUploadBoxProps {
  title: string;
  type: string;
  file?: File;
  onUpload: (file: File) => void;
}

const DocumentUploadBox: React.FC<DocumentUploadBoxProps> = ({ 
  title, 
  file, 
  onUpload 
}) => {
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      onUpload(selectedFile);
    }
  };

  return (
    <div className="relative">
      <label className="block">
        <div className={`
          border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors
          ${file 
            ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' 
            : 'border-gray-300 hover:border-blue-400 dark:border-gray-600 dark:hover:border-blue-500'
          }
        `}>
          {file ? (
            <div className="space-y-2">
              <CheckCircle className="w-8 h-8 text-green-600 dark:text-green-400 mx-auto" />
              <p className="text-sm font-medium text-green-900 dark:text-green-100">
                {title}
              </p>
              <p className="text-xs text-green-700 dark:text-green-300 truncate">
                {file.name}
              </p>
            </div>
          ) : (
            <div className="space-y-2">
              <Upload className="w-8 h-8 text-gray-400 mx-auto" />
              <p className="text-sm font-medium text-gray-900 dark:text-white">
                {title}
              </p>
              <p className="text-xs text-gray-500">
                Click to upload
              </p>
            </div>
          )}
        </div>
        <input
          type="file"
          className="hidden"
          accept="image/*,application/pdf"
          onChange={handleFileChange}
        />
      </label>
    </div>
  );
};
