# MVP Task List

## Overview
This document outlines all remaining tasks to complete the MSC Wound Portal MVP based on the latest documentation review and DOCUSEAL_MAPPING_PLAN.md analysis.

## Priority Levels
- 🔴 **CRITICAL**: Must have for MVP launch
- 🟡 **HIGH**: Important but can be phased
- 🟢 **MEDIUM**: Nice to have for MVP
- 🔵 **LOW**: Post-MVP consideration

---

## 1. Database & Schema Alignment 🔴

### 1.1 Product Request Table Cleanup
- [x] Remove unused fields from `product_requests` table
  - [x] `patient_name`
  - [x] `patient_dob`
  - [x] `patient_gender`
  - [x] `patient_address`
  - [x] `patient_phone`
  - [x] `patient_member_id`
  - [x] `ivr_status` (use order status instead)
  - [x] `ivr_sent_at`
  - [x] `ivr_confirmed_at`
- [x] Keep only non-PHI fields as documented

### 1.2 Facility Model Enhancements
- [x] Add migration for facility fields:
  ```php
  $table->string('npi')->nullable();
  $table->string('tax_id')->nullable();
  $table->string('ptan')->nullable();
  $table->string('medicare_admin_contractor')->nullable();
  $table->enum('default_place_of_service', ['11', '12', '31', '32'])->default('11');
  ```
- [x] Update Facility model with new fillable fields
- [x] Add validation rules for NPI format

### 1.3 Provider Profile Enhancements
- [ ] Add migration for provider profile fields:
  ```php
  $table->string('ptan')->nullable();
  $table->string('medicaid_number')->nullable();
  $table->string('tax_id')->nullable();
  ```
- [ ] Update ProviderProfile model
- [ ] Add validation for provider identifiers

### 1.4 Sales Attribution System
- [ ] Add to users table:
  ```php
  $table->uuid('attributed_sales_rep_id')->nullable();
  $table->foreign('attributed_sales_rep_id')->references('id')->on('msc_sales_reps');
  $table->timestamp('attribution_date')->nullable();
  ```
- [ ] Create provider attribution management interface

### 1.5 Commission Tracking Enhancement
- [ ] Add to orders table:
  ```php
  $table->decimal('payment_amount', 10, 2)->nullable();
  $table->date('payment_date')->nullable();
  $table->string('payment_reference')->nullable();
  $table->enum('payment_status', ['pending', 'paid', 'rejected'])->default('pending');
  ```

---

## 2. Status Workflow Implementation 🔴

### 2.1 Order Status Standardization
- [x] Update order status enum:
  - Change `ivr_confirmed` to `manufacturer_approved`
  - Ensure all statuses align with documentation
- [ ] Create `OrderStatusService` with transition rules:
  ```php
  class OrderStatusService {
      public function canTransition($from, $to);
      public function transition($order, $newStatus);
      public function getNextStatuses($currentStatus);
  }
  ```

### 2.2 Status Display Components
- [x] Update `OrderStatusBadge.tsx` with all statuses
- [x] Create status timeline component
- [x] Add status transition buttons in admin views

### 2.3 Automated Status Transitions
- [ ] Implement automatic transition to `pending_ivr` after submission
- [ ] Add scheduled job for status timeout handling
- [ ] Create status change audit logging

---

## 3. IVR Process Simplification 🔴

### 3.1 Remove Signature Requirements
- [ ] Update `IvrDocusealService` to remove signature fields
- [ ] Modify DocuSeal templates to be PDF-only
- [ ] Remove signature tracking from database

### 3.2 One-Click IVR Generation
- [x] Create `GenerateIVRButton` component
- [x] Add IVR requirement modal:
  - [x] Radio toggle (IVR Required/Not Required)
  - [x] Required justification text field when skipping IVR
  - [ ] Audit log entry for skip decisions
  - [x] Direct route to Approve/Send Back/Deny if IVR skipped
- [ ] Implement IVR generation endpoint:
  ```php
  public function generateIVR(Order $order) {
      // Validate order has required data
      // Generate IVR without signatures
      // Update status to ivr_sent
      // Return PDF for download
  }
  ```
- [ ] Add email notification system:
  - [ ] Email IVR PDF to manufacturer contact
  - [ ] Configure manufacturer email addresses
  - [ ] Track email sent status

### 3.3 Auto-Population Query
- [ ] Implement 90% field population in `IvrDocusealService`:
  - [ ] Fetch facility data with new fields
  - [ ] Fetch provider data with new fields
  - [ ] Query FHIR for minimal PHI (10%)
  - [ ] Apply transformation functions from mapping plan


---

## 4. FHIR Integration Optimization 🔴

### 4.1 Minimize PHI Access
- [ ] Update `AzureFhirClient` to fetch only:
  - Patient address (if needed for IVR)
  - Patient phone (if needed for IVR)
  - Coverage details (for insurance)
- [ ] Remove unnecessary PHI queries
- [ ] Add PHI access audit logging

