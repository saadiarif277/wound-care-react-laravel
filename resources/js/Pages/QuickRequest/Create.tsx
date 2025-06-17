import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiArrowLeft, FiArrowRight, FiCheck } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import Step1PatientInfo from './Components/Step1PatientInfoNew';
import Step2ProductSelection from './Components/Step2ProductSelection';
import Step3Documentation from './Components/Step3Documentation';
import Step4Confirmation from './Components/Step4Confirmation';

interface QuickRequestFormData {
  // Step 1: Patient Information
  insurance_card_front?: File | null;
  insurance_card_back?: File | null;
  insurance_card_auto_filled?: boolean;
  patient_first_name: string;
  patient_last_name: string;
  patient_dob: string;
  patient_gender?: 'male' | 'female' | 'other' | 'unknown';
  patient_member_id?: string;
  patient_address_line1?: string;
  patient_address_line2?: string;
  patient_city?: string;
  patient_state?: string;
  patient_zip?: string;
  patient_phone?: string;
  caregiver_name?: string;
  caregiver_relationship?: string;
  caregiver_phone?: string;
  
  // Step 2: Product Selection & Manufacturer Fields
  product_id: number | null;
  product_code?: string;
  product_name?: string;
  manufacturer?: string;
  size?: string;
  quantity: number;
  manufacturer_fields?: Record<string, any>;
  
  // Step 3: Documentation & Authorization
  face_sheet?: File | null;
  clinical_notes?: File | null;
  wound_photo?: File | null;
  failed_conservative_treatment: boolean;
  information_accurate: boolean;
  medical_necessity_established: boolean;
  maintain_documentation: boolean;
  authorize_prior_auth: boolean;
  provider_signature?: string;
  provider_name?: string;
  signature_date?: string;
  provider_npi?: string;
  verbal_order?: {
    received_from: string;
    date: string;
    documented_by: string;
  };
  
  // Additional fields
  facility_id: number | null;
  payer_name: string;
  payer_id: string;
  expected_service_date: string;
  wound_type: string;
  shipping_speed?: string;
  place_of_service?: string;
  insurance_type?: string;
  is_patient_subscriber?: boolean;
}

interface Props {
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  providers: Array<{
    id: number;
    name: string;
    npi?: string;
  }>;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    sizes?: string[];
  }>;
  woundTypes: Record<string, string>;
  currentUser: {
    id: number;
    name: string;
    npi?: string;
  };
}

