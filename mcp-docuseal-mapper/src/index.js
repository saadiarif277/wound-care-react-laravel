#!/usr/bin/env node
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Manufacturer configurations mapping
const MANUFACTURER_CONFIGS = {
  'ACZ': {
    id: '1',
    name: 'ACZ',
    templateId: '852440',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {
      // Patient fields
      patient_name: 'patient_first_name + patient_last_name',
      patient_dob: 'patient_dob',
      patient_email: 'patient_email',
      patient_phone: 'patient_phone',
      patient_address: 'patient_address_line1 + patient_address_line2',
      patient_city: 'patient_city',
      patient_state: 'patient_state',
      patient_zip: 'patient_zip',
      
      // Insurance fields
      insurance_name: 'primary_insurance_name',
      member_id: 'primary_member_id',
      
      // Clinical fields
      diagnosis_code: 'primary_diagnosis_code || diagnosis_code',
      wound_type: 'wound_type',
      wound_location: 'wound_location',
      wound_size: 'wound_size_length * wound_size_width',
      
      // Provider fields
      provider_name: 'provider_name',
      provider_npi: 'provider_npi',
      facility_name: 'facility_name',
    }
  },
  'Advanced Health': {
    id: '2',
    name: 'Advanced Health',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: true,
    fields: {
      // Similar field mappings
    }
  },
  'MedLife': {
    id: '3',
    name: 'MedLife',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  },
  'Centurion': {
    id: '4',
    name: 'Centurion Therapeutics',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  },
  'BioWerX': {
    id: '5',
    name: 'BioWerX',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  },
  'BioWound': {
    id: '6',
    name: 'BioWound',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  },
  'Extremity Care': {
    id: '7',
    name: 'Extremity Care',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: true,
    fields: {}
  },
  'SKYE Biologics': {
    id: '8',
    name: 'SKYE Biologics',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  },
  'Total Ancillary': {
    id: '9',
    name: 'Total Ancillary',
    templateId: 'TBD',
    signatureRequired: true,
    hasOrderForm: false,
    fields: {}
  }
};

// Product to manufacturer mapping
const PRODUCT_MAPPINGS = {
  'EMP001': 'ACZ',
  'EMP002': 'ACZ',
  'SKN001': 'Advanced Health',
  'SKN002': 'Advanced Health',
  'MDL001': 'MedLife',
  'MDL002': 'MedLife',
  'CTN001': 'Centurion',
  'BWX001': 'BioWerX',
  'BWD001': 'BioWound',
  'EXT001': 'Extremity Care',
  'EXT002': 'Extremity Care',
  'SKY001': 'SKYE Biologics',
  'TAC001': 'Total Ancillary',
};

class DocuSealMapperServer {
  constructor() {
    this.server = new Server(
      {
        name: 'docuseal-mapper',
        version: '1.0.0',
      },
      {
        capabilities: {
          tools: {},
        },
      }
    );

    this.setupToolHandlers();
    
    // Error handling
    this.server.onerror = (error) => console.error('[MCP Error]', error);
    process.on('SIGINT', async () => {
      await this.server.close();
      process.exit(0);
    });
  }

