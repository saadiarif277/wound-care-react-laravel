# Status Document Upload Enhancement

## Objective
Enhance the status update modal to include document upload functionality with document types (IVR doc or Order related doc) and show these uploaded documents in the Additional Documents section on the order details page.

## Tasks

### ✅ Backend Changes

- [x] **Create Migration**
  - [x] Created `create_order_status_documents_table` migration
  - [x] Added fields: product_request_id, document_type, file_name, file_path, file_url, file_size, mime_type, uploaded_by, notes, status_type, status_value
  - [x] Added indexes for performance

- [x] **Create Model**
  - [x] Created `OrderStatusDocument` model
  - [x] Added relationships to ProductRequest and User
  - [x] Added accessor methods for document type label, display URL, and human file size
  - [x] Added scopes for filtering by document type and status type

- [x] **Update ProductRequest Model**
  - [x] Added relationship to OrderStatusDocument

- [x] **Update OrderCenterController**
  - [x] Updated `changeOrderStatus` method to handle status documents
  - [x] Added `saveStatusDocument` method for file storage and database record creation
  - [x] Updated `getOrderDocuments` method to include status documents
  - [x] Added `getFileTypeFromMime` helper method

### ✅ Frontend Changes

- [x] **Update StatusUpdateModal**
  - [x] Added `statusDocuments` state and interface field
  - [x] Added file upload section for status documents
  - [x] Updated form submission to include status documents
  - [x] Added explanatory text about document types

- [x] **Update AdditionalDocumentsSection**
  - [x] Added new document types: 'ivr_doc' and 'order_related_doc'
  - [x] Updated document type labels and colors
  - [x] Added display of status information and notes for status documents
  - [x] Enhanced document display with status metadata

### 🔄 Pending Tasks

- [ ] **Testing**
  - [ ] Test document upload from IVR status modal
  - [ ] Test document upload from Order status modal
  - [ ] Verify documents appear in Additional Documents section
  - [ ] Test document download and viewing functionality
  - [ ] Verify proper document type labeling

- [ ] **Route Updates**
  - [ ] Ensure routes are properly configured for file uploads
  - [ ] Test file upload endpoint

- [ ] **Error Handling**
  - [ ] Add proper error handling for file upload failures
  - [ ] Add validation for file types and sizes

## Changes Made

### Database Schema
- Created `order_status_documents` table with comprehensive fields for document storage
- Added proper foreign key relationships and indexes
- Included metadata fields for status tracking and notes

### Backend Implementation
- **OrderStatusDocument Model**: Full model with relationships, accessors, and scopes
- **Controller Updates**: Enhanced status update process to handle document uploads
- **File Storage**: Integrated with Laravel's storage system for secure file handling

### Frontend Implementation
- **StatusUpdateModal**: Added dedicated document upload section with clear labeling
- **AdditionalDocumentsSection**: Enhanced to display status documents with proper categorization
- **User Experience**: Clear visual distinction between different document types

## Technical Details

### Document Types
- `ivr_doc`: Documents uploaded during IVR status updates
- `order_related_doc`: Documents uploaded during order status updates

### File Storage
- Files stored in `storage/app/public/order-status-documents/`
- Unique filenames generated to prevent conflicts
- Original filenames preserved in database

### Status Tracking
- Each document linked to the specific status update that triggered its upload
- Status type and value stored for audit trail
- Notes field available for additional context

## Review

### Summary of Changes
1. **Database**: New table for storing status update documents with comprehensive metadata
2. **Backend**: Enhanced status update process with document handling capabilities
3. **Frontend**: Updated modals and document display with new document types and metadata

### Key Features
- Document upload during status updates with automatic type assignment
- Clear visual distinction between IVR and Order documents
- Comprehensive metadata display including status information and notes
- Proper file storage and retrieval system

### Next Steps
- Complete testing of all functionality
- Add error handling and validation
- Consider adding document deletion capabilities
- Add bulk document operations if needed 
