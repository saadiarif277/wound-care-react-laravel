import { format, parseISO, isValid } from 'date-fns';

/**
 * Unified field mapping utilities for frontend
 * Consolidates all field formatting, transformation, and mapping logic
 */

// Import existing formatters to maintain compatibility
export { 
  formatCurrency, 
  formatPercentage, 
  formatFileSize, 
  formatNumber, 
  formatDuration, 
  truncateText, 
  formatPatientDisplayId 
} from './formatters';

/**
 * Date formatting with multiple format support
 */
export const formatDate = (
  date: string | Date | null | undefined,
  formatString: string = 'MM/dd/yyyy'
): string => {
  if (!date) return '';

  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date;
    if (!isValid(dateObj)) return '';
    return format(dateObj, formatString);
  } catch (error) {
    console.warn('Date formatting error:', error);
    return '';
  }
};

/**
 * Phone formatting with multiple format support
 */
export const formatPhone = (
  phone: string | null | undefined,
  format: 'US' | 'E164' | 'DIGITS' = 'US'
): string => {
  if (!phone) return '';

  // Remove all non-digit characters
  const digits = phone.replace(/\D/g, '');

  switch (format) {
    case 'US':
      if (digits.length === 10) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
      } else if (digits.length === 11 && digits[0] === '1') {
        return `+1 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7)}`;
      }
      break;
    
    case 'E164':
      if (digits.length === 10) {
        return `+1${digits}`;
      } else if (digits.length === 11 && digits[0] === '1') {
        return `+${digits}`;
      }
      break;
    
    case 'DIGITS':
      return digits;
  }

  return phone; // Return original if can't format
};

/**
 * Boolean formatting with multiple output formats
 */
export const formatBoolean = (
  value: any,
  format: 'yes_no' | '1_0' | 'true_false' | 'Y_N' = 'yes_no'
): string => {
  // Normalize to boolean
  const isTrue = ['yes', '1', 'true', 'on', 'y'].includes(
    String(value).toLowerCase()
  ) || value === true || value === 1;

  switch (format) {
    case 'yes_no':
      return isTrue ? 'Yes' : 'No';
    case '1_0':
      return isTrue ? '1' : '0';
    case 'true_false':
      return isTrue ? 'true' : 'false';
    case 'Y_N':
      return isTrue ? 'Y' : 'N';
    default:
      return isTrue ? 'Yes' : 'No';
  }
};

/**
 * Address formatting with flexible input
 */
export const formatAddress = (address: {
  line1?: string;
  line2?: string;
  city?: string;
  state?: string;
  postal_code?: string;
  zip?: string;
  address_line1?: string;
  address_line2?: string;
}, format: 'full' | 'single_line' = 'full'): string => {
  const line1 = address.line1 || address.address_line1 || '';
  const line2 = address.line2 || address.address_line2 || '';
  const city = address.city || '';
  const state = address.state || '';
  const zip = address.postal_code || address.zip || '';

  const parts = [];
  
  if (line1) parts.push(line1);
  if (line2) parts.push(line2);
  
  const cityStateZip = [];
  if (city) cityStateZip.push(city);
  if (state) cityStateZip.push(state);
  if (zip) cityStateZip.push(zip);
  
  if (cityStateZip.length > 0) {
    parts.push(cityStateZip.join(', '));
  }
  
  return format === 'full' 
    ? parts.join('\n') 
    : parts.join(', ');
};

/**
 * Field mapping configuration interface
 */
export interface FieldMappingConfig {
  source: string;
  target: string;
  transform?: (value: any) => any;
  defaultValue?: any;
  required?: boolean;
}

/**
 * Map fields from source to target based on configuration
 */
