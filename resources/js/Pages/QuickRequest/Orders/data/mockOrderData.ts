
import { OrderData } from '../types/orderTypes';

export const mockOrderData: OrderData = {
  orderNumber: "ORD-2024-001234",
  orderStatus: "Draft",
  createdDate: "2024-06-24",
  createdBy: "Dr. Sarah Johnson",
  
  patient: {
    fullName: "John Michael Smith",
    dateOfBirth: "1975-03-15",
    phone: "(555) 123-4567",
    email: "john.smith@email.com",
    address: "1234 Oak Street, Springfield, IL 62701",
    primaryInsurance: {
      payerName: "Blue Cross Blue Shield",
      planName: "PPO Plan",
      policyNumber: "BC123456789"
    },
    secondaryInsurance: null,
    insuranceCardUploaded: true
  },
  
  provider: {
    name: "Dr. Sarah Johnson, MD",
    facilityName: "Springfield Medical Center",
    facilityAddress: "456 Medical Plaza, Springfield, IL 62701",
    organization: "Springfield Healthcare System",
    npi: "1234567890"
  },
  
  clinical: {
    woundType: "Diabetic Foot Ulcer",
    woundSize: "2.5cm x 1.8cm x 0.8cm deep",
    diagnosisCodes: [
      { code: "E11.621", description: "Type 2 diabetes mellitus with foot ulcer" },
      { code: "L97.519", description: "Non-pressure chronic ulcer of other part of right foot with unspecified severity" }
    ],
    icd10Codes: [
      { code: "E11.621", description: "Type 2 diabetes mellitus with foot ulcer" },
      { code: "L97.519", description: "Non-pressure chronic ulcer of other part of right foot" }
    ],
    procedureInfo: "Application of advanced wound matrix",
    priorApplications: 0,
    anticipatedApplications: 3,
    facilityInfo: "Outpatient Wound Care Clinic"
  },
  
  product: {
    name: "Advanced Wound Matrix - Premium",
    sizes: ["4cm x 4cm"],
    quantity: 3,
    aspPrice: 2850.00,
    discountedPrice: 2565.00,
    coverageWarnings: []
  },
  
  ivrForm: {
    status: "Complete",
    submissionDate: "2024-06-22",
    documentLink: "/documents/ivr-form-001234.pdf"
  },
  
  orderForm: {
    status: "Complete", 
    submissionDate: "2024-06-22",
    documentLink: "/documents/order-form-001234.pdf"
  }
};
