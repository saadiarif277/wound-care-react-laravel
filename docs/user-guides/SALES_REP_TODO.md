# Sales Rep Dashboard Todo List

## Overview

This document tracks the implementation progress for the Sales Rep Dashboard based on requirements from SALES_REP_DASHBOARD.md and current system assessment.

## Current State Assessment

### ✅ What's Already Done

1. **Database Infrastructure**
   - `msc_sales_reps` table with parent/sub-rep relationships
   - `commission_rules` table for flexible commission rates
   - `commission_records` table for tracking individual commissions
   - `commission_payouts` table for aggregated payouts
   - Basic commission calculation services (CommissionRuleFinderService, OrderItemCommissionCalculatorService)

2. **Backend Services**
   - Core commission calculation logic
   - Basic payout processing service
   - Commission rule finder (product → manufacturer → category → default)

3. **Basic UI**
   - `/commission/management` page exists with 5 tabs:
     - Overview (analytics summary)
     - Commission Tracking
     - Payout Management
     - Sales Rep Management
     - Sub-Rep Approvals
   - Mock data structure in place (no real API integration yet)

### ❌ Critical Missing Components

1. **Provider Attribution System** - No link between providers and sales reps who brought them
2. **Payment Integration** - Commissions not tied to actual payment status
3. **Real API Endpoints** - Frontend uses mock data, no actual backend APIs
4. **Sales Rep Dashboard** - Admin view exists but no sales rep-specific dashboard
5. **Data Fields** - Missing many required fields from requirements

## Implementation Todo List

### Phase 1: Database & Model Updates (Week 1)

#### High Priority - Provider Attribution System

- [ ] Add provider-sales rep relationship tracking to users table

  ```sql
  ALTER TABLE users ADD COLUMN acquired_by_rep_id BIGINT NULL;
  ALTER TABLE users ADD COLUMN acquired_by_subrep_id BIGINT NULL;
  ALTER TABLE users ADD COLUMN acquisition_date TIMESTAMP NULL;
  ```

- [ ] Create provider attribution tracking service
- [ ] Update commission calculation to use provider attribution instead of order.sales_rep_id

#### High Priority - Commission Record Enhancements

- [ ] Add missing fields to commission_records table:
  - [ ] `invoice_number` VARCHAR(255)
  - [ ] `first_application_date` DATE
  - [ ] `tissue_ids` JSON
  - [ ] `payment_delay_days` INT (calculated field)
  - [ ] `payment_date` TIMESTAMP
  - [ ] `friendly_patient_id` VARCHAR(10)

#### Medium Priority - Product Request Updates

- [ ] Add `friendly_patient_id` to product_requests table (auto-generated: JO-SM-1234 format)
- [ ] Add tissue tracking fields
- [ ] Link to actual patient FHIR data for friendly ID generation

### Phase 2: Backend API Development (Week 1-2)

#### High Priority - Sales Rep Dashboard APIs

- [ ] Create `/api/sales-rep/commission-summary` endpoint
  - [ ] Total paid commissions by date range
  - [ ] Pending commissions with reasons
  - [ ] Average payout time calculation
  - [ ] Next expected payout date
  
- [ ] Create `/api/sales-rep/commission-details` endpoint
  - [ ] Filter by date range, status, provider, manufacturer
  - [ ] Include all required fields from dashboard plan
  - [ ] Support pagination and sorting
  
- [ ] Create `/api/sales-rep/delayed-payments` endpoint
  - [ ] Track payments >60 days
  - [ ] Include aging report data
  - [ ] Provide delay reasons

#### High Priority - Payment Status Integration

- [ ] Update commission calculation to only trigger on `payment_status = 'paid'`
- [ ] Add payment date tracking to orders
- [ ] Create payment status change event handlers
- [ ] Update commission status workflow

#### Medium Priority - Analytics APIs

- [ ] Create `/api/sales-rep/analytics` endpoint
  - [ ] Provider performance metrics
  - [ ] Monthly/quarterly trends
  - [ ] Product category breakdown
  - [ ] Territory performance

### Phase 3: Frontend Implementation (Week 2)

#### High Priority - Sales Rep Dashboard Component

- [ ] Create new `SalesRepDashboard.tsx` component
- [ ] Implement commission overview widgets:
  - [ ] Total Paid Commissions (with date range filter)
  - [ ] Pending Payments widget
  - [ ] Average Payout Time card
  - [ ] Next Payout Date indicator

#### High Priority - Commission Details Table

- [ ] Build comprehensive commission table with all required columns:
  - [ ] Invoice Number (sortable)
  - [ ] Provider Name & Facility
  - [ ] Friendly Patient ID
  - [ ] Date of Service
  - [ ] First Application Date
  - [ ] Product & Sizes
  - [ ] Commission Amount & Split
  - [ ] Status with payment date
  - [ ] Expandable row for tissue IDs

#### High Priority - Delayed Payments View

