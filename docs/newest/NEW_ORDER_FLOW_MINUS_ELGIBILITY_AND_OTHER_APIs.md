# Complete MSC-MVP Workflow: From Request to Order Form (Updated)

## Overview
This document demonstrates the complete automated workflow showing how the platform achieves 90-second provider submission and 85-90% administrative time savings through intelligent auto-population and streamlined processes.

## Phase 1: Initial Request Creation (90 seconds total)

### Provider Action:
```typescript
// Step 1: Patient Info (30 seconds)
const patientData = {
  patientName: "John Smith",
  dateOfBirth: "1965-01-15",
  gender: "Male",
  memberId: "M123456789",
  insuranceName: "Medicare" // Dropdown selection
};

// Step 2: Clinical Context (30 seconds)
const clinicalData = {
  expectedServiceDate: "2025-06-25",
  placeOfService: "11", // Auto-filled from facility default
  woundType: "DFU", // Dropdown: Diabetic Foot Ulcer
  primaryDiagnosis: "E11.621", // Smart suggestion based on DFU
  procedureCode: "15275" // Smart suggestion for skin substitute
};

// Step 3: Product Selection (30 seconds)
const productData = {
  product: "XCELLERATE Q4234",
  suggestedSize: "4x4cm", // Auto-suggested based on wound
  quantity: 1
};

// Submit creates ProductRequest with status 'submitted'
Platform Auto-Processing (Instant):
typescript// Automatic actions on submission:
✅ Creates ProductRequest in Supabase:
   - order_status: "submitted"
   - patient_fhir_id: [creates/references in Azure]
   - provider_id: [from session]
   - facility_id: [from provider profile]
   - total_order_value: $10,062.08

✅ Creates/Updates Patient in Azure FHIR (if needed)
✅ Sends notification to Admin dashboard
Phase 2: Admin Review & Approval (2 minutes)
Admin Action:
typescript// Admin sees in Order Center:
Order REQ-001 | Dr. Smith | 🔵 Awaiting Review | 06/25

// Admin clicks to review - sees all pre-populated data
// Makes decision:
await updateOrderStatus({
  orderId: "REQ-001",
  status: "approved",
  notes: "All documentation complete"
});

// System automatically advances to 'pending_ivr'
Phase 3: One-Click IVR Generation (30 seconds)
Admin Action:
typescript// Status shows: 🟠 Generate IVR
// Admin clicks "Generate IVR" button

// No modal, no options - immediate generation
Platform Auto-Processing:
typescript// System executes 90% auto-population:
const ivrData = await db.query(`
  SELECT 
    -- 30% from Facility
    f.name as facilityName,
    f.address, f.city, f.state, f.zip_code,
    f.npi as facilityNPI,
    f.phone as facilityPhone,
    f.ptan as facilityPTAN,
    
    -- 25% from Provider  
    u.first_name + ' ' + u.last_name as physicianName,
    u.npi_number as physicianNPI,
    u.credentials,
    
    -- 20% from Organization
    o.name as organizationName,
    o.tax_id as taxId,
    
    -- 15% from Request
    pr.expected_service_date as procedureDate,
    pr.wound_type,
    pr.payer_name_submitted as insurance
    
  FROM product_requests pr
  JOIN users u ON pr.provider_id = u.id
  JOIN facilities f ON pr.facility_id = f.id
  JOIN organizations o ON f.organization_id = o.id
  WHERE pr.id = ?
