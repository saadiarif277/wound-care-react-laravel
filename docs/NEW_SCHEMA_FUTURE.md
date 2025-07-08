-- =====================================================
-- AGNOSTIC MEDICAL DISTRIBUTION PLATFORM SCHEMA
-- Business Logic Database (Non-PHI)
-- FHIR IDs reference Azure Health Data Services
-- =====================================================
1. Principles
Non-PHI, business logic only (all PHI in Azure Health/FHIR by reference)


Multi-tenant, multi-vertical (pluggable for DME, surgical, pharma, etc)


Dynamic, scoped RBAC (roles/permissions, mapping tables, plus ENUM shortcuts for core workflow)


Strong constraints, enums, audit


Episode → Request → Order → Verification as backbone flow


Pluggable modules: commissions, compliance, integrations, docs, audit, workflow views



2. Table Definitions
### 2.1. Tenants
CREATE TABLE tenants (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  type ENUM('distributor', 'manufacturer', 'platform') NOT NULL,
  settings JSON DEFAULT '{}',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 2.2. Users
CREATE TABLE users (
  id CHAR(36) PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255),
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  phone VARCHAR(50),
  provider_fhir_id VARCHAR(255), -- FHIR Practitioner
  user_type ENUM('provider', 'office_manager', 'sales_rep', 'admin', 'manufacturer_rep') NOT NULL,
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  settings JSON DEFAULT '{}',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 2.3. Roles
CREATE TABLE roles (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(50) UNIQUE NOT NULL,
  description VARCHAR(255),
  is_system BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


### 2.4. Permissions
CREATE TABLE permissions (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


### 2.5. User Roles (Scoped RBAC)
CREATE TABLE user_roles (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  role_id CHAR(36) NOT NULL,
  scope_type ENUM('organization', 'facility', 'manufacturer', 'tenant', 'global') NOT NULL,
  scope_id CHAR(36),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);


### 2.6. Role Permissions
CREATE TABLE role_permissions (
  id CHAR(36) PRIMARY KEY,
  role_id CHAR(36) NOT NULL,
  permission_id CHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
);


### 2.7. Organizations (Facilities/Distributors/Manufacturers)
CREATE TABLE organizations (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  parent_id CHAR(36),
  organization_fhir_id VARCHAR(255),
  type ENUM('facility', 'manufacturer', 'provider_practice', 'distributor', 'payer') NOT NULL,
  name VARCHAR(255) NOT NULL,
  npi VARCHAR(20),
  tax_id VARCHAR(20),
  business_email VARCHAR(255),
  business_phone VARCHAR(50),
  settings JSON DEFAULT '{}',
  status ENUM('active', 'inactive', 'onboarding') DEFAULT 'onboarding',
  activated_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (parent_id) REFERENCES organizations(id)
);


### 2.8. User Facility Assignments (Fine-grained per-org permissions)
CREATE TABLE user_facility_assignments (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  role ENUM('office_manager', 'provider', 'coordinator', 'viewer', 'admin', 'rep') NOT NULL,
  can_order BOOLEAN DEFAULT FALSE,
  can_view_orders BOOLEAN DEFAULT TRUE,
  can_view_financial BOOLEAN DEFAULT FALSE,
  can_manage_verifications BOOLEAN DEFAULT FALSE,
  can_order_for_providers JSON DEFAULT '[]', -- array of provider_fhir_ids
  is_primary_facility BOOLEAN DEFAULT FALSE,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (facility_id) REFERENCES organizations(id),
  UNIQUE KEY uniq_user_facility (user_id, facility_id)
);


### 2.9. Patient References (Pointer only, no PHI)
CREATE TABLE patient_references (
  id CHAR(36) PRIMARY KEY,
  patient_fhir_id VARCHAR(255) UNIQUE NOT NULL,
  patient_display_id VARCHAR(10), -- For UI, non-PHI
  display_metadata JSON NOT NULL, -- e.g., {"first_init":"JO", "last_init":"SM", "random":"1234"}
  tenant_id CHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);


### 2.10. Episodes
CREATE TABLE episodes (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  episode_number VARCHAR(50) UNIQUE NOT NULL,
  patient_fhir_id VARCHAR(255) NOT NULL,
  primary_provider_fhir_id VARCHAR(255),
  primary_facility_id CHAR(36) NOT NULL,
  type ENUM('wound_care', 'surgical_case', 'dme_need', 'implant_procedure', 'ongoing_supply') NOT NULL,
  sub_type VARCHAR(100),
  status ENUM('planned', 'active', 'completed', 'cancelled', 'on_hold') DEFAULT 'planned',
  diagnosis_fhir_refs JSON DEFAULT '[]',
  procedure_fhir_refs JSON DEFAULT '[]',
  estimated_duration_days INT,
  priority ENUM('routine', 'urgent', 'emergent') DEFAULT 'routine',
  start_date DATE NOT NULL,
  target_date DATE,
  end_date DATE,
  tags JSON DEFAULT '[]',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by CHAR(36),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (primary_facility_id) REFERENCES organizations(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);


### 2.11. Episode Care Team
CREATE TABLE episode_care_team (
  id CHAR(36) PRIMARY KEY,
  episode_id CHAR(36) NOT NULL,
  user_id CHAR(36),
  provider_fhir_id VARCHAR(255),
  role ENUM('primary_surgeon', 'attending_physician', 'care_coordinator', 'office_manager', 'consulting_physician') NOT NULL,
  can_order BOOLEAN DEFAULT FALSE,
  can_modify BOOLEAN DEFAULT FALSE,
  can_view_financial BOOLEAN DEFAULT FALSE,
  assigned_date DATE NOT NULL,
  removed_date DATE,
  FOREIGN KEY (episode_id) REFERENCES episodes(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);


### 2.12. Product Requests
CREATE TABLE product_requests (
  id CHAR(36) PRIMARY KEY,
  episode_id CHAR(36) NOT NULL,
  request_number VARCHAR(50) UNIQUE NOT NULL,
  requested_by CHAR(36) NOT NULL,
  requested_for_provider_fhir_id VARCHAR(255),
  request_type ENUM('initial_assessment', 'replenishment', 'urgent_need', 'planned_procedure') NOT NULL,
  status ENUM('draft', 'submitted', 'reviewing', 'approved', 'converted_to_order', 'cancelled') DEFAULT 'draft',
  clinical_need TEXT,
  urgency ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
  product_categories JSON DEFAULT '[]',
  specific_products JSON DEFAULT '[]',
  needed_by_date DATE,
  submitted_at TIMESTAMP NULL,
  reviewed_at TIMESTAMP NULL,
  converted_to_order_id CHAR(36),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (episode_id) REFERENCES episodes(id),
  FOREIGN KEY (requested_by) REFERENCES users(id)
);


### 2.13. Orders
CREATE TABLE orders (
  id CHAR(36) PRIMARY KEY,
  episode_id CHAR(36) NOT NULL,
  product_request_id CHAR(36),
  order_number VARCHAR(50) UNIQUE NOT NULL,
  order_type ENUM('standard', 'urgent', 'standing', 'trial') DEFAULT 'standard',
  status ENUM('draft', 'pending_verification', 'verification_in_progress', 'pending_approval', 'approved', 'transmitted_to_manufacturer', 'acknowledged', 'in_fulfillment', 'shipped', 'delivered', 'cancelled') DEFAULT 'draft',
  ordering_provider_fhir_id VARCHAR(255),
  ordered_by_user_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  manufacturer_id CHAR(36),
  service_date DATE NOT NULL,
  ship_to_type ENUM('facility', 'patient_home', 'other') DEFAULT 'facility',
  requires_insurance_verification BOOLEAN DEFAULT TRUE,
  requires_prior_auth BOOLEAN DEFAULT FALSE,
  verification_status ENUM('not_required', 'pending', 'in_progress', 'completed', 'failed', 'bypassed') DEFAULT 'pending',
  estimated_total DECIMAL(10,2),
  final_total DECIMAL(10,2),
  patient_responsibility DECIMAL(10,2),
  insurance_coverage DECIMAL(10,2),
  compliance_check_status ENUM('pending', 'passed', 'failed_with_override', 'failed') DEFAULT 'pending',
  submitted_at TIMESTAMP NULL,
  approved_at TIMESTAMP NULL,
  transmitted_at TIMESTAMP NULL,
  shipped_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  internal_notes TEXT,
  manufacturer_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (episode_id) REFERENCES episodes(id),
  FOREIGN KEY (product_request_id) REFERENCES product_requests(id),
  FOREIGN KEY (ordered_by_user_id) REFERENCES users(id),
  FOREIGN KEY (facility_id) REFERENCES organizations(id),
  FOREIGN KEY (manufacturer_id) REFERENCES organizations(id)
);


### 2.14. Order Items
CREATE TABLE order_items (
  id CHAR(36) PRIMARY KEY,
  order_id CHAR(36) NOT NULL,
  product_id CHAR(36) NOT NULL,
  quantity INT NOT NULL,
  unit_of_measure VARCHAR(20) DEFAULT 'each',
  unit_price DECIMAL(10,2),
  discount_percentage DECIMAL(5,2) DEFAULT 0,
  line_total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price * (1 - discount_percentage/100)) STORED,
  specific_indication TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);


### 2.15. Verifications
CREATE TABLE verifications (
  id CHAR(36) PRIMARY KEY,
  episode_id CHAR(36) NOT NULL,
  order_id CHAR(36),
  verification_type ENUM('insurance_eligibility', 'prior_authorization', 'medical_necessity', 'provider_license') NOT NULL,
  verification_subtype VARCHAR(100),
  required_by_organization_id CHAR(36) NOT NULL,
  payer_organization_id CHAR(36),
  form_template_id VARCHAR(255),
  form_provider ENUM('docuseal', 'office_ally', 'availity', 'internal', 'manual') DEFAULT 'internal',
  status ENUM('not_started', 'pending', 'in_progress', 'under_review', 'completed', 'expired', 'failed') DEFAULT 'not_started',
  required_fields JSON DEFAULT '{}',
  completed_fields JSON DEFAULT '{}',
  completeness_percentage DECIMAL(5,2) DEFAULT 0,
  determination ENUM('approved', 'denied', 'partial', 'pending') NULL,
  coverage_details JSON DEFAULT '{}',
  external_submission_id VARCHAR(255),
  external_status VARCHAR(100),
  verified_date DATE,
  expires_date DATE,
  submitted_document_ids JSON DEFAULT '[]',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (episode_id) REFERENCES episodes(id),
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (required_by_organization_id) REFERENCES organizations(id),
  FOREIGN KEY (payer_organization_id) REFERENCES organizations(id)
);


### 2.16. Products
CREATE TABLE products (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  manufacturer_id CHAR(36) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  manufacturer_part_number VARCHAR(100),
  category ENUM('wound_dressing', 'surgical_implant', 'dme_equipment', 'surgical_instrument', 'pharmaceutical', 'other') NOT NULL,
  sub_category VARCHAR(100),
  name VARCHAR(255) NOT NULL,
  description TEXT,
  hcpcs_code VARCHAR(20),
  cpt_codes JSON DEFAULT '[]',
  requires_prescription BOOLEAN DEFAULT FALSE,
  requires_verification BOOLEAN DEFAULT TRUE,
  requires_sizing BOOLEAN DEFAULT FALSE,
  specifications JSON DEFAULT '{}',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (manufacturer_id) REFERENCES organizations(id),
  UNIQUE KEY uniq_product_sku (tenant_id, sku)
);


### 2.17. Compliance Rules
CREATE TABLE compliance_rules (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  rule_name VARCHAR(255) NOT NULL,
  rule_type ENUM('medicare_lcd', 'medicare_ncd', 'payer_policy', 'state_regulation', 'internal_policy') NOT NULL,
  applies_to_categories JSON DEFAULT '[]',
  applies_to_products JSON DEFAULT '[]',
  applies_to_states JSON DEFAULT '[]',
  applies_to_payers JSON DEFAULT '[]',
  rule_engine ENUM('json_logic', 'javascript', 'regex') DEFAULT 'json_logic',
  rule_definition TEXT NOT NULL,
  required_documentation JSON DEFAULT '[]',
  required_fields JSON DEFAULT '[]',
  severity ENUM('error', 'warning', 'info') DEFAULT 'error',
  can_override BOOLEAN DEFAULT FALSE,
  effective_date DATE NOT NULL,
  expiration_date DATE,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);


### 2.18. Order Compliance Checks
CREATE TABLE order_compliance_checks (
  id CHAR(36) PRIMARY KEY,
  order_id CHAR(36) NOT NULL,
  check_type ENUM('pre_submission', 'pre_approval', 'final') NOT NULL,
  passed BOOLEAN NOT NULL,
  applied_rules JSON DEFAULT '[]',
  failures JSON DEFAULT '[]',
  warnings JSON DEFAULT '[]',
  overridden BOOLEAN DEFAULT FALSE,
  override_reason TEXT,
  overridden_by CHAR(36),
  checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (overridden_by) REFERENCES users(id)
);


### 2.19. Documents (Metadata Only)
CREATE TABLE documents (
  id CHAR(36) PRIMARY KEY,
  entity_type ENUM('episode', 'order', 'verification', 'product_request') NOT NULL,
  entity_id CHAR(36) NOT NULL,
  document_type VARCHAR(50) NOT NULL,
  document_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500),
  mime_type VARCHAR(100),
  file_size_bytes BIGINT,
  requires_signature BOOLEAN DEFAULT FALSE,
  signature_type ENUM('patient', 'provider', 'witness', 'notary'),
  signed_at TIMESTAMP NULL,
  signature_method ENUM('docuseal', 'manual', 'tablet'),
  metadata JSON DEFAULT '{}',
  uploaded_by CHAR(36),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  retention_until DATE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
);


### 2.20. Commission Rules
CREATE TABLE commission_rules (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  rule_name VARCHAR(255) NOT NULL,
  applies_to_products JSON DEFAULT '[]',
  applies_to_categories JSON DEFAULT '[]',
  applies_to_facilities JSON DEFAULT '[]',
  commission_type ENUM('percentage', 'flat_amount', 'tiered') NOT NULL,
  base_rate DECIMAL(10,4),
  tier_definitions JSON DEFAULT '[]',
  split_rules JSON DEFAULT '[]',
  effective_date DATE NOT NULL,
  end_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);


### 2.21. Commission Records
CREATE TABLE commission_records (
  id CHAR(36) PRIMARY KEY,
  order_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  rule_id CHAR(36) NOT NULL,
  base_amount DECIMAL(10,2) NOT NULL,
  commission_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending', 'approved', 'paid', 'cancelled', 'clawback') DEFAULT 'pending',
  payment_period VARCHAR(20),
  paid_date DATE,
  payment_reference VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at TIMESTAMP NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (rule_id) REFERENCES commission_rules(id)
);


### 2.22. Integration Events
CREATE TABLE integration_events (
  id CHAR(36) PRIMARY KEY,
  entity_type VARCHAR(50),
  entity_id CHAR(36),
  integration_type ENUM('fhir', 'docuseal', 'availity', 'opt

um', 'office_ally') NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 request_data JSON,
 response_data JSON,
 status ENUM('success', 'failure', 'timeout', 'partial') NOT NULL,
 error_message TEXT,
 duration_ms INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 );

---

### ### 2.23. Audit Logs

```sql
CREATE TABLE audit_logs (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36),
  acting_as VARCHAR(255),
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50),
  entity_id CHAR(36),
  changes JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =====================================================
-- HELPFUL VIEWS
-- =====================================================

-- Office manager order view (NO FINANCIAL DATA)
CREATE VIEW office_manager_order_view AS
SELECT 
  o.id,
  o.order_number,
  o.episode_id,
  o.status,
  o.order_type,
  o.ordering_provider_fhir_id,
  o.service_date,
  o.verification_status,
  o.submitted_at,
  o.delivered_at,
  p.patient_display_id,
  e.episode_number,
  e.type as episode_type,
  f.name as facility_name
FROM orders o
JOIN episodes e ON o.episode_id = e.id
JOIN patient_references p ON e.patient_fhir_id = p.patient_fhir_id
JOIN organizations f ON o.facility_id = f.id
-- Note: NO financial fields included
;

-- Provider financial view
CREATE VIEW provider_order_financial_view AS
SELECT 
  o.*,
  e.episode_number,
  p.patient_display_id,
  f.name as facility_name,
  m.name as manufacturer_name
FROM orders o
JOIN episodes e ON o.episode_id = e.id
JOIN patient_references p ON e.patient_fhir_id = p.patient_fhir_id
JOIN organizations f ON o.facility_id = f.id
LEFT JOIN organizations m ON o.manufacturer_id = m.id
;

-- Episode summary with verification status
CREATE VIEW episode_verification_summary AS
SELECT 
  e.id as episode_id,
  e.episode_number,
  e.status as episode_status,
  COUNT(DISTINCT v.id) as total_verifications,
  COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) as completed_verifications,
  COUNT(DISTINCT CASE WHEN v.status = 'expired' THEN v.id END) as expired_verifications,
  MIN(v.expires_date) as next_expiration_date
FROM episodes e
LEFT JOIN verifications v ON v.episode_id = e.id
GROUP BY e.id;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Generate patient display ID
CREATE FUNCTION generate_patient_display_id(first_name VARCHAR(100), last_name VARCHAR(100))
RETURNS JSON
DETERMINISTIC
BEGIN
  DECLARE first_init VARCHAR(2);
  DECLARE last_init VARCHAR(2);
  DECLARE random_digits VARCHAR(4);
  
  SET first_init = UPPER(LEFT(first_name, 2));
  SET last_init = UPPER(LEFT(last_name, 2));
  SET random_digits = LPAD(FLOOR(RAND() * 10000), 4, '0');
  
  RETURN JSON_OBJECT(
    'display_id', CONCAT(first_init, last_init, random_digits),
    'first_init', first_init,
    'last_init', last_init,
    'random', random_digits
  );
END//

-- Check if user can view order financial data
CREATE FUNCTION can_user_view_financial(user_id CHAR(36), order_id CHAR(36))
RETURNS BOOLEAN
READS SQL DATA
BEGIN
  DECLARE user_type VARCHAR(50);
  DECLARE facility_id CHAR(36);
  DECLARE can_view BOOLEAN DEFAULT FALSE;
  
  -- Get user type
  SELECT user_type INTO user_type FROM users WHERE id = user_id;
  
  -- Admins always can
  IF user_type = 'admin' THEN
    RETURN TRUE;
  END IF;
  
  -- Get order facility
  SELECT facility_id INTO facility_id FROM orders WHERE id = order_id;
  
  -- Check facility assignment permissions
  SELECT can_view_financial INTO can_view
  FROM user_facility_assignments
  WHERE user_id = user_id AND facility_id = facility_id;
  
  RETURN COALESCE(can_view, FALSE);
END//

DELIMITER ;


-- =====================================================
-- INITIAL DATA
-- =====================================================

-- System roles
INSERT INTO roles (id, name, permissions, is_system) VALUES
  (UUID(), 'provider', '["create_episodes", "create_orders", "view_own_orders", "view_financial"]', TRUE),
  (UUID(), 'office_manager', '["create_orders", "manage_verifications", "view_facility_orders"]', TRUE),
  (UUID(), 'sales_rep', '["view_orders", "view_commissions", "manage_relationships"]', TRUE),
  (UUID(), 'facility_admin', '["manage_facility", "view_all_orders", "view_financial", "manage_users"]', TRUE);


Database Separation Strategy
FHIR Database (Azure Health Data Services)
Stores all PHI and clinical data:
Patient demographics (name, DOB, address)
Clinical observations (wound measurements, vitals)
Conditions (diagnoses)
Procedures
Practitioners (provider details)
Organizations (clinical aspects)
Business Logic Database (This Schema)
Stores operational and business data:
Episode management and workflow
Order processing and status
Verification tracking
Commission calculations
User access control
Document references (not content)
Financial data (with access control)
Key Integration Points
Patient References: Only store FHIR ID and display ID
Provider References: Store provider_fhir_id on users
Clinical Data: Store as FHIR references, not actual data
Documents: Store metadata only, actual files in blob storage
Why This Works for Other Healthcare Verticals
1. Surgical Hardware Sales
The episode model perfectly maps to surgical cases:
   Episode (Hip Replacement Surgery) → 
   Product Request (Surgeon identifies need for specific implant) → 
   Order (Hospital orders implant kit) →   
    Verifications (Insurance pre-auth, FDA tracking)

Key Benefits:
Episodes track the entire surgical case lifecycle
Product requests capture surgeon preferences
Verification system handles implant tracking requirements
Commission structure supports complex sales hierarchies
2. DME (Durable Medical Equipment)
Episodes represent ongoing patient needs:
Episode (COPD Management) → 
  Product Request (Oxygen concentrator needed) → 
  Order (DME supplier fulfillment) → 
  Verifications (Insurance, medical necessity)
Key Benefits:
Episodes can be long-running for chronic conditions
Supports rental vs. purchase models
Verification handles recurring eligibility checks
Compliance engine manages Medicare DME rules
3. Pharmaceutical Distribution
Episodes track treatment protocols:
Episode (Oncology Treatment) → 
  Product Request (Chemo regimen) → 
  Order (Specialty pharmacy) → 
  Verifications (Prior auth, REMS requirements)
Key Benefits:
Episodes group related medication orders
Verification handles complex PA requirements
Compliance manages controlled substance tracking
Supports both retail and specialty pharmacy





4. Surgical Supply Chain
  Episodes represent procedure planning:
  Episode (Scheduled Surgery) → 
  Product Request (OR supplies needed) → 
  Order (Multiple vendors) → 
  Verifications (Vendor credentials, product recalls)
Key Benefits:
Episodes coordinate multi-vendor orders
Product requests support preference cards
Verification ensures vendor compliance
Handles consignment inventory
The Power of Episode → Request → Order Flow
This three-step model provides crucial benefits:
Clinical Context: Episodes establish WHY products are needed
Flexibility: Requests can be informal/exploratory before formal orders
Compliance: Each step can have different validation rules
Traceability: Full audit trail from clinical need to delivery
Financial Control: Office managers can create requests/orders without seeing costs