### 4.2 Secondary Insurance Collection
- [ ] Add optional secondary insurance fields to `PatientInformationStep`:
  ```typescript
  secondary_insurance?: {
    payer_name: string;
    policy_number: string;
    subscriber_name?: string;
  }
  ```
- [ ] Update product request submission to include secondary insurance

### 4.3 Coverage Resource Integration
- [ ] Implement `getCoverageDetails()` in FHIR service
- [ ] Map Coverage resource to insurance fields
- [ ] Handle subscriber information extraction

---

## 5. Sales Rep System Completion 🔴

### 5.1 Provider Attribution
- [ ] Create provider attribution interface in admin
- [ ] Implement attribution rules:
  - First order attribution
  - Manual override capability
  - Attribution date tracking
- [ ] Add attribution to provider profiles

### 5.2 Commission Calculation Engine
- [ ] Complete `CommissionCalculatorService`:
  ```php
  public function calculateCommission($order) {
      // Check if order is paid
      // Get sales rep from provider attribution
      // Apply commission rules
      // Create commission record
  }
  ```
- [ ] Link to actual payment data
- [ ] Handle sub-rep commission splits

### 5.3 Sales Dashboard Real Data
- [ ] Replace mock data in `MscRepDashboard.tsx`
- [ ] Connect to real commission records
- [ ] Implement date range filtering
- [ ] Add export functionality

### 5.4 Sub-Rep Management
- [ ] Create sub-rep management interface
- [ ] Implement commission split configuration
- [ ] Add sub-rep performance tracking

---

## 6. DocuSeal Integration Enhancement 🟡

### 6.1 IVR Field Mapper Service
- [ ] Create `IVRFieldMapper` service class
- [ ] Implement transformation functions:
  - [ ] `mapWoundType()`
  - [ ] `getICD10FromWoundType()`
  - [ ] `getCPTFromProduct()`
  - [ ] `parseAddress()`

### 6.2 Manufacturer-Specific Templates
- [ ] Configure field mappings for each manufacturer
- [ ] Test with each template variation
- [ ] Document manufacturer requirements

### 6.3 PDF Generation Optimization
- [ ] Remove signature placeholders
- [ ] Optimize PDF layout for printing
- [ ] Add barcode/QR code for tracking

---

## 7. UI/UX Improvements 🟡

### 7.1 Admin Order Center Dashboard
- [ ] Dashboard Landing Page:
  - [x] Create sticky filter tabs ("Requiring Action" | "All Orders")
  - [ ] Implement sortable table columns:
    - [ ] Order ID
    - [ ] Provider Name
    - [ ] Patient Identifier (no PHI)
    - [ ] Order Status
    - [ ] Order Request Date
    - [ ] Manufacturer Name
    - [ ] Action Required (Yes/No)
  - [x] Add "Action Required" indicator column
  - [x] Make entire row clickable for detail view
  - [x] Add urgency visual indicators (red dots, warning icons)
  - [x] Implement pagination (LOW priority)
  - [x] Future: Search by Order ID/Provider (post-MVP)
- [x] Implement all action buttons:
  - [x] View Details
  - [x] Generate IVR
  - [x] Update Status  
  - [ ] Add Notes
  - [x] Approve/Send Back/Deny
- [ ] Add bulk actions support (future iteration)
- [x] Implement status-based filtering

### 7.2 Order Detail View Implementation 🔴
- [x] Create Order Detail Page structure:
  - [x] Header with Order ID, Status, Provider, Date
  - [x] Two-column layout:
    - [x] Left: Order metadata, patient ID, provider details
    - [x] Right: Product details, documents, notes, manufacturer info
  - [x] Sticky header with Order ID + status + action buttons
  - [x] Supporting documents display section
  - [x] Action history timeline with timestamps and actors
  - [x] Status change log visualization
- [x] Implement collapsible sections (LOW priority):
  - [x] Supporting Documents
  - [x] Notes/Comments
  - [x] Action History
- [x] Add action buttons based on status:
  - [x] Generate IVR (if pending_ivr)
  - [x] Approve/Send Back/Deny (if ivr_confirmed)
  - [x] Submit to Manufacturer (if approved)
  - [ ] Download documents
  - [ ] View/edit notes

### 7.3 Provider Workflow Streamlining
- [ ] Optimize product request flow for speed
- [ ] Add progress indicators
- [ ] Implement auto-save functionality
- [ ] Add keyboard shortcuts

### 7.4 Mobile Optimization
- [ ] Test and fix responsive layouts
- [ ] Optimize touch interactions
- [ ] Ensure forms work on mobile
- [ ] Minimum 1280px layout width

