# Organization → Facility → Provider Relationship Model

Based on analyzing the real-world forms and MSC-MVP's business model, here's the optimal relationship structure:

## Core Relationship Architecture:

```
Organization (1) ←→ (Many) Facilities (1) ←→ (Many) Providers
     ↓                    ↓                      ↓
Business Entity      Physical Locations    Individual Practitioners
Contract Holder      Service Delivery      Order Creators
```

## 1. Organization Table (Contract & Billing Entity):

```sql
CREATE TABLE organizations (
  id UUID PRIMARY KEY,
  
  -- Business Identity
  name VARCHAR NOT NULL,                    -- "Healing Hands Hospital System"
  legal_entity_name VARCHAR,               -- "Healing Hands Healthcare, LLC"
  tax_id VARCHAR ENCRYPTED NOT NULL,       -- Master tax ID for billing
  
  -- Organization Type (Important for MSC sales strategy)
  organization_type ENUM NOT NULL,         -- 'HospitalSystem', 'LargePracticeGroup', 
                                          -- 'IndependentClinicGroup', 'SinglePractice'
  
  -- Contract & Commercial
  msc_contract_status ENUM,               -- 'Active', 'Pending', 'Suspended'
  primary_sales_rep_id UUID,              -- Assigned MSC sales rep
  contract_effective_date DATE,
  
  -- Billing Consolidation
  consolidated_billing BOOLEAN DEFAULT false, -- Bill entire org together?
  payment_terms VARCHAR DEFAULT '30_days',    -- Organization-wide terms
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

## 2. Facility Table (Physical Locations):

```sql
CREATE TABLE facilities (
  id UUID PRIMARY KEY,
  organization_id UUID REFERENCES organizations(id), -- Parent organization
  
  -- Facility Identity
  name VARCHAR NOT NULL,                   -- "Healing Hands - Downtown Clinic"
  facility_type ENUM NOT NULL,            -- 'Clinic', 'HospitalOutpatient', 'PhysicianOffice'
  
  -- Physical Location (Required for shipping)
  street_address VARCHAR NOT NULL,
  city VARCHAR NOT NULL,
  state VARCHAR(2) NOT NULL,
  zip_code VARCHAR(10) NOT NULL,
  
  -- Unique Identifiers
  facility_npi VARCHAR(10),               -- Group NPI (may be same as org)
  facility_tax_id VARCHAR ENCRYPTED,      -- If different from organization
  
  -- Operational Details
  phone VARCHAR NOT NULL,
  email VARCHAR NOT NULL,
  default_place_of_service VARCHAR(2) DEFAULT '11', -- Most common: Office
  
  -- Medicare/Billing
  ptan VARCHAR,                           -- Facility-specific PTAN
  mac_jurisdiction VARCHAR,               -- Determined by ZIP code
  
  -- Status
  is_active BOOLEAN DEFAULT true,
  accepts_new_orders BOOLEAN DEFAULT true,
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

## 3. Provider Table (Individual Practitioners):

```sql
CREATE TABLE providers (
  id UUID PRIMARY KEY,
  
  -- Personal Identity
  first_name VARCHAR NOT NULL,
  last_name VARCHAR NOT NULL,
  credentials VARCHAR NOT NULL,            -- "MD", "DPM", "NP", etc.
  
  -- Professional Identity (Unique to individual)
  individual_npi VARCHAR(10) UNIQUE NOT NULL,
  specialty VARCHAR NOT NULL,              -- "Podiatry", "Wound Care", "Endocrinology"
  
  -- License & Credentials
  primary_state_license VARCHAR ENCRYPTED,
  license_state VARCHAR(2),
  license_expiration DATE,
  dea_number VARCHAR ENCRYPTED,            -- If prescribing
  
  -- Contact
  professional_email VARCHAR UNIQUE,       -- Provider's direct email
  professional_phone VARCHAR,              -- Provider's direct line
  
  -- Medicare
  individual_ptan VARCHAR,                 -- Provider-specific PTAN
  
  -- Platform Access
  user_id UUID,                           -- Link to platform user account
  
  -- Status
  is_active BOOLEAN DEFAULT true,
  credentials_verified BOOLEAN DEFAULT false,
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

## 4. Provider-Facility Assignments (Many-to-Many):

```sql
CREATE TABLE provider_facility_assignments (
  id UUID PRIMARY KEY,
  provider_id UUID REFERENCES providers(id),
  facility_id UUID REFERENCES facilities(id),
  
  -- Assignment Details
  is_primary_location BOOLEAN DEFAULT false,     -- Provider's main practice location
  can_order_products BOOLEAN DEFAULT true,       -- Ordering privileges at this facility
  can_approve_orders BOOLEAN DEFAULT false,      -- Administrative approval rights
  
  -- Relationship Context
  employment_type ENUM,                          -- 'Employee', 'Contractor', 'Affiliate'
  start_date DATE NOT NULL,
  end_date DATE,                                 -- If assignment ends
  
  -- Operational
  office_extension VARCHAR,                      -- Phone extension at this facility
  office_email VARCHAR,                         -- Facility-specific email if different
  
  -- Status
  assignment_status ENUM DEFAULT 'Active',      -- 'Active', 'Inactive', 'Suspended'
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  
  UNIQUE(provider_id, facility_id)
);
```

## Real-World Examples:

### **Single Practice (Simple):**
```yaml
Organization: "Dr. Smith Podiatry"
  └── Facility: "Dr. Smith Podiatry - Main Office"
      └── Provider: "Dr. James Smith, DPM"
```

### **Hospital System (Complex):**
```yaml
Organization: "Regional Health System" 
  ├── Facility: "Regional Hospital - Wound Care Center"
  │   ├── Provider: "Dr. Sarah Jones, MD" (Wound Care)
  │   └── Provider: "Dr. Mike Chen, DPM" (Podiatry)
  ├── Facility: "Regional Clinic - Downtown"
  │   ├── Provider: "Dr. Sarah Jones, MD" (also practices here)
  │   └── Provider: "Dr. Lisa Brown, NP" (Nurse Practitioner)
  └── Facility: "Regional Surgery Center"
      └── Provider: "Dr. Mike Chen, DPM" (also does surgery here)
```

### **Large Practice Group (Medium):**
```yaml
Organization: "Metro Wound Care Associates"
  ├── Facility: "Metro WCA - North Campus"
  │   ├── Provider: "Dr. Robert Johnson, DPM"
  │   └── Provider: "Dr. Emily Davis, MD"
  ├── Facility: "Metro WCA - South Campus" 
  │   ├── Provider: "Dr. Emily Davis, MD" (practices both locations)
  │   └── Provider: "Dr. Alex Kim, DPM"
  └── Facility: "Metro WCA - Outpatient Surgery"
      └── Provider: "All providers" (shared surgical facility)
```

## IVR Auto-Population Logic:

### **Data Hierarchy for Forms:**
```typescript
// When filling IVR for Dr. Johnson at Metro WCA North:

1. Organization Level:
   ✅ Contract status, sales rep assignment
   ✅ Consolidated billing preferences
   ✅ Master tax ID (if facility doesn't have separate)

2. Facility Level:
   ✅ Physical shipping address  
   ✅ Facility NPI, PTAN
   ✅ Phone, email for orders
   ✅ Default place of service
   ✅ MAC jurisdiction

3. Provider Level:
   ✅ Individual NPI, credentials
   ✅ Specialty, license information
   ✅ Personal contact details
   ✅ Individual PTAN (if different)

4. Assignment Level:
   ✅ Ordering privileges confirmation
   ✅ Facility-specific contact info
   ✅ Role-based permissions
```

## Business Rules Implementation:

### **Order Authorization:**
```sql
-- Can this provider order from this facility?
SELECT pfa.can_order_products 
FROM provider_facility_assignments pfa
WHERE pfa.provider_id = ? 
  AND pfa.facility_id = ?
  AND pfa.assignment_status = 'Active'
  AND pfa.end_date IS NULL OR pfa.end_date > CURRENT_DATE;
```

### **Billing Consolidation:**
```sql
-- Should this order bill to organization or facility?
SELECT CASE 
  WHEN o.consolidated_billing = true THEN o.tax_id
  ELSE f.facility_tax_id 
END as billing_tax_id
FROM facilities f 
JOIN organizations o ON f.organization_id = o.id
WHERE f.id = ?;
```

### **Sales Rep Assignment:**
```sql
-- Who manages this relationship?
SELECT o.primary_sales_rep_id
FROM orders ord
JOIN facilities f ON ord.facility_id = f.id  
JOIN organizations o ON f.organization_id = o.id
WHERE ord.id = ?;
```

This model supports both **simple single-practice scenarios** and **complex multi-location health systems** while maintaining clear data boundaries for IVR auto-population and business operations.