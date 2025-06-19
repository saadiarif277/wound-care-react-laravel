Universal Medical Distribution Platform - Database Schema
Core Design Principles

Multi-tenant capable: Support multiple distribution companies
Product agnostic: Works for devices, pharmaceuticals, biologics, supplies
Regulatory flexible: Handles various compliance requirements
Workflow adaptable: Supports different approval chains

sql-- =====================================================
-- TENANT & ORGANIZATION STRUCTURE
-- =====================================================

-- Multi-tenant support for different distribution companies
CREATE TABLE tenants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name VARCHAR(255) NOT NULL,
  subdomain VARCHAR(100) UNIQUE NOT NULL,
  settings JSONB DEFAULT '{}',
  features JSONB DEFAULT '{"products": true, "verifications": true, "commissions": true}',
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Companies using the platform (customers of the distribution company)
CREATE TABLE organizations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL, -- 'hospital', 'clinic', 'practice', 'pharmacy', 'home_health'
  tax_id VARCHAR(50) ENCRYPTED,
  status VARCHAR(50) DEFAULT 'active',
  onboarding_date DATE,
  contract_details JSONB,
  settings JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_org_tenant (tenant_id)
);

-- Locations/facilities for each organization
CREATE TABLE facilities (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id UUID NOT NULL REFERENCES organizations(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'main', 'satellite', 'warehouse', 'clinic'
  identifier VARCHAR(100), -- NPI, DEA, state license, etc.
  identifier_type VARCHAR(50), -- 'npi', 'dea', 'state_license'
  is_ship_to BOOLEAN DEFAULT true,
  is_bill_to BOOLEAN DEFAULT true,
  status VARCHAR(50) DEFAULT 'active',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_facility_org (organization_id)
);

-- =====================================================
-- USER MANAGEMENT
-- =====================================================

CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES tenants(id), -- NULL for super admins
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL, -- 'super_admin', 'tenant_admin', 'sales_rep', 'provider', 'facility_manager', 'viewer'
  is_active BOOLEAN DEFAULT true,
  email_verified_at TIMESTAMP WITH TIME ZONE,
  settings JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Link users to organizations/facilities
CREATE TABLE user_assignments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  assignable_type VARCHAR(50) NOT NULL, -- 'organization', 'facility'
  assignable_id UUID NOT NULL,
  role VARCHAR(50), -- 'admin', 'user', 'viewer'
  permissions JSONB DEFAULT '{}',
  is_primary BOOLEAN DEFAULT false,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(user_id, assignable_type, assignable_id)
);

-- =====================================================
-- PRODUCT CATALOG (Generic for any medical product)
-- =====================================================

CREATE TABLE product_categories (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  parent_category_id UUID REFERENCES product_categories(id),
  type VARCHAR(50), -- 'wound_care', 'orthopedic', 'pharmaceutical', 'dme', 'supplies'
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE manufacturers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'manufacturer', 'distributor', 'pharmacy', 'compounding'
  status VARCHAR(50) DEFAULT 'active',
  contact_info JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE products (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  manufacturer_id UUID REFERENCES manufacturers(id),
  category_id UUID REFERENCES product_categories(id),
  name VARCHAR(255) NOT NULL,
  sku VARCHAR(100),
  code VARCHAR(100), -- HCPCS, NDC, UPN, etc.
  code_type VARCHAR(50), -- 'hcpcs', 'ndc', 'upn', 'custom'
  type VARCHAR(50), -- 'device', 'drug', 'biologic', 'supply', 'equipment'
  description TEXT,
  specifications JSONB DEFAULT '{}', -- Size options, dosages, etc.
  pricing JSONB DEFAULT '{}', -- Complex pricing rules
  requires_prescription BOOLEAN DEFAULT false,
  requires_verification BOOLEAN DEFAULT false,
  is_active BOOLEAN DEFAULT true,
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_product_tenant (tenant_id),
  INDEX idx_product_code (code)
);

-- =====================================================
-- MANUFACTURER/SUPPLIER CONFIGURATION
-- =====================================================

CREATE TABLE manufacturer_settings (
  manufacturer_id UUID PRIMARY KEY REFERENCES manufacturers(id),
  -- Submission settings
  submission_method VARCHAR(50) DEFAULT 'email', -- 'email', 'api', 'portal', 'edi'
  submission_config JSONB DEFAULT '{}', -- API endpoints, email addresses, etc.
  primary_contacts TEXT[] DEFAULT '{}',
  cc_contacts TEXT[] DEFAULT '{}',
  
  -- Verification settings
  verification_required BOOLEAN DEFAULT false,
  verification_type VARCHAR(50), -- 'insurance', 'prior_auth', 'medical_necessity', 'license'
  verification_frequency VARCHAR(50), -- 'per_order', 'weekly', 'monthly', 'quarterly', 'annual'
  verification_documents JSONB DEFAULT '[]', -- Required document types
  
  -- Onboarding requirements
  onboarding_documents JSONB DEFAULT '[]', -- ['agreement', 'w9', 'license', 'insurance']
  requires_owner_signature BOOLEAN DEFAULT false,
  
  -- Order settings
  order_form_template_id UUID,
  minimum_order_amount DECIMAL(10,2),
  lead_time_days INTEGER DEFAULT 1,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- =====================================================
-- GENERIC VERIFICATION SYSTEM (Not just IVR)
-- =====================================================

-- Patient/Customer records (generic for any end recipient)
CREATE TABLE recipients (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  type VARCHAR(50) NOT NULL, -- 'patient', 'customer', 'facility'
  identifier VARCHAR(255) NOT NULL, -- Can be MRN, customer ID, etc.
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  full_name VARCHAR(255) GENERATED ALWAYS AS (first_name || ' ' || last_name) STORED,
  date_of_birth DATE,
  metadata JSONB DEFAULT '{}', -- Additional fields as needed
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_recipient_tenant (tenant_id),
  INDEX idx_recipient_identifier (identifier)
);

-- Generic verification tracking (insurance, licensing, etc.)
CREATE TABLE recipient_verifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  manufacturer_id UUID REFERENCES manufacturers(id),
  verification_type VARCHAR(50) NOT NULL, -- 'insurance', 'prior_auth', 'license', 'medical_necessity'
  status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'submitted', 'verified', 'expired', 'rejected'
  verified_date DATE,
  expiration_date DATE,
  verification_data JSONB DEFAULT '{}', -- Flexible data storage
  submitted_by UUID REFERENCES users(id),
  submitted_at TIMESTAMP WITH TIME ZONE,
  reviewed_by UUID REFERENCES users(id),
  reviewed_at TIMESTAMP WITH TIME ZONE,
  notes TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(recipient_id, manufacturer_id, verification_type),
  INDEX idx_verification_recipient (recipient_id),
  INDEX idx_verification_status (status),
  INDEX idx_verification_expiration (expiration_date)
);

-- =====================================================
-- PAYER/INSURANCE (Generic for any payment source)
-- =====================================================

CREATE TABLE payers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'insurance', 'government', 'direct_pay', 'employer'
  identifier VARCHAR(100), -- Payer ID
  contact_info JSONB DEFAULT '{}',
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE coverage_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  payer_id UUID REFERENCES payers(id),
  policy_number VARCHAR(255),
  group_number VARCHAR(255),
  is_primary BOOLEAN DEFAULT true,
  effective_date DATE,
  termination_date DATE,
  coverage_details JSONB DEFAULT '{}',
  subscriber_info JSONB DEFAULT '{}', -- If different from recipient
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- =====================================================
-- ORDERS (Flexible for any product type)
-- =====================================================

CREATE TABLE orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  order_number VARCHAR(100) UNIQUE NOT NULL,
  type VARCHAR(50) DEFAULT 'standard', -- 'standard', 'rush', 'consignment', 'trial'
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  
  -- Parties involved
  organization_id UUID NOT NULL REFERENCES organizations(id),
  facility_id UUID REFERENCES facilities(id),
  provider_id UUID REFERENCES users(id),
  recipient_id UUID REFERENCES recipients(id),
  
  -- Ashley's specific requests
  recipient_name VARCHAR(255), -- Denormalized for quick display
  service_date DATE,
  requested_delivery_date DATE NOT NULL,
  
  -- Flexible verification reference
  verification_ids UUID[] DEFAULT '{}', -- Array of recipient_verifications
  
  -- Documents
  form_documents JSONB DEFAULT '[]', -- Array of document references
  
  -- Status tracking
  provider_submitted_at TIMESTAMP WITH TIME ZONE,
  admin_reviewed_at TIMESTAMP WITH TIME ZONE,
  admin_reviewed_by UUID REFERENCES users(id),
  manufacturer_submitted_at TIMESTAMP WITH TIME ZONE,
  manufacturer_confirmed_at TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  notes TEXT,
  internal_notes TEXT,
  metadata JSONB DEFAULT '{}',
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_order_tenant (tenant_id),
  INDEX idx_order_status (status),
  INDEX idx_order_dates (service_date, requested_delivery_date)
);

