# MSC Wound Portal - Development Task List

## Infrastructure & Setup

### Database & Storage
- [x] Supabase PostgreSQL setup and configuration
- [x] Database migrations for core tables (users, accounts, organizations, contacts, facilities)
- [x] Database migrations for MSC-specific tables (msc_products, msc_sales_reps, orders, order_items)
- [x] Database migrations for commission system (commission_records, commission_payouts, commission_rules)
- [x] Supabase S3-compatible storage configuration
- [x] File storage service for non-PHI documents
- [x] TypeScript types generation for database schema
- [ ] Row Level Security (RLS) policies implementation
- [ ] Azure Health Data Services (FHIR) integration setup
- [ ] FHIR resource templates for PHI data

### Authentication & Authorization
- [x] Laravel Sanctum authentication setup (fixed middleware for Laravel 11+)
- [x] Login/logout functionality
- [x] User registration and management
- [x] Basic user roles (owner/user)
- [x] Database sessions configuration
- [✅] **Modern Login Screen & Access Request System (Completed)**
  - [✅] Modernized login page with MSC branding and gradient design
  - [✅] MSC logo integration and professional styling
  - [✅] Access request database migration (access_requests table)
  - [✅] AccessRequest model with role-specific field management
  - [✅] AccessRequestController with full CRUD operations
  - [✅] Dynamic role-based request form (React component)
  - [✅] Role-specific field validation and requirements
  - [✅] Public access request routes and admin management routes
  - [✅] Integration between login page and request access system
  - [ ] Admin interface for reviewing and approving access requests (Phase 3)
  - [ ] Email notifications for request status updates
  - [ ] Automatic user account creation upon approval
- [✅] **MSC Portal Role-Based Menu Structure & Financial Restrictions (Completed)**
  - [✅] Complete role-based navigation system for all 6 roles
  - [✅] Role-specific menu generation with hierarchical structure
  - [✅] Dynamic menu filtering based on user permissions
  - [✅] Financial access control middleware implementation
  - [✅] Critical Office Manager financial restrictions enforced
- [ ] Provider credentials management
- [ ] Facility-based access permissions
- [ ] HIPAA-compliant audit logging

## Core Business Logic

### User & Organization Management
- [x] User CRUD operations
- [x] Organization management
- [x] Contact management
- [x] Basic facility structure
- [x] Organization model relationships and fillable fields
- [x] Account model with proper relationships
- [x] User model with account relationships
- [x] Organization test suite fixes and completion
- [✅] **MSC Portal User Role System Implementation (6 Roles) - Database Foundation Complete**
  - [✅] Dedicated user roles table/enum (Provider, Office Manager, MSC Rep, MSC SubRep, MSC Admin, SuperAdmin)
  - [✅] UserRole model with role constants and helper methods
  - [✅] User model updated with role relationships and helper methods
  - [✅] Role hierarchy system (0=highest privilege)
  - [✅] Financial access restrictions (Office Manager cannot see discounts)
  - [✅] Dashboard configuration per role
  - [ ] Role-based access control (RBAC) implementation for all 6 roles (Delegated to Main Technical Dev)
  - [ ] Provider portal user management (Healthcare providers/clinicians)
  - [ ] Office Manager portal user management (Facility-attached with provider oversight)
  - [ ] MSC Rep portal user management (Primary sales representatives)
  - [ ] MSC SubRep portal user management (Sub-representatives with limited access)
  - [ ] MSC Admin portal user management (MSC internal administrators)
  - [ ] SuperAdmin portal user management (Highest level system access)
  - [✅] **Facility-User Relationships**
    - [✅] Office Manager to Facility attachment system (Many-to-Many pivot table)
    - [✅] Provider to Facility attachment system (Many-to-Many pivot table)
    - [✅] Facility model with user relationship methods
    - [ ] Facility-scoped data access for Office Managers
    - [ ] Provider activity visibility within facility scope
- [ ] Provider profile management
- [ ] Facility certification tracking
- [ ] NPI validation
- [ ] DEA number management
- [ ] License verification system

