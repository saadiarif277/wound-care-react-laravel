/**
 * Episode-Based Order Workflow Types
 *
 * These types define the data structures used in the episode-based order management system.
 * Episodes group orders by patient+manufacturer combination for streamlined processing.
 */

// Episode Status Types
export type EpisodeStatus =
  | 'ready_for_review'
  | 'ivr_sent'
  | 'ivr_verified'
  | 'sent_to_manufacturer'
  | 'tracking_added'
  | 'completed';

export type IVRStatus =
<<<<<<< HEAD
  | 'pending'
  | 'verified'
  | 'expired';
=======
  | 'N/A'
  | 'pending'
  | 'sent'
  | 'verified'
  | 'rejected';
>>>>>>> origin/provider-side

// Episode Status Configuration
export interface EpisodeStatusConfig {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  color: 'blue' | 'yellow' | 'green' | 'purple' | 'indigo' | 'red' | 'orange' | 'gray';
}

export interface IVRStatusConfig {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  color: 'green' | 'red' | 'gray';
}

// Core Episode Interface
export interface Episode {
  id: string;
  patient_id: string;
  patient_name?: string;
  patient_display_id: string;
  manufacturer: Manufacturer;
  status: EpisodeStatus;
  ivr_status: IVRStatus;
  verification_date?: string;
  expiration_date?: string;
  orders_count: number;
  total_order_value: number;
  latest_order_date: string;
  action_required: boolean;
  orders: EpisodeOrder[];
  created_at: string;
  last_order_date?: string;
  recent_activities?: Array<{
    type: string;
    date: string;
    description: string;
  }>;
}

// Manufacturer Information
export interface Manufacturer {
  id: number;
  name: string;
  contact_email?: string;
  contact_phone?: string;
  logo?: string;
}

// Order within Episode
export interface EpisodeOrder {
  id: string;
  order_number: string;
  order_status: string;
  expected_service_date: string;
  submitted_at: string;
  total_order_value?: number;
  action_required?: boolean;
  provider?: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
  };
  facility?: {
    id: number;
    name: string;
    city: string;
    state: string;
  };
  products?: EpisodeOrderProduct[];
}

// Product within Episode Order
export interface EpisodeOrderProduct {
  id: number;
  name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  total_price: number;
}

// Episode Detail Interface (for ShowEpisode page)
export interface EpisodeDetail extends Episode {
  docuseal: EpisodeDocuSeal;
  audit_log: EpisodeAuditLogEntry[];
}

// DocuSeal Integration
export interface EpisodeDocuSeal {
  status?: string;
  signed_documents?: DocuSealDocument[];
  audit_log_url?: string;
  last_synced_at?: string;
}

export interface DocuSealDocument {
  id: number;
  filename?: string;
  name?: string;
  url: string;
}

// Audit Log
export interface EpisodeAuditLogEntry {
  id: number;
  action: string;
  actor: string;
  timestamp: string;
  notes?: string;
}

