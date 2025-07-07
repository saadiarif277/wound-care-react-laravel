import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiArrowRight, FiCheck, FiAlertCircle, FiUser, FiActivity, FiShoppingCart, FiFileText, FiZap } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { ensureValidCSRFToken } from '@/lib/csrf';
import Step2PatientInsurance from './Components/Step2PatientInsurance';
import Step4ClinicalBilling from './Components/Step4ClinicalBilling';
import Step5ProductSelection from './Components/Step5ProductSelection';
import Step6ReviewSubmit from './Components/Step6ReviewSubmit';
import Step7PDFIIVR from './Components/Step7PDFIIVR';
import axios from 'axios';

interface QuickRequestFormData {
  // Context & Request Type
  request_type: 'new_request' | 'reverification' | 'additional_applications';
  provider_id: number | null;
  facility_id: number | null;
  sales_rep_id?: string;

  // Patient Information
  patient_name: string; // Combined name for episode creation
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
  wound_type?: string; // Changed from array to single string
  wound_types: string[]; // Keep for backward compatibility
  wound_other_specify?: string;
  wound_location: string;
  wound_location_details?: string;
  // Old diagnosis code fields
  yellow_diagnosis_code?: string;
  orange_diagnosis_code?: string;
  pressure_ulcer_diagnosis_code?: string;
  // New diagnosis code fields
  primary_diagnosis_code?: string;
  secondary_diagnosis_code?: string;
  diagnosis_code?: string;
  wound_size_length: string;
  wound_size_width: string;
  wound_size_depth: string;
  wound_duration?: string;
  // New duration fields
  wound_duration_days?: string;
  wound_duration_weeks?: string;
  wound_duration_months?: string;
  wound_duration_years?: string;
  previous_treatments?: string;
  previous_treatments_selected?: Record<string, boolean>; // NEW - for checkbox selections

  // Procedure Information
  application_cpt_codes: string[];
  application_cpt_codes_other?: string; // NEW - for "Other" CPT codes
  prior_applications?: string;
  prior_application_product?: string; // NEW
  prior_application_within_12_months?: boolean; // NEW
  anticipated_applications?: string;

  // Billing Status
  place_of_service: string;
  medicare_part_b_authorized?: boolean;
  snf_days?: string;
  hospice_status?: boolean;
  hospice_family_consent?: boolean; // NEW
  hospice_clinically_necessary?: boolean; // NEW
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

  // Documents
  additional_documents?: Array<{
    file: File;
    type: 'insurance_card_front' | 'insurance_card_back' | 'insurance_card_combined' | 'clinical_notes' | 'demographics_page';
  }>; // NEW - for additional IVR documents with types

  // Episode tracking
  episode_id?: string;
  patient_display_id?: string;
  
  // PDF document tracking
  pdf_document_id?: string;
  patient_fhir_id?: string;
  fhir_practitioner_id?: string;
  fhir_organization_id?: string;
  fhir_condition_id?: string; // Added for FHIR Condition
  fhir_episode_of_care_id?: string;
  fhir_coverage_ids?: string[];
  fhir_encounter_id?: string; // Added for FHIR Encounter
  fhir_questionnaire_response_id?: string;
  fhir_device_request_id?: string;
  pdf_document_id?: string;
  final_submission_id?: string;
  final_submission_completed?: boolean;
  final_submission_data?: any;

  // Organization Info (auto-populated)
  organization_id?: number;
  organization_name?: string;

  // Manufacturer tracking
  manufacturer_id?: number;
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
    fhir_practitioner_id?: string;
  }>;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
    available_sizes?: number[];
    price_per_sq_cm?: number;
  }>;
  woundTypes?: Record<string, string>;
  diagnosisCodes?: {
    yellow: Array<{ code: string; description: string }>;
    orange: Array<{ code: string; description: string }>;
  };
  currentUser: {
    id: number;
    name: string;
    npi?: string;
    role?: string;
    fhir_practitioner_id?: string;
    organization?: {
      id: number;
      name: string;
      address?: string;
      phone?: string;
      fhir_organization_id?: string;
    };
  };
  providerProducts?: Record<string, string[]>; // provider ID to product codes mapping

}

