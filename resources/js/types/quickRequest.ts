// Quick Request Workflow TypeScript Definitions

// FHIR Resource References
export interface FhirReference {
  reference: string;
  type?: string;
  display?: string;
}

// Patient Information Types
export interface PatientName {
  use: 'official' | 'usual' | 'temp' | 'nickname' | 'anonymous' | 'old' | 'maiden';
  family: string;
  given: string[];
  prefix?: string[];
  suffix?: string[];
  text?: string;
}

export interface PatientAddress {
  use: 'home' | 'work' | 'temp' | 'old' | 'billing';
  type: 'postal' | 'physical' | 'both';
  line: string[];
  city: string;
  state: string;
  postalCode: string;
  country?: string;
}

export interface PatientContact {
  relationship?: CodeableConcept[];
  name?: PatientName;
  telecom?: ContactPoint[];
  address?: PatientAddress;
  gender?: 'male' | 'female' | 'other' | 'unknown';
  organization?: FhirReference;
  period?: Period;
}

export interface ContactPoint {
  system: 'phone' | 'fax' | 'email' | 'pager' | 'url' | 'sms' | 'other';
  value: string;
  use: 'home' | 'work' | 'temp' | 'old' | 'mobile';
  rank?: number;
  period?: Period;
}

export interface Period {
  start?: string;
  end?: string;
}

export interface CodeableConcept {
  coding?: Coding[];
  text: string;
}

export interface Coding {
  system?: string;
  version?: string;
  code: string;
  display?: string;
  userSelected?: boolean;
}

// Step 1: Patient & Insurance
export interface PatientInsuranceData {
  patient: {
    firstName: string;
    lastName: string;
    middleName?: string;
    dateOfBirth: string;
    gender: 'male' | 'female' | 'other' | 'unknown';
    ssn?: string;
    medicareNumber?: string;
    address: PatientAddress;
    phone: string;
    email?: string;
    emergencyContact?: PatientContact;
  };
  insurance: {
    primary: InsuranceCoverage;
    secondary?: InsuranceCoverage;
    tertiary?: InsuranceCoverage;
  };
}

export interface InsuranceCoverage {
  type: 'medicare' | 'medicaid' | 'private' | 'other';
  policyNumber: string;
  groupNumber?: string;
  subscriberId: string;
  subscriberName: string;
  subscriberRelationship: string;
  effectiveDate: string;
  expirationDate?: string;
  payorName: string;
  payorId?: string;
  planName?: string;
  planType?: string;
}

// Step 2: Clinical & Billing
export interface ClinicalBillingData {
  provider: {
    npi: string;
    name: string;
    specialty?: string;
    phone?: string;
    fax?: string;
  };
  facility: {
    id: string;
    name: string;
    npi?: string;
    address: PatientAddress;
    phone: string;
    fax?: string;
    taxId?: string;
  };
  referral: {
    referringProviderId?: string;
    referralDate?: string;
    referralNumber?: string;
    authorizationNumber?: string;
  };
  diagnosis: {
    primary: DiagnosisCode;
    secondary: DiagnosisCode[];
  };
  woundDetails: {
    woundType: string;
    woundLocation: string;
    woundSize: {
      length: number;
      width: number;
      depth?: number;
      unit: 'cm' | 'mm' | 'in';
    };
    woundStage?: string;
    woundAge?: string;
    drainageType?: string;
    drainageAmount?: string;
    periWoundCondition?: string;
    treatmentGoal?: string;
  };
}

export interface DiagnosisCode {
  code: string;
  system: 'icd10' | 'icd9' | 'snomed';
  display: string;
  isPrimary?: boolean;
  dateRecorded?: string;
}

// Step 3: Product Selection
export interface ProductSelectionData {
  manufacturer: {
    id: string;
    name: string;
    code: string;
  };
  products: SelectedProduct[];
  deliveryPreferences: {
    method: 'standard' | 'expedited' | 'overnight';
    specialInstructions?: string;
    preferredDeliveryDays?: string[];
    deliveryAddress?: PatientAddress;
  };
}

export interface SelectedProduct {
  id: string;
  name: string;
  code: string;
  category: string;
  quantity: number;
  frequency: 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'as_needed';
  sizes: ProductSize[];
  modifiers?: string[];
  specialInstructions?: string;
  mueLimits?: {
    quantity: number;
    period: 'day' | 'week' | 'month';
  };
}

export interface ProductSize {
  size: string;
  quantity: number;
  unit: string;
}

// Step 4: DocuSeal IVR
export interface DocuSealIVRData {
  template: {
    id: string;
    name: string;
    manufacturer: string;
    type: 'insurance_verification' | 'order_form' | 'consent' | 'other';
  };
  fields: Record<string, any>;
  signatures: {
    patient?: SignatureData;
    provider?: SignatureData;
    officeManager?: SignatureData;
  };
  documents: GeneratedDocument[];
}

export interface SignatureData {
  signedAt: string;
  signedBy: string;
  ipAddress?: string;
  userAgent?: string;
  signatureImage?: string;
}

export interface GeneratedDocument {
  id: string;
  type: string;
  fileName: string;
  mimeType: string;
  size: number;
  url?: string;
  generatedAt: string;
  s3Key?: string;
}

// Step 5: Review & Submit
export interface ReviewSubmitData {
  episode: {
    id?: string;
    status: EpisodeStatus;
    patient: PatientInsuranceData;
    clinical: ClinicalBillingData;
    products: ProductSelectionData;
    documents: DocuSealIVRData;
    tasks: TaskData[];
    audit: AuditEntry[];
  };
  validationResults: ValidationResult[];
  consentGiven: boolean;
  submittedAt?: string;
  submittedBy?: string;
}