CREATE TABLE order_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES products(id),
  quantity INTEGER NOT NULL,
  unit_price DECIMAL(10,2),
  specifications JSONB DEFAULT '{}', -- Size, color, configuration, etc.
  notes TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- =====================================================
-- SHIPPING & TRACKING
-- =====================================================

CREATE TABLE shipments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  tracking_number VARCHAR(255),
  carrier VARCHAR(100),
  shipped_date TIMESTAMP WITH TIME ZONE,
  delivered_date TIMESTAMP WITH TIME ZONE,
  shipping_documents JSONB DEFAULT '[]',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_shipment_order (order_id),
  INDEX idx_shipment_tracking (tracking_number)
);

-- =====================================================
-- DOCUMENT MANAGEMENT
-- =====================================================

CREATE TABLE document_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'order_form', 'verification_form', 'agreement', 'invoice'
  category VARCHAR(50), -- 'manufacturer_specific', 'payer_specific', 'general'
  entity_id UUID, -- References manufacturer_id, payer_id, etc.
  template_data JSONB NOT NULL, -- Form fields, layout, etc.
  version VARCHAR(50),
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  type VARCHAR(50) NOT NULL, -- 'order_form', 'verification', 'confirmation', 'agreement'
  status VARCHAR(50) DEFAULT 'draft', -- 'draft', 'pending_signature', 'signed', 'submitted'
  
  -- Polymorphic association
  documentable_type VARCHAR(50) NOT NULL, -- 'order', 'verification', 'recipient'
  documentable_id UUID NOT NULL,
  
  -- Document details
  template_id UUID REFERENCES document_templates(id),
  form_data JSONB DEFAULT '{}',
  file_url VARCHAR(500),
  file_metadata JSONB DEFAULT '{}',
  
  -- Signatures
  requires_signature BOOLEAN DEFAULT false,
  signatures JSONB DEFAULT '[]', -- Array of signature records
  
  -- Tracking
  created_by UUID REFERENCES users(id),
  submitted_at TIMESTAMP WITH TIME ZONE,
  submitted_to JSONB DEFAULT '{}', -- Email addresses, API endpoints, etc.
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_document_type (documentable_type, documentable_id)
);

-- =====================================================
-- COMMISSION & SALES TRACKING
-- =====================================================

CREATE TABLE commission_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'product', 'category', 'manufacturer', 'volume'
  target_id UUID, -- Product, category, or manufacturer ID
  calculation_type VARCHAR(50), -- 'percentage', 'flat_rate', 'tiered'
  rate_data JSONB NOT NULL, -- Flexible rate structure
  conditions JSONB DEFAULT '{}', -- Additional conditions
  valid_from DATE,
  valid_to DATE,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE sales_reps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  employee_id VARCHAR(100),
  territory JSONB DEFAULT '{}',
  commission_rules JSONB DEFAULT '[]', -- Override rules
  parent_rep_id UUID REFERENCES sales_reps(id), -- For hierarchical structures
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE commissions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  order_id UUID NOT NULL REFERENCES orders(id),
  sales_rep_id UUID NOT NULL REFERENCES sales_reps(id),
  rule_id UUID REFERENCES commission_rules(id),
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'paid'
  calculation_details JSONB DEFAULT '{}',
  period_start DATE,
  period_end DATE,
  paid_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- =====================================================
-- NOTIFICATIONS & ACTIVITY TRACKING
-- =====================================================

CREATE TABLE notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  user_id UUID NOT NULL REFERENCES users(id),
  type VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT,
  data JSONB DEFAULT '{}',
  read_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_notification_user (user_id, read_at)
);

CREATE TABLE activity_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  user_id UUID REFERENCES users(id),
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(50),
  entity_id UUID,
  changes JSONB DEFAULT '{}',
  metadata JSONB DEFAULT '{}',
  ip_address INET,
  user_agent TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  INDEX idx_activity_entity (entity_type, entity_id),
  INDEX idx_activity_user (user_id),
  INDEX idx_activity_created (created_at)
);

-- =====================================================
-- SETTINGS & CONFIGURATION
-- =====================================================

CREATE TABLE settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES tenants(id), -- NULL for system-wide
  scope VARCHAR(50) NOT NULL, -- 'system', 'tenant', 'organization', 'user'
  scope_id UUID, -- ID of the scoped entity
  key VARCHAR(255) NOT NULL,
  value JSONB NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(tenant_id, scope, scope_id, key)
);

-- =====================================================
-- HELPER VIEWS
-- =====================================================

-- Ashley's main dashboard view
CREATE OR REPLACE VIEW admin_order_queue AS
SELECT
  o.id,
  o.order_number,
  o.recipient_name as patient_name,
  u.full_name as provider_name,
  f.name as facility_name,
  o.service_date,
  o.requested_delivery_date,
  o.status,
  o.provider_submitted_at,
  CASE
    WHEN o.status = 'pending_admin_review' THEN 'Review'
    WHEN o.status = 'submitted_to_manufacturer' THEN 'Track'
    ELSE 'View'
  END as action,
  -- Check if verifications are valid
  EXISTS (
    SELECT 1 FROM recipient_verifications rv
    WHERE rv.recipient_id = o.recipient_id
    AND rv.status = 'verified'
    AND (rv.expiration_date IS NULL OR rv.expiration_date > CURRENT_DATE)
  ) as has_valid_verification
FROM orders o
JOIN users u ON o.provider_id = u.id
LEFT JOIN facilities f ON o.facility_id = f.id
WHERE o.status IN ('pending_admin_review', 'submitted_to_manufacturer', 'shipped')
ORDER BY o.provider_submitted_at DESC;

-- Patient verification status view
CREATE OR REPLACE VIEW recipient_verification_status AS
SELECT
  rv.*,
  m.name as manufacturer_name,
  ms.verification_frequency,
  r.full_name as recipient_name,
  CASE
    WHEN rv.expiration_date < CURRENT_DATE THEN 'expired'
    WHEN rv.expiration_date < CURRENT_DATE + INTERVAL '7 days' THEN 'expiring_soon'
    ELSE rv.status
  END as effective_status
FROM recipient_verifications rv
JOIN manufacturers m ON rv.manufacturer_id = m.id
JOIN manufacturer_settings ms ON m.id = ms.manufacturer_id
JOIN recipients r ON rv.recipient_id = r.id;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_orders_tenant_status ON orders(tenant_id, status);
CREATE INDEX idx_documents_pending ON documents(status) WHERE status IN ('draft', 'pending_signature');
CREATE INDEX idx_verifications_expiring ON recipient_verifications(expiration_date)
  WHERE status = 'verified' AND expiration_date IS NOT NULL;
CREATE INDEX idx_shipments_pending ON shipments(order_id) WHERE delivered_date IS NULL;

-- =====================================================
-- ROW LEVEL SECURITY (Example for multi-tenant)
-- =====================================================

ALTER TABLE orders ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON orders
  FOR ALL
  USING (tenant_id = current_setting('app.current_tenant_id')::uuid);

-- Apply similar RLS policies to all tenant-scoped tables
Key Design Features

1. Multi-Tenant Architecture

Supports multiple medical distribution companies
Each tenant has isolated data with RLS
Configurable features per tenant

2. Flexible Product Support

Works for any medical product type (devices, drugs, supplies)
Configurable code types (HCPCS, NDC, custom)
Flexible pricing and specifications

3. Generic Verification System

