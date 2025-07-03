export type DocumentType = 'demographics' | 'insurance_card' | 'chart_notes' | 'diagonal_clinical_notes';

export interface UploadedFile {
  name: string;
  size: number;
  type: string;
  file: File;
}

export interface DocumentUpload {
  type: DocumentType;
  files: {
    primary?: UploadedFile;
    secondary?: UploadedFile; // For insurance card back
  };
  id: string;
}

export interface DocumentTypeConfig {
  label: string;
  accept: string;
  maxFiles: number;
  subLabels?: {
    primary: string;
    secondary: string;
  };
  description?: string;
}

export const DOCUMENT_TYPE_CONFIGS: Record<DocumentType, DocumentTypeConfig> = {
  demographics: {
    label: 'Demographics / Face Sheet',
    accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png',
    maxFiles: 1,
    description: 'Patient demographics and face sheet information'
  },
  insurance_card: {
    label: 'Insurance Card',
    accept: 'image/*,application/pdf',
    maxFiles: 2,
    subLabels: {
      primary: 'Front of Card',
      secondary: 'Back of Card'
    },
    description: 'Front and back of insurance card'
  },
  chart_notes: {
    label: 'Chart Notes',
    accept: '.pdf,.doc,.docx',
    maxFiles: 1,
    description: 'Clinical notes and documentation'
  },
  diagonal_clinical_notes: {
    label: 'Diagonal Clinical Notes',
    accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png',
    maxFiles: 1,
    description: 'Diagonal clinical notes and specialized documentation'
  }
};