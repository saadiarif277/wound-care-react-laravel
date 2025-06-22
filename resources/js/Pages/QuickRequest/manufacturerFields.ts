export interface ManufacturerField {
  name: string;
  label: string;
  type: 'checkbox' | 'text' | 'select' | 'radio' | 'date';
  required?: boolean;
  options?: { value: string; label: string }[];
  description?: string;
  placeholder?: string;
  conditionalOn?: { field: string; value: any };
  // DocuSeal embedded field tag information
  docusealFieldName?: string; // Custom field name if different from 'name'
  docusealFieldType?: 'text' | 'checkbox' | 'select' | 'radio' | 'date' | 'signature';
  docusealAttributes?: string; // Additional DocuSeal attributes like "required=true;role=Provider"
}

export interface ManufacturerConfig {
  name: string;
  products: string[];
  signatureRequired: boolean;
  docusealTemplateId?: string;
  fields: ManufacturerField[];
}

export const manufacturerConfigs: ManufacturerConfig[] = [
  {
    name: 'ACZ',
    products: ['Membrane Wrap', 'Revoshield'],
    signatureRequired: true,
    docusealTemplateId: '123456', // Replace with your actual DocuSeal template ID (numeric)
    fields: [
      {
        name: 'physician_attestation',
        label: 'Physician attestation of medical necessity',
        type: 'checkbox',
        required: true,
      },
      {
        name: 'not_used_previously',
        label: 'Product has not been used on this wound previously',
        type: 'checkbox',
        required: true,
      },
    ],
  },
  {
    name: 'Advanced Health',
    products: ['CompleteAA', 'CompleteFT', 'WoundPlus'],
    signatureRequired: true,
    docusealTemplateId: '123457', // Replace with your actual DocuSeal template ID (numeric)
    fields: [
      {
        name: 'multiple_products',
        label: 'Multiple products being applied in same session?',
        type: 'checkbox',
      },
      {
        name: 'additional_products',
        label: 'If yes, list additional products',
        type: 'text',
        placeholder: 'List additional products...',
        conditionalOn: { field: 'multiple_products', value: true },
      },
      {
        name: 'previous_use',
        label: 'Previous use of Advanced Health products?',
        type: 'checkbox',
      },
      {
        name: 'previous_product_info',
        label: 'If yes, which product and date',
        type: 'text',
        placeholder: 'Product name and date...',
        conditionalOn: { field: 'previous_use', value: true },
      },
    ],
  },
  {
    name: 'MedLife',
    products: ['Amnio AMP'],
    signatureRequired: true,
    docusealTemplateId: '1233913', // Replace with your actual DocuSeal template ID (numeric)
    fields: [
      {
        name: 'amnio_amp_size',
        label: 'Amnio AMP Specific Size Required',
        type: 'radio',
        required: true,
        options: [
          { value: '2x2', label: '2�2 cm' },
          { value: '2x4', label: '2�4 cm' },
          { value: '4x4', label: '4�4 cm' },
          { value: '4x6', label: '4�6 cm' },
          { value: '4x8', label: '4�8 cm' },
        ],
      },
    ],
  },
  {
    name: 'Centurion',
    products: ['AmnioBand', 'Allopatch'],
    signatureRequired: false,
    fields: [
      {
        name: 'previous_amnion_use',
        label: 'Previous amnion/chorion product use?',
        type: 'checkbox',
      },
      {
        name: 'previous_product',
        label: 'Product',
        type: 'text',
        placeholder: 'Product name',
        conditionalOn: { field: 'previous_amnion_use', value: true },
      },
      {
        name: 'previous_date',
        label: 'Date',
        type: 'date',
        conditionalOn: { field: 'previous_amnion_use', value: true },
      },
      {
        name: 'stat_order',
        label: 'STAT/After-hours order (expedited processing)',
        type: 'checkbox',
      },
    ],
  },
  {
    name: 'BioWerX',
    products: [],
    signatureRequired: false,
    fields: [
      {
        name: 'first_application',
        label: 'First application to this wound',
        type: 'checkbox',
      },
      {
        name: 'reapplication',
        label: 'Reapplication',
        type: 'checkbox',
      },
      {
        name: 'previous_product',
        label: 'Specify previous product',
        type: 'text',
        placeholder: 'Previous product name',
        conditionalOn: { field: 'reapplication', value: true },
      },
    ],
  },
  {
    name: 'BioWound',
    products: ['Membrane Wrap', 'Derm-Maxx', 'Bio-Connekt', 'NeoStim', 'Amnio-Maxx'],
    signatureRequired: true,
    docusealTemplateId: '123459', // Replace with your actual DocuSeal template ID (numeric)
    fields: [
      {
        name: 'california_facility',
        label: 'Non-HOPD facility certification (California facilities only)',
        type: 'checkbox',
        description: 'For California facilities only',
      },
      {
        name: 'mesh_configuration',
        label: 'Mesh Configuration (NeoStim products only)',
        type: 'radio',
        options: [
          { value: 'DL', label: 'DL' },
          { value: 'TL', label: 'TL' },
          { value: 'SL', label: 'SL' },
        ],
        description: 'For NeoStim products only',
      },
      {
        name: 'previous_biologics_failed',
        label: 'Previous biologics failed',
        type: 'checkbox',
      },
      {
        name: 'failed_biologics_list',
        label: 'Specify failed biologics',
        type: 'text',
        placeholder: 'List failed biologics...',
        conditionalOn: { field: 'previous_biologics_failed', value: true },
      },
    ],
  },
  {
    name: 'Extremity Care',
    products: ['Coll-e-Derm', 'CompleteFT', 'Restorigin'],
    signatureRequired: false,
    fields: [
      {
        name: 'quarter',
        label: 'Quarter',
        type: 'radio',
        required: true,
        options: [
          { value: 'Q1', label: 'Q1' },
          { value: 'Q2', label: 'Q2' },
          { value: 'Q3', label: 'Q3' },
          { value: 'Q4', label: 'Q4' },
        ],
      },
      {
        name: 'order_type',
        label: 'Order Type',
        type: 'radio',
        required: true,
        options: [
          { value: 'standing', label: 'Standing order/Quarterly shipment' },
          { value: 'single', label: 'Single application order' },
        ],
      },
    ],
  },
  {
    name: 'Skye Biologics',
    products: ['WoundPlus'],
    signatureRequired: true,
    docusealTemplateId: '123460', // Replace with your actual DocuSeal template ID (numeric)
    fields: [
      {
        name: 'shipping_speed_required',
        label: 'Shipping Speed Required',
        type: 'radio',
        required: true,
        options: [
          { value: 'standard_ground', label: 'Standard Ground' },
          { value: 'next_day_air', label: 'Next Day Air' },
          { value: 'next_day_air_early', label: 'Next Day Air Early AM' },
          { value: 'saturday_delivery', label: 'Saturday Delivery' },
        ],
      },
      {
        name: 'temperature_controlled',
        label: 'Temperature-controlled shipping required',
        type: 'checkbox',
      },
    ],
  },
  {
    name: 'Total Ancillary Forms',
    products: [],
    signatureRequired: false,
    fields: [
      {
        name: 'universal_benefits_verified',
        label: 'Universal benefits verification completed',
        type: 'checkbox',
      },
      {
        name: 'facility_account_number',
        label: 'Facility account number',
        type: 'text',
        placeholder: 'Enter account number...',
      },
    ],
  },
];