Not limited to insurance verification
Supports any verification type (licensing, prior auth, etc.)
Tracks at recipient + manufacturer level (per Ashley's requirement)

4. Document Management

Template-based forms
Polymorphic associations for flexibility
Full signature tracking

5. Workflow Adaptability

Status-driven workflows
Configurable per manufacturer
Supports Ashley's review-and-forward pattern

6. Universal Recipient Model

Works for patients, customers, or facilities
Flexible metadata storage
No PHI assumptions

7. Commission Flexibility

Multiple calculation types
Hierarchical sales structures
Rule-based system

Migration from Current Schema
sql-- Example migration helpers
INSERT INTO recipients (tenant_id, type, identifier, first_name, last_name, metadata)
SELECT
  '00000000-0000-0000-0000-000000000001' as tenant_id,
  'patient' as type,
  patient_fhir_id as identifier,
  split_part(patient_name, ' ', 1) as first_name,
  split_part(patient_name, ' ', 2) as last_name,
  jsonb_build_object('migrated_from', 'wound_care_system')
FROM old_orders;
This schema is designed to be:

Industry agnostic: Works for any medical distribution
Scalable: Handles growth through proper indexing
Flexible: JSONB fields for custom requirements
Secure: RLS ready for multi-tenant isolation
Ashley-friendly: Maintains her workflow patterns
RetryRVnow looking at our other project documents any changes youd add to the above schemaEditScrutinized schema gaps, proposed comprehensive medical data enhancements.17sLooking at the project documents, I need to identify what's missing from the schema I just created. Let me go through the key documents:

NON_PHI_DATA_MODELS.md - This has detailed data models for the current system
PHI_DATA_MODELS.md - Shows FHIR integration patterns
Order_Approval_Workflow_No_PHI.md - Details Ashley's workflow
MAC Validation Engine - Medicare compliance rules
Clinical Opportunity Engine - Revenue optimization
Eligibility & Pre-Authorization Engine - Payer API integrations
Product Recommendation Engine - Smart product suggestions
Commission Engine - Complex commission calculations
Enhanced Order Flow - Detailed field requirements

Key things I notice that are missing or need enhancement:

MAC Validation & Compliance Rules - The schema needs tables for LCD/NCD rules, MAC jurisdictions, validation rules
Clinical Opportunity Engine - Need tables for opportunity rules and tracking
External API Integration Tracking - For Optum, Availity, Office Ally integrations
Product Recommendation Rules - Smart recommendation engine
Eligibility & Prior Auth Tracking - More detailed than just verifications
FHIR References - The schema should maintain FHIR ID references
Wound-Specific Fields - While generic, we need to support wound measurements, etc.
Care Reminders - From Availity integration
DocuSeal Integration - Specific tracking for DocuSeal
Vector Embeddings - For RAG/AI features
Commission Hierarchy - More complex than current schema
Audit Trail - More comprehensive than activity_logs

Let me enhance the schema with these missing pieces while keeping it generic enough for any medical distribution company.Looking at the project documents, here are the critical additions needed to make the schema complete:
🔧 Enhanced Schema Additions

1. Compliance & Validation Engine Tables
sql-- =====================================================
-- COMPLIANCE & MAC VALIDATION (Generic for any regulatory body)
-- =====================================================

-- Regulatory jurisdictions (MAC, state boards, etc.)
CREATE TABLE regulatory_jurisdictions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  type VARCHAR(50) NOT NULL, -- 'mac', 'state_board', 'dea', 'fda'
  code VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  coverage_area JSONB DEFAULT '{}', -- ZIP codes, states, regions
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(tenant_id, type, code)
);

-- Coverage and validation rules
CREATE TABLE compliance_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_code VARCHAR(100) UNIQUE NOT NULL,
  rule_type VARCHAR(50) NOT NULL, -- 'coverage', 'documentation', 'clinical', 'frequency'
  name VARCHAR(255) NOT NULL,
  description TEXT,
  
  -- Conditions
  applies_to_products UUID[] DEFAULT '{}',
  applies_to_codes VARCHAR[] DEFAULT '{}', -- CPT, HCPCS, ICD-10
  jurisdiction_ids UUID[] DEFAULT '{}',
  
  -- Rule logic
  rule_logic JSONB NOT NULL, -- Complex conditional logic
  required_documentation JSONB DEFAULT '[]',
  
  -- Metadata
  severity VARCHAR(20) DEFAULT 'warning', -- 'error', 'warning', 'info'
  effective_date DATE,
  expiration_date DATE,
  source_policy VARCHAR(255), -- LCD/NCD reference
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_compliance_rules_products (applies_to_products),
  INDEX idx_compliance_rules_codes (applies_to_codes)
);

-- Validation results for orders
CREATE TABLE order_validations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  validation_type VARCHAR(50) NOT NULL, -- 'mac', 'payer', 'clinical'
  status VARCHAR(50) NOT NULL, -- 'passed', 'passed_with_warnings', 'failed'
  
  -- Results
  applied_rules JSONB DEFAULT '[]',
  errors JSONB DEFAULT '[]',
  warnings JSONB DEFAULT '[]',
  
  -- Context
  jurisdiction_id UUID REFERENCES regulatory_jurisdictions(id),
  validated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  validated_by UUID REFERENCES users(id),
  
  INDEX idx_order_validation (order_id, validation_type)
);
2. Clinical Decision Support & Opportunities
sql-- =====================================================
-- CLINICAL OPPORTUNITY ENGINE (Revenue optimization)
-- =====================================================

CREATE TABLE opportunity_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_name VARCHAR(255) NOT NULL,
  rule_type VARCHAR(50), -- 'additional_service', 'product_upgrade', 'documentation_improvement'
  
  -- Conditions
  clinical_criteria JSONB DEFAULT '{}', -- Diagnosis codes, clinical indicators
  product_criteria JSONB DEFAULT '{}', -- Current products, quantities
  payer_criteria JSONB DEFAULT '{}', -- Payer types, coverage
  
  -- Opportunity details
  suggested_action VARCHAR(255) NOT NULL,
  suggested_codes VARCHAR[] DEFAULT '{}', -- CPT, HCPCS codes
  estimated_value DECIMAL(10,2),
  clinical_benefit TEXT,
  documentation_guidance TEXT,
  
  -- Tracking
  confidence_score DECIMAL(3,2), -- 0.00 to 1.00
  success_rate DECIMAL(3,2), -- Historical success
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track presented opportunities
CREATE TABLE opportunity_presentations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  rule_id UUID NOT NULL REFERENCES opportunity_rules(id),
  presented_to UUID REFERENCES users(id),
  presented_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  action_taken VARCHAR(50), -- 'accepted', 'dismissed', 'deferred'
  action_at TIMESTAMP WITH TIME ZONE,
  action_by UUID REFERENCES users(id),
  notes TEXT,
  resulted_in_order_id UUID REFERENCES orders(id),
  
  INDEX idx_opportunity_order (order_id),
  INDEX idx_opportunity_action (action_taken)
);

-- Machine learning patterns (for future opportunities)
CREATE TABLE opportunity_patterns (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  pattern_hash VARCHAR(64) NOT NULL, -- Hash of pattern criteria
  pattern_data JSONB NOT NULL,
  frequency INTEGER DEFAULT 1,
  first_seen TIMESTAMP WITH TIME ZONE DEFAULT now(),
  last_seen TIMESTAMP WITH TIME ZONE DEFAULT now(),
  status VARCHAR(50) DEFAULT 'identified', -- 'identified', 'reviewed', 'converted_to_rule'
  
  UNIQUE(tenant_id, pattern_hash)
);
3. External API Integration Management
sql-- =====================================================
-- EXTERNAL API INTEGRATIONS (Payers, Eligibility, etc.)
-- =====================================================

CREATE TABLE api_configurations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  provider_name VARCHAR(100) NOT NULL, -- 'optum', 'availity', 'office_ally', 'custom'
  api_type VARCHAR(50) NOT NULL, -- 'eligibility', 'prior_auth', 'claims', 'remittance'
  
  -- Configuration
  base_url VARCHAR(500) NOT NULL,
  auth_method VARCHAR(50), -- 'oauth2', 'api_key', 'basic', 'custom'
  credentials JSONB ENCRYPTED, -- Encrypted credentials
  
  -- Routing rules
  supported_payers UUID[] DEFAULT '{}', -- Which payers this API supports
  priority INTEGER DEFAULT 100, -- For fallback routing
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track all API transactions
CREATE TABLE api_transactions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  api_config_id UUID REFERENCES api_configurations(id),
  
  -- Request details
  transaction_type VARCHAR(50) NOT NULL, -- 'eligibility_check', 'prior_auth_submit', etc.
  entity_type VARCHAR(50), -- 'order', 'recipient', 'coverage'
  entity_id UUID,
  
  -- API details
  request_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
  response_timestamp TIMESTAMP WITH TIME ZONE,
  status_code INTEGER,
  
  -- Tracking
  external_transaction_id VARCHAR(255), -- Their reference number
  request_hash VARCHAR(64), -- For deduplication
  response_hash VARCHAR(64),
  
  -- Storage references (not actual data)
  request_storage_path VARCHAR(500),
  response_storage_path VARCHAR(500),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_api_transaction_entity (entity_type, entity_id),
  INDEX idx_api_transaction_external (external_transaction_id)
);

-- Care reminders from payers
CREATE TABLE care_reminders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  source_api VARCHAR(100), -- 'availity', 'optum', etc.
  external_reminder_id VARCHAR(255),
  
  reminder_type VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  due_date DATE,
  
  status VARCHAR(50) DEFAULT 'active', -- 'active', 'addressed', 'dismissed'
  addressed_by UUID REFERENCES users(id),
  addressed_at TIMESTAMP WITH TIME ZONE,
  action_taken TEXT,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_care_reminder_recipient (recipient_id, status)
);
4. Enhanced Eligibility & Prior Authorization
sql-- =====================================================
-- ELIGIBILITY & PRIOR AUTHORIZATION
-- =====================================================

-- Detailed eligibility results (beyond basic verification)
CREATE TABLE eligibility_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID REFERENCES orders(id),
  coverage_policy_id UUID REFERENCES coverage_policies(id),
  
  -- Check details
  check_type VARCHAR(50) DEFAULT 'standard', -- 'standard', 'detailed', 'benefits'
  api_transaction_id UUID REFERENCES api_transactions(id),
  
  -- Results
  status VARCHAR(50) NOT NULL, -- 'eligible', 'not_eligible', 'pending', 'error'
  coverage_active BOOLEAN,
  
  -- Benefits summary (no PHI)
  benefits_summary JSONB DEFAULT '{}', -- Copay, deductible, limits
  
  -- Prior auth determination
  prior_auth_required VARCHAR(50), -- 'required', 'not_required', 'conditional'
  prior_auth_reasons JSONB DEFAULT '[]',
  
  checked_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  valid_until TIMESTAMP WITH TIME ZONE,
  
  INDEX idx_eligibility_order (order_id)
);

