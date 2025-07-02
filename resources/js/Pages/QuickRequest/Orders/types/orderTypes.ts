export interface OrderData {
  orderNumber: string;
  orderStatus: string;
  createdDate: string;
  createdBy: string;
  patient: {
    fullName: string;
    dateOfBirth: string;
    phone: string;
    email: string;
    address: string;
    primaryInsurance: {
      payerName: string;
      planName: string;
      policyNumber: string;
    };
    secondaryInsurance: {
      payerName: string;
      planName: string;
      policyNumber: string;
    } | null;
    insuranceCardUploaded: boolean;
  };
  provider: {
    name: string;
    facilityName: string;
    facilityAddress: string;
    organization: string;
    npi: string;
  };
  clinical: {
    woundType: string;
    woundSize: string;
    diagnosisCodes: Array<{
      code: string;
      description: string;
    }>;
    icd10Codes: Array<{
      code: string;
      description: string;
    }>;
    procedureInfo: string;
    priorApplications: number;
    anticipatedApplications: number;
    facilityInfo: string;
  };
  product: {
    name: string;
    sizes: string[];
    quantity: number;
    aspPrice: number;
    discountedPrice: number;
    coverageWarnings: string[];
  };
  ivrForm: {
    status: string;
    submissionDate: string;
    documentLink: string;
  };
  orderForm: {
    status: string;
    submissionDate: string;
    documentLink: string;
  };
}

export type UserRole = 'Provider' | 'OM' | 'Admin';