### Product Catalog & Inventory
- [x] MSC products table structure
- [x] Basic product model
- [✅] **Product Catalog System (Completed)**
  - [✅] Comprehensive Product model with scopes and accessors
  - [✅] ProductController with full CRUD operations
  - [✅] Advanced search, filtering, and sorting functionality
  - [✅] Product catalog UI with grid/list view modes
  - [✅] Product detail pages with pricing breakdowns
  - [✅] Product seeder with 25+ MSC wound care products
  - [✅] Integration with order creation workflow
  - [✅] API endpoints for product recommendations
  - [✅] Category and manufacturer filtering
  - [✅] MSC pricing calculation (40% discount from National ASP)
  - [✅] Available sizes management and pricing per size
  - [✅] Commission rate tracking per product
  - [✅] **Role-based pricing visibility (Office Manager restrictions)**
    - [✅] Backend API filtering of MSC pricing data based on user role
    - [✅] Frontend component updates for conditional pricing display
    - [✅] ProductSelector and AIRecommendationCard role-based pricing
    - [✅] ProductCard pricing restrictions for office managers
    - [✅] Test suite for verifying pricing visibility restrictions
- [ ] Inventory tracking
- [ ] Product documentation management (images, brochures, IFUs)

### Order Management System
- [x] Orders table structure
- [x] Order items table structure
- [x] Basic order controller
- [ ] Order creation workflow
- [ ] Order approval process
- [ ] Order status tracking
- [ ] Order history and audit trail
- [ ] Bulk order operations
- [ ] Order templates
- [ ] Recurring order setup

### Commission & Sales Management
- [x] Sales representative management
- [x] Commission calculation service
- [x] Commission records tracking
- [x] Commission payout system
- [x] Commission rules engine
- [x] Commission reporting dashboard
- [ ] Sales territory management
- [ ] Rep hierarchy visualization
- [ ] Payout approval workflow UI
- [ ] Tax document generation (1099s)
- [ ] Performance analytics

## Clinical & Healthcare Features

### Eligibility Verification
- [x] Basic eligibility controller structure
- [x] Mock eligibility verification responses
- [ ] Real payer API integrations (Optum, Availity, Office Ally)
- [ ] Prior authorization workflow
- [ ] Benefits verification
- [ ] Coverage determination
- [ ] Eligibility history tracking
- [ ] Multi-payer support

### MAC (Medicare Administrative Contractor) Validation
- [x] Basic MAC validation controller
- [x] Mock validation responses
- [ ] Real MAC LCD (Local Coverage Determination) integration
- [ ] Policy rule engine
- [ ] Documentation requirement tracking
- [ ] Compliance scoring
- [ ] Appeal process management
- [ ] MAC jurisdiction mapping

### Clinical Documentation
- [ ] FHIR Patient resource management
- [ ] Wound assessment forms
- [ ] Clinical image upload (to Azure FHIR)
- [ ] Treatment plan documentation
- [ ] Progress notes
- [ ] Discharge summaries
- [ ] Clinical decision support

## Integration & External Services

### Payer Integrations
- [ ] Optum API integration
- [ ] Availity API integration
- [ ] Office Ally API integration
- [ ] Change Healthcare integration
- [ ] Real-time eligibility checks
- [ ] Claims submission
- [ ] ERA (Electronic Remittance Advice) processing

### Document Management
- [ ] DocuSeal e-signature integration
- [ ] Document template management
- [ ] Automated document generation
- [ ] Document version control
- [ ] Signature workflow management
- [ ] Legal compliance tracking

### Azure Health Data Services
- [ ] FHIR R4 server setup
- [ ] Patient resource management
- [ ] Observation resources for wound data
- [ ] Condition resources for diagnoses
- [ ] DocumentReference for clinical images
- [ ] Coverage resources for insurance
- [ ] Claim resources for billing

## User Interface & Experience

