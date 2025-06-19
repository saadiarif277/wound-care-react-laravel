import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiArrowLeft, FiArrowRight, FiCheck, FiAlertCircle, FiClock, FiUser, FiPackage, FiCreditCard, FiActivity, FiShoppingCart, FiHelpCircle, FiFileText } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import Step1ContextRequest from './Components/Step1ContextRequest';
import Step2PatientShipping from './Components/Step2PatientShipping';
import Step3Insurance from './Components/Step3Insurance';
import Step4ClinicalBilling from './Components/Step4ClinicalBilling';
import Step5ProductSelection from './Components/Step5ProductSelection';
import Step6ManufacturerQuestions from './Components/Step6ManufacturerQuestions';
import Step7DocuSealIVR from './Components/Step7DocuSealIVR';
import { getManufacturerByProduct } from './manufacturerFields';

interface QuickRequestFormData {
  // Context & Request Type
  request_type: 'new_request' | 'reverification' | 'additional_applications';
  provider_id: number | null;
  facility_id: number | null;
  sales_rep_id?: string;
  
  // Patient Information
  insurance_card_front?: File | null;
  insurance_card_back?: File | null;
  insurance_card_auto_filled?: boolean;
  patient_first_name: string;
  patient_last_name: string;
  patient_dob: string;
  patient_gender: 'male' | 'female' | 'other' | 'unknown';
  patient_member_id?: string;
  patient_address_line1?: string;
  patient_address_line2?: string;
  patient_city?: string;
  patient_state?: string;
  patient_zip?: string;
  patient_phone?: string;
  patient_email?: string;
  patient_is_subscriber: boolean;
  
  // Caregiver (if not subscriber)
  caregiver_name?: string;
  caregiver_relationship?: string;
  caregiver_phone?: string;
  
  // Service & Shipping
  expected_service_date: string;
  shipping_speed: string;
  delivery_date?: string;
  
  // Primary Insurance
  primary_insurance_name: string;
  primary_member_id: string;
  primary_payer_phone?: string;
  primary_plan_type: string;
  
  // Secondary Insurance
  has_secondary_insurance: boolean;
  secondary_insurance_name?: string;
  secondary_member_id?: string;
  secondary_subscriber_name?: string;
  secondary_subscriber_dob?: string;
  secondary_payer_phone?: string;
  secondary_plan_type?: string;
  
  // Prior Authorization
  prior_auth_permission: boolean;
  
  // Clinical Information
  wound_types: string[];
  wound_other_specify?: string;
  wound_location: string;
  wound_location_details?: string;
  yellow_diagnosis_code?: string;
  orange_diagnosis_code?: string;
  wound_size_length: string;
  wound_size_width: string;
  wound_size_depth: string;
  wound_duration?: string;
  previous_treatments?: string;
  
  // Procedure Information
  application_cpt_codes: string[];
  prior_applications?: string;
  anticipated_applications?: string;
  
  // Billing Status
  place_of_service: string;
  medicare_part_b_authorized?: boolean;
  snf_days?: string;
  hospice_status?: boolean;
  part_a_status?: boolean;
  global_period_status?: boolean;
  global_period_cpt?: string;
  global_period_surgery_date?: string;
  
  // Product Selection
  selected_product?: string;
  selected_products?: Array<{
    product_id: number;
    quantity: number;
    size?: string;
    product?: any;
  }>;
  order_items: Array<{
    id: number;
    product_code: string;
    size: string;
    quantity: number;
    unit_price: number;
    total_price: number;
  }>;
  
  // Documentation & Authorization
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
  
  // Manufacturer Fields
  manufacturer_fields?: Record<string, any>;
  
  // DocuSeal
  docuseal_submission_id?: string;
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
    credentials?: string;
    npi?: string;
  }>;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    sizes?: string[];
    price_per_sq_cm?: number;
  }>;
  woundTypes?: Record<string, string>;
  insuranceCarriers?: string[];
  diagnosisCodes?: {
    yellow: Array<{ code: string; description: string }>;
    orange: Array<{ code: string; description: string }>;
  };
  currentUser: {
    id: number;
    name: string;
    npi?: string;
    role?: string;
  };
  providerProducts?: Record<string, string[]>; // provider ID to product codes mapping
}

