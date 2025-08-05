# Database Schema Documentation

**Version:** 1.0  
**Last Updated:** January 2025  
**Status:** Production Schema

---

## 📋 Overview

The MSC Wound Care Portal uses a comprehensive database schema designed to support healthcare workflows, FHIR compliance, and regulatory requirements. This document provides detailed schema documentation including tables, relationships, and data flow patterns.

## 🏗️ Database Architecture

### Schema Organization
```
Database Schema:
├── Core Entities/
│   ├── Users & Authentication
│   ├── Organizations & Facilities
│   └── Roles & Permissions (RBAC)
├── Healthcare Data/
│   ├── FHIR Resources (PHI Separated)
│   ├── Medical Terminology
│   └── Clinical Observations
├── Order Management/
│   ├── Products & Manufacturers
│   ├── Product Requests & Orders
│   └── Order Lifecycle Tracking
├── Insurance & Compliance/
│   ├── Eligibility & Coverage
│   ├── Prior Authorizations
│   └── Medicare MAC Validation
├── Document Management/
│   ├── DocuSeal Integration
│   ├── IVR Episodes
│   └── Document References
└── Analytics & Audit/
    ├── Commission Tracking
    ├── Audit Logs
    └── Machine Learning Models
```

## 👥 User Management Schema

### users
Primary user table with authentication and basic profile information.

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    npi_number VARCHAR(10) UNIQUE,
    owner BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_npi (npi_number),
    INDEX idx_owner (owner)
);
```

### organizations
Healthcare organization hierarchy management.

```sql
CREATE TABLE organizations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    account_id BIGINT,
    tax_id VARCHAR(20),
    type VARCHAR(50), -- 'Hospital', 'Clinic Group', etc.
    status VARCHAR(20) DEFAULT 'active',
    sales_rep_id BIGINT,
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    region VARCHAR(50),
    country VARCHAR(50) DEFAULT 'US',
    postal_code VARCHAR(20),
    fhir_id VARCHAR(100), -- FHIR Organization ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (sales_rep_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_sales_rep (sales_rep_id),
    INDEX idx_fhir_id (fhir_id)
);
```

### roles
Role-based access control system.

```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    permissions JSON, -- Array of permission strings
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug)
);
```

### user_roles
Many-to-many relationship between users and roles.

```sql
CREATE TABLE user_roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by BIGINT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
);
```

## 🏥 FHIR Healthcare Data Schema

### fhir_patients
PHI-separated patient data following FHIR R4 Patient resource.

```sql
CREATE TABLE fhir_patients (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    fhir_id VARCHAR(100) UNIQUE NOT NULL, -- External FHIR ID
    active BOOLEAN DEFAULT TRUE,
    gender ENUM('male', 'female', 'other', 'unknown'),
    birth_date DATE,
    marital_status VARCHAR(50),
    deceased BOOLEAN DEFAULT FALSE,
    deceased_date_time TIMESTAMP NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    preferred_language VARCHAR(10),
    race VARCHAR(100),
    ethnicity VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_fhir_id (fhir_id),
    INDEX idx_active (active),
    INDEX idx_birth_date (birth_date)
);
```

### fhir_practitioners
Healthcare provider FHIR data.

```sql
CREATE TABLE fhir_practitioners (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    fhir_id VARCHAR(100) UNIQUE NOT NULL,
    user_id BIGINT, -- Link to users table
    active BOOLEAN DEFAULT TRUE,
    npi_number VARCHAR(10),
    qualification JSON, -- Array of qualifications
    specialty JSON, -- Array of specialties
    communication JSON, -- Array of languages
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_fhir_id (fhir_id),
    INDEX idx_user_id (user_id),
    INDEX idx_npi (npi_number)
);
```

### fhir_facilities
Healthcare facility management.

```sql
CREATE TABLE fhir_facilities (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    fhir_id VARCHAR(100) UNIQUE NOT NULL,
    organization_id BIGINT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100), -- 'Hospital', 'Clinic', etc.
    status VARCHAR(20) DEFAULT 'active',
    address JSON, -- FHIR Address structure
    contact JSON, -- Contact information
    services JSON, -- Available services
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    INDEX idx_fhir_id (fhir_id),
    INDEX idx_organization_id (organization_id),
    INDEX idx_status (status)
);
```

### fhir_observations
Clinical observations and measurements.

```sql
CREATE TABLE fhir_observations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    fhir_id VARCHAR(100) UNIQUE NOT NULL,
    patient_fhir_id VARCHAR(100) NOT NULL,
    practitioner_fhir_id VARCHAR(100),
    category VARCHAR(100), -- 'vital-signs', 'survey', etc.
    code VARCHAR(100) NOT NULL, -- LOINC code
    value_type VARCHAR(50), -- 'quantity', 'string', 'boolean', etc.
    value_data JSON, -- Observation value and unit
    effective_date_time TIMESTAMP,
    issued TIMESTAMP,
    status VARCHAR(20) DEFAULT 'final',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_fhir_id) REFERENCES fhir_patients(fhir_id),
    FOREIGN KEY (practitioner_fhir_id) REFERENCES fhir_practitioners(fhir_id),
    INDEX idx_patient_fhir_id (patient_fhir_id),
    INDEX idx_code (code),
    INDEX idx_effective_date (effective_date_time)
);
```

## 📦 Order Management Schema

### manufacturers
Product manufacturers and their details.

```sql
CREATE TABLE manufacturers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    code VARCHAR(50) UNIQUE,
    logo_url VARCHAR(500),
    website VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    status VARCHAR(20) DEFAULT 'active',
    configuration JSON, -- Manufacturer-specific settings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_status (status)
);
```

### products
Product catalog with hierarchical categories.

```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    manufacturer_id BIGINT NOT NULL,
    category_id BIGINT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sku VARCHAR(100),
    upc VARCHAR(20),
    hcpcs_code VARCHAR(10),
    unit_of_measure VARCHAR(50),
    base_price DECIMAL(10,2),
    medicare_allowable DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'active',
    requires_prior_auth BOOLEAN DEFAULT FALSE,
    clinical_indications JSON,
    contraindications JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_manufacturer_id (manufacturer_id),
    INDEX idx_sku (sku),
    INDEX idx_hcpcs_code (hcpcs_code),
    INDEX idx_status (status)
);
```

### product_requests
Core product request entity.

```sql
CREATE TABLE product_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    user_id BIGINT NOT NULL, -- Requesting provider
    organization_id BIGINT,
    facility_id BIGINT,
    patient_fhir_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'draft',
    priority VARCHAR(20) DEFAULT 'normal',
    clinical_details JSON,
    insurance_details JSON,
    shipping_details JSON,
    notes TEXT,
    submitted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    FOREIGN KEY (facility_id) REFERENCES fhir_facilities(id),
    INDEX idx_request_number (request_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at)
);
```

### orders
Manufacturing orders generated from product requests.

```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    product_request_id BIGINT NOT NULL,
    manufacturer_id BIGINT NOT NULL,
    total_amount DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'pending',
    priority VARCHAR(20) DEFAULT 'normal',
    expected_ship_date DATE,
    tracking_number VARCHAR(100),
    shipping_carrier VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_request_id) REFERENCES product_requests(id),
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id),
    INDEX idx_order_number (order_number),
    INDEX idx_product_request_id (product_request_id),
    INDEX idx_status (status)
);
```

### order_items
Line items for orders.

```sql
CREATE TABLE order_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    product_specifications JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);
```

## 🏥 Insurance & Compliance Schema

### eligibility_checks
Insurance eligibility verification records.

```sql
CREATE TABLE eligibility_checks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_fhir_id VARCHAR(100) NOT NULL,
    payer_name VARCHAR(255),
    payer_id VARCHAR(100),
    member_id VARCHAR(100),
    group_number VARCHAR(100),
    eligibility_status VARCHAR(50),
    coverage_active BOOLEAN,
    coverage_details JSON,
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    source VARCHAR(50), -- 'availity', 'manual', etc.
    raw_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_fhir_id) REFERENCES fhir_patients(fhir_id),
    INDEX idx_patient_fhir_id (patient_fhir_id),
    INDEX idx_check_date (check_date),
    INDEX idx_expires_at (expires_at)
);
```

### medicare_mac_validations
Medicare Administrative Contractor validations.

```sql
CREATE TABLE medicare_mac_validations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    validation_id VARCHAR(100) UNIQUE NOT NULL,
    order_id BIGINT,
    patient_fhir_id VARCHAR(100),
    facility_id BIGINT,
    mac_contractor VARCHAR(100),
    mac_jurisdiction VARCHAR(10),
    patient_zip_code VARCHAR(10),
    validation_type VARCHAR(50),
    validation_status VARCHAR(50),
    validation_results JSON,
    coverage_policies JSON,
    coverage_met BOOLEAN,
    procedures_validated JSON,
    cpt_codes_validated JSON,
    documentation_complete BOOLEAN DEFAULT FALSE,
    required_documentation JSON,
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_validation_due TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (patient_fhir_id) REFERENCES fhir_patients(fhir_id),
    FOREIGN KEY (facility_id) REFERENCES fhir_facilities(id),
    INDEX idx_validation_id (validation_id),
    INDEX idx_mac_jurisdiction (mac_jurisdiction),
    INDEX idx_validated_at (validated_at)
);
```

### prior_authorizations
Prior authorization tracking.

```sql
CREATE TABLE prior_authorizations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    auth_number VARCHAR(100) UNIQUE,
    product_request_id BIGINT,
    payer_name VARCHAR(255),
    member_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',
    requested_date DATE,
    approved_date DATE,
    expiry_date DATE,
    approved_amount DECIMAL(10,2),
    approved_units INT,
    denial_reason TEXT,
    supporting_documents JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_request_id) REFERENCES product_requests(id),
    INDEX idx_auth_number (auth_number),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
);
```

## 📄 Document Management Schema

### patient_manufacturer_ivr_episodes
IVR document generation episodes.

```sql
CREATE TABLE patient_manufacturer_ivr_episodes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    episode_number VARCHAR(50) UNIQUE NOT NULL,
    patient_fhir_id VARCHAR(100) NOT NULL,
    manufacturer_id BIGINT NOT NULL,
    product_request_id BIGINT,
    status VARCHAR(50) DEFAULT 'pending',
    template_id VARCHAR(100),
    docuseal_submission_id VARCHAR(100),
    form_data JSON,
    mapped_data JSON,
    completion_status VARCHAR(50),
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_fhir_id) REFERENCES fhir_patients(fhir_id),
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id),
    FOREIGN KEY (product_request_id) REFERENCES product_requests(id),
    INDEX idx_episode_number (episode_number),
    INDEX idx_patient_fhir_id (patient_fhir_id),
    INDEX idx_status (status)
);
```

### docuseal_submissions
DocuSeal document submission tracking.

```sql
CREATE TABLE docuseal_submissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    submission_id VARCHAR(100) UNIQUE NOT NULL,
    template_id VARCHAR(100) NOT NULL,
    episode_id BIGINT,
    status VARCHAR(50) DEFAULT 'pending',
    submission_url TEXT,
    download_url TEXT,
    completed_at TIMESTAMP NULL,
    audit_trail JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (episode_id) REFERENCES patient_manufacturer_ivr_episodes(id),
    INDEX idx_submission_id (submission_id),
    INDEX idx_template_id (template_id),
    INDEX idx_status (status)
);
```

## 💰 Commission & Analytics Schema

### commission_records
Sales commission tracking.

```sql
CREATE TABLE commission_records (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sales_rep_id BIGINT NOT NULL,
    order_id BIGINT NOT NULL,
    organization_id BIGINT,
    commission_type VARCHAR(50), -- 'order', 'bonus', 'override'
    base_amount DECIMAL(10,2),
    commission_rate DECIMAL(5,4),
    commission_amount DECIMAL(10,2),
    tier_level VARCHAR(20),
    period_month INT,
    period_year INT,
    status VARCHAR(50) DEFAULT 'calculated',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sales_rep_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    INDEX idx_sales_rep_id (sales_rep_id),
    INDEX idx_period (period_year, period_month),
    INDEX idx_status (status)
);
```

### clinical_opportunities
AI-generated clinical opportunities.

```sql
CREATE TABLE clinical_opportunities (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_fhir_id VARCHAR(100) NOT NULL,
    opportunity_type VARCHAR(100),
    risk_score DECIMAL(5,2),
    priority VARCHAR(20) DEFAULT 'medium',
    title VARCHAR(255),
    description TEXT,
    recommended_actions JSON,
    evidence_data JSON,
    status VARCHAR(50) DEFAULT 'open',
    assigned_to BIGINT,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_fhir_id) REFERENCES fhir_patients(fhir_id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_patient_fhir_id (patient_fhir_id),
    INDEX idx_risk_score (risk_score),
    INDEX idx_status (status)
);
```

## 📊 Audit & Logging Schema

### order_audit_logs
Comprehensive order audit trail.

```sql
CREATE TABLE order_audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    user_id BIGINT,
    action VARCHAR(100) NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_order_id (order_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);