// API Response Types
export interface EpisodesIndexResponse {
  episodes: {
    data: Episode[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: EpisodeFilters;
  statusCounts: Record<EpisodeStatus, number>;
  ivrStatusCounts: Record<IVRStatus, number>;
  manufacturers: Manufacturer[];
  expiringIVRs?: ExpiringIVR[];
}

export interface EpisodeShowResponse {
  episode: EpisodeDetail;
  can_generate_ivr: boolean;
  can_manage_episode: boolean;
  can_send_to_manufacturer: boolean;
}

// Filter Types
export interface EpisodeFilters {
  search?: string;
  status?: EpisodeStatus;
  ivr_status?: IVRStatus;
  action_required?: boolean;
  manufacturer?: string;
  date_range?: string;
}

// Expiring IVR Warning
export interface ExpiringIVR {
  id: number;
  patient_fhir_id: string;
  patient_name: string;
  patient_display_id: string;
  manufacturer_name: string;
  expiration_date: string;
  days_until_expiration: number;
}

// Episode Action Types
export interface EpisodeActionRequest {
  episode_id: string;
}

export interface EpisodeTrackingRequest extends EpisodeActionRequest {
  tracking_number: string;
  carrier: string;
  estimated_delivery?: string;
}

export interface EpisodeActionResponse {
  success: boolean;
  message: string;
  docuseal_submission_id?: string;
}

// Episode Statistics
export interface EpisodeStats {
  totalEpisodes: number;
  pendingActions: number;
  completedRate: number;
  todaysEpisodes: number;
}

// Episode Creation/Update Types
export interface CreateEpisodeRequest {
  patient_id: string;
  manufacturer_id: number;
  status?: EpisodeStatus;
  ivr_status?: IVRStatus;
}

export interface UpdateEpisodeRequest {
  status?: EpisodeStatus;
  ivr_status?: IVRStatus;
  verification_date?: string;
  expiration_date?: string;
  docuseal_submission_id?: string;
  docuseal_status?: string;
}

// Form Data Types
export interface EpisodeFormData {
  patient_id: string;
  manufacturer_id: number;
  status: EpisodeStatus;
  ivr_status: IVRStatus;
  verification_date?: string;
  expiration_date?: string;
}

// Episode Search and Pagination
export interface EpisodeSearchParams {
  search?: string;
  status?: EpisodeStatus;
  ivr_status?: IVRStatus;
  manufacturer?: string;
  action_required?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: 'latest_order_date' | 'created_at' | 'patient_name' | 'total_order_value';
  sort_direction?: 'asc' | 'desc';
}

// Episode Metrics and Analytics
export interface EpisodeMetrics {
  episode_id: string;
  orders_count: number;
  total_value: number;
  avg_order_value: number;
  days_since_created: number;
  days_since_last_order: number;
  completion_rate: number;
}

// Episode Status Transition
export interface EpisodeStatusTransition {
  from_status: EpisodeStatus;
  to_status: EpisodeStatus;
  allowed: boolean;
  required_permissions?: string[];
  validation_rules?: string[];
}

// Episode Workflow Configuration
export interface EpisodeWorkflowConfig {
  status_transitions: Record<EpisodeStatus, EpisodeStatus[]>;
  ivr_status_transitions: Record<IVRStatus, IVRStatus[]>;
  action_permissions: Record<string, string[]>;
  status_colors: Record<EpisodeStatus, string>;
  ivr_status_colors: Record<IVRStatus, string>;
}

// Episode Event Types (for real-time updates)
export interface EpisodeEvent {
  type: 'status_updated' | 'ivr_generated' | 'order_added' | 'tracking_updated' | 'completed';
  episode_id: string;
  data: any;
  timestamp: string;
  user_id?: string;
}

// Episode Bulk Actions
export interface EpisodeBulkAction {
  action: 'generate_ivr' | 'send_to_manufacturer' | 'mark_completed' | 'update_status';
  episode_ids: string[];
  parameters?: any;
}

export interface EpisodeBulkActionResponse {
  success: boolean;
  message: string;
  results: Array<{
    episode_id: string;
    success: boolean;
    message: string;
  }>;
}

// Episode Export Types
export interface EpisodeExportRequest {
  filters?: EpisodeFilters;
  format: 'csv' | 'excel' | 'pdf';
  include_orders?: boolean;
  include_audit_log?: boolean;
}

export interface EpisodeExportResponse {
  success: boolean;
  download_url: string;
  expires_at: string;
}

// MAC Validation Types
export interface MacValidationData {
  risk_score: number;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  coverage_status: 'covered' | 'conditional' | 'not_covered' | 'requires_prior_auth';
  contractor: {
    name: string;
    jurisdiction: string;
  };
  lcd_compliance: {
    status: 'compliant' | 'partial' | 'non_compliant';
    missing_criteria?: string[];
    documentation_required?: string[];
  };
  denial_prediction: {
    probability: number;
    top_risk_factors: Array<{
      factor: string;
      impact: 'high' | 'medium' | 'low';
      mitigation?: string;
    }>;
  };
  financial_impact: {
    potential_denial_amount: number;
    approval_confidence: number;
    estimated_reimbursement: number;
  };
  recommendations: Array<{
    priority: 'critical' | 'high' | 'medium' | 'low';
    action: string;
    impact: string;
  }>;
}

// Episode with MAC Validation
export interface EpisodeWithMacValidation extends Episode {
  mac_validation?: MacValidationData;
}

// Component Props Types
export interface EpisodeIndexProps {
  episodes: EpisodesIndexResponse['episodes'];
  filters: EpisodeFilters;
  statusCounts: Record<EpisodeStatus, number>;
  ivrStatusCounts: Record<IVRStatus, number>;
  manufacturers: Manufacturer[];
  expiringIVRs?: ExpiringIVR[];
}

export interface EpisodeShowProps {
  episode: EpisodeDetail;
  can_generate_ivr: boolean;
  can_manage_episode: boolean;
  can_send_to_manufacturer: boolean;
}

export interface EpisodeStatusBadgeProps {
  status: EpisodeStatus;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
}

export interface IVRStatusBadgeProps {
  status: IVRStatus;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
}

// Utility Types
export type EpisodeStatusKey = keyof typeof EpisodeStatus;
export type IVRStatusKey = keyof typeof IVRStatus;

// Type Guards
export function isValidEpisodeStatus(status: string): status is EpisodeStatus {
  return ['ready_for_review', 'ivr_sent', 'ivr_verified', 'sent_to_manufacturer', 'tracking_added', 'completed'].includes(status);
}

export function isValidIVRStatus(status: string): status is IVRStatus {
  return ['pending', 'verified', 'expired'].includes(status);
}

// Constants
export const EPISODE_STATUSES: EpisodeStatus[] = [
  'ready_for_review',
  'ivr_sent',
  'ivr_verified',
  'sent_to_manufacturer',
  'tracking_added',
  'completed'
];

export const IVR_STATUSES: IVRStatus[] = [
  'pending',
  'verified',
  'expired'
];

export const EPISODE_STATUS_LABELS: Record<EpisodeStatus, string> = {
  ready_for_review: 'Ready for Review',
  ivr_sent: 'IVR Sent',
  ivr_verified: 'IVR Verified',
  sent_to_manufacturer: 'Sent to Manufacturer',
  tracking_added: 'Tracking Added',
  completed: 'Completed'
};

export const IVR_STATUS_LABELS: Record<IVRStatus, string> = {
  pending: 'Pending IVR',
  verified: 'Verified',
  expired: 'Expired'
};
