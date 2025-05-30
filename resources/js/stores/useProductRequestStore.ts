import { create } from 'zustand';

interface ProductRequestState {
  // Patient Information
  patientFhirId: string | null;
  patientDisplayId: string | null;

  // Clinical Assessment
  azureOrderChecklistFhirId: string | null;

  // Product Selection / Order Details (placeholders for now)
  selectedProducts: any[]; // Replace 'any' with actual product type
  orderId: string | null;

  // Actions
  setPatientData: (data: { patientFhirId: string; patientDisplayId: string }) => void;
  setClinicalAssessmentData: (data: { azureOrderChecklistFhirId: string }) => void;
  resetProductRequest: () => void;
  // TODO: Add actions for product selection, order submission etc.
}

export const useProductRequestStore = create<ProductRequestState>((set) => ({
  // Initial state
  patientFhirId: null,
  patientDisplayId: null,
  azureOrderChecklistFhirId: null,
  selectedProducts: [],
  orderId: null,

  // Actions implementation
  setPatientData: (data) => set({
    patientFhirId: data.patientFhirId,
    patientDisplayId: data.patientDisplayId,
  }),

  setClinicalAssessmentData: (data) => set({
    azureOrderChecklistFhirId: data.azureOrderChecklistFhirId,
  }),

  resetProductRequest: () => set({
    patientFhirId: null,
    patientDisplayId: null,
    azureOrderChecklistFhirId: null,
    selectedProducts: [],
    orderId: null,
  }),
}));
