# **MSC-MVP Wound Care Platform: Complete**  **Non-PHI Data Models Documentation**

**Version:** 2.0 \- Complete Implementation Guide

**Date:** December 19, 2024

**Status:** Production Schema \+ Future Roadmap

## **📋 Executive Summary**

This document provides the definitive reference for all non-PHI operational data models in the MSC-MVP Wound Care Platform. It covers:

1. **Current Production Schema** (18 tables in active use)  
2. **Migration Cleanup Strategy** (Remove deprecated, add missing)  
3. **Future Implementation Phases** (Organized roadmap through 2026\)  
4. **Performance Optimizations** (Indexes, computed columns, caching)  
5. **Development Guidelines** (Standards, best practices, compliance)

### **Key Architectural Principles**

1. **PHI Separation**: All PHI stored exclusively in Azure Health Data Services  
2. **Referential Integrity**: PostgreSQL contains only secure references to PHI  
3. **Phase-Based Growth**: Incremental implementation aligned with business needs  
4. **Performance First**: Optimized for real-world query patterns  
5. **HIPAA Compliance**: Audit trails and access controls throughout

## **🏗 Current Production Schema (Phase 1\)**

*These 18 tables are currently implemented and operational:*

### **System Infrastructure Tables**

#### **`migrations`**

**Purpose:** Laravel migration tracking

| Column Type Constraints Description |
| :---- |

| id | INTEGER | PK, AUTO INCREMENT | Migration sequence ID |
| :---- | :---- | :---- | :---- |
| migration | VARCHAR | NOT NULL | Migration file name |
| batch | INTEGER | NOT NULL | Migration batch number |

#### **`failed_jobs`**

**Purpose:** Laravel failed job queue tracking

| Column Type Constraints Description |
| :---- |

| id | BIGINT | PK, AUTO INCREMENT | Unique identifier |
| :---- | :---- | :---- | :---- |
| uuid | VARCHAR | UNIQUE, NOT NULL | Job UUID |
| connection | TEXT | NOT NULL | Queue connection |
| queue | TEXT | NOT NULL | Queue name |
| payload | LONGTEXT | NOT NULL | Job payload |
| exception | LONGTEXT | NOT NULL | Exception details |
| failed\_at | TIMESTAMP | NOT NULL | Failure timestamp |

#### **`cache`**

**Purpose:** Application-level caching

| Column Type Constraints Description |
| :---- |

| key | VARCHAR | PK | Cache key |
| :---- | :---- | :---- | :---- |
| value | MEDIUMTEXT | NOT NULL | Cached value |
| expiration | INTEGER | NOT NULL | Expiration timestamp |

####   **`cache_locks`**

**Purpose:** Cache lock management

| Column Type Constraints Description |
| :---- |

| key | VARCHAR | PK | Lock key |
| :---- | :---- | :---- | :---- |
| owner | VARCHAR | NOT NULL | Lock owner |
| expiration | INTEGER | NOT NULL | Lock expiration |

#### **`password_resets`**

**Purpose:** Password reset token management

| Column Type Constraints Description |
| :---- |

| email | VARCHAR | INDEXED | User email |
| :---- | :---- | :---- | :---- |
| token | VARCHAR | NOT NULL | Reset token |
| created\_at | TIMESTAMP | NULL | Token creation time |

#### **`personal_access_tokens`**

**Purpose:** API authentication tokens (Laravel Sanctum)

| Column Type Constraints Description |
| :---- |

| id | BIGINT | PK, AUTO INCREMENT | Unique identifier |
| :---- | :---- | :---- | :---- |
| tokenable\_type | VARCHAR | NOT NULL | Polymorphic type |
| tokenable\_id | BIGINT | NOT NULL | Entity ID |
| name | VARCHAR | NOT NULL | Token name |
| token | VARCHAR | UNIQUE, NOT NULL | Hashed token |
| abilities | TEXT | NULL | Token permissions |
| last\_used\_at | TIMESTAMP | NULL | Last usage timestamp |
| created\_at | TIMESTAMP | NOT NULL | Token creation |
| updated\_at | TIMESTAMP | NOT NULL | Token update |

### **Permission & Role Management**

#### **`permissions`**

**Purpose:** System permission definitions

| Column Type Constraints Description |
| :---- |

| id | BIGINT | PK, AUTO INCREMENT | Unique identifier |
| :---- | :---- | :---- | :---- |
| name | VARCHAR | NOT NULL | Permission name |
| slug | VARCHAR | UNIQUE, NOT NULL | Permission slug |
| guard\_name | VARCHAR | NOT NULL | Guard context |
| description | TEXT | NULL | Permission description |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |

#### **`permission_role`**

**Purpose:** Permission-role relationship mapping

| Column Type Constraints Description |
| :---- |

| id | BIGINT | PK, AUTO INCREMENT | Unique identifier |
| :---- | :---- | :---- | :---- |
| permission\_id | BIGINT | FK → permissions.id | Permission reference |
| role\_id | BIGINT | NOT NULL | Role reference |
| created\_at | TIMESTAMP | NOT NULL | Assignment timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |

### **Core Business Entities**

#### **`accounts`**

**Purpose:** Top-level account management

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| name | VARCHAR | NOT NULL | Account name |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |

#### **`organizations`**

**Purpose:** Healthcare organizations and customer entities

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| account\_id | UUID | FK → accounts.id | Parent account |
| name | VARCHAR | NOT NULL | Organization name |
| email | VARCHAR | NULL | Contact email |
| phone | VARCHAR | NULL | Contact phone |
| address | VARCHAR | NULL | Street address |
| city | VARCHAR | NULL | City |
| region | VARCHAR | NULL | State/Region |
| country | VARCHAR | NULL | Country |
| postal\_code | VARCHAR | NULL | ZIP/Postal code |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

#### **`facilities`**

**Purpose:** Individual facility locations within organizations

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| organization\_id | UUID | FK → organizations.id | Parent organization |
| name | VARCHAR | NOT NULL | Facility name |
| facility\_type | VARCHAR | NULL | Type classification |
| address | VARCHAR | NULL | Street address |
| city | VARCHAR | NULL | City |
| state | VARCHAR | NULL | State |
| zip\_code | VARCHAR | NULL | ZIP code |
| phone | VARCHAR | NULL | Contact phone |
| email | VARCHAR | NULL | Contact email |
| npi | VARCHAR | NULL | National Provider Identifier |
| business\_hours | TEXT | NULL | Operating hours |
| active | BOOLEAN | DEFAULT TRUE | Active status |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

#### **`provider_credentials`**

**Purpose:** Healthcare provider credential management (business info only)

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| provider\_id | UUID | NOT NULL | Provider reference |
| credential\_type | VARCHAR | NOT NULL | Type of credential |
| credential\_number | VARCHAR | NOT NULL | Credential number |
| credential\_display\_name | VARCHAR | NULL | Display name |
| issuing\_authority | VARCHAR | NULL | Issuing organization |
| issuing\_state | VARCHAR | NULL | State of issuance |
| issue\_date | DATE | NULL | Issue date |
| expiration\_date | DATE | NULL | Expiration date |
| effective\_date | DATE | NULL | Effective date |
| verification\_status | VARCHAR | NOT NULL | Verification status |
| verified\_at | TIMESTAMP | NULL | Verification timestamp |
| verified\_by | UUID | NULL | Verifier reference |
| verification\_notes | TEXT | NULL | Verification notes |
| document\_path | VARCHAR | NULL | Document storage path |
| document\_type | VARCHAR | NULL | Document type |
| document\_size | INTEGER | NULL | Document size |
| document\_hash | VARCHAR | NULL | Document hash |
| auto\_renewal\_enabled | BOOLEAN | DEFAULT FALSE | Auto-renewal flag |