function QuickRequestCreateNew({
  facilities = [],
  providers = [],
  products = [],
  diagnosisCodes,
  currentUser,
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

  // Use manufacturers hook (simplified since we don't need order form logic)

  const [currentSection, setCurrentSection] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isCreatingDraft, setIsCreatingDraft] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Get tomorrow's date as default for service date
  const getTomorrowDate = (): string => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0] || '';
  };

  const [formData, setFormData] = useState<QuickRequestFormData>({
    // Initialize with defaults
    request_type: 'new_request',
    provider_id: currentUser.role === 'provider' ? currentUser.id : null,
    facility_id: null,
    sales_rep_id: currentUser.role === 'sales_rep' ? `AUTO-${currentUser.id}` : undefined,
    organization_id: currentUser.organization?.id,
    organization_name: currentUser.organization?.name || '',
    patient_name: '',
    patient_first_name: '',
    patient_last_name: '',
    patient_dob: '',
    patient_gender: 'unknown',
    patient_is_subscriber: true,
    primary_insurance_name: '',
    primary_member_id: '',
    primary_plan_type: '',
    has_secondary_insurance: false,
    prior_auth_permission: true,
    wound_type: '', // Changed from array to single string
    wound_types: [], // Keep for backward compatibility
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
    pdf_document_id: '',
    // FHIR IDs from providers/orgs
    fhir_practitioner_id: currentUser.role === 'provider' ? currentUser.fhir_practitioner_id : undefined,
    fhir_organization_id: currentUser.organization?.fhir_organization_id,
  });

  const updateFormData = (updates: Partial<QuickRequestFormData>) => {
    setFormData(prev => ({ ...prev, ...updates }));
  };

  // Check organization on mount
  useEffect(() => {
    if (!currentUser.organization || !currentUser.organization.id) {
      console.warn('⚠️ Current user has no organization in frontend data, but backend may still find it');
      // Don't show error immediately - backend has fallback logic
    } else {
      console.log('✅ Organization found:', currentUser.organization.name);
    }
  }, [currentUser]);

  // Simplified sections array - no order form step needed
  const sections = [
    { title: 'Patient & Insurance', icon: FiUser },
    { title: 'Clinical Validation', icon: FiActivity },
    { title: 'Select Products', icon: FiShoppingCart },
    { title: 'Complete IVR Form', icon: FiFileText },
    { title: 'Review & Confirm', icon: FiCheck },
  ];
  const prefillTestData = () => {
    const testData: Partial<QuickRequestFormData> = {
      // Provider & Facility (pick first available)
      provider_id: providers[0]?.id || null,
      facility_id: facilities[0]?.id || null,

      // Patient Information
      patient_first_name: 'John',
      patient_last_name: 'Doe',
      patient_dob: '1965-03-15',
      patient_gender: 'male',
      patient_member_id: 'MED123456789',
      patient_address_line1: '123 Main Street',
      patient_address_line2: 'Apt 4B',
      patient_city: 'New York',
      patient_state: 'NY',
      patient_zip: '10001',
      patient_phone: '(555) 123-4567',
      patient_email: 'john.doe@email.com',
      patient_is_subscriber: true,

      // Insurance
      primary_insurance_name: 'Medicare',
      primary_member_id: 'MED123456789',
      primary_payer_phone: '(800) 633-4227',
      primary_plan_type: 'ffs',
      has_secondary_insurance: false,

      // Clinical Information
      wound_type: 'diabetic_foot_ulcer',
      wound_types: ['diabetic_foot_ulcer'],
      wound_location: 'right_foot',
      wound_location_details: 'Plantar surface, first metatarsal head',
      primary_diagnosis_code: 'E11.621',
      secondary_diagnosis_code: 'L97.519',
      wound_size_length: '2.5',
      wound_size_width: '1.8',
      wound_size_depth: '0.3',
      wound_duration_weeks: '6',
      wound_duration_days: '2',

      // Procedure Information
      application_cpt_codes: ['97597'],
      prior_applications: '2',
      prior_application_product: 'Standard dressing',
      prior_application_within_12_months: false,
      anticipated_applications: '4',

      // Billing Status
      place_of_service: '11',
      medicare_part_b_authorized: true,
      hospice_status: false,
      part_a_status: false,
      global_period_status: false,

      // Selected Products (pick first product if available)
      selected_products: products.length > 0 && products[0] ? [{
        product_id: products[0].id,
        quantity: 1,
        size: products[0]?.available_sizes?.[0]?.toString() || '4',
        product: products[0]
      }] : [],

      // Authorization
      failed_conservative_treatment: true,
      information_accurate: true,
      medical_necessity_established: true,
      maintain_documentation: true,
      authorize_prior_auth: true,

      // Manufacturer Fields (example)
      manufacturer_fields: {
        patient_ambulatory: true,
        patient_weight_bearing: false,
        wound_infected: false,
        wound_drainage: 'minimal',
        wound_odor: false,
        tunnel_present: false,
        undermining_present: false,
        exposed_bone: false,
        exposed_tendon: false,
        patient_diabetic: true,
        hba1c_level: '7.2',
        ankle_brachial_index: '0.9',
        transcutaneous_oxygen: '45',
        patient_compliant: true,
        offloading_used: true,
        compression_therapy: false,
        hyperbaric_oxygen: false,
        negative_pressure: false,
        debridement_frequency: 'weekly',
        dressing_change_frequency: 'twice_weekly',
        nutritional_support: true,
        patient_smoker: false,
        allergies_collagen: false,
        allergies_silver: false,
        allergies_honey: false,
        contraindications: false
      }
    };

    updateFormData(testData);

    // Show success message
    const successDiv = document.createElement('div');
    successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    successDiv.textContent = '✅ Test data pre-filled successfully!';
    document.body.appendChild(successDiv);

    setTimeout(() => {
      successDiv.remove();
    }, 3000);
  };

  const validateSection = (section: number): Record<string, string> => {
    const errors: Record<string, string> = {};

    switch (section) {
      case 0: // Patient Information (including insurance, service date, shipping)
        // Provider & Facility validation
        if (!formData.provider_id) errors.provider_id = 'Provider selection is required';
        if (!formData.facility_id) errors.facility_id = 'Facility selection is required';

        // Patient info validation
        if (!formData.patient_first_name) errors.patient_first_name = 'First name is required';
        if (!formData.patient_last_name) errors.patient_last_name = 'Last name is required';
        if (!formData.patient_dob) errors.patient_dob = 'Date of birth is required';

        // Service & Shipping validation with proper date validation
        if (!formData.expected_service_date) {
          errors.expected_service_date = 'Service date is required';
        } else {
          const serviceDate = new Date(formData.expected_service_date);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          if (serviceDate <= today) {
            errors.expected_service_date = 'Service date must be in the future';
          }
        }
        if (!formData.shipping_speed) errors.shipping_speed = 'Shipping speed is required';

        // Validate delivery date for choose_delivery_date option
        if (formData.shipping_speed === 'choose_delivery_date' && !formData.delivery_date) {
          errors.delivery_date = 'Please select a delivery date';
        }

        // Insurance validation
        if (!formData.primary_insurance_name) errors.primary_insurance_name = 'Primary insurance is required';
        if (!formData.primary_member_id) errors.primary_member_id = 'Member ID is required';
        if (!formData.primary_plan_type) errors.primary_plan_type = 'Plan type is required';

        if (formData.has_secondary_insurance) {
          if (!formData.secondary_insurance_name) errors.secondary_insurance = 'Secondary insurance name is required';
          if (!formData.secondary_member_id) errors.secondary_insurance = 'Secondary member ID is required';
        }
        break;

      case 1: // Clinical Details
        if (!formData.wound_type) errors.wound_type = 'Please select a wound type';
        if (formData.wound_type === 'other' && !formData.wound_other_specify) {
          errors.wound_other_specify = 'Please specify the other wound type';
        }
        if (!formData.wound_location) errors.wound_location = 'Wound location is required';
        if (!formData.wound_size_length) errors.wound_size = 'Wound length is required';
        if (!formData.wound_size_width) errors.wound_size = 'Wound width is required';
        if ((!formData.application_cpt_codes || formData.application_cpt_codes.length === 0) &&
            !formData.application_cpt_codes_other) {
          errors.cpt_codes = 'At least one CPT code must be selected';
        }
        if (!formData.place_of_service) errors.place_of_service = 'Place of service is required';

        // Validate diagnosis codes based on wound type
        if (formData.wound_type === 'diabetic_foot_ulcer' || formData.wound_type === 'venous_leg_ulcer') {
          if (!formData.primary_diagnosis_code) errors.primary_diagnosis_code = 'Primary diagnosis code is required';
          if (!formData.secondary_diagnosis_code) errors.secondary_diagnosis_code = 'Secondary diagnosis code is required';
        } else if (formData.wound_type) {
          if (!formData.diagnosis_code) errors.diagnosis_code = 'Diagnosis code is required';
        }

        // Validate at least one duration field is filled
        if (!formData.wound_duration_days && !formData.wound_duration_weeks &&
            !formData.wound_duration_months && !formData.wound_duration_years) {
          errors.wound_duration = 'At least one duration field is required';
        }
        break;

      case 2: // Product Selection
        // More robust validation
        const hasValidProducts = formData.selected_products &&
          Array.isArray(formData.selected_products) &&
          formData.selected_products.length > 0 &&
          formData.selected_products.every(product =>
            product &&
            typeof product === 'object' &&
            product.product_id &&
            product.quantity &&
            product.quantity > 0
          );

        if (!hasValidProducts) {
          errors.products = 'Please select at least one product with valid quantity';
        }
        break;

      case 3: // Complete IVR Form
        // IVR completion validation - manufacturer fields should be filled
        break;

      case 4: // Review & Submit Order
        // Final review validation
        break;
    }

    return errors;
  };

  const handleNext = async () => {
    // Validate current section
    const sectionErrors = validateSection(currentSection);
    if (Object.keys(sectionErrors).length > 0) {
      setErrors(sectionErrors);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    setErrors({});

    // Refresh CSRF token before proceeding to next section
    try {
      await ensureValidCSRFToken();
    } catch (error) {
      console.error('Failed to refresh CSRF token:', error);
      setErrors({ csrf: 'Unable to refresh security token. Please refresh the page.' });
      return;
    }

    // If this is the final step, submit the order directly
    const isFinalStep = currentSection === 4; // Step 4 is Review & Confirm

    if (isFinalStep) {
      await handleSubmitOrder();
      return;
    }

    // All frontend FHIR creation logic has been removed.
    // The backend orchestrator is now the single source of truth.

    // Create draft episode when moving from Product Selection (step 2) to IVR Form (step 3)
    if (currentSection === 2 && !formData.episode_id) {
      setIsCreatingDraft(true);
      try {
        console.log('🔄 Creating draft episode before IVR step...');
        
        // Get manufacturer name from the selected product
        const selectedProduct = formData.selected_products?.[0];
        const product = products.find(p => p.id === selectedProduct?.product_id);
        const manufacturerName = product?.manufacturer;

        if (!manufacturerName) {
          setErrors({ episode: 'Unable to determine manufacturer from selected products. Please select a product first.' });
          return;
        }
        
        // Try to include organization_id if available, but don't block if missing
        // Backend has fallback logic to find organization
        const updatedFormData = {
          ...formData,
          organization_id: formData.organization_id || currentUser.organization?.id || null,
          organization_name: formData.organization_name || currentUser.organization?.name || '',
        };
        
        console.log('📋 Submitting draft episode with organization data:', {
          organization_id: updatedFormData.organization_id,
          organization_name: updatedFormData.organization_name,
          has_current_org: !!currentUser.organization,
          current_user_id: currentUser.id
        });
        
        const payload = {
          form_data: updatedFormData,
          manufacturer_name: manufacturerName,
        };

        const response = await axios.post(
          "/api/v1/quick-request/create-draft-episode",
          payload,
          {
            headers: {
              'X-CSRF-TOKEN': await ensureValidCSRFToken(),
              'Content-Type': 'application/json'
            }
          }
        );

        if (response.data.success && response.data.episode_id) {
          console.log('✅ Draft episode created:', response.data.episode_id);
          
          // Update form data with the draft episode ID
          updateFormData({
            episode_id: response.data.episode_id
          });
        } else {
          console.error('❌ Failed to create draft episode:', response.data);
          setErrors({ episode: 'Failed to create draft episode. Please try again.' });
          return;
        }
      } catch (error: any) {
        console.error('❌ Error creating draft episode:', error);
        setErrors({ episode: 'Error creating draft episode. Please try again.' });
        return;
      } finally {
        setIsCreatingDraft(false);
      }
    }

    // Move to the next section
    setCurrentSection(currentSection + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleBack = () => {
    if (currentSection > 0) {
      setCurrentSection(prev => prev - 1);
      setErrors({});
    }
  };

  const handleSubmitOrder = async () => {
    setIsSubmitting(true);

    // Retry mechanism for CSRF token issues
    const maxRetries = 2;
    let currentRetry = 0;

    while (currentRetry <= maxRetries) {
      try {
        // Always refresh the CSRF token before submission
        const csrfToken = await ensureValidCSRFToken();
        if (!csrfToken) {
          setErrors({ submit: 'Unable to obtain security token. Please refresh the page.' });
          return;
        }

        // The extractIvrFields function has been removed. This is now a backend responsibility.

      // Clean and prepare the form data for submission
      const finalFormData = {
        ...formData,
        // manufacturer_fields are now directly from the form state, not a separate extraction.
        manufacturer_fields: formData.manufacturer_fields || {},
        // Ensure pdf_document_id is a string or null
        pdf_document_id: formData.pdf_document_id ? String(formData.pdf_document_id) : null,
        // Ensure selected_products have valid product_id values
        selected_products: formData.selected_products?.map(product => ({
          ...product,
          product_id: Number(product.product_id)
        })).filter(product => product.product_id && !isNaN(product.product_id)) || [],
        // Ensure required fields have default values
        request_type: formData.request_type || 'new_request',
        patient_is_subscriber: Boolean(formData.patient_is_subscriber),
        has_secondary_insurance: Boolean(formData.has_secondary_insurance),
        prior_auth_permission: Boolean(formData.prior_auth_permission),
        failed_conservative_treatment: Boolean(formData.failed_conservative_treatment),
        information_accurate: Boolean(formData.information_accurate),
        medical_necessity_established: Boolean(formData.medical_necessity_established),
        maintain_documentation: Boolean(formData.maintain_documentation),
        application_cpt_codes: Array.isArray(formData.application_cpt_codes) ? formData.application_cpt_codes : [],
        // Ensure numeric fields are properly formatted
        wound_size_length: Number(formData.wound_size_length) || 0,
        wound_size_width: Number(formData.wound_size_width) || 0,
        wound_size_depth: formData.wound_size_depth ? Number(formData.wound_size_depth) : null,
        // Ensure provider and facility IDs are numbers
        provider_id: Number(formData.provider_id),
        facility_id: Number(formData.facility_id)
      };

      console.log('🚀 Submitting order directly:', {
        formDataKeys: Object.keys(finalFormData),
        formDataCount: Object.keys(finalFormData).length,
        sampleData: {
          patient_name: finalFormData.patient_name,
          request_type: finalFormData.request_type,
          provider_id: finalFormData.provider_id,
          facility_id: finalFormData.facility_id,
          selected_products: finalFormData.selected_products,
          pdf_document_id: finalFormData.pdf_document_id,
        }
      });

      // Debug: Check if we have valid product IDs
      if (finalFormData.selected_products && finalFormData.selected_products.length > 0) {
        console.log('🔍 Product validation check:', {
          availableProductIds: products.map(p => p.id),
          selectedProductIds: finalFormData.selected_products.map(p => p.product_id),
          allValid: finalFormData.selected_products.every(p => products.some(prod => prod.id === p.product_id))
        });
      }

      // Debug: Check provider and facility IDs
      console.log('🔍 Provider and Facility check:', {
        availableProviderIds: providers.map(p => p.id),
        availableFacilityIds: facilities.map(f => f.id),
        selectedProviderId: finalFormData.provider_id,
        selectedFacilityId: finalFormData.facility_id,
        providerValid: providers.some(p => p.id === finalFormData.provider_id),
        facilityValid: facilities.some(f => f.id === finalFormData.facility_id)
      });

      console.log('🔍 Selected products details:', finalFormData.selected_products);
      console.log('🔍 PDF document ID:', finalFormData.pdf_document_id, 'Type:', typeof finalFormData.pdf_document_id);

      // Submit order directly
      const response = await axios.post('/quick-requests/submit-order', {
        formData: finalFormData,
        episodeData: {
          episode_id: formData.episode_id,
          patient_fhir_id: formData.patient_fhir_id,
          fhir_episode_of_care_id: formData.fhir_episode_of_care_id,
          fhir_coverage_ids: formData.fhir_coverage_ids,
          fhir_questionnaire_response_id: formData.fhir_questionnaire_response_id,
          fhir_device_request_id: formData.fhir_device_request_id
        },
        adminNote: 'Order submitted directly from Quick Request form'
      }, {
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json'
        }
      });

      console.log('✅ Order submission response:', response.data);

      if (response.data.success) {
        // Show success message and redirect
        alert('Order submitted successfully! Your order is now being processed.');

        // Temporarily redirect to dashboard to avoid access issues
        // TODO: Re-enable episode redirect once access control is fixed
        const redirectToDashboard = true; // Toggle this to test episode redirect

        if (redirectToDashboard) {
          // Go directly to dashboard where the new order will be visible
          setTimeout(() => {
            router.visit('/provider/dashboard');
          }, 1000);
        } else if (response.data.episode_id) {
          // Original episode redirect logic (currently has access issues)
          setTimeout(() => {
            router.visit(`/provider/episodes/${response.data.episode_id}`, {
              onError: () => {
                console.error('Failed to navigate to episode, redirecting to dashboard');
                router.visit('/provider/dashboard');
              }
            });
          }, 2000);
        } else {
          // Fallback to provider dashboard if no episode_id
          router.visit('/provider/dashboard');
        }
        return; // Exit the retry loop on success
      } else {
        throw new Error(response.data.message || 'Failed to submit order');
      }
    } catch (error: any) {
      console.error('❌ Error submitting order (attempt ' + (currentRetry + 1) + '):', error);
      let errorMessage = 'An unexpected error occurred. Please try again.';

      // Handle CSRF token expiration specifically
      if (error.response?.status === 419) {
        console.log('CSRF token expired, attempting to refresh...');

        // Force refresh the token
        const newToken = await ensureValidCSRFToken();
        if (!newToken) {
          console.error('Failed to refresh CSRF token');
          setErrors({ submit: 'Session expired. Please refresh the page and try again.' });
          return; // Exit if token refresh fails
        }

        // If we have retries left, try again
        if (currentRetry < maxRetries) {
          currentRetry++;
          console.log(`Retrying submission with fresh token (attempt ${currentRetry + 1})...`);
          continue; // Continue to the next iteration of the while loop
        }
        
        // If we've exhausted retries, set an error message before falling through
        errorMessage = 'Session expired. Please refresh the page and try again.';

      } else if (error.message) {
        errorMessage = error.message;
      }

      setErrors({ submit: errorMessage });
      setIsSubmitting(false); // Make sure to turn off submitting state on final failure
      return; // Exit the function
    }
  } // End of while loop

  // This should be outside the loop. If the loop finishes without returning, it means all retries failed.
  setIsSubmitting(false);
};

  // Set default expected service date to tomorrow when component mounts
  useEffect(() => {
    if (!formData.expected_service_date) {
      updateFormData({ expected_service_date: getTomorrowDate() });
    }
  }, []); // Empty dependency array means this runs once when component mounts

  // Calculate delivery date based on shipping speed
  useEffect(() => {
    if (formData.expected_service_date && formData.shipping_speed) {
      if (formData.shipping_speed === 'choose_delivery_date') {
        // Don't auto-calculate for 'choose_delivery_date', user will input manually
        return;
      }

      const today = new Date();
      const daysToAdd = formData.shipping_speed === 'standard_2_day' ? 2 : 1;

      const deliveryDate = new Date(today);
      deliveryDate.setDate(deliveryDate.getDate() + daysToAdd);

      updateFormData({ delivery_date: deliveryDate.toISOString().split('T')[0] });
    } else if (!formData.shipping_speed || formData.shipping_speed !== 'choose_delivery_date') {
      // Clear delivery date when no shipping speed selected or not 'choose_delivery_date'
      updateFormData({ delivery_date: '' });
    }
  }, [formData.expected_service_date, formData.shipping_speed]);

  // Sync patient_name with first_name and last_name
  useEffect(() => {
    if (formData.patient_first_name && formData.patient_last_name) {
      const fullName = `${formData.patient_first_name} ${formData.patient_last_name}`.trim();
      if (fullName !== formData.patient_name) {
        updateFormData({ patient_name: fullName });
      }
    }
  }, [formData.patient_first_name, formData.patient_last_name]);

  // The createFhirResources function has been removed as it is redundant.
  // The backend orchestrator now handles all FHIR resource creation.

  // The extractIvrFields function has been removed.
  // This logic is now handled by the backend to ensure a single source of truth.

  // Periodically refresh CSRF token to prevent expiration during long form sessions
  useEffect(() => {
    const refreshInterval = setInterval(async () => {
      try {
        await ensureValidCSRFToken();
        console.log('CSRF token refreshed automatically');
      } catch (error) {
        console.error('Failed to refresh CSRF token automatically:', error);
      }
    }, 10 * 60 * 1000); // Refresh every 10 minutes

    return () => clearInterval(refreshInterval);
  }, []);

  // Calculate wound area
  const woundArea = formData.wound_size_length && formData.wound_size_width
    ? (parseFloat(formData.wound_size_length) * parseFloat(formData.wound_size_width)).toFixed(2)
    : '0';

  return (
    <MainLayout>
      <Head title="Quick Request - Enhanced Flow" />

      <div className={cn("min-h-screen", t.background.base, t.background.noise)}>
        <div className="max-w-5xl mx-auto p-6">
          {/* Header */}
          <div className="mb-8">
            <h1 className={cn("text-3xl font-bold mb-2", t.text.primary)}>
              Create New Order
            </h1>
            <p className={cn(t.text.secondary)}>
              Complete your wound care order in {sections.length} simple steps
            </p>

            {/* Pre-fill Test Data Button */}
            <button
              onClick={prefillTestData}
              className={cn(
                "mt-4 px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition-all duration-200",
                "bg-purple-500/20 hover:bg-purple-500/30 border border-purple-500/30",
                "text-purple-300 hover:text-purple-200"
              )}
            >
              <FiZap className="h-4 w-4" />
              Pre-fill Test Data
            </button>
          </div>

          {/* Progress Bar */}
          <div className="mb-8">
            <div className="flex items-center justify-between mb-2 transition-all duration-500">
              {sections.map((section, index) => {
                const Icon = section.icon;
                const isActive = index <= currentSection;
                const isCompleted = index < currentSection;
                return (
                  <div
                    key={index}
                    className={`flex items-center ${index < sections.length - 1 ? 'flex-1' : ''}`}
                  >
                    <div className={cn("flex flex-col items-center", isActive ? "text-blue-500" : t.text.muted)}>
                      <div className={cn(
                        "rounded-full p-3",
                        isActive
                          ? "bg-blue-500/20 border-2 border-blue-500/40"
                          : cn("border-2", t.glass.border, t.glass.base)
                      )}>
                        {isCompleted ? <FiCheck className="h-6 w-6 text-emerald-400" /> : <Icon className="h-6 w-6" />}
                      </div>
                      <span className={cn(
                        "text-xs mt-2 text-center max-w-[100px]",
                        t.text.secondary,
                        // Highlight order form step when it appears
                        section.title === 'Order Form Review' && "font-semibold"
                      )}>{section.title}</span>
                    </div>
                    {index < sections.length - 1 && (
                      <div className={cn(
                        "flex-1 h-0.5 mx-2 rounded",
                        isCompleted ? "bg-gradient-to-r from-blue-500 to-emerald-500" : t.glass.border
                      )} />
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Validation Error Summary */}
          {Object.keys(errors).length > 0 && (
            <div className="mb-6 p-4 rounded-lg border border-amber-500/30 bg-amber-500/10">
              <div className="flex items-start">
                <FiAlertCircle className="w-5 h-5 mr-2 flex-shrink-0 mt-0.5 text-amber-400" />
                <div>
                  <h4 className={cn("text-sm font-medium mb-1", t.text.primary)}>
                    Please fix the following errors:
                  </h4>
                  <ul className={cn("text-sm space-y-1", t.text.secondary)}>
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field}>• {message}</li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          )}

          {/* Section Content */}
          <div className={cn("rounded-2xl p-8 mb-6", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-2xl font-semibold mb-6 flex items-center", t.text.primary)}>
              {React.createElement(sections[currentSection]?.icon as any, { className: "h-6 w-6 mr-3 text-blue-500" })}
              {sections[currentSection]?.title}
            </h2>

            {currentSection === 0 && (
              <Step2PatientInsurance
                formData={formData as any}
                updateFormData={updateFormData as any}
                errors={errors}
                facilities={facilities}
                providers={providers}
                currentUser={currentUser}
              />
            )}

            {currentSection === 1 && (
              <Step4ClinicalBilling
                formData={formData as any}
                updateFormData={updateFormData as any}
                diagnosisCodes={diagnosisCodes}
                woundArea={woundArea}
                errors={errors}
              />
            )}

            {currentSection === 2 && (
              <Step5ProductSelection
                formData={formData as any}
                updateFormData={updateFormData as any}
                errors={errors}
                currentUser={currentUser}
              />
            )}

            {currentSection === 3 && (
              <Step7PDFIIVR
                formData={formData as any}
                updateFormData={updateFormData as any}
                products={products}
                errors={errors}
                onNext={handleNext}
              />
            )}

            {/* Review & Submit Step */}
            {currentSection === 4 && (
              <Step6ReviewSubmit
                formData={formData}
                products={products}
                providers={providers}
                facilities={facilities}
                errors={errors}
                onSubmit={handleNext}
                isSubmitting={isSubmitting}
              />
            )}
          </div>

          {/* Navigation Buttons */}
          <div className="flex justify-between">
            <button
              onClick={handleBack}
              disabled={currentSection === 0}
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all duration-200",
                currentSection === 0
                  ? cn("cursor-not-allowed opacity-50", t.glass.base, t.text.muted)
                  : cn(t.button.secondary.base, t.button.secondary.hover)
              )}
            >
              Previous
            </button>

            {currentSection < sections.length - 1 ? (
              <button
                onClick={handleNext}
                className={cn(
                  "px-6 py-3 rounded-xl font-medium flex items-center transition-all duration-200",
                  t.button.primary.base,
                  t.button.primary.hover
                )}
              >
                Next
                <FiArrowRight className="ml-2 h-5 w-5" />
              </button>
            ) : null /* Submit button is now inside Step6ReviewSubmit */}
          </div>

          {/* Status Display */}
          <div className={cn("mt-6 text-center text-sm", t.text.tertiary)}>
            {formData.patient_fhir_id && (
              <div className={cn("mt-2 p-2 rounded-lg", t.status.success)}>
                ✅ Patient FHIR ID: {formData.patient_fhir_id}
              </div>
            )}
            {formData.fhir_episode_of_care_id && (
              <div className={cn("mt-2 p-2 rounded-lg", t.status.success)}>
                ✅ FHIR EpisodeOfCare ID: {formData.fhir_episode_of_care_id}
              </div>
            )}
            {formData.episode_id && (
              <div className={cn("mt-2 p-2 rounded-lg", t.status.success)}>
                ✅ Episode created: {formData.episode_id}
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

export default QuickRequestCreateNew;
