import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiUser,
  FiActivity,
  FiShoppingCart,
  FiAlertCircle,
  FiCheck,
  FiHome,
  FiShield,
  FiCreditCard,
  FiMessageSquare
} from 'react-icons/fi';
import OrderReviewSummary from './OrderReviewSummary';
import { AuthButton } from '@/Components/ui/auth-button';

interface Step6Props {
  formData: any;
  products: Array<any>;
  providers: Array<any>;
  facilities: Array<any>;
  errors: Record<string, string>;
  onSubmit: () => void;
  isSubmitting: boolean;
  orderId?: string;
  comprehensiveData?: any;
}

// Helper function to safely map form data to order data structure (same as Index.tsx)
const mapFormDataToOrderData = (formData: any, providers: Array<any> = [], facilities: Array<any> = [], comprehensiveData?: any): any => {
  // Helper function to get provider details
  const getProviderDetails = (providerId: any) => {
    if (!providerId) return null;
    return providers.find(provider => provider.id == providerId) ||
           providers.find(provider => provider.id === providerId);
  };

  // Helper function to get facility details
  const getFacilityDetails = (facilityId: any) => {
    if (!facilityId) return null;
    return facilities.find(facility => facility.id == facilityId) ||
           facilities.find(facility => facility.id === facilityId);
  };

  // Get provider and facility details
  const providerDetails = getProviderDetails(formData?.provider_id);
  const facilityDetails = getFacilityDetails(formData?.facility_id);

  return {
    orderNumber: formData?.episode_id || 'N/A',
    orderStatus: formData?.order_status || 'draft',
    createdDate: formData?.created_at ? new Date(formData.created_at).toLocaleDateString() : new Date().toLocaleDateString(),
    createdBy: providerDetails?.name || formData?.provider_name || 'N/A',
    patient: {
      fullName: `${formData?.patient_first_name || ''} ${formData?.patient_last_name || ''}`.trim() || 'N/A',
      dateOfBirth: formData?.patient_dob || 'N/A',
      phone: formData?.patient_phone || 'N/A',
      email: formData?.patient_email || 'N/A',
      address: formData?.patient_address || 'N/A',
      primaryInsurance: {
        payerName: formData?.primary_insurance_name || 'N/A',
        planName: formData?.primary_plan_type || 'N/A',
        policyNumber: formData?.primary_member_id || 'N/A',
      },
      secondaryInsurance: formData?.has_secondary_insurance ? {
        payerName: formData?.secondary_insurance_name || 'N/A',
        planName: formData?.secondary_plan_type || 'N/A',
        policyNumber: formData?.secondary_member_id || 'N/A',
      } : null,
      insuranceCardUploaded: !!formData?.insurance_card_front,
    },
    provider: {
      name: providerDetails?.name || formData?.provider_name || 'N/A',
      facilityName: facilityDetails?.name || formData?.facility_name || 'N/A',
      facilityAddress: facilityDetails?.address || formData?.facility_address || formData?.service_address || 'N/A',
      organization: providerDetails?.organization?.name || formData?.organization_name || 'N/A',
      npi: providerDetails?.npi || formData?.provider_npi || 'N/A',
    },
    clinical: {
      woundType: formData?.wound_type || 'N/A',
      woundSize: formData?.wound_size_length && formData?.wound_size_width
        ? `${formData.wound_size_length} x ${formData.wound_size_width}cm`
        : 'N/A',
      diagnosisCodes: (() => {
        // Handle multiple diagnosis code formats from different QuickRequest steps
        const codes = [];

        // Check for new diagnosis code fields
        if (formData?.primary_diagnosis_code) {
          codes.push({
            code: formData.primary_diagnosis_code,
            description: formData.primary_diagnosis_description || 'Primary Diagnosis'
          });
        }

        if (formData?.secondary_diagnosis_code) {
          codes.push({
            code: formData.secondary_diagnosis_code,
            description: formData.secondary_diagnosis_description || 'Secondary Diagnosis'
          });
        }

        // Check for old diagnosis code fields
        if (formData?.yellow_diagnosis_code) {
          codes.push({
            code: formData.yellow_diagnosis_code,
            description: 'Yellow Wound Diagnosis'
          });
        }

        if (formData?.orange_diagnosis_code) {
          codes.push({
            code: formData.orange_diagnosis_code,
            description: 'Orange Wound Diagnosis'
          });
        }

        if (formData?.pressure_ulcer_diagnosis_code) {
          codes.push({
            code: formData.pressure_ulcer_diagnosis_code,
            description: 'Pressure Ulcer Diagnosis'
          });
        }

        // Check for array format
        if (Array.isArray(formData?.diagnosis_codes)) {
          formData.diagnosis_codes.forEach((code: any) => {
            codes.push({
              code: typeof code === 'string' ? code : code?.code || 'N/A',
              description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
            });
          });
        }

        // Check for icd10_codes
        if (Array.isArray(formData?.icd10_codes)) {
          formData.icd10_codes.forEach((code: any) => {
            codes.push({
              code: typeof code === 'string' ? code : code?.code || 'N/A',
              description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
            });
          });
        }

        return codes;
      })(),
      icd10Codes: Array.isArray(formData?.icd10_codes)
        ? formData.icd10_codes.map((code: any) => ({
            code: typeof code === 'string' ? code : code?.code || 'N/A',
            description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
          }))
        : [],
      procedureInfo: formData?.procedure_info || 'N/A',
      priorApplications: parseInt(formData?.prior_applications) || 0,
      anticipatedApplications: parseInt(formData?.anticipated_applications) || 0,
      facilityInfo: facilityDetails?.name || formData?.facility_name || 'N/A',
    },
    product: {
      name: formData?.selected_products?.[0]?.product?.name || 'N/A',
      sizes: formData?.selected_products?.map((p: any) => p?.size || 'Standard') || ['N/A'],
      quantity: parseInt(formData?.selected_products?.[0]?.quantity) || 1,
      aspPrice: parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
      discountedPrice: parseFloat(formData?.selected_products?.[0]?.product?.discounted_price) ||
                      parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
      coverageWarnings: formData?.coverage_warnings || [],
    },
    ivrForm: {
      status: formData?.docuseal_submission_id ? 'Completed' : 'Not Started',
      submissionDate: formData?.ivr_completed_at || 'N/A',
      documentLink: formData?.ivr_document_link || '',
    },
    orderForm: {
      status: formData?.order_form_status || 'Not Sent',
      submissionDate: formData?.order_form_completed_at || 'N/A',
      documentLink: formData?.order_form_link || '',
    },
        // Comprehensive clinical summary with All_data key for ProductRequest show.tsx compatibility
    clinical_summary: {
      All_data: comprehensiveData ? {
        // Use comprehensive data if available (preferred)
        ...comprehensiveData,
        // Override with any additional form data
        id: undefined, // Will be set by backend
        request_number: formData?.episode_id || `QR-${Date.now()}`,
        episode_id: formData?.episode_id,
        docuseal_submission_id: formData?.docuseal_submission_id,
        ivr_document_url: undefined, // Will be set by backend
        metadata: {
          ...comprehensiveData.metadata,
          final_submission: new Date().toISOString(),
          total_steps_completed: comprehensiveData.metadata?.steps_completed?.length || 0
        }
      } : {
        // Fallback to original mapping if no comprehensive data
        // Core Request Information (matching ProductRequest interface)
        id: undefined, // Will be set by backend
        request_number: formData?.episode_id || `QR-${Date.now()}`,
        order_status: 'draft',
        step: 6,
        step_description: 'Review & Submit',
        wound_type: formData?.wound_type,
        wound_type_display: formData?.wound_type,
        expected_service_date: formData?.expected_service_date,
        patient_display: `${formData?.patient_first_name || ''} ${formData?.patient_last_name || ''}`.trim(),
        patient_fhir_id: formData?.patient_fhir_id || formData?.episode_id,
        payer_name: formData?.primary_insurance_name,
        total_amount: 0, // Will be calculated separately
        created_at: new Date().toISOString(),
        episode_id: formData?.episode_id,
        docuseal_submission_id: formData?.docuseal_submission_id,
        ivr_document_url: undefined, // Will be set by backend
        place_of_service: formData?.place_of_service,
        place_of_service_display: formData?.place_of_service,
        action_required: false,

        // Patient Information (comprehensive - matching ProductRequest.patient interface)
        patient: {
          name: `${formData?.patient_first_name || ''} ${formData?.patient_last_name || ''}`.trim(),
          firstName: formData?.patient_first_name,
          lastName: formData?.patient_last_name,
          dob: formData?.patient_dob,
          gender: formData?.patient_gender,
          phone: formData?.patient_phone,
          email: formData?.patient_email,
          address: formData?.patient_address ||
                   (formData?.patient_address_line1 ?
                     `${formData.patient_address_line1}${formData.patient_address_line2 ? ', ' + formData.patient_address_line2 : ''}, ${formData.patient_city || ''}, ${formData.patient_state || ''} ${formData.patient_zip || ''}` : ''),
          memberId: formData?.patient_member_id,
          displayId: formData?.patient_display_id || formData?.episode_id,
          isSubscriber: formData?.patient_is_subscriber !== false,
          // Additional patient fields
          caregiver_name: formData?.caregiver_name,
          caregiver_relationship: formData?.caregiver_relationship,
          caregiver_phone: formData?.caregiver_phone,
        },

        // Insurance Information (comprehensive - matching ProductRequest.insurance interface)
        insurance: {
          primary: {
            name: formData?.primary_insurance_name,
            memberId: formData?.primary_member_id,
            planType: formData?.primary_plan_type,
            payer_phone: formData?.primary_payer_phone,
          },
          secondary: formData?.has_secondary_insurance ? {
            name: formData?.secondary_insurance_name,
            memberId: formData?.secondary_member_id,
            subscriber_name: formData?.secondary_subscriber_name,
            subscriber_dob: formData?.secondary_subscriber_dob,
            payer_phone: formData?.secondary_payer_phone,
            planType: formData?.secondary_plan_type,
          } : null,
          hasSecondary: formData?.has_secondary_insurance || false,
          prior_auth_permission: formData?.prior_auth_permission || false,
        },

        // Clinical Information (comprehensive - matching ProductRequest.clinical interface)
        clinical: {
          woundType: formData?.wound_type,
          woundLocation: formData?.wound_location,
          size: formData?.wound_size_length && formData?.wound_size_width
            ? `${formData.wound_size_length} x ${formData.wound_size_width}cm`
            : 'N/A',
          depth: formData?.wound_size_depth,
          diagnosisCodes: (() => {
            const codes = [];
            if (formData?.primary_diagnosis_code) codes.push(formData.primary_diagnosis_code);
            if (formData?.secondary_diagnosis_code) codes.push(formData.secondary_diagnosis_code);
            if (formData?.yellow_diagnosis_code) codes.push(formData.yellow_diagnosis_code);
            if (formData?.orange_diagnosis_code) codes.push(formData.orange_diagnosis_code);
            if (formData?.pressure_ulcer_diagnosis_code) codes.push(formData.pressure_ulcer_diagnosis_code);
            if (Array.isArray(formData?.diagnosis_codes)) codes.push(...formData.diagnosis_codes);
            if (Array.isArray(formData?.icd10_codes)) codes.push(...formData.icd10_codes);
            return codes;
          })(),
          primaryDiagnosis: formData?.primary_diagnosis_code || formData?.wound_type,
          clinicalNotes: formData?.clinical_notes || formData?.previous_treatments,
          failedConservativeTreatment: formData?.failed_conservative_treatment || false,
          // Additional clinical fields
          wound_types: formData?.wound_types || [],
          wound_other_specify: formData?.wound_other_specify,
          wound_location_details: formData?.wound_location_details,
          wound_size_length: formData?.wound_size_length,
          wound_size_width: formData?.wound_size_width,
          wound_size_depth: formData?.wound_size_depth,
          wound_duration: formData?.wound_duration,
          wound_duration_days: formData?.wound_duration_days,
          wound_duration_weeks: formData?.wound_duration_weeks,
          wound_duration_months: formData?.wound_duration_months,
          wound_duration_years: formData?.wound_duration_years,
          previous_treatments: formData?.previous_treatments,
          previous_treatments_selected: formData?.previous_treatments_selected || {},
          information_accurate: formData?.information_accurate || false,
          medical_necessity_established: formData?.medical_necessity_established || false,
          maintain_documentation: formData?.maintain_documentation || false,

          // Diagnosis Codes (comprehensive)
          primary_diagnosis_code: formData?.primary_diagnosis_code,
          secondary_diagnosis_code: formData?.secondary_diagnosis_code,
          yellow_diagnosis_code: formData?.yellow_diagnosis_code,
          orange_diagnosis_code: formData?.orange_diagnosis_code,
          pressure_ulcer_diagnosis_code: formData?.pressure_ulcer_diagnosis_code,

          // CPT Codes
          application_cpt_codes: formData?.application_cpt_codes || [],
          application_cpt_codes_other: formData?.application_cpt_codes_other,

          // Procedure Information
          prior_applications: formData?.prior_applications,
          prior_application_product: formData?.prior_application_product,
          prior_application_within_12_months: formData?.prior_application_within_12_months,
          anticipated_applications: formData?.anticipated_applications,

          // Billing Status
          place_of_service: formData?.place_of_service,
          medicare_part_b_authorized: formData?.medicare_part_b_authorized,
          snf_days: formData?.snf_days,
          hospice_status: formData?.hospice_status,
          hospice_family_consent: formData?.hospice_family_consent,
          hospice_clinically_necessary: formData?.hospice_clinically_necessary,
          part_a_status: formData?.part_a_status,
          global_period_status: formData?.global_period_status,
          global_period_cpt: formData?.global_period_cpt,
          global_period_surgery_date: formData?.global_period_surgery_date,
        },

        // Product Information (comprehensive - matching ProductRequest.product interface)
        product: {
          name: formData?.selected_products?.[0]?.product?.name || formData?.product_name || 'N/A',
          code: formData?.selected_products?.[0]?.product?.code || formData?.product_code || formData?.selected_products?.[0]?.product?.q_code || 'N/A',
          quantity: formData?.selected_products?.[0]?.quantity || formData?.quantity || 1,
          size: formData?.selected_products?.[0]?.size || formData?.size || 'Standard',
          category: formData?.selected_products?.[0]?.product?.category || 'N/A',
          manufacturer: formData?.selected_products?.[0]?.product?.manufacturer || formData?.manufacturer || 'N/A',
          manufacturerId: formData?.manufacturer_id,
          selectedProducts: formData?.selected_products?.map((item: any) => ({
            product_id: item.product_id || item.product?.id,
            quantity: item.quantity
          })) || [],
          shippingInfo: {
            speed: formData?.shipping_speed || 'standard',
            instructions: formData?.shipping_instructions || '',
            address: formData?.facility_address ||
                     (formData?.facility_address_line1 ?
                       `${formData.facility_address_line1}${formData.facility_address_line2 ? ', ' + formData.facility_address_line2 : ''}, ${formData.facility_city || ''}, ${formData.facility_state || ''} ${formData.facility_zip || ''}` : ''),
          }
        },

        // Products Array (matching ProductRequest.products interface)
        products: formData?.selected_products?.map((item: any) => {
          // Get product details directly from item or fallback
          const product = item.product || {};
          return {
            id: item.product_id || item.product?.id || item.id,
            name: product?.name || item.product_name || 'N/A',
            q_code: product?.code || product?.q_code || item.product_code || 'N/A',
            quantity: item.quantity,
            size: item.size || 'Standard',
            unit_price: product?.price || product?.discounted_price || product?.unit_price || item.price || item.unit_price || 0,
            total_price: (product?.price || product?.discounted_price || product?.unit_price || item.price || item.unit_price || 0) * item.quantity
          };
        }) || [],

        // Provider Information (comprehensive - matching ProductRequest.provider interface)
        provider: {
          id: formData?.provider_id,
          name: formData?.provider_name || 'N/A',
          npi: formData?.provider_npi || 'N/A',
          email: formData?.provider_email || 'N/A',
          phone: formData?.provider_phone || 'N/A',
          specialty: formData?.provider_specialty || 'N/A',
          credentials: formData?.provider_credentials || 'N/A',
        },

        // Facility Information (comprehensive - matching ProductRequest.facility interface)
        facility: {
          id: formData?.facility_id,
          name: formData?.facility_name || 'N/A',
          address: formData?.facility_address ||
                   (formData?.facility_address_line1 ?
                     `${formData.facility_address_line1}${formData.facility_address_line2 ? ', ' + formData.facility_address_line2 : ''}, ${formData.facility_city || ''}, ${formData.facility_state || ''} ${formData.facility_zip || ''}` : ''),
          phone: formData?.facility_phone || 'N/A',
          fax: formData?.facility_fax || 'N/A',
          email: formData?.facility_email || 'N/A',
          npi: formData?.facility_npi || 'N/A',
          tax_id: formData?.facility_tax_id || 'N/A',
        },

        // Order Status and Forms
        ivr_status: formData?.docuseal_submission_id ? 'completed' : 'pending',
        order_form_status: formData?.order_form_status || 'pending',
        total_order_value: 0, // Will be calculated separately

        // Documents and Attachments
        documents: formData?.documents || [],
        insurance_card_front: formData?.insurance_card_front,
        insurance_card_back: formData?.insurance_card_back,
        insurance_card_auto_filled: formData?.insurance_card_auto_filled,
        face_sheet: formData?.face_sheet,
        clinical_notes: formData?.clinical_notes,
        wound_photo: formData?.wound_photo,

        // Attestations
        attestations: {
          failed_conservative_treatment: formData?.failed_conservative_treatment || false,
          information_accurate: formData?.information_accurate || false,
          medical_necessity_established: formData?.medical_necessity_established || false,
          maintain_documentation: formData?.maintain_documentation || false,
          authorize_prior_auth: formData?.authorize_prior_auth || false,
        },

        // DocuSeal Information
        docuseal: {
          submission_id: formData?.docuseal_submission_id,
          ivr_document_url: formData?.ivr_document_url,
        },

        // Additional fields that might be needed
        mac_validation_results: formData?.mac_validation_results,
        mac_validation_status: formData?.mac_validation_status,
        eligibility_results: formData?.eligibility_results,
        eligibility_status: formData?.eligibility_status,
        pre_auth_required: formData?.pre_auth_required,
        clinical_opportunities: formData?.clinical_opportunities,

        // Metadata
        metadata: {
          created_at: formData?.created_at || new Date().toISOString(),
          episode_id: formData?.episode_id,
          order_status: formData?.order_status || 'draft',
          step: formData?.step || 6,
          submission_method: 'quick_request',
          last_updated: new Date().toISOString(),
        }
      }
    }
  };
};