### **Product & Sales Management**

#### **`msc_products`**

**Purpose:** MSC product catalog and pricing

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| sku | VARCHAR | UNIQUE, NOT NULL | Product SKU |
| name | VARCHAR | NOT NULL | Product name |
| description | TEXT | NULL | Product description |
| manufacturer | VARCHAR | NULL | Manufacturer name |
| manufacturer\_id | UUID | NULL | Manufacturer reference |
| category | VARCHAR | NULL | Product category |
| category\_id | UUID | NULL | Category reference |
| national\_asp | DECIMAL(10,2) | NULL | National Average Selling Price |
| price\_per\_sq\_cm | DECIMAL(8,4) | NULL | Price per square centimeter |
| q\_code | VARCHAR | NULL | HCPCS Q-code |
| available\_sizes | JSON | NULL | Available size options |
| graph\_type | VARCHAR | NULL | Graph classification |
| image\_url | VARCHAR | NULL | Product image URL |
| document\_urls | JSON | NULL | Related document URLs |
| is\_active | BOOLEAN | DEFAULT TRUE | Active status |
| commission\_rate | DECIMAL(5,2) | NULL | Commission percentage |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

#### **`msc_sales_reps`**

**Purpose:** MSC sales representative management

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| name | VARCHAR | NOT NULL | Representative name |
| email | VARCHAR | UNIQUE, NOT NULL | Contact email |
| phone | VARCHAR | NULL | Contact phone |
| territory | VARCHAR | NULL | Assigned territory |
| commission\_rate\_direct | DECIMAL(5,2) | NULL | Direct commission rate |
| sub\_rep\_parent\_share\_percentage | DECIMAL(5,2) | NULL | Parent share of sub-rep commission |
| parent\_rep\_id | UUID | FK → msc\_sales\_reps.id | Parent representative |
| is\_active | BOOLEAN | DEFAULT TRUE | Active status |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

###  **Order & Request Management**

#### **`product_requests`**

**Purpose:** Product request workflow management (PHI references only)

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| request\_number | VARCHAR | UNIQUE, NOT NULL | Human-readable request number |
| provider\_id | UUID | NOT NULL | Requesting provider reference |
| patient\_fhir\_id | VARCHAR | INDEXED | **🔒 PHI Reference** \- Patient in Azure FHIR |
| patient\_display\_id | VARCHAR | NULL | Non-PHI patient display identifier |
| facility\_id | UUID | FK → facilities.id | Requesting facility |
| payer\_name\_submitted | VARCHAR | NULL | Insurance payer name |
| payer\_id | VARCHAR | NULL | Insurance payer identifier |
| expected\_service\_date | DATE | NULL | Expected service date |
| wound\_type | VARCHAR | NULL | Wound type classification |
| azure\_order\_checklist\_fhir\_id | VARCHAR | NULL | **🔒 PHI Reference** \- Clinical checklist in Azure FHIR |
| clinical\_summary | TEXT | NULL | Non-PHI clinical summary |
| mac\_validation\_results | JSON | NULL | MAC validation results |
| mac\_validation\_status | VARCHAR | NULL | MAC validation status |
| eligibility\_results | JSON | NULL | Insurance eligibility results |
| eligibility\_status | VARCHAR | NULL | Eligibility status |
| pre\_auth\_required\_determination | VARCHAR | NULL | Prior authorization requirement |
| clinical\_opportunities | JSON | NULL | Identified clinical opportunities |
| order\_status | VARCHAR | NOT NULL | Current request status |
| step | VARCHAR | NULL | Current workflow step |
| submitted\_at | TIMESTAMP | NULL | Submission timestamp |
| approved\_at | TIMESTAMP | NULL | Approval timestamp |
| total\_order\_value | DECIMAL(12,2) | NULL | Total request value |
| acquiring\_rep\_id | UUID | FK → msc\_sales\_reps.id | Acquiring sales rep |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

####         **`product_request_products`**

**Purpose:** Line items for product requests

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| product\_request\_id | UUID | FK → product\_requests.id | Parent request |
| product\_id | UUID | FK → msc\_products.id | Requested product |
| quantity | DECIMAL(8,2) | NOT NULL | Quantity requested |
| size | VARCHAR | NULL | Specific size requested |
| unit\_price | DECIMAL(10,2) | NOT NULL | Unit price |
| total\_price | DECIMAL(12,2) | NOT NULL | Line total |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |

#### **`orders`**

**Purpose:** Finalized orders and fulfillment tracking

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| order\_number | VARCHAR | UNIQUE, NOT NULL | Human-readable order number |
| patient\_fhir\_id | VARCHAR | INDEXED | **🔒 PHI Reference** \- Patient in Azure FHIR |
| facility\_id | UUID | FK → facilities.id | Fulfillment facility |
| sales\_rep\_id | UUID | FK → msc\_sales\_reps.id | Assigned sales rep |
| date\_of\_service | DATE | NULL | Actual service date |
| credit\_terms | VARCHAR | NULL | Payment terms |
| status | VARCHAR | NOT NULL | Order status |
| total\_amount | DECIMAL(12,2) | NULL | Total order amount |
| expected\_reimbursement | DECIMAL(12,2) | NULL | Expected reimbursement |
| expected\_collection\_date | DATE | NULL | Expected payment date |
| payment\_status | VARCHAR | NULL | Payment status |
| msc\_commission\_structure | JSON | NULL | Commission calculation details |
| msc\_commission | DECIMAL(10,2) | NULL | Total MSC commission |
| document\_urls | JSON | NULL | Related document URLs |
| notes | TEXT | NULL | Order notes |
| created\_at | TIMESTAMP | NOT NULL | Creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

### **Audit & Compliance**

#### **`profile_audit_log`**

**Purpose:** Comprehensive audit trail for compliance and security

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| entity\_type | VARCHAR | NOT NULL | Type of entity modified |
| entity\_id | UUID | NOT NULL | Entity identifier |
| entity\_display\_name | VARCHAR | NULL | Human-readable entity name |
| user\_id | UUID | NULL | User performing action |
| user\_email | VARCHAR | NULL | User email |
| user\_role | VARCHAR | NULL | User role |
| action\_type | VARCHAR | NOT NULL | Type of action performed |
| action\_description | TEXT | NULL | Detailed action description |
| field\_changes | JSON | NULL | Before/after field values |
| metadata | JSON | NULL | Additional context data |
| reason | VARCHAR | NULL | Reason for action |
| notes | TEXT | NULL | Additional notes |
| ip\_address | VARCHAR | NULL | Client IP address |
| user\_agent | TEXT | NULL | Client user agent |
| request\_id | VARCHAR | NULL | Request correlation ID |
| session\_id | VARCHAR | NULL | Session identifier |
| is\_sensitive\_data | BOOLEAN | DEFAULT FALSE | Contains sensitive data flag |
| compliance\_category | VARCHAR | NULL | Compliance classification |
| requires\_approval | BOOLEAN | DEFAULT FALSE | Requires approval flag |
| approved\_at | TIMESTAMP | NULL | Approval timestamp |
| created\_at | TIMESTAMP | NOT NULL | Action timestamp |