-- Prior authorization tracking
CREATE TABLE prior_authorizations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  eligibility_check_id UUID REFERENCES eligibility_checks(id),
  
  -- PA details
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  auth_number VARCHAR(255),
  
  -- Submission tracking
  submitted_at TIMESTAMP WITH TIME ZONE,
  submitted_via UUID REFERENCES api_configurations(id),
  submission_reference VARCHAR(255),
  
  -- Response
  determination VARCHAR(50), -- 'approved', 'denied', 'partial', 'pending'
  effective_date DATE,
  expiration_date DATE,
  approved_units INTEGER,
  denial_reasons JSONB DEFAULT '[]',
  
  -- Documents
  supporting_documents JSONB DEFAULT '[]',
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_prior_auth_order (order_id),
  INDEX idx_prior_auth_status (status)
);
5. Product Intelligence & Recommendations
sql-- =====================================================
-- PRODUCT RECOMMENDATION ENGINE
-- =====================================================

CREATE TABLE recommendation_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_name VARCHAR(255) NOT NULL,
  
  -- Conditions
  clinical_criteria JSONB DEFAULT '{}', -- Wound type, size, duration, etc.
  patient_criteria JSONB DEFAULT '{}', -- Age, comorbidities
  treatment_history JSONB DEFAULT '{}', -- Previous products, outcomes
  
  -- Recommendations
  recommended_products JSONB NOT NULL, -- Ranked list with reasoning
  
  -- Sizing logic
  size_calculation_method VARCHAR(100), -- 'match_wound', 'standard', 'custom'
  size_logic JSONB DEFAULT '{}',
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track recommendation effectiveness
CREATE TABLE recommendation_tracking (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  rule_id UUID REFERENCES recommendation_rules(id),
  
  products_recommended JSONB NOT NULL,
  products_selected JSONB NOT NULL,
  
  recommendation_score DECIMAL(3,2), -- Confidence score
  was_followed BOOLEAN,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
6. AI/RAG Support Tables
sql-- =====================================================
-- AI & KNOWLEDGE BASE (For MSC Assist)
-- =====================================================

-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Knowledge base documents
CREATE TABLE knowledge_documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  source_type VARCHAR(50) NOT NULL, -- 'product_info', 'clinical_guideline', 'policy', 'faq'
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  
  -- Metadata
  source_id UUID, -- Reference to product, rule, etc.
  url VARCHAR(500),
  
  is_active BOOLEAN DEFAULT true,
  last_indexed TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Vector embeddings for semantic search
CREATE TABLE knowledge_embeddings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  document_id UUID NOT NULL REFERENCES knowledge_documents(id),
  chunk_index INTEGER NOT NULL,
  chunk_text TEXT NOT NULL,
  embedding vector(1536), -- OpenAI embedding size
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(document_id, chunk_index)
);

-- Create index for similarity search
CREATE INDEX knowledge_embeddings_vector_idx ON knowledge_embeddings
USING ivfflat (embedding vector_cosine_ops);
7. Clinical Data Extensions
sql-- =====================================================
-- CLINICAL DATA SUPPORT (Flexible for wound care, etc.)
-- =====================================================

-- Clinical assessments (wound measurements, etc.)
CREATE TABLE clinical_assessments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  assessment_type VARCHAR(50) NOT NULL, -- 'wound', 'mobility', 'pain', etc.
  
  -- Flexible measurement storage
  measurements JSONB NOT NULL, -- {length: 4.5, width: 3.2, depth: 0.8}
  
  -- Clinical observations
  observations JSONB DEFAULT '{}', -- {tissue_type: 'granulation', drainage: 'minimal'}
  
  -- Images/documents
  attachments JSONB DEFAULT '[]',
  
  assessed_by UUID REFERENCES users(id),
  assessed_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Treatment history
CREATE TABLE treatment_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  order_id UUID REFERENCES orders(id),
  
  treatment_type VARCHAR(100) NOT NULL,
  treatment_details JSONB NOT NULL,
  
  start_date DATE NOT NULL,
  end_date DATE,
  
  outcome VARCHAR(50), -- 'effective', 'ineffective', 'adverse_reaction'
  outcome_details TEXT,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_treatment_recipient (recipient_id)
);
8. Enhanced Document Management for DocuSeal
sql-- =====================================================
-- DOCUSEAL INTEGRATION ENHANCEMENTS
-- =====================================================

ALTER TABLE documents ADD COLUMN IF NOT EXISTS
  docuseal_submission_id VARCHAR(255),
  docuseal_template_id VARCHAR(255),
  docuseal_status VARCHAR(50), -- DocuSeal-specific status
  field_mappings JSONB DEFAULT '{}', -- How fields map
  signature_request_sent_at TIMESTAMP WITH TIME ZONE,
  signature_completed_at TIMESTAMP WITH TIME ZONE;

CREATE INDEX idx_documents_docuseal ON documents(docuseal_submission_id)
  WHERE docuseal_submission_id IS NOT NULL;
9. Enhanced Audit Trail
sql-- =====================================================
-- COMPREHENSIVE AUDIT TRAIL
-- =====================================================

-- PHI access logging (references only)
CREATE TABLE phi_access_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  user_id UUID NOT NULL REFERENCES users(id),
  
  -- What was accessed
  resource_type VARCHAR(50) NOT NULL, -- 'patient', 'clinical_note', etc.
  resource_identifier VARCHAR(255) NOT NULL, -- FHIR ID or other reference
  
  -- Access details
  action VARCHAR(50) NOT NULL, -- 'view', 'create', 'update', 'delete'
  reason VARCHAR(255) NOT NULL, -- Business justification
  
  -- Context
  ip_address INET,
  session_id VARCHAR(255),
  
  accessed_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_phi_access_user (user_id),
  INDEX idx_phi_access_resource (resource_type, resource_identifier)
);
10. Platform-Specific Settings
sql-- =====================================================
-- ASHLEY'S WORKFLOW PREFERENCES
-- =====================================================

-- Quick access to manufacturer email lists
CREATE TABLE manufacturer_contacts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  manufacturer_id UUID NOT NULL REFERENCES manufacturers(id),
  contact_type VARCHAR(50) NOT NULL, -- 'order', 'ivr', 'support'
  
  primary_recipients TEXT[] NOT NULL DEFAULT '{}',
  cc_recipients TEXT[] DEFAULT '{}',
  
  notes TEXT, -- "Susan for rush orders, Mark for standard"
  
  updated_by UUID REFERENCES users(id),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(manufacturer_id, contact_type)
);

-- Manufacturer request tracking (when they ask for bulk IVR updates)
CREATE TABLE manufacturer_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  manufacturer_id UUID NOT NULL REFERENCES manufacturers(id),
  request_type VARCHAR(50) NOT NULL, -- 'bulk_ivr_update', 'form_change'
  
  requested_via VARCHAR(50), -- 'email', 'text', 'phone'
  requested_at TIMESTAMP WITH TIME ZONE NOT NULL,
  
  details TEXT,
  
  status VARCHAR(50) DEFAULT 'pending',
  completed_at TIMESTAMP WITH TIME ZONE,
  
  created_by UUID REFERENCES users(id),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
Key Enhancements Summary

Compliance Engine: Full MAC validation and regulatory rules
Clinical Intelligence: Opportunity identification and tracking
API Management: Comprehensive external integration tracking
Enhanced Prior Auth: Detailed PA workflow support
AI/RAG Support: Vector embeddings for intelligent assistance
Clinical Flexibility: Generic assessment and treatment tracking
Ashley's Workflows: Specific tables for her coordination needs

This enhanced schema maintains the generic, multi-tenant nature while incorporating all the sophisticated features from your existing platform design.

I'll examine the directory structure to identify any additional components or features that might need database support.Based on the project documents provided, here are the critical components missing from the schema that would make it truly comprehensive:

## 🚨 Critical Missing Components

### 1. **FHIR Reference Management**

```sql
-- =====================================================
-- FHIR INTEGRATION & REFERENCE TRACKING
-- =====================================================

-- Track all FHIR references (maintaining PHI separation)
CREATE TABLE fhir_references (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  -- Local reference
  entity_type VARCHAR(50) NOT NULL, -- 'recipient', 'order', 'clinical_assessment'
  entity_id UUID NOT NULL,
  
  -- FHIR reference
  fhir_resource_type VARCHAR(50) NOT NULL, -- 'Patient', 'Coverage', 'DocumentReference'
  fhir_resource_id VARCHAR(255) NOT NULL, -- The FHIR ID
  fhir_server_url VARCHAR(500), -- Which FHIR server
  
  -- Tracking
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  last_accessed TIMESTAMP WITH TIME ZONE,
  
  UNIQUE(entity_type, entity_id, fhir_resource_type),
  INDEX idx_fhir_ref_entity (entity_type, entity_id)
);

-- SMART on FHIR session management
CREATE TABLE smart_sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  
  -- Launch context
  iss VARCHAR(500) NOT NULL, -- FHIR server URL
  launch_token VARCHAR(500),
  
  -- Tokens
  access_token TEXT ENCRYPTED,
  refresh_token TEXT ENCRYPTED,
  id_token TEXT ENCRYPTED,
  
  -- Context
  patient_id VARCHAR(255), -- FHIR patient ID
  encounter_id VARCHAR(255),
  practitioner_id VARCHAR(255),
  
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_smart_session_user (user_id),
  INDEX idx_smart_session_expires (expires_at)
);
```

