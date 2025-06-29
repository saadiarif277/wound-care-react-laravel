import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiArrowRight, FiCheck, FiAlertCircle, FiUser, FiActivity, FiShoppingCart, FiFileText, FiZap } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { ensureValidCSRFToken, addCSRFTokenToFormData } from '@/lib/csrf';
// CSRFTestButton import removed as requested
import Step2PatientInsurance from './Components/Step2PatientInsurance';
import Step4ClinicalBilling from './Components/Step4ClinicalBilling';
import Step5ProductSelection from './Components/Step5ProductSelection';
import Step6ReviewSubmit from './Components/Step6ReviewSubmit';
import Step7DocuSealIVR from './Components/Step7DocuSealIVR';
import Step8OrderFormApproval from './Components/Step8OrderFormApproval';
import { useManufacturers } from '@/hooks/useManufacturers';
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

  // Procedure Information
  application_cpt_codes: string[];
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

  // Episode tracking
  episode_id?: string;
  patient_display_id?: string;
  patient_fhir_id?: string;
  fhir_practitioner_id?: string;
  fhir_organization_id?: string;
  fhir_condition_id?: string; // Added for FHIR Condition
  fhir_episode_of_care_id?: string;
  fhir_coverage_ids?: string[];
  fhir_encounter_id?: string; // Added for FHIR Encounter
  fhir_questionnaire_response_id?: string;
  fhir_device_request_id?: string;
  docuseal_submission_id?: string;
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

  // Use manufacturers hook
  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();

  const [currentSection, setCurrentSection] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);
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
    primary_plan_type: 'ffs',
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
    expected_service_date: getTomorrowDate(),
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
    // FHIR IDs from providers/orgs
    fhir_practitioner_id: currentUser.role === 'provider' ? currentUser.fhir_practitioner_id : undefined,
    fhir_organization_id: currentUser.organization?.fhir_organization_id,
  });

  const updateFormData = (updates: Partial<QuickRequestFormData>) => {
    setFormData(prev => ({ ...prev, ...updates }));
  };

  // Helper function to check if any selected products have manufacturers with order forms
  const hasManufacturerWithOrderForm = (): boolean => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return false;
    }

    return formData.selected_products.some(selectedProduct => {
      const product = products.find(p => p.id === selectedProduct.product_id);
      if (!product) return false;

      // Find the manufacturer configuration from API data
      const manufacturerConfig = getManufacturerByName(product.manufacturer);
      
      // For now, we'll return false since order forms aren't configured in the database yet
      // TODO: Add order_form_template_id to manufacturers table and check manufacturerConfig?.order_form_template_id
      return false;
    });
  };

  // Pre-fill function for testing
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
      selected_products: products.length > 0 ? [{
        product_id: products[0].id,
        quantity: 1,
        size: products[0].available_sizes?.[0]?.toString() || '4',
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

  // State for IVR fields
  const [ivrFields, setIvrFields] = useState<Record<string, any>>({});
  const [isExtractingIvrFields, setIsExtractingIvrFields] = useState(false);

  // Dynamic sections array that conditionally includes order form step
  const getSections = () => {
    const baseSections = [
      { title: 'Patient & Insurance', icon: FiUser },
      { title: 'Clinical Validation', icon: FiActivity },
      { title: 'Select Products', icon: FiShoppingCart },
      { title: 'Complete IVR Form', icon: FiFileText },
    ];

    // Check if order form step should be included
    if (hasManufacturerWithOrderForm()) {
      baseSections.push({ title: 'Order Form Review', icon: FiFileText });
    }

    baseSections.push({ title: 'Review & Confirm', icon: FiCheck });
    return baseSections;
  };

  const sections = getSections();

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
        if (!formData.application_cpt_codes || formData.application_cpt_codes.length === 0) errors.cpt_codes = 'At least one CPT code must be selected';
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
        if (!formData.selected_products || formData.selected_products.length === 0) {
          errors.products = 'Product selection is required';
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

  // Enhanced FHIR resource creation with best practices
  const createFhirResources = async (resourceType: string) => {
    const maxAttempts = 3;
    let attempt = 0;

    while (attempt < maxAttempts) {
      try {
        const csrfToken = await ensureValidCSRFToken();
        if (!csrfToken) {
          throw new Error('Unable to obtain security token');
        }

        switch (resourceType) {
          case 'Coverage': {
            if (formData.primary_insurance_name && formData.primary_member_id && formData.patient_fhir_id) {
              const coverageResource = {
                resourceType: 'Coverage',
                status: 'active',
                beneficiary: {
                  reference: `Patient/${formData.patient_fhir_id}`
                },
                subscriber: {
                  reference: formData.patient_is_subscriber ? `Patient/${formData.patient_fhir_id}` : undefined,
                  display: formData.patient_is_subscriber ? undefined : formData.caregiver_name
                },
                subscriberId: formData.primary_member_id,
                payor: [{
                  display: formData.primary_insurance_name
                }],
                order: 1,
                network: formData.primary_plan_type,
                extension: formData.fhir_episode_of_care_id ? [{
                  url: 'https://mscwoundcare.com/fhir/StructureDefinition/episode-context',
                  valueReference: {
                    reference: `EpisodeOfCare/${formData.fhir_episode_of_care_id}`
                  }
                }] : undefined
              };

              const response = await axios.post('/api/fhir/Coverage', coverageResource, {
                headers: {
                  'X-CSRF-TOKEN': csrfToken,
                  'Content-Type': 'application/fhir+json',
                  'Accept': 'application/fhir+json',
                  'User-Agent': 'MSC-WoundCare/1.0'
                },
                timeout: 30000
              });

              if (response.data?.id) {
                const coverageIds = [...(formData.fhir_coverage_ids || []), response.data.id];
                updateFormData({ fhir_coverage_ids: coverageIds });
                console.log(`✅ FHIR Coverage created: ${response.data.id}`);
              }
            }
            break;
          }
          case 'QuestionnaireResponse': {
            if (formData.patient_fhir_id && formData.wound_types.length > 0) {
              const questionnaireResponse = {
                resourceType: 'QuestionnaireResponse',
                status: 'completed',
                subject: {
                  reference: `Patient/${formData.patient_fhir_id}`
                },
                authored: new Date().toISOString(),
                encounter: formData.fhir_episode_of_care_id ? {
                  reference: `EpisodeOfCare/${formData.fhir_episode_of_care_id}`
                } : undefined,
                item: [
                  {
                    linkId: 'wound-type',
                    answer: [{
                      valueCoding: {
                        display: formData.wound_types[0]
                      }
                    }]
                  },
                  {
                    linkId: 'wound-location',
                    answer: [{
                      valueString: formData.wound_location
                    }]
                  },
                  {
                    linkId: 'wound-size-length',
                    answer: [{
                      valueDecimal: parseFloat(formData.wound_size_length) || 0
                    }]
                  },
                  {
                    linkId: 'wound-size-width',
                    answer: [{
                      valueDecimal: parseFloat(formData.wound_size_width) || 0
                    }]
                  },
                  {
                    linkId: 'place-of-service',
                    answer: [{
                      valueCoding: {
                        code: formData.place_of_service,
                        display: formData.place_of_service === '11' ? 'Office' : 'Other'
                      }
                    }]
                  }
                ]
              };

              const response = await axios.post('/api/fhir/QuestionnaireResponse', questionnaireResponse, {
                headers: {
                  'X-CSRF-TOKEN': csrfToken,
                  'Content-Type': 'application/fhir+json',
                  'Accept': 'application/fhir+json',
                  'User-Agent': 'MSC-WoundCare/1.0'
                },
                timeout: 30000
              });

              if (response.data?.id) {
                updateFormData({ fhir_questionnaire_response_id: response.data.id });
                console.log(`✅ FHIR QuestionnaireResponse created: ${response.data.id}`);
              }
            }
            break;
          }
          case 'DeviceRequest': {
            if (formData.patient_fhir_id && formData.selected_products && formData.selected_products.length > 0) {
              const firstSelectedProduct = formData.selected_products[0];
              if (firstSelectedProduct) {
                const product = products.find(p => p.id === firstSelectedProduct.product_id);
                if (product) {
                  const deviceRequest = {
                    resourceType: 'DeviceRequest',
                    status: 'draft',
                    intent: 'order',
                    subject: {
                      reference: `Patient/${formData.patient_fhir_id}`
                    },
                    code: {
                      coding: [{
                        system: 'https://mscwoundcare.com/products',
                        code: product.code,
                        display: product.name
                      }]
                    },
                    occurrenceDateTime: formData.expected_service_date,
                    requester: formData.fhir_practitioner_id ? {
                      reference: `Practitioner/${formData.fhir_practitioner_id}`
                    } : undefined,
                    encounter: formData.fhir_episode_of_care_id ? {
                      reference: `EpisodeOfCare/${formData.fhir_episode_of_care_id}`
                    } : undefined
                  };

                  const response = await axios.post('/api/fhir/DeviceRequest', deviceRequest, {
                    headers: {
                      'X-CSRF-TOKEN': csrfToken,
                      'Content-Type': 'application/fhir+json',
                      'Accept': 'application/fhir+json',
                      'User-Agent': 'MSC-WoundCare/1.0'
                    },
                    timeout: 30000
                  });

                  if (response.data?.id) {
                    updateFormData({ fhir_device_request_id: response.data.id });
                    console.log(`✅ FHIR DeviceRequest created: ${response.data.id}`);
                  }
                }
              }
            }
            break;
          }
          // Add other resource cases here as needed, following the same pattern
        }
        // Success - break out of retry loop
        break;
      } catch (error: any) {
        attempt++;
        console.error(`❌ Error creating FHIR ${resourceType} (attempt ${attempt}/${maxAttempts}):`, error);
        if (error.response?.status === 401) {
          console.warn('🔑 Authentication failed, will retry with fresh token');
        } else if (error.response?.status === 429) {
          console.warn('⏰ Rate limit exceeded, implementing exponential backoff');
          await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
        } else if (error.code === 'ECONNABORTED') {
          console.warn('⏱️ Request timeout, will retry');
        }
        if (attempt >= maxAttempts) {
          console.error(`💥 Final error creating FHIR ${resourceType} after ${maxAttempts} attempts:`, {
            status: error.response?.status,
            statusText: error.response?.statusText,
            data: error.response?.data,
            message: error.message
          });
          if (resourceType === 'Coverage') {
            console.warn('⚠️ Failed to create FHIR Coverage - continuing without it');
          } else if (resourceType === 'QuestionnaireResponse') {
            console.warn('⚠️ Failed to create FHIR QuestionnaireResponse - continuing without it');
          } else if (resourceType === 'DeviceRequest') {
            console.warn('⚠️ Failed to create FHIR DeviceRequest - continuing without it');
          }
        } else {
          await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
        }
      }
    }
  };


  // Extract IVR fields when moving to Step 6 (after product selection)
  const extractIvrFields = async () => {
    if (!formData.selected_products || formData.selected_products.length === 0) return;

    const firstProduct = formData.selected_products[0];
    if (!firstProduct) return;

    const product = products.find(p => p.id === firstProduct.product_id);
    if (!product) return;

    const manufacturerConfig = getManufacturerByName(product.manufacturer);
    if (!manufacturerConfig) return;

    setIsExtractingIvrFields(true);
    try {
      const csrfToken = await ensureValidCSRFToken();
      if (!csrfToken) throw new Error('Unable to obtain security token');

      // Get FHIR IDs from form data and providers/facilities
      const selectedProvider = providers.find(p => p.id === formData.provider_id);

      const response = await axios.post('/api/quick-request/extract-ivr-fields', {
        patient_id: formData.patient_fhir_id,
        practitioner_id: formData.fhir_practitioner_id || selectedProvider?.fhir_practitioner_id,
        organization_id: formData.fhir_organization_id || currentUser.organization?.fhir_organization_id,
        questionnaire_response_id: formData.fhir_questionnaire_response_id,
        device_request_id: formData.fhir_device_request_id,
        episode_id: formData.episode_id,
        episode_of_care_id: formData.fhir_episode_of_care_id,
        manufacturer_key: manufacturerConfig.name,
        sales_rep: formData.sales_rep_id ? {
          name: 'MSC Distribution',
          email: 'orders@mscwoundcare.com'
        } : undefined,
        selected_products: formData.selected_products?.map(sp => {
          const product = products.find(p => p.id === sp.product_id);
          return {
            name: product?.name || 'Unknown Product',
            code: product?.code || 'Unknown Code',
            size: sp.size
          };
        })
      }, {
        headers: { 'X-CSRF-TOKEN': csrfToken }
      });

      if (response.data.success) {
        setIvrFields(response.data.ivr_fields);
        updateFormData({ manufacturer_fields: response.data.ivr_fields });
        console.log(`IVR Field Coverage: ${response.data.field_coverage.percentage}%`);
      }
    } catch (error) {
      console.error('Error extracting IVR fields:', error);
    } finally {
      setIsExtractingIvrFields(false);
    }
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

    // Create FHIR patient when moving from section 0 to section 1
    if (currentSection === 0 && !formData.patient_fhir_id) {
      try {
        const csrfToken = await ensureValidCSRFToken();
        if (!csrfToken) {
          setErrors({ patient_fhir_id: 'Unable to obtain security token. Please refresh the page.' });
          return;
        }

        // Parse patient name into first and last
        const nameParts = (formData.patient_name || '').trim().split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || nameParts[0] || '';

        // Create FHIR patient resource
        const patientResource = {
          resourceType: 'Patient',
          identifier: [{
            system: 'https://mscwoundcare.com/patient-id',
            value: ((formData.patient_name || '') as string).replace(/\s+/g, '').substring(0, 10) + '_' + Date.now()
          }],
          name: [{
            given: [firstName],
            family: lastName
          }],
          active: true,
          meta: {
            tag: [{
              system: 'https://mscwoundcare.com/tags',
              code: 'quick-request-patient'
            }]
          }
        };

        const response = await axios.post('/api/fhir/Patient', patientResource, {
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/fhir+json'
          },
        });

        if (response.data && response.data.id) {
          const patientFhirId = response.data.id;

          // Update practitioner ID if provider is selected
          const selectedProvider = providers.find(p => p.id === formData.provider_id);

          // Create FHIR EpisodeOfCare resource
          const episodeOfCareResource = {
            resourceType: 'EpisodeOfCare',
            status: 'active',
            patient: {
              reference: `Patient/${patientFhirId}`
            },
            managingOrganization: formData.fhir_organization_id ? {
              reference: `Organization/${formData.fhir_organization_id}`
            } : undefined,
            team: selectedProvider?.fhir_practitioner_id ? [{
              reference: `CareTeam/${selectedProvider.fhir_practitioner_id}`
            }] : undefined,
            type: [{
              coding: [{
                system: 'http://snomed.info/sct',
                code: '225358003',
                display: 'Wound care'
              }]
            }],
            period: {
              start: new Date().toISOString()
            },
            identifier: [{
              system: 'https://mscwoundcare.com/episode-id',
              value: `EPISODE_${Date.now()}`
            }],
            meta: {
              tag: [{
                system: 'https://mscwoundcare.com/tags',
                code: 'wound-care-episode'
              }]
            }
          };

          const episodeResponse = await axios.post('/api/fhir/EpisodeOfCare', episodeOfCareResource, {
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Content-Type': 'application/fhir+json'
            },
          });

          if (episodeResponse.data && episodeResponse.data.id) {
            updateFormData({
              patient_fhir_id: patientFhirId,
              patient_display_id: ((formData.patient_name || '') as string).replace(/\s+/g, '').substring(0, 10),
              fhir_practitioner_id: selectedProvider?.fhir_practitioner_id || formData.fhir_practitioner_id,
              fhir_episode_of_care_id: episodeResponse.data.id
            });
          } else {
            setErrors({ episode_of_care: 'Failed to create episode of care. Please try again.' });
            return;
          }
        } else {
          setErrors({ patient_fhir_id: 'Failed to create patient record. Please try again.' });
          return;
        }
      } catch (error: any) {
        console.error('Error creating patient:', error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || 'Failed to create patient';
        setErrors({ patient_fhir_id: errorMessage });
        return;
      }
    }

    // Create FHIR Coverage when moving from section 0 to 1
    if (currentSection === 0) {
      await createFhirResources('Coverage');
    }

    // Create FHIR QuestionnaireResponse when moving from section 1 to 2
    if (currentSection === 1) {
      await createFhirResources('QuestionnaireResponse');
    }

    // Create DeviceRequest when moving from section 2 to 3 (after product selection)
    if (currentSection === 2) {
      await createFhirResources('DeviceRequest');

      // Episode will be created during final submission (Step 7). Early creation removed to avoid duplicate calls and 500 errors.
    }

    // Extract IVR fields when moving from section 3 to 4
    if (currentSection === 3) {
      await extractIvrFields();
    }

    setCurrentSection(prev => Math.min(prev + 1, sections.length - 1));
  };

  const handlePrevious = () => {
    setCurrentSection(prev => Math.max(prev - 1, 0));
    setErrors({});
  };

  const handleSubmit = async () => {
    // Validate all sections
    let allErrors: Record<string, string> = {};
    for (let i = 0; i <= 4; i++) {
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
      const csrfToken = await ensureValidCSRFToken();
      if (!csrfToken) {
        alert('Unable to get security token. Please refresh the page and try again.');
        window.location.reload();
        return;
      }

      // Prepare final form data including IVR fields
      const finalFormData = {
        ...formData,
        manufacturer_fields: ivrFields || {}
      };

      // Store form data in session and redirect to review page
      const response = await axios.post('/api/session/store-form-data', {
        quick_request_form_data: finalFormData,
        validated_episode_data: {
          episode_id: formData.episode_id,
          patient_fhir_id: formData.patient_fhir_id,
          fhir_episode_of_care_id: formData.fhir_episode_of_care_id,
          fhir_coverage_ids: formData.fhir_coverage_ids,
          fhir_questionnaire_response_id: formData.fhir_questionnaire_response_id,
          fhir_device_request_id: formData.fhir_device_request_id
        }
      }, {
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        // Redirect to review page
        router.visit('/quick-requests/review');
      } else {
        throw new Error('Failed to store form data in session');
      }

    } catch (error) {
      console.error('Error preparing order for review:', error);
      alert('An error occurred while preparing the order for review');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Calculate delivery date based on shipping speed
  useEffect(() => {
    if (formData.expected_service_date && formData.shipping_speed) {
      const today = new Date();
      const daysToAdd = formData.shipping_speed === 'standard_2_day' ? 2 : 1;

      const deliveryDate = new Date(today);
      deliveryDate.setDate(deliveryDate.getDate() + daysToAdd);

      updateFormData({ delivery_date: deliveryDate.toISOString().split('T')[0] });
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

            {/* CSRF Test Component removed as requested */}
          </div>

          {/* Progress Bar */}
          <div className="mb-8">
            <div className="flex items-center justify-between mb-2">
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
                      <span className={cn("text-xs mt-2 text-center max-w-[100px]", t.text.secondary)}>{section.title}</span>
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
              <Step7DocuSealIVR
                formData={formData as any}
                updateFormData={updateFormData as any}
                products={products}
                providers={providers}
                facilities={facilities}
                errors={errors}
              />
            )}

            {/* Order Form Review Step (conditional) */}
            {currentSection === 4 && hasManufacturerWithOrderForm() && (
              <Step8OrderFormApproval
                formData={formData as any}
                updateFormData={updateFormData as any}
                products={products}
                providers={providers}
                facilities={facilities}
                errors={errors}
              />
            )}

            {/* Review & Submit Step */}
            {currentSection === (hasManufacturerWithOrderForm() ? 5 : 4) && (
              <Step6ReviewSubmit
                formData={formData}
                products={products}
                providers={providers}
                facilities={facilities}
                errors={errors}
                onSubmit={handleSubmit}
                isSubmitting={isSubmitting}
              />
            )}
          </div>

          {/* Navigation Buttons */}
          <div className="flex justify-between">
            <button
              onClick={handlePrevious}
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
            {isExtractingIvrFields && (
              <div className={cn("mt-2 p-2 rounded-lg", t.status.info)}>
                ⏳ Extracting IVR fields from FHIR resources...
              </div>
            )}
            {Object.keys(ivrFields).length > 0 && (
              <div className={cn("mt-2 p-2 rounded-lg", t.status.success)}>
                ✅ IVR fields extracted: {Object.keys(ivrFields).length} fields
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

export default QuickRequestCreateNew;
