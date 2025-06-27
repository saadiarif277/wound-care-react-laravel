export interface CommissionSummary {
  dateRange: {
    start: string;
    end: string;
  };
  totals: {
    paid: number;
    pending: number;
    processing: number;
  };
  byStatus: {
    paid: { count: number; amount: number };
    pending: { count: number; amount: number };
    processing: { count: number; amount: number };
  };
  averagePayoutDays: number;
  nextPayoutDate: string;
}

export interface CommissionDetail {
  id: string;
  orderId: string;
  invoiceNumber?: string;
  providerName: string;
  facilityName: string;
  friendlyPatientId?: string;
  dateOfService: string;
  firstApplicationDate?: string;
  product: {
    name: string;
    manufacturer: string;
    sizes: string[];
    qCode?: string;
  };
  orderValue: number;
  commissionAmount: number;
  split?: {
    type: 'sub-rep' | 'direct';
    repAmount: number;
    subRepAmount: number;
    repPercentage: number;
    subRepPercentage: number;
  };
  status: 'pending' | 'approved' | 'paid' | 'disputed';
  paymentDate?: string;
  payoutBatch?: string;
  tissueIds: string[];
}

export interface DelayedPayment {
  orderId: string;
  invoiceNumber?: string;
  daysDelayed: number;
  originalDueDate: string;
  amount: number;
  reason: string;
  provider: string;
  facility: string;
}

export interface DelayedPaymentsResponse {
  thresholdDays: number;
  data: DelayedPayment[];
  summary: {
    totalDelayed: number;
    totalAmount: number;
    averageDelay: number;
  };
}

export interface CommissionTrend {
  period: string;
  totalCommission: number;
  commissionCount: number;
  avgCommission: number;
}

export interface TopProvider {
  providerId: string;
  providerName: string;
  orderCount: number;
  totalCommission: number;
  avgCommission: number;
}

export interface ProductPerformance {
  productId: string;
  productName: string;
  manufacturer: string;
  unitsSold: number;
  totalCommission: number;
}

export interface MonthlyTarget {
  currentMonthTarget: number;
  currentMonthActual: number;
  achievementPercentage: number;
}

export interface PaymentTimeline {
  status: string;
  count: number;
  amount: number;
  avgDaysToPayment: number;
}

export interface CommissionAnalytics {
  commissionTrend: CommissionTrend[];
  topProviders: TopProvider[];
  productPerformance: ProductPerformance[];
  monthlyTargets: MonthlyTarget;
  paymentTimeline: PaymentTimeline[];
}

export interface CommissionFilters {
  dateFrom?: string;
  dateTo?: string;
  status?: string[];
  provider?: string;
  manufacturer?: string;
  page?: number;
  perPage?: number;
}

export interface CommissionDetailsResponse {
  data: CommissionDetail[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface SalesRepInfo {
  id: string;
  name: string;
  email: string;
  territory: string;
  repType: 'msc-rep' | 'msc-subrep';
  parentRepId?: string;
  parentRepName?: string;
  commissionRate: number;
  isActive: boolean;
}

export interface CommissionMetricCard {
  title: string;
  value: number | string;
  change?: number;
  changeType?: 'increase' | 'decrease' | 'neutral';
  format?: 'currency' | 'number' | 'percentage';
  subtitle?: string;
  icon?: React.ReactNode;
}

export interface DashboardFilters {
  dateRange: {
    start: string;
    end: string;
  };
  statusFilter: string[];
  providerFilter?: string;
  manufacturerFilter?: string;
}

export interface CommissionExportOptions {
  format: 'csv' | 'pdf' | 'excel';
  dateRange: {
    start: string;
    end: string;
  };
  includeDetails: boolean;
  includeDelayedPayments: boolean;
}