### 2. **Specialty-Specific Configuration**

```sql
-- =====================================================
-- SPECIALTY MANAGEMENT (Wound, Vascular, etc.)
-- =====================================================

CREATE TABLE specialties (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  code VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  
  -- Access control
  restricted_access BOOLEAN DEFAULT false, -- e.g., vascular requires vascular specialist
  
  -- Specialty-specific settings
  validation_rules JSONB DEFAULT '{}',
  form_templates JSONB DEFAULT '{}',
  workflow_overrides JSONB DEFAULT '{}',
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(tenant_id, code)
);

-- Link products to specialties
CREATE TABLE product_specialties (
  product_id UUID NOT NULL REFERENCES products(id),
  specialty_id UUID NOT NULL REFERENCES specialties(id),
  is_primary BOOLEAN DEFAULT false,
  PRIMARY KEY (product_id, specialty_id)
);

-- User specialty credentials
CREATE TABLE user_specialties (
  user_id UUID NOT NULL REFERENCES users(id),
  specialty_id UUID NOT NULL REFERENCES specialties(id),
  credential_type VARCHAR(100), -- 'board_certified', 'fellowship', etc.
  credential_number VARCHAR(255),
  verified_at TIMESTAMP WITH TIME ZONE,
  expires_at TIMESTAMP WITH TIME ZONE,
  PRIMARY KEY (user_id, specialty_id)
);
```

### 3. **EHR Integration Tracking**

```sql
-- =====================================================
-- EHR INTEGRATIONS (eClinicalWorks, Epic, etc.)
-- =====================================================

CREATE TABLE ehr_connections (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id UUID NOT NULL REFERENCES organizations(id),
  
  ehr_system VARCHAR(50) NOT NULL, -- 'eclinicalworks', 'epic', 'cerner'
  connection_type VARCHAR(50), -- 'fhir', 'api', 'hl7'
  
  -- Connection details
  base_url VARCHAR(500),
  credentials JSONB ENCRYPTED,
  
  -- Sync settings
  sync_enabled BOOLEAN DEFAULT true,
  sync_direction VARCHAR(50) DEFAULT 'bidirectional', -- 'inbound', 'outbound', 'bidirectional'
  sync_frequency VARCHAR(50), -- 'realtime', 'hourly', 'daily'
  last_sync_at TIMESTAMP WITH TIME ZONE,
  
  -- Field mappings
  field_mappings JSONB DEFAULT '{}',
  
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track EHR sync events
CREATE TABLE ehr_sync_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  connection_id UUID NOT NULL REFERENCES ehr_connections(id),
  
  sync_type VARCHAR(50) NOT NULL, -- 'patient', 'order', 'result'
  direction VARCHAR(50) NOT NULL, -- 'inbound', 'outbound'
  
  records_processed INTEGER DEFAULT 0,
  records_failed INTEGER DEFAULT 0,
  
  started_at TIMESTAMP WITH TIME ZONE NOT NULL,
  completed_at TIMESTAMP WITH TIME ZONE,
  
  error_details JSONB DEFAULT '[]',
  
  INDEX idx_ehr_sync_connection (connection_id)
);
```

### 4. **Enhanced Order Flow Fields**

```sql
-- =====================================================
-- ENHANCED ORDER DETAILS (From 90-second flow)
-- =====================================================

-- Add to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS
  -- Request context
  request_type VARCHAR(50) DEFAULT 'new_request', -- 'new_request', 'reverification', 'additional_applications'
  
  -- Shipping details
  shipping_speed VARCHAR(50), -- '1st_am', 'early_next', 'standard_next', 'standard_2day'
  actual_delivery_date DATE,
  delivery_instructions TEXT,
  
  -- Clinical context
  wound_measurements JSONB, -- {length: 4.2, width: 3.1, depth: 0.8, total_area: 13.02}
  previous_treatments JSONB DEFAULT '[]',
  treatment_duration_weeks INTEGER,
  
  -- Billing context
  place_of_service VARCHAR(10), -- '11', '22', '31', etc.
  snf_status BOOLEAN DEFAULT false,
  snf_days INTEGER,
  hospice_status BOOLEAN DEFAULT false,
  part_a_status BOOLEAN DEFAULT false,
  global_period_status BOOLEAN DEFAULT false,
  global_period_cpt VARCHAR(10),
  global_period_surgery_date DATE,
  
  -- Payer details
  subscriber_name VARCHAR(255), -- If different from patient
  subscriber_dob DATE,
  subscriber_relationship VARCHAR(50),
  payer_phone VARCHAR(50),
  plan_type VARCHAR(50), -- 'ffs', 'hmo', 'ppo'
  network_status VARCHAR(50), -- 'in_network', 'out_of_network'
  
  -- Medicare specific
  provider_ptan VARCHAR(50),
  facility_ptan VARCHAR(50),
  mac_jurisdiction VARCHAR(10);
```

### 5. **Cost Estimation & Financial**

```sql
-- =====================================================
-- COST ESTIMATION & PATIENT RESPONSIBILITY
-- =====================================================

CREATE TABLE cost_estimates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  
  -- Estimate details
  total_charges DECIMAL(10,2),
  allowed_amount DECIMAL(10,2),
  
  -- Patient responsibility
  deductible_amount DECIMAL(10,2),
  copay_amount DECIMAL(10,2),
  coinsurance_amount DECIMAL(10,2),
  patient_responsibility_total DECIMAL(10,2),
  
  -- Payer responsibility
  payer_responsibility DECIMAL(10,2),
  
  -- Estimate metadata
  estimate_valid_until TIMESTAMP WITH TIME ZONE,
  confidence_level VARCHAR(50), -- 'high', 'medium', 'low'
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_cost_estimate_order (order_id)
);
```

### 6. **Quick Actions & Shortcuts (Ashley's Workflow)**

```sql
-- =====================================================
-- USER WORKFLOW OPTIMIZATIONS
-- =====================================================

-- Quick action templates
CREATE TABLE quick_actions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  
  action_type VARCHAR(50) NOT NULL, -- 'email_template', 'bulk_action', 'saved_filter'
  name VARCHAR(255) NOT NULL,
  
  -- Action configuration
  config JSONB NOT NULL, -- Template content, filter criteria, etc.
  
  -- Usage tracking
  usage_count INTEGER DEFAULT 0,
  last_used TIMESTAMP WITH TIME ZONE,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Saved searches/filters
CREATE TABLE saved_filters (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  
  entity_type VARCHAR(50) NOT NULL, -- 'orders', 'verifications', 'patients'
  name VARCHAR(255) NOT NULL,
  
  filter_criteria JSONB NOT NULL,
  sort_order JSONB DEFAULT '{}',
  
  is_default BOOLEAN DEFAULT false,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
```

### 7. **Analytics & Reporting Foundation**

```sql
-- =====================================================
-- ANALYTICS & METRICS
-- =====================================================

-- Aggregated metrics (pre-calculated for dashboards)
CREATE TABLE metrics_snapshots (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  metric_type VARCHAR(100) NOT NULL, -- 'daily_orders', 'verification_rate', 'commission_summary'
  metric_date DATE NOT NULL,
  
  -- Dimensions
  dimensions JSONB DEFAULT '{}', -- {facility_id: 'xxx', product_category: 'wound_care'}
  
  -- Values
  values JSONB NOT NULL, -- {count: 45, total_value: 12500.00, avg_time: 3.5}
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(tenant_id, metric_type, metric_date, dimensions)
);

-- User activity tracking for analytics
CREATE TABLE user_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  user_id UUID NOT NULL REFERENCES users(id),
  
  event_type VARCHAR(100) NOT NULL, -- 'order_created', 'verification_completed', etc.
  event_data JSONB DEFAULT '{}',
  
  -- Performance metrics
  duration_ms INTEGER, -- How long the action took
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_user_events_type (event_type, created_at)
);
```

### 8. **Integration Queue Management**

```sql
-- =====================================================
-- ASYNC PROCESSING & QUEUES
-- =====================================================

CREATE TABLE integration_queue (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  queue_type VARCHAR(50) NOT NULL, -- 'outbound_order', 'eligibility_check', 'document_generate'
  priority INTEGER DEFAULT 100,
  
  -- Payload
  entity_type VARCHAR(50),
  entity_id UUID,
  payload JSONB NOT NULL,
  
  -- Processing
  status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
  attempts INTEGER DEFAULT 0,
  max_attempts INTEGER DEFAULT 3,
  
  -- Scheduling
  process_after TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  -- Results
  processed_at TIMESTAMP WITH TIME ZONE,
  error_message TEXT,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_queue_status_priority (status, priority, process_after)
);
```

