// Field Mapping Type Definitions

export interface CanonicalField {
  id: number;
  category: string;
  field_name: string;
  field_path: string;
  data_type: string;
  is_required: boolean;
  description: string | null;
  validation_rules: ValidationRule[];
  hipaa_flag: boolean;
  created_at: string;
  updated_at: string;
}

export interface ValidationRule {
  type: string;
  pattern?: string;
  format?: string;
  max_length?: number;
  accepted_values?: string[];
}

export interface FieldMapping {
  id?: number;
  template_id: string;
  field_name: string;
  canonical_field_id: number | null;
  canonical_field?: CanonicalField;
  transformation_rules: TransformationRule[];
  confidence_score: number;
  validation_status: 'valid' | 'warning' | 'error';
  validation_messages: string[];
  is_active: boolean;
  created_by?: number;
  updated_by?: number;
  version?: number;
  created_at?: string;
  updated_at?: string;
}

export interface TransformationRule {
  type: 'parse' | 'format' | 'convert' | 'normalize';
  operation: string;
  parameters: Record<string, any>;
}

export interface MappingStatistics {
  totalFields: number;
  mappedFields: number;
  unmappedFields: number;
  activeFields: number;
  requiredFieldsMapped: number;
  totalRequiredFields: number;
  optionalFieldsMapped: number;
  coveragePercentage: number;
  requiredCoveragePercentage: number;
  highConfidenceCount: number;
  validationStatus: {
    valid: number;
    warning: number;
    error: number;
  };
  lastUpdated: string | null;
  lastUpdatedBy: string | null;
}

export interface BulkOperation {
  operation: 'map_by_pattern' | 'copy_from_template' | 'reset_category' | 'apply_transformation';
  parameters: Record<string, any>;
}

export interface MappingSuggestion {
  canonical_field_id: number;
  canonical_field: CanonicalField;
  confidence: number;
  method: 'pattern' | 'similarity' | 'historical';
  final_score?: number;
}

export interface ValidationResult {
  validation_results: Record<string, {
    status: 'valid' | 'warning' | 'error' | 'unmapped';
    messages: string[];
  }>;
  summary: {
    total_fields: number;
    valid: number;
    warnings: number;
    errors: number;
    unmapped: number;
  };
  missing_required_fields: Array<{
    field: string;
    category: string;
    description: string;
  }>;
  coverage_percentage: number;
  is_complete: boolean;
}

export interface MappingExportData {
  template: {
    id: string;
    name: string;
    docuseal_template_id: string;
    manufacturer: string | null;
    document_type: string;
  };
  exported_at: string;
  exported_by: string;
  statistics: MappingStatistics;
  mappings: Array<{
    field_name: string;
    canonical_field_id: number | null;
    canonical_field_name: string | null;
    canonical_field_path: string | null;
    transformation_rules: TransformationRule[];
    confidence_score: number;
    validation_status: string;
    is_active: boolean;
  }>;
}

export interface FieldMappingInterfaceProps {
  templateId: string;
  onClose: () => void;
  onUpdate: (templateId: string) => void;
}

export interface TransformationRuleOption {
  type: string;
  operations: Record<string, string>;
}

export const AVAILABLE_TRANSFORMATION_RULES: Record<string, TransformationRuleOption> = {
  parse: {
    type: 'parse',
    operations: {
      address: 'Parse combined address into components',
      name: 'Parse full name into first/last components',
      split: 'Split value by delimiter',
    },
  },
  format: {
    type: 'format',
    operations: {
      phone: 'Format phone number as (XXX) XXX-XXXX',
      date: 'Format date to specified format',
      ssn: 'Format SSN as XXX-XX-XXXX',
      taxid: 'Format Tax ID as XX-XXXXXXX',
      uppercase: 'Convert to uppercase',
      lowercase: 'Convert to lowercase',
      titlecase: 'Convert to title case',
    },
  },
  convert: {
    type: 'convert',
    operations: {
      boolean: 'Convert to boolean true/false',
      pos_code: 'Convert to Place of Service code',
      state_abbr: 'Convert state name to abbreviation',
      number: 'Convert to numeric value',
    },
  },
  normalize: {
    type: 'normalize',
    operations: {
      whitespace: 'Normalize whitespace',
      alphanumeric: 'Keep only alphanumeric characters',
      numeric: 'Keep only numeric characters',
      remove_special: 'Remove special characters',
    },
  },
};