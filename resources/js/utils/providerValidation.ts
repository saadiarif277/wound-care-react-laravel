// Centralized validation for provider onboarding
import type { 
  ProviderRegistrationData,
  ValidationSchema,
  ValidationRules 
} from '@/types/provider';

// Validation patterns
export const VALIDATION_PATTERNS = {
  NPI: /^\d{10}$/,
  TAX_ID: /^\d{2}-\d{7}$/,
  ZIP: /^\d{5}(-\d{4})?$/,
  PHONE: /^[\+]?[\d\s\-\(\)]+$/,
  EMAIL: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
} as const;

// Validation messages
const MESSAGES = {
  required: (field: string) => `${field} is required`,
  pattern: (field: string) => `${field} format is invalid`,
  minLength: (field: string, min: number) => `${field} must be at least ${min} characters`,
  maxLength: (field: string, max: number) => `${field} must be no more than ${max} characters`,
  passwordMatch: 'Passwords do not match',
  npi: 'NPI must be exactly 10 digits',
  taxId: 'Tax ID should be in format XX-XXXXXXX',
  zip: 'ZIP code must be 5 digits or ZIP+4 format',
  phone: 'Please enter a valid phone number',
  email: 'Please enter a valid email address',
} as const;

// Field display names
const FIELD_NAMES: Record<string, string> = {
  first_name: 'First name',
  last_name: 'Last name',
  password: 'Password',
  password_confirmation: 'Password confirmation',
  individual_npi: 'Individual NPI',
  organization_tax_id: 'Tax ID',
  facility_zip: 'ZIP code',
  facility_phone: 'Phone number',
  facility_email: 'Email',
  accept_terms: 'Terms acceptance',
  facility_name: 'Facility name',
  facility_type: 'Facility type',
  facility_address: 'Facility address',
  facility_city: 'City',
  facility_state: 'State',
  organization_name: 'Organization name',
  facility_id: 'Facility selection',
};

// Helper to get field display name
const getFieldName = (field: string): string => {
  return FIELD_NAMES[field] || field.replace(/_/g, ' ');
};
// Validate a single field
export const validateField = (
  value: any, 
  rules: ValidationRules, 
  fieldName: string,
  data?: ProviderRegistrationData
): string | null => {
  const displayName = getFieldName(fieldName);

  // Required check
  if (rules.required && (!value || (typeof value === 'string' && !value.trim()))) {
    return MESSAGES.required(displayName);
  }

  // Skip other validations if value is empty and not required
  if (!value) return null;

  // Pattern check
  if (rules.pattern && typeof value === 'string' && !rules.pattern.test(value)) {
    // Use specific messages for known patterns
    if (rules.pattern === VALIDATION_PATTERNS.NPI) return MESSAGES.npi;
    if (rules.pattern === VALIDATION_PATTERNS.TAX_ID) return MESSAGES.taxId;
    if (rules.pattern === VALIDATION_PATTERNS.ZIP) return MESSAGES.zip;
    if (rules.pattern === VALIDATION_PATTERNS.PHONE) return MESSAGES.phone;
    if (rules.pattern === VALIDATION_PATTERNS.EMAIL) return MESSAGES.email;
    return MESSAGES.pattern(displayName);
  }

  // Length checks
  if (rules.minLength && typeof value === 'string' && value.length < rules.minLength) {
    return MESSAGES.minLength(displayName, rules.minLength);
  }

  if (rules.maxLength && typeof value === 'string' && value.length > rules.maxLength) {
    return MESSAGES.maxLength(displayName, rules.maxLength);
  }

  // Custom validation
  if (rules.custom && data) {
    return rules.custom(value, data);
  }

  return null;
};
// Step-specific validation schemas
export const STEP_VALIDATIONS: Record<string, ValidationSchema<ProviderRegistrationData>> = {
  personal: {
    first_name: { required: true },
    last_name: { required: true },
    password: { required: true, minLength: 8 },
    password_confirmation: {
      required: true,
      custom: (value, data) => 
        value !== data.password ? MESSAGES.passwordMatch : null
    },
    phone: { pattern: VALIDATION_PATTERNS.PHONE },
  },

  organization: {
    organization_name: { required: true },
    organization_tax_id: { 
      pattern: VALIDATION_PATTERNS.TAX_ID,
      custom: (value) => {
        if (!value) return null;
        const formatted = value.replace(/\D/g, '');
        if (formatted.length !== 9) {
          return 'Tax ID must be 9 digits';
        }
        return null;
      }
    },
  },

  facility: {
    facility_name: { required: true },
    facility_type: { required: true },
    facility_address: { required: true },
    facility_city: { required: true },
    facility_state: { required: true },
    facility_zip: { required: true, pattern: VALIDATION_PATTERNS.ZIP },
    facility_phone: { pattern: VALIDATION_PATTERNS.PHONE },
    facility_email: { pattern: VALIDATION_PATTERNS.EMAIL },
    group_npi: { pattern: VALIDATION_PATTERNS.NPI },
  },

  credentials: {
    individual_npi: { pattern: VALIDATION_PATTERNS.NPI },
    accept_terms: { 
      required: true,
      custom: (value) => !value ? 'You must accept the terms to continue' : null
    },
  },

  'facility-selection': {
    facility_id: { 
      required: true,
      custom: (value) => !value ? 'You must select a facility to continue' : null
    },
  },
};
// Validate all fields for a specific step
export const validateStep = (
  step: string,
  data: Partial<ProviderRegistrationData>
): Record<string, string> => {
  const schema = STEP_VALIDATIONS[step];
  if (!schema) return {};

  const errors: Record<string, string> = {};

  Object.entries(schema).forEach(([field, rules]) => {
    const value = data[field as keyof ProviderRegistrationData];
    const error = validateField(
      value, 
      rules, 
      field, 
      data as ProviderRegistrationData
    );
    
    if (error) {
      errors[field] = error;
    }
  });

  return errors;
};

// Format phone number as user types
export const formatPhoneNumber = (value: string): string => {
  const cleaned = value.replace(/\D/g, '');
  const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
  if (match) {
    return '(' + match[1] + ') ' + match[2] + '-' + match[3];
  }
  return value;
};

// Format Tax ID as user types
export const formatTaxId = (value: string): string => {
  const cleaned = value.replace(/\D/g, '');
  const match = cleaned.match(/^(\d{2})(\d{0,7})$/);
  if (match) {
    return match[2] ? match[1] + '-' + match[2] : match[1];
  }
  return value;
};

// Format NPI with spaces for readability
export const formatNPI = (value: string): string => {
  const cleaned = value.replace(/\D/g, '');
  // Format as: 1234 5678 90
  const match = cleaned.match(/^(\d{4})(\d{4})(\d{2})$/);
  if (match) {
    return match[1] + ' ' + match[2] + ' ' + match[3];
  }
  return cleaned;
};