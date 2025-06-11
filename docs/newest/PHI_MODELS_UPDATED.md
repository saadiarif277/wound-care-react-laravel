## 8. PHI_DATA_MODELS_UPDATED.md

```markdown
# MSC-MVP Wound Care Platform: PHI (Azure Health Data Services) Data Models (Updated)

**Version:** 1.1  
**Date:** June 9, 2025

## Document Purpose

This document details the data models for all Protected Health Information (PHI) stored exclusively in the MSC-MVP Wound Care Platform's Azure Health Data Services FHIR server. Updates reflect the simplified architecture from the Technical Alignment document.

## Key Architecture Updates

1. **Practitioner Linking**: Practitioners link to the hybrid users table via practitioner_fhir_id
2. **Minimal PHI Access**: Only 10% of IVR data comes from FHIR (patient demographics)
3. **Simplified References**: Streamlined reference patterns between systems

## Core FHIR Resources (No Major Changes)

### Patient Resource
The Patient resource remains unchanged, storing core demographic information with custom extensions for platform consent and status.

**Key Fields for IVR (10% of total data):**
- `name` - Patient's full name
- `birthDate` - Date of birth  
- `gender` - Administrative gender
- `identifier` - Patient identifiers (MRN, etc.)

**Custom Extensions:**
- `woundcare-patient-consent` - Platform consent tracking
- `woundcare-platform-status` - Platform-specific flags

### Practitioner Resource (Updated Linking)
The Practitioner resource links to the hybrid users table for seamless integration.

**Key Update:**
```json
{
  "resourceType": "Practitioner",
  "id": "prac123456",
  "extension": [
    {
      "url": "https://msc-mvp.com/fhir/StructureDefinition/woundcare-msc-user-id",
      "valueString": "123" // References users.id in Supabase
    }
  ],
  "identifier": [
    {
      "system": "http://hl7.org/fhir/sid/us-npi",
      "value": "1234567890" // Matches users.npi_number
    }
  ]
}
This bidirectional linking enables:

Users table: practitioner_fhir_id → FHIR Practitioner
FHIR Practitioner: extension → users.id

Coverage Resource
No changes - continues to store insurance eligibility information with custom extensions for MAC jurisdiction and validation details.
Condition Resource
No changes - stores diagnoses and wound-specific conditions with custom wound type and staging extensions.
Observation Resource
No changes - stores clinical measurements and wound assessments.
DocumentReference Resource (Order Checklists)
Stores completed order checklists with structured JSON content.
Simplified Usage Pattern:
json{
  "resourceType": "DocumentReference",
  "type": {
    "coding": [{
      "system": "http://loinc.org",
      "code": "34117-2",
      "display": "Wound assessment form"
    }]
  },
  "content": [{
    "attachment": {
      "contentType": "application/json",
      "data": "[base64 encoded checklist data]",
      "title": "Order Checklist REQ-001"
    }
  }],
  "extension": [{
    "url": "https://msc-mvp.com/fhir/StructureDefinition/woundcare-order-checklist-type",
    "valueString": "SkinSubstitutePreApp"
  }]
}
Simplified PHI Access Patterns
1. Minimal PHI for IVR Generation
typescript// Only fetch what's needed (10% of IVR data)
const patient = await fhirClient.read('Patient', patientFhirId);
const ivrPatientData = {
  patientName: formatName(patient.name[0]),
  dateOfBirth: patient.birthDate,
  gender: patient.gender
};
// Everything else comes from Supabase
2. Order Creation Pattern
typescript// Step 1: Create/update patient if needed
const patientId = await ensurePatient({
  name: request.patientName,
  birthDate: request.dateOfBirth,
  identifier: request.memberId
});

// Step 2: Store reference in Supabase
await createProductRequest({
  patient_fhir_id: patientId,
  // All other data in Supabase
});
3. Clinical Documentation Storage
Order checklists continue to be stored as DocumentReference resources but with simplified retrieval patterns focused on compliance rather than operational use.
Security & Compliance (No Changes)
All existing security measures remain in place:

Azure AD B2C authentication
Role-based access control
Encryption at rest and in transit
Comprehensive audit logging
Minimum necessary access principles

Key Simplifications

Reduced PHI Access: Only 10% of IVR data from FHIR (vs previous designs requiring more)
Practitioner Sync: Simple bidirectional reference between users and Practitioner
Streamlined Workflows: PHI accessed only when absolutely necessary
Clear Boundaries: PHI for clinical/compliance, Supabase for operational

Implementation Notes
Practitioner Synchronization
typescript// When creating a provider user
async function createProviderUser(userData) {
  // 1. Create user in Supabase
  const user = await createUser(userData);
  
  // 2. Create Practitioner in FHIR
  const practitioner = await fhirClient.create('Practitioner', {
    name: [{ given: [userData.firstName], family: userData.lastName }],
    identifier: [{ system: 'npi', value: userData.npiNumber }],
    extension: [{
      url: 'https://msc-mvp.com/fhir/StructureDefinition/woundcare-msc-user-id',
      valueString: user.id.toString()
    }]
  });
  
  // 3. Update user with FHIR reference
  await updateUser(user.id, {
    practitioner_fhir_id: practitioner.id
  });
}
This simplified approach maintains HIPAA compliance while maximizing operational efficiency through the 90% auto-population strategy.