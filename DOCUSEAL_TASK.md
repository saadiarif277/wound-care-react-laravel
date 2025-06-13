# DocuSeal Integration & Template Management Task Plan

## Overview

This document tracks the implementation of a robust DocuSeal template management system, including pull/push sync, field mapping, Azure Document Intelligence (ADI) integration, and seamless order flow for the MSC Wound Portal.

---

## 1. Goals

- Admin UI: View, sync, create, edit, and push DocuSeal templates.
- Mapping: Robust, maintainable mapping between order/product request data and DocuSeal template fields, using existing mapping config and field type logic.
- ADI Integration: Use Azure Document Intelligence to extract field schemas, suggest mappings, and extract data from completed documents.
- Order Flow: Ensure seamless, auditable order → document generation → signature → manufacturer workflow.

---

## 2. Architectural Plan

### A. Template Management (Pull/Push/Sync)

- Backend: `DocuSealTemplateSyncService` for pull/push, ADI analysis, and mapping.
- API Endpoints: CRUD, sync, and ADI analysis for templates.
- Frontend: Admin UI for listing, syncing, creating, editing, and mapping templates.

### B. Field Mapping & Type Handling

- Use `ivr-field-mappings.php` and `DocuSealFieldFormatterService` for canonical mapping/formatting.
- ADI assists in extracting field schemas and suggesting mappings.
- Admin UI for mapping review/override.

### C. Azure Document Intelligence Integration

- ADI used for field schema extraction, mapping suggestions, and data extraction from completed documents.
- Service layer for ADI analysis and extraction.

### D. Order Flow Integration

- Use mapping for document generation and data extraction.
- Admin UI shows document status, extracted data, and audit trail.

---

## 3. Implementation Steps & Checklist

### Step 1: Backend

- [x] Implement `DocuSealTemplateSyncService` with pull, push, and ADI analysis methods.
- [x] Add/extend API endpoints for template CRUD, sync, and ADI analysis.
- [ ] Integrate with existing mapping config and field formatter (IVR focus first).
- [ ] Add audit logging for all template and document actions.
- [ ] **TODO:** Scaffold and implement `OrderFieldMappingService` for order form mapping (after IVR/ADI complete).
- [ ] **TODO:** Scaffold and implement `OnboardingFieldMappingService` for onboarding form mapping (after IVR/ADI complete).

### Step 2: Frontend

- [ ] Update `Templates.tsx` to list templates, show mapping status, and allow sync/add/edit.
- [ ] Add modals for uploading/analyzing templates, mapping fields, and editing mappings.
- [ ] Integrate with new API endpoints for sync, push, and ADI analysis.

### Step 3: Mapping & ADI

- [ ] On template upload or sync, run ADI, show mapping UI, and store mapping (IVR focus first).
- [ ] On document generation, use mapping and field formatter to fill fields.
- [ ] On document completion, use ADI to extract data and map back for audit.
- [ ] **TODO:** Implement mapping and ADI integration for order and onboarding forms after IVR/ADI is complete.

### Step 4: Order Flow

- [ ] Ensure order → document generation → signature → manufacturer workflow uses new mapping and audit trail.
- [ ] Show document status, extracted data, and audit log in order details.

---

## 4. Example API Contract

- **GET `/api/v1/docuseal/templates`**

  ```json
  [
    {
      "id": "uuid",
      "template_name": "IVR Form",
      "template_type": "IVR",
      "docuseal_template_id": "12345",
      "folder_id": "67890",
      "is_active": true,
      "is_default": false,
      "last_synced_at": "2024-06-01T12:00:00Z"
    }
  ]
  ```

- **POST `/api/v1/docuseal/templates/sync`** — Pull from DocuSeal, update DB, run ADI on new/changed templates
- **POST `/api/v1/docuseal/templates`** — Create new template (push to DocuSeal, update DB)
- **PUT `/api/v1/docuseal/templates/{id}`** — Update template (push to DocuSeal, update DB)
- **POST `/api/v1/docuseal/templates/{id}/analyze`** — Run ADI on a template (PDF upload or DocuSeal export)
- **GET `/api/v1/docuseal/templates/{id}/fields`** — Get mapped fields/schema

---

## 5. Key Benefits

- Scalable: Easily add new templates/vendors.
- Maintainable: Centralized mapping and field type logic.
- Auditable: All actions logged, all data extractable for compliance.
- User-friendly: Admin UI for mapping, sync, and template management.

---

## 6. Progress Tracking

- [x] Step 1: Backend
- [ ] Step 2: Frontend
- [ ] Step 3: Mapping & ADI
- [ ] Step 4: Order Flow

---

**Mark tasks as complete as you implement each feature.**
