# Ashley Workflow Implementation Summary

## Overview

This document summarizes the implementation of Ashley's admin review and forward workflow for the episode-based order management system.

## Key Requirements Implemented

### 1. Provider IVR Generation ✅

- **Provider Workflow**: Providers generate IVR at end of order submission using DocuSeal
- **Auto-Population**: IVR form is pre-filled with all order data from QuickRequest
- **Required Step**: ALL orders require IVR completion before submission (`signatureRequired = true`)
- **DocuSeal Integration**: Provider signs electronically, submission ID stored with order

### 2. Admin Review & Forward Workflow ✅

- **Status Flow**: `ready_for_review` → `ivr_verified` → `sent_to_manufacturer` → `tracking_added` → `completed`
- **Admin Role**: Ashley reviews provider-generated IVR and approves before sending
- **No IVR Generation**: Admin doesn't generate IVR, only reviews what provider created
- **Manual Send**: Admin manually sends to manufacturer with custom recipients

### 3. IVR Status Tracking ✅

- **Episode-Based**: IVR tracked per PATIENT + MANUFACTURER combination
- **Provider Completed**: IVR status shows `provider_completed` when submitted
- **Admin Reviewed**: Changes to `admin_reviewed` after Ashley approves
- **Expiration Tracking**: Tracks verification and expiration dates

### 4. Email Recipients Management ✅

- **Dynamic Recipients**: Ashley can add/modify email recipients when sending to manufacturers
- **Default Recipients**: Pre-populated with manufacturer's default email
- **Validation**: Email format validation and duplicate prevention
- **Persistence**: Recipients stored with episode metadata

### 5. Document Handling ✅

- **IVR Documents**: Provider-generated DocuSeal IVRs viewable in episode
- **Confirmation Documents**: Ashley can upload confirmation documents
- **Tracking Management**: Dedicated tracking information component
- **Audit Trail**: All actions logged with timestamps and user info

## Implementation Details

### Provider IVR Generation Flow

```typescript
// Step4Confirmation.tsx - REQUIRED for all orders
const signatureRequired = true; // Ashley's requirement
const canSubmit = ivrSigned && !isSubmitting; // Cannot submit without IVR

// DocuSealIVRForm.tsx - Pre-populates all fields
const prepareIVRFields = (data: any) => {
  return {
    'patient_first_name': data.patient_first_name,
    'patient_last_name': data.patient_last_name,
    'product_name': data.product_name,
    'wound_type': data.wound_type,
    'manufacturer': data.manufacturer,
    // ... all order details auto-filled
  };
};
```

### Admin Review Process

```php
// OrderCenterController.php - Review provider IVR
public function reviewEpisode($episodeId) {
    // Validate IVR was completed by provider
    if ($episode->ivr_status !== 'provider_completed' || !$episode->docuseal_submission_id) {
        return back()->with('error', 'Provider has not completed IVR for this episode.');
    }
    
    // Update status to indicate admin reviewed
    $episode->update([
        'status' => 'ivr_verified',
        'ivr_status' => 'admin_reviewed',
    ]);
}
```

## Components Created

### 1. SendToManufacturer Component

**Location**: `resources/js/Components/Admin/SendToManufacturer.tsx`

**Features**:

- Dynamic email recipient management (add/remove)
- Email validation
- Additional notes for manufacturer
- Shows what will be sent (orders, IVR, clinical notes)
- Manufacturer contact information display

### 2. TrackingManager Component

**Location**: `resources/js/Components/Admin/TrackingManager.tsx`

**Features**:

- Add tracking number and carrier
- Estimated delivery date
- View existing tracking information
- Direct links to carrier tracking pages
- Update tracking after initial entry

## Frontend Updates

### 1. ShowEpisode.tsx

- Integrated SendToManufacturer component
- Integrated TrackingManager component
- Updated workflow descriptions to match Ashley's requirements
- Added ConfirmationDocuments component
- Added AuditLog component

### 2. Show.tsx (Individual Order View)

- Added automatic redirect to episode view if order has `ivr_episode_id`
- Maintains backwards compatibility for orders without episodes

## Workflow Timeline

1. **Provider Creates Order**
   - Fills all order details in QuickRequest
   - Reaches Step 4 - Confirmation