## **🔄 Migration Cleanup Strategy**

### **🗑 DELETE These Deprecated Migrations**

\# Safe to remove if not yet deployed to production  
rm database/migrations/\*create\_password\_resets\_table.php \# Use Laravel Fortify instead  
rm database/migrations/\*create\_cache\_table.php \# Use Redis for caching  
rm database/migrations/\*create\_cache\_locks\_table.php \# Redis handles locks  
rm database/migrations/\*create\_sessions\_table.php \# Use Redis for sessions  
rm database/migrations/\*create\_patient\_display\_sequences\_table.php \# Better patient ID strategy  
rm database/migrations/\*create\_access\_requests\_table.php \# If unused/overlapping  
rm database/migrations/\*create\_ecw\_user\_tokens\_table.php \# Redundant with centralized auth  
rm database/migrations/\*create\_ecw\_audit\_log\_table.php \# Duplicate of profile\_audit\_log  
rm database/migrations/\*create\_medicare\_mac\_validations\_table.php \# Moving to rules engine

### **✅ KEEP These Current Migrations**

1. `create_accounts_table.php` \- Core foundation  
2. `create_organizations_table.php` \- Production entity  
3. `create_facilities_table.php` \- Production entity  
4. `create_msc_products_table.php` \- Product catalog  
5. `create_msc_sales_reps_table.php` \- Sales hierarchy  
6. `create_orders_table.php` \- Order fulfillment  
7. `create_product_requests_table.php` \- Request workflow  
8. `create_product_request_products_table.php` \- Line items  
9. `create_profile_audit_log_table.php` \- Compliance ready  
10. `create_provider_credentials_table.php` \- Core credentials

### **🛠 CREATE These Missing Critical Tables**

*Ready for immediate implementation:*

## **🏗 Phase 2 Implementation: User Management** 

### **`users`**

**Purpose:** Laravel-managed user authentication (replaces current auth)