## 💡 Three Thought-Provoking Questions

1. **Multi-Modal Integration Strategy**: Given that medical distributors often need to integrate with dozens of different systems (EHRs, payers, manufacturers), should we implement a plugin architecture where each integration is a self-contained module with its own schema extensions? This could allow rapid onboarding of new partners without core schema changes.

2. **AI-Driven Optimization**: With the comprehensive data we're collecting (validation patterns, approval rates, shipping times), how could we implement predictive analytics to automatically suggest workflow optimizations? For example, identifying which verification types could be batched for certain manufacturers or predicting which orders are likely to face delays?

3. **Regulatory Compliance Evolution**: As Medicare moves toward value-based care and new billing models emerge, how should we design the schema to be "regulation-agnostic" while still supporting complex compliance rules? Should we consider a rules engine that can interpret regulatory documents directly and generate validation logic?

Looking at the project documents, here are the critical additions needed to make the schema complete:

## 🔧 Enhanced Schema Additions

### 1. **Compliance & Validation Engine Tables**

```sql
-- =====================================================
-- COMPLIANCE & MAC VALIDATION (Generic for any regulatory body)
-- =====================================================

-- Regulatory jurisdictions (MAC, state boards, etc.)
CREATE TABLE regulatory_jurisdictions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  type VARCHAR(50) NOT NULL, -- 'mac', 'state_board', 'dea', 'fda'
  code VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  coverage_area JSONB DEFAULT '{}', -- ZIP codes, states, regions
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(tenant_id, type, code)
);

-- Coverage and validation rules
CREATE TABLE compliance_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_code VARCHAR(100) UNIQUE NOT NULL,
  rule_type VARCHAR(50) NOT NULL, -- 'coverage', 'documentation', 'clinical', 'frequency'
  name VARCHAR(255) NOT NULL,
  description TEXT,
  
  -- Conditions
  applies_to_products UUID[] DEFAULT '{}',
  applies_to_codes VARCHAR[] DEFAULT '{}', -- CPT, HCPCS, ICD-10
  jurisdiction_ids UUID[] DEFAULT '{}',
  
  -- Rule logic
  rule_logic JSONB NOT NULL, -- Complex conditional logic
  required_documentation JSONB DEFAULT '[]',
  
  -- Metadata
  severity VARCHAR(20) DEFAULT 'warning', -- 'error', 'warning', 'info'
  effective_date DATE,
  expiration_date DATE,
  source_policy VARCHAR(255), -- LCD/NCD reference
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_compliance_rules_products (applies_to_products),
  INDEX idx_compliance_rules_codes (applies_to_codes)
);

-- Validation results for orders
CREATE TABLE order_validations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  validation_type VARCHAR(50) NOT NULL, -- 'mac', 'payer', 'clinical'
  status VARCHAR(50) NOT NULL, -- 'passed', 'passed_with_warnings', 'failed'
  
  -- Results
  applied_rules JSONB DEFAULT '[]',
  errors JSONB DEFAULT '[]',
  warnings JSONB DEFAULT '[]',
  
  -- Context
  jurisdiction_id UUID REFERENCES regulatory_jurisdictions(id),
  validated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  validated_by UUID REFERENCES users(id),
  
  INDEX idx_order_validation (order_id, validation_type)
);
```

### 2. **Clinical Decision Support & Opportunities**

```sql
-- =====================================================
-- CLINICAL OPPORTUNITY ENGINE (Revenue optimization)
-- =====================================================

CREATE TABLE opportunity_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_name VARCHAR(255) NOT NULL,
  rule_type VARCHAR(50), -- 'additional_service', 'product_upgrade', 'documentation_improvement'
  
  -- Conditions
  clinical_criteria JSONB DEFAULT '{}', -- Diagnosis codes, clinical indicators
  product_criteria JSONB DEFAULT '{}', -- Current products, quantities
  payer_criteria JSONB DEFAULT '{}', -- Payer types, coverage
  
  -- Opportunity details
  suggested_action VARCHAR(255) NOT NULL,
  suggested_codes VARCHAR[] DEFAULT '{}', -- CPT, HCPCS codes
  estimated_value DECIMAL(10,2),
  clinical_benefit TEXT,
  documentation_guidance TEXT,
  
  -- Tracking
  confidence_score DECIMAL(3,2), -- 0.00 to 1.00
  success_rate DECIMAL(3,2), -- Historical success
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track presented opportunities
CREATE TABLE opportunity_presentations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  rule_id UUID NOT NULL REFERENCES opportunity_rules(id),
  presented_to UUID REFERENCES users(id),
  presented_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  action_taken VARCHAR(50), -- 'accepted', 'dismissed', 'deferred'
  action_at TIMESTAMP WITH TIME ZONE,
  action_by UUID REFERENCES users(id),
  notes TEXT,
  resulted_in_order_id UUID REFERENCES orders(id),
  
  INDEX idx_opportunity_order (order_id),
  INDEX idx_opportunity_action (action_taken)
);

-- Machine learning patterns (for future opportunities)
CREATE TABLE opportunity_patterns (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  pattern_hash VARCHAR(64) NOT NULL, -- Hash of pattern criteria
  pattern_data JSONB NOT NULL,
  frequency INTEGER DEFAULT 1,
  first_seen TIMESTAMP WITH TIME ZONE DEFAULT now(),
  last_seen TIMESTAMP WITH TIME ZONE DEFAULT now(),
  status VARCHAR(50) DEFAULT 'identified', -- 'identified', 'reviewed', 'converted_to_rule'
  
  UNIQUE(tenant_id, pattern_hash)
);
```

### 3. **External API Integration Management**

```sql
-- =====================================================
-- EXTERNAL API INTEGRATIONS (Payers, Eligibility, etc.)
-- =====================================================

CREATE TABLE api_configurations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  provider_name VARCHAR(100) NOT NULL, -- 'optum', 'availity', 'office_ally', 'custom'
  api_type VARCHAR(50) NOT NULL, -- 'eligibility', 'prior_auth', 'claims', 'remittance'
  
  -- Configuration
  base_url VARCHAR(500) NOT NULL,
  auth_method VARCHAR(50), -- 'oauth2', 'api_key', 'basic', 'custom'
  credentials JSONB ENCRYPTED, -- Encrypted credentials
  
  -- Routing rules
  supported_payers UUID[] DEFAULT '{}', -- Which payers this API supports
  priority INTEGER DEFAULT 100, -- For fallback routing
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track all API transactions
CREATE TABLE api_transactions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  api_config_id UUID REFERENCES api_configurations(id),
  
  -- Request details
  transaction_type VARCHAR(50) NOT NULL, -- 'eligibility_check', 'prior_auth_submit', etc.
  entity_type VARCHAR(50), -- 'order', 'recipient', 'coverage'
  entity_id UUID,
  
  -- API details
  request_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
  response_timestamp TIMESTAMP WITH TIME ZONE,
  status_code INTEGER,
  
  -- Tracking
  external_transaction_id VARCHAR(255), -- Their reference number
  request_hash VARCHAR(64), -- For deduplication
  response_hash VARCHAR(64),
  
  -- Storage references (not actual data)
  request_storage_path VARCHAR(500),
  response_storage_path VARCHAR(500),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_api_transaction_entity (entity_type, entity_id),
  INDEX idx_api_transaction_external (external_transaction_id)
);

-- Care reminders from payers
CREATE TABLE care_reminders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  source_api VARCHAR(100), -- 'availity', 'optum', etc.
  external_reminder_id VARCHAR(255),
  
  reminder_type VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  due_date DATE,
  
  status VARCHAR(50) DEFAULT 'active', -- 'active', 'addressed', 'dismissed'
  addressed_by UUID REFERENCES users(id),
  addressed_at TIMESTAMP WITH TIME ZONE,
  action_taken TEXT,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_care_reminder_recipient (recipient_id, status)
);
```

### 4. **Enhanced Eligibility & Prior Authorization**

