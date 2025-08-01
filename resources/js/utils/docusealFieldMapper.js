/**
 * DocuSeal Field Mapper Utility
 *
 * This utility provides comprehensive field mapping for DocuSeal forms,
 * complementing the backend enhanced mapping system.
 *
 * Features:
 * - Text field mapping with validation
 * - Radio button mapping with exact value matching
 * - Date field formatting
 * - Conditional field handling
 * - Template field validation
 * - Fallback data sources
 */

/**
 * Map form data to DocuSeal fields with comprehensive field coverage
 * @param {Object} formData - The form data from the application
 * @param {Object} docuSealTemplate - The DocuSeal template structure
 * @param {Object} options - Additional options for mapping
 * @returns {Array} Array of DocuSeal field objects
 */
export function mapFormDataToDocuSealFields(formData, docuSealTemplate, options = {}) {
  const {
    manufacturer = 'ACZ',
    useEnhancedMapping = true,
    debug = false
  } = options;

  // Initialize the fields array that will be sent to DocuSeal
  const fields = [];

  if (debug) {
    console.log('🔍 Starting DocuSeal field mapping:', {
      formDataKeys: Object.keys(formData),
      templateFields: docuSealTemplate?.fields?.length || 0,
      manufacturer,
      useEnhancedMapping
    });
  }

  // Helper function to add a text field
  const addTextField = (templateFieldName, value) => {
    if (value === undefined || value === null || value === '') return;

    const field = docuSealTemplate.fields.find(f => f.name === templateFieldName);
    if (field) {
      fields.push({
        uuid: field.uuid,
        value: String(value),
        type: 'text'
      });

      if (debug) {
        console.log(`✅ Added text field: ${templateFieldName} = "${value}"`);
      }
    } else if (debug) {
      console.log(`❌ Field not found in template: ${templateFieldName}`);
    }
  };

  // Helper function to add a radio field
  const addRadioField = (templateFieldName, selectedValue) => {
    if (selectedValue === undefined || selectedValue === null) return;

    const field = docuSealTemplate.fields.find(f => f.name === templateFieldName);
    if (field && field.options) {
      // Find the option that matches our value (case-insensitive, whitespace-tolerant)
      const option = field.options.find(opt =>
        opt.value.toLowerCase() === String(selectedValue).toLowerCase() ||
        opt.value.replace(/\s+/g, '').toLowerCase() === String(selectedValue).replace(/\s+/g, '').toLowerCase()
      );

      if (option) {
        fields.push({
          uuid: field.uuid,
          value: option.value,
          option_uuid: option.uuid,
          type: 'radio'
        });

        if (debug) {
          console.log(`✅ Added radio field: ${templateFieldName} = "${option.value}"`);
        }
      } else if (debug) {
        console.log(`❌ Radio option not found: ${templateFieldName} = "${selectedValue}"`);
        console.log(`Available options:`, field.options.map(opt => opt.value));
      }
    } else if (debug) {
      console.log(`❌ Radio field not found or has no options: ${templateFieldName}`);
    }
  };

  // Helper function to add a date field (formatted as MM/DD/YYYY)
  const addDateField = (templateFieldName, dateString) => {
    if (!dateString) return;

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return;

    const formattedDate = `${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getDate().toString().padStart(2, '0')}/${date.getFullYear()}`;

    addTextField(templateFieldName, formattedDate);
  };

  // Helper function to get value from multiple sources
  const getValueFromSources = (sources, defaultValue = '') => {
    for (const source of sources) {
      if (source !== undefined && source !== null && source !== '') {
        return source;
      }
    }
    return defaultValue;
  };

  // Helper function to format address
  const formatAddress = (line1, line2 = '') => {
    const parts = [line1, line2].filter(part => part && part.trim());
    return parts.join(' ').trim();
  };

  // Helper function to format city, state, zip
  const formatCityStateZip = (city, state, zip) => {
    const parts = [city, state, zip].filter(part => part && part.trim());
    return parts.join(', ');
  };

  // Helper function to format caregiver info
  const formatCaregiverInfo = (name, phone, relationship) => {
    const parts = [];
    if (name) parts.push(name);
    if (relationship) parts.push(`(${relationship})`);
    if (phone) parts.push(`- ${phone}`);
    return parts.join(' ');
  };

  // Helper function to format wound size
  const formatWoundSize = (length, width, depth) => {
    const parts = [];
    if (length && width) {
      parts.push(`${length} × ${width} cm`);
    }
    if (depth) {
      parts.push(`Depth: ${depth} cm`);
    }
    return parts.join(', ');
  };

  // Helper function to calculate wound area
  const calculateWoundArea = (length, width) => {
    const area = parseFloat(length || 0) * parseFloat(width || 0);
    return area > 100 ? '> 100 SQ CM' : '< 100 SQ CM';
  };

  // Helper function to map wound location
  const mapWoundLocation = (location) => {
    const locationMapping = {
      'right_foot': 'Feet/Hands/Head',
      'left_foot': 'Feet/Hands/Head',
      'right_leg': 'Legs/Arms/Trunk',
      'left_leg': 'Legs/Arms/Trunk',
      'trunk': 'Legs/Arms/Trunk',
      'upper_extremity': 'Legs/Arms/Trunk',
      'lower_extremity': 'Legs/Arms/Trunk'
    };
    return locationMapping[location] || 'Legs/Arms/Trunk';
  };

  // Helper function to map place of service
  const mapPlaceOfService = (pos) => {
    const posMapping = {
      '11': 'POS 11',
      '22': 'POS 22',
      '24': 'POS 24',
      '12': 'POS 12',
      '32': 'POS 32',
      'office': 'POS 11',
      'outpatient_hospital': 'POS 22',
      'ambulatory_surgical_center': 'POS 24',
      'home': 'POS 12',
      'nursing_facility': 'POS 32'
    };
    return posMapping[pos] || 'POS 11';
  };

  // Helper function to map network status
  const mapNetworkStatus = (status) => {
    if (status === 'out_of_network' || status === 'out-of-network') {
      return 'Out-of-Network';
    }
    return 'In-Network';
  };

  // Helper function to map boolean to Yes/No
  const mapBooleanToYesNo = (value) => {
    if (value === true || value === 'true' || value === 1) {
      return 'Yes';
    }
    return 'No';
  };

  // ===== PRODUCT SELECTION =====

  // Product Q Code (radio button)
  if (formData.selected_products && formData.selected_products.length > 0) {
    const product = formData.selected_products[0].product;
    const productCode = product?.q_code || product?.code;
    if (productCode) {
      addRadioField('Product Q Code', productCode);
    }
  }

  // ===== REPRESENTATIVE INFORMATION =====

  // Sales Rep
  const salesRep = getValueFromSources([
    formData.sales_rep_name,
    formData.sales_rep,
    formData.organization_sales_rep_name,
    formData.provider_name,
    formData.physician_name,
    'MSC Wound Care'
  ]);
  addTextField('Sales Rep', salesRep);

  // ISO if applicable
  const isoNumber = getValueFromSources([
    formData.iso_number,
    formData.iso_if_applicable,
    formData.iso_id
  ]);
  addTextField('ISO if applicable', isoNumber);

  // Additional Emails for Notification
  const additionalEmails = getValueFromSources([
    formData.additional_emails,
    formData.additional_notification_emails,
    formData.notification_emails
  ]);
  addTextField('Additional Emails for Notification', additionalEmails);

  // ===== PHYSICIAN INFORMATION =====

  // Physician Name
  const physicianName = getValueFromSources([
    formData.physician_name,
    formData.provider_name,
    'Dr. Provider'
  ]);
  addTextField('Physician Name', physicianName);

  // Physician NPI
  addTextField('Physician NPI', formData.physician_npi || formData.provider_npi);

  // Physician Specialty
  addTextField('Physician Specialty', formData.physician_specialty || formData.provider_specialty || 'Wound Care');

  // Physician Tax ID
  addTextField('Physician Tax ID', formData.physician_tax_id || formData.provider_tax_id);

  // Physician PTAN
  addTextField('Physician PTAN', formData.physician_ptan || formData.provider_ptan);

  // Physician Medicaid #
  addTextField('Physician Medicaid #', formData.physician_medicaid || formData.provider_medicaid);

  // Physician Phone #
  addTextField('Physician Phone #', formData.physician_phone || formData.provider_phone);

  // Physician Fax #
  addTextField('Physician Fax #', formData.physician_fax || formData.provider_fax);

  // Physician Organization
  const physicianOrg = getValueFromSources([
    formData.physician_organization,
    formData.provider_organization,
    formData.organization_name,
    'MSC Wound Care'
  ]);
  addTextField('Physician Organization', physicianOrg);

  // ===== FACILITY INFORMATION =====

  // Facility NPI
  addTextField('Facility NPI', formData.facility_npi || formData.organization_npi);

  // Facility Tax ID
  addTextField('Facility Tax ID', formData.facility_tax_id || formData.organization_tax_id);

  // Facility Name
  const facilityName = getValueFromSources([
    formData.facility_name,
    formData.organization_name,
    'MSC Wound Care Facility'
  ]);
  addTextField('Facility Name', facilityName);

  // Facility PTAN
  addTextField('Facility PTAN', formData.facility_ptan || formData.organization_ptan);

  // Facility Address
  const facilityAddress = formatAddress(
    formData.facility_address || formData.facility_address_line1,
    formData.facility_address_line2
  );
  addTextField('Facility Address', facilityAddress);

  // Facility Medicaid #
  addTextField('Facility Medicaid #', formData.facility_medicaid || formData.organization_medicaid);

  // Facility City, State, Zip
  const facilityCityStateZip = formatCityStateZip(
    formData.facility_city,
    formData.facility_state,
    formData.facility_zip
  );
  addTextField('Facility City, State, Zip', facilityCityStateZip);

  // Facility Phone #
  addTextField('Facility Phone #', formData.facility_phone || formData.organization_phone);

  // Facility Contact Name
  addTextField('Facility Contact Name', formData.facility_contact_name || formData.organization_contact_name);

  // Facility Fax #
  addTextField('Facility Fax #', formData.facility_fax || formData.organization_fax);

  // Facility Contact Phone # / Facility Contact Email
  const contactPhone = formData.facility_contact_phone || formData.organization_contact_phone;
  const contactEmail = formData.facility_contact_email || formData.organization_contact_email;
  if (contactPhone && contactEmail) {
    addTextField('Facility Contact Phone # / Facility Contact Email', `${contactPhone} / ${contactEmail}`);
  } else if (contactPhone) {
    addTextField('Facility Contact Phone # / Facility Contact Email', contactPhone);
  } else if (contactEmail) {
    addTextField('Facility Contact Phone # / Facility Contact Email', contactEmail);
  }

  // Facility Organization
  const facilityOrg = getValueFromSources([
    formData.facility_organization,
    formData.organization_name
  ]);
  addTextField('Facility Organization', facilityOrg);

  // ===== PLACE OF SERVICE =====

  // Place of Service (radio button)
  const posValue = mapPlaceOfService(formData.place_of_service || formData.pos);
  addRadioField('Place of Service', posValue);

  // POS Other Specify (conditional)
  if (posValue === 'Other') {
    addTextField('POS Other Specify', formData.place_of_service || formData.pos);
  }

  // ===== PATIENT INFORMATION =====

  // Patient Name
  const patientName = getValueFromSources([
    formData.patient_name,
    `${formData.patient_first_name || ''} ${formData.patient_last_name || ''}`.trim(),
    `${formData.fhir_patient_first_name || ''} ${formData.fhir_patient_last_name || ''}`.trim()
  ]);
  addTextField('Patient Name', patientName);

  // Patient DOB
  addDateField('Patient DOB', formData.patient_dob || formData.fhir_patient_birth_date);

  // Patient Address
  const patientAddress = formatAddress(
    formData.patient_address || formData.patient_address_line1 || formData.fhir_patient_address_line1,
    formData.patient_address_line2
  );
  addTextField('Patient Address', patientAddress);

  // Patient City, State, Zip
  const patientCityStateZip = formatCityStateZip(
    formData.patient_city || formData.fhir_patient_city,
    formData.patient_state || formData.fhir_patient_state,
    formData.patient_zip || formData.fhir_patient_zip
  );
  addTextField('Patient City, State, Zip', patientCityStateZip);

  // Patient Phone #
  addTextField('Patient Phone #', formData.patient_phone || formData.fhir_patient_phone || formData.patient_phone_number);

  // Patient Email
  addTextField('Patient Email', formData.patient_email || formData.fhir_patient_email || formData.patient_email_address);

  // Patient Caregiver Info
  const caregiverInfo = formatCaregiverInfo(
    formData.patient_caregiver_name || formData.caregiver_name,
    formData.patient_caregiver_phone || formData.caregiver_phone,
    formData.patient_caregiver_relationship || formData.caregiver_relationship
  );
  addTextField('Patient Caregiver Info', caregiverInfo);

  // ===== INSURANCE INFORMATION =====

  // Primary Insurance Name
  addTextField('Primary Insurance Name', formData.primary_insurance_name || formData.primary_payer_name);

  // Secondary Insurance Name
  addTextField('Secondary Insurance Name', formData.secondary_insurance_name || formData.secondary_payer_name);

  // Primary Policy Number
  addTextField('Primary Policy Number', formData.primary_policy_number || formData.primary_member_id || formData.fhir_coverage_subscriber_id);

  // Secondary Policy Number
  addTextField('Secondary Policy Number', formData.secondary_policy_number || formData.secondary_member_id);

  // Primary Payer Phone #
  addTextField('Primary Payer Phone #', formData.primary_payer_phone || formData.primary_insurance_phone);

  // Secondary Payer Phone #
  addTextField('Secondary Payer Phone #', formData.secondary_payer_phone || formData.secondary_insurance_phone);

  // ===== NETWORK STATUS =====

  // Physician Status With Primary
  const primaryStatus = mapNetworkStatus(formData.primary_physician_network_status || formData.provider_status);
  addRadioField('Physician Status With Primary', primaryStatus);

  // Physician Status With Secondary
  const secondaryStatus = mapNetworkStatus(formData.secondary_physician_network_status || formData.secondary_provider_status);
  addRadioField('Physician Status With Secondary', secondaryStatus);

  // ===== AUTHORIZATION QUESTIONS =====

  // Permission To Initiate And Follow Up On Prior Auth?
  const priorAuthPermission = mapBooleanToYesNo(formData.prior_auth_permission);
  addRadioField('Permission To Initiate And Follow Up On Prior Auth?', priorAuthPermission);

  // Is The Patient Currently in Hospice?
  const hospiceStatus = mapBooleanToYesNo(formData.hospice_status);
  addRadioField('Is The Patient Currently in Hospice?', hospiceStatus);

  // Is The Patient In A Facility Under Part A Stay?
  const partAStatus = mapBooleanToYesNo(formData.part_a_status);
  addRadioField('Is The Patient In A Facility Under Part A Stay?', partAStatus);

  // Is The Patient Under Post-Op Global Surgery Period?
  const globalSurgeryStatus = mapBooleanToYesNo(formData.global_period_status);
  addRadioField('Is The Patient Under Post-Op Global Surgery Period?', globalSurgeryStatus);

  // ===== CONDITIONAL SURGERY FIELDS =====

  // If Yes, List Surgery CPTs (conditional on global surgery = "Yes")
  if (globalSurgeryStatus === 'Yes') {
    const surgeryCpts = getValueFromSources([
      formData.surgery_cpts,
      formData.surgery_codes,
      formData.global_surgery_cpts,
      formData.post_op_cpts,
      formData.application_cpt_codes?.join(', ')
    ]);
    addTextField('If Yes, List Surgery CPTs', surgeryCpts);
  }

  // Surgery Date (conditional on global surgery = "Yes")
  if (globalSurgeryStatus === 'Yes') {
    addDateField('Surgery Date', formData.surgery_date || formData.global_surgery_date || formData.post_op_date || formData.expected_service_date);
  }

  // ===== CLINICAL INFORMATION =====

  // Location of Wound
  const woundLocation = mapWoundLocation(formData.wound_location);
  const woundSizeCategory = calculateWoundArea(formData.wound_size_length, formData.wound_size_width);
  const woundLocationValue = `${woundLocation} ${woundSizeCategory}`;
  addRadioField('Location of Wound', woundLocationValue);

  // ICD-10 Codes
  const icdCodes = [];
  if (formData.primary_diagnosis_code) icdCodes.push(formData.primary_diagnosis_code);
  if (formData.secondary_diagnosis_code) icdCodes.push(formData.secondary_diagnosis_code);
  addTextField('ICD-10 Codes', icdCodes.join(', '));

  // Total Wound Size
  const woundSize = formatWoundSize(
    formData.wound_size_length,
    formData.wound_size_width,
    formData.wound_size_depth
  );
  addTextField('Total Wound Size', woundSize);

  // Medical History
  const medicalHistoryParts = [];
  if (formData.wound_type) medicalHistoryParts.push(`Wound Type: ${formData.wound_type}`);
  if (formData.wound_duration_weeks) medicalHistoryParts.push(`Duration: ${formData.wound_duration_weeks} weeks`);
  if (formData.prior_application_product) medicalHistoryParts.push(`Prior Treatment: ${formData.prior_application_product}`);
  if (formData.medical_history) medicalHistoryParts.push(formData.medical_history);
  if (formData.clinical_notes) medicalHistoryParts.push(formData.clinical_notes);

  const medicalHistory = medicalHistoryParts.length > 0
    ? medicalHistoryParts.join('; ')
    : 'Patient presents with wound requiring treatment.';
  addTextField('Medical History', medicalHistory);

  if (debug) {
    console.log('✅ DocuSeal field mapping completed:', {
      totalFields: fields.length,
      mappedFields: fields.map(f => f.name || f.uuid),
      manufacturer,
      useEnhancedMapping
    });
  }

  return fields;
}

