# Sales Rep Dashboard Implementation Plan

## Overview

This plan outlines the implementation of a comprehensive sales dashboard for MSC sales representatives and sub-representatives to track commissions, monitor provider performance, and view order details based on the provided field requirements.

## Key Requirements Summary

### High Priority Fields

- **Commission Amount**: Individual order commission values
- **Total Commissions Paid**: Aggregated by date range
- **Pending Payments**: Unpaid commissions due to unmet triggers
- **Commission Status**: Current state (Paid, Pending, Processing)
- **Invoice #**: Associated with each order
- **First Date of Application**: When product was first used

### Medium Priority Fields

- **Commission Payment Date**: For calculating average payout times
- **Provider Name**: Source of commission
- **Rep/Sub-rep Split**: Payment distribution breakdown
- **Date of Service**: When applications were used
- **Sizes**: Product sizes used in orders
- **Product Used**: Specific products ordered
- **Friendly Patient ID**: First 2 letters of first/last name format

### Additional Requirements

- **Delayed Payment Tracking**: Aging report for payments >60 days
- **Tissue IDs**: Associated with each order
- **Date Range Filtering**: For all financial metrics
- **Status Filtering**: For commission tracking

## Dashboard Architecture

### 1. Data Model Requirements

#### Commission Tracking Table (commission_records - already exists)

```sql
-- Existing fields that support requirements:
- order_id (links to orders/product_requests)
- rep_id (sales rep earning commission)
- parent_rep_id (for split calculations)
- amount (commission amount)
- percentage_rate (for split calculations)
- status (pending, approved, paid)
- payout_id (links to payment batch)

-- New fields needed:
ALTER TABLE commission_records ADD COLUMN payment_delay_days INT DEFAULT 0;
ALTER TABLE commission_records ADD COLUMN invoice_number VARCHAR(255);
ALTER TABLE commission_records ADD COLUMN first_application_date DATE;
ALTER TABLE commission_records ADD COLUMN tissue_ids JSON;
```

#### Product Requests Enhancement

```sql
-- Add tracking for patient-friendly ID
ALTER TABLE product_requests ADD COLUMN friendly_patient_id VARCHAR(10);
-- Format: JO-SM-1234 (first 2 of first, first 2 of last, random 4 digits)
```

### 2. Dashboard Components

#### A. Commission Overview Widget

```typescript
interface CommissionOverview {
  totalPaid: {
    amount: number;
    currency: 'USD';
    dateRange: DateRange;
  };
  pendingPayments: {
    amount: number;
    count: number;
    reasons: {
      awaitingDelivery: number;
      paymentProcessing: number;
      over60Days: number;
    };
  };
  averagePayoutTime: number; // in days
  nextPayoutDate: Date;
}
```

#### B. Commission Details Table

```typescript
interface CommissionDetail {
  // High Priority
  commissionAmount: number;
  commissionStatus: 'paid' | 'pending' | 'processing';
  invoiceNumber: string;
  firstApplicationDate: Date;
  
  // Medium Priority
  orderId: string;
  providerName: string;
  facilityName: string;
  repSubrepSplit: {
    repPercentage: number;
    subRepPercentage: number;
    repAmount: number;
    subRepAmount: number;
  };
  dateOfService: Date;
  productDetails: {
    name: string;
    sizes: string[];
    totalValue: number;
  };
  friendlyPatientId: string;
  
  // Additional
  paymentDate?: Date;
  delayedDays?: number;
  tissueIds: string[];
  manufacturerName: string;
}
```

#### C. Delayed Payment Alert System

```typescript
interface DelayedPaymentAlert {
  orderId: string;
  daysDelayed: number;
  originalDueDate: Date;
  amount: number;
  reason: string;
  providerName: string;
  facilityName: string;
}
```

### 3. API Endpoints

#### Sales Rep Analytics API Enhancement