`);

// Add 10% from FHIR
const patient = await azureFhir.getPatient(pr.patient_fhir_id);
ivrData.patientName = patient.name;
ivrData.patientDOB = patient.birthDate;

// Generate PDF via DocuSeal (no signatures)
const ivrPdf = await docuseal.generatePDF({
  templateId: manufacturer.ivrTemplateId,
  data: ivrData,
  signatureRequired: false
});

// Update status
await updateOrderStatus({
  orderId: "REQ-001",
  status: "ivr_sent",
  ivr_document_url: ivrPdf.url
});
Result:

✅ IVR 90% pre-populated
✅ PDF ready for download
✅ Status updated to ivr_sent

Phase 4: Manual Manufacturer Submission (1 minute)
Admin Action:
typescript// Admin downloads IVR PDF
// Reviews for accuracy
// Emails to manufacturer: orders@extremitycare.com
// Clicks "Mark as Sent to Manufacturer"

await markAsSent({
  orderId: "REQ-001",
  manufacturer_sent_at: new Date(),
  manufacturer_sent_by: adminUserId
});
Phase 5: Manufacturer Approval (External 24-48 hours)
External Process:

Manufacturer reviews IVR
Sends approval email to MSC

Admin Action:
typescript// Admin receives approval email
// Clicks "Confirm Manufacturer Approval"
// Enters reference: "EXT-2025-06-001"

await confirmManufacturerApproval({
  orderId: "REQ-001",
  approval_reference: "EXT-2025-06-001",
  status: "manufacturer_approved"
});
Phase 6: Order Form Generation & Submission (2 minutes)
Platform Auto-Generation:
typescript// System generates manufacturer-specific order form
const orderForm = await generateOrderForm({
  // All data already collected - no new entry needed
  template: "extremity_care_order_template",
  data: {
    ...ivrData, // Reuse all IVR data
    approvalReference: "EXT-2025-06-001",
    deliveryPreference: "Day before procedure",
    poNumber: "PO-2025-0615" // Optional
  }
});
Admin Final Action:
typescript// Admin reviews order form
// Clicks "Submit to Manufacturer"

await submitToManufacturer({
  orderId: "REQ-001",
  method: "email", // or fax
  status: "submitted_to_manufacturer"
});
Complete Workflow Time Summary:
PhaseActorTime RequiredStatus Changes1. Request CreationProvider90 secondsdraft → submitted2. Admin ReviewAdmin2 minutessubmitted → approved → pending_ivr3. IVR GenerationAdmin30 secondspending_ivr → ivr_sent4. Send to ManufacturerAdmin1 minute(timestamp update)5. Manufacturer ApprovalExternal24-48 hoursivr_sent → manufacturer_approved6. Order SubmissionAdmin2 minutesmanufacturer_approved → submitted_to_manufacturer
Total Active Time:

Provider: 90 seconds
Admin: 5.5 minutes
vs Manual Process: 45-60 minutes
Time Saved: 85-90%

Key Success Factors:
1. 90% Auto-Population
yamlFrom Database Query (80%):
- Facility: name, address, NPI, phone, PTAN
- Provider: name, NPI, credentials
- Organization: name, tax ID
- Order: service date, wound type, insurance

From FHIR (10%):
- Patient: name, DOB

Provider Entry (10%):
- Member ID
- Any specific overrides
2. Smart Suggestions
typescript// ICD-10 suggestions based on wound type
const diagnosisSuggestions = {
  'DFU': ['E11.621', 'E10.621'],
  'VLU': ['I87.2', 'I83.0'],
  'PU': ['L89.90', 'L89.91']
};

// CPT suggestions based on product
const procedureSuggestions = {
  'skin_substitute': ['15275', '15276', '15277']
};
3. Manufacturer-Specific Templates
typescriptconst manufacturerTemplates = {
  'Extremity Care': {
    ivr: 'extremity_ivr_v2',
    orderForm: 'extremity_order_v2',
    requiredFields: ['facilityPTAN', 'physicianNPI']
  },
  'Stability Biologics': {
    ivr: 'stability_ivr_v3',
    orderForm: 'stability_order_v3',
    requiredFields: ['taxId', 'poNumber']
  }
};
Competitive Advantages:

Provider Experience: 90 seconds vs 15-20 minutes
Admin Efficiency: 5.5 minutes vs 45-60 minutes
Error Reduction: 90% auto-population eliminates typos
Status Visibility: Real-time tracking for all parties
Audit Trail: Complete history of all actions

This streamlined workflow represents the optimal balance between automation and necessary human oversight, achieving maximum efficiency while maintaining compliance and accuracy.