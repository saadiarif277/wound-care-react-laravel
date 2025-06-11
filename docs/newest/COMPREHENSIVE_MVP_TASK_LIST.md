# MSC Wound Portal - Comprehensive MVP Task List

**Version:** 1.0  
**Date:** January 10, 2025  
**Status:** Based on documentation analysis and REVISED_PLAN.md

## Executive Summary

This task list consolidates all pending work based on the Technical Alignment document, REVISED_PLAN.md, and supporting documentation. The platform has strong foundations but needs specific alignments and feature completions to achieve the MVP goals of 90-second provider workflows and 85-90% administrative time savings.

## Task Categories Overview

1. **Database & Schema Alignment** - Critical foundation updates
2. **Status Workflow Implementation** - Standardize order progression
3. **IVR Process Simplification** - Remove signatures, enable one-click
4. **Sales Rep System Completion** - Connect dashboards to real data
5. **UI/UX Improvements** - Complete missing interfaces
6. **Integration Requirements** - FHIR and external system connections
7. **Testing & Quality Assurance** - Comprehensive test coverage
8. **Security & Compliance** - PHI handling and access control

---

## 1. Database & Schema Alignment Tasks

### 1.1 Schema Cleanup Migration (High Priority)

**Status:** Not Started  
**Timeline:** Week 1  
**Dependencies:** Database access permissions

#### Tasks:
- [ ] Create migration to remove unused product_requests fields:
  - [ ] Remove `medicare_part_b_authorized`
  - [ ] Remove `ivr_bypass_reason`
  - [ ] Remove `ivr_bypassed_at`
  - [ ] Remove `ivr_bypassed_by`
  - [ ] Remove `ivr_signed_at`
  - [ ] Remove `pre_auth_submitted_at`
  - [ ] Remove `pre_auth_approved_at`
  - [ ] Remove `pre_auth_denied_at`

- [ ] Add missing facilities fields:
  - [ ] Add `ptan` VARCHAR(255) DEFAULT NULL
  - [ ] Add `default_place_of_service` VARCHAR(2) DEFAULT '11'

- [ ] Add provider attribution to users table:
  - [ ] Add `acquired_by_rep_id` BIGINT UNSIGNED NULL
  - [ ] Add `acquired_by_subrep_id` BIGINT UNSIGNED NULL
  - [ ] Add `acquisition_date` TIMESTAMP NULL
  - [ ] Add index `idx_acquired_by`

- [ ] Update msc_sales_reps table:
  - [ ] Add `monthly_target` DECIMAL(10,2) DEFAULT 0
  - [ ] Add `provider_count` INT DEFAULT 0

### 1.2 Status Enum Updates (High Priority)

**Status:** Not Started  
**Timeline:** Week 1  
**Dependencies:** Schema migration completion

#### Tasks:
- [ ] Update product_requests status enum:
  - [ ] Change `ivr_confirmed` to `manufacturer_approved`
  - [ ] Update all references in codebase
  - [ ] Update status constants in ProductRequest model
  - [ ] Update frontend status displays

### 1.3 Commission Enhancement Fields (Medium Priority)

**Status:** Not Started  
**Timeline:** Week 1-2  
**Dependencies:** Commission system assessment

#### Tasks:
- [ ] Add missing commission_records fields:
  - [ ] `invoice_number` VARCHAR(255)
  - [ ] `first_application_date` DATE
  - [ ] `tissue_ids` JSON
  - [ ] `payment_delay_days` INT (calculated)
  - [ ] `payment_date` TIMESTAMP
  - [ ] `friendly_patient_id` VARCHAR(10)

- [ ] Add to product_requests:
  - [ ] `friendly_patient_id` VARCHAR(10) (auto-generated)

---

## 2. Status Workflow Implementation

### 2.1 Status Transition Service (Critical)

**Status:** Not Started  
**Timeline:** Week 1  
**Dependencies:** Status enum updates

#### Tasks:
- [ ] Create OrderStatusTransitionService:
  - [ ] Define allowed transitions matrix
  - [ ] Implement validation logic
  - [ ] Add audit logging for transitions
  - [ ] Create unit tests

- [ ] Update controllers to use transition service:
  - [ ] ProductRequestController
  - [ ] AdminOrderCenterController
  - [ ] OrderController

### 2.2 Status Display Standardization (High Priority)

**Status:** Not Started   
**Dependencies:** Status transition service

#### Tasks:
- [ ] Update Admin Order Center:
  - [ ] Implement standardized status badges
  - [ ] Create action availability matrix
  - [ ] Update status color scheme
  - [ ] Add urgent actions filter

- [ ] Update provider dashboards:
  - [ ] Consistent status indicators
  - [ ] Clear progression visualization

---

## 3. IVR Process Simplification

### 3.1 Remove Signature Requirements (Critical)

#### Tasks:
- [ ] Update IvrDocusealService:
  - [ ] Remove all signature field logic
  - [ ] Set `signatureRequired: false` in all calls
  - [ ] Update to PDF-only generation mode
  - [ ] Remove signature tracking fields

