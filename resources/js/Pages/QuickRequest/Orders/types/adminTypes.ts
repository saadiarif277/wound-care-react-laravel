
export type OrderStatus = 
  | 'Pending'
  | 'Submitted to Manufacturer'
  | 'Confirmed by Manufacturer'
  | 'Rejected'
  | 'Canceled';

export type IVRStatus = 
  | 'N/A'
  | 'Pending'
  | 'Sent'
  | 'Verified'
  | 'Rejected';

export type OrderFormStatus = 
  | 'Draft'
  | 'Submitted'
  | 'Under Review'
  | 'Approved'
  | 'Rejected';

export interface IVRData {
  status: IVRStatus;
  sentDate?: string;
  resultsReceivedDate?: string;
  verifiedDate?: string;
  resultsFileUrl?: string;
  notes?: string;
}

export interface OrderFormData {
  status: OrderFormStatus;
  submissionDate?: string;
  reviewDate?: string;
  approvalDate?: string;
  fileUrl?: string;
  notes?: string;
}

export interface AdminOrderData {
  orderNumber: string;
  patientIdentifier: string; // De-identified (e.g., "RaRE042")
  productName: string;
  providerName: string;
  orderStatus: OrderStatus;
  orderRequestDate: string;
  manufacturerName: string;
  actionRequired: boolean;
  ivrData: IVRData;
  orderFormData: OrderFormData;
  expectedServiceDate?: string;
  totalAspPrice: number;
  amountDue: number;
  quantity: number;
  sizes: string[];
  applicationCount: number;
  shippingSpeed?: string;
  woundType: string;
  diagnosisCodes: Array<{ code: string; description: string; }>;
  facilityContact?: string;
  supportingDocuments: Array<{
    orderNumber: string;
    documentType: string;
    dateUploaded: string;
    fileUrl: string;
  }>;
  actionHistory: Array<{
    timestamp: string;
    actor: string;
    action: string;
    notes?: string;
  }>;
  otherOrders?: Array<{
    orderNumber: string;
    dateOfService: string;
    woundSize: string;
    dateShipped?: string;
    amountDue: number;
  }>;
}

export interface AdminActionProps {
  order: AdminOrderData;
  onStatusChange: (orderNumber: string, newStatus: OrderStatus, notes?: string) => void;
  onGenerateIVR: (orderNumber: string, skipIVR?: boolean, reason?: string) => void;
  onSubmitToManufacturer: (orderNumber: string) => void;
  onUploadDocument: (orderNumber: string, file: File, documentType: string) => void;
  onUpdateIVRStatus: (orderNumber: string, status: IVRStatus, notes?: string) => void;
  onUpdateOrderFormStatus: (orderNumber: string, status: OrderFormStatus, notes?: string) => void;
  onUploadIVRResults: (orderNumber: string, file: File) => void;
}

export type AdminViewMode = 'dashboard' | 'detail';
export type DashboardFilter = 'requiring-action' | 'all-orders';