```php
// GET /api/sales-rep/commission-summary
{
  "dateRange": {
    "start": "2025-01-01",
    "end": "2025-01-31"
  },
  "totals": {
    "paid": 45000.00,
    "pending": 12500.00,
    "processing": 3200.00
  },
  "byStatus": {
    "paid": { "count": 45, "amount": 45000.00 },
    "pending": { "count": 23, "amount": 12500.00 },
    "processing": { "count": 8, "amount": 3200.00 }
  },
  "averagePayoutDays": 42,
  "nextPayoutDate": "2025-02-15"
}

// GET /api/sales-rep/commission-details
{
  "filters": {
    "dateRange": { "start": "2025-01-01", "end": "2025-01-31" },
    "status": ["paid", "pending"],
    "provider": "provider_id",
    "manufacturer": "manufacturer_id"
  },
  "data": [
    {
      "id": "comm_123",
      "orderId": "REQ-001",
      "invoiceNumber": "INV-2025-001",
      "providerName": "Dr. Sarah Johnson",
      "facilityName": "Metro Wound Care",
      "friendlyPatientId": "JO-SM-4782",
      "dateOfService": "2025-01-15",
      "firstApplicationDate": "2025-01-16",
      "product": {
        "name": "XCELLERATE",
        "manufacturer": "Extremity Care",
        "sizes": ["4x4cm", "2x2cm"],
        "qCode": "Q4234"
      },
      "orderValue": 10062.08,
      "commissionAmount": 503.10,
      "split": {
        "type": "sub-rep",
        "repAmount": 251.55,
        "subRepAmount": 251.55,
        "repPercentage": 50,
        "subRepPercentage": 50
      },
      "status": "paid",
      "paymentDate": "2025-01-30",
      "payoutBatch": "PAYOUT-2025-01",
      "tissueIds": ["TISSUE-123", "TISSUE-124"]
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 50,
    "total": 125
  }
}

// GET /api/sales-rep/delayed-payments
{
  "thresholdDays": 60,
  "data": [
    {
      "orderId": "REQ-045",
      "invoiceNumber": "INV-2024-045",
      "daysDelayed": 75,
      "originalDueDate": "2024-11-15",
      "amount": 1250.00,
      "reason": "Insurance claim pending",
      "provider": "Dr. Michael Chen",
      "facility": "Regional Hospital"
    }
  ],
  "summary": {
    "totalDelayed": 5,
    "totalAmount": 6750.00,
    "averageDelay": 68
  }
}
```

### 4. UI Components

#### Main Dashboard Layout

```tsx
// SalesRepDashboard.tsx
const SalesRepDashboard = () => {
  return (
    <div className="sales-dashboard">
      {/* Top Row - Key Metrics */}
      <div className="metrics-row">
        <CommissionSummaryCard />
        <PendingPaymentsCard />
        <AveragePayoutTimeCard />
        <NextPayoutCard />
      </div>
      
      {/* Filters Bar */}
      <FilterBar>
        <DateRangePicker />
        <StatusFilter />
        <ProviderFilter />
        <ManufacturerFilter />
      </FilterBar>
      
      {/* Main Content */}
      <Tabs>
        <Tab title="Commission Details">
          <CommissionDetailsTable />
        </Tab>
        <Tab title="Delayed Payments">
          <DelayedPaymentsTable />
        </Tab>
        <Tab title="Provider Performance">
          <ProviderPerformanceGrid />
        </Tab>
      </Tabs>
    </div>
  );
};
```

#### Commission Details Table Component

```tsx
interface CommissionTableColumns {
  invoiceNumber: { sortable: true, priority: 'high' };
  providerName: { sortable: true, priority: 'medium' };
  friendlyPatientId: { sortable: false, priority: 'medium' };
  dateOfService: { sortable: true, priority: 'medium' };
  firstApplicationDate: { sortable: true, priority: 'high' };
  product: { sortable: true, priority: 'medium' };
  sizes: { sortable: false, priority: 'medium' };
  orderValue: { sortable: true, priority: 'low' };
  commissionAmount: { sortable: true, priority: 'high' };
  split: { sortable: false, priority: 'medium' };
  status: { sortable: true, priority: 'high' };
  paymentDate: { sortable: true, priority: 'medium' };
  actions: { expandable: true }; // View details, tissue IDs
}
```

### 5. Business Logic Implementation

#### Commission Calculation Service

```php
class EnhancedCommissionService {
    public function calculateCommissionForOrder($orderId) {
        $order = ProductRequest::with(['provider', 'items.product'])
            ->findOrFail($orderId);
        
        // Get provider's sales rep attribution
        $provider = $order->provider;
        $repId = $provider->acquired_by_rep_id;
        $subRepId = $provider->acquired_by_subrep_id;
        
        // Calculate base commission
        $totalValue = $order->total_order_value;
        $commissionRate = $this->getCommissionRate($order);
        $totalCommission = $totalValue * ($commissionRate / 100);
        
        // Handle rep/sub-rep split
        if ($subRepId) {
            $splitPercentage = $this->getSubRepSplitPercentage($subRepId);
            $repAmount = $totalCommission * ((100 - $splitPercentage) / 100);
            $subRepAmount = $totalCommission * ($splitPercentage / 100);
            
            // Create commission records for both
            $this->createCommissionRecord($order, $repId, $repAmount, 'parent_rep');
            $this->createCommissionRecord($order, $subRepId, $subRepAmount, 'sub_rep');
        } else {
            // Full commission to rep
            $this->createCommissionRecord($order, $repId, $totalCommission, 'direct');
        }
    }
    
    private function createCommissionRecord($order, $repId, $amount, $type) {
        return CommissionRecord::create([
            'order_id' => $order->id,
            'rep_id' => $repId,
            'amount' => $amount,
            'type' => $type,
            'status' => 'pending',
            'invoice_number' => $this->generateInvoiceNumber($order),
            'first_application_date' => $order->expected_service_date,
            'tissue_ids' => $this->extractTissueIds($order),
            'friendly_patient_id' => $this->generateFriendlyPatientId($order)
        ]);
    }
    
    private function generateFriendlyPatientId($order) {
        // Fetch patient name from FHIR
        $patient = $this->fhirService->getPatient($order->patient_fhir_id);
        $firstName = substr($patient->name[0]->given[0], 0, 2);
        $lastName = substr($patient->name[0]->family, 0, 2);
        $random = rand(1000, 9999);
        return strtoupper("{$firstName}-{$lastName}-{$random}");
    }
}
```

