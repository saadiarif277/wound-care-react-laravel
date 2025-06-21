import React, { useState, useRef, useEffect } from 'react';
import { FiInfo, FiUploadCloud, FiFile, FiX, FiUser, FiMapPin, FiLoader } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import axios from 'axios';

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
  onEpisodeCreated?: (episodeData: any) => void;
}

export default function Step1CreateEpisode({
  formData,
  updateFormData,
  providers,
  facilities,
  currentUser,
  errors,
  onEpisodeCreated
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

  const [uploadedFiles, setUploadedFiles] = useState<File[]>([]);
  const [processingDocuments, setProcessingDocuments] = useState(false);
  const [documentProcessingStatus, setDocumentProcessingStatus] = useState<string>('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    if (event.target.files) {
      const newFiles = Array.from(event.target.files);
      const allFiles = [...uploadedFiles, ...newFiles];
      setUploadedFiles(allFiles);
      updateFormData({ patient_documents: allFiles });

      // Auto-process documents if we have all required context
      if (formData.provider_id && formData.facility_id && formData.patient_name && allFiles.length > 0) {
        processDocumentsAndCreateEpisode(allFiles);
      }
    }
  };

  const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    const files = Array.from(event.dataTransfer.files);
    const allFiles = [...uploadedFiles, ...files];
    setUploadedFiles(allFiles);
    updateFormData({ patient_documents: allFiles });

    // Auto-process documents if we have all required context
    if (formData.provider_id && formData.facility_id && formData.patient_name && allFiles.length > 0) {
      processDocumentsAndCreateEpisode(allFiles);
    }
  };

  const handleDragOver = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
  };

  const removeFile = (index: number) => {
    const newFiles = uploadedFiles.filter((_, i) => i !== index);
    setUploadedFiles(newFiles);
    updateFormData({ patient_documents: newFiles });
  };

  const processDocumentsAndCreateEpisode = async (files: File[]) => {
    if (!formData.provider_id || !formData.facility_id || !formData.patient_name) {
      return; // Wait for all required fields
    }

    setProcessingDocuments(true);
    setDocumentProcessingStatus('Creating episode and processing documents...');

    try {
      const formDataToSend = new FormData();
      formDataToSend.append('provider_id', formData.provider_id.toString());
      formDataToSend.append('facility_id', formData.facility_id.toString());
      formDataToSend.append('patient_name', formData.patient_name);
      formDataToSend.append('request_type', formData.request_type || 'new_request');

      files.forEach((file, index) => {
        formDataToSend.append(`documents[${index}]`, file);
      });

      const response = await axios.post('/api/quick-request/create-episode-with-documents', formDataToSend, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        const { episode_id, patient_fhir_id, extracted_data } = response.data;

        // Update form data with extracted information
        updateFormData({
          episode_id,
          patient_fhir_id,
          ...extracted_data, // This will include patient demographics, insurance info, etc.
        });

        // Notify parent component
        if (onEpisodeCreated) {
          onEpisodeCreated(response.data);
        }

        setDocumentProcessingStatus('✅ Documents processed successfully! Form has been pre-filled.');
      } else {
        setDocumentProcessingStatus('⚠️ Document processing failed. You can continue manually.');
      }
    } catch (error) {
      console.error('Error processing documents:', error);
      setDocumentProcessingStatus('⚠️ Document processing failed. You can continue manually.');
    } finally {
      setProcessingDocuments(false);
    }
  };

  // Auto-process when all required fields are filled
  React.useEffect(() => {
    if (formData.provider_id && formData.facility_id && formData.patient_name && uploadedFiles.length > 0 && !processingDocuments) {
      processDocumentsAndCreateEpisode(uploadedFiles);
    }
  }, [formData.provider_id, formData.facility_id, formData.patient_name]);

  // Pre-select provider if user is a provider
  React.useEffect(() => {
    if (currentUser.role === 'provider' && !formData.provider_id) {
      updateFormData({ provider_id: currentUser.id });
    }
  }, [currentUser, formData.provider_id, updateFormData]);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Create New Episode
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Set up the episode context and upload documents to auto-fill the form
        </p>
      </div>

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
          value={formData.request_type || 'new_request'}
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
          <FiUser className="inline mr-2" />
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
          <FiMapPin className="inline mr-2" />
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

      {/* Patient Name */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
          Patient Name <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          className={cn(
            "w-full p-3 rounded-lg border transition-all",
            theme === 'dark'
              ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
              : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
            errors.patient_name && 'border-red-500'
          )}
          value={formData.patient_name || ''}
          onChange={(e) => updateFormData({ patient_name: e.target.value })}
          placeholder="Enter patient's full name"
        />
        {errors.patient_name && (
          <p className="mt-1 text-sm text-red-500">{errors.patient_name}</p>
        )}
      </div>

      {/* AI-Powered Document Upload */}
      <div>
        <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
          ⚡️ Upload Documents (Optional - Auto-fills Form)
        </label>
        <div
          className={cn(
            "relative border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer",
            theme === 'dark' ? 'border-gray-600 hover:border-blue-500' : 'border-gray-300 hover:border-blue-500',
            errors.patient_documents && 'border-red-500',
            processingDocuments && 'border-blue-500 bg-blue-50/5'
          )}
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onClick={() => fileInputRef.current?.click()}
        >
          <input
            ref={fileInputRef}
            type="file"
            multiple
            className="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer"
            onChange={handleFileChange}
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
          />

          {processingDocuments ? (
            <div className="flex flex-col items-center">
              <FiLoader className="h-12 w-12 text-blue-500 animate-spin mb-4" />
              <p className={cn("text-lg font-medium", t.text.primary)}>Processing Documents...</p>
              <p className={cn("text-sm", t.text.secondary)}>{documentProcessingStatus}</p>
            </div>
          ) : (
            <>
              <FiUploadCloud className="mx-auto h-16 w-16 text-gray-400 mb-4" />
              <p className={cn("text-lg font-medium mb-2", t.text.primary)}>
                Drop files here or click to browse
              </p>
              <p className={cn("text-sm mb-2", t.text.secondary)}>
                Face Sheet, Demographics, Clinical Notes, Insurance Cards
              </p>
              <p className={cn("text-xs", t.text.tertiary)}>
                PDF, DOCX, PNG, JPG accepted • AI will extract data automatically
              </p>
            </>
          )}
        </div>

        {documentProcessingStatus && !processingDocuments && (
          <div className={cn("mt-2 p-3 rounded-lg", theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50')}>
            <p className={cn("text-sm", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
              {documentProcessingStatus}
            </p>
          </div>
        )}

        {errors.patient_documents && (
          <p className="mt-1 text-sm text-red-500">{errors.patient_documents}</p>
        )}

        {uploadedFiles.length > 0 && (
          <div className="mt-4 space-y-2">
            <h4 className={cn("text-sm font-medium", t.text.secondary)}>Uploaded Files:</h4>
            {uploadedFiles.map((file, index) => (
              <div key={index} className={cn("flex items-center justify-between p-3 rounded-lg", theme === 'dark' ? 'bg-gray-800 border border-gray-700' : 'bg-gray-50 border border-gray-200')}>
                <div className="flex items-center">
                  <FiFile className="h-5 w-5 mr-3 text-gray-400" />
                  <div>
                    <span className={cn("text-sm font-medium", t.text.primary)}>{file.name}</span>
                    <p className={cn("text-xs", t.text.secondary)}>
                      {(file.size / 1024 / 1024).toFixed(1)} MB
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    removeFile(index);
                  }}
                  className="p-1 text-red-500 hover:text-red-400"
                  disabled={processingDocuments}
                >
                  <FiX className="h-5 w-5" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

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
              How the Episode-Centric Workflow Works
            </h4>
            <p className={cn(
              "mt-1 text-sm",
              theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
            )}>
              1. Select provider and facility • 2. Enter patient name • 3. Upload documents
              • 4. AI extracts data and creates FHIR patient record • 5. Form auto-fills
              • 6. You verify and complete remaining details
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