export function mapFields(
  sourceData: Record<string, any>,
  mappingConfig: FieldMappingConfig[]
): Record<string, any> {
  const result: Record<string, any> = {};

  mappingConfig.forEach(config => {
    // Get value from source using dot notation support
    const value = getValueByPath(sourceData, config.source);
    
    // Apply transformation if specified
    const transformedValue = config.transform 
      ? config.transform(value) 
      : value;
    
    // Use default if value is null/undefined
    const finalValue = transformedValue ?? config.defaultValue;
    
    // Set in result using dot notation support
    setValueByPath(result, config.target, finalValue);
  });

  return result;
}

/**
 * Get value from object using dot notation path
 */
export function getValueByPath(obj: any, path: string): any {
  const keys = path.split('.');
  let value = obj;

  for (const key of keys) {
    if (value == null) return undefined;
    value = value[key];
  }

  return value;
}

/**
 * Set value in object using dot notation path
 */
export function setValueByPath(obj: any, path: string, value: any): void {
  const keys = path.split('.');
  const lastKey = keys.pop()!;
  
  let target = obj;
  for (const key of keys) {
    if (!(key in target)) {
      target[key] = {};
    }
    target = target[key];
  }
  
  target[lastKey] = value;
}

/**
 * Common field transformations
 */
export const fieldTransformers = {
  // Date transformers
  toMDY: (value: any) => formatDate(value, 'MM/dd/yyyy'),
  toDMY: (value: any) => formatDate(value, 'dd/MM/yyyy'),
  toISO: (value: any) => formatDate(value, 'yyyy-MM-dd'),
  
  // Phone transformers
  toUSPhone: (value: any) => formatPhone(value, 'US'),
  toE164Phone: (value: any) => formatPhone(value, 'E164'),
  toDigitsPhone: (value: any) => formatPhone(value, 'DIGITS'),
  
  // Boolean transformers
  toYesNo: (value: any) => formatBoolean(value, 'yes_no'),
  toOneZero: (value: any) => formatBoolean(value, '1_0'),
  toTrueFalse: (value: any) => formatBoolean(value, 'true_false'),
  toYN: (value: any) => formatBoolean(value, 'Y_N'),
  
  // Text transformers
  toUpper: (value: any) => String(value).toUpperCase(),
  toLower: (value: any) => String(value).toLowerCase(),
  toTitle: (value: any) => String(value).split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' '),
  
  // Number transformers
  toInteger: (value: any) => Math.round(Number(value) || 0),
  toFixed2: (value: any) => Number(value).toFixed(2),
  toPercentage: (value: any) => `${Number(value).toFixed(1)}%`,
};

/**
 * Validate field mapping results
 */
export interface ValidationResult {
  valid: boolean;
  errors: string[];
  warnings: string[];
}

export function validateFieldMapping(
  data: Record<string, any>,
  requiredFields: string[]
): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Check required fields
  requiredFields.forEach(field => {
    const value = getValueByPath(data, field);
    if (value === null || value === undefined || value === '') {
      errors.push(`Required field '${field}' is missing`);
    }
  });

  // Check common field formats
  const phoneFields = ['phone', 'patient_phone', 'provider_phone', 'facility_phone'];
  const emailFields = ['email', 'patient_email', 'provider_email'];
  const dateFields = ['dob', 'patient_dob', 'date_of_birth', 'service_date'];

  // Validate phone numbers
  phoneFields.forEach(field => {
    const value = getValueByPath(data, field);
    if (value && !/^\+?1?\d{10,11}$/.test(value.replace(/\D/g, ''))) {
      warnings.push(`Field '${field}' may have invalid phone format`);
    }
  });

  // Validate emails
  emailFields.forEach(field => {
    const value = getValueByPath(data, field);
    if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      warnings.push(`Field '${field}' may have invalid email format`);
    }
  });

  // Validate dates
  dateFields.forEach(field => {
    const value = getValueByPath(data, field);
    if (value) {
      try {
        const date = parseISO(value);
        if (!isValid(date)) {
          warnings.push(`Field '${field}' may have invalid date format`);
        }
      } catch {
        warnings.push(`Field '${field}' may have invalid date format`);
      }
    }
  });

  return {
    valid: errors.length === 0,
    errors,
    warnings
  };
}