### Frontend Components (React/TypeScript)
- [x] Basic Inertia.js + React setup
- [x] Authentication pages
- [x] Dashboard layout with role-based navigation
- [x] User management UI
- [x] Organization management UI (with proper error handling)
- [x] Contact management UI
- [x] Commission dashboard
- [x] MainLayout component with usePage hook integration
- [x] Role-based navigation component
- [x] Fixed collection resource components (OrganizationCollection, UserOrganizationCollection)
- [✅] **PHASE 1 - Dashboard Enhancement (Completed)**
  - [✅] Role-based dashboard variants (Provider/Admin/Sales Rep)
  - [✅] Action Required Notifications widget
  - [✅] Quick Action Buttons section
  - [✅] Clinical Opportunities widget (functional with dummy data)
  - [✅] Enhanced Recent Requests (beyond verifications)
- [✅] **PHASE 5 - Complete Role-Based Dashboard Implementation (Completed)**
  - [✅] **All 6 Role-Specific Dashboards Complete**
    - [✅] Provider Dashboard - Clinical focus with patient management and AI opportunities
    - [✅] Office Manager Dashboard - Facility operations and provider coordination
    - [✅] MSC Rep Dashboard - Sales territory management, commission tracking, customer oversight
    - [✅] MSC Sub-Rep Dashboard - Limited access with customer support and coordination tasks
    - [✅] MSC Administrator Dashboard - Business operations, commission management, revenue tracking
    - [✅] Super Administrator Dashboard - System health, security monitoring, technical controls
  - [✅] **Persistent Global Role Switcher (Development)**
    - [✅] localStorage persistence across page refreshes and navigation
    - [✅] Global access via MainLayout (appears on every page in development)
    - [✅] Smart role detection (URL params → localStorage → props priority)
    - [✅] Minimizable interface with current role indicator
    - [✅] Development-only visibility with environment detection
    - [✅] Clean role clearing functionality
    - [✅] Seamless role switching with Inertia.js integration
  - [✅] **Enhanced Dashboard Features**
    - [✅] Complete dummy data sets for all business scenarios
    - [✅] Role-specific metrics and KPI tracking
    - [✅] Interactive elements (progress bars, status indicators, action buttons)
    - [✅] Responsive design across all dashboard variants
    - [✅] Proper role-based routing and fallback handling
- [✅] **PHASE 2 - Modern Authentication & Access Request System (Completed)**
  - [✅] Modern login screen with MSC branding
  - [✅] Professional gradient design and logo integration
  - [✅] Complete access request system infrastructure
  - [✅] Role-based dynamic form system
  - [✅] Backend API and database foundation
  - [✅] React frontend components with validation
- [✅] **PHASE 3 - Access Request Admin Management Interface (Completed)**
  - [✅] Admin dashboard for reviewing access requests with comprehensive filtering
  - [✅] Request approval/denial workflow UI with admin notes
  - [✅] Bulk operations for request management (selection and bulk actions)
  - [✅] Request status tracking and filtering (by status, role, search)
  - [✅] Detailed request view with role-specific information display
  - [✅] Modal-based approval/denial system with confirmation
  - [✅] Integration with navigation system for admin roles
  - [ ] Email notification system integration (future enhancement)
  - [ ] Automatic user account creation upon approval (future enhancement)

### ✅ **COMPLETED: Role-Based Menu Structure & Financial Restrictions (Phase 4)**

#### **Implementation Summary**
Successfully implemented a comprehensive role-based navigation system with critical financial restrictions for Office Managers, ensuring complete separation of clinical and financial access.

#### **Key Accomplishments**

##### **1. Role-Based Navigation System**
- **File**: `resources/js/Components/Navigation/RoleBasedNavigation.tsx`
- **Features**: 
  - Dynamic menu generation for all 6 roles
  - Hierarchical menu structure with expandable sub-items
  - Role-specific routing and access control
  - Responsive design with collapsible sidebar support

##### **2. Critical Office Manager Financial Restrictions**
- **Complete financial data blocking** - Zero financial visibility
- **National ASP pricing only** - No discounts, MSC pricing, or special rates
- **Order total restrictions** - No amounts owed or financial summaries
- **Commission data blocking** - No access to any commission information

