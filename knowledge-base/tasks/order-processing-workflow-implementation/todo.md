# MSC Platform Order Processing Workflow Implementation Plan

## Overview

This plan addresses the implementation of the complete order processing workflow where Providers/Office Managers create and review orders with IVR pre-generation before submission, followed by dual notifications and Admin-managed manufacturer communications via DocuSeal.

## Current State Analysis

### ✅ **What's Working:**
- **Basic Infrastructure**: QuickRequestController, Order/ProductRequest models, DocuSeal integration
- **FHIR Integration**: PHI data properly separated in Azure FHIR
- **Role-Based Access**: Basic RBAC system with permissions
- **DocuSeal Service**: Full API integration for form generation and submission
- **Notification Framework**: Basic email notification system exists

### ❌ **Critical Gaps Identified:**

1. **IVR Timing Issue**: IVR is generated AFTER submission, but requirement is BEFORE submission during order review
2. **Missing Dual Notifications**: Only basic notifications exist, need both Provider/OM AND Admin notifications
3. **Financial Data Exposure**: Office Managers can currently see ASP and Amount to be Billed (should be hidden)
4. **No Pre-submission Review**: Missing order review page with consent checkbox
5. **Limited Provider Access**: No deep link system for Provider/OM to track order progress
6. **Edit Restrictions**: No proper edit controls based on submission status

## Task Implementation Plan

### Phase 1: Core Backend Workflow (High Priority)

#### ✅ Task 001: Analyze current implementation gaps
**Status**: Completed
- Reviewed QuickRequestController, Order models, and notification systems
- Identified key gaps between current implementation and requirements

#### 🔄 Task 002: Create comprehensive implementation plan
**Status**: In Progress
- Creating this detailed plan document
- Defining implementation phases and task priorities

#### ⏳ Task 003: Fix QuickRequestController submit method 
**Status**: Pending | **Priority**: High
**Details**:
- Modify `submit()` method to implement proper pre-submission workflow
- Add IVR generation BEFORE order submission 
- Implement order review validation
- Add consent checkbox requirement
- Create proper order status transitions

**Files to modify**:
- `app/Http/Controllers/Api/V1/QuickRequestController.php`
- `app/Services/QuickRequestService.php`

#### ⏳ Task 004: Implement dual notification system
**Status**: Pending | **Priority**: High
**Details**:
- Send notifications to BOTH Provider/OM and Admin on submission
- Create deep link generation for order tracking
- Implement notification templates for different recipient types
- Add notification logging and tracking

**Files to create/modify**:
- `app/Services/OrderNotificationService.php` (new)
- `app/Mail/OrderSubmissionNotification.php` (new)
- `app/Mail/AdminOrderAlertNotification.php` (new)

#### ⏳ Task 005: Add financial data visibility controls
**Status**: Pending | **Priority**: High
**Details**:
- Create middleware to filter financial data based on user role
- Hide ASP and Amount to be Billed from Office Managers
- Implement granular field-level access controls
- Add audit logging for financial data access

**Files to create/modify**:
- `app/Http/Middleware/FilterFinancialDataMiddleware.php` (new)
- `app/Services/FinancialDataFilterService.php` (new)
- API response transformers for role-based filtering

### Phase 2: Frontend Order Review Interface (High Priority)

#### ⏳ Task 006: Create order review page with pre-submission IVR
**Status**: Pending | **Priority**: High
**Details**:
- Build React component for order review page
- Implement IVR pre-generation during review
- Add consent checkbox and validation
- Show/hide financial data based on user role
- Enable edit navigation back to previous steps

**Files to create**:
- `resources/js/Pages/Orders/OrderReview.tsx` (new)
- `resources/js/Components/Orders/IVRPreview.tsx` (new)
- `resources/js/Components/Orders/ConsentCheckbox.tsx` (new)
- `resources/js/Components/Orders/FinancialSummary.tsx` (new)

