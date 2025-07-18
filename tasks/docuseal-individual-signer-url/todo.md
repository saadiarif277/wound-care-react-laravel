# Docuseal Individual Signer URL Implementation

## Problem
Need to implement functionality to fetch Docuseal submission slugs and open individual signer URLs for viewing documents in the OrderDetails component.

## Requirements
1. Add API endpoint to fetch submission slugs from Docuseal API
2. Add functionality to open individual signer URLs in OrderDetails component
3. Integrate with existing Docuseal service
4. Handle error cases and loading states

## Plan

### 1. Backend Implementation
- [ ] Add method to DocusealService to fetch submission slugs
- [ ] Add API endpoint to get submission slugs for an order
- [ ] Add proper error handling and logging

### 2. Frontend Implementation  
- [ ] Add button to open Docuseal document in OrderDetails
- [ ] Add loading state for document opening
- [ ] Handle error cases gracefully
- [ ] Add proper TypeScript interfaces

### 3. Integration
- [ ] Test the complete flow
- [ ] Ensure proper permissions
- [ ] Add logging for debugging

## Implementation Steps

### Step 1: Backend - Add DocusealService method
- [x] Add `getSubmissionSlugs` method to DocusealService
- [x] Add API endpoint in DocusealController
- [x] Add proper validation and error handling

### Step 2: Frontend - Add OrderDetails functionality
- [x] Add button to open document
- [x] Add loading state
- [x] Add error handling
- [x] Test integration

### Step 3: Testing
- [ ] Test with valid submission ID
- [ ] Test with invalid submission ID
- [ ] Test error scenarios
- [ ] Verify permissions work correctly

## Progress
- [x] Backend implementation
- [x] Frontend implementation  
- [ ] Integration testing
- [ ] Documentation

## Review

### Implementation Summary

#### Backend Changes
1. **DocusealService.php**: Added `getSubmissionSlugs()` method that:
   - Fetches submission slugs from Docuseal API using `/submitters` endpoint
   - Returns individual signer URLs in format `https://docuseal.com/s/{slug}`
   - Includes proper error handling and logging
   - Returns structured data with signer information (name, email, status, etc.)

2. **DocusealController.php**: Added two new methods:
   - `getSubmissionSlugs()`: API endpoint for getting all submission slugs
   - `getDocusealDocumentUrl()`: Controller method that fetches submission ID from order and returns document URL
   - Both require `manage-orders` permission
   - Includes proper error handling and logging

3. **routes/api.php**: Added API route for submission slugs
4. **routes/web.php**: Added controller route for document URL generation

#### Frontend Changes
1. **IVRDocumentSection.tsx**: Enhanced with:
   - New "All Signers" button that appears when `docusealSubmissionId` is available
   - `handleViewAllSigners()` function to fetch and display all signer URLs via API
   - `handleViewIVR()` function that uses controller method to get document URL and opens in new tab
   - Modal to display all signers with their individual URLs
   - Loading states for both buttons
   - Error handling with multiple fallback methods

#### Key Features
- **Individual Signer URLs**: Uses Docuseal's `/s/{slug}` format for direct document access
- **Multiple Signers Support**: Shows all signers in a modal with their individual URLs
- **Fallback Compatibility**: Maintains backward compatibility with existing document viewing methods
- **Error Handling**: Comprehensive error handling with multiple fallback strategies
- **Loading States**: Proper loading indicators for better UX
- **Permission Control**: Respects existing permission system

#### API Response Format
```json
{
  "success": true,
  "submission_id": "123",
  "slugs": [
    {
      "id": 7,
      "slug": "dsEeWrhRD8yDXT",
      "email": "submitter@example.com",
      "name": "John Doe",
      "status": "completed",
      "url": "https://docuseal.com/s/dsEeWrhRD8yDXT",
      "completed_at": "2023-12-14T15:49:21.701Z",
      "opened_at": "2023-12-14T15:48:23.011Z"
    }
  ],
  "total_count": 1
}
```

### Testing Checklist
- [ ] Test with valid submission ID
- [ ] Test with invalid submission ID
- [ ] Test error scenarios
- [ ] Verify permissions work correctly
- [ ] Test fallback mechanisms
- [ ] Test loading states
- [ ] Test modal functionality

### Documentation
- [x] Code review
- [ ] Testing review
- [ ] Documentation review 
