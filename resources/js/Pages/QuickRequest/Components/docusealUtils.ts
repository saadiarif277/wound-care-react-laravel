// Shared DocuSeal data preparation utility for QuickRequest steps
// Minimal FormData interface for DocuSeal utility typing
interface FormData {
  [key: string]: any;
}

export function prepareDocuSealData({
  formData,
  products,
  providers = [],
  facilities = []
}: {
  formData: FormData,
  products: any[],
  providers?: any[],
  facilities?: any[]
}) {
  // Get the selected product
  const selectedProduct = formData.selected_products && formData.selected_products.length > 0
    ? products.find((p: any) => p.id === formData.selected_products[0].product_id)
    : null;

  // Get provider and facility details
  const provider = formData.provider_id ? providers.find((p: any) => p.id === formData.provider_id) : null;
  const facility = formData.facility_id ? facilities.find((f: any) => f.id === formData.facility_id) : null;

  // Format selected products for display
  const productDetails = (formData.selected_products || []).map((item: any) => {
    const prod = products.find((p: any) => p.id === item.product_id);
    return {
      name: prod?.name || '',
      code: prod?.code || '',
      size: item.size || 'Standard',
      quantity: item.quantity,
      manufacturer: prod?.manufacturer || '',
      manufacturer_id: prod?.manufacturer_id || prod?.id
    };
  });

  // Calculate total wound size
  const woundSizeLength = parseFloat(formData.wound_size_length || '0');
  const woundSizeWidth = parseFloat(formData.wound_size_width || '0');
  const totalWoundSize = woundSizeLength * woundSizeWidth;

  // Format wound types - handle both single wound_type and array wound_types
  const woundTypesDisplay = formData.wound_type || (formData.wound_types && formData.wound_types.join(', ')) || 'Not specified';

  // Format wound duration
  const durationParts = [];
  if (formData.wound_duration_years) durationParts.push(`${formData.wound_duration_years} years`);
  if (formData.wound_duration_months) durationParts.push(`${formData.wound_duration_months} months`);
  if (formData.wound_duration_weeks) durationParts.push(`${formData.wound_duration_weeks} weeks`);
  if (formData.wound_duration_days) durationParts.push(`${formData.wound_duration_days} days`);
  const woundDuration = durationParts.length > 0 ? durationParts.join(', ') : 'Not specified';

  // Format diagnosis codes
  let diagnosisCodeDisplay = '';
  if (formData.primary_diagnosis_code && formData.secondary_diagnosis_code) {
    diagnosisCodeDisplay = `Primary: ${formData.primary_diagnosis_code}, Secondary: ${formData.secondary_diagnosis_code}`;
  } else if (formData.diagnosis_code) {
    diagnosisCodeDisplay = formData.diagnosis_code;
  } else if (formData.yellow_diagnosis_code || formData.orange_diagnosis_code) {
    diagnosisCodeDisplay = formData.yellow_diagnosis_code || formData.orange_diagnosis_code || '';
  }

  // Format patient name - handle various formats
  let patientName = '';
  if (formData.patient_first_name && formData.patient_last_name) {
    patientName = `${formData.patient_first_name} ${formData.patient_last_name}`;
  } else if (formData.patient_name) {
    patientName = formData.patient_name;
  }

  // Format address
  let patientAddress = '';
  if (formData.patient_address_line1) {
    patientAddress = formData.patient_address_line1;
    if (formData.patient_address_line2) {
      patientAddress += `, ${formData.patient_address_line2}`;
    }
    if (formData.patient_city && formData.patient_state && formData.patient_zip) {
      patientAddress += `, ${formData.patient_city}, ${formData.patient_state} ${formData.patient_zip}`;
    }
  }

  // Format hospice and prior application data for display
  const formatBooleanForDisplay = (value: any) => {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (value === 'true' || value === true) return 'Yes';
    if (value === 'false' || value === false) return 'No';
    return value || 'No';
  };

  return {
    ...formData,
    
    // Patient Information - ensure all variations are included
    patient_name: patientName,
    patient_first_name: formData.patient_first_name || '',
    patient_last_name: formData.patient_last_name || '',
    patient_dob: formData.patient_dob || '',
    patient_display_id: formData.patient_display_id || '',
    patient_member_id: formData.patient_member_id || '',
    patient_phone: formData.patient_phone || '',
    patient_email: formData.patient_email || '',
    patient_address_line1: formData.patient_address_line1 || '',
    patient_address_line2: formData.patient_address_line2 || '',
    patient_city: formData.patient_city || '',
    patient_state: formData.patient_state || '',
    patient_zip: formData.patient_zip || '',
    patient_gender: formData.patient_gender || '',
    patient_address: patientAddress,
    
    // Insurance Information
    primary_insurance_name: formData.primary_insurance_name || formData.payer_name || '',
    primary_member_id: formData.primary_member_id || formData.patient_member_id || '',
    primary_plan_type: formData.primary_plan_type || '',
    group_number: formData.group_number || '',
    payer_phone: formData.payer_phone || '',
    
    // Provider Information
    provider_name: provider?.name || formData.provider_name || '',
    provider_credentials: provider?.credentials || formData.provider_credentials || '',
    provider_npi: provider?.npi || formData.provider_npi || '',
    provider_email: formData.provider_email || provider?.email || '',
    provider_tax_id: formData.provider_tax_id || '',
    
    // Facility Information
    facility_name: facility?.name || formData.facility_name || '',
    facility_address: facility?.address || formData.facility_address || '',
    
    // Product Information
    product_name: selectedProduct?.name || formData.product_name || '',
    product_code: selectedProduct?.code || formData.product_code || '',
    product_manufacturer: selectedProduct?.manufacturer || formData.product_manufacturer || '',
    manufacturer_id: selectedProduct?.manufacturer_id || selectedProduct?.id,
    product_details: productDetails,
    product_details_text: productDetails.map((p: any) => `${p.name} (${p.code}) - Size: ${p.size}, Qty: ${p.quantity}`).join('\n'),
    
    // Clinical Information
    wound_type: formData.wound_type || woundTypesDisplay,
    wound_types_display: woundTypesDisplay,
    wound_location: formData.wound_location || '',
    wound_size_length: formData.wound_size_length || '0',
    wound_size_width: formData.wound_size_width || '0', 
    wound_size_depth: formData.wound_size_depth || '0',
    total_wound_size: `${totalWoundSize} sq cm`,
    wound_dimensions: `${formData.wound_size_length || '0'} × ${formData.wound_size_width || '0'} cm`,
    wound_duration: woundDuration,
    wound_duration_days: formData.wound_duration_days || '',
    wound_duration_weeks: formData.wound_duration_weeks || '',
    wound_duration_months: formData.wound_duration_months || '',
    wound_duration_years: formData.wound_duration_years || '',
    
    // Diagnosis codes
    diagnosis_code_display: diagnosisCodeDisplay,
    diagnosis_codes_display: diagnosisCodeDisplay,
    primary_diagnosis_code: formData.primary_diagnosis_code || '',
    secondary_diagnosis_code: formData.secondary_diagnosis_code || '',
    diagnosis_code: formData.diagnosis_code || diagnosisCodeDisplay,
    
    // Prior applications
    prior_applications: formData.prior_applications || '0',
    number_of_prior_applications: formData.prior_applications || '0',
    prior_application_product: formData.prior_application_product || '',
    prior_application_within_12_months: formatBooleanForDisplay(formData.prior_application_within_12_months),
    
    // Hospice information
    hospice_status: formatBooleanForDisplay(formData.hospice_status),
    patient_in_hospice: formatBooleanForDisplay(formData.hospice_status),
    hospice_family_consent: formatBooleanForDisplay(formData.hospice_family_consent),
    hospice_clinically_necessary: formatBooleanForDisplay(formData.hospice_clinically_necessary),
    
    // Date fields
    service_date: formData.expected_service_date || formData.service_date || new Date().toISOString().split('T')[0],
    expected_service_date: formData.expected_service_date || new Date().toISOString().split('T')[0],
    signature_date: new Date().toISOString().split('T')[0],
    
    // Sales Rep Information
    sales_rep_name: formData.sales_rep_name || '',
    
    // Signature fields (for DocuSeal template)
    provider_signature_required: true,
    physician_attestation: formatBooleanForDisplay(formData.physician_attestation || formData.manufacturer_fields?.physician_attestation),
    
    // Manufacturer-specific fields (ensure boolean conversion)
    ...Object.entries(formData.manufacturer_fields || {}).reduce((acc, [key, value]) => {
      acc[key] = typeof value === 'boolean' ? formatBooleanForDisplay(value) : value;
      return acc;
    }, {} as Record<string, any>),
  };
}