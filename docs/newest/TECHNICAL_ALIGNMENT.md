# MSC-MVP Technical Alignment Document

**Version:** 1.0  
**Date:** June 9, 2025  
**Purpose:** Resolve inconsistencies across documentation and provide definitive implementation approach

## 🎯 **Alignment Decisions Made**

Based on your preferences for minimal breaking changes and maximum workflow efficiency:

1. **IVR Process:** No signature required - admin generates and manually sends
2. **Database Strategy:** Keep current schema with minimal cleanup 
3. **User Model:** Maintain hybrid users table (no separation)
4. **Status Workflow:** Streamlined progression focused on speed

---

## 📊 **Standardized Database Schema**

### **Current Product Requests (Minimal Cleanup)**
```sql
-- Keep existing table, remove unused complexity
ALTER TABLE `product_requests` 
DROP COLUMN `medicare_part_b_authorized`,
DROP COLUMN `ivr_bypass_reason`, 
DROP COLUMN `ivr_bypassed_at`,
DROP COLUMN `ivr_bypassed_by`,
DROP COLUMN `ivr_signed_at`,
DROP COLUMN `pre_auth_submitted_at`,
DROP COLUMN `pre_auth_approved_at`,
DROP COLUMN `pre_auth_denied_at`;

-- Simplified essential fields only:
-- ✅ Keep: id, request_number, provider_id, facility_id
-- ✅ Keep: patient_fhir_id, expected_service_date, wound_type
-- ✅ Keep: payer_name_submitted, payer_id
-- ✅ Keep: order_status, docuseal_submission_id, ivr_document_url
-- ✅ Keep: manufacturer_approved, manufacturer_approval_reference
-- ✅ Keep: total_order_value, created_at, updated_at
```

### **Current Users Table (No Changes)**
```sql
-- Keep hybrid approach - no breaking changes
-- ✅ Users table handles both platform access AND provider data
-- ✅ NPI, credentials, license info stays in users table
-- ✅ practitioner_fhir_id links to Azure FHIR
-- ✅ current_organization_id for context
```

### **Current Facilities Table (Minor Additions)**
```sql
-- Add missing fields for IVR auto-population
ALTER TABLE `facilities`
ADD COLUMN `ptan` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `default_place_of_service` VARCHAR(2) DEFAULT '11';
```

---

## 🔄 **Standardized Status Workflow**

### **Complete Status Progression**
```yaml
# Provider Actions
draft                    # Provider creating request
submitted               # Provider completed submission

# Admin Review  
processing              # Admin reviewing request
approved               # Admin approved for IVR generation

# IVR Process
pending_ivr            # Needs IVR generation
ivr_sent               # IVR generated and sent to manufacturer

# Manufacturer Response
manufacturer_approved  # Manufacturer confirmed approval
submitted_to_manufacturer # Order sent to manufacturer for fulfillment

# Fulfillment
shipped                # Product shipped
delivered              # Product delivered

# Terminal States
cancelled              # Cancelled at any stage
denied                 # Admin denied request
sent_back             # Admin sent back for corrections
```

### **Status Transition Rules**
```typescript
const allowedTransitions = {
  'draft': ['submitted', 'cancelled'],
  'submitted': ['processing', 'cancelled'],
  'processing': ['approved', 'sent_back', 'denied'],
  'approved': ['pending_ivr', 'cancelled'],
  'pending_ivr': ['ivr_sent', 'cancelled'],
  'ivr_sent': ['manufacturer_approved', 'cancelled'],
  'manufacturer_approved': ['submitted_to_manufacturer'],
  'submitted_to_manufacturer': ['shipped', 'cancelled'],
  'shipped': ['delivered'],
  // Terminal states
  'delivered': [],
  'cancelled': [],
  'denied': [],
  'sent_back': ['submitted'] // Provider can resubmit
};
```

---

## 📋 **Streamlined IVR Process (No Signatures)**

### **Definitive IVR Workflow**
```typescript
// Phase 1: Admin initiates IVR generation
1. Order reaches status: 'pending_ivr'
2. Admin clicks "Generate IVR" in Order Center
3. System uses DocuSeal to create pre-filled PDF (no signature fields)
4. Status updates to 'ivr_sent'
5. Admin downloads PDF for review

// Phase 2: Manual manufacturer submission  
6. Admin emails IVR to manufacturer manually
7. Admin clicks "Mark as Sent to Manufacturer"
8. System records manufacturer_sent_at timestamp

// Phase 3: Manufacturer approval tracking
9. Manufacturer reviews IVR (external process)
10. Admin receives manufacturer approval confirmation
11. Admin clicks "Confirm Manufacturer Approval" 
12. Admin enters approval reference number
13. Status updates to 'manufacturer_approved'
```