/**
 * Validate DocuSeal template structure
 * @param {Object} template - The DocuSeal template to validate
 * @returns {Object} Validation result
 */
export function validateDocuSealTemplate(template) {
  const errors = [];
  const warnings = [];

  if (!template) {
    errors.push('Template is null or undefined');
    return { valid: false, errors, warnings };
  }

  if (!template.fields || !Array.isArray(template.fields)) {
    errors.push('Template fields is not an array');
    return { valid: false, errors, warnings };

  }

  if (template.fields.length === 0) {
    warnings.push('Template has no fields');
  }

  // Check for required fields
  const requiredFields = [
    'Patient Name',
    'Physician Name',
    'Facility Name'
  ];

  const fieldNames = template.fields.map(f => f.name);

  for (const requiredField of requiredFields) {
    if (!fieldNames.includes(requiredField)) {
      warnings.push(`Required field not found: ${requiredField}`);
    }
  }

  return {
    valid: errors.length === 0,
    errors,
    warnings,
    fieldCount: template.fields.length,
    fieldNames
  };
}

/**
 * Get field statistics for debugging
 * @param {Array} fields - The mapped fields
 * @param {Object} template - The DocuSeal template
 * @returns {Object} Statistics about the mapping
 */
export function getFieldMappingStats(fields, template) {
  const fieldTypes = {};
  const mappedFieldNames = [];
  const unmappedFieldNames = [];

  // Count field types
  fields.forEach(field => {
    const type = field.type || 'unknown';
    fieldTypes[type] = (fieldTypes[type] || 0) + 1;
    if (field.name) {
      mappedFieldNames.push(field.name);
    }
  });

  // Find unmapped fields
  if (template && template.fields) {
    template.fields.forEach(templateField => {
      if (!mappedFieldNames.includes(templateField.name)) {
        unmappedFieldNames.push(templateField.name);
      }
    });
  }

  return {
    totalMapped: fields.length,
    fieldTypes,
    mappedFieldNames,
    unmappedFieldNames,
    mappingCoverage: template && template.fields
      ? Math.round((fields.length / template.fields.length) * 100)
      : 0
  };
}

export default {
  mapFormDataToDocuSealFields,
  validateDocuSealTemplate,
  getFieldMappingStats
};