```

### fhir_audit_logs
FHIR operation audit trail.

```sql
CREATE TABLE fhir_audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    resource_type VARCHAR(50) NOT NULL,
    resource_id VARCHAR(100) NOT NULL,
    operation VARCHAR(20) NOT NULL, -- 'create', 'read', 'update', 'delete'
    user_id BIGINT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    outcome VARCHAR(20), -- 'success', 'failure'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_resource_type (resource_type),
    INDEX idx_operation (operation),
    INDEX idx_created_at (created_at)
);
```

## 🔄 Data Relationships & Constraints

### Key Relationships
```
users → organizations (many-to-many via user_organizations)
users → roles (many-to-many via user_roles)
organizations → facilities (one-to-many)
product_requests → orders (one-to-many)
orders → order_items (one-to-many)
patients → product_requests (one-to-many)
patients → eligibility_checks (one-to-many)
patients → clinical_opportunities (one-to-many)
```

### Foreign Key Constraints
- All foreign keys use `RESTRICT` on delete by default
- User-related foreign keys use `SET NULL` to preserve data integrity
- Audit logs use `CASCADE` for cleanup

### Data Integrity Rules
- PHI data is separated into FHIR-specific tables
- All monetary values use `DECIMAL(10,2)` for precision
- Timestamps include timezone information
- Soft deletes implemented for critical business entities

## 📈 Performance Optimization

### Index Strategy
- Primary keys on all tables
- Foreign key indexes for join performance
- Composite indexes for common query patterns
- Partial indexes for status-based queries

### Partitioning Strategy
```sql
-- Example: Partition audit logs by month
CREATE TABLE order_audit_logs (
    ...
) PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    ...
);
```

### Query Optimization
- Read replicas for analytical queries
- Connection pooling for high-traffic endpoints
- Query result caching for static data
- Background job processing for heavy operations

## 🔒 Security & Compliance

### PHI Protection
- PHI data isolated in FHIR tables
- Encrypted at rest and in transit
- Access logging for all PHI operations
- Data retention policies

### Access Control
- Row-level security for multi-tenant data
- Role-based access to sensitive tables
- Audit trails for all data modifications
- Automated compliance monitoring

---

**Related Documentation:**
- [System Architecture](./SYSTEM_ARCHITECTURE.md)
- [Security Architecture](./SECURITY_ARCHITECTURE.md)
- [FHIR Integration](../features/FHIR_INTEGRATION_FEATURE.md)
