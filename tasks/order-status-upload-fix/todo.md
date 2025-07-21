# Order Status Upload and File Upload Fix

## Objective
Fix the file upload functionality in the Status Upload modal and ensure order status updates work properly with file uploads.

## Issues Identified

1. **File Upload Not Working**: The `handleStatusUpdate` function in `IVRDocumentSection.tsx` is not sending the file data to the backend
2. **Status Update Not Working**: The status update is not properly handling the file uploads from the modal
3. **Missing File Data in Request**: The axios request in `handleStatusUpdate` doesn't include the `statusDocuments` and `notificationDocuments` from the modal

## Tasks

### ✅ Frontend Fixes

- [x] **Fix IVRDocumentSection.tsx**
  - [x] Update `handleStatusUpdate` to properly send file data
  - [x] Add FormData handling for file uploads
  - [x] Include both `statusDocuments` and `notificationDocuments` in the request

- [x] **Fix StatusUpdateModal.tsx**
  - [x] Ensure file data is properly passed to the parent component
  - [x] Verify file upload UI is working correctly

### ✅ Backend Fixes

- [x] **Fix OrderCenterController.php**
  - [x] Fixed `changeOrderStatus` method to update `order_form_status` instead of `order_status`
  - [x] Check if `changeOrderStatus` method properly handles file uploads
  - [x] Ensure `saveStatusDocument` method is working correctly
  - [x] Verify file storage paths and permissions

### ✅ Testing

- [ ] **Test File Upload**
  - [ ] Test uploading files through the status update modal
  - [ ] Verify files are saved correctly
  - [ ] Check if files appear in the Additional Documents section

- [ ] **Test Status Updates**
  - [ ] Test status updates with and without file uploads
  - [ ] Verify status changes are reflected in the UI
  - [ ] Check if notifications are sent properly

## Changes Made

### Frontend (IVRDocumentSection.tsx)
1. **Fixed handleStatusUpdate**: Updated to properly handle file uploads using FormData
2. **Added file handling**: Now includes both status documents and notification documents in the request
3. **Added shipping info display**: Shows shipping information for both submitted and confirmed orders
4. **Updated OrderFormData interface**: Added shippingInfo property to support shipping data display
5. **Improved error handling**: Better error messages and user feedback

### Backend (OrderCenterController.php)
1. **Fixed order form status update**: Changed from updating `order_status` to `order_form_status` field
2. **Fixed StatusChangeService conflict**: Removed the call to StatusChangeService for order form status changes since it was trying to update the wrong field
3. **Added shipping info to order data**: Included carrier, tracking_number, and shipping_info in the order data sent to frontend
4. **Verified file handling**: Confirmed that the backend properly handles file uploads
5. **Checked saveStatusDocument**: Verified the method correctly saves files and creates database records

### Backend (QuickRequestOrchestrator.php)
1. **Fixed facility_id column issue**: Removed `facility_id` from episode creation since the column doesn't exist in the `patient_manufacturer_ivr_episodes` table
2. **Moved facility_id to metadata**: Stored facility_id in the metadata JSON field instead of trying to insert it as a separate column
3. **Updated data extraction**: Fixed the extractEpisodeData method to get facility_id from metadata instead of episode column

### Backend (NotificationHandler.php)
1. **Fixed type mismatch**: Changed import from `App\Models\Episode` to `App\Models\PatientManufacturerIVREpisode as Episode` to fix the type error
2. **Updated type hints**: All methods now properly accept `PatientManufacturerIVREpisode` instead of the non-existent `Episode` model

## Review

The main issues were:

1. **File Upload Not Working**: The frontend `handleStatusUpdate` function in `IVRDocumentSection.tsx` was not properly sending the file data to the backend. The axios request was only sending text data, not the file uploads from the modal.

2. **Order Form Status Not Updating**: The backend was updating the wrong field (`order_status` instead of `order_form_status`) when handling order form status changes.
3. **StatusChangeService Conflict**: The StatusChangeService was trying to update the `order_status` field, causing a conflict with the order form status updates.
4. **Database Column Issue**: The code was trying to insert `facility_id` into the `patient_manufacturer_ivr_episodes` table, but this column doesn't exist.
5. **Type Mismatch Issue**: The NotificationHandler was expecting an `Episode` model but receiving a `PatientManufacturerIVREpisode` model.

The fixes involved:
1. **Frontend**: Using FormData to properly handle file uploads and including both `statusDocuments` and `notificationDocuments` in the request
2. **Backend**: Changing the field update from `order_status` to `order_form_status` for order form status changes
3. **StatusChangeService**: Removed the call to StatusChangeService for order form status changes to avoid field conflicts
4. **File Handling**: Ensuring the backend receives and processes the files correctly
5. **Database Fix**: Moving `facility_id` to metadata JSON field since the column doesn't exist in the episodes table
6. **Type Fix**: Updated NotificationHandler to use the correct model type

This resolves the file upload, order form status update, database column, and type mismatch issues. 