#### ⏳ Task 010: Implement role-based API response filtering
**Status**: Pending | **Priority**: High
**Details**:
- Create API resource transformers for different user roles
- Implement conditional field inclusion based on permissions
- Add financial data masking for Office Managers
- Ensure PHI compliance throughout

**Files to create/modify**:
- `app/Http/Resources/OrderResource.php` (new)
- `app/Http/Resources/ProductRequestResource.php` (new)
- Update existing API controllers to use role-based resources

### Phase 3: Order Management & Tracking (Medium Priority)

#### ⏳ Task 007: Implement deep link access for Provider/OM tracking
**Status**: Pending | **Priority**: Medium
**Details**:
- Create unique deep link generation for orders
- Build Provider/OM order tracking interface
- Implement read-only view for submitted orders
- Add order status updates and notifications

**Files to create**:
- `app/Services/DeepLinkService.php` (new)
- `resources/js/Pages/Orders/OrderTracking.tsx` (new)
- `resources/js/Components/Orders/OrderStatusTimeline.tsx` (new)

#### ⏳ Task 008: Add edit capabilities for Provider/OM until submission
**Status**: Pending | **Priority**: Medium
**Details**:
- Implement conditional edit permissions based on order status
- Add "Edit Order" functionality for pending orders
- Create order versioning for edit tracking
- Implement proper validation on re-submission

#### ⏳ Task 009: Create Admin order management interface
**Status**: Pending | **Priority**: Medium
**Details**:
- Build Admin dashboard for order management
- Implement manufacturer communication workflow
- Add bulk order operations
- Create order status management tools

**Files to create**:
- `resources/js/Pages/Admin/OrderManagement.tsx` (new)
- `resources/js/Components/Admin/ManufacturerCommunication.tsx` (new)
- `app/Http/Controllers/Admin/OrderManagementController.php` (new)

#### ⏳ Task 011: Create notification service for manufacturer communications
**Status**: Pending | **Priority**: Medium
**Details**:
- Implement DocuSeal integration for manufacturer notifications
- Create webhook handling for manufacturer responses
- Add automated follow-up system
- Implement approval document handling

**Files to create/modify**:
- `app/Services/ManufacturerCommunicationService.php` (new)
- Update `app/Services/DocusealService.php`
- `app/Http/Controllers/ManufacturerWebhookController.php` (new)

#### ⏳ Task 012: Add order status management and tracking
**Status**: Pending | **Priority**: Medium
**Details**:
- Create comprehensive order status state machine
- Implement automated status transitions
- Add status change logging and notifications
- Create status-based workflow triggers

### Phase 4: Quality & Compliance (Low Priority)

#### ⏳ Task 013: Create audit logging for order workflow
**Status**: Pending | **Priority**: Low
**Details**:
- Implement comprehensive audit trail
- Log all financial data access attempts
- Track order modifications and approvals
- Create compliance reporting

#### ⏳ Task 014: Build React components for order review and tracking UI
**Status**: Pending | **Priority**: Medium
**Details**:
- Create reusable UI components
- Implement responsive design
- Add accessibility features
- Create component documentation

#### ⏳ Task 015: Write comprehensive tests for new workflow
**Status**: Pending | **Priority**: Low
**Details**:
- Create unit tests for services
- Build integration tests for API endpoints
- Add E2E tests for complete workflow
- Create test data factories

## Key Implementation Requirements

### 1. **IVR Pre-Generation Workflow**
```php
// New workflow sequence:
// 1. Provider/OM completes order form
// 2. System generates IVR during order review (NOT after submission)
// 3. Provider/OM reviews IVR and order details
// 4. Provider/OM provides consent
// 5. Order is submitted with completed IVR
// 6. Dual notifications sent
// 7. Admin manages manufacturer communication
```

### 2. **Financial Data Visibility Matrix**
| User Role | ASP Total | Amount to be Billed | Product Info |
|-----------|-----------|-------------------|--------------|
| Provider | ✅ Visible | ✅ Visible | ✅ Visible |
| Office Manager | ❌ HIDDEN | ❌ HIDDEN | ✅ Visible |
| Admin | ✅ Visible | ✅ Visible | ✅ Visible |