##### **3. Backend Security Implementation**
- **Middleware**: `app/Http/Middleware/FinancialAccessControl.php`
  - Route-level financial access protection
  - Automatic role restriction injection into requests
  - JSON and web response handling for unauthorized access
- **Enhanced UserRole Model**: `app/Models/UserRole.php`
  - Financial restriction method implementations
  - Role-specific dashboard configurations
  - Pricing access level management
  - Customer data restriction handling

##### **4. Frontend Security Components**
- **PricingDisplay Component**: `resources/js/Components/Pricing/PricingDisplay.tsx`
  - Role-aware pricing visibility (Office Manager sees only National ASP)
  - Automatic financial data filtering
  - Multi-pricing tier support for authorized roles
- **OrderTotalDisplay Component**: Blocks all financial totals for restricted roles
- **CommissionDisplay Component**: Commission data with access level controls

##### **5. Role Permissions Matrix Implementation**
| Role | Financial Data | Discounts | MSC Pricing | Order Totals | Commission | PHI Access |
|------|---------------|-----------|-------------|--------------|------------|------------|
| Provider | ✅ Full | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No | ✅ Yes |
| Office Manager | ❌ **BLOCKED** | ❌ **BLOCKED** | ❌ **BLOCKED** | ❌ **BLOCKED** | ❌ **BLOCKED** | ✅ Yes |
| MSC Rep | ✅ Full | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Full | ❌ No |
| MSC Sub-Rep | ❌ No | ❌ No | ❌ No | ❌ No | 🟡 Limited | ❌ No |
| MSC Admin | ✅ Full | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Full | ✅ Yes |
| Super Admin | ✅ Full | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Full | ✅ Yes |

##### **6. Comprehensive Documentation**
- **Documentation**: `docs/ROLE_BASED_MENU_STRUCTURE.md`
- **Complete portal structure** for all 6 roles
- **Security implementation details** and compliance notes
- **Testing scenarios** and validation requirements
- **Configuration file references** for maintenance

##### **7. Shared Data Integration**
- **Middleware**: `app/Http/Middleware/HandleInertiaRequests.php`
- **Automatic role restriction sharing** with React frontend
- **Real-time permission checking** for dynamic UI updates
- **Seamless integration** with existing authentication system

#### **Security & Compliance Features**
- ✅ **Backend Enforcement**: All restrictions enforced at API level
- ✅ **Middleware Protection**: Financial routes protected by custom middleware  
- ✅ **Component-Level Security**: Frontend components respect role restrictions
- ✅ **Data Sanitization**: Sensitive data stripped before sending to unauthorized roles
- ✅ **HIPAA Compliance**: PHI restrictions for sales roles
- ✅ **Audit Ready**: All access attempts can be logged for compliance