#### Delayed Payment Monitoring

```php
class DelayedPaymentMonitoringService {
    const PAYMENT_TERMS_DAYS = 60;
    
    public function getDelayedPayments($repId) {
        return CommissionRecord::where('rep_id', $repId)
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subDays(self::PAYMENT_TERMS_DAYS))
            ->with(['order.provider', 'order.facility'])
            ->get()
            ->map(function ($record) {
                $daysDelayed = now()->diffInDays($record->created_at) - self::PAYMENT_TERMS_DAYS;
                return [
                    'order_id' => $record->order->request_number,
                    'invoice_number' => $record->invoice_number,
                    'days_delayed' => $daysDelayed,
                    'original_due_date' => $record->created_at->addDays(self::PAYMENT_TERMS_DAYS),
                    'amount' => $record->amount,
                    'provider' => $record->order->provider->full_name,
                    'facility' => $record->order->facility->name
                ];
            });
    }
}
```

### 6. Database Migrations

```sql
-- Migration: Add sales dashboard fields
CREATE MIGRATION add_sales_dashboard_fields:

-- Add friendly patient ID to product requests
ALTER TABLE product_requests 
ADD COLUMN friendly_patient_id VARCHAR(10) GENERATED ALWAYS AS (
    CONCAT(
        UPPER(SUBSTR(patient_first_name, 1, 2)),
        '-',
        UPPER(SUBSTR(patient_last_name, 1, 2)),
        '-',
        LPAD(FLOOR(RAND() * 10000), 4, '0')
    )
) STORED;

-- Add invoice tracking to commission records
ALTER TABLE commission_records
ADD COLUMN invoice_number VARCHAR(255),
ADD COLUMN first_application_date DATE,
ADD COLUMN tissue_ids JSON,
ADD COLUMN payment_delay_days INT GENERATED ALWAYS AS (
    CASE 
        WHEN status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 DAY)
        THEN DATEDIFF(NOW(), DATE_ADD(created_at, INTERVAL 60 DAY))
        ELSE 0
    END
) STORED;

-- Add indexes for performance
CREATE INDEX idx_commission_delay ON commission_records(payment_delay_days) WHERE payment_delay_days > 0;
CREATE INDEX idx_commission_invoice ON commission_records(invoice_number);
CREATE INDEX idx_friendly_patient ON product_requests(friendly_patient_id);
```

### 7. Implementation Timeline

#### Phase 1: Database & API (Week 1)

- [ ] Create database migrations for new fields
- [ ] Update commission calculation service
- [ ] Build enhanced API endpoints
- [ ] Implement delayed payment monitoring

#### Phase 2: UI Components (Week 2)

- [ ] Build commission overview widgets
- [ ] Create commission details table
- [ ] Implement delayed payments view
- [ ] Add filtering and sorting

#### Phase 3: Integration & Testing (Week 3)

- [ ] Connect UI to real API data
- [ ] Implement real-time updates
- [ ] Add export functionality
- [ ] Performance optimization

#### Phase 4: Enhancements (Week 4)

- [ ] Add charts and visualizations
- [ ] Implement email notifications for delayed payments
- [ ] Create printable commission statements
- [ ] Mobile optimization

### 8. Success Metrics

1. **Commission Accuracy**: 100% accurate commission calculations
2. **Payment Visibility**: Real-time status for all commissions
3. **Delayed Payment Reduction**: 50% reduction in >60 day payments
4. **User Adoption**: 95% of sales reps actively using dashboard
5. **Performance**: <2 second load time for commission details

### 9. Security Considerations

- Role-based access: Reps see only their commissions
- Parent reps can view sub-rep performance
- Sensitive patient data (names) only used for friendly ID generation
- Audit trail for all commission modifications
- Encrypted storage for financial data

### 10. Future Enhancements

1. **Predictive Analytics**: Forecast future commissions
2. **Goal Tracking**: Set and monitor commission targets
3. **Team Leaderboards**: Gamification for sales teams
4. **Mobile App**: Native mobile dashboard
5. **Automated Reporting**: Weekly/monthly email summaries