- [ ] Update DocuSeal configuration:
  - [ ] Set `generate_pdf_only: true`
  - [ ] Set `auto_submit: false`
  - [ ] Update service config

### 3.2 Implement One-Click IVR Generation (Critical)

#### Tasks:
- [ ] Simplify admin UI:
  - [ ] Remove IVR generation modal
  - [ ] Direct PDF generation on button click
  - [ ] Auto-download generated PDF
  - [ ] Update status immediately

- [ ] Add manufacturer workflow buttons:
  - [ ] "Mark as Sent to Manufacturer" action
  - [ ] "Confirm Manufacturer Approval" with reference field
  - [ ] Timestamp tracking for all actions

### 3.3 Implement 90% Auto-Population Query (Critical)

**Dependencies:** Database schema updates

#### Tasks:
- [ ] Create efficient IVR data query:
  - [ ] Single query for 80% of data from Supabase
  - [ ] Minimal FHIR calls for patient demographics (10%)
  - [ ] Optimize query performance
  - [ ] Add proper indexes

- [ ] Update field mapping:
  - [ ] Map all facility fields (30%)
  - [ ] Map all provider fields (25%)
  - [ ] Map organization fields (20%)
  - [ ] Map request fields (15%)

---

## 4. Sales Rep System Completion

### 4.1 Provider Attribution System (Critical)

**Dependencies:** Database updates, user management

#### Tasks:
- [ ] Create provider attribution service:
  - [ ] Link providers to acquiring sales reps
  - [ ] Handle sub-rep relationships
  - [ ] Track acquisition dates
  - [ ] Support bulk attribution

- [ ] Update commission calculation:
  - [ ] Use provider attribution instead of order.sales_rep_id
  - [ ] Calculate splits based on provider relationships
  - [ ] Only process paid orders

### 4.2 Connect Dashboards to Real Data (High Priority)


#### Tasks:
- [ ] Update SalesRepAnalyticsController:
  - [ ] Replace mock data with real queries
  - [ ] Implement commission summary endpoint
  - [ ] Add provider performance metrics
  - [ ] Create delayed payment tracking

- [ ] Update dashboard components:
  - [ ] Connect to real API endpoints
  - [ ] Implement real-time updates
  - [ ] Add filtering and date ranges
  - [ ] Create export functionality

### 4.3 Sub-Rep Management System (Medium Priority)

**Status:** Not Started  
**Timeline:** Week 3-4  
**Dependencies:** Sales rep dashboard completion

#### Tasks:
- [ ] Create sub-rep invitation system:
  - [ ] Invitation table and model
  - [ ] Email notification service
  - [ ] Registration flow
  - [ ] Parent-sub relationship management

- [ ] Build sub-rep dashboard:
  - [ ] Personal commission tracking
  - [ ] Provider list view
  - [ ] Split visualization
  - [ ] Performance metrics

### 4.4 Commission Payment Integration (High Priority)

**Dependencies:** Payment system understanding

#### Tasks:
- [ ] Link commissions to payment status:
  - [ ] Only calculate when payment_status = 'paid'
  - [ ] Track payment dates
  - [ ] Update commission status workflow
  - [ ] Handle payment delays

- [ ] Create payout processing:
  - [ ] Batch payout generation
  - [ ] Approval workflow
  - [ ] Export for accounting
  - [ ] Payment confirmation tracking

---

## 5. UI/UX Improvements

### 5.1 Complete Admin Order Center (High Priority)

**Dependencies:** Status workflow completion

#### Tasks:
- [ ] Implement missing actions:
  - [ ] Generate IVR button (one-click)
  - [ ] Mark as Sent functionality
  - [ ] Manufacturer approval form
  - [ ] Bulk action support

- [ ] Add quick stats bar:
  - [ ] Pending review count
  - [ ] Awaiting IVR count
  - [ ] In transit count
  - [ ] Real-time updates

### 5.2 Provider Dashboard Enhancements (Medium Priority)

**Dependencies:** None

#### Tasks:
- [ ] Streamline product request form:
  - [ ] Smart field suggestions
  - [ ] Auto-population from history
  - [ ] Progress indicator
  - [ ] Draft saving

- [ ] Add request tracking:
  - [ ] Status timeline view
  - [ ] Estimated completion times
  - [ ] Action notifications

### 5.3 Mobile Optimization (Low Priority)

**Dependencies:** Desktop UI completion

#### Tasks:
- [ ] Responsive design updates:
  - [ ] Order center mobile view
  - [ ] Sales rep dashboard mobile
  - [ ] Touch-friendly actions
  - [ ] Minimum 1280px optimization

---

## 6. Integration Requirements

### 6.1 FHIR Integration Optimization (High Priority)

**Dependencies:** Azure FHIR access

#### Tasks:
- [ ] Minimize PHI access:
  - [ ] Only fetch during IVR generation
  - [ ] Cache-free implementation
  - [ ] Audit all access
  - [ ] Optimize query performance

- [ ] Update patient data handling:
  - [ ] Streamline patient creation
  - [ ] Efficient demographics retrieval
  - [ ] Proper error handling

### 6.2 DocuSeal Integration Updates (Critical)

