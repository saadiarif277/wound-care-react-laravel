# MVP End-to-End Ordering: TASK LIST

This task list outlines the **required (not nice-to-have)** steps to implement a minimal viable product (MVP) for the end-to-end ordering flow, from product request by a provider to document signing via DocuSeal.

## Phase 1: Foundational Setup (Backend & Infrastructure)

- [ ] **Database Schema:**
  - [ ] Implement migrations for `orders` table and related entities for product requests (e.g., `order_items`, `patient_display_sequences` if using the "JoSm001" ID).
  - [ ] Execute DocuSeal schema migrations:
    - `docuseal_templates` table
    - `docuseal_submissions` table
    - `docuseal_folders` table
    - `ALTER TABLE orders` to add DocuSeal-related columns (`docuseal_generation_status`, `docuseal_folder_id`, `manufacturer_delivery_status`).
- [ ] **Database Seeding (Initial Data):**
  - [ ] Seed at least one `manufacturers` record.
  - [ ] Seed at least one `docuseal_folders` record linked to a manufacturer.
  - [ ] Seed default `docuseal_templates` (InsuranceVerification, OrderForm - with basic field mappings placeholder `{}`).
- [ ] **Environment Configuration (`.env`):**
  - [ ] Supabase connection details.
  - [ ] Azure FHIR service endpoint and authentication details (or mock/dev connection for MVP).
  - [ ] DocuSeal API Key (`DOCUSEAL_API_KEY`).
  - [ ] DocuSeal API URL (`DOCUSEAL_API_URL`).
  - [ ] DocuSeal Webhook Secret (`DOCUSEAL_WEBHOOK_SECRET`).
  - [ ] Application base URL (`APP_URL`) for redirect and webhook construction.

## Phase 2: Product Request Flow (Provider Portal - MVP Core)

- [ ] **Patient Information:**
  - [ ] Frontend: Patient Information Form (capture minimal required PHI for FHIR Patient resource, and non-PHI like facility, expected service date, payer name).
  - [ ] Backend API:
    - Receives patient data.
    - Creates/updates Patient resource in Azure FHIR.
    - Returns `patient_fhir_id` (and `patient_display_id` if implementing simplified version).
- [ ] **Clinical Assessment:**
  - [ ] Frontend: Clinical Assessment Form (capture minimal wound data for MVP, e.g., wound type, basic location).
  - [ ] Backend API:
    - Receives assessment data.
    - Creates relevant FHIR resources (e.g., DocumentReference or Observation) in Azure FHIR, linked to the Patient.
    - Returns FHIR reference ID(s) (e.g., `azure_order_checklist_fhir_id`).
- [ ] **Product Selection:**
  - [ ] Frontend: Simple product selection mechanism (e.g., dropdown or basic search from `msc_products` table).
  - [ ] Frontend: Allow adding selected products (with quantity) to the request.
- [ ] **Eligibility & Validation Stubs (MVP Simplification):**
  - [ ] Frontend: Display placeholders for eligibility status and MAC validation. For MVP, these can be non-functional or default to a "pending" / "assumed pass" state.
  - [ ] Backend: `orders` table to store these statuses with default values.
- [ ] **Review & Submit Order:**
  - [ ] Frontend: Consolidate review page for the request.
  - [ ] Backend API (`POST /api/v1/orders` or similar):
    - Receives all collected non-PHI data and FHIR reference IDs.
    - Creates an `orders` record in Supabase with initial status (e.g., 'Pending Approval' or 'Submitted').
    - Stores `patient_fhir_id`, `azure_order_checklist_fhir_id`, selected products, etc.

## Phase 3: Order Approval (Admin Portal - MVP Core)

- [ ] **Admin Order Review & Approval:**
  - [ ] Backend API (`POST /api/v1/admin/orders/{orderId}/approve`):
    - Updates `orders.status` to 'Approved'.
    - Sets `orders.docuseal_generation_status` to 'pending_generation' (or similar, to flag readiness for document creation).
  - [ ] Frontend (Admin Portal):
    - List orders with 'Pending Approval' (or 'Submitted') status.
    - Provide a button/action to call the `/approve` API for a selected order.

## Phase 4: DocuSeal Document Generation & Signing (MVP Core)