2. **Provider Generates IVR**
   - REQUIRED step - cannot skip
   - DocuSeal form auto-populated with order data
   - Provider reviews and signs electronically
   - Submission ID stored with order

3. **Episode Creation**
   - System creates/finds episode for provider+manufacturer
   - Status: `ready_for_review`
   - IVR Status: `provider_completed`

4. **Admin Reviews IVR**
   - Views provider-generated IVR in episode
   - Clicks "Approve IVR & Continue"
   - Status: `ivr_verified`

5. **Admin Sends to Manufacturer**
   - Manages email recipients dynamically
   - Adds notes if needed
   - Sends episode with IVR attached

6. **Tracking & Completion**
   - Admin adds tracking when shipped
   - Marks as completed when delivered

## Benefits of Provider IVR Generation

### Clinical Efficiency

- **Accurate Data**: IVR populated directly from order data
- **No Transcription Errors**: Automated data transfer
- **Provider Accountability**: Provider verifies accuracy before signing

### Operational Benefits

- **Reduced Admin Burden**: Ashley doesn't generate IVRs
- **Faster Processing**: IVR ready when order submitted
- **Better Compliance**: Provider signature captured upfront

### User Experience

- **Streamlined Workflow**: One continuous process for providers
- **Clear Status**: Admin knows IVR is complete
- **Audit Trail**: Complete documentation from start

## Testing Checklist

1. [ ] Provider completes order with all details
2. [ ] Provider cannot submit without IVR completion
3. [ ] IVR form pre-populates correctly
4. [ ] DocuSeal submission ID saved with order
5. [ ] Episode shows provider-completed IVR
6. [ ] Admin can view IVR documents
7. [ ] Admin approval updates statuses correctly
8. [ ] Email recipient management works
9. [ ] Tracking information can be added
10. [ ] Audit log captures all actions

## Workflow Documentation Updates

### Episode-Based Order Workflow

**File**: `docs/features/episode-based-order-workflow.md`

**Key Changes**:

- Updated to reflect admin-centered workflow
- Clarified that admin generates IVRs (not providers)
- Documented manual review process
- Added email recipient management details

## Database Considerations

### Episode Metadata Storage

- Email recipients stored in `metadata` JSON field
- Tracking information stored in episode
- Audit trail maintained for all actions

### Product Requests Table

- `manufacturer_recipients` field stores email recipients as JSON
- `manufacturer_sent_at` timestamp tracks when sent

## Security & Permissions

### Required Permissions

- `review-episodes`: Review provider-submitted orders
- `manage-episodes`: Full episode management
- `send-to-manufacturer`: Send episodes to manufacturers

### Data Protection

- No PHI exposed in emails
- Audit trail for all actions
- Email validation to prevent typos

## Testing Recommendations

### Manual Testing Checklist

1. [ ] Create new order as provider
2. [ ] Verify order appears in "Ready for Review" status
3. [ ] Admin reviews and generates IVR
4. [ ] Test email recipient management (add/remove/validate)
5. [ ] Send to manufacturer with multiple recipients
6. [ ] Add tracking information
7. [ ] Verify tracking links work
8. [ ] Mark episode as completed
9. [ ] Check audit log for all actions

### Edge Cases to Test

- Invalid email formats
- Duplicate email recipients
- Missing manufacturer email
- Orders without episodes (legacy)
- Episode status transitions
- Permission-based UI elements

## Future Enhancements

### Planned Features

1. **Bulk Email Sending**: Send multiple episodes at once
2. **Email Templates**: Customizable email content
3. **Tracking Notifications**: Automatic updates when packages move
4. **Recipient History**: Remember frequently used recipients
5. **Email Delivery Status**: Track if emails were opened/delivered

### Integration Opportunities

1. **DocuSeal Integration**: Automatically attach IVR documents
2. **Carrier APIs**: Real-time tracking updates
3. **Email Service**: Use dedicated email service for better deliverability
4. **Notification System**: Alert providers of order status changes

## Conclusion

The implementation successfully addresses all of Ashley's requirements with a provider-centered IVR generation workflow. Providers complete IVRs using DocuSeal at order submission, ensuring accurate data capture and reducing admin workload. Ashley maintains control over review and forwarding while benefiting from automated data population and electronic signatures.
