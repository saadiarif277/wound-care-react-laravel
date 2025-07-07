# PDF IVR Viewer Implementation

## Overview
Update the Step7PDFIIVR.tsx component to properly display the manufacturer's PDF form using the new PDF generation API. The component currently submits data to the manufacturer but doesn't show the actual PDF form for review.

## TODO List

### 1. Add PDF Generation API Call
- [ ] Add state for PDF document data (document_id, url, status)
- [ ] Add loading state for PDF generation
- [ ] Add error state for PDF generation failures
- [ ] Call `/api/v1/pdf/generate-ivr` API when component loads
- [ ] Pass episode_id from formData
- [ ] Handle API response and store document data

### 2. Add PDF Viewer Component
- [ ] Add iframe or embed element to display PDF
- [ ] Style the PDF viewer container appropriately
- [ ] Handle PDF loading states
- [ ] Add fallback for browsers that don't support inline PDF viewing
- [ ] Ensure proper sizing and responsiveness

### 3. Update UI Flow
- [ ] Show PDF generation loading state
- [ ] Display generated PDF for review
- [ ] Keep existing manufacturer submission button below PDF
- [ ] Update submission flow to include PDF document_id
- [ ] Show success state after PDF is loaded

### 4. Error Handling
- [ ] Handle PDF generation API errors
- [ ] Show appropriate error messages to user
- [ ] Add retry functionality for failed PDF generation
- [ ] Handle expired PDF URLs (they expire after 1 hour)

### 5. Integration with Existing Flow
- [ ] Ensure PDF is generated before allowing manufacturer submission
- [ ] Include PDF document_id in manufacturer submission data
- [ ] Update manufacturer submission payload to reference the PDF
- [ ] Maintain existing auto-submission logic but after PDF loads

## Technical Details

### API Endpoint
- **URL**: `/api/v1/pdf/generate-ivr`
- **Method**: POST
- **Payload**: `{ episode_id: string, for_review: boolean }`
- **Response**: 
  ```json
  {
    "success": true,
    "data": {
      "document_id": "string",
      "status": "string",
      "url": "string (secure Azure blob URL)",
      "expires_in": 3600,
      "signature_status": "string",
      "requires_signatures": ["array of signature types"]
    }
  }
  ```

### Implementation Notes
- The PDF URL is a secure Azure blob URL that expires after 1 hour
- The PDF should be displayed in an iframe for security
- The component should handle the full lifecycle: generate → display → submit
- Maintain existing permission checks and error handling patterns