  setupToolHandlers() {
    this.server.setRequestHandler(ListToolsRequestSchema, async () => ({
      tools: [
        {
          name: 'get_manufacturer_config',
          description: 'Get manufacturer configuration by name or product code',
          inputSchema: {
            type: 'object',
            properties: {
              identifier: {
                type: 'string',
                description: 'Manufacturer name or product code',
              },
            },
            required: ['identifier'],
          },
        },
        {
          name: 'list_manufacturers',
          description: 'List all available manufacturers and their configurations',
          inputSchema: {
            type: 'object',
            properties: {},
          },
        },
        {
          name: 'map_form_data',
          description: 'Map QuickRequest form data to DocuSeal template fields',
          inputSchema: {
            type: 'object',
            properties: {
              manufacturer: {
                type: 'string',
                description: 'Manufacturer name',
              },
              formData: {
                type: 'object',
                description: 'Form data from QuickRequest',
              },
            },
            required: ['manufacturer', 'formData'],
          },
        },
        {
          name: 'validate_mapping',
          description: 'Validate if all required fields are mapped for a manufacturer',
          inputSchema: {
            type: 'object',
            properties: {
              manufacturer: {
                type: 'string',
                description: 'Manufacturer name',
              },
              formData: {
                type: 'object',
                description: 'Form data to validate',
              },
            },
            required: ['manufacturer', 'formData'],
          },
        },
        {
          name: 'get_ivr_form_path',
          description: 'Get the IVR form PDF path for a manufacturer',
          inputSchema: {
            type: 'object',
            properties: {
              manufacturer: {
                type: 'string',
                description: 'Manufacturer name',
              },
            },
            required: ['manufacturer'],
          },
        },
      ],
    }));

    this.server.setRequestHandler(CallToolRequestSchema, async (request) => {
      try {
        const { name, arguments: args } = request.params;

        switch (name) {
          case 'get_manufacturer_config':
            return this.getManufacturerConfig(args.identifier);
          
          case 'list_manufacturers':
            return this.listManufacturers();
          
          case 'map_form_data':
            return this.mapFormData(args.manufacturer, args.formData);
          
          case 'validate_mapping':
            return this.validateMapping(args.manufacturer, args.formData);
          
          case 'get_ivr_form_path':
            return this.getIvrFormPath(args.manufacturer);
          
          default:
            throw new Error(`Unknown tool: ${name}`);
        }
      } catch (error) {
        return {
          content: [
            {
              type: 'text',
              text: `Error: ${error.message}`,
            },
          ],
        };
      }
    });
  }

