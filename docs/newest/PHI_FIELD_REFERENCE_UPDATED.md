## 9. PHI_FIELD_REFERENCE_UPDATED.md

```markdown
# MSC-MVP Wound Care Platform: PHI Field Reference (Updated)

**Version:** 1.1  
**Date:** June 9, 2025

## 1. Overview

This document provides a comprehensive reference for all data fields that may contain Protected Health Information (PHI) within the MSC-MVP Wound Care Platform, updated to reflect the simplified architecture from the Technical Alignment document.

## 2. Key Architecture Updates

1. **Minimal PHI Access**: Only 10% of IVR data comes from PHI sources
2. **Hybrid Users Table**: Provider data remains in users table (non-PHI)
3. **Streamlined Workflows**: PHI accessed only when absolutely necessary

## 3. PHI Field Classification

### 3.1 Direct Identifiers in Azure FHIR (10% of IVR Data)

| Field Name | FHIR Resource | IVR Usage | Access Pattern |
|------------|---------------|-----------|----------------|
| `name` | Patient | ✅ Used in IVR | Fetched during IVR generation |
| `birthDate` | Patient | ✅ Used in IVR | Fetched during IVR generation |
| `gender` | Patient | ✅ Used in IVR | Fetched during IVR generation |
| `identifier` | Patient | ❌ Not in IVR | Reference only |
| `telecom` | Patient | ❌ Not in IVR | Rarely accessed |
| `address` | Patient | ❌ Not in IVR | Rarely accessed |

### 3.2 Non-PHI Provider Data in Supabase (25% of IVR Data)

| Field Name | Table | IVR Usage | Classification |
|------------|-------|-----------|----------------|
| `first_name` | users | ✅ Used in IVR | Non-PHI (Provider) |
| `last_name` | users | ✅ Used in IVR | Non-PHI (Provider) |
| `npi_number` | users | ✅ Used in IVR | Non-PHI (Provider) |
| `credentials` | users | ✅ Used in IVR | Non-PHI (Provider) |
| `email` | users | ✅ Used in IVR | Non-PHI (Provider) |

### 3.3 PHI Reference Fields (Not PHI Themselves)

| Field Name | Table | Purpose | Security |
|------------|-------|---------|----------|
| `patient_fhir_id` | product_requests | Links to Patient in FHIR | No reverse lookup |
| `azure_order_checklist_fhir_id` | product_requests | Links to DocumentReference | Audit trail only |
| `practitioner_fhir_id` | users | Links to Practitioner in FHIR | Bidirectional sync |

## 4. Simplified PHI Handling Patterns

### 4.1 IVR Generation (90% Auto-Population)
```typescript
// 90% from Supabase (Non-PHI)
const dbData = await getIVRDataFromSupabase(orderId);

// 10% from FHIR (PHI)
const patient = await fhirClient.read('Patient', dbData.patient_fhir_id);
const ivrData = {
  ...dbData,
  patientName: formatName(patient.name[0]),
  dateOfBirth: patient.birthDate,
  gender: patient.gender
};
4.2 Provider Workflows (Minimal PHI Entry)
typescript// Provider enters only (90 seconds total):
{
  // Basic patient info (stored in FHIR)
  patientName: "John Smith",
  dateOfBirth: "1965-01-15",
  memberId: "M123456789", // Stored as identifier
  
  // Everything else is non-PHI or references
  insuranceName: "Medicare", // Non-PHI (payer name)
  expectedServiceDate: "2025-06-25", // Non-PHI
  woundType: "DFU" // Non-PHI
}
5. PHI Access Audit Requirements
Required Audit Fields
typescriptinterface PHIAccessLog {
  userId: number;           // Who accessed
  patientFhirId: string;   // What patient
  accessType: 'read' | 'write' | 'update';
  resourceType: 'Patient' | 'Coverage' | 'Condition' | 'Observation';
  businessReason: 'ivr_generation' | 'order_creation' | 'clinical_update';
  timestamp: Date;
  ipAddress: string;
}
Audit Triggers

Any FHIR API call that accesses Patient resources
IVR generation that fetches patient demographics
Order creation that creates/updates FHIR resources

6. Simplified Security Measures
6.1 Technical Controls

Minimal Access: Only fetch PHI when required for specific operation
No Caching: PHI never cached outside Azure FHIR
Transient Processing: PHI processed in memory, never persisted
Reference Architecture: Use IDs, not PHI, in operational systems

6.2 Operational Controls

90% Reduction: Most data comes from non-PHI sources
Role-Based Access: PHI access limited to clinical operations
Audit Everything: Every PHI access logged and reviewable

7. PHI Minimization Achievements
Before (Traditional Approach)

Patient demographics in every system
PHI scattered across databases
Complex synchronization required
High risk surface area

After (MSC-MVP Approach)

✅ 90% of IVR data from non-PHI sources
✅ PHI only in Azure FHIR
✅ Simple reference architecture
✅ Minimal risk exposure

8. Quick Reference: What's PHI vs Non-PHI
PHI (In Azure FHIR Only)

Patient name, DOB, gender
Clinical diagnoses details
Treatment specifics
Insurance member IDs

Non-PHI (In Supabase)

Provider information (all fields)
Facility information (all fields)
Order metadata (dates, products, status)
Reference IDs to PHI
Business operations data

9. Developer Guidelines
DO:

✅ Use the single IVR query for 90% of data
✅ Fetch PHI only when displaying to authorized users
✅ Log all PHI access with business justification
✅ Use reference IDs in all operational flows

DON'T:

❌ Store PHI outside Azure FHIR
❌ Cache patient demographics
❌ Include PHI in logs or error messages
❌ Fetch PHI "just in case"

This simplified approach achieves the platform's efficiency goals while maintaining strict HIPAA compliance through architectural design rather than complex procedural controls.