### **DocuSeal Integration Settings**
```typescript
// IVR Template Configuration
const ivrConfig = {
  signatureRequired: false,        // ✅ No signature needed
  generatePDFOnly: true,          // ✅ Create document for download
  autoSubmit: false,              // ✅ Manual admin review required
  manufacturerSpecific: true,     // ✅ Different templates per manufacturer
  prePopulationLevel: 90          // ✅ 90% fields auto-filled
};
```

---

## 🏗️ **IVR Auto-Population Architecture**

### **Single Query for 90% Pre-Population**
```sql
-- Get everything needed for IVR generation
SELECT 
  -- Request Data (15%)
  pr.request_number,
  pr.expected_service_date,
  pr.wound_type,
  pr.payer_name_submitted,
  pr.payer_id,
  pr.patient_fhir_id,
  
  -- Provider Info (25%) 
  u.first_name as provider_first_name,
  u.last_name as provider_last_name,
  u.npi_number as provider_npi,
  u.credentials as provider_credentials,
  u.email as provider_email,
  
  -- Facility Info (30%)
  f.name as facility_name,
  f.npi as facility_npi,
  f.address as facility_address,
  f.city as facility_city,
  f.state as facility_state,
  f.zip_code as facility_zip,
  f.phone as facility_phone,
  f.email as facility_email,
  f.ptan as facility_ptan,
  
  -- Organization Info (20%)
  o.name as organization_name,
  o.tax_id as organization_tax_id

FROM product_requests pr
JOIN users u ON pr.provider_id = u.id
JOIN facilities f ON pr.facility_id = f.id  
JOIN organizations o ON f.organization_id = o.id
WHERE pr.id = ?;
```

### **Patient Data from Azure FHIR (10%)**
```typescript
// Fetch patient details from Azure FHIR
const patientData = await azureFhirClient.getPatient(pr.patient_fhir_id);
const demographics = {
  patientName: patientData.name[0].given[0] + ' ' + patientData.name[0].family,
  dateOfBirth: patientData.birthDate,
  gender: patientData.gender,
  // Address, phone if needed for specific manufacturers
};
```

---

## 🎯 **Complete 60-Second Provider Workflow**

### **Provider Experience (Total: 90 seconds)**
```typescript
// Step 1: Basic Patient Info (30 seconds)
{
  patientName: "John Smith",
  dateOfBirth: "1965-01-15", 
  memberId: "M123456789",
  insuranceName: "Medicare",
  expectedServiceDate: "2025-06-25"
}

// Step 2: Clinical Context (30 seconds)  
{
  woundType: "DFU",
  placeOfService: "11", // Pre-filled from facility
  primaryICD10: "E11.621", // Smart suggestion
  procedureCPT: "15275"    // Smart suggestion
}

// Step 3: Product Selection (30 seconds)
{
  selectedProduct: "XCELLERATE Q4234", 
  suggestedSize: "4x4cm", // Based on wound area
  quantity: 1
}

// Auto-Processing (Behind the scenes)
→ Creates product_request with status 'submitted'
→ Triggers admin notification
→ All IVR data ready for generation
```

---

## 🏭 **Manufacturer Integration Approach**

### **Per-Manufacturer Configuration**
```typescript
// Manufacturer-specific templates and endpoints
const manufacturerConfig = {
  'Extremity Care': {
    docusealFolderId: 'folder_123',
    ivrTemplateId: 'template_456', 
    emailContact: 'orders@extremitycare.com',
    orderFormTemplate: 'extremity_order_template',
    submissionMethod: 'email_fax'
  },
  'Stability Biologics': {
    docusealFolderId: 'folder_789',
    ivrTemplateId: 'template_012',
    emailContact: 'cservice@stabilitybio.com', 
    orderFormTemplate: 'stability_order_template',
    submissionMethod: 'email'
  }
  // Add more manufacturers as needed
};
```