  getManufacturerConfig(identifier) {
    // Check if it's a product code
    if (PRODUCT_MAPPINGS[identifier]) {
      const manufacturerName = PRODUCT_MAPPINGS[identifier];
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify({
              product_code: identifier,
              manufacturer: manufacturerName,
              config: MANUFACTURER_CONFIGS[manufacturerName],
            }, null, 2),
          },
        ],
      };
    }

    // Check if it's a manufacturer name
    const config = MANUFACTURER_CONFIGS[identifier];
    if (config) {
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify({
              manufacturer: identifier,
              config: config,
            }, null, 2),
          },
        ],
      };
    }

    throw new Error(`No configuration found for: ${identifier}`);
  }

  listManufacturers() {
    const summary = Object.entries(MANUFACTURER_CONFIGS).map(([name, config]) => ({
      name,
      id: config.id,
      templateId: config.templateId,
      signatureRequired: config.signatureRequired,
      hasOrderForm: config.hasOrderForm,
      fieldsCount: Object.keys(config.fields).length,
    }));

    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(summary, null, 2),
        },
      ],
    };
  }

  mapFormData(manufacturer, formData) {
    const config = MANUFACTURER_CONFIGS[manufacturer];
    if (!config) {
      throw new Error(`Unknown manufacturer: ${manufacturer}`);
    }

    const mappedData = {};
    
    // Map fields according to configuration
    for (const [templateField, formField] of Object.entries(config.fields)) {
      if (formField.includes('+')) {
        // Handle concatenated fields
        const parts = formField.split('+').map(f => f.trim());
        const values = parts.map(part => formData[part] || '').filter(v => v);
        mappedData[templateField] = values.join(' ');
      } else if (formField.includes('*')) {
        // Handle calculated fields
        const parts = formField.split('*').map(f => f.trim());
        const values = parts.map(part => parseFloat(formData[part]) || 0);
        mappedData[templateField] = values.reduce((a, b) => a * b, 1);
      } else if (formField.includes('||')) {
        // Handle fallback fields
        const parts = formField.split('||').map(f => f.trim());
        mappedData[templateField] = parts.find(part => formData[part]) || '';
      } else {
        // Direct mapping
        mappedData[templateField] = formData[formField] || '';
      }
    }

    // Add calculated fields
    if (formData.wound_duration_years || formData.wound_duration_months || 
        formData.wound_duration_weeks || formData.wound_duration_days) {
      const durationParts = [];
      if (formData.wound_duration_years) durationParts.push(`${formData.wound_duration_years} years`);
      if (formData.wound_duration_months) durationParts.push(`${formData.wound_duration_months} months`);
      if (formData.wound_duration_weeks) durationParts.push(`${formData.wound_duration_weeks} weeks`);
      if (formData.wound_duration_days) durationParts.push(`${formData.wound_duration_days} days`);
      mappedData.wound_duration = durationParts.join(', ');
    }

    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({
            manufacturer,
            templateId: config.templateId,
            mappedFields: mappedData,
            unmappedFormFields: Object.keys(formData).filter(
              key => !Object.values(config.fields).some(mapping => mapping.includes(key))
            ),
          }, null, 2),
        },
      ],
    };
  }

  validateMapping(manufacturer, formData) {
    const config = MANUFACTURER_CONFIGS[manufacturer];
    if (!config) {
      throw new Error(`Unknown manufacturer: ${manufacturer}`);
    }

    const requiredFields = [
      'patient_first_name',
      'patient_last_name',
      'patient_dob',
      'primary_insurance_name',
      'primary_member_id',
      'provider_name',
      'provider_npi',
    ];

    const missingFields = requiredFields.filter(field => !formData[field]);
    const isValid = missingFields.length === 0;

    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({
            manufacturer,
            isValid,
            missingFields,
            message: isValid 
              ? 'All required fields are present' 
              : `Missing ${missingFields.length} required field(s)`,
          }, null, 2),
        },
      ],
    };
  }

  async getIvrFormPath(manufacturer) {
    const ivrFormPaths = {
      'ACZ': 'docs/ivr-forms/ACZ/ACZ_IVR_Form.pdf',
      'Advanced Health': 'docs/ivr-forms/Advanced Health (Complete AA)/Template IVR Advanced Solution Universal REV2.0 copy (2).pdf',
      'BioWerX': 'docs/ivr-forms/BioWerX/BioWerX Fillable IVR Apr 2024.pdf',
      'BioWound': 'docs/ivr-forms/BioWound/California-Non-HOPD-IVR-Form.pdf',
      'Centurion Therapeutics': 'docs/ivr-forms/Centurion Therapeutics/MTF Generic Prior Auth Form (7).xls',
      'Extremity Care': 'docs/ivr-forms/Extremity Care/Q2 CompleteFT IVR.pdf',
      'MedLife': 'docs/ivr-forms/Medlife/AMNIO AMP MedLife IVR-fillable .pdf',
      'SKYE Biologics': 'docs/ivr-forms/SKYE Onboarding/WoundPlus.Patient.Insurance.Verification.Form.September2023R1 (2) (1).pdf',
      'Total Ancillary': 'docs/ivr-forms/Total Ancillary Forms/Copy of Universal_Benefits_Verification_April_23_V2 (1).pdf',
    };

    const formPath = ivrFormPaths[manufacturer];
    if (!formPath) {
      throw new Error(`No IVR form found for manufacturer: ${manufacturer}`);
    }

    // Check if file exists
    const fullPath = path.join(process.cwd(), '..', formPath);
    try {
      await fs.access(fullPath);
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify({
              manufacturer,
              formPath,
              fullPath,
              exists: true,
            }, null, 2),
          },
        ],
      };
    } catch {
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify({
              manufacturer,
              formPath,
              fullPath,
              exists: false,
              message: 'File not found at expected location',
            }, null, 2),
          },
        ],
      };
    }
  }

  async run() {
    const transport = new StdioServerTransport();
    await this.server.connect(transport);
    console.error('DocuSeal Mapper MCP server running');
  }
}

const server = new DocuSealMapperServer();
server.run();