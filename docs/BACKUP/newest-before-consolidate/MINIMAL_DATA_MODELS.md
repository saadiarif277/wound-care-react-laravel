# Minimal Provider & Facility Data Models

Here are the **absolute minimum** data models needed to achieve 85-90% IVR auto-population:

## Minimal Facility Profile

### **Core Facility Table:**
```sql
CREATE TABLE facilities_minimal (
  id UUID PRIMARY KEY,
  
  -- Basic Identity (Required for all IVRs)
  name VARCHAR NOT NULL,                    -- "Healing Hands Wound Care"
  npi VARCHAR(10) NOT NULL,                -- "9876543210" 
  tax_id VARCHAR ENCRYPTED NOT NULL,       -- "12-3456789"
  
  -- Address (Required for shipping/billing)
  street_address VARCHAR NOT NULL,         -- "789 Healthcare Drive"
  city VARCHAR NOT NULL,                   -- "Anytown"
  state VARCHAR(2) NOT NULL,               -- "CA"
  zip_code VARCHAR(10) NOT NULL,           -- "12345"
  
  -- Contact (Required for order processing)
  phone VARCHAR NOT NULL,                  -- "555-123-7890"
  email VARCHAR NOT NULL,                  -- "contact@healinghands.com"
  
  -- Operational Context
  facility_type ENUM NOT NULL,             -- 'Clinic', 'HospitalOutpatient', 'PhysicianOffice'
  default_place_of_service VARCHAR(2),     -- "11" (Office) - most common
  
  -- Billing
  ptan VARCHAR,                            -- Medicare PTAN number
  
  -- Timestamps
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

### **Facility Contact Person (Optional but Helpful):**
```sql
CREATE TABLE facility_contacts_minimal (
  id UUID PRIMARY KEY,
  facility_id UUID REFERENCES facilities_minimal(id),
  
  contact_name VARCHAR NOT NULL,           -- "Sarah Johnson, Office Manager"
  contact_phone VARCHAR,                   -- Direct line for orders
  contact_email VARCHAR,                   -- For order confirmations
  is_primary BOOLEAN DEFAULT false,
  
  created_at TIMESTAMP NOT NULL
);
```

## Minimal Provider Profile

### **Core Provider Table:**
```sql
CREATE TABLE providers_minimal (
  id UUID PRIMARY KEY,
  
  -- Basic Identity (Required for all IVRs)
  first_name VARCHAR NOT NULL,             -- "Robert"
  last_name VARCHAR NOT NULL,              -- "Johnson"
  credentials VARCHAR,                     -- "MD, DPM" 
  
  -- Professional Identity (Required)
  individual_npi VARCHAR(10) UNIQUE NOT NULL, -- "1234567890"
  specialty VARCHAR NOT NULL,              -- "Podiatry", "Wound Care", "Family Medicine"
  
  -- Contact (Required for orders)
  contact_email VARCHAR UNIQUE NOT NULL,   -- "dr.johnson@healinghands.com"
  contact_phone VARCHAR,                   -- "555-123-7891"
  
  -- Medicare/Billing (Required for reimbursement)
  ptan VARCHAR,                            -- Individual PTAN if different from facility
  
  -- License (Required for compliance)
  primary_state_license VARCHAR ENCRYPTED, -- State medical license number
  license_state VARCHAR(2),                -- "CA"
  
  -- Status
  is_active BOOLEAN DEFAULT true,
  
  -- Timestamps  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

### **Provider-Facility Assignments:**
```sql
CREATE TABLE provider_facility_assignments_minimal (
  id UUID PRIMARY KEY,
  provider_id UUID REFERENCES providers_minimal(id),
  facility_id UUID REFERENCES facilities_minimal(id),
  
  -- Assignment Status
  is_primary_location BOOLEAN DEFAULT false,  -- Provider's main practice location
  can_order_products BOOLEAN DEFAULT true,    -- Can this provider order from this location
  
  -- Timestamps
  assigned_at TIMESTAMP NOT NULL,
  is_active BOOLEAN DEFAULT true
);
```

## IVR Auto-Population Coverage:

### **From Facility Profile (30% of IVR):**
```yaml
✅ Facility Name
✅ Facility Address (street, city, state, zip)  
✅ Facility NPI
✅ Tax ID
✅ Facility Phone
✅ Facility Email
✅ Default Place of Service
✅ Facility PTAN (if available)
```

### **From Provider Profile (25% of IVR):**
```yaml
✅ Physician Name (first + last + credentials)
✅ Physician NPI  
✅ Physician Specialty
✅ Provider Phone
✅ Provider Email
✅ Provider PTAN (if available)
```

### **Still Requires Manual Entry (45% of IVR):**
```yaml
❓ Patient demographics (name, DOB, member ID, insurance)
❓ Clinical codes (ICD-10, CPT) 
❓ Service date and specific place of service
❓ Wound measurements and clinical details
❓ Product selection and sizing
```

## Sample Data Structure:

### **Complete Facility Record:**
```json
{
  "id": "fac-12345",
  "name": "Healing Hands Wound Care Center",
  "npi": "9876543210",
  "tax_id": "[ENCRYPTED:12-3456789]",
  "street_address": "789 Healthcare Drive",
  "city": "Anytown", 
  "state": "CA",
  "zip_code": "12345",
  "phone": "555-123-7890",
  "email": "contact@healinghands.com",
  "facility_type": "Clinic",
  "default_place_of_service": "11",
  "ptan": "12345CA",
  "primary_contact": {
    "name": "Sarah Johnson",
    "phone": "555-123-7891", 
    "email": "sarah@healinghands.com"
  }
}
```

### **Complete Provider Record:**
```json
{
  "id": "prov-67890",
  "first_name": "Robert",
  "last_name": "Johnson", 
  "credentials": "DPM",
  "individual_npi": "1234567890",
  "specialty": "Podiatry",
  "contact_email": "dr.johnson@healinghands.com",
  "contact_phone": "555-123-7892",
  "ptan": "67890CA",
  "primary_state_license": "[ENCRYPTED:MD12345]",
  "license_state": "CA",
  "facilities": [
    {
      "facility_id": "fac-12345",
      "is_primary_location": true,
      "can_order_products": true
    }
  ]
}
```

## Optional Enhancements (For Better UX):

### **Provider Preferences:**
```sql
-- Could be added later for even better auto-population
CREATE TABLE provider_preferences (
  provider_id UUID REFERENCES providers_minimal(id),
  
  -- Common selections (learns from usage)
  preferred_place_of_service VARCHAR(2),   -- If different from facility default
  common_wound_types JSON,                 -- ["DFU", "VLU"] - most treated
  common_procedure_codes JSON,             -- ["15275", "15276"] - most used
  
  -- Ordering preferences  
  preferred_delivery_timing VARCHAR,       -- "day_before", "two_days_before"
  preferred_order_type VARCHAR             -- "consignment", "direct_purchase"
);
```

This minimal model captures **exactly what's needed** for IVR auto-population while keeping the data structure simple and focused. You could start with just these core tables and add enhancements based on usage patterns.