# AI-Assisted Quick Request with OCR & Dynamic Markdown Forms

## Overview

Create an AI-powered interface that uses OCR to extract data from uploaded documents and dynamically generates Superinterface Markdown forms showing the extracted fields for review and completion.

## Tasks

### Phase 1: OCR & Dynamic Form Generation (Week 1)

- [ ] Create enhanced document upload interface in AIOverlay component
- [ ] Integrate existing Azure Document Intelligence OCR service
- [ ] Build Markdown form generator for OCR results
- [ ] Implement Superinterface client tools for document processing

### Phase 2: Dynamic Workflows (Week 2)

- [ ] Create document type detection system
- [ ] Build Markdown component handlers for form rendering
- [ ] Implement document attachment to episodes via FHIR
- [ ] Create backend DocumentProcessingService

### Phase 3: Full Integration (Week 3) ✅ COMPLETED

- [✅] Connect to QuickRequestOrchestrator for data flow
- [✅] Implement Docuseal pre-filling with extracted data
- [✅] Add document preview components
- [✅] Complete end-to-end testing

### Phase 4: Superinterface Integration (COMPLETED)

- [✅] Enhanced client tools with Superinterface function calling
- [✅] Complete DocumentProcessingController with type-specific processing
- [✅] Validation and IVR generation endpoints
- [✅] Enhanced QuickRequestService with AI form processing
- [✅] **REFACTORED: Migrated from AzureOpenAiService to AzureFoundryService**
  - Updated DocumentProcessingController to use AzureFoundryService for intelligent data extraction
  - Enhanced DocumentProcessingService with proper service dependencies
  - Improved AI-powered form translation with translateFormData method
  - Added comprehensive schema definitions for different document types
  - Removed prescription support as requested
  - Fixed all PHP syntax errors and service dependency issues

## Progress Log

### 2025-01-06

- Created project structure and todo list
- Starting implementation of document upload interface

## Review

### Completed Features

1. **Document Upload Interface**
   - Created `DocumentUploadZone` component with drag-and-drop support
   - Supports multiple file types (images, PDFs)
   - Auto-detects document type based on filename
   - Shows upload status and previews

2. **Markdown Form Renderer**
   - Built `MarkdownFormRenderer` component for dynamic forms
   - Supports input fields, dates, selects, textareas, buttons
   - Handles file and document previews
   - Two-way data binding with form values

3. **AI Overlay Enhancement**
   - Integrated document upload into AIOverlay
   - Added document processing with OCR
   - Displays extracted data in markdown forms
   - Toggle-able document upload section

4. **Backend OCR Integration**
   - Created `DocumentProcessingController` for document analysis
   - Integrated with existing Azure Document Intelligence services
   - Supports multiple document types (insurance cards, clinical notes, wound photos, prescriptions)
   - Smart data extraction based on document type

5. **Superinterface Client Tools**
   - Implemented window-level functions for AI assistant
   - `processDocument()` - OCR processing with markdown generation
   - `fillQuickRequestField()` - Direct form field manipulation
   - `attachDocumentToEpisode()` - Document attachment to episodes
   - `getCurrentQuickRequestData()` - Form data retrieval
   - `validateFormField()` - Field validation
   - `generateIVRForm()` - Docuseal form generation

### Architecture Decisions

- **Frontend**: Used React with TypeScript for type safety
- **Markdown Forms**: Dynamic form generation from OCR results allows flexibility
- **Client Tools**: Window-level functions enable AI assistant integration
- **Backend**: Leveraged existing services (Azure DI, Docuseal) for consistency

### Next Steps

The remaining tasks focus on deeper integration:

- Connecting document attachments to FHIR DocumentReferences
- Integrating with QuickRequestOrchestrator for seamless data flow
- Pre-filling Docuseal forms with extracted data
- Adding document preview capabilities
- End-to-end testing of the complete workflow

### Key Benefits

1. **Reduced Manual Entry**: OCR automatically extracts data from uploaded documents
2. **Visual Feedback**: Users see extracted data in clean markdown forms
3. **Flexibility**: Support for various document types with type-specific extraction
4. **AI Integration**: Client tools enable AI assistant to help with form filling
5. **Progressive Enhancement**: Works alongside existing Quick Request flow