### 7.5 Visual Design Standards 🟡
- [x] Implement status color scheme:
  - [x] Pending IVR (Gray - #6B7280)
  - [x] IVR Sent (Blue - #3B82F6)
  - [x] IVR Confirmed (Purple - #8B5CF6)
  - [x] Approved (Green - #10B981)
  - [x] Denied (Red - #EF4444)
  - [x] Sent Back (Orange - #F97316)
  - [x] Submitted to Manufacturer (Dark Green - #059669)
- [ ] Add accessibility features:
  - [ ] Keyboard navigation support
  - [ ] Screen reader compatibility
  - [ ] ARIA labels for status indicators
  - [ ] Color contrast compliance
- [x] Progressive disclosure patterns

---

## 8. Testing & Quality Assurance 🟡

### 8.1 Unit Tests
- [ ] Test `IVRFieldMapper` transformations
- [ ] Test status transition logic
- [ ] Test commission calculations
- [ ] Test FHIR integration

### 8.2 Integration Tests
- [ ] End-to-end product request flow
- [ ] IVR generation workflow
- [ ] Commission calculation pipeline
- [ ] Status transition workflows

### 8.3 Performance Testing
- [ ] Optimize database queries
- [ ] Add caching where appropriate
- [ ] Test with large datasets

---

## 9. Security & Compliance 🔴

### 9.1 PHI Access Controls
- [ ] Implement PHI access audit logging
- [ ] Verify role-based access controls
- [ ] Add data retention policies

### 9.2 Financial Data Security
- [ ] Secure commission data access
- [ ] Implement payment data encryption
- [ ] Add financial audit trails

### 9.3 Authentication Enhancement
- [ ] Implement session timeout
- [ ] Add two-factor authentication (optional)
- [ ] Enhance password policies

---

## 10. Admin Order Creation 🔴

### 10.1 Create Order on Behalf of Provider
- [ ] Add "Create Order" button to Admin Order Center dashboard
- [ ] Create admin order flow interface:
  - [ ] Provider selection dropdown (searchable)
  - [ ] Patient selection from FHIR database
  - [ ] Reuse existing provider order flow components
  - [ ] Maintain same validation rules
- [ ] Tag admin-created orders:
  - [ ] Add `created_by_admin` flag to orders table
  - [ ] Store admin user ID who created the order
  - [ ] Display "Created by Admin" indicator in order lists
- [ ] Ensure admin-created orders follow same workflow:
  - [ ] Same IVR generation process
  - [ ] Same approval workflow
  - [ ] Same audit trail requirements

---

## 11. Email Notification System 🟡

### 11.1 Email Service Implementation
- [ ] Create email notification service:
  - [ ] Configure email provider (SendGrid/SES/SMTP)
  - [ ] Create email queue for reliability
  - [ ] Add email logging for audit trail
- [ ] Implement email templates:
  - [ ] IVR submission to manufacturer (with PDF attachment)
  - [ ] Provider notification for Send Back status
  - [ ] Provider notification for Denied status
  - [ ] Order approval confirmation
  - [ ] Manufacturer submission confirmation
- [ ] Add notification preferences:
  - [ ] Provider email notification toggle
  - [ ] Configurable email addresses per manufacturer
  - [ ] CC/BCC options for compliance

### 11.2 Manufacturer Email Configuration
- [ ] Add email contacts to manufacturer data:
  - [ ] Primary IVR email address
  - [ ] Secondary/backup email addresses
  - [ ] Email format preferences
- [ ] Create manufacturer email management interface
- [ ] Test email delivery for each manufacturer

---

## 12. External Integrations 🟢

### 10.1 Payer Database
- [ ] Create payer lookup table
- [ ] Import payer phone numbers
- [ ] Create payer search interface

### 10.2 Medicare MAC Lookup
- [ ] Implement ZIP to MAC mapping
- [ ] Create MAC lookup service
- [ ] Add to facility profiles

### 10.3 Manufacturer Communication
- [ ] Plan API integration strategy
- [ ] Document communication protocols
- [ ] Create webhook endpoints

---

## 11. Documentation & Training 🟢

### 11.1 User Documentation
- [ ] Create provider quick start guide
- [ ] Document admin workflows
- [ ] Create video tutorials

### 11.2 Technical Documentation
- [ ] Document API endpoints
- [ ] Create deployment guide
- [ ] Document configuration options

### 11.3 Training Materials
- [ ] Create training environment
- [ ] Develop training scenarios
- [ ] Create certification process

---

## Outstanding Questions to Resolve

1. **Commission Structure**: Confirm exact commission percentages and split rules
2. **Payment Integration**: Determine payment tracking system integration
3. **FHIR Permissions**: Confirm minimal PHI access requirements
4. **Audit Requirements**: Clarify compliance audit trail needs

---

## Success Metrics

### Technical Metrics
- [ ] 90-second average provider workflow completion
- [ ] 90% IVR field auto-population rate
- [ ] < 10% PHI access from FHIR
- [ ] 100% automated status transitions

### Business Metrics
- [ ] 85-90% reduction in administrative time
- [ ] 100% commission tracking accuracy
- [ ] < 24 hour IVR turnaround time
- [ ] 95% manufacturer approval rate

---