export type EpisodeStatus = 
  | 'draft'
  | 'pending_review'
  | 'manufacturer_review'
  | 'approved'
  | 'rejected'
  | 'completed'
  | 'cancelled';

export interface TaskData {
  id: string;
  type: 'approval' | 'review' | 'verification' | 'notification';
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  assignedTo?: string;
  dueDate?: string;
  completedAt?: string;
  completedBy?: string;
  notes?: string;
}

export interface AuditEntry {
  timestamp: string;
  action: string;
  userId: string;
  userName?: string;
  details?: Record<string, any>;
  ipAddress?: string;
}

export interface ValidationResult {
  field: string;
  rule: string;
  passed: boolean;
  message?: string;
  severity: 'error' | 'warning' | 'info';
}

// Quick Request Workflow State
export interface QuickRequestState {
  currentStep: QuickRequestStep;
  data: {
    patientInsurance?: PatientInsuranceData;
    clinicalBilling?: ClinicalBillingData;
    productSelection?: ProductSelectionData;
    docuSealIVR?: DocuSealIVRData;
    reviewSubmit?: ReviewSubmitData;
  };
  validation: {
    [key in QuickRequestStep]?: ValidationResult[];
  };
  navigation: {
    canGoBack: boolean;
    canGoForward: boolean;
    completedSteps: QuickRequestStep[];
    visitedSteps: QuickRequestStep[];
  };
  metadata: {
    startedAt: string;
    lastModifiedAt: string;
    sessionId: string;
    userId: string;
    source?: string;
  };
}

export type QuickRequestStep = 
  | 'patient-insurance'
  | 'clinical-billing'
  | 'product-selection'
  | 'docuseal-ivr'
  | 'review-submit';

// API Response Types
export interface QuickRequestApiResponse<T = any> {
  success: boolean;
  data?: T;
  errors?: ValidationError[];
  warnings?: Warning[];
  metadata?: {
    timestamp: string;
    requestId: string;
    version: string;
  };
}

export interface ValidationError {
  field: string;
  code: string;
  message: string;
  details?: any;
}

export interface Warning {
  code: string;
  message: string;
  field?: string;
}

// Episode Management Types
export interface Episode {
  id: string;
  patientFhirId: string;
  practitionerFhirId: string;
  organizationFhirId: string;
  episodeOfCareFhirId?: string;
  status: EpisodeStatus;
  orders: Order[];
  tasks: TaskData[];
  documents: GeneratedDocument[];
  createdAt: string;
  updatedAt: string;
  completedAt?: string;
  cancelledAt?: string;
  metadata?: Record<string, any>;
}

export interface Order {
  id: string;
  episodeId: string;
  basedOn?: string; // Parent order ID for follow-ups
  type: 'initial' | 'follow_up';
  status: OrderStatus;
  details: {
    products: SelectedProduct[];
    deliveryInfo: any;
    clinicalInfo: any;
  };
  deviceRequestFhirId?: string;
  createdAt: string;
  updatedAt: string;
  fulfilledAt?: string;
}

export type OrderStatus = 
  | 'draft'
  | 'active'
  | 'on_hold'
  | 'completed'
  | 'cancelled'
  | 'entered_in_error';

// Form Props Types
export interface QuickRequestStepProps {
  data: QuickRequestState['data'];
  onNext: (stepData: any) => void;
  onBack: () => void;
  onSave: (stepData: any) => void;
  isLoading?: boolean;
  errors?: ValidationError[];
  warnings?: Warning[];
}

// Hook Return Types
export interface UseQuickRequestReturn {
  state: QuickRequestState;
  currentStepData: any;
  formData: QuickRequestState['data'];
  isLoading: boolean;
  errors: ValidationError[];
  warnings: Warning[];
  goToStep: (step: QuickRequestStep) => void;
  goNext: () => void;
  goBack: () => void;
  saveStep: (data: any) => Promise<void>;
  updateFormData: (data: any) => void;
  submitEpisode: () => Promise<Episode>;
  validateStep: (step?: QuickRequestStep) => Promise<ValidationResult[]>;
  resetWorkflow: () => void;
  canGoBack: boolean;
  canGoNext: boolean;
  progress: number;
}

// Context Types
export interface QuickRequestContextValue {
  state: QuickRequestState;
  dispatch: React.Dispatch<QuickRequestAction>;
  api: {
    saveProgress: (sessionId: string, step: QuickRequestStep, data: any) => Promise<void>;
    loadProgress: (sessionId: string) => Promise<QuickRequestState | null>;
    createEpisode: (data: QuickRequestState['data']) => Promise<Episode>;
    validateStep: (step: QuickRequestStep, data: any) => Promise<ValidationResult[]>;
  };
}

// Action Types for Reducer
export type QuickRequestAction =
  | { type: 'SET_STEP'; payload: QuickRequestStep }
  | { type: 'SET_STEP_DATA'; payload: { step: QuickRequestStep; data: any } }
  | { type: 'SET_VALIDATION'; payload: { step: QuickRequestStep; results: ValidationResult[] } }
  | { type: 'COMPLETE_STEP'; payload: QuickRequestStep }
  | { type: 'RESET_WORKFLOW' }
  | { type: 'LOAD_STATE'; payload: QuickRequestState }
  | { type: 'SET_LOADING'; payload: boolean }
  | { type: 'SET_ERRORS'; payload: ValidationError[] }
  | { type: 'SET_WARNINGS'; payload: Warning[] }
  | { type: 'UPDATE_DATA'; payload: Partial<QuickRequestState['data']> };