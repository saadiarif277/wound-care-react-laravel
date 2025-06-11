## 7. NON_PHI_DATA_MODELS_UPDATED.md

```markdown
# MSC-MVP Wound Care Platform: Non-PHI (Supabase) Data Models (Updated)

**Version:** 1.1  
**Date:** June 9, 2025

## Document Purpose

This document details the data models for all non-PHI operational data stored in the MSC-MVP Wound Care Platform's Supabase PostgreSQL database, reflecting the simplified architecture from the Technical Alignment document.

## Key Architecture Decisions

1. **Hybrid Users Table**: Maintains both authentication and provider data
2. **Simplified Product Requests**: Removed unused workflow tracking fields  
3. **Minimal Schema Changes**: Preserves existing relationships

## Core Data Models

### Users Table (Hybrid Model - Authentication + Provider Data)
```sql
CREATE TABLE users (
  -- Authentication Fields
  id BIGINT UNSIGNED PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  remember_token VARCHAR(255) NULL,
  
  -- User Identity
  first_name VARCHAR(255) NOT NULL,
  last_name VARCHAR(255) NOT NULL,
  role ENUM('Provider', 'OrgAdmin', 'FacilityManager', 'MscRep', 'MscSubRep', 'MscAdmin', 'SuperAdmin') NOT NULL,
  
  -- Provider-Specific Fields (for Provider role)
  npi_number VARCHAR(10) UNIQUE NULL,
  credentials VARCHAR(255) NULL, -- 'MD', 'DPM', 'NP'
  specialty VARCHAR(255) NULL,
  license_number VARCHAR(255) NULL,
  license_state VARCHAR(2) NULL,
  license_expiry DATE NULL,
  dea_number VARCHAR(255) NULL,
  practitioner_fhir_id VARCHAR(255) UNIQUE NULL,
  
  -- Organizational Context
  current_organization_id BIGINT UNSIGNED NULL,
  current_facility_id BIGINT UNSIGNED NULL,
  
  -- Sales Attribution (NEW)
  acquired_by_rep_id BIGINT UNSIGNED NULL,
  acquired_by_subrep_id BIGINT UNSIGNED NULL,
  acquisition_date TIMESTAMP NULL,
  
  -- Status
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  
  INDEX idx_acquired_by (acquired_by_rep_id, acquired_by_subrep_id)
);
Organizations Table (No Changes)
sqlCREATE TABLE organizations (
  id BIGINT UNSIGNED PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  legal_entity_name VARCHAR(255) NULL,
  organization_type ENUM('HospitalSystem', 'LargePracticeGroup', 'IndependentClinicGroup', 'SinglePractice') NOT NULL,
  tax_id VARCHAR(255) ENCRYPTED NOT NULL,
  primary_billing_address_id BIGINT UNSIGNED NULL,
  primary_contact_user_id BIGINT UNSIGNED NULL,
  status ENUM('PendingOnboarding', 'Active', 'Suspended', 'Archived') NOT NULL,
  msc_account_manager_rep_id BIGINT UNSIGNED NULL,
  onboarding_date DATE NULL,
  contract_details_json JSON NULL,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
Facilities Table (Minor Additions)
sqlCREATE TABLE facilities (
  id BIGINT UNSIGNED PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  facility_type ENUM('Clinic', 'HospitalOutpatient', 'PhysicianOffice', 'SNF', 'HomeHealthAgencyBranch') NOT NULL,
  npi VARCHAR(10) NULL, -- Group NPI
  facility_tax_id VARCHAR(255) ENCRYPTED NULL,
  
  -- Address
  address VARCHAR(255) NOT NULL,
  city VARCHAR(255) NOT NULL,
  state VARCHAR(255) NOT NULL,
  zip_code VARCHAR(255) NOT NULL,
  
  -- Contact
  primary_contact_name VARCHAR(255) NULL,
  primary_contact_email VARCHAR(255) NULL,
  phone VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  
  -- NEW Fields for IVR
  ptan VARCHAR(255) NULL, -- Medicare PTAN
  default_place_of_service VARCHAR(2) DEFAULT '11', -- Usually office
  
  -- Status
  status ENUM('Active', 'Inactive') NOT NULL,
  default_mac_jurisdiction VARCHAR(255) NULL,
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
Product Requests Table (Simplified)
sqlCREATE TABLE product_requests (
  id BIGINT UNSIGNED PRIMARY KEY,
  request_number VARCHAR(255) UNIQUE NOT NULL,
  
  -- Core Relationships
  provider_id BIGINT UNSIGNED NOT NULL, -- References users.id
  facility_id BIGINT UNSIGNED NOT NULL,
  patient_fhir_id VARCHAR(255) NOT NULL,
  
  -- Essential Request Data
  expected_service_date DATE NOT NULL,
  wound_type ENUM('DFU','VLU','PU','TW','AU','OTHER') NOT NULL,
  payer_name_submitted VARCHAR(255) NOT NULL,
  payer_id VARCHAR(255) NULL,
  
  -- Streamlined Status (from Technical Alignment)
  order_status ENUM(
    'draft','submitted','processing','approved','pending_ivr',
    'ivr_sent','manufacturer_approved','submitted_to_manufacturer',
    'shipped','delivered','cancelled','denied','sent_back'
  ) NOT NULL DEFAULT 'draft',
  
  -- Financial
  total_order_value DECIMAL(10,2) NULL,
  
  -- FHIR References
  azure_order_checklist_fhir_id VARCHAR(255) NULL,
  
  -- IVR Workflow (Simplified)
  docuseal_submission_id VARCHAR(255) NULL,
  docuseal_template_id VARCHAR(255) NULL,
  ivr_sent_at TIMESTAMP NULL,
  ivr_document_url VARCHAR(255) NULL,
  manufacturer_sent_at TIMESTAMP NULL,
  manufacturer_sent_by BIGINT UNSIGNED NULL,
  manufacturer_approved BOOLEAN DEFAULT false,
  manufacturer_approved_at TIMESTAMP NULL,
  manufacturer_approval_reference VARCHAR(255) NULL,
  manufacturer_notes TEXT NULL,
  
  -- Commission Tracking
  acquiring_rep_id BIGINT UNSIGNED NULL,
  sales_person_id BIGINT UNSIGNED NULL,
  
  -- Audit
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  
  -- Indexes for Performance
  INDEX idx_status (order_status),
  INDEX idx_provider_facility (provider_id, facility_id),
  INDEX idx_service_date (expected_service_date)
);
Order Items Table (No Changes)
sqlCREATE TABLE order_items (
  id BIGINT UNSIGNED PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL, -- References product_requests.id
  msc_product_id BIGINT UNSIGNED NOT NULL,
  quantity INTEGER NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  specific_size VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
MSC Sales Reps Table (Minor Updates)
sqlCREATE TABLE msc_sales_reps (
  id BIGINT UNSIGNED PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, -- References users.id
  first_name VARCHAR(255) NOT NULL,
  last_name VARCHAR(255) NOT NULL,
  employee_id VARCHAR(255) NULL,
  contact_email VARCHAR(255) UNIQUE NOT NULL,
  contact_phone VARCHAR(255) NULL,
  parent_rep_id BIGINT UNSIGNED NULL,
  commission_rate_direct DECIMAL(5,2) NOT NULL,
  sub_rep_parent_share_percentage DECIMAL(5,2) NULL,
  status ENUM('PendingApproval', 'Active', 'Terminated') NOT NULL,
  region_assignment_json JSON NULL,
  
  -- NEW Fields
  monthly_target DECIMAL(10,2) DEFAULT 0,
  provider_count INT DEFAULT 0,
  
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
Simplified Relationships
User-Facility Assignments (Many-to-Many)
sqlCREATE TABLE user_facilities (
  id BIGINT UNSIGNED PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  facility_id BIGINT UNSIGNED NOT NULL,
  is_primary_location BOOLEAN DEFAULT false,
  can_order_products BOOLEAN DEFAULT true,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  
  UNIQUE KEY unique_user_facility (user_id, facility_id)
);
Key Queries for 90% IVR Auto-Population
Single Query for IVR Data
sqlSELECT 
  -- Product Request (15%)
  pr.request_number,
  pr.expected_service_date,
  pr.wound_type,
  pr.payer_name_submitted,
  pr.patient_fhir_id,
  
  -- Provider/User (25%)
  u.first_name as provider_first_name,
  u.last_name as provider_last_name,
  u.npi_number as provider_npi,
  u.credentials,
  u.email as provider_email,
  
  -- Facility (30%)
  f.name as facility_name,
  f.npi as facility_npi,
  f.address,
  f.city,
  f.state,
  f.zip_code,
  f.phone as facility_phone,
  f.ptan,
  f.default_place_of_service,
  
  -- Organization (20%)
  o.name as organization_name,
  o.tax_id

FROM product_requests pr
JOIN users u ON pr.provider_id = u.id
JOIN facilities f ON pr.facility_id = f.id
JOIN organizations o ON f.organization_id = o.id
WHERE pr.id = ?;
Migration Notes
Required Schema Changes
sql-- 1. Add fields to facilities
ALTER TABLE facilities 
ADD COLUMN ptan VARCHAR(255) NULL,
ADD COLUMN default_place_of_service VARCHAR(2) DEFAULT '11';

-- 2. Add sales attribution to users
ALTER TABLE users
ADD COLUMN acquired_by_rep_id BIGINT UNSIGNED NULL,
ADD COLUMN acquired_by_subrep_id BIGINT UNSIGNED NULL,
ADD COLUMN acquisition_date TIMESTAMP NULL,
ADD INDEX idx_acquired_by (acquired_by_rep_id, acquired_by_subrep_id);

-- 3. Update sales reps
ALTER TABLE msc_sales_reps
ADD COLUMN monthly_target DECIMAL(10,2) DEFAULT 0,
ADD COLUMN provider_count INT DEFAULT 0;

-- 4. Clean up product_requests (remove unused fields)
ALTER TABLE product_requests
DROP COLUMN medicare_part_b_authorized,
DROP COLUMN ivr_bypass_reason,
DROP COLUMN ivr_bypassed_at,
DROP COLUMN ivr_bypassed_by,
DROP COLUMN ivr_signed_at,
DROP COLUMN pre_auth_submitted_at,
DROP COLUMN pre_auth_approved_at,
DROP COLUMN pre_auth_denied_at;
Benefits of This Approach

Minimal Breaking Changes: Existing code continues to work
90% IVR Auto-Population: Single query provides most data
Simplified Status Flow: Clear progression without complexity
Maintained Flexibility: Supports complex healthcare organizations
Performance Optimized: Proper indexes for common queries