function QuickRequestCreate({ 
  facilities, 
  providers, 
  products, 
  woundTypes,
  currentUser 
}: Props) {
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
  
  const [currentStep, setCurrentStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  
  const [formData, setFormData] = useState<QuickRequestFormData>({
    // Initialize form data
    patient_first_name: '',
    patient_last_name: '',
    patient_dob: '',
    quantity: 1,
    product_id: null,
    facility_id: null,
    payer_name: '',
    payer_id: '',
    expected_service_date: '',
    wound_type: '',
    failed_conservative_treatment: false,
    information_accurate: false,
    medical_necessity_established: false,
    maintain_documentation: false,
    authorize_prior_auth: false,
    provider_npi: currentUser.npi || '',
    is_patient_subscriber: true, // Default to true
  });

  const updateFormData = (updates: Partial<QuickRequestFormData>) => {
    setFormData(prev => ({ ...prev, ...updates }));
  };

  const steps = [
    { number: 1, title: 'Patient Information', icon: '📸' },
    { number: 2, title: 'Product Selection', icon: '📦' },
    { number: 3, title: 'Documentation', icon: '📄' },
    { number: 4, title: 'Confirmation', icon: '✅' },
  ];

  const handleNext = () => {
    // Validate current step
    const stepErrors = validateStep(currentStep);
    if (Object.keys(stepErrors).length > 0) {
      setErrors(stepErrors);
      return;
    }
    setErrors({});
    setCurrentStep(prev => Math.min(prev + 1, 4));
  };

  const handlePrevious = () => {
    setCurrentStep(prev => Math.max(prev - 1, 1));
  };

  const validateStep = (step: number): Record<string, string> => {
    const errors: Record<string, string> = {};
    
    switch (step) {
      case 1:
        if (!formData.patient_first_name) errors.patient_first_name = 'First name is required';
        if (!formData.patient_last_name) errors.patient_last_name = 'Last name is required';
        if (!formData.patient_dob) errors.patient_dob = 'Date of birth is required';
        if (!formData.payer_name) errors.payer_name = 'Payer name is required';
        if (!formData.expected_service_date) errors.expected_service_date = 'Expected service date is required';
        break;
      case 2:
        if (!formData.product_id) errors.product_id = 'Product selection is required';
        if (!formData.size) errors.size = 'Size is required';
        // Validate manufacturer-specific required fields
        if (formData.manufacturer_fields) {
          // TODO: Add manufacturer-specific validation
        }
        break;
      case 3:
        if (!formData.failed_conservative_treatment) errors.attestation = 'All clinical attestations must be confirmed';
        if (!formData.information_accurate) errors.attestation = 'All clinical attestations must be confirmed';
        if (!formData.medical_necessity_established) errors.attestation = 'All clinical attestations must be confirmed';
        if (!formData.maintain_documentation) errors.attestation = 'All clinical attestations must be confirmed';
        break;
    }
    
    return errors;
  };

  const handleSubmit = async () => {
    const allErrors = validateStep(3);
    if (Object.keys(allErrors).length > 0) {
      setErrors(allErrors);
      return;
    }

    setIsSubmitting(true);
    try {
      // Create FormData for file uploads
      const submitData = new FormData();
      
      // Add all form fields
      Object.entries(formData).forEach(([key, value]) => {
        if (value instanceof File) {
          submitData.append(key, value);
        } else if (value !== null && value !== undefined) {
          submitData.append(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
        }
      });

      // Submit the quick request
      router.post('/quick-requests', submitData, {
        onSuccess: () => {
          // Redirect to confirmation or order page
        },
        onError: (errors) => {
          setErrors(errors);
        },
      });
    } catch (error) {
      console.error('Error submitting quick request:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <MainLayout>
      <Head title="Quick Request" />
      
      <div className="min-h-screen">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className={cn("text-3xl font-bold", t.text.primary)}>
              Quick Request
            </h1>
            <p className={cn("mt-2", t.text.secondary)}>
              Fast-track your product orders with our streamlined process
            </p>
          </div>

          {/* Progress Steps */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              {steps.map((step, index) => (
                <div key={step.number} className="flex-1 flex items-center">
                  <div className={cn(
                    "flex items-center justify-center w-10 h-10 rounded-full text-sm font-medium",
                    currentStep === step.number
                      ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
                      : currentStep > step.number
                      ? "bg-green-500 text-white"
                      : theme === 'dark' ? "bg-gray-700 text-gray-400" : "bg-gray-200 text-gray-500"
                  )}>
                    {currentStep > step.number ? <FiCheck /> : step.icon}
                  </div>
                  <div className="ml-2 flex-1">
                    <p className={cn(
                      "text-sm font-medium",
                      currentStep === step.number ? t.text.primary : t.text.secondary
                    )}>
                      {step.title}
                    </p>
                  </div>
                  {index < steps.length - 1 && (
                    <div className={cn(
                      "flex-1 h-0.5 mx-4",
                      currentStep > step.number
                        ? "bg-green-500"
                        : theme === 'dark' ? "bg-gray-700" : "bg-gray-200"
                    )} />
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Step Content */}
          <div className={cn("shadow-xl rounded-2xl p-8", t.glass.card)}>
            {currentStep === 1 && (
              <Step1PatientInfo
                formData={formData}
                updateFormData={updateFormData}
                facilities={facilities}
                woundTypes={woundTypes}
                errors={errors}
              />
            )}
            
            {currentStep === 2 && (
              <Step2ProductSelection
                formData={formData}
                updateFormData={updateFormData}
                products={products}
                errors={errors}
                facilities={facilities}
                woundTypes={woundTypes}
                userRole={'provider'}
              />
            )}
            
            {currentStep === 3 && (
              <Step3Documentation
                formData={formData}
                updateFormData={updateFormData}
                providers={providers}
                currentUser={currentUser}
                errors={errors}
              />
            )}
            
            {currentStep === 4 && (
              <Step4Confirmation
                formData={formData}
                products={products}
                facilities={facilities}
                onSubmit={handleSubmit}
                isSubmitting={isSubmitting}
              />
            )}
          </div>

          {/* Navigation Buttons */}
          <div className="mt-8 flex justify-between">
            <button
              onClick={handlePrevious}
              disabled={currentStep === 1}
              className={cn(
                "flex items-center px-6 py-3 rounded-lg font-medium transition-all",
                currentStep === 1
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:shadow-lg",
                t.button.secondary
              )}
            >
              <FiArrowLeft className="mr-2" />
              Previous
            </button>

            {currentStep < 4 ? (
              <button
                onClick={handleNext}
                className={cn(
                  "flex items-center px-6 py-3 rounded-lg font-medium transition-all hover:shadow-lg",
                  "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
                )}
              >
                Next
                <FiArrowRight className="ml-2" />
              </button>
            ) : (
              <button
                onClick={handleSubmit}
                disabled={isSubmitting}
                className={cn(
                  "flex items-center px-6 py-3 rounded-lg font-medium transition-all",
                  isSubmitting
                    ? "opacity-50 cursor-not-allowed"
                    : "hover:shadow-lg",
                  "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
                )}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Order'}
                <FiCheck className="ml-2" />
              </button>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

export default QuickRequestCreate;