#### **Next Phase Priorities**
1. Provider Portal step-through forms with progress indicators
2. MAC validation and eligibility checking interface implementation
3. Prior authorization workflow management
4. Order management workflow completion
5. Mobile responsive design enhancements
- [✅] **PHASE 4 - MSC Portal Role-Based Menu Structure (Completed)**
  - [✅] **Comprehensive Role-Based Navigation System**
    - [✅] Provider Portal menu structure (Dashboard, Product Requests, MAC/Eligibility/PA, Product Catalog)
    - [✅] Office Manager Portal menu structure (with Provider Management)
    - [✅] MSC Rep Portal menu structure (Customer Orders, Commissions, My Customers)
    - [✅] MSC Sub-Rep Portal menu structure (limited access)
    - [✅] MSC Admin Portal menu structure (full admin capabilities)
    - [✅] Super Admin Portal menu structure (system-wide access)
  - [✅] **Office Manager Financial Restrictions (Critical Implementation)**
    - [✅] **Complete Financial Data Blocking**
      - [✅] NO financial totals visible anywhere
      - [✅] NO amounts owed displayed
      - [✅] NO order totals shown
      - [✅] NO discounts or special pricing visible
      - [✅] NO MSC pricing access
      - [✅] ONLY National ASP pricing displayed
    - [✅] **Backend Enforcement**
      - [✅] FinancialAccessControl middleware implementation
      - [✅] UserRole model with financial restriction methods
      - [✅] API-level data filtering for office managers
    - [✅] **Frontend Component Security**
      - [✅] PricingDisplay component with role-based restrictions
      - [✅] OrderTotalDisplay component blocking financial data
      - [✅] CommissionDisplay component with access controls
  - [ ] **Remaining Portal Feature Implementation**
    - [ ] Provider Portal step-through forms with progress indicators
    - [ ] MAC validation and eligibility checking interfaces  
    - [ ] Prior authorization management workflows
    - [ ] Mobile responsive design enhancements
  - [ ] MSC Rep Portal Features Implementation
    - [ ] Commission tracking and statements
    - [ ] Sales analytics and performance monitoring
    - [ ] Sub-rep management and coordination
  - [ ] MSC SubRep Portal Features Implementation
    - [ ] Limited commission access and tracking
    - [ ] Sub-rep specific workflows
    - [ ] Reporting to primary MSC Rep
  - [ ] MSC Admin Portal Features Implementation
    - [ ] System administration and configuration
    - [ ] User management across all roles
    - [ ] Operational metrics and oversight
    - [ ] Platform health monitoring
    - [ ] Advanced data tables with sorting/filtering
    - [ ] Multi-panel interfaces for related information
    - [ ] Configuration builders for rules/engines
  - [ ] SuperAdmin Portal Features Implementation **Not Really Needed at this time**
    - [ ] Complete system access and control
    - [ ] Security management and monitoring
    - [ ] Critical system operations
    - [ ] Audit oversight and compliance
    - [ ] System-wide configuration management
- [ ] Order creation/management UI
- [ ] Product catalog interface
- [ ] Eligibility verification UI
- [ ] MAC validation interface
- [ ] Clinical forms
- [ ] Reporting interface

### UI/UX Improvements
- [ ] **MSC Portal UI/UX Requirements (From Documentation)**
  - [ ] Step-through forms with clear progress indicators
  - [ ] Status visualization with color-coded indicators
  - [ ] Timeline visualizations for request progression
  - [ ] Badge indicators for actions needed
  - [ ] Advanced data tables with sorting, filtering, bulk actions
  - [ ] Multi-panel interfaces for side-by-side information viewing
  - [ ] Process visualization diagrams and decision trees
  - [ ] Configuration builders with intuitive interfaces
- [ ] Mobile-responsive design (Provider Portal requirement)
- [ ] Accessibility compliance (WCAG 2.1)
- [ ] Healthcare-specific UI patterns
- [ ] Dark/light mode support
- [ ] Advanced search functionality
- [ ] Bulk operations interface
- [ ] Data visualization components

## Reporting & Analytics

### Business Intelligence
- [x] Basic reports controller structure
- [ ] Sales performance reports
- [ ] Commission analytics
- [ ] Revenue tracking
- [ ] Territory performance
- [ ] Product performance analytics
- [ ] Customer acquisition reports

### Clinical Reporting
- [ ] Outcome tracking reports
- [ ] Compliance reporting
- [ ] Quality metrics
- [ ] Treatment effectiveness analysis
- [ ] Cost-effectiveness reporting
- [ ] Regulatory compliance reports

### Financial Reporting
- [ ] Revenue cycle management
- [ ] Commission statements
- [ ] Tax reporting
- [ ] Expense tracking
- [ ] Profit/loss analysis
- [ ] Accounts receivable aging

## Compliance & Security

### HIPAA Compliance
- [x] PHI/non-PHI data separation architecture
- [x] Basic file storage service (non-PHI only)
- [ ] Business Associate Agreements (BAAs)
- [ ] Risk assessment documentation
- [ ] Employee training system
- [ ] Incident response procedures
- [ ] Regular compliance audits

