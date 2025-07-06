# Update IVR Status Modal - Order Details for Admin

## Objective
Move "Send notification to Provider/OM" checkbox above comments and add document upload functionality when checked.

## Tasks

### ✅ Frontend Changes

- [x] **Update StatusUpdateModal.tsx**
  - [x] Add notificationDocuments state
  - [x] Move "Send notification to Provider/OM" checkbox above comments field
  - [x] Add document upload functionality when checkbox is checked
  - [x] Update StatusUpdateData interface to include notificationDocuments
  - [x] Update handleConfirm to pass notification documents
  - [x] Update handleClose to reset notification documents

- [x] **Update IVRSection.tsx** (if needed)
  - [x] Updated interface to handle new parameters
  - [x] Updated handleStatusUpdate to pass notification data

### ✅ Backend Changes

- [x] **Update OrderCenterController.php**
  - [x] Modified changeOrderStatus method to handle notification documents
  - [x] Updated sendStatusChangeNotification call to include documents

### 🔄 Pending Tasks

- [ ] **Update EmailNotificationService.php**
  - [ ] Modify sendStatusChangeNotification method to handle notification documents
  - [ ] Add file upload and attachment functionality

- [ ] **Testing**
  - [ ] Test the updated modal functionality
  - [ ] Verify document upload works correctly
  - [ ] Test notification sending with documents

## Changes Made

### Frontend (StatusUpdateModal.tsx)
1. **Moved notification checkbox**: "Send notification to Provider/OM" checkbox is now positioned above the comments field
2. **Added document upload**: When notification checkbox is checked, a file upload input appears allowing multiple document selection
3. **Enhanced interface**: Added notificationDocuments to StatusUpdateData interface
4. **Updated handlers**: Modified handleConfirm and handleClose to manage notification documents

### Backend (OrderCenterController.php)
1. **File handling**: Added logic to handle notification documents from request
2. **Service integration**: Updated call to EmailNotificationService to include documents

## Next Steps
1. Update EmailNotificationService to handle document attachments
2. Test the complete workflow
3. Verify file upload and email functionality

## Review
- ✅ Frontend modal layout updated as requested
- ✅ Document upload functionality added
- ✅ Backend prepared for document handling
- 🔄 Email service needs updating for document attachments 
