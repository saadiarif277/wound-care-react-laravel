# IVR Field Discovery with Azure Document Intelligence

## Overview
The IVR Field Discovery feature uses Azure Document Intelligence to automatically extract and map fields from manufacturer IVR PDFs, eliminating the need for manual field mapping configuration.

## Features
- **PDF Field Extraction**: Upload manufacturer IVR forms to automatically discover all fields
- **Smart Mapping Suggestions**: AI-powered suggestions for mapping IVR fields to system fields
- **Bulk Operations**: Apply high-confidence mappings with one click
- **Visual Feedback**: Color-coded confidence scores and field categories
- **Product Checkbox Detection**: Automatically identifies Q-code product checkboxes
- **Multiple NPI Support**: Detects and maps multiple physician/facility NPI fields

## Setup

### 1. Azure Document Intelligence
1. Create an Azure Document Intelligence resource in Azure Portal
2. Copy the endpoint and API key
3. Add to your `.env` file:
   ```
   AZURE_DI_ENDPOINT=https://your-instance.cognitiveservices.azure.com/
   AZURE_DI_KEY=your-api-key
   ```

### 2. Run Migration
```bash
php artisan migrate
```

## Usage

### Admin Interface
1. Navigate to **Admin > DocuSeal > Templates**
2. Click on any template to view field mappings
3. Switch to the **Field Discovery** tab
4. Upload manufacturer IVR PDF
5. Review extracted fields and mapping suggestions
6. Apply auto-mappings or manually adjust
7. Save mappings

### Field Categories
- **Product**: Product checkboxes (Q-codes)
- **Provider**: Physician information (name, NPI, specialty)
- **Facility**: Facility/hospital details
- **Patient**: Patient demographics
- **Insurance**: Primary/secondary insurance
- **Clinical**: Wound information, ICD codes
- **Authorization**: Prior auth fields

### Confidence Levels
- **Green (>80%)**: High confidence, auto-mapping recommended
- **Yellow (60-80%)**: Medium confidence, review suggested
- **Red (<60%)**: Low confidence, manual mapping needed

## Technical Details

### Services
- `IvrFormExtractionService`: Handles Azure DI API calls
- `IvrFieldDiscoveryService`: Generates mapping suggestions
- `DocuSealTemplateController`: API endpoints

### API Endpoints
- `POST /api/v1/docuseal/templates/extract-fields`: Extract fields from PDF
- `POST /api/v1/docuseal/templates/{id}/update-mappings`: Save field mappings

### Mock Mode
When Azure DI is not configured, the system uses mock data for testing with common IVR fields.

## Benefits
1. **Time Savings**: 90% reduction in field mapping time
2. **Accuracy**: Eliminates manual transcription errors
3. **Adaptability**: Easy to update when manufacturers change forms
4. **Scalability**: Add new manufacturers without code changes