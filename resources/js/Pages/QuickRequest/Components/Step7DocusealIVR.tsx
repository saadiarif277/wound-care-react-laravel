import React, { useState, useEffect, useMemo, useRef } from 'react';
import { CheckCircle, ArrowRight, AlertCircle, FileText, Upload, X, Eye } from 'lucide-react';
import { Button } from '@/Components/Button';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { EnhancedDocusealEmbed } from '@/Components/QuickRequest/EnhancedDocusealEmbed';
import { useManufacturers } from '@/Hooks/useManufacturers';
import MultiFileUpload from '@/Components/FileUpload/MultiFileUpload';
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

  // Document uploads - Updated to support multiple files
  insurance_card_front?: File;
  insurance_card_back?: File;
  clinical_documents?: Array<{
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    uploadedAt: Date;
  }>;
  demographics_documents?: Array<{
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    uploadedAt: Date;
  }>;

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
  clinical_documents?: Array<{
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    uploadedAt: Date;
  }>;
  demographics_documents?: Array<{
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    uploadedAt: Date;
  }>;
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
  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();
  const [showDocUpload, setShowDocUpload] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);
  const [ivrError, setIvrError] = useState<string | null>(null);
  const [uploadedDocs, setUploadedDocs] = useState<DocumentUpload>({});
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const docusealFormRef = useRef<any>(null);

  // Debug logging
  useEffect(() => {
    console.log('🔍 Step7DocuSealIVR Debug:', {
      formData: formData,
      products: products,
      manufacturers: manufacturers,
      isIvrRequired: formData.ivr_required,
      ivr_bypass_reason: formData.ivr_bypass_reason,
      uploadedDocs: uploadedDocs
    });
  }, [formData, products, manufacturers, uploadedDocs]);

  // Get selected product and manufacturer config
  const selectedProduct = useMemo(() => {
    if (!formData.selected_products?.length) return null;
    const firstProduct = formData.selected_products[0];
    if (!firstProduct) return null;
    return firstProduct.product || products.find(p => p.id === firstProduct.product_id);
  }, [formData.selected_products, products]);

  const manufacturerConfig = useMemo(() => {
    if (manufacturersLoading || !selectedProduct?.manufacturer) return null;
    return getManufacturerByName(selectedProduct.manufacturer);
  }, [manufacturersLoading, selectedProduct?.manufacturer, getManufacturerByName]);

  const templateId = manufacturerConfig?.docuseal_template_id;

  // Check if IVR is required for this order
  const isIvrRequired = useMemo(() => {
    // Check if IVR is explicitly marked as not required
    if (formData.ivr_required === false) {
      return false;
    }

    // Check if IVR was bypassed with a reason
    if (formData.ivr_bypass_reason) {
      return false;
    }

    // Default: IVR is required
    return true;
  }, [formData.ivr_required, formData.ivr_bypass_reason]);

  // Handle clinical documents upload
  const handleClinicalDocumentsChange = (files: Array<{
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    uploadedAt: Date;
  }>) => {
    setUploadedDocs(prev => ({ ...prev, clinical_documents: files }));
    updateFormData({ clinical_documents: files });
  };

  // Handle clinical documents removal
  const handleClinicalDocumentsRemove = (fileId: string) => {
    const updatedFiles = uploadedDocs.clinical_documents?.filter(f => f.id !== fileId) || [];
    setUploadedDocs(prev => ({ ...prev, clinical_documents: updatedFiles }));
    updateFormData({ clinical_documents: updatedFiles });
  };

  // Handle document uploads (legacy support for insurance cards)
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

  // Handle IVR form completion from API
  const handleIvrComplete = (data: any) => {
    console.log('✅ IVR form completed via API:', data);
    setIsSubmitting(true);

    const submissionId = data.slug || data.submission_id || data.id;

    if (!submissionId) {
      console.error('❌ No submission ID received from DocuSeal API');
      setIvrError('Invalid submission response - no submission ID received');
      setIsSubmitting(false);
      return;
    }

    // Update form data with API submission details
    updateFormData({
      docuseal_submission_id: submissionId,
      ivr_completed: true,
      docuseal_completed_at: new Date().toISOString(),
      final_submission_data: data
    });

    console.log('✅ IVR API submission successful:', {
      submissionId,
      timestamp: new Date().toISOString()
    });

    setIvrError(null);
    setIsSubmitting(false);
  };

  // Handle IVR errors
  const handleIvrError = (error: string) => {
    console.error('❌ IVR form error:', error);
    setIvrError(error);
  };

  // Handle Next button click - only proceed if IVR is submitted via API
  const handleNext = () => {
    // Double-check that we have both submission ID and completion flag from API
    if (isCompleted && formData.docuseal_submission_id && formData.ivr_completed) {
      console.log('✅ IVR API submission verified, proceeding to next step');
      onNext?.();
    } else {
      console.warn('⚠️ IVR submission not complete:', {
        isCompleted,
        docuseal_submission_id: formData.docuseal_submission_id,
        ivr_completed: formData.ivr_completed
      });
    }
  };

  // Check if IVR is completed via API submission
  useEffect(() => {
    // IVR is only considered completed when we have a submission ID from the API
    const hasApiSubmission = !!(formData.docuseal_submission_id && formData.ivr_completed);
    setIsCompleted(hasApiSubmission);

    // Debug logging for IVR completion status
    console.log('🔍 IVR Completion Check:', {
      docuseal_submission_id: formData.docuseal_submission_id,
      ivr_completed: formData.ivr_completed,
      isCompleted: hasApiSubmission
    });
  }, [formData.docuseal_submission_id, formData.ivr_completed]);

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
      <div className="text-center py-8">
        <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
          No Product Selected
        </h3>
        <p className="text-gray-600 dark:text-gray-400">
          Please select a product before proceeding to the IVR step.
        </p>
      </div>
    );
  }

  // Handle IVR not required scenario
  if (!isIvrRequired) {
    return (
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
            Insurance Verification Request (IVR)
          </h2>
          <p className="text-gray-600 dark:text-gray-400">
            IVR is not required for this order
          </p>
        </div>

        {/* IVR Not Required Message */}
        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
          <div className="flex items-center gap-3 mb-4">
            <AlertCircle className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            <h3 className="text-lg font-medium text-blue-900 dark:text-blue-100">
              IVR Not Required
            </h3>
          </div>
          <p className="text-blue-700 dark:text-blue-300 text-sm">
            This order does not require an Insurance Verification Request (IVR) from the manufacturer.
            You can still upload clinical documents and supporting files below if needed.
          </p>
          {formData.ivr_bypass_reason && (
            <div className="mt-3 p-3 bg-blue-100 dark:bg-blue-800 rounded-md">
              <p className="text-sm text-blue-800 dark:text-blue-200">
                <strong>Reason:</strong> {formData.ivr_bypass_reason}
              </p>
            </div>
          )}
        </div>



        {/* Next Button for IVR Not Required */}
        <div className="mt-6 flex justify-between items-center">
          <div className="text-sm text-gray-600 dark:text-gray-400">
            <span className="text-blue-600 dark:text-blue-400">
              ℹ️ IVR not required - proceeding to Order Form
            </span>
          </div>
          <Button
            onClick={() => onNext?.()}
            className="inline-flex items-center gap-2"
          >
            Next
            <ArrowRight className="w-4 h-4" />
          </Button>
        </div>

        {/* Continue Button */}
        <div className="flex justify-end">
          <Button onClick={() => onNext?.()} className="inline-flex items-center gap-2">
            Continue to Order Form
            <ArrowRight className="w-4 h-4" />
          </Button>
        </div>
      </div>
    );
  }

  // if (!templateId) {
  //   return (
  //     <div className="text-center py-8">
  //       <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
  //       <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
  //         No IVR Template Found
  //       </h3>
  //       <p className="text-gray-600 dark:text-gray-400">
  //         No IVR template is configured for {selectedProduct.name}. Please contact support.
  //       </p>
  //     </div>
  //   );
  // }

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
                    variant="ghost"
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
              <>
                <div ref={docusealFormRef}>
                  {/* Enhanced Field Mapping Component - Uncomment to test */}
                  {/* <EnhancedDocusealEmbed
                    manufacturerId={String(manufacturerConfig?.id || '')}
                    templateId={templateId}
                    productCode={selectedProduct?.q_code || selectedProduct?.code || ''}
                    documentType="IVR"
                    formData={formData}
                    episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
                    onComplete={handleIvrComplete}
                    onError={handleIvrError}
                    debug={true}
                    useFrontendMapping={true}
                    useBackendEnhancedMapping={true}
                  /> */}

                  {/* Original Component (currently active) */}
                  <DocusealEmbed
                    manufacturerId={String(manufacturerConfig?.id || '')}
                    templateId={templateId}
                    productCode={selectedProduct?.q_code || selectedProduct?.code || ''}
                    documentType="IVR"
                    formData={formData}
                    episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
                    onComplete={handleIvrComplete}
                    onError={handleIvrError}
                    debug={true}
                  />
                </div>

                {/* Next Button - Only enabled when IVR is completed */}
                <div className="mt-6 flex justify-between items-center">
                  <div className="flex-1">
                    {isSubmitting ? (
                      <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                        <div className="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                        <span>🔄 Processing IVR submission...</span>
                      </div>
                    ) : isCompleted ? (
                      <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                        <CheckCircle className="w-4 h-4" />
                        <span>✅ IVR form submitted successfully via API</span>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-lg border border-red-200 dark:border-red-800">
                        <AlertCircle className="w-4 h-4" />
                        <span className="font-medium">⚠️ IVR submission is required - Please complete and submit the form above</span>
                      </div>
                    )}
                  </div>

                </div>
              </>
            )}
          </div>
        ) : (
          // Completion State
          <div className="p-8 text-center">
            <CheckCircle className="w-16 h-16 text-green-600 dark:text-green-400 mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-green-900 dark:text-green-100 mb-2">
              IVR Form Submitted Successfully!
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


      <div className="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-6 border-2 border-blue-200 dark:border-blue-800">
        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
          📁 Upload Documents (Optional)
        </h3>

        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Upload insurance cards or clinical documents to auto-fill form fields
        </p>

        <div className="space-y-6">
          {/* Clinical Documents Upload */}
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
            <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">
              🏥 Clinical Documents
            </h4>
            <MultiFileUpload
              title="Upload Clinical Documents"
              description="Upload clinical notes, wound photos, and other medical documentation"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              maxFiles={10}
              maxFileSize={10 * 1024 * 1024} // 10MB
              onFilesChange={(files) => {
                console.log('📁 Clinical documents uploaded:', files);
                handleClinicalDocumentsChange(files);
              }}
              onFileRemove={(fileId) => {
                console.log('🗑️ Clinical document removed:', fileId);
                handleClinicalDocumentsRemove(fileId);
              }}
              showPreview={true}
            />
          </div>

          {/* Demographics and Supporting Documents Upload */}
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
            <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">
              👤 Demographics & Supporting Documents
            </h4>
            <MultiFileUpload
              title="Upload Demographics & Supporting Documents"
              description="Upload patient demographics, face sheets, ID documents, and other supporting files"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              maxFiles={15}
              maxFileSize={10 * 1024 * 1024} // 10MB
              onFilesChange={(files) => {
                console.log('📁 Demographics and supporting documents uploaded:', files);
                // Handle demographics and supporting documents
                setUploadedDocs(prev => ({ ...prev, demographics_documents: files }));
                updateFormData({ demographics_documents: files });
              }}
              onFileRemove={(fileId) => {
                console.log('🗑️ Demographics file removed:', fileId);
                // Handle file removal
                const updatedFiles = uploadedDocs.demographics_documents?.filter(f => f.id !== fileId) || [];
                setUploadedDocs(prev => ({ ...prev, demographics_documents: updatedFiles }));
                updateFormData({ demographics_documents: updatedFiles });
              }}
              showPreview={true}
            />
          </div>
        </div>
      </div>
    </div>
  );
}

// Document Upload Component (Legacy support for insurance cards)
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
