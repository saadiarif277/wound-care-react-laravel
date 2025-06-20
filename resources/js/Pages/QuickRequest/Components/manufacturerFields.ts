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
    name: 'ACZ & Associates',
    products: ['Ensano ACA', 'Revoshield+ Amnio', 'Dermabind FM'],
    signatureRequired: true,
    docusealTemplateId: import.meta.env.VITE_DOCUSEAL_TEMPLATE_ACZ || '852440', // TODO: Move to env config
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
    name: 'Advanced Solution',
    products: ['Complete FT', 'Membrane Wrap', 'Complete AA'],
    signatureRequired: true,
    docusealTemplateId: process.env.DOCUSEAL_TEMPLATE_ADVANCED || 'template_order_form_001', // TODO: Get actual template ID
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
    name: 'MedLife Solutions',
    products: ['Amnio AMP'],
    signatureRequired: true,
    docusealTemplateId: process.env.DOCUSEAL_TEMPLATE_MEDLIFE || 'template_order_form_001', // TODO: Get actual template ID
    fields: [
      {
        name: 'amnio_amp_size',
        label: 'Amnio AMP Specific Size Required',
        type: 'radio',
        required: true,
        options: [
          { value: '2x2', label: '2×2 cm' },
          { value: '2x4', label: '2×4 cm' },
          { value: '4x4', label: '4×4 cm' },
          { value: '4x6', label: '4×6 cm' },
          { value: '4x8', label: '4×8 cm' },
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
    name: 'BioWound Solutions',
    products: ['Membrane Wrap Hydro', 'Neostim TL', 'Neostim DL', 'Neostim SL', 'Amnio-Maxx', 'Derm-maxx'],
    signatureRequired: true,
    docusealTemplateId: process.env.DOCUSEAL_TEMPLATE_BIOWOUND || 'template_order_form_001', // TODO: Get actual template ID
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
    docusealTemplateId: process.env.DOCUSEAL_TEMPLATE_SKYE || 'skye_biologics_ivr_template', // TODO: Get actual template ID
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