```sql
-- =====================================================
-- ELIGIBILITY & PRIOR AUTHORIZATION
-- =====================================================

-- Detailed eligibility results (beyond basic verification)
CREATE TABLE eligibility_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID REFERENCES orders(id),
  coverage_policy_id UUID REFERENCES coverage_policies(id),
  
  -- Check details
  check_type VARCHAR(50) DEFAULT 'standard', -- 'standard', 'detailed', 'benefits'
  api_transaction_id UUID REFERENCES api_transactions(id),
  
  -- Results
  status VARCHAR(50) NOT NULL, -- 'eligible', 'not_eligible', 'pending', 'error'
  coverage_active BOOLEAN,
  
  -- Benefits summary (no PHI)
  benefits_summary JSONB DEFAULT '{}', -- Copay, deductible, limits
  
  -- Prior auth determination
  prior_auth_required VARCHAR(50), -- 'required', 'not_required', 'conditional'
  prior_auth_reasons JSONB DEFAULT '[]',
  
  checked_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  valid_until TIMESTAMP WITH TIME ZONE,
  
  INDEX idx_eligibility_order (order_id)
);

-- Prior authorization tracking
CREATE TABLE prior_authorizations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  eligibility_check_id UUID REFERENCES eligibility_checks(id),
  
  -- PA details
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  auth_number VARCHAR(255),
  
  -- Submission tracking
  submitted_at TIMESTAMP WITH TIME ZONE,
  submitted_via UUID REFERENCES api_configurations(id),
  submission_reference VARCHAR(255),
  
  -- Response
  determination VARCHAR(50), -- 'approved', 'denied', 'partial', 'pending'
  effective_date DATE,
  expiration_date DATE,
  approved_units INTEGER,
  denial_reasons JSONB DEFAULT '[]',
  
  -- Documents
  supporting_documents JSONB DEFAULT '[]',
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_prior_auth_order (order_id),
  INDEX idx_prior_auth_status (status)
);
```

### 5. **Product Intelligence & Recommendations**

```sql
-- =====================================================
-- PRODUCT RECOMMENDATION ENGINE
-- =====================================================

CREATE TABLE recommendation_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  rule_name VARCHAR(255) NOT NULL,
  
  -- Conditions
  clinical_criteria JSONB DEFAULT '{}', -- Wound type, size, duration, etc.
  patient_criteria JSONB DEFAULT '{}', -- Age, comorbidities
  treatment_history JSONB DEFAULT '{}', -- Previous products, outcomes
  
  -- Recommendations
  recommended_products JSONB NOT NULL, -- Ranked list with reasoning
  
  -- Sizing logic
  size_calculation_method VARCHAR(100), -- 'match_wound', 'standard', 'custom'
  size_logic JSONB DEFAULT '{}',
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Track recommendation effectiveness
CREATE TABLE recommendation_tracking (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  rule_id UUID REFERENCES recommendation_rules(id),
  
  products_recommended JSONB NOT NULL,
  products_selected JSONB NOT NULL,
  
  recommendation_score DECIMAL(3,2), -- Confidence score
  was_followed BOOLEAN,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
```

### 6. **AI/RAG Support Tables**

```sql
-- =====================================================
-- AI & KNOWLEDGE BASE (For MSC Assist)
-- =====================================================

-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Knowledge base documents
CREATE TABLE knowledge_documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  source_type VARCHAR(50) NOT NULL, -- 'product_info', 'clinical_guideline', 'policy', 'faq'
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  
  -- Metadata
  source_id UUID, -- Reference to product, rule, etc.
  url VARCHAR(500),
  
  is_active BOOLEAN DEFAULT true,
  last_indexed TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Vector embeddings for semantic search
CREATE TABLE knowledge_embeddings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  document_id UUID NOT NULL REFERENCES knowledge_documents(id),
  chunk_index INTEGER NOT NULL,
  chunk_text TEXT NOT NULL,
  embedding vector(1536), -- OpenAI embedding size
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(document_id, chunk_index)
);

-- Create index for similarity search
CREATE INDEX knowledge_embeddings_vector_idx ON knowledge_embeddings 
USING ivfflat (embedding vector_cosine_ops);
```

### 7. **Clinical Data Extensions**

```sql
-- =====================================================
-- CLINICAL DATA SUPPORT (Flexible for wound care, etc.)
-- =====================================================

-- Clinical assessments (wound measurements, etc.)
CREATE TABLE clinical_assessments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  assessment_type VARCHAR(50) NOT NULL, -- 'wound', 'mobility', 'pain', etc.
  
  -- Flexible measurement storage
  measurements JSONB NOT NULL, -- {length: 4.5, width: 3.2, depth: 0.8}
  
  -- Clinical observations
  observations JSONB DEFAULT '{}', -- {tissue_type: 'granulation', drainage: 'minimal'}
  
  -- Images/documents
  attachments JSONB DEFAULT '[]',
  
  assessed_by UUID REFERENCES users(id),
  assessed_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Treatment history
CREATE TABLE treatment_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  recipient_id UUID NOT NULL REFERENCES recipients(id),
  order_id UUID REFERENCES orders(id),
  
  treatment_type VARCHAR(100) NOT NULL,
  treatment_details JSONB NOT NULL,
  
  start_date DATE NOT NULL,
  end_date DATE,
  
  outcome VARCHAR(50), -- 'effective', 'ineffective', 'adverse_reaction'
  outcome_details TEXT,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_treatment_recipient (recipient_id)
);
```

### 8. **Enhanced Document Management for DocuSeal**

```sql
-- =====================================================
-- DOCUSEAL INTEGRATION ENHANCEMENTS
-- =====================================================

ALTER TABLE documents ADD COLUMN IF NOT EXISTS
  docuseal_submission_id VARCHAR(255),
  docuseal_template_id VARCHAR(255),
  docuseal_status VARCHAR(50), -- DocuSeal-specific status
  field_mappings JSONB DEFAULT '{}', -- How fields map
  signature_request_sent_at TIMESTAMP WITH TIME ZONE,
  signature_completed_at TIMESTAMP WITH TIME ZONE;

CREATE INDEX idx_documents_docuseal ON documents(docuseal_submission_id) 
  WHERE docuseal_submission_id IS NOT NULL;
```

### 9. **Enhanced Audit Trail**

```sql
-- =====================================================
-- COMPREHENSIVE AUDIT TRAIL
-- =====================================================

-- PHI access logging (references only)
CREATE TABLE phi_access_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  user_id UUID NOT NULL REFERENCES users(id),
  
  -- What was accessed
  resource_type VARCHAR(50) NOT NULL, -- 'patient', 'clinical_note', etc.
  resource_identifier VARCHAR(255) NOT NULL, -- FHIR ID or other reference
  
  -- Access details
  action VARCHAR(50) NOT NULL, -- 'view', 'create', 'update', 'delete'
  reason VARCHAR(255) NOT NULL, -- Business justification
  
  -- Context
  ip_address INET,
  session_id VARCHAR(255),
  
  accessed_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_phi_access_user (user_id),
  INDEX idx_phi_access_resource (resource_type, resource_identifier)
);
```

### 10. **Platform-Specific Settings**

```sql
-- =====================================================
-- ASHLEY'S WORKFLOW PREFERENCES
-- =====================================================

-- Quick access to manufacturer email lists
CREATE TABLE manufacturer_contacts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  manufacturer_id UUID NOT NULL REFERENCES manufacturers(id),
  contact_type VARCHAR(50) NOT NULL, -- 'order', 'ivr', 'support'
  
  primary_recipients TEXT[] NOT NULL DEFAULT '{}',
  cc_recipients TEXT[] DEFAULT '{}',
  
  notes TEXT, -- "Susan for rush orders, Mark for standard"
  
  updated_by UUID REFERENCES users(id),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(manufacturer_id, contact_type)
);

-- Manufacturer request tracking (when they ask for bulk IVR updates)
CREATE TABLE manufacturer_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  manufacturer_id UUID NOT NULL REFERENCES manufacturers(id),
  request_type VARCHAR(50) NOT NULL, -- 'bulk_ivr_update', 'form_change'
  
  requested_via VARCHAR(50), -- 'email', 'text', 'phone'
  requested_at TIMESTAMP WITH TIME ZONE NOT NULL,
  
  details TEXT,
  
  status VARCHAR(50) DEFAULT 'pending',
  completed_at TIMESTAMP WITH TIME ZONE,
  
  created_by UUID REFERENCES users(id),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
```

## Key Enhancements Summary

1. **Compliance Engine**: Full MAC validation and regulatory rules
2. **Clinical Intelligence**: Opportunity identification and tracking
3. **API Management**: Comprehensive external integration tracking
4. **Enhanced Prior Auth**: Detailed PA workflow support
5. **AI/RAG Support**: Vector embeddings for intelligent assistance
6. **Clinical Flexibility**: Generic assessment and treatment tracking
7. **Ashley's Workflows**: Specific tables for her coordination needs

This enhanced schema maintains the generic, multi-tenant nature while incorporating all the sophisticated features from your existing platform design.

Looking at this comprehensive schema against your project documents, it's **nearly all-encompassing** but I've identified some gaps that could make it truly complete:

## 🔍 Missing Components Analysis

### 1. **Fee Schedule & Pricing Intelligence**

```sql
-- =====================================================
-- CMS FEE SCHEDULES & PRICING
-- =====================================================

CREATE TABLE fee_schedules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  code_type VARCHAR(50) NOT NULL, -- 'cpt', 'hcpcs', 'drg'
  code VARCHAR(50) NOT NULL,
  
  -- Pricing data
  medicare_rate DECIMAL(10,2),
  commercial_rate DECIMAL(10,2),
  
  -- Geographic adjustments
  jurisdiction_id UUID REFERENCES regulatory_jurisdictions(id),
  locality VARCHAR(100),
  
  effective_date DATE NOT NULL,
  end_date DATE,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_fee_schedule_code (code_type, code),
  UNIQUE(tenant_id, code_type, code, jurisdiction_id, effective_date)
);

-- Product margin analysis
CREATE TABLE product_margins (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID NOT NULL REFERENCES products(id),
  
  cost_basis DECIMAL(10,2) NOT NULL,
  medicare_reimbursement DECIMAL(10,2),
  average_commercial_rate DECIMAL(10,2),
  
  margin_percentage DECIMAL(5,2),
  
  calculated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_product_margin (product_id)
);
```