// database/migrations/2024\_12\_19\_001000\_create\_users\_table.php  
Schema::create('users', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('email')-\>unique();  
$table-\>timestamp('email\_verified\_at')-\>nullable();  
$table-\>string('password');  
$table-\>string('full\_name');  
$table-\>enum('role', \[  
'provider', 'org\_admin', 'facility\_manager',   
'msc\_rep', 'msc\_sub\_rep', 'msc\_admin', 'super\_admin'  
\]);  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>rememberToken();  
$table-\>timestamps();  
$table-\>index(\['email', 'is\_active'\]);  
$table-\>index(\['role', 'is\_active'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| email | VARCHAR | UNIQUE, NOT NULL | User's email address |
| email\_verified\_at | TIMESTAMP | NULL | Email verification timestamp |
| password | VARCHAR | NOT NULL | Hashed password (Laravel managed) |
| full\_name | VARCHAR | NOT NULL | User's full name |
| role | ENUM | NOT NULL | User role classification |
| is\_active | BOOLEAN | DEFAULT TRUE | Active status flag |
| remember\_token | VARCHAR | NULL | "Remember me" token |
| created\_at | TIMESTAMP | NOT NULL | Account creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`providers`**

**Purpose:** Healthcare provider business information

// database/migrations/2024\_12\_19\_002000\_create\_providers\_table.php  
Schema::create('providers', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('user\_id')-\>nullable();  
$table-\>string('first\_name');  
$table-\>string('last\_name');  
$table-\>string('middle\_name')-\>nullable();  
$table-\>string('suffix')-\>nullable();  
$table-\>string('credentials');  
$table-\>string('individual\_npi')-\>unique();  
$table-\>string('taxonomy\_code')-\>nullable();  
$table-\>string('contact\_email')-\>unique();  
$table-\>string('contact\_phone')-\>nullable();  
$table-\>enum('status', \['pending\_verification', 'active', 'inactive', 'suspended'\])  
\-\>default('pending\_verification');  
$table-\>timestamps();  
$table-\>softDeletes();  
$table-\>index(\['status', 'individual\_npi'\]);  
$table-\>index(\['contact\_email'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| user\_id | UUID | FK → users.id | Associated user account |
| first\_name | VARCHAR | NOT NULL | Provider first name |
| last\_name | VARCHAR | NOT NULL | Provider last name |
| middle\_name | VARCHAR | NULL | Provider middle name |
| suffix | VARCHAR | NULL | Name suffix (MD, DO, etc.) |
| credentials | VARCHAR | NOT NULL | Professional credentials |
| individual\_npi | VARCHAR | UNIQUE, INDEXED | Individual NPI number |
| taxonomy\_code | VARCHAR | NULL | Provider taxonomy code |
| contact\_email | VARCHAR | UNIQUE | Professional email |
| contact\_phone | VARCHAR | NULL | Professional phone |
| status | ENUM | NOT NULL | Verification/active status |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

### **`user_profile_details`**

**Purpose:** Extended user profile information and role associations

// database/migrations/2024\_12\_19\_003000\_create\_user\_profile\_details\_table.php  
Schema::create('user\_profile\_details', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('user\_id');  
$table-\>uuid('linked\_provider\_id')-\>nullable();  
$table-\>uuid('linked\_msc\_rep\_id')-\>nullable();  
$table-\>uuid('default\_organization\_id')-\>nullable();  
$table-\>uuid('default\_facility\_id')-\>nullable();  
$table-\>string('avatar\_url')-\>nullable();  
$table-\>json('preferences')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('user\_id')-\>references('id')-\>on('users')-\>onDelete('cascade');  
$table-\>foreign('linked\_provider\_id')-\>references('id')-\>on('providers')-\>onDelete('set null');  
$table-\>foreign('linked\_msc\_rep\_id')-\>references('id')-\>on('msc\_sales\_reps')-\>onDelete('set null');  
$table-\>foreign('default\_organization\_id')-\>references('id')-\>on('organizations')-\>onDelete('set null');  
$table-\>foreign('default\_facility\_id')-\>references('id')-\>on('facilities')-\>onDelete('set null');  
$table-\>index(\['user\_id'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| user\_id | UUID | FK → users.id | Reference to user |
| linked\_provider\_id | UUID | FK → providers.id, NULL | Link to provider record if applicable |
| linked\_msc\_rep\_id | UUID | FK → msc\_sales\_reps.id, NULL | Link to sales rep record if applicable |
| default\_organization\_id | UUID | FK → organizations.id, NULL | Default org context |
| default\_facility\_id | UUID | FK → facilities.id, NULL | Default facility context |
| avatar\_url | VARCHAR | NULL | Profile image URL |
| preferences | JSON | NULL | User preferences and settings |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`facility_provider_assignments`**

**Purpose:** Many-to-many relationship between facilities and providers

// database/migrations/2024\_12\_19\_004000\_create\_facility\_provider\_assignments\_table.php  
Schema::create('facility\_provider\_assignments', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('facility\_id');  
$table-\>uuid('provider\_id');  
$table-\>boolean('is\_primary\_location\_for\_provider')-\>default(false);  
$table-\>boolean('can\_order\_for\_facility')-\>default(true);  
$table-\>enum('assignment\_status', \['active', 'inactive'\])-\>default('active');  
$table-\>date('start\_date');  
$table-\>date('end\_date')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('facility\_id')-\>references('id')-\>on('facilities')-\>onDelete('cascade');  
$table-\>foreign('provider\_id')-\>references('id')-\>on('providers')-\>onDelete('cascade');  
$table-\>unique(\['facility\_id', 'provider\_id'\], 'facility\_provider\_unique');  
$table-\>index(\['provider\_id', 'assignment\_status'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| facility\_id | UUID | FK → facilities.id | Assigned facility |
| provider\_id | UUID | FK → providers.id | Provider being assigned |
| is\_primary\_location\_for\_provider | BOOLEAN | DEFAULT FALSE | Primary location flag |
| can\_order\_for\_facility | BOOLEAN | DEFAULT TRUE | Ordering privileges flag |
| assignment\_status | ENUM | NOT NULL | 'active', 'inactive' |
| start\_date | DATE | NOT NULL | Assignment start date |
| end\_date | DATE | NULL | Assignment end date |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`addresses`**

**Purpose:** Standardized address storage for various entities

// database/migrations/2024\_12\_19\_005000\_create\_addresses\_table.php  
Schema::create('addresses', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('street\_1');  
$table-\>string('street\_2')-\>nullable();  
$table-\>string('city');  
$table-\>string('state\_province');  
$table-\>string('postal\_code');  
$table-\>string('country\_code', 3)-\>default('USA');  
$table-\>enum('address\_type', \['billing', 'shipping', 'mailing', 'practice\_location'\]);  
$table-\>boolean('is\_primary')-\>default(false);  
$table-\>uuid('addressable\_id');  
$table-\>string('addressable\_type'); // 'Organization', 'Facility', 'Provider'  
$table-\>timestamps();  
$table-\>softDeletes();  
$table-\>index(\['addressable\_id', 'addressable\_type'\]);  
$table-\>index(\['address\_type', 'is\_primary'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| street\_1 | VARCHAR | NOT NULL | Address line 1 |
| street\_2 | VARCHAR | NULL | Address line 2 |
| city | VARCHAR | NOT NULL | City |
| state\_province | VARCHAR | NOT NULL | State/Province |
| postal\_code | VARCHAR | NOT NULL | ZIP/Postal code |
| country\_code | VARCHAR | NOT NULL | Country code |
| address\_type | ENUM | NOT NULL | Address classification |
| is\_primary | BOOLEAN | DEFAULT FALSE | Primary address flag |
| addressable\_id | UUID | NOT NULL | ID of related entity |
| addressable\_type | VARCHAR | NOT NULL | Type of related entity |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |
| deleted\_at | TIMESTAMP | NULL | Soft delete timestamp |

## **💰 Phase 3 Implementation: Enhanced Commerce**   **`manufacturers`**

**Purpose:** Product manufacturer information (separate from embedded field)

// database/migrations/2025\_01\_15\_001000\_create\_manufacturers\_table.php  
Schema::create('manufacturers', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('name');  
$table-\>json('contact\_info')-\>nullable();  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>timestamps();  
$table-\>index(\['name', 'is\_active'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| name | VARCHAR | NOT NULL | Manufacturer name |
| contact\_info | JSON | NULL | Contact information |
| is\_active | BOOLEAN | DEFAULT TRUE | Active status |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`commission_rules`**

**Purpose:** Flexible commission rule engine

// database/migrations/2025\_01\_15\_002000\_create\_commission\_rules\_table.php  
Schema::create('commission\_rules', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>enum('target\_type', \['product', 'manufacturer', 'category'\]);  
$table-\>string('target\_id');  
$table-\>decimal('percentage\_rate', 5, 2);  
$table-\>date('valid\_from');  
$table-\>date('valid\_to')-\>nullable();  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>timestamps();  
$table-\>index(\['target\_type', 'target\_id', 'is\_active'\]);  
$table-\>index(\['valid\_from', 'valid\_to'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| target\_type | ENUM | NOT NULL | 'product', 'manufacturer', 'category' |
| target\_id | VARCHAR | NOT NULL | ID of target entity |
| percentage\_rate | DECIMAL(5,2) | NOT NULL | Commission percentage |
| valid\_from | DATE | NOT NULL | Effective start date |
| valid\_to | DATE | NULL | Expiration date |
| is\_active | BOOLEAN | DEFAULT TRUE | Active rule flag |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`commission_records`**

**Purpose:** Individual commission transaction tracking

// database/migrations/2025\_01\_15\_003000\_create\_commission\_records\_table.php  
Schema::create('commission\_records', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('order\_item\_id');  
$table-\>uuid('rep\_id');  
$table-\>decimal('amount', 10, 2);  
$table-\>enum('type', \['direct\_rep', 'sub\_rep\_share', 'parent\_rep\_share'\]);  
$table-\>enum('status', \['pending', 'approved', 'included\_in\_payout', 'paid'\]);  
$table-\>timestamp('calculation\_date');  
$table-\>uuid('commission\_payout\_id')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('rep\_id')-\>references('id')-\>on('msc\_sales\_reps')-\>onDelete('cascade');  
$table-\>foreign('commission\_payout\_id')-\>references('id')-\>on('commission\_payouts')-\>onDelete('set null');  
$table-\>index(\['rep\_id', 'status'\]);  
$table-\>index(\['calculation\_date'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| order\_item\_id | UUID | FK → order\_items.id | Related order item |
| rep\_id | UUID | FK → msc\_sales\_reps.id | Sales rep earning commission |
| amount | DECIMAL(10,2) | NOT NULL | Commission amount |
| type | ENUM | NOT NULL | Commission type classification |
| status | ENUM | NOT NULL | Payment processing status |
| calculation\_date | TIMESTAMP | NOT NULL | When commission was calculated |
| commission\_payout\_id | UUID | FK → commission\_payouts.id, NULL | Related payout record |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

### **`commission_payouts`**

**Purpose:** Batch commission payment tracking

// database/migrations/2025\_01\_15\_004000\_create\_commission\_payouts\_table.php  
Schema::create('commission\_payouts', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('rep\_id');  
$table-\>date('period\_start');  
$table-\>date('period\_end');  
$table-\>decimal('total\_amount', 12, 2);  
$table-\>enum('status', \['calculated', 'approved', 'processed'\]);  
$table-\>string('payment\_reference')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('rep\_id')-\>references('id')-\>on('msc\_sales\_reps')-\>onDelete('cascade');  
$table-\>index(\['rep\_id', 'period\_start', 'period\_end'\]);  
$table-\>index(\['status'\]);  
});

| Column Type Constraints Description |
| :---- |

| id | UUID | PK | Unique identifier |
| :---- | :---- | :---- | :---- |
| rep\_id | UUID | FK → msc\_sales\_reps.id | Sales rep receiving payout |
| period\_start | DATE | NOT NULL | Start of commission period |
| period\_end | DATE | NOT NULL | End of commission period |
| total\_amount | DECIMAL(12,2) | NOT NULL | Total payout amount |
| status | ENUM | NOT NULL | Processing status |
| payment\_reference | VARCHAR | NULL | Payment reference number |
| created\_at | TIMESTAMP | NOT NULL | Record creation timestamp |
| updated\_at | TIMESTAMP | NOT NULL | Record update timestamp |

## **🧠 Phase 4 Implementation: Clinical Intelligence (Q3 2025\)**

### **`coe_opportunity_rules`**

**Purpose:** Clinical opportunity identification rules

// database/migrations/2025\_04\_15\_001000\_create\_coe\_opportunity\_rules\_table.php  
Schema::create('coe\_opportunity\_rules', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('rule\_name');  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>json('primary\_icd10\_codes')-\>nullable();  
$table-\>enum('wound\_type', \['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER'\])-\>nullable();  
$table-\>enum('wound\_depth', \['PartialThickness', 'FullThickness', 'DeepToStructure'\])-\>nullable();  
$table-\>decimal('wound\_size\_min\_sqcm', 8, 2)-\>nullable();  
$table-\>decimal('wound\_size\_max\_sqcm', 8, 2)-\>nullable();  
$table-\>integer('wound\_duration\_min\_weeks')-\>nullable();  
$table-\>json('exposed\_structures\_present')-\>nullable();  
$table-\>enum('infection\_status', \['None', 'ControlledSuperficial', 'DeepOsteo'\])-\>nullable();  
$table-\>json('prior\_treatment\_failed')-\>nullable();  
$table-\>json('patient\_comorbidities\_match')-\>nullable();  
$table-\>json('mac\_validation\_flags\_to\_consider')-\>nullable();  
$table-\>json('payer\_type\_preference')-\>nullable();  
$table-\>string('suggested\_cpt\_hcpcs');  
$table-\>text('suggested\_description');  
$table-\>decimal('suggested\_revenue\_value', 10, 2)-\>nullable();  
$table-\>text('outcome\_benefit\_description')-\>nullable();  
$table-\>text('documentation\_guidance')-\>nullable();  
$table-\>enum('rule\_status', \['draft', 'active', 'inactive', 'archived'\])-\>default('active');  
$table-\>uuid('created\_by\_user\_id');  
$table-\>uuid('updated\_by\_user\_id');  
$table-\>timestamps();  
$table-\>foreign('created\_by\_user\_id')-\>references('id')-\>on('users');  
$table-\>foreign('updated\_by\_user\_id')-\>references('id')-\>on('users');  
$table-\>index(\['rule\_status', 'is\_active'\]);  
$table-\>index(\['wound\_type', 'wound\_depth'\]);  
});

### **`msc_product_recommendation_rules`**

**Purpose:** Product recommendation engine rules

// database/migrations/2025\_04\_15\_002000\_create\_msc\_product\_recommendation\_rules\_table.php  
Schema::create('msc\_product\_recommendation\_rules', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('rule\_name');  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>json('primary\_icd10\_codes')-\>nullable();  
$table-\>enum('wound\_type', \['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER'\])-\>nullable();  
$table-\>enum('wound\_depth', \['PartialThickness', 'FullThickness', 'DeepToStructure'\])-\>nullable();  
$table-\>decimal('wound\_size\_min\_sqcm', 8, 2)-\>nullable();  
$table-\>decimal('wound\_size\_max\_sqcm', 8, 2)-\>nullable();  
$table-\>integer('wound\_duration\_min\_weeks')-\>nullable();  
$table-\>json('exposed\_structures\_present')-\>nullable();  
$table-\>enum('infection\_status', \['None', 'ControlledSuperficial', 'DeepOsteo'\])-\>nullable();  
$table-\>json('prior\_treatment\_failed')-\>nullable();  
$table-\>json('patient\_comorbidities\_match')-\>nullable();  
$table-\>json('mac\_validation\_flags\_to\_consider')-\>nullable();  
$table-\>json('payer\_type\_preference')-\>nullable();  
$table-\>json('recommended\_msc\_product\_qcodes\_ranked'); // Ranked recommendations  
$table-\>json('reasoning\_templates'); // Explanation templates  
$table-\>string('default\_size\_suggestion\_key')-\>nullable();  
$table-\>uuid('created\_by\_user\_id');  
$table-\>uuid('updated\_by\_user\_id');  
$table-\>timestamps();  
$table-\>foreign('created\_by\_user\_id')-\>references('id')-\>on('users');  
$table-\>foreign('updated\_by\_user\_id')-\>references('id')-\>on('users');  
$table-\>index(\['rule\_status', 'is\_active'\]);  
$table-\>index(\['wound\_type', 'wound\_depth'\]);  
});

### **`cms_fee_schedules`**

**Purpose:** CMS fee schedule data for revenue calculations

// database/migrations/2025\_04\_15\_003000\_create\_cms\_fee\_schedules\_table.php  
Schema::create('cms\_fee\_schedules', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('cpt\_hcpcs\_code');  
$table-\>text('description')-\>nullable();  
$table-\>string('mac\_jurisdiction')-\>nullable();  
$table-\>decimal('payment\_amount', 10, 2);  
$table-\>date('effective\_date');  
$table-\>date('end\_date')-\>nullable();  
$table-\>timestamps();  
$table-\>index(\['cpt\_hcpcs\_code', 'effective\_date'\]);  
$table-\>index(\['mac\_jurisdiction', 'effective\_date'\]);  
});

## **🔗 Phase 5 Implementation: Integration Platform**  **`patient_api_transactions`**

**Purpose:** Track API calls to external payer systems (PHI references only)

// database/migrations/2025\_07\_15\_001000\_create\_patient\_api\_transactions\_table.php  
Schema::create('patient\_api\_transactions', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('order\_id')-\>nullable();  
$table-\>string('patient\_fhir\_id'); // PHI Reference  
$table-\>enum('api\_type', \['eligibility', 'cost\_estimate', 'prior\_auth\_submit', 'prior\_auth\_status', 'care\_reminder'\]);  
$table-\>enum('api\_vendor', \['optum', 'availity', 'office\_ally'\]);  
$table-\>timestamp('request\_timestamp');  
$table-\>timestamp('response\_timestamp')-\>nullable();  
$table-\>integer('status\_code\_received')-\>nullable();  
$table-\>string('external\_trace\_id')-\>nullable();  
$table-\>string('internal\_request\_payload\_summary\_hash');  
$table-\>string('internal\_normalized\_response\_summary\_hash')-\>nullable();  
$table-\>string('raw\_request\_payload\_reference\_path')-\>nullable();  
$table-\>string('raw\_response\_payload\_reference\_path')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('order\_id')-\>references('id')-\>on('orders')-\>onDelete('set null');  
$table-\>index(\['patient\_fhir\_id', 'api\_type'\]);  
$table-\>index(\['api\_vendor', 'request\_timestamp'\]);  
});

### **`patient_care_reminders`**

**Purpose:** Care reminders from payer systems (PHI references only)

// database/migrations/2025\_07\_15\_002000\_create\_patient\_care\_reminders\_table.php  
Schema::create('patient\_care\_reminders', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('patient\_fhir\_id'); // PHI Reference  
$table-\>string('availity\_reminder\_id')-\>nullable();  
$table-\>string('reminder\_type');  
$table-\>text('reminder\_description');  
$table-\>date('due\_date')-\>nullable();  
$table-\>enum('status', \['active', 'addressed', 'dismissed'\])-\>default('active');  
$table-\>text('action\_taken')-\>nullable();  
$table-\>date('action\_date')-\>nullable();  
$table-\>uuid('action\_provider\_id')-\>nullable();  
$table-\>timestamp('last\_checked\_at');  
$table-\>timestamps();  
$table-\>foreign('action\_provider\_id')-\>references('id')-\>on('providers')-\>onDelete('set null');  
$table-\>index(\['patient\_fhir\_id', 'status'\]);  
$table-\>index(\['due\_date', 'status'\]);  
});

## **📄 Phase 6 Implementation: Document Automation**   **`document_templates`**

**Purpose:** DocuSeal template management

// database/migrations/2025\_10\_15\_001000\_create\_document\_templates\_table.php  
Schema::create('document\_templates', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>string('template\_name');  
$table-\>uuid('manufacturer\_id')-\>nullable();  
$table-\>enum('document\_type', \['onboarding', 'order\_form', 'ivr\_form', 'generic'\]);  
$table-\>string('docuseal\_external\_template\_id');  
$table-\>json('field\_mappings'); // Mapping rules for auto-fill  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>integer('version')-\>default(1);  
$table-\>text('description')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('manufacturer\_id')-\>references('id')-\>on('manufacturers')-\>onDelete('set null');  
$table-\>index(\['document\_type', 'is\_active'\]);  
$table-\>index(\['manufacturer\_id', 'is\_active'\]);  
});

### **`docuseal_submissions`**

**Purpose:** Track DocuSeal document submissions

// database/migrations/2025\_10\_15\_002000\_create\_docuseal\_submissions\_table.php  
Schema::create('docuseal\_submissions', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('order\_id')-\>nullable();  
$table-\>uuid('customer\_id')-\>nullable();  
$table-\>string('docuseal\_submission\_id');  
$table-\>uuid('msc\_template\_id');  
$table-\>string('docuseal\_template\_id');  
$table-\>uuid('generated\_by\_user\_id');  
$table-\>enum('status', \['pending', 'completed', 'expired', 'cancelled'\]);  
$table-\>timestamp('generated\_at');  
$table-\>timestamp('completed\_at')-\>nullable();  
$table-\>string('link\_to\_document')-\>nullable();  
$table-\>timestamps();  
$table-\>foreign('order\_id')-\>references('id')-\>on('orders')-\>onDelete('set null');  
$table-\>foreign('customer\_id')-\>references('id')-\>on('organizations')-\>onDelete('set null');  
$table-\>foreign('msc\_template\_id')-\>references('id')-\>on('document\_templates');  
$table-\>foreign('generated\_by\_user\_id')-\>references('id')-\>on('users');  
$table-\>index(\['status', 'generated\_at'\]);  
$table-\>index(\['order\_id'\]);  
});

## **🤖 Phase 7 Implementation: AI Enhancement**  **`knowledge_sources`**

**Purpose:** Knowledge base content management for RAG Agent

// database/migrations/2026\_01\_15\_001000\_create\_knowledge\_sources\_table.php  
Schema::create('knowledge\_sources', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>enum('source\_type', \['product\_info', 'clinical\_guideline', 'mac\_policy', 'platform\_doc'\]);  
$table-\>string('title');  
$table-\>text('content');  
$table-\>string('url')-\>nullable();  
$table-\>timestamp('last\_updated');  
$table-\>boolean('is\_active')-\>default(true);  
$table-\>timestamps();  
$table-\>index(\['source\_type', 'is\_active'\]);  
$table-\>index(\['last\_updated'\]);  
$table-\>fullText(\['title', 'content'\]); // Full-text search  
});

### **`vector_embeddings`**

**Purpose:** RAG Agent knowledge base with vector search

// database/migrations/2026\_01\_15\_002000\_create\_vector\_embeddings\_table.php  
use Illuminate\\Support\\Facades\\DB;

Schema::create('vector\_embeddings', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>text('content\_chunk');  
$table-\>vector('embedding', 1536); // OpenAI embedding dimension  
$table-\>string('source\_type');  
$table-\>string('source\_id');  
$table-\>json('metadata')-\>nullable();  
$table-\>timestamps();  
$table-\>index(\['source\_type', 'source\_id'\]);  
});

// Create index for vector similarity search (requires pgvector extension)  
DB::statement('CREATE INDEX vector\_embeddings\_embedding\_idx ON vector\_embeddings USING ivfflat (embedding vector\_cosine\_ops)');

### **`coe_audit_log`**

**Purpose:** Clinical Opportunity Engine audit trail

// database/migrations/2026\_01\_15\_003000\_create\_coe\_audit\_log\_table.php  
Schema::create('coe\_audit\_log', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('order\_id')-\>nullable();  
$table-\>string('patient\_fhir\_id')-\>nullable(); // PHI Reference  
$table-\>uuid('opportunity\_rule\_id')-\>nullable();  
$table-\>uuid('provider\_id')-\>nullable();  
$table-\>enum('action', \['presented', 'accepted', 'dismissed', 'implemented'\]);  
$table-\>timestamp('action\_timestamp');  
$table-\>uuid('action\_user\_id');  
$table-\>text('notes')-\>nullable();  
$table-\>timestamp('created\_at');  
$table-\>foreign('order\_id')-\>references('id')-\>on('orders')-\>onDelete('set null');  
$table-\>foreign('opportunity\_rule\_id')-\>references('id')-\>on('coe\_opportunity\_rules')-\>onDelete('set null');  
$table-\>foreign('provider\_id')-\>references('id')-\>on('providers')-\>onDelete('set null');  
$table-\>foreign('action\_user\_id')-\>references('id')-\>on('users');  
$table-\>index(\['patient\_fhir\_id', 'action\_timestamp'\]);  
$table-\>index(\['action', 'action\_timestamp'\]);  
});

## **📊 Performance Optimizations**

### **Critical Indexes for Current Production**

\-- Product requests (heaviest queried table)  
CREATE INDEX CONCURRENTLY idx\_product\_requests\_status\_facility   
ON product\_requests(order\_status, facility\_id, created\_at DESC)  
WHERE deleted\_at IS NULL;

CREATE INDEX CONCURRENTLY idx\_product\_requests\_provider\_status   
ON product\_requests(provider\_id, order\_status)  
WHERE deleted\_at IS NULL AND order\_status IN ('pending', 'submitted', 'approved');

CREATE INDEX CONCURRENTLY idx\_product\_requests\_eligibility\_batch   
ON product\_requests(eligibility\_status, payer\_id)  
WHERE eligibility\_status IN ('not\_checked', 'pending');

\-- Orders optimization  
CREATE INDEX CONCURRENTLY idx\_orders\_facility\_status   
ON orders(facility\_id, status, date\_of\_service)  
WHERE deleted\_at IS NULL;

CREATE INDEX CONCURRENTLY idx\_orders\_sales\_rep\_payment   
ON orders(sales\_rep\_id, payment\_status, created\_at DESC)  
WHERE deleted\_at IS NULL;

\-- Product requests to products join optimization  
CREATE INDEX CONCURRENTLY idx\_product\_request\_products\_request   
ON product\_request\_products(product\_request\_id, product\_id);

\-- MSC products search optimization  
CREATE INDEX CONCURRENTLY idx\_msc\_products\_active\_category   
ON msc\_products(category\_id, is\_active)  
WHERE deleted\_at IS NULL AND is\_active \= true;

CREATE INDEX CONCURRENTLY idx\_msc\_products\_search   
ON msc\_products USING gin(to\_tsvector('english', name || ' ' || description))  
WHERE deleted\_at IS NULL AND is\_active \= true;

\-- Sales reps hierarchy  
CREATE INDEX CONCURRENTLY idx\_msc\_sales\_reps\_hierarchy   
ON msc\_sales\_reps(parent\_rep\_id, is\_active)  
WHERE deleted\_at IS NULL;

\-- Facilities by organization  
CREATE INDEX CONCURRENTLY idx\_facilities\_org\_active   
ON facilities(organization\_id, active)  
WHERE deleted\_at IS NULL;

\-- Audit log performance  
CREATE INDEX CONCURRENTLY idx\_profile\_audit\_log\_entity\_time   
ON profile\_audit\_log(entity\_type, entity\_id, created\_at DESC);

CREATE INDEX CONCURRENTLY idx\_profile\_audit\_log\_user\_time   
ON profile\_audit\_log(user\_id, created\_at DESC);

### **Computed Columns for Enhanced Performance**

\-- Product requests enhancements  
ALTER TABLE product\_requests   
ADD COLUMN days\_since\_submission INTEGER GENERATED ALWAYS AS (  
CASE   
WHEN submitted\_at IS NOT NULL THEN EXTRACT(DAYS FROM (NOW() \- submitted\_at))  
ELSE NULL   
END  
) STORED;

ALTER TABLE product\_requests   
ADD COLUMN is\_urgent BOOLEAN GENERATED ALWAYS AS (  
(expected\_service\_date \<= CURRENT\_DATE \+ INTERVAL '3 days'   
AND order\_status IN ('pending', 'submitted'))  
) STORED;

ALTER TABLE product\_requests   
ADD COLUMN processing\_time\_days INTEGER GENERATED ALWAYS AS (  
CASE   
WHEN approved\_at IS NOT NULL AND submitted\_at IS NOT NULL   
THEN EXTRACT(DAYS FROM (approved\_at \- submitted\_at))  
ELSE NULL   
END  
) STORED;

\-- Orders enhancements  
ALTER TABLE orders   
ADD COLUMN profit\_margin DECIMAL(5,2) GENERATED ALWAYS AS (  
CASE   
WHEN total\_amount \> 0 THEN   
((expected\_reimbursement \- total\_amount) / total\_amount \* 100\)  
ELSE NULL   
END  
) STORED;

ALTER TABLE orders   
ADD COLUMN is\_overdue BOOLEAN GENERATED ALWAYS AS (  
(expected\_collection\_date \< CURRENT\_DATE AND payment\_status \!= 'paid')  
) STORED;

\-- MSC products enhancements  
ALTER TABLE msc\_products   
ADD COLUMN searchable\_text TEXT GENERATED ALWAYS AS (  
LOWER(name || ' ' || COALESCE(description, '') || ' ' || COALESCE(manufacturer, '') || ' ' || COALESCE(q\_code, ''))  
) STORED;

\-- Sales reps enhancements  
ALTER TABLE msc\_sales\_reps   
ADD COLUMN is\_parent\_rep BOOLEAN GENERATED ALWAYS AS (  
(parent\_rep\_id IS NULL AND is\_active \= true)  
) STORED;

### **Materialized Views for Dashboard Performance**

\-- Facility dashboard view  
CREATE MATERIALIZED VIEW facility\_dashboard\_mv AS  
SELECT   
f.id as facility\_id,  
f.name,  
f.organization\_id,  
COUNT(pr.id) as total\_requests,  
COUNT(pr.id) FILTER (WHERE pr.order\_status \= 'pending') as pending\_requests,  
COUNT(pr.id) FILTER (WHERE pr.eligibility\_status \= 'not\_checked') as need\_eligibility,  
COUNT(pr.id) FILTER (WHERE pr.created\_at \>= CURRENT\_DATE \- INTERVAL '30 days') as requests\_last\_30\_days,  
COALESCE(SUM(pr.total\_order\_value), 0\) as total\_value,  
AVG(pr.processing\_time\_days) as avg\_processing\_time  
FROM facilities f  
LEFT JOIN product\_requests pr ON f.id \= pr.facility\_id   
AND pr.deleted\_at IS NULL  
AND pr.created\_at \>= CURRENT\_DATE \- INTERVAL '90 days'  
WHERE f.deleted\_at IS NULL AND f.active \= true  
GROUP BY f.id, f.name, f.organization\_id;

CREATE UNIQUE INDEX ON facility\_dashboard\_mv (facility\_id);

\-- Sales rep leaderboard  
CREATE MATERIALIZED VIEW sales\_rep\_leaderboard\_mv AS  
SELECT   
sr.id as rep\_id,  
sr.name,  
sr.territory,  
COUNT(o.id) as orders\_count,  
COALESCE(SUM(o.total\_amount), 0\) as total\_sales,  
COALESCE(SUM(o.msc\_commission), 0\) as total\_commission,  
COUNT(DISTINCT o.facility\_id) as unique\_facilities,  
AVG(o.total\_amount) as avg\_order\_value,  
RANK() OVER (ORDER BY COALESCE(SUM(o.msc\_commission), 0\) DESC) as commission\_rank  
FROM msc\_sales\_reps sr  
LEFT JOIN orders o ON sr.id \= o.sales\_rep\_id   
AND o.deleted\_at IS NULL  
AND o.created\_at \>= date\_trunc('month', CURRENT\_DATE)  
WHERE sr.deleted\_at IS NULL AND sr.is\_active \= true  
GROUP BY sr.id, sr.name, sr.territory;

CREATE UNIQUE INDEX ON sales\_rep\_leaderboard\_mv (rep\_id);

## **🔗 Complete Entity Relationships**

erDiagram  
%% Current Production (Phase 1\)  
accounts ||--o{ organizations : "has many"  
organizations ||--o{ facilities : "has many"  
facilities ||--o{ product\_requests : "receives"  
facilities ||--o{ orders : "fulfills"  
msc\_sales\_reps ||--o{ msc\_sales\_reps : "manages sub-reps"  
msc\_sales\_reps ||--o{ orders : "assigned to"  
msc\_sales\_reps ||--o{ product\_requests : "acquires"  
product\_requests ||--o{ product\_request\_products : "contains"  
msc\_products ||--o{ product\_request\_products : "requested in"  
permissions ||--o{ permission\_role : "granted via"  
%% Phase 2: User Management  
users ||--o{ user\_profile\_details : "has profile"  
users ||--o{ providers : "may be"  
providers ||--o{ facility\_provider\_assignments : "assigned to"  
facilities ||--o{ facility\_provider\_assignments : "hosts"  
providers ||--o{ provider\_credentials : "has many"  
%% Phase 3: Enhanced Commerce  
manufacturers ||--o{ msc\_products : "manufactures"  
msc\_sales\_reps ||--o{ commission\_records : "earns"  
commission\_records }o--|| commission\_payouts : "included in"  
commission\_rules ||--o{ commission\_records : "governs"  
organizations ||--o{ addresses : "has many"  
facilities ||--o{ addresses : "has many"  
providers ||--o{ addresses : "has many"  
%% Phase 4: Clinical Intelligence  
users ||--o{ coe\_opportunity\_rules : "creates"  
users ||--o{ msc\_product\_recommendation\_rules : "creates"  
coe\_opportunity\_rules ||--o{ coe\_audit\_log : "triggers"  
providers ||--o{ coe\_audit\_log : "interacts with"  
msc\_product\_recommendation\_rules ||--o{ product\_requests : "suggests for"  
%% Phase 5: Integration  
orders ||--o{ patient\_api\_transactions : "triggers"  
providers ||--o{ patient\_care\_reminders : "acts on"  
%% Phase 6: Documents  
manufacturers ||--o{ document\_templates : "may have"  
document\_templates ||--o{ docuseal\_submissions : "generates"  
orders ||--o{ docuseal\_submissions : "creates"  
users ||--o{ docuseal\_submissions : "generates"  
%% Phase 7: AI  
knowledge\_sources ||--o{ vector\_embeddings : "chunks into"  
coe\_opportunity\_rules ||--o{ coe\_potential\_opportunities\_log : "may suggest"

## **🛡 Security & Compliance**

### **PHI Reference Architecture**

// Proper PHI reference pattern  
interface ProductRequest {  
id: string;  
patient\_fhir\_id: string; // 🔒 PHI Reference \- not actual PHI  
azure\_order\_checklist\_fhir\_id: string; // 🔒 PHI Reference  
clinical\_summary: string; // ✅ Non-PHI summary only  
// ... other non-PHI fields  
}

// PHI retrieval pattern  
async function getPatientContext(patient\_fhir\_id: string): Promise\<PatientContext\> {  
// Fetch actual PHI from Azure Health Data Services  
const fhirClient \= await azureAuth.getFhirClient();  
const patient \= await fhirClient.read('Patient', patient\_fhir\_id);  
// Log PHI access for audit  
await auditLog.logPhiAccess({  
entity\_type: 'Patient',  
entity\_id: patient\_fhir\_id,  
action\_type: 'read',  
user\_id: getCurrentUser().id  
});  
return transformPatientData(patient);  
}

### **Audit Trail Requirements**

All sensitive operations must be logged in `profile_audit_log`:

\-- Example audit log entry  
INSERT INTO profile\_audit\_log (  
entity\_type, entity\_id, user\_id, action\_type,   
field\_changes, is\_sensitive\_data, compliance\_category  
) VALUES (  
'ProductRequest', 'req-123', 'user-456', 'status\_change',  
'{"old\_status": "pending", "new\_status": "approved"}',  
false, 'workflow'  
);

### **Data Encryption Requirements**

1. **At Rest**: Database-level encryption for all sensitive fields  
2. **In Transit**: TLS 1.3 for all API communications  
3. **Field-Level**: Encrypt `dea_number`, `tax_id` fields in PostgreSQL  
4. **PHI**: All actual PHI encrypted in Azure Health Data Services

## **🔧 Development Guidelines**

### **Naming Conventions**

1. **Tables**: Snake case, plural nouns (`product_requests`, `facility_provider_assignments`)  
2. **Columns**: Snake case (`created_at`, `facility_id`, `patient_fhir_id`)  
3. **Foreign Keys**: `{table}_id` format (`facility_id`, `provider_id`)  
4. **Indexes**: `idx_{table}_{columns}` format (`idx_orders_sales_rep_payment`)  
5. **Enums**: Snake case values (`pending_verification`, `not_checked`)

### **Data Types & Standards**

1. **Primary Keys**: UUID for business entities, BIGINT for system tables  
2. **Timestamps**: Laravel's `created_at`/`updated_at` pattern  
3. **Money**: `DECIMAL(12,2)` for currency amounts  
4. **Percentages**: `DECIMAL(5,2)` for rates and percentages  
5. **JSON**: Use for flexible metadata, not structured relational data  
6. **Soft Deletes**: Include `deleted_at` for all business entities  
7. **PHI References**: Always `VARCHAR` for FHIR resource IDs

### **Query Performance Best Practices**

\-- ✅ GOOD: Use proper indexing and WHERE clauses  
SELECT pr.\*, f.name as facility\_name   
FROM product\_requests pr  
JOIN facilities f ON pr.facility\_id \= f.id  
WHERE pr.deleted\_at IS NULL   
AND pr.order\_status \= 'pending'  
AND pr.created\_at \>= CURRENT\_DATE \- INTERVAL '30 days'  
ORDER BY pr.created\_at DESC;

\-- ❌ BAD: No WHERE clause on soft deletes, no index usage  
SELECT \* FROM product\_requests   
WHERE order\_status LIKE '%pending%'  
ORDER BY created\_at;

### **Migration Best Practices**

// ✅ GOOD: Proper migration with indexes and foreign keys  
Schema::create('example\_table', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('parent\_id');  
$table-\>string('status');  
$table-\>timestamps();  
$table-\>softDeletes();  
$table-\>foreign('parent\_id')-\>references('id')-\>on('parent\_table')-\>onDelete('cascade');  
$table-\>index(\['status', 'created\_at'\]);  
$table-\>index(\['parent\_id', 'deleted\_at'\]);  
});

// ❌ BAD: Missing indexes and constraints  
Schema::create('example\_table', function (Blueprint $table) {  
$table-\>uuid('id')-\>primary();  
$table-\>uuid('parent\_id');  
$table-\>string('status');  
$table-\>timestamps();  
});

## **📈 Monitoring & Maintenance**

### **Performance Monitoring Queries**

\-- Check index usage  
SELECT   
schemaname,  
tablename,  
indexname,  
idx\_scan as times\_used,  
idx\_tup\_read as tuples\_read  
FROM pg\_stat\_user\_indexes   
WHERE schemaname \= 'public'  
ORDER BY idx\_scan DESC;

\-- Monitor slow queries  
SELECT   
query,  
calls,  
total\_time,  
mean\_time,  
rows  
FROM pg\_stat\_statements   
WHERE query LIKE '%product\_requests%'   
ORDER BY mean\_time DESC   
LIMIT 10;

### **Data Quality Checks**

\-- Orphaned records check  
SELECT COUNT(\*) as orphaned\_product\_requests  
FROM product\_requests pr  
LEFT JOIN facilities f ON pr.facility\_id \= f.id  
WHERE f.id IS NULL AND pr.deleted\_at IS NULL;

\-- Invalid status combinations  
SELECT COUNT(\*) as invalid\_status\_combinations  
FROM product\_requests   
WHERE order\_status \= 'approved'   
AND approved\_at IS NULL   
AND deleted\_at IS NULL;

### **Regular Maintenance Tasks**

\-- Update table statistics (weekly)  
ANALYZE;

\-- Refresh materialized views (hourly)  
REFRESH MATERIALIZED VIEW CONCURRENTLY facility\_dashboard\_mv;  
REFRESH MATERIALIZED VIEW CONCURRENTLY sales\_rep\_leaderboard\_mv;

\-- Clean up old audit logs (monthly)  
DELETE FROM profile\_audit\_log   
WHERE created\_at \< CURRENT\_DATE \- INTERVAL '7 years'  
AND compliance\_category \!= 'hipaa\_required';

This complete documentation serves as the definitive reference for the MSC-MVP Wound Care Platform's non-PHI data models, providing everything needed for current operations and future development through 2026.​​​​​​​​​​​​​​​​

