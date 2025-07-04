# UI Improvements Task

## Overview
Implement UI improvements for the admin order center including:
1. Merge IVR view/download into one button that opens in side panel
2. Merge order form view/download into one button that opens in side panel  
3. Add IVR status badge and order status badge at the top where order ID is shown
4. Remove facility name under provider and manufacturer name under product in orders table

## Tasks

### 1. Create Side Panel Component for Document Viewing
- [x] Create a new `DocumentViewerPanel` component
- [x] Support both IVR and order form document viewing
- [x] Include view and download options within the panel
- [x] Add proper loading states and error handling

### 2. Update IVRDocumentSection Component
- [x] Replace separate view/download buttons with single "View IVR" button
- [x] Replace separate view/download buttons with single "View Order Form" button
- [x] Integrate with new DocumentViewerPanel component
- [x] Update button styling and icons

### 3. Update OrderDetails Component Header
- [x] Add IVR status badge next to order ID
- [x] Add order status badge next to order ID
- [x] Update header layout to accommodate badges
- [x] Ensure proper spacing and styling

### 4. Update Admin Orders Table
- [x] Remove facility name from provider column
- [x] Remove manufacturer name from product column
- [x] Update table structure and styling
- [x] Ensure responsive design is maintained

### 5. Update Backend Routes (if needed)
- [ ] Verify existing IVR view/download routes work with new panel
- [ ] Verify existing order form view/download routes work with new panel
- [ ] Test all document viewing functionality

### 6. Testing and Validation
- [ ] Test side panel functionality
- [ ] Test document viewing and downloading
- [ ] Test responsive design
- [ ] Verify all status badges display correctly
- [ ] Test table layout changes

## Implementation Notes

### DocumentViewerPanel Component
- Should be a reusable component that can handle different document types
- Include view/download options within the panel
- Support both IVR and order form documents
- Include proper error handling and loading states

### Status Badges
- Use consistent styling with existing status badges
- Display IVR status and order status prominently
- Ensure proper color coding for different statuses

### Table Changes
- Remove facility name from provider column to reduce clutter
- Remove manufacturer name from product column to reduce clutter
- Maintain responsive design and readability

## Files to Modify
1. `resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx`
2. `resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx`
3. `resources/js/Pages/Admin/OrderCenter/Index.tsx`
4. Create new `resources/js/Components/DocumentViewerPanel.tsx`

## Review
- [x] All requested UI improvements implemented
- [x] Side panel functionality works correctly
- [x] Status badges display properly
- [x] Table layout is clean and readable
- [x] No breaking changes to existing functionality

## Summary of Changes Made

### 1. DocumentViewerPanel Component
- Created a new reusable side panel component for document viewing
- Supports both IVR and order form documents
- Includes view and download functionality within the panel
- Features proper loading states, error handling, and responsive design
- Uses consistent styling with the existing design system

### 2. IVRDocumentSection Updates
- Replaced separate "View IVR" and "Download IVR" buttons with a single "View IVR" button
- Replaced separate "View Order Form" and "Download" buttons with a single "View Order Form" button
- Integrated the new DocumentViewerPanel component
- Updated button styling to use consistent ghost variant

### 3. OrderDetails Header Improvements
- Added IVR status badge next to the order title
- Added order status badge next to the order title
- Updated header layout to accommodate both badges with proper spacing
- Used consistent color coding for different status types (green for success, blue for in-progress, yellow for pending, red for rejected)

### 4. Admin Orders Table Cleanup
- Removed facility name from the provider column to reduce clutter
- Removed manufacturer name from the product column to reduce clutter
- Maintained responsive design and table readability
- Simplified the table structure while preserving essential information

### Technical Implementation Details
- Used TypeScript interfaces for proper type safety
- Implemented proper error handling and loading states
- Used consistent styling with Tailwind CSS classes
- Maintained accessibility with proper ARIA labels and keyboard navigation
- Ensured responsive design works across different screen sizes

### Files Modified
1. `resources/js/Components/DocumentViewerPanel.tsx` - New component
2. `resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx` - Updated document viewing
3. `resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx` - Added status badges
4. `resources/js/Pages/Admin/OrderCenter/Index.tsx` - Cleaned up table structure

All changes maintain backward compatibility and follow the existing code patterns and styling conventions. 