### 2. **Enhanced Commission Hierarchy**

```sql
-- =====================================================
-- MULTI-LEVEL COMMISSION TRACKING
-- =====================================================

-- Commission splits for hierarchical teams
CREATE TABLE commission_splits (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  commission_id UUID NOT NULL REFERENCES commissions(id),
  
  recipient_rep_id UUID NOT NULL REFERENCES sales_reps(id),
  split_type VARCHAR(50), -- 'direct', 'override', 'team_bonus'
  split_percentage DECIMAL(5,2),
  split_amount DECIMAL(10,2),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Team structures
CREATE TABLE sales_teams (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  team_name VARCHAR(255) NOT NULL,
  team_lead_id UUID REFERENCES sales_reps(id),
  
  commission_structure JSONB DEFAULT '{}', -- Team-specific rules
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE sales_team_members (
  team_id UUID NOT NULL REFERENCES sales_teams(id),
  rep_id UUID NOT NULL REFERENCES sales_reps(id),
  role VARCHAR(50), -- 'member', 'co-lead', etc.
  
  PRIMARY KEY (team_id, rep_id)
);
```

### 3. **Batch Processing & Bulk Operations**

```sql
-- =====================================================
-- BATCH OPERATIONS (Ashley's bulk IVR updates)
-- =====================================================

CREATE TABLE batch_operations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  operation_type VARCHAR(50) NOT NULL, -- 'bulk_verification_update', 'mass_order_submit'
  status VARCHAR(50) DEFAULT 'pending',
  
  -- Batch details
  total_records INTEGER NOT NULL,
  processed_records INTEGER DEFAULT 0,
  failed_records INTEGER DEFAULT 0,
  
  -- Input/Output
  input_file_url VARCHAR(500),
  output_file_url VARCHAR(500),
  error_log JSONB DEFAULT '[]',
  
  -- Execution
  started_at TIMESTAMP WITH TIME ZONE,
  completed_at TIMESTAMP WITH TIME ZONE,
  created_by UUID REFERENCES users(id),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_batch_status (status)
);

CREATE TABLE batch_operation_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  batch_id UUID NOT NULL REFERENCES batch_operations(id),
  
  item_index INTEGER NOT NULL,
  entity_type VARCHAR(50),
  entity_id UUID,
  
  status VARCHAR(50) DEFAULT 'pending',
  error_message TEXT,
  
  processed_at TIMESTAMP WITH TIME ZONE,
  
  INDEX idx_batch_item (batch_id, status)
);
```

### 4. **Returns & Refunds Workflow**

```sql
-- =====================================================
-- RETURNS & REFUNDS
-- =====================================================

CREATE TABLE returns (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES orders(id),
  
  return_type VARCHAR(50) NOT NULL, -- 'defective', 'wrong_item', 'not_needed', 'expired'
  status VARCHAR(50) DEFAULT 'requested',
  
  -- Return details
  items JSONB NOT NULL, -- Which items being returned
  reason TEXT,
  
  -- Processing
  rma_number VARCHAR(100), -- Return authorization
  return_shipping_label VARCHAR(500),
  
  -- Refund/Credit
  refund_amount DECIMAL(10,2),
  credit_issued BOOLEAN DEFAULT false,
  
  requested_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  approved_at TIMESTAMP WITH TIME ZONE,
  received_at TIMESTAMP WITH TIME ZONE,
  completed_at TIMESTAMP WITH TIME ZONE,
  
  INDEX idx_return_order (order_id),
  INDEX idx_return_status (status)
);
```

### 5. **Financial Reconciliation**

```sql
-- =====================================================
-- FINANCIAL RECONCILIATION
-- =====================================================

CREATE TABLE invoices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  invoice_number VARCHAR(100) UNIQUE NOT NULL,
  organization_id UUID NOT NULL REFERENCES organizations(id),
  
  -- Billing period
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  
  -- Amounts
  subtotal DECIMAL(10,2) NOT NULL,
  tax_amount DECIMAL(10,2) DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  
  -- Payment tracking
  amount_paid DECIMAL(10,2) DEFAULT 0,
  balance_due DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
  
  status VARCHAR(50) DEFAULT 'draft',
  due_date DATE,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_invoice_org (organization_id),
  INDEX idx_invoice_status (status)
);

CREATE TABLE payments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Polymorphic reference
  payable_type VARCHAR(50) NOT NULL, -- 'invoice', 'order'
  payable_id UUID NOT NULL,
  
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50), -- 'check', 'ach', 'credit_card', 'insurance'
  
  reference_number VARCHAR(255),
  
  received_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_payment_payable (payable_type, payable_id)
);
```

### 6. **Notification Templates & Delivery**

```sql
-- =====================================================
-- ENHANCED NOTIFICATIONS
-- =====================================================

CREATE TABLE notification_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  template_code VARCHAR(100) UNIQUE NOT NULL,
  name VARCHAR(255) NOT NULL,
  
  -- Delivery channels
  channels JSONB DEFAULT '["email"]', -- ['email', 'sms', 'in_app', 'push']
  
  -- Templates per channel
  email_template JSONB,
  sms_template JSONB,
  push_template JSONB,
  
  -- Variables
  required_variables JSONB DEFAULT '[]',
  
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE notification_deliveries (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  notification_id UUID NOT NULL REFERENCES notifications(id),
  
  channel VARCHAR(50) NOT NULL,
  recipient_address VARCHAR(255), -- Email, phone, device token
  
  status VARCHAR(50) DEFAULT 'pending', -- 'sent', 'delivered', 'failed', 'bounced'
  
  sent_at TIMESTAMP WITH TIME ZONE,
  delivered_at TIMESTAMP WITH TIME ZONE,
  failed_at TIMESTAMP WITH TIME ZONE,
  
  error_details JSONB,
  
  INDEX idx_delivery_notification (notification_id),
  INDEX idx_delivery_status (status)
);
```

### 7. **Inventory Management (If Needed)**

```sql
-- =====================================================
-- INVENTORY TRACKING (Optional)
-- =====================================================

CREATE TABLE inventory_locations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50), -- 'warehouse', 'consignment', 'trunk_stock'
  
  facility_id UUID REFERENCES facilities(id),
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE inventory_levels (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  product_id UUID NOT NULL REFERENCES products(id),
  location_id UUID NOT NULL REFERENCES inventory_locations(id),
  
  quantity_on_hand INTEGER NOT NULL DEFAULT 0,
  quantity_allocated INTEGER NOT NULL DEFAULT 0,
  quantity_available INTEGER GENERATED ALWAYS AS (quantity_on_hand - quantity_allocated) STORED,
  
  reorder_point INTEGER,
  reorder_quantity INTEGER,
  
  last_counted_at TIMESTAMP WITH TIME ZONE,
  
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  UNIQUE(product_id, location_id)
);
```

### 8. **Mobile App Support**

```sql
-- =====================================================
-- MOBILE APP SUPPORT
-- =====================================================

CREATE TABLE device_registrations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  
  device_type VARCHAR(50) NOT NULL, -- 'ios', 'android'
  device_token VARCHAR(500), -- Push notification token
  
  app_version VARCHAR(50),
  os_version VARCHAR(50),
  
  last_active_at TIMESTAMP WITH TIME ZONE,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_device_user (user_id)
);

CREATE TABLE mobile_sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  device_id UUID REFERENCES device_registrations(id),
  
  access_token TEXT ENCRYPTED,
  refresh_token TEXT ENCRYPTED,
  
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  
  INDEX idx_mobile_session_user (user_id)
);
```

## 🎯 Assessment: 95% Complete

The schema is **extremely comprehensive** and covers:

- ✅ Multi-tenant architecture
- ✅ Complex validation rules
- ✅ External API integrations
- ✅ Clinical decision support
- ✅ AI/RAG capabilities
- ✅ Sophisticated commission structures
- ✅ Document management
- ✅ Ashley's specific workflows

With the additions above, it would be 100% complete for a universal medical distribution platform.

## 💡 Three Strategic Questions

1. **Data Partitioning Strategy**: Given the potential scale (millions of orders, verifications), should we implement PostgreSQL table partitioning from day one? For example, partitioning `orders` by `created_at` month or `api_transactions` by date could dramatically improve query performance.

2. **Event Sourcing Architecture**: With complex workflows like order approval, verification updates, and commission calculations, would implementing an event-sourced architecture alongside the current state-based schema provide better auditability and the ability to replay/correct historical calculations?

3. **Multi-Region Compliance**: As medical distribution often crosses state/country boundaries with different regulations, should we design for multi-region deployment from the start with region-specific schema extensions and data residency considerations?