### Security Features
- [ ] Multi-factor authentication (MFA)
- [ ] Role-based access control (RBAC)
- [ ] API rate limiting
- [ ] SQL injection protection
- [ ] XSS protection
- [ ] CSRF protection
- [ ] Data encryption at rest
- [ ] Data encryption in transit
- [ ] Secure API endpoints

### Audit & Monitoring
- [ ] Comprehensive audit logging
- [ ] User activity monitoring
- [ ] Failed login attempt tracking
- [ ] Data access logging
- [ ] System performance monitoring
- [ ] Security incident detection

## Testing & Quality Assurance

### Automated Testing
- [x] Basic test infrastructure setup
- [x] Organizations feature tests (fixed and working)
- [x] User factory with proper model relationships
- [ ] Unit tests for business logic
- [ ] Integration tests for API endpoints
- [ ] Feature tests for user workflows
- [ ] Database tests for data integrity
- [ ] PHI handling tests
- [ ] Security vulnerability tests

### Quality Assurance
- [ ] Code review processes
- [ ] Performance testing
- [ ] Load testing
- [ ] Security penetration testing
- [ ] HIPAA compliance testing
- [ ] User acceptance testing (UAT)

## DevOps & Deployment

### CI/CD Pipeline
- [x] Basic GitHub Actions workflow
- [ ] Automated testing pipeline
- [ ] Security scanning
- [ ] Dependency vulnerability checks
- [ ] Automated deployment
- [ ] Environment management
- [ ] Database migration automation

### Monitoring & Maintenance
- [ ] Application performance monitoring
- [ ] Error tracking and reporting
- [ ] Log aggregation and analysis
- [ ] Backup and disaster recovery
- [ ] System health checks
- [ ] Automated scaling

## Documentation & Training

### Technical Documentation
- [x] Basic README and setup documentation
- [x] Database schema documentation
- [ ] API documentation
- [ ] Architecture decision records
- [ ] Deployment guides
- [ ] Troubleshooting guides
- [ ] Security procedures

### User Documentation
- [ ] User manuals by role
- [ ] Training materials
- [ ] Video tutorials
- [ ] FAQ documentation
- [ ] Workflow guides
- [ ] Best practices guides

## Regulatory & Legal

### Healthcare Regulations
- [ ] HIPAA compliance certification
- [ ] FDA medical device regulations (if applicable)
- [ ] State licensing requirements
- [ ] Medicare/Medicaid compliance
- [ ] Insurance regulations compliance

### Legal Framework
- [ ] Terms of service
- [ ] Privacy policy
- [ ] Data processing agreements
- [ ] Business associate agreements
- [ ] Insurance and liability coverage

---

## Priority Levels

### High Priority (MVP)
- **✅ Dashboard Enhancement (Phase 1) - Completed**
- **✅ MSC Portal Role-Based Menu Structure & Financial Restrictions (Phase 4) - Completed**
- **🔄 Provider Portal Clinical Workflows - Next Priority**
- Complete order management system
- Real payer integrations
- Azure FHIR integration
- Commission system UI
- HIPAA compliance

### Medium Priority
- Advanced reporting
- Mobile optimization
- Clinical documentation
- Advanced security features

### Low Priority
- Advanced analytics
- Third-party integrations
- Performance optimizations
- Advanced UI features

---

## Completion Status

**Overall Progress: ~75% Complete**

- ✅ **Completed**: Infrastructure setup, basic CRUD operations, authentication, commission calculation backend, organization/user/account relationships, role-based dashboard and navigation, collection resources, test infrastructure, **MSC Portal 6-role system database foundation**, **Modern login screen with MSC branding**, **Complete access request system with role-based forms**, **Full admin interface for access request management**, **Complete role-based menu structure with financial restrictions**, **Critical Office Manager financial data blocking**, **Role-aware pricing components**, **All 6 role-specific dashboards with comprehensive data**, **Persistent global role switcher for development testing**
- 🟡 **In Progress**: Provider Portal step-through forms, Order management workflows, file storage optimization, clinical feature implementation
- 🔴 **Not Started**: Real payer integrations, FHIR implementation, advanced compliance features, mobile optimization 