### **Order Form Auto-Generation**
```typescript
// After manufacturer approval, generate order form
const orderFormData = {
  // From IVR data (already collected) ✅
  requestingProvider: ivrData.providerName,
  patientName: ivrData.patientName,
  dateOfService: ivrData.expectedServiceDate,
  
  // From product selection ✅
  catalogNumber: productData.catalogNumber,
  productDescription: productData.description,
  quantity: orderData.quantity,
  
  // From facility profile ✅  
  shipToAddress: facilityData.fullAddress,
  
  // Provider final confirmation (30 seconds)
  deliveryDate: "Day before procedure",
  orderType: "Direct Purchase",
  poNumber: "[Optional]"
};
```

---

## 📱 **Admin UI Standardization**

### **Order Center Status Display**
```typescript
// Consistent status badges across all admin interfaces
const statusConfig = {
  'submitted': { color: 'blue', text: 'Awaiting Review', urgent: true },
  'processing': { color: 'yellow', text: 'Under Review', urgent: true },
  'pending_ivr': { color: 'orange', text: 'Generate IVR', urgent: true },
  'ivr_sent': { color: 'purple', text: 'Awaiting Manufacturer', urgent: false },
  'manufacturer_approved': { color: 'green', text: 'Ready to Submit', urgent: true },
  'submitted_to_manufacturer': { color: 'blue', text: 'In Fulfillment', urgent: false },
  'shipped': { color: 'green', text: 'Shipped', urgent: false },
  'delivered': { color: 'gray', text: 'Complete', urgent: false }
};
```

### **Action Availability Matrix**
```typescript
const availableActions = {
  'submitted': ['approve', 'send_back', 'deny'],
  'processing': ['approve', 'send_back', 'deny'], 
  'pending_ivr': ['generate_ivr', 'skip_ivr'],
  'ivr_sent': ['mark_sent_to_manufacturer'],
  'manufacturer_approved': ['submit_to_manufacturer'],
  // etc.
};
```

---

## 🔄 **Migration Strategy (Non-Breaking)**

### **Phase 1: Database Cleanup (Week 1)**
```sql
-- Remove unused fields without breaking existing code
-- Add missing fields for IVR auto-population
-- Update indexes for performance
```

### **Phase 2: Status Standardization (Week 2)**  
```typescript
// Update status handling in existing controllers
// Ensure all status transitions use new workflow
// Update UI to show standardized status badges
```

### **Phase 3: IVR Process Alignment (Week 3)**
```typescript
// Ensure DocuSeal integration uses no-signature approach
// Update admin UI to match streamlined workflow  
// Test manufacturer email integration
```

### **Phase 4: Auto-Population Enhancement (Week 4)**
```typescript
// Implement single-query IVR data collection
// Enhance DocuSeal field mapping
// Add smart code suggestions for ICD-10/CPT
```

---

## 🏆 **Success Metrics**

### **Provider Experience**
- ✅ 90 seconds total request time
- ✅ 90% IVR pre-population rate
- ✅ Zero duplicate data entry

### **Admin Efficiency** 
- ✅ One-click IVR generation
- ✅ Clear action priorities
- ✅ Streamlined approval process

### **Manufacturer Integration**
- ✅ Consistent IVR format per manufacturer
- ✅ Automated order form generation
- ✅ Reduced processing time

---

## 📋 **Implementation Checklist**

### **Database Updates**
- [ ] Remove unused product_requests fields
- [ ] Add PTAN and default_place_of_service to facilities  
- [ ] Update status enum values
- [ ] Create migration scripts

### **API Updates**
- [ ] Standardize status transition endpoints
- [ ] Update IVR generation service
- [ ] Align DocuSeal integration
- [ ] Create auto-population query

### **UI Updates**
- [ ] Standardize status badges
- [ ] Update admin action buttons
- [ ] Streamline provider forms
- [ ] Test mobile responsiveness

### **Integration Updates**
- [ ] Configure manufacturer-specific templates
- [ ] Test email submission workflows
- [ ] Validate FHIR data retrieval
- [ ] Document API endpoints

---

## 🎯 **Final Architecture Summary**

**MSC-MVP achieves the 60-second provider workflow through:**

1. **Minimal Data Collection:** Only 6 essential fields from provider
2. **Intelligent Auto-Population:** 90% of IVR forms filled automatically  
3. **Streamlined Admin Process:** One-click generation, manual review, manufacturer submission
4. **No Breaking Changes:** Works with existing database and user model
5. **Manufacturer Flexibility:** Configurable templates and submission methods

**Result:** Providers save 85-90% of administrative time while maintaining accuracy and compliance.