- [ ] **Backend: DocuSeal Client & Services:**
  - [ ] `DocuSealClient` (e.g., in `packages/integrations/docuseal/client.ts`):
    - `createSubmission(templateId, submitters, prefill_data, folderId, metadata, redirect_url)` method.
    - `getSigningLink(submissionId, submitterEmail, role)` method (if needed, or rely on DocuSeal's redirect/email flow).
  - [ ] `OrderDataAggregator` Service (e.g., in `packages/health-data/services/order-data-aggregator.ts`):
    - `aggregateOrderData(orderId)`: Fetches order from Supabase, related PHI (Patient, Coverage, Clinical) from Azure FHIR.
  - [ ] `DocumentGenerator` Service (e.g., in `packages/documents/services/document-generator.ts`):
    - `generateInsuranceVerification(orderId, aggregatedData)`:
      - Retrieves appropriate `docuseal_templates.docuseal_template_id` for 'InsuranceVerification'.
      - Retrieves `docuseal_folders.docuseal_folder_id` for the manufacturer.
      - Maps `aggregatedData` to DocuSeal prefill fields (hardcoded/simple mapping for MVP).
      - Defines submitters (e.g., admin as reviewer, provider as signer).
      - Calls `DocuSealClient.createSubmission`.
      - Stores submission details (ID, type, order_id, folder_id, status) in `docuseal_submissions` table.
    - `generateOrderForm(orderId, aggregatedData)`: Similar to above for 'OrderForm'.
- [ ] **Backend: API Endpoints for Document Management:**
  - [ ] Document Generation API (`POST /api/v1/admin/orders/{orderId}/generate-documents`):
    - Calls `OrderDataAggregator.aggregateOrderData(orderId)`.
    - Calls `DocumentGenerator` methods for 'InsuranceVerification' and 'OrderForm'.
    - Updates `orders.docuseal_generation_status` to 'generated' or 'documents_sent'.
  - [ ] Get Signing Links API (`GET /api/v1/orders/{orderId}/signing-links` or similar for provider):
    - Retrieves active signing URLs for an order's documents from `docuseal_submissions` (or generates them if DocuSeal client provides such). This might be simplified if DocuSeal emails links directly.
- [ ] **Backend: DocuSeal Webhook Handler:**
  - [ ] API Endpoint (`POST /api/webhooks/docuseal`):
    - Verify webhook signature using `DOCUSEAL_WEBHOOK_SECRET`.
    - Handle `submission.completed` (or `submission.signed` by all parties) event:
      - Update `docuseal_submissions.status`, `document_url`, `completed_at`.
    - Logic to check if all required submissions for an `order_id` are completed.
      - If all complete, update `orders.status` (e.g., to 'Pending Manufacturer Delivery' or 'Documents Signed').
- [ ] **Frontend: Admin Portal Document Actions:**
  - [ ] Button/action on an 'Approved' order to trigger the `/generate-documents` API.
  - [ ] Display document status (from `docuseal_submissions` or `orders.docuseal_generation_status`).
  - [ ] Display generated signing links (if admin needs to share them manually, or for reference).
- [ ] **Frontend: Provider Portal Document Signing:**
  - [ ] Notification/section for orders with documents pending provider signature.
  - [ ] Provide direct links to the DocuSeal signing pages for the provider (using `/signing-links` API or links received from DocuSeal if it emails them).

## Phase 5: MVP Testing & Minimal Deployment

- [ ] **Test Data Setup:**
  - [ ] Ensure a test patient exists in Azure FHIR with relevant demographic and (mocked) coverage data.
  - [ ] Ensure a test provider and facility exist in Supabase.
  - [ ] Ensure a test manufacturer and linked DocuSeal folder/templates exist.
- [ ] **End-to-End (E2E) Manual Test - Happy Path:**
    1. Provider: Submit a new product request for the test patient.
    2. Admin: Approve the submitted order.
    3. Admin: Trigger document generation for the approved order.
    4. Provider: Access signing links and complete document signing in DocuSeal.
    5. System: Verify DocuSeal webhook is received and correctly updates `docuseal_submissions` and `orders` status.
    6. Verify final order status reflects completion of document signing.
- [ ] **Deployment (UAT Environment):**
  - [ ] Deploy all code changes.
  - [ ] Run database migrations and seed scripts in the UAT environment.
  - [ ] Configure all environment variables correctly in UAT.
- [ ] **Monitoring (Basic):**
  - [ ] Implement basic server-side logging for critical API calls (order submission, approval, document generation, webhook processing).
  - [ ] Basic logging for DocuSeal API client calls and responses.

---
**Note:** This list prioritizes functionality for the core "happy path" to demonstrate the E2E flow for UAT. Error handling, advanced features, UI polish, and optimizations are deferred.
