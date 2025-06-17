# Azure Document Intelligence Integration

## Overview

The Quick Request feature integrates with Azure Document Intelligence to automatically extract patient and insurance information from insurance card images. This feature uses the prebuilt health insurance card model to process both front and back images of insurance cards.

## Features

- **Automatic Data Extraction**: Extracts patient name, member ID, date of birth, insurer information, and more
- **Dual Card Processing**: Processes both front and back of insurance cards for comprehensive data extraction
- **Smart Form Filling**: Automatically populates form fields with extracted data
- **Fallback Support**: Graceful degradation if Azure service is unavailable

## Configuration

Add the following environment variables to your `.env` file:

```env
# Azure Document Intelligence
AZURE_DI_ENDPOINT=https://your-instance.cognitiveservices.azure.com/
AZURE_DI_KEY=your-azure-di-key
AZURE_DI_API_VERSION=2024-02-29-preview
```

## Data Extracted

### From Front of Card:
- **Insurer Information**: Insurance company name
- **Member Details**:
  - Full name (parsed into first/last)
  - Member ID
  - Date of birth
- **Group Information**:
  - Group number
  - Group name
- **Plan Details**:
  - Plan number
  - Plan name
  - Plan type
- **Prescription Benefits**:
  - BIN number
  - PCN
  - Group (GRP)
- **Copay Information**: Various copay amounts and types
- **Payer ID**: For claims processing

### From Back of Card:
- **Claims Address**: Full mailing address for claims
- **Service Numbers**: Customer service phone numbers by type

## API Endpoints

### Analyze Insurance Card
`POST /api/insurance-card/analyze`

**Request:**
```
Content-Type: multipart/form-data

insurance_card_front: (required) Image file (jpg, jpeg, png, pdf)
insurance_card_back: (optional) Image file (jpg, jpeg, png, pdf)
```

**Response:**
```json
{
  "success": true,
  "data": {
    "patient_first_name": "John",
    "patient_last_name": "Doe",
    "patient_dob": "1980-01-01",
    "patient_member_id": "123456789",
    "payer_name": "Blue Cross Blue Shield",
    "payer_id": "BCBS",
    "insurance_type": "commercial"
  },
  "extracted_data": {
    // Full extraction results
  }
}
```

### Check Service Status
`GET /api/insurance-card/status`

**Response:**
```json
{
  "configured": true,
  "service": "Azure Document Intelligence",
  "api_version": "2024-02-29-preview"
}
```

## Usage in Quick Request

1. User uploads front and back of insurance card
2. System automatically calls Azure Document Intelligence API
3. Extracted data is mapped to form fields
4. User can review and edit auto-filled information
5. Form submission includes extracted data for reference

## Security Considerations

- Insurance card images are processed in memory and not permanently stored
- Azure Document Intelligence API calls use secure HTTPS
- API keys are stored as environment variables
- All PHI data handling follows HIPAA compliance guidelines

## Error Handling

The system includes robust error handling:
- Network timeouts (30 second maximum per analysis)
- Invalid image formats
- API service unavailability
- Partial data extraction (gracefully handles missing fields)

## Supported Insurance Card Types

Azure Document Intelligence supports most US health insurance cards including:
- Commercial insurance cards
- Medicare cards
- Medicaid cards
- Medicare Advantage cards
- Prescription benefit cards

## Development Notes

### Testing without Azure
If Azure Document Intelligence is not configured, the system will:
1. Accept the insurance card uploads
2. Show the processing animation
3. Skip the auto-fill functionality
4. Allow manual entry of all fields

### Local Development
For local development without Azure credentials:
1. Set `AZURE_DI_ENDPOINT` and `AZURE_DI_KEY` to empty values
2. The status endpoint will show `configured: false`
3. Upload functionality will work but without extraction

## Future Enhancements

- Support for additional document types (ID cards, etc.)
- Batch processing for multiple cards
- Historical extraction data storage
- Enhanced validation of extracted data
- Integration with eligibility verification using extracted data