# ACZ IVR Field Mapping - Missing Fields Analysis

## Current Status
- **Total Template Fields**: 52
- **Mapped Fields**: 24
- **Success Rate**: 46.2%
- **Missing Fields**: 28

## ✅ Successfully Mapped Fields (24/52)

### Product Selection (1/1) - 100%
- ✅ Product Q Code: Q4316 (Amchoplast)

### Representative Information (1/3) - 33%
- ✅ Sales Rep: Dr. Jane Smith
- ❌ ISO if applicable: Missing `iso_number`
- ❌ Additional Emails for Notification: Missing `additional_notification_emails`

### Physician Information (2/9) - 22%
- ✅ Physician Name: Dr. Jane Smith
- ✅ Physician NPI: 12345
- ❌ Physician Specialty: Missing `provider_specialty`
- ❌ Physician Tax ID: Missing `provider_tax_id`
- ❌ Physician PTAN: Missing `provider_ptan`
- ❌ Physician Medicaid #: Missing `provider_medicaid`
- ❌ Physician Phone #: Missing `provider_phone`
- ❌ Physician Fax #: Missing `provider_fax`
- ❌ Physician Organization: Missing `provider_organization`

### Facility Information (3/12) - 25%
- ✅ Facility Name: Test Healthcare Network
- ✅ Facility Address: 456 Medical Center Blvd
- ✅ Facility City, State, Zip: New York, NY, 10002
- ❌ Facility NPI: Missing `facility_npi`
- ❌ Facility Tax ID: Missing `facility_tax_id`
- ❌ Facility PTAN: Missing `facility_ptan`
- ❌ Facility Medicaid #: Missing `facility_medicaid`
- ❌ Facility Phone #: Missing `facility_phone`
- ❌ Facility Contact Name: Missing `facility_contact_name`
- ❌ Facility Fax #: Missing `facility_fax`
- ❌ Facility Contact Phone # / Facility Contact Email: Missing `facility_contact_phone`, `facility_contact_email`
- ❌ Facility Organization: Missing `facility_organization`

### Place of Service (1/2) - 50%
- ✅ Place of Service: POS 11
- ❌ POS Other Specify: Conditional field (only needed if "Other" selected)

### Patient Information (6/7) - 86%
- ✅ Patient Name: John Doe
- ✅ Patient DOB: 03/15/1965
- ✅ Patient Address: 123 Main Street, Apt 4B
- ✅ Patient City, State, Zip: New York, NY, 10001
- ✅ Patient Phone #: (555) 123-4567
- ✅ Patient Email: john.doe@email.com
- ❌ Patient Caregiver Info: Missing `patient_caregiver_info`

### Insurance Information (2/6) - 33%
- ✅ Primary Insurance Name: Cigna
- ✅ Primary Policy Number: MED123456789
- ❌ Secondary Insurance Name: Missing `secondary_insurance_name`
- ❌ Secondary Policy Number: Missing `secondary_member_id`
- ❌ Primary Payer Phone #: Empty in form data
- ❌ Secondary Payer Phone #: Missing `secondary_payer_phone`

### Network Status (1/2) - 50%
- ✅ Physician Status With Primary: In-Network
- ❌ Physician Status With Secondary: Missing `secondary_physician_network_status` (REQUIRED)

### Authorization Questions (4/4) - 100%
- ✅ Permission To Initiate And Follow Up On Prior Auth?: Yes
- ✅ Is The Patient Currently in Hospice?: No
- ✅ Is The Patient In A Facility Under Part A Stay?: No
- ✅ Is The Patient Under Post-Op Global Surgery Period?: No

### Surgery Fields (0/2) - 0%
- ❌ If Yes, List Surgery CPTs: Conditional (only if global surgery = Yes)
- ❌ Surgery Date: Conditional (only if global surgery = Yes)

### Clinical Information (3/4) - 75%
- ✅ Location of Wound: Feet/Hands/Head < 100 SQ CM
- ✅ ICD-10 Codes: E11.621, L97.519
- ✅ Total Wound Size: 4cm x 4cm = 16 sq cm
- ❌ Medical History: Missing `medical_history`

## 🚨 Critical Issues

### 1. Missing Required Field
- **Physician Status With Secondary**: This is marked as required but missing from form data