export default function Step6ReviewSubmit({
  formData,
  products,
  providers = [],
  facilities = [],
  errors,
  onSubmit,
  isSubmitting,
  comprehensiveData
}: Step6Props) {
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmChecked, setConfirmChecked] = useState(false);
  const [adminNote, setAdminNote] = useState('');
  const [openSections, setOpenSections] = useState({
    patient: true,
    insurance: true,
    clinical: true,
    product: true,
    provider: true,
    forms: true,
    shipping: false,
    billing: false,
  });

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

  // Create order data with proper mapping
  const orderData = mapFormDataToOrderData(formData, providers, facilities, comprehensiveData);

  // Helper function to check if order is complete
  const isOrderComplete = (): boolean => {
    return !!(
      formData?.patient_first_name &&
      formData?.patient_last_name &&
      formData?.patient_dob &&
      formData?.primary_insurance_name &&
      formData?.wound_type &&
      formData?.wound_location &&
      formData?.selected_products?.length > 0
    );
  };

  // Helper function to get selected product details from form data
  const getSelectedProductDetails = (item: any) => {
    // First try to get from the product object stored in form data
    if (item.product) {
      return item.product;
    }

    // Check if item has direct product properties
    if (item.product_name || item.product_code) {
      return {
        name: item.product_name,
        code: item.product_code || item.q_code,
        manufacturer: item.manufacturer,
        price: item.price || item.unit_price,
        discounted_price: item.discounted_price,
        description: item.description
      };
    }

    // Fallback to products array if needed
    if (item.product_id && products.length > 0) {
      return products.find(product => product.id === item.product_id);
    }

    // Last resort - return item itself if it has basic product info
    return item;
  };

  // Calculate total bill from form data
  const calculateTotalBill = () => {
    if (!formData.selected_products) return 0;

    // First try to get finalized pricing from Step5
    if (orderData.clinical_summary?.All_data?.pricing?.total_amount) {
      return orderData.clinical_summary.All_data.pricing.total_amount;
    }

    // Check Step5 products structure
    if (orderData.clinical_summary?.All_data?.products?.total_value) {
      return orderData.clinical_summary.All_data.products.total_value;
    }

    // Check Step5 products items total
    if (orderData.clinical_summary?.All_data?.products?.items) {
      const total = orderData.clinical_summary.All_data.products.items.reduce((sum: number, item: any) => {
        return sum + (item.finalized_price || item.price || 0);
      }, 0);
      if (total > 0) return total;
    }

    // Check Step5 pricing product_prices array
    if (orderData.clinical_summary?.All_data?.pricing?.product_prices) {
      const total = orderData.clinical_summary.All_data.pricing.product_prices.reduce((sum: number, price: number) => {
        return sum + (price || 0);
      }, 0);
      if (total > 0) return total;
    }

    // Fallback to calculating from form data
    return formData.selected_products.reduce((total: number, item: any) => {
      const product = getSelectedProductDetails(item);
      // Try multiple price fields
      const price = product?.price ||
                   product?.discounted_price ||
                   product?.unit_price ||
                   item.price ||
                   item.unit_price ||
                   0;
      return total + (price * item.quantity);
    }, 0);
  };

  // Debug logging for troubleshooting
  console.log('Step6ReviewSubmit Debug:', {
    formData: {
      selected_products: formData?.selected_products,
      diagnosis_codes: formData?.diagnosis_codes,
      primary_diagnosis_code: formData?.primary_diagnosis_code,
      secondary_diagnosis_code: formData?.secondary_diagnosis_code,
      wound_type: formData?.wound_type,
    },
    products: products,
    comprehensiveData: comprehensiveData,
    orderData: {
      clinical: orderData.clinical,
      product: orderData.product,
      clinical_summary: orderData.clinical_summary,
    },
    calculatedTotal: calculateTotalBill(),
    finalizedPricing: orderData.clinical_summary?.All_data?.pricing,
    step5Products: orderData.clinical_summary?.All_data?.products,
    step5Pricing: orderData.clinical_summary?.All_data?.pricing,
  });

  // Update the clinical summary with calculated totals
  if (orderData.clinical_summary?.All_data) {
    // Use finalized prices from Step5ProductSelection if available
    if (orderData.clinical_summary.All_data.pricing?.total_amount) {
      console.log('💰 Using finalized total from Step5 pricing:', orderData.clinical_summary.All_data.pricing.total_amount);
      orderData.clinical_summary.All_data.total_amount = orderData.clinical_summary.All_data.pricing.total_amount;
      orderData.clinical_summary.All_data.total_order_value = orderData.clinical_summary.All_data.pricing.total_amount;
    } else if (orderData.clinical_summary.All_data.products?.total_value) {
      console.log('💰 Using finalized total from Step5 products:', orderData.clinical_summary.All_data.products.total_value);
      orderData.clinical_summary.All_data.total_amount = orderData.clinical_summary.All_data.products.total_value;
      orderData.clinical_summary.All_data.total_order_value = orderData.clinical_summary.All_data.products.total_value;
    } else if (orderData.clinical_summary.All_data.products?.items) {
      // Calculate total from Step5 products items
      const total = orderData.clinical_summary.All_data.products.items.reduce((sum: number, item: any) => {
        return sum + (item.finalized_price || item.price || 0);
      }, 0);
      if (total > 0) {
        console.log('💰 Using calculated total from Step5 products items:', total);
        orderData.clinical_summary.All_data.total_amount = total;
        orderData.clinical_summary.All_data.total_order_value = total;
      }
    } else if (orderData.clinical_summary.All_data.pricing?.product_prices) {
      // Calculate total from Step5 pricing product_prices array
      const total = orderData.clinical_summary.All_data.pricing.product_prices.reduce((sum: number, price: number) => {
        return sum + (price || 0);
      }, 0);
      if (total > 0) {
        console.log('💰 Using calculated total from Step5 pricing product_prices:', total);
        orderData.clinical_summary.All_data.total_amount = total;
        orderData.clinical_summary.All_data.total_order_value = total;
      }
    } else {
      // Fallback to calculating total if pricing data not available
      const fallbackTotal = calculateTotalBill();
      console.log('💰 Using calculated total (fallback):', fallbackTotal);
      orderData.clinical_summary.All_data.total_amount = fallbackTotal;
      orderData.clinical_summary.All_data.total_order_value = fallbackTotal;
    }
  }

    // Toggle section visibility
  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section as keyof typeof prev]
    }));
  };

  const handleSubmit = async () => {
    if (!confirmChecked) return;

    setShowConfirmModal(false);
    if (onSubmit) {
      // Pass the admin note to the onSubmit function
      await onSubmit();
    }
  };

  // Early return if formData is not available
  if (!formData) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <p className={cn("text-center", t.text.secondary)}>Loading form data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg flex justify-between items-start", t.glass.card)}>
        <div>
          <h1 className={cn("text-2xl font-bold", t.text.primary)}>
            Review & Confirm Order
          </h1>
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Please review all information before submitting your order
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <AuthButton
            onClick={() => setShowConfirmModal(true)}
            disabled={!isOrderComplete()}
            className={cn(
              "px-6 py-2 rounded-lg font-medium transition-all",
              isOrderComplete() && !isSubmitting
                ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
                : "bg-gray-300 text-gray-500 cursor-not-allowed"
            )}
          >
            {isSubmitting ? 'Submitting...' : 'Submit Order'}
          </AuthButton>
        </div>
      </div>



      {/* Patient & Insurance Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiUser />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Patient & Insurance</h3>
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('patient')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.patient ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.patient && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Patient Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.patient.fullName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>DOB:</dt>
                  <dd className={t.text.primary}>{orderData.patient.dateOfBirth}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                  <dd className={t.text.primary}>{orderData.patient.phone}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                  <dd className={t.text.primary}>{orderData.patient.email}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                  <dd className={t.text.primary}>{orderData.patient.address}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Insurance Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Primary:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.payerName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Plan:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.planName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Policy:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.policyNumber}</dd>
                </div>
                {orderData.patient.secondaryInsurance && (
                  <>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Secondary:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.payerName}</dd>
                    </div>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Plan:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.planName}</dd>
                    </div>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Policy:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.policyNumber}</dd>
                    </div>
                  </>
                )}
              </dl>
            </div>
          </div>
        )}
      </div>

      {/* Clinical Information Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiActivity />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Clinical Information</h3>
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('clinical')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.clinical ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.clinical && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Wound Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Type:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.woundType}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Size:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.woundSize}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Location:</dt>
                  <dd className={t.text.primary}>{formData.wound_location || 'N/A'}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Prior Apps:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.priorApplications}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Anticipated:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.anticipatedApplications}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Diagnosis Codes</h4>
              <dl className="space-y-2 text-sm">
                {orderData.clinical.diagnosisCodes.length > 0 ? (
                  orderData.clinical.diagnosisCodes.map((code: any, index: number) => (
                    <div key={index} className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Code {index + 1}:</dt>
                      <dd className={t.text.primary}>{code.code} - {code.description}</dd>
                    </div>
                  ))
                ) : (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Codes:</dt>
                    <dd className={t.text.primary}>No diagnosis codes</dd>
                  </div>
                )}
              </dl>
            </div>
          </div>
        )}
      </div>

      {/* Product Selection Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiShoppingCart />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Product Selection</h3>
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('product')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.product ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.product && (
          <div>
            {formData.selected_products && formData.selected_products.length > 0 ? (
              <div className="space-y-4">
                {formData.selected_products.map((item: any, index: number) => {
                  const product = getSelectedProductDetails(item);
                  return (
                    <div key={index} className={cn("p-4 rounded-lg", t.glass.frost)}>
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className={cn("font-medium", t.text.primary)}>
                            {product?.name || item.product_name || 'Product'}
                          </h4>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Code: {product?.code || product?.q_code || item.product_code || 'N/A'}
                          </p>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Manufacturer: {product?.manufacturer || item.manufacturer || 'N/A'}
                          </p>
                          {product?.description && (
                            <p className={cn("text-sm", t.text.secondary)}>
                              {product.description}
                            </p>
                          )}
                        </div>
                        <div className="text-right">
                          <p className={cn("font-medium", t.text.primary)}>
                            Qty: {item.quantity}
                          </p>
                          {item.size && (
                            <p className={cn("text-sm", t.text.secondary)}>
                              Size: {item.size}
                            </p>
                          )}
                          <div className="mt-2">
                            <p className={cn("text-sm", t.text.secondary)}>
                              Price: ${(() => {
                                // Use finalized price from Step5 if available
                                if (orderData.clinical_summary?.All_data?.products?.product_details?.[index]?.finalized_price) {
                                  return orderData.clinical_summary.All_data.products.product_details[index].finalized_price.toFixed(2);
                                }
                                // Check for Step5 pricing structure
                                if (orderData.clinical_summary?.All_data?.pricing?.product_prices?.[index]) {
                                  return orderData.clinical_summary.All_data.pricing.product_prices[index].toFixed(2);
                                }
                                // Check for Step5 products structure
                                if (orderData.clinical_summary?.All_data?.products?.items?.[index]?.finalized_price) {
                                  return orderData.clinical_summary.All_data.products.items[index].finalized_price.toFixed(2);
                                }
                                // Fallback to product price
                                const price = product?.price || product?.discounted_price || product?.unit_price || item.price || item.unit_price || 0;
                                return price.toFixed(2);
                              })()}
                            </p>
                            {product?.discounted_price && product?.price && product.discounted_price !== product.price && (
                              <p className={cn("text-xs text-green-600", t.text.secondary)}>
                                Original: ${product.price.toFixed(2)}
                              </p>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
                <div className={cn("flex justify-between items-center pt-4 border-t", t.glass.border)}>
                  <span className={cn("font-medium", t.text.primary)}>Total ASP:</span>
                  <span className={cn("text-lg font-bold", t.text.primary)}>
                    ${(() => {
                      // Use finalized prices from Step5 if available
                      if (orderData.clinical_summary?.All_data?.pricing?.total_amount) {
                        return orderData.clinical_summary.All_data.pricing.total_amount.toFixed(2);
                      }
                      // Check Step5 products structure
                      if (orderData.clinical_summary?.All_data?.products?.total_value) {
                        return orderData.clinical_summary.All_data.products.total_value.toFixed(2);
                      }
                      // Check Step5 products items total
                      if (orderData.clinical_summary?.All_data?.products?.items) {
                        const total = orderData.clinical_summary.All_data.products.items.reduce((sum: number, item: any) => {
                          return sum + (item.finalized_price || item.price || 0);
                        }, 0);
                        if (total > 0) return total.toFixed(2);
                      }
                      // Check Step5 pricing product_prices array
                      if (orderData.clinical_summary?.All_data?.pricing?.product_prices) {
                        const total = orderData.clinical_summary.All_data.pricing.product_prices.reduce((sum: number, price: number) => {
                          return sum + (price || 0);
                        }, 0);
                        if (total > 0) return total.toFixed(2);
                      }
                      // Fallback to calculated total
                      return calculateTotalBill().toFixed(2);
                    })()}
                  </span>
                </div>
              </div>
            ) : (
              <p className={cn("text-sm", t.text.secondary)}>No products selected</p>
            )}
          </div>
        )}
      </div>

      {/* Provider & Facility Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiHome />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Provider & Facility</h3>
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('provider')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.provider ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.provider && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Provider Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.provider.name}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>NPI:</dt>
                  <dd className={t.text.primary}>{orderData.provider.npi}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Organization:</dt>
                  <dd className={t.text.primary}>{orderData.provider.organization}</dd>
                </div>
                {formData.provider_credentials && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Credentials:</dt>
                    <dd className={t.text.primary}>{formData.provider_credentials}</dd>
                  </div>
                )}
                {formData.provider_email && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                    <dd className={t.text.primary}>{formData.provider_email}</dd>
                  </div>
                )}
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Facility Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.provider.facilityName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                  <dd className={t.text.primary}>{orderData.provider.facilityAddress}</dd>
                </div>
                {formData.facility_phone && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                    <dd className={t.text.primary}>{formData.facility_phone}</dd>
                  </div>
                )}
              </dl>
            </div>
          </div>
        )}
      </div>

      {/* Forms Status Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiShield />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Forms Status</h3>
          </div>
          <button
            onClick={() => toggleSection('forms')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            {openSections.forms ? '−' : '+'}
          </button>
        </div>

        {openSections.forms && (
          <div className="space-y-4">
            <div className={cn("p-4 rounded-lg", t.glass.frost)}>
              <div className="flex justify-between items-center">
                <div>
                  <h4 className={cn("font-medium", t.text.primary)}>IVR Form</h4>
                  <p className={cn("text-sm", t.text.secondary)}>Insurance Verification Request</p>
                </div>
                <div className={cn("text-sm px-3 py-1 rounded-md", t.glass.frost)}>
                  {orderData.ivrForm.status}
                </div>
              </div>
            </div>

            <div className={cn("p-4 rounded-lg", t.glass.frost)}>
              <div className="flex justify-between items-center">
                <div>
                  <h4 className={cn("font-medium", t.text.primary)}>Order Form</h4>
                  <p className={cn("text-sm", t.text.secondary)}>Manufacturer Order Form</p>
                </div>
                <div className={cn("text-sm px-3 py-1 rounded-md", t.glass.frost)}>
                  {orderData.orderForm.status}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Submit Button at Bottom */}
      <div className="flex justify-center">
        <AuthButton
          onClick={() => setShowConfirmModal(true)}
          disabled={!isOrderComplete()}
          className={cn(
            "px-8 py-3 rounded-lg font-medium transition-all",
            isOrderComplete() && !isSubmitting
              ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          {isSubmitting ? 'Submitting...' : 'Submit Order'}
        </AuthButton>
      </div>

      {/* Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className={cn("p-6 rounded-lg max-w-md w-full mx-4", t.glass.card)}>
            <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Confirm Order Submission</h3>
            <p className={cn("text-sm mb-4", t.text.secondary)}>
              Are you sure you want to submit this order? This action cannot be undone.
            </p>

            {/* Admin Notes Section */}
            <div className="mb-4">
              <label htmlFor="admin-note" className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                Admin Notes (Optional)
              </label>
              <textarea
                id="admin-note"
                value={adminNote}
                onChange={(e) => setAdminNote(e.target.value)}
                placeholder="Add any admin notes or comments about this order..."
                className={cn(
                  "w-full h-24 p-3 rounded-lg resize-none",
                  "border border-gray-300 dark:border-gray-600",
                  "bg-white dark:bg-gray-800",
                  "text-gray-900 dark:text-gray-100",
                  "focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                )}
              />
            </div>

            <div className="flex items-center mb-4">
              <input
                type="checkbox"
                id="confirm-checkbox"
                checked={confirmChecked}
                onChange={(e) => setConfirmChecked(e.target.checked)}
                className="mr-2"
              />
              <label htmlFor="confirm-checkbox" className={cn("text-sm", t.text.secondary)}>
                I confirm that all information is accurate and complete
              </label>
            </div>
            <div className="flex justify-end space-x-3">
              <AuthButton
                onClick={() => setShowConfirmModal(false)}
                variant="secondary"
                className={cn("px-4 py-2 rounded-lg")}
              >
                Cancel
              </AuthButton>
              <AuthButton
                onClick={handleSubmit}
                disabled={!confirmChecked}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium",
                  confirmChecked && !isSubmitting
                    ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
                    : "bg-gray-300 text-gray-500 cursor-not-allowed"
                )}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Order'}
              </AuthButton>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