### 3. **Notification Flow**
```
Order Submission Trigger
├── Provider/OM Notification (Confirmation + Deep Link)
└── Admin Notification (New Order Alert + Management Link)

Status Updates
├── Provider/OM (Status Change Notifications)
└── Admin (Action Required Notifications)
```

### 4. **Edit Permission Matrix**
| Order Status | Provider/OM Edit | Admin Edit | Notes |
|--------------|-----------------|------------|-------|
| Draft | ✅ Full Edit | ✅ Full Edit | Pre-submission |
| Pending | ❌ Read Only | ✅ Full Edit | Post-submission |
| Submitted | ❌ Read Only | ✅ Status Only | Manufacturer review |
| Approved | ❌ Read Only | ✅ Status Only | Final state |

### 5. **Deep Link Structure**
```
Provider/OM Links:
- /orders/{orderId}/track?token={secureToken}
- Read-only access to order details and status
- Financial data filtered based on role

Admin Links:
- /admin/orders/{orderId}
- Full management capabilities
- Manufacturer communication tools
```

## Critical Success Factors

### 1. **PHI Compliance**
- All patient data remains in Azure FHIR
- No PHI stored in operational database
- Proper audit logging for all PHI access

### 2. **Financial Data Security**
- Office Managers CANNOT see financial data at any point
- Audit all financial data access attempts
- Role-based middleware enforcement

### 3. **Workflow Integrity**
- IVR MUST be generated before submission
- Both Provider/OM AND Admin must receive notifications
- Order edit permissions strictly enforced by status

### 4. **User Experience**
- Simple, intuitive order review process
- Clear consent mechanism
- Real-time status updates
- Easy navigation between workflow steps

## Technical Architecture

### Backend Services
```
QuickRequestService (enhanced)
├── IVR pre-generation
├── Order validation
└── Status management

OrderNotificationService (new)
├── Dual notification system
├── Deep link generation
└── Notification tracking

FinancialDataFilterService (new)
├── Role-based data filtering
├── API response transformation
└── Audit logging

ManufacturerCommunicationService (new)
├── DocuSeal integration
├── Webhook handling
└── Approval workflow
```

### Frontend Components
```
OrderReview.tsx (new)
├── IVRPreview.tsx
├── ConsentCheckbox.tsx
├── FinancialSummary.tsx
└── EditOrderButton.tsx

OrderTracking.tsx (new)
├── OrderStatusTimeline.tsx
├── DocumentViewer.tsx
└── StatusUpdates.tsx

Admin/OrderManagement.tsx (new)
├── ManufacturerCommunication.tsx
├── BulkActions.tsx
└── StatusManagement.tsx
```

## Questions for Clarification

1. **IVR Template Selection**: Should the IVR template be selected automatically based on manufacturer/product, or should the Provider/OM have a choice?

2. **Edit Restrictions**: After submission, should Providers be able to add notes/comments, or is it completely read-only?

3. **Notification Frequency**: How often should status update notifications be sent to Provider/OM (immediate, daily digest, on major changes only)?

4. **Deep Link Security**: What level of security do you want for the deep links (time-based expiration, IP restrictions, etc.)?

5. **Financial Data Audit**: Do you need real-time alerts when someone attempts to access financial data inappropriately?

6. **Manufacturer Response**: How should the system handle manufacturer rejections or requests for additional information?

## Next Steps

Once you approve this plan, I will:

1. **Start with Phase 1**: Implement core backend workflow changes
2. **Update TodoWrite**: Mark tasks as completed and move to next phase
3. **Provide Progress Updates**: High-level explanation of changes after each task
4. **Test Each Component**: Ensure each change works before moving to next task
5. **Maintain Simplicity**: Make minimal, focused changes to avoid complexity

Would you like me to proceed with this plan, or do you have questions about any specific aspects?