### 2. Missing Provider Information
- Provider phone number is missing
- Most provider-specific fields are not available

### 3. Missing Facility Information
- Facility phone number is missing
- Most facility-specific fields are not available

## 💡 Recommendations to Achieve 100% Completion

### 1. Add Missing Data Sources to Form

**Provider Information:**
```javascript
// Add these fields to your form
provider_specialty: "Wound Care Specialist",
provider_tax_id: "12-3456789",
provider_ptan: "123456789",
provider_medicaid: "123456789",
provider_phone: "(555) 987-6543",
provider_fax: "(555) 987-6544",
provider_organization: "Wound Care Associates"
```

**Facility Information:**
```javascript
// Add these fields to your form
facility_npi: "1234567890",
facility_tax_id: "98-7654321",
facility_ptan: "987654321",
facility_medicaid: "987654321",
facility_phone: "(555) 456-7890",
facility_contact_name: "Jane Smith",
facility_fax: "(555) 456-7891",
facility_contact_phone: "(555) 456-7892",
facility_contact_email: "jane.smith@facility.com",
facility_organization: "Healthcare Network LLC"
```

**Patient Information:**
```javascript
// Add these fields to your form
patient_caregiver_info: "Spouse: Mary Doe, Phone: (555) 111-2222"
```

**Insurance Information:**
```javascript
// Add these fields to your form
secondary_insurance_name: "Medicare",
secondary_member_id: "1AB2C3D4E5F6",
secondary_payer_phone: "(800) 633-4227",
secondary_physician_network_status: "in_network" // REQUIRED
```

**Additional Information:**
```javascript
// Add these fields to your form
iso_number: "ISO123456",
additional_notification_emails: "admin@facility.com, billing@facility.com",
medical_history: "Diabetes, Peripheral Vascular Disease, Previous wound care treatment"
```

### 2. Update Form Data Structure

The form should include these additional fields to achieve 100% completion:

```javascript
const completeFormData = {
    // ... existing fields ...
    
    // Provider Information
    provider_specialty: "Wound Care Specialist",
    provider_tax_id: "12-3456789",
    provider_ptan: "123456789",
    provider_medicaid: "123456789",
    provider_phone: "(555) 987-6543",
    provider_fax: "(555) 987-6544",
    provider_organization: "Wound Care Associates",
    
    // Facility Information
    facility_npi: "1234567890",
    facility_tax_id: "98-7654321",
    facility_ptan: "987654321",
    facility_medicaid: "987654321",
    facility_phone: "(555) 456-7890",
    facility_contact_name: "Jane Smith",
    facility_fax: "(555) 456-7891",
    facility_contact_phone: "(555) 456-7892",
    facility_contact_email: "jane.smith@facility.com",
    facility_organization: "Healthcare Network LLC",
    
    // Patient Information
    patient_caregiver_info: "Spouse: Mary Doe, Phone: (555) 111-2222",
    
    // Insurance Information
    secondary_insurance_name: "Medicare",
    secondary_member_id: "1AB2C3D4E5F6",
    secondary_payer_phone: "(800) 633-4227",
    secondary_physician_network_status: "in_network", // REQUIRED
    
    // Additional Information
    iso_number: "ISO123456",
    additional_notification_emails: "admin@facility.com, billing@facility.com",
    medical_history: "Diabetes, Peripheral Vascular Disease, Previous wound care treatment"
};
```

### 3. Test with Complete Data

Run the debug command with complete data:

```bash
php artisan debug:acz-ivr-mapping --data='{"provider_specialty":"Wound Care Specialist","facility_phone":"(555) 456-7890","secondary_physician_network_status":"in_network"}'
```

## 🎯 Expected Results After Implementation

With all the missing fields added to the form data:

- **Total Template Fields**: 52
- **Mapped Fields**: 52
- **Success Rate**: 100%
- **Required Fields**: All mapped
- **Optional Fields**: All mapped where data is available

## 🚀 Next Steps

1. **Add missing fields to your form** (see recommendations above)
2. **Test with complete data** using the debug command
3. **Integrate into production** once 100% mapping is achieved
4. **Monitor success rates** in production environment

This analysis shows that the field mapping strategy is working correctly - it's just missing some data sources that need to be added to the form to achieve 100% completion. 
