
import { AdminOrderData } from '../types/adminTypes';

export const mockAdminOrders: AdminOrderData[] = [
  {
    orderNumber: "ORD-2024-001234",
    patientIdentifier: "RaRE042",
    productName: "Advanced Wound Matrix - Premium",
    providerName: "Dr. Sarah Johnson",
    orderStatus: "Pending IVR",
    orderRequestDate: "2024-06-24",
    manufacturerName: "MedTech Solutions",
    actionRequired: true,
    totalAspPrice: 2850.00,
    amountDue: 2565.00,
    quantity: 3,
    sizes: ["4cm x 4cm"],
    applicationCount: 3,
    woundType: "Diabetic Foot Ulcer",
    ivrData: {
      status: "Pending",
      notes: "Waiting for IVR generation"
    },
    orderFormData: {
      status: "Submitted",
      submissionDate: "2024-06-24",
      fileUrl: "/documents/order-form-001234.pdf"
    },
    diagnosisCodes: [
      { code: "E11.621", description: "Type 2 diabetes mellitus with foot ulcer" }
    ],
    supportingDocuments: [
      {
        orderNumber: "ORD-2024-001234",
        documentType: "Insurance Card",
        dateUploaded: "2024-06-22",
        fileUrl: "/documents/insurance-card-001234.pdf"
      }
    ],
    actionHistory: [
      {
        timestamp: "2024-06-24T10:30:00Z",
        actor: "Dr. Sarah Johnson",
        action: "Order Submitted"
      }
    ]
  },
  {
    orderNumber: "ORD-2024-001235",
    patientIdentifier: "JoSM123",
    productName: "Wound Care Matrix Standard",
    providerName: "Dr. Michael Chen",
    orderStatus: "IVR Verified",
    orderRequestDate: "2024-06-23",
    manufacturerName: "BioHeal Corp",
    actionRequired: true,
    totalAspPrice: 1850.00,
    amountDue: 1665.00,
    quantity: 2,
    sizes: ["3cm x 3cm"],
    applicationCount: 2,
    woundType: "Venous Ulcer",
    ivrData: {
      status: "Verified",
      sentDate: "2024-06-23",
      resultsReceivedDate: "2024-06-24",
      verifiedDate: "2024-06-24",
      resultsFileUrl: "/documents/ivr-results-001235.pdf"
    },
    orderFormData: {
      status: "Under Review",
      submissionDate: "2024-06-23",
      reviewDate: "2024-06-24",
      fileUrl: "/documents/order-form-001235.pdf"
    },
    diagnosisCodes: [
      { code: "I87.31", description: "Chronic venous hypertension with ulcer" }
    ],
    supportingDocuments: [
      {
        orderNumber: "ORD-2024-001235",
        documentType: "IVR Form",
        dateUploaded: "2024-06-23",
        fileUrl: "/documents/ivr-form-001235.pdf"
      }
    ],
    actionHistory: [
      {
        timestamp: "2024-06-23T14:15:00Z",
        actor: "Dr. Michael Chen",
        action: "Order Submitted"
      },
      {
        timestamp: "2024-06-24T09:00:00Z",
        actor: "Admin User",
        action: "IVR Generated"
      },
      {
        timestamp: "2024-06-24T11:30:00Z",
        actor: "Admin User",
        action: "IVR Verified"
      }
    ]
  }
];
