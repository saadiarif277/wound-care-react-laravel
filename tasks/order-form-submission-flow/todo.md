# Order Form Submission Flow w/ IVR Scenarios Implementation

## Overview

Implement the Order Form Submission Flow with IVR scenarios as specified in the PRD. This involves modifying the order flow to handle both IVR required and IVR not required scenarios, with appropriate UI changes and backend logic.

## Objectives

- If IVR is required, Providers/OMs complete IVR form during order request, then Order form can be filled out afterwards on Order Details page
- If IVR is not required, Providers/OMs fill out Order form during the ordering process
- Implement confirmation modal with optional comment field
- Admin notifications via email

## Scenarios

### Scenario 1: IVR Required

- User completes IVR form during order request (auto-populated where possible)
- When user clicks Next (IVR is locked/submitted), goes to Order Summary page and Submits order to Admin
- Order form available immediately after order request creation
- Nice to have: If IVR not verified, order form is disabled with message "Order form can be completed now but cannot be submitted until IVR is verified"
- After IVR verified: User can submit order form via order details page

### Scenario 2: No IVR Required

- Users see IVR step with generic message "IVR is not required for this order"
- Still able to upload Clinical Notes and Supporting Docs (optional)
- When user clicks Next, they go to Order Form step to fill out Order Form
- UI should be similar to IVR Form (current UI)
- User completes order form and proceeds to Order Summary

## Confirmation Modal

- Title: "Confirm Order Submission"
- Message: "By submitting this order, I consent to having the Order submitted to Admin for review and approval. I understand that the order will not be placed with the manufacturer until IVR verification (if required) is completed and the order is fully approved."
- Options:
  - Optional Comment field (text, max 500 characters)
  - Checkbox: "I confirm the information is accurate and complete."
  - Buttons: Go Back | Confirm

## Acceptance Criteria

- [ ] When IVR is required, Order forms accessible post order request on Order Details page
- [ ] Nice to have: Order form disabled until IVR is verified
- [ ] When IVR is not required, user can complete Order Form and Submit
- [ ] Confirmation modal includes optional comment
- [ ] Admin notified via email

## Implementation Plan

### Phase 1: Backend Changes

- [x] Update ProductRequest model to handle IVR required/not required logic
- [x] Modify QuickRequestController to support both scenarios
- [x] Update order status flow to handle IVR verification states
- [x] Implement confirmation modal backend logic
- [x] Add email notification system for admin

### Phase 2: Frontend Changes

- [x] Update Step7DocusealIVR to handle IVR not required scenario
- [x] Modify order flow to show appropriate steps based on IVR requirement
- [x] Implement confirmation modal component
- [x] Update order details page to handle order form completion
- [x] Add proper validation and error handling

### Phase 3: Integration & Testing

- [ ] Test both IVR required and not required scenarios
- [ ] Verify confirmation modal functionality
- [ ] Test admin notifications
- [ ] Validate order form completion flow
- [ ] Test error handling and edge cases

## Files to Modify

### Backend Files

- `app/Models/Order/ProductRequest.php` - Add IVR requirement logic
- `app/Http/Controllers/QuickRequestController.php` - Update order flow
- `app/Http/Controllers/Api/OrderReviewController.php` - Handle confirmation
- `app/Services/QuickRequest/QuickRequestOrchestrator.php` - Update orchestration
- `app/Mail/OrderSubmissionNotification.php` - Admin notification email

### Frontend Files

- `resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx` - Handle IVR not required
- `resources/js/Pages/QuickRequest/Components/Step8OrderFormApproval.tsx` - Update order form flow
- `resources/js/Pages/QuickRequest/Orders/Index.tsx` - Update order review
- `resources/js/Components/ConfirmationModal.tsx` - New confirmation modal component
- `resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx` - Handle order form completion

## Current Status

- [x] Planning phase complete
- [x] Backend implementation started
- [x] Frontend implementation started
- [ ] Integration testing complete
- [ ] Documentation updated

## Notes

- Focus on minimal changes to existing functionality
- Ensure backward compatibility
- Follow existing code patterns and conventions
- Maintain HIPAA compliance throughout
- Test thoroughly with both scenarios

## Review

- [ ] Code review completed
- [ ] Testing completed
- [ ] Documentation updated
- [ ] Deployment ready