- [ ] Create delayed payments table/list
- [ ] Add aging indicators (60+ days, 90+ days)
- [ ] Include delay reasons
- [ ] Add export functionality

#### Medium Priority - Filtering & Search

- [ ] Date range picker component
- [ ] Multi-select filters for:
  - [ ] Commission status
  - [ ] Providers
  - [ ] Manufacturers
  - [ ] Payment status
- [ ] Global search functionality

### Phase 4: Commission Calculation Updates (Week 2-3)

#### High Priority - Provider-Based Commission Logic

- [ ] Update `OrderCommissionProcessorService`:
  - [ ] Use provider's `acquired_by_rep_id` instead of order's sales_rep_id
  - [ ] Handle parent/sub-rep splits based on provider attribution
  - [ ] Only calculate on paid orders

#### High Priority - Invoice & Tracking

- [ ] Implement invoice number generation
- [ ] Add tissue ID tracking from product requests
- [ ] Calculate and store first application date
- [ ] Generate friendly patient IDs

### Phase 5: Sub-Rep System (Week 3)

#### High Priority - Sub-Rep Dashboard

- [ ] Create sub-rep specific dashboard view
- [ ] Show only their commissions and splits
- [ ] Provider list they've brought in
- [ ] Personal performance metrics

#### Medium Priority - Sub-Rep Invitation System

- [ ] Create invitation workflow
- [ ] Email notification system
- [ ] Registration flow for invited sub-reps
- [ ] Link to parent rep on registration

### Phase 6: Reporting & Export (Week 3-4)

#### High Priority - Export Functionality

- [ ] Commission statement PDF generation
- [ ] CSV export for commission details
- [ ] Printable commission reports
- [ ] Email delivery of statements

#### Medium Priority - Advanced Analytics

- [ ] Commission forecasting
- [ ] Provider acquisition trends
- [ ] Product performance by rep
- [ ] Territory heat maps

### Phase 7: Testing & Optimization (Week 4)

#### High Priority - Testing

- [ ] Unit tests for commission calculation with provider attribution
- [ ] Integration tests for payment status workflow
- [ ] E2E tests for sales rep dashboard
- [ ] Performance testing for large datasets

#### High Priority - Performance

- [ ] Add database indexes for commission queries
- [ ] Implement caching for analytics
- [ ] Optimize commission calculation queries
- [ ] Add pagination to all list views

### Phase 8: Background Jobs & Automation (Week 4+)

#### Medium Priority - Automated Processes

- [ ] Daily commission calculation job
- [ ] Weekly payout generation
- [ ] Monthly commission statements
- [ ] Delayed payment notifications

#### Low Priority - Notifications

- [ ] Email alerts for new commissions
- [ ] SMS for payout processing
- [ ] Dashboard notifications
- [ ] Webhook integration

## Questions Needing Clarification

### Business Logic Questions

1. **Commission Triggers**: Should commissions be calculated when:
   - Order is placed?
   - Payment is received?
   - Product is delivered?
   - First application date is recorded?

2. **Split Rules**: For sub-rep commissions:
   - Is the split always 50/50?
   - Can it vary by sub-rep or agreement?
   - Who sets the split percentage?

3. **Delayed Payment Handling**:
   - What constitutes a valid delay reason?
   - Who can approve delayed payments?
   - Are there penalties for delays?

### Technical Questions

1. **Patient Data**:
   - How do we access patient names for friendly ID generation?
   - Should we store the friendly ID or generate it dynamically?
   - What happens if patient data changes?

2. **Invoice Numbers**:
   - What format should invoice numbers follow?
   - Are they generated per order or per commission?
   - Do they need to be sequential?

3. **Tissue IDs**:
   - Where are tissue IDs captured in the current flow?
   - How many tissue IDs per order typically?
   - Format/validation requirements?

### UI/UX Questions

1. **Dashboard Access**:
   - Should sub-reps see their parent rep's performance?
   - Can reps see other reps' territories?
   - What admin overrides are needed?

2. **Mobile Experience**:
   - Is mobile access required for sales reps?
   - Which features are priority for mobile?
   - Native app or responsive web?

## Success Metrics to Track

1. **Accuracy**: 100% accurate commission calculations
2. **Timeliness**: <2 second load time for commission details
3. **Adoption**: 95% of sales reps actively using dashboard
4. **Payment Speed**: 50% reduction in >60 day delayed payments
5. **Data Quality**: 100% of orders have complete commission data

## Next Steps

1. **Immediate** (This Week):
   - Get clarification on business logic questions
   - Start database migrations for missing fields
   - Begin provider attribution system

2. **Short Term** (Next 2 Weeks):
   - Implement core APIs
   - Build sales rep dashboard UI
   - Update commission calculation logic

3. **Medium Term** (Next Month):
   - Complete sub-rep system
   - Add reporting/export features
   - Performance optimization

4. **Long Term** (Next Quarter):
   - Mobile app development
   - Advanced analytics
   - AI-powered insights