export function getManufacturerConfig(manufacturer: string): ManufacturerConfig | undefined {
  return manufacturerConfigs.find(config =>
    config.name.toLowerCase() === manufacturer.toLowerCase()
  );
}

export function getManufacturerByProduct(productName: string): ManufacturerConfig | undefined {
  return manufacturerConfigs.find(config =>
    config.products.some(p => p.toLowerCase() === productName.toLowerCase())
  );
}

// Helper function to validate manufacturer fields
export function validateManufacturerFields(
  manufacturerConfig: ManufacturerConfig,
  fields: Record<string, any>
): Record<string, string> {
  const errors: Record<string, string> = {};

  manufacturerConfig.fields.forEach(field => {
    // Check if field should be shown based on conditional
    if (field.conditionalOn) {
      const dependentValue = fields[field.conditionalOn.field];
      if (dependentValue !== field.conditionalOn.value) {
        return; // Skip validation for hidden fields
      }
    }

    // Validate required fields
    if (field.required) {
      const value = fields[field.name];
      if (!value || (typeof value === 'string' && value.trim() === '')) {
        errors[field.name] = `${field.label} is required`;
      }
    }
  });

  return errors;
}

// Helper function to get all required documents for a manufacturer
export function getManufacturerDocumentRequirements(manufacturer: string): string[] {
  const requirements: string[] = [
    'Face sheet or patient demographics',
    'Clinical notes supporting medical necessity',
    'Wound photo (if available)',
  ];

  const config = getManufacturerConfig(manufacturer);
  if (config?.signatureRequired) {
    requirements.push('Provider signature and attestations');
  }

  // Add manufacturer-specific requirements
  switch (manufacturer.toLowerCase()) {
    case 'biowound':
      requirements.push('Non-HOPD certification (California facilities)');
      break;
    case 'advanced health':
      requirements.push('Documentation of previous product use (if applicable)');
      break;
    case 'extremity care':
      requirements.push('Quarterly standing order documentation (if applicable)');
      break;
  }

  return requirements;
}
