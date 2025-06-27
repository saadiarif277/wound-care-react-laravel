// Provider-specific type definitions
import { ComponentProps } from 'react';

export interface ProviderInvitationData {
  id: string;
  organization_name: string;
  organization_type: string;
  invited_email: string;
  invited_role: string;
  expires_at: string;
  status: 'pending' | 'accepted' | 'expired' | 'declined';
  metadata: {
    organization_id: string;
    invited_by: string;
    invited_by_name: string;
  };
}

export type PracticeType = 'solo_practitioner' | 'group_practice' | 'existing_organization';

// Base provider data that all practice types share
interface BaseProviderData {
  // Personal Information
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone: string;
  title: string;

  // Professional Credentials
  individual_npi: string;
  specialty: string;
  license_number: string;
  license_state: string;
  ptan?: string;

  // Terms
  accept_terms: boolean;
  
  practice_type: PracticeType;
}

// Solo practitioner needs full organization setup
export interface SoloPractitionerData extends BaseProviderData {
  practice_type: 'solo_practitioner';
  
  // Organization Information
  organization_name: string;
  organization_tax_id: string;
  organization_type: string;

  // Facility Information
  facility_name: string;
  facility_type: string;
  group_npi?: string;
  facility_tax_id?: string;
  facility_ptan?: string;

  // Addresses
  facility_address: string;
  facility_city: string;
  facility_state: string;
  facility_zip: string;
  facility_phone: string;
  facility_email: string;

  // Billing
  billing_address: string;
  billing_city: string;
  billing_state: string;
  billing_zip: string;

  // AP Contact
  ap_contact_name?: string;
  ap_contact_phone?: string;
  ap_contact_email?: string;
}

// Group practice - similar to solo but joining new group
export interface GroupPracticeData extends SoloPractitionerData {
  practice_type: 'group_practice';
}

// Existing organization - minimal setup
export interface ExistingOrganizationData extends BaseProviderData {
  practice_type: 'existing_organization';
  facility_id: number;
}

// Union type for all provider registration data
export type ProviderRegistrationData = 
  | SoloPractitionerData 
  | GroupPracticeData 
  | ExistingOrganizationData;

// Step definitions for the wizard
export interface OnboardingStep {
  id: string;
  title: string;
  description: string;
  component: React.ComponentType<any>;
  validation?: (data: ProviderRegistrationData) => Record<string, string>;
}

// API Response types
export interface ProviderInvitationResponse {
  invitation: ProviderInvitationData;
  token: string;
  facilities: FacilityData[];
  states: StateOption[];
}

export interface FacilityData {
  id: number;
  name: string;
  full_address: string;
  npi?: string;
  type?: string;
}

export interface StateOption {
  code: string;
  name: string;
}

// Validation schemas
export interface ValidationRules {
  required?: boolean;
  pattern?: RegExp;
  minLength?: number;
  maxLength?: number;
  custom?: (value: any, data: ProviderRegistrationData) => string | null;
}

// CheckboxInput extends the base input props but label can be React.ReactNode
export interface CheckboxInputProps extends ComponentProps<'input'> {
  label?: React.ReactNode;
  error?: string;
}