**Status:** Needs simplification  
**Timeline:** Week 2  
**Dependencies:** DocuSeal API access

#### Tasks:
- [ ] Update template configuration:
  - [ ] Remove signature fields from all templates
  - [ ] Verify manufacturer-specific mappings
  - [ ] Test PDF generation
  - [ ] Update field mappings per UNIVERSAL_IVR_FORM.md

### 6.3 Manufacturer Communication (Medium Priority)

**Status:** Manual process  
**Timeline:** Week 3-4  
**Dependencies:** Business process clarification

#### Tasks:
- [ ] Document manufacturer contacts:
  - [ ] Email addresses for each manufacturer
  - [ ] Submission preferences
  - [ ] Response time expectations

- [ ] Future automation planning:
  - [ ] API integration possibilities
  - [ ] Automated status updates
  - [ ] Electronic approval tracking

---

## 7. Testing & Quality Assurance

### 7.1 Unit Test Coverage (High Priority)

**Status:** Partial coverage  
**Timeline:** Ongoing  
**Dependencies:** Feature completion

#### Tasks:
- [ ] Test status transition logic
- [ ] Test commission calculations with attribution
- [ ] Test IVR generation without signatures
- [ ] Test 90% auto-population query
- [ ] Test payment integration logic

### 7.2 Integration Testing (High Priority)

**Dependencies:** All features complete

#### Tasks:
- [ ] End-to-end order flow testing
- [ ] Commission calculation scenarios
- [ ] FHIR integration testing
- [ ] DocuSeal PDF generation
- [ ] Multi-user scenarios

### 7.3 Performance Testing (Medium Priority)
 
**Dependencies:** Feature completion

#### Tasks:
- [ ] Dashboard load time optimization
- [ ] Query performance testing
- [ ] Concurrent user testing
- [ ] Large dataset handling
- [ ] Mobile performance

---

## 8. Security & Compliance

### 8.1 PHI Access Controls (Critical)

**Dependencies:** None

#### Tasks:
- [ ] Implement comprehensive audit logging:
  - [ ] All PHI access tracked
  - [ ] Business justification required
  - [ ] Regular audit reviews
  - [ ] Compliance reporting

- [ ] Minimize PHI exposure:
  - [ ] Only 10% of IVR from FHIR
  - [ ] No PHI caching
  - [ ] Reference-based architecture
  - [ ] Proper data segregation

### 8.2 Access Control Updates (High Priority)

**Dependencies:** Role definitions

#### Tasks:
- [ ] Verify role permissions:
  - [ ] Sales rep data isolation
  - [ ] Provider access limits
  - [ ] Admin override controls
  - [ ] Cross-organization restrictions

- [ ] Implement financial controls:
  - [ ] Commission visibility rules
  - [ ] Payment data access
  - [ ] Export restrictions

---

## Implementation Priority Matrix

### Week 1: Foundation
1. Database schema migrations
2. Status enum updates
3. Status transition service
4. Basic commission fields

### Week 2: Core Features
1. IVR simplification (no signatures)
2. One-click generation
3. 90% auto-population query
4. Admin Order Center updates

### Week 3: Sales & Integration
1. Provider attribution system
2. Connect sales dashboards
3. Commission calculations
4. Payment integration

### Week 4: Polish & Testing
1. Sub-rep management
2. Mobile optimization
3. Integration testing
4. Performance optimization

---

## Success Metrics

### Technical Metrics
- [ ] 90-second provider workflow achieved
- [ ] 90% IVR auto-population working
- [ ] <2 second dashboard load times
- [ ] 100% test coverage for critical paths

### Business Metrics
- [ ] 85-90% time savings documented
- [ ] Commission accuracy at 100%
- [ ] All sales reps using dashboards
- [ ] Zero PHI exposure incidents

### User Adoption
- [ ] 95% provider satisfaction
- [ ] 95% sales rep dashboard usage
- [ ] <5% support tickets
- [ ] Positive user feedback

---

## Outstanding Questions

### Business Process
1. Commission trigger timing (order/payment/delivery)?
2. Sub-rep split percentages (fixed or variable)?
3. Delayed payment approval process?
4. Manufacturer approval SLAs?

### Technical
1. Patient data access for friendly IDs?
2. Invoice number format requirements?
3. Tissue ID capture process?
4. Payment system integration details?

### Compliance
1. PHI audit retention requirements?
2. Commission data privacy rules?
3. Export restrictions for financial data?
4. Cross-organization data sharing rules?

---

## Next Steps

1. **Immediate** (This Week):
   - Get stakeholder approval on priorities
   - Begin database migrations
   - Start status workflow updates

2. **Short Term** (Next 2 Weeks):
   - Complete IVR simplification
   - Build provider attribution
   - Connect sales dashboards

3. **Medium Term** (Next Month):
   - Full integration testing
   - Performance optimization
   - User training materials

4. **Long Term** (Next Quarter):
   - Mobile app development
   - Advanced analytics
   - Automation enhancements

---

**Document Status:** This task list represents the current state of the MVP implementation needs based on available documentation. It should be reviewed with stakeholders and updated as priorities shift or new requirements emerge.