/**
 * Calculate field completeness
 */
export interface CompletenessResult {
  percentage: number;
  filled: number;
  total: number;
  missing: string[];
}

export function calculateFieldCompleteness(
  data: Record<string, any>,
  fields: string[]
): CompletenessResult {
  const missing: string[] = [];
  let filled = 0;

  fields.forEach(field => {
    const value = getValueByPath(data, field);
    if (value !== null && value !== undefined && value !== '') {
      filled++;
    } else {
      missing.push(field);
    }
  });

  return {
    percentage: fields.length > 0 ? Math.round((filled / fields.length) * 100) : 0,
    filled,
    total: fields.length,
    missing
  };
}

/**
 * Field aliases for common variations
 */
export const fieldAliases: Record<string, string[]> = {
  'patient_first_name': ['first_name', 'fname', 'patient_fname', 'firstName'],
  'patient_last_name': ['last_name', 'lname', 'patient_lname', 'lastName'],
  'patient_dob': ['date_of_birth', 'dob', 'birth_date', 'birthDate'],
  'patient_phone': ['phone', 'phone_number', 'telephone', 'contact_phone'],
  'patient_email': ['email', 'email_address', 'contact_email'],
  'primary_insurance_name': ['insurance_name', 'payer_name', 'insurance_company'],
  'primary_member_id': ['member_id', 'subscriber_id', 'insurance_id'],
  'provider_npi': ['npi', 'provider_number', 'npi_number'],
};

/**
 * Find best matching field from aliases
 */
export function findFieldWithAliases(
  data: Record<string, any>,
  targetField: string
): { field: string; value: any } | null {
  // Try exact match first
  if (data[targetField] !== undefined) {
    return { field: targetField, value: data[targetField] };
  }

  // Try aliases
  const aliases = fieldAliases[targetField] || [];
  for (const alias of aliases) {
    if (data[alias] !== undefined) {
      return { field: alias, value: data[alias] };
    }
  }

  // Try case-insensitive match
  const lowerTarget = targetField.toLowerCase();
  for (const key in data) {
    if (key.toLowerCase() === lowerTarget) {
      return { field: key, value: data[key] };
    }
  }

  return null;
}

/**
 * Manufacturer-specific field mapping configurations
 */
export const manufacturerFieldMappings: Record<string, FieldMappingConfig[]> = {
  'ACZ': [
    { source: 'patient.first_name', target: 'patient_first_name' },
    { source: 'patient.last_name', target: 'patient_last_name' },
    { source: 'patient.date_of_birth', target: 'patient_dob', transform: fieldTransformers.toMDY },
    { source: 'patient.phone', target: 'patient_phone', transform: fieldTransformers.toUSPhone },
    { source: 'productRequest.wound_type', target: 'wound_type' },
    { source: 'productRequest.wound_location', target: 'wound_location' },
    { source: 'productRequest.wound_length', target: 'wound_size_length' },
    { source: 'productRequest.wound_width', target: 'wound_size_width' },
    { source: 'provider.npi', target: 'provider_npi' },
    { source: 'provider.name', target: 'provider_name' },
  ],
  // Add more manufacturer configurations as needed
};

/**
 * Get field mapping for a specific manufacturer
 */
export function getManufacturerFieldMapping(
  manufacturer: string
): FieldMappingConfig[] | undefined {
  return manufacturerFieldMappings[manufacturer];
}

/**
 * Apply manufacturer-specific field mapping
 */
export function applyManufacturerFieldMapping(
  sourceData: Record<string, any>,
  manufacturer: string
): Record<string, any> {
  const mapping = getManufacturerFieldMapping(manufacturer);
  if (!mapping) {
    console.warn(`No field mapping found for manufacturer: ${manufacturer}`);
    return sourceData;
  }

  return mapFields(sourceData, mapping);
}