function QuickRequestCreateNew({ 
  facilities = [], 
  providers = [], 
  products = [], 
  woundTypes = {},
  insuranceCarriers = [],
  diagnosisCodes,
  currentUser,
  providerProducts = {}
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
  
  const [currentSection, setCurrentSection] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  
  const [formData, setFormData] = useState<QuickRequestFormData>({
    // Initialize with defaults
    request_type: 'new_request',
    provider_id: currentUser.role === 'provider' ? currentUser.id : null,
    facility_id: null,
    sales_rep_id: currentUser.role === 'sales_rep' ? `AUTO-${currentUser.id}` : undefined,
    patient_first_name: '',
    patient_last_name: '',
    patient_dob: '',
    patient_gender: 'unknown',
    patient_is_subscriber: true,
    primary_insurance_name: '',
    primary_member_id: '',
    primary_plan_type: 'ffs',
    has_secondary_insurance: false,
    prior_auth_permission: true,
    wound_types: [],
    wound_location: '',
    wound_size_length: '',
    wound_size_width: '',
    wound_size_depth: '',
    application_cpt_codes: [],
    place_of_service: '11',
    shipping_speed: 'standard_next_day',
    expected_service_date: '',
    order_items: [],
    failed_conservative_treatment: false,
    information_accurate: false,
    medical_necessity_established: false,
    maintain_documentation: false,
    authorize_prior_auth: false,
    provider_npi: currentUser.npi || '',
    selected_products: [],
    manufacturer_fields: {},
    docuseal_submission_id: '',
  });

  const updateFormData = (updates: Partial<QuickRequestFormData>) => {
    setFormData(prev => ({ ...prev, ...updates }));
  };

  const sections = [
    { title: 'Context & Request', icon: FiUser, estimatedTime: '15 seconds' },
    { title: 'Patient & Shipping', icon: FiPackage, estimatedTime: '25 seconds' },
    { title: 'Insurance', icon: FiCreditCard, estimatedTime: '25 seconds' },
    { title: 'Clinical & Billing', icon: FiActivity, estimatedTime: '20 seconds' },
    { title: 'Product Selection', icon: FiShoppingCart, estimatedTime: '15 seconds' },
    { title: 'Manufacturer Questions', icon: FiHelpCircle, estimatedTime: '10 seconds' },
    { title: 'Electronic Signature', icon: FiFileText, estimatedTime: '30 seconds' }
  ];

  const handleNext = () => {
    // Validate current section
    const sectionErrors = validateSection(currentSection);
    if (Object.keys(sectionErrors).length > 0) {
      setErrors(sectionErrors);
      // Scroll to top to show errors
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    setErrors({});
    setCurrentSection(prev => Math.min(prev + 1, sections.length - 1));
  };

  const handlePrevious = () => {
    setCurrentSection(prev => Math.max(prev - 1, 0));
    setErrors({});
  };

  const validateSection = (section: number): Record<string, string> => {
    const errors: Record<string, string> = {};
    
    switch (section) {
      case 0: // Context & Request
        if (!formData.provider_id) errors.provider_id = 'Provider selection is required';
        if (!formData.facility_id) errors.facility_id = 'Facility selection is required';
        break;
        
      case 1: // Patient & Shipping
        if (!formData.patient_first_name) errors.patient_first_name = 'First name is required';
        if (!formData.patient_last_name) errors.patient_last_name = 'Last name is required';
        if (!formData.patient_dob) errors.patient_dob = 'Date of birth is required';
        if (!formData.expected_service_date) errors.expected_service_date = 'Service date is required';
        if (!formData.shipping_speed) errors.shipping_speed = 'Shipping speed is required';
        
        // If patient is not subscriber, validate caregiver info
        if (!formData.patient_is_subscriber) {
          if (!formData.caregiver_name) errors.caregiver_name = 'Caregiver name is required';
          if (!formData.caregiver_relationship) errors.caregiver_relationship = 'Caregiver relationship is required';
        }
        break;
        
      case 2: // Insurance
        if (!formData.primary_insurance_name) errors.primary_insurance_name = 'Primary insurance is required';
        if (!formData.primary_member_id) errors.primary_member_id = 'Member ID is required';
        if (!formData.primary_plan_type) errors.primary_plan_type = 'Plan type is required';
        
        if (formData.has_secondary_insurance) {
          if (!formData.secondary_insurance_name) errors.secondary_insurance = 'Secondary insurance name is required';
          if (!formData.secondary_member_id) errors.secondary_insurance = 'Secondary member ID is required';
        }
        break;
        
      case 3: // Clinical & Billing
        if (!formData.wound_types.length) errors.wound_types = 'At least one wound type must be selected';
        if (formData.wound_types.includes('other') && !formData.wound_other_specify) {
          errors.wound_other_specify = 'Please specify the other wound type';
        }
        if (!formData.wound_location) errors.wound_location = 'Wound location is required';
        if (!formData.wound_size_length) errors.wound_size = 'Wound length is required';
        if (!formData.wound_size_width) errors.wound_size = 'Wound width is required';
        if (!formData.application_cpt_codes.length) errors.cpt_codes = 'At least one CPT code must be selected';
        if (!formData.place_of_service) errors.place_of_service = 'Place of service is required';
        
        // Validate diagnosis codes for specific wound types
        if (formData.wound_types.includes('diabetic_foot_ulcer')) {
          if (!formData.yellow_diagnosis_code) errors.yellow_diagnosis = 'Yellow (diabetes) diagnosis code is required for DFU';
          if (!formData.orange_diagnosis_code) errors.orange_diagnosis = 'Orange (chronic ulcer) diagnosis code is required for DFU';
        }
        break;
        
      case 4: // Product Selection
        if (!formData.selected_products || formData.selected_products.length === 0) {
          errors.products = 'Product selection is required';
        }
        break;
        
      case 5: // Manufacturer Questions
        // Validate manufacturer-specific fields if required
        if (formData.selected_products && formData.selected_products.length > 0) {
          const selectedProductId = formData.selected_products[0].product_id;
          const selectedProduct = products.find(p => p.id === selectedProductId);
          if (selectedProduct) {
            const manufacturerConfig = getManufacturerByProduct(selectedProduct.name);
            if (manufacturerConfig) {
              manufacturerConfig.fields.forEach(field => {
                if (field.required) {
                  const fieldValue = formData.manufacturer_fields?.[field.name];
                  if (!fieldValue || (typeof fieldValue === 'string' && fieldValue.trim() === '')) {
                    errors[`manufacturer_${field.name}`] = `${field.label} is required`;
                  }
                }
              });
            }
          }
        }
        break;
        
      case 6: // DocuSeal IVR
        // Check if IVR is required and completed
        if (formData.selected_products && formData.selected_products.length > 0) {
          const selectedProductId = formData.selected_products[0].product_id;
          const selectedProduct = products.find(p => p.id === selectedProductId);
          if (selectedProduct) {
            const manufacturerConfig = getManufacturerByProduct(selectedProduct.name);
            if (manufacturerConfig?.signatureRequired && !formData.docuseal_submission_id) {
              errors.docuseal = 'Electronic signature is required for this product';
            }
          }
        }
        break;
    }
    
    return errors;
  };

  const handleSubmit = async () => {
    // Validate all sections
    let allErrors: Record<string, string> = {};
    for (let i = 0; i <= 6; i++) {
      const sectionErrors = validateSection(i);
      allErrors = { ...allErrors, ...sectionErrors };
    }
    
    if (Object.keys(allErrors).length > 0) {
      setErrors(allErrors);
      alert('Please fix all errors before submitting');
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
          if (Array.isArray(value) || typeof value === 'object') {
            submitData.append(key, JSON.stringify(value));
          } else {
            submitData.append(key, String(value));
          }
        }
      });

      // Submit the quick request
      router.post('/quick-requests', submitData, {
        onSuccess: () => {
          // Redirect handled by server
        },
        onError: (errors) => {
          console.error('Form submission errors:', errors);
          setErrors(errors as Record<string, string>);
        },
      });
    } catch (error) {
      console.error('Error submitting quick request:', error);
      alert('An error occurred while submitting the request');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Calculate delivery date based on shipping speed
  useEffect(() => {
    if (formData.expected_service_date && formData.shipping_speed) {
      const serviceDate = new Date(formData.expected_service_date);
      const today = new Date();
      const daysToAdd = formData.shipping_speed === 'standard_2_day' ? 2 : 1;
      
      const deliveryDate = new Date(today);
      deliveryDate.setDate(deliveryDate.getDate() + daysToAdd);
      
      updateFormData({ delivery_date: deliveryDate.toISOString().split('T')[0] });
    }
  }, [formData.expected_service_date, formData.shipping_speed]);

  // Calculate wound area
  const woundArea = formData.wound_size_length && formData.wound_size_width 
    ? (parseFloat(formData.wound_size_length) * parseFloat(formData.wound_size_width)).toFixed(2)
    : '0';

  return (
    <MainLayout>
      <Head title="Quick Request - Enhanced Flow" />
      
      <div className="min-h-screen">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className={cn("text-3xl font-bold", t.text.primary)}>
              MSC Quick Request
            </h1>
            <p className={cn("mt-2", t.text.secondary)}>
              Complete wound care product ordering in 90 seconds
            </p>
          </div>

          {/* Progress Bar */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              {sections.map((section, index) => {
                const Icon = section.icon;
                return (
                  <div 
                    key={index}
                    className={`flex items-center ${index < sections.length - 1 ? 'flex-1' : ''}`}
                  >
                    <div className={cn(
                      "flex flex-col items-center",
                      index <= currentSection ? t.text.primary : t.text.secondary
                    )}>
                      <div className={cn(
                        "rounded-full p-3",
                        index <= currentSection 
                          ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
                          : theme === 'dark' ? "bg-gray-700" : "bg-gray-100"
                      )}>
                        {index < currentSection ? <FiCheck className="h-6 w-6" /> : <Icon className="h-6 w-6" />}
                      </div>
                      <span className="text-xs mt-2 text-center max-w-[100px]">{section.title}</span>
                      <span className={cn("text-xs flex items-center mt-1", t.text.secondary)}>
                        <FiClock className="h-3 w-3 mr-1" />
                        {section.estimatedTime}
                      </span>
                    </div>
                    {index < sections.length - 1 && (
                      <div className={cn(
                        "flex-1 h-0.5 mx-2",
                        index < currentSection 
                          ? "bg-gradient-to-r from-[#1925c3] to-[#c71719]" 
                          : theme === 'dark' ? "bg-gray-700" : "bg-gray-300"
                      )} />
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Validation Error Summary */}
          {Object.keys(errors).length > 0 && (
            <div className={cn(
              "mb-6 p-4 rounded-lg border",
              theme === 'dark' 
                ? 'bg-red-900/20 border-red-800' 
                : 'bg-red-50 border-red-200'
            )}>
              <div className="flex items-start">
                <FiAlertCircle className={cn(
                  "w-5 h-5 mr-2 flex-shrink-0 mt-0.5",
                  theme === 'dark' ? 'text-red-400' : 'text-red-600'
                )} />
                <div>
                  <h4 className={cn(
                    "text-sm font-medium mb-1",
                    theme === 'dark' ? 'text-red-300' : 'text-red-800'
                  )}>
                    Please fix the following errors:
                  </h4>
                  <ul className={cn(
                    "text-sm space-y-1",
                    theme === 'dark' ? 'text-red-400' : 'text-red-700'
                  )}>
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field}>• {message}</li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          )}

          {/* Section Content */}
          <div className={cn("shadow-xl rounded-2xl p-8", t.glass.card)}>
            <h2 className={cn("text-2xl font-semibold mb-6 flex items-center", t.text.primary)}>
              {React.createElement(sections[currentSection].icon, { className: "h-6 w-6 mr-3 text-gradient" })}
              {sections[currentSection].title}
            </h2>
            
            {currentSection === 0 && (
              <Step1ContextRequest
                formData={formData}
                updateFormData={updateFormData}
                providers={providers}
                facilities={facilities}
                currentUser={currentUser}
                errors={errors}
              />
            )}
            
            {currentSection === 1 && (
              <Step2PatientShipping
                formData={formData}
                updateFormData={updateFormData}
                errors={errors}
              />
            )}
            
            {currentSection === 2 && (
              <Step3Insurance
                formData={formData}
                updateFormData={updateFormData}
                insuranceCarriers={insuranceCarriers}
                errors={errors}
              />
            )}
            
            {currentSection === 3 && (
              <Step4ClinicalBilling
                formData={formData}
                updateFormData={updateFormData}
                diagnosisCodes={diagnosisCodes}
                woundArea={woundArea}
                errors={errors}
              />
            )}
            
            {currentSection === 4 && (
              <Step5ProductSelection
                formData={formData}
                updateFormData={updateFormData}
                products={products}
                providerProducts={providerProducts}
                errors={errors}
                currentUser={currentUser}
              />
            )}
            
            {currentSection === 5 && (
              <Step6ManufacturerQuestions
                formData={formData}
                updateFormData={updateFormData}
                products={products}
                errors={errors}
              />
            )}
            
            {currentSection === 6 && (
              <Step7DocuSealIVR
                formData={formData}
                updateFormData={updateFormData}
                products={products}
                providers={providers}
                facilities={facilities}
                errors={errors}
              />
            )}
          </div>

          {/* Navigation Buttons */}
          <div className="mt-8 flex justify-between">
            <button
              onClick={handlePrevious}
              disabled={currentSection === 0}
              className={cn(
                "flex items-center px-6 py-3 rounded-lg font-medium transition-all",
                currentSection === 0
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:shadow-lg",
                t.button.secondary
              )}
            >
              <FiArrowLeft className="mr-2" />
              Previous
            </button>

            {currentSection < sections.length - 1 ? (
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
                  "bg-gradient-to-r from-green-500 to-green-600 text-white"
                )}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Order'}
                <FiCheck className="ml-2" />
              </button>
            )}
          </div>

          {/* Timer Display */}
          <div className={cn("mt-6 text-center text-sm", t.text.secondary)}>
            Total estimated completion time: <span className="font-semibold">90 seconds</span>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

export default QuickRequestCreateNew;