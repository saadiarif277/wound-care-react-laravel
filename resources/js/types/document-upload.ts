export type DocumentType = 'demographics' | 'insurance_card' | 'clinical_notes' | 'other';

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
    label: 'Demographics',
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
  clinical_notes: {
    label: 'Clinical Notes',
    accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png',
    maxFiles: 1,
    description: 'Clinical notes and documentation'
  },
  other: {
    label: 'Other',
    accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png',
    maxFiles: 1,
    description: 'Other supporting documents'
  }
};