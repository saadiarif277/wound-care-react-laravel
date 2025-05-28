# MSC Wound Care Portal - Development Task List

> **RBAC Architecture**: This project uses a robust Role + Permission based access control system with `User ↔ Role ↔ Permission` relationships. All access control is handled through permission-based middleware (`$this->middleware('permission:permission-name')`) and the `$user->hasPermission()` method. **No hardcoded role checks are used.**

## 🚨 CRITICAL RBAC FIXES NEEDED - Office Manager Role

### Office Manager API Endpoint Failures 🔥 IMMEDIATE PRIORITY
*Multiple 403/500 errors due to missing permissions and route protection issues*

**Current Failures:**
- [x] **`/products` endpoint**: ✅ FIXED - Added proper permission-based route protection
- [x] **`/providers` endpoint**: ✅ FIXED - Added `view-providers` permission to Office Manager role
- [x] **`/mac-validation` endpoint**: ✅ FIXED - Added `manage-mac-validation` permission to Office Manager role
- [x] **`/pre-authorization` endpoint**: ✅ FIXED - Added `manage-pre-authorization` permission to Office Manager role

**Required Permission Updates:**
- [x] **Add to Office Manager role**: ✅ COMPLETED - Added `view-providers`, `view-product-requests`, `view-products`
- [x] **Add MAC validation permissions**: ✅ COMPLETED - Office managers now have `manage-mac-validation` permission
- [x] **Add Pre-Auth permissions**: ✅ COMPLETED - Office managers now have `manage-pre-authorization` permission
- [x] **Add proper route protection**: ✅ COMPLETED - Products routes now use `permission:view-products` middleware

**RBAC Compliance Requirements:**
Based on MSC-MVP Product Request Flow documentation, Office Managers should:
- ✅ **Can see product catalog** (but without MSC pricing/savings)
- ✅ **Can create product requests** for their facility
- ✅ **Can view providers** in their facility for request management
- ✅ **Can manage pre-authorization** requests for their patients
- ✅ **Can access MAC validation** for compliance checking
- ❌ **Cannot see MSC pricing** or financial savings data
- ❌ **Cannot see commission rates** or financial analytics

**Technical Implementation:**
1. ✅ **Update UserRoleSeeder.php**: COMPLETED - Added missing permissions to office-manager role
2. ✅ **Update Product Routes**: COMPLETED - Added proper permission middleware to product routes
3. ✅ **Create MAC Validation permissions**: COMPLETED - Added limited MAC validation access for office managers
4. ✅ **Test Permission Filtering**: COMPLETED - Financial data filtering maintained at API level

**Implementation Summary:**
- **New Permissions Added**: `view-products`, `view-providers`, `view-product-requests`, `manage-pre-authorization`, `manage-mac-validation`
- **Routes Updated**: Products routes now use permission-based middleware instead of just `auth`
- **MAC Controller Updated**: Updated to use `manage-mac-validation` permission consistently
- **Database Seeded**: All new permissions applied to Office Manager role in database
- **RBAC Compliance**: Maintained 100% permission-based access control (no hardcoded role checks)

**Security Notes:**
- Office managers need functional access to complete product request workflow
- Financial data filtering must be maintained at API level (already implemented in ProductController)
- All new permissions must follow existing RBAC patterns (no hardcoded role checks)

---

## 🎯 System Overview

### Core Architecture Status ✅ COMPLETE
- **Backend**: Laravel 11 + PHP 8.1+
- **Frontend**: React 18 + TypeScript + Inertia.js + Tailwind CSS
- **Database**: Supabase PostgreSQL (operational data) + Azure Health Data Services (PHI)
- **Authentication**: Laravel Sanctum with session-based auth
- **RBAC**: Complete Role + Permission system with middleware protection
- **Storage**: AWS S3 (documents) + Azure FHIR (PHI)

### Completed Foundation Systems ✅

#### ✅ Infrastructure & Database
- **Supabase Setup**: PostgreSQL, S3-compatible storage, TypeScript types
- **Core Tables**: Users, roles, permissions, organizations, facilities, contacts
- **Business Tables**: Products, orders, commissions, product requests  
- **RBAC Tables**: Role-user pivot, permission-role pivot, granular permissions
- **Performance**: Database indexes, soft deletes, query optimization

#### ✅ Authentication & Authorization (Robust RBAC)
- **Laravel Sanctum**: Session-based authentication with CSRF protection
- **Role System**: 6 roles (Provider, Office Manager, MSC Rep/SubRep, MSC Admin, Super Admin)
- **Permission System**: 19 granular permissions (view-users, edit-financials, manage-products, etc.)
- **Middleware Protection**: All routes protected with `permission:permission-name` middleware
- **Access Control**: Role-based financial restrictions, commission access levels
- **Frontend Integration**: roleRestrictions passed from controllers to React components
- **Complete Audit**: 100% compliance - zero hardcoded role checks across entire application

#### ✅ User Management & Access
- **Modern Login**: MSC-branded authentication with gradient design
- **Access Request System**: Role-based application forms with admin approval workflow
- **User CRUD**: Complete user management with role assignments
- **Organization Management**: Multi-level organization/facility structure
- **Test Users**: 6 test users with proper role assignments for all roles

#### ✅ Product Catalog & Business Logic
- **Product System**: 25+ MSC wound care products with comprehensive data
- **Pricing Engine**: National ASP vs MSC pricing (40% discount) with role-based access
- **Product API**: Search, filtering, recommendations with role-aware responses
- **Commission Tracking**: Product-level commission rates and calculations
- **Role-Based UI**: Financial data hidden from restricted roles (Office Manager)

#### ✅ Dashboard & Navigation
- **Role-Based Dashboards**: 6 unique dashboards with role-specific content and metrics
- **Navigation System**: Dynamic menu generation based on user permissions
- **Quick Actions**: Role-appropriate action buttons and workflow shortcuts
- **Clinical Opportunities**: AI-powered clinical decision support widget
- **Financial Restrictions**: Complete financial data blocking for unauthorized roles

#### ✅ API & Integration Foundation
- **FHIR R4 Server**: Azure Health Data Services proxy with MSC extensions
- **CMS Integration**: Live coverage data from api.coverage.cms.gov
- **Validation Engine**: Comprehensive wound care MAC validation rules
- **Medicare MAC Routes**: 45+ API endpoints for compliance and validation
- **Security**: SQL injection protection, XSS protection, audit logging

#### ✅ Quality & Documentation
- **Testing Suite**: Feature tests, unit tests, validation engine tests
- **API Documentation**: Swagger/OpenAPI with comprehensive endpoint documentation
- **Comprehensive Docs**: 20+ documentation files covering all aspects
- **Code Quality**: 100% RBAC compliance audit, zero hardcoded role checks

#### ✅ MSC Admin Access Control Fixes
- **Permission System**: Added missing permissions (`view-customers`, `view-analytics`, `create-orders`) to MSC Admin role
- **Route Implementation**: Created `/orders/analytics` route with comprehensive analytics dashboard
- **Route Protection**: Moved `/orders/create` to proper permission-based middleware protection
- **Analytics Dashboard**: Built Order/Analytics.tsx with role-aware financial data, interactive charts, and comprehensive metrics
- **Database Integration**: Updated UserRoleSeeder with all required MSC Admin permissions
- **Technical Excellence**: Maintained 100% RBAC compliance with zero hardcoded role checks

### ✅ COMPLETED HIGH PRIORITY FEATURES

### 1. Customer Management & Onboarding System ✅ COMPLETED
*Complete organizational onboarding and customer lifecycle management*

**UI Components Created:**
- [x] **Organization Creation Wizard** (`OrganizationWizard.tsx`) - 4-step organization setup process
- [x] **Provider Invitation Flow** (`ProviderInvitation.tsx`) - Multi-step provider onboarding 
- [x] **Organization Onboarding Wizard** (`OrganizationOnboardingWizard.tsx`) - 6-step comprehensive onboarding
- [x] **Customer Management Dashboard** (`Dashboard.tsx`) - Complete admin oversight interface
- [x] **Credential Management UI** (`CredentialManagement.tsx`) - Professional credential tracking
- [x] **Supporting UI Components** (`card.tsx`, `progress.tsx`, `badge.tsx`) - Reusable components

**Backend Implementation:**
- [x] **Customer Management Controllers** - Complete RBAC-compliant backend
- [x] **Organization Management** - Full lifecycle management
- [x] **User Role Management** - Comprehensive RBAC integration
- [x] **Audit & Compliance** - Complete tracking and logging

### 2. Admin Product Request Review System ✅ COMPLETED
*Comprehensive admin interface for reviewing and managing product requests*

**Frontend Components:**
- [x] **Admin Product Request Review Dashboard** (`Review.tsx`) - Complete filtering, sorting, bulk actions
- [x] **Product Request Detail View** - Full clinical documentation review interface
- [x] **Approval/Rejection Workflow** - Status management with comments and conditions
- [x] **Bulk Action Management** - Multi-request processing capabilities
- [x] **Clinical Documentation Review** - MAC validation and eligibility verification integration

**Backend Implementation:**
- [x] **ProductRequestReviewController** - Complete admin review workflow
- [x] **ProviderController** - Provider management and statistics
- [x] **PreAuthorizationController** - Prior authorization workflow management  
- [x] **EngineController** - Clinical rules and recommendation engine management
- [x] **SystemAdminController** - System configuration and audit management
- [x] **PreAuthorization Model** - Supporting data model for PA workflows

**Key Features Implemented:**
- [x] **Advanced Filtering** - By status, facility, priority, days pending
- [x] **Priority Scoring Algorithm** - Automated request prioritization
- [x] **Clinical Review Workflow** - Approval/rejection with clinical reasoning
- [x] **Bulk Actions** - Mass approval, rejection, information requests
- [x] **Role-Based Access Control** - Permission-based feature access
- [x] **Audit Trail** - Complete action logging and history tracking
- [x] **Integration Ready** - APIs for MAC validation, eligibility checking, prior auth

**Routes Configured:**
- [x] **Admin Review Routes** - `/admin/product-requests/review`
- [x] **Provider Management Routes** - `/providers/*`
- [x] **Prior Authorization Routes** - `/pre-authorization/*` 
- [x] **Engine Management Routes** - `/engines/*`
- [x] **System Admin Routes** - `/system-admin/*`

### 3. CustomerManagementController Security & Code Quality Fixes ✅ COMPLETED
*Comprehensive security hardening and Laravel best practices implementation*

**Security Issues Fixed:**
- [x] **Secure File Storage** - Migrated from insecure public storage to Supabase S3 with private access
- [x] **Enhanced File Validation** - Added comprehensive MIME type validation, file extension checks, and suspicious filename detection
- [x] **Proper Authentication** - Fixed unsafe `auth()` calls, replaced with proper `Auth::facade` usage
- [x] **Input Sanitization** - Implemented secure filename generation with timestamp and random hash
- [x] **Storage Path Security** - Organized file storage with entity-specific directory structure

**Code Quality Improvements:**
- [x] **N+1 Query Prevention** - Added eager loading with optimized select statements to prevent database performance issues
- [x] **Service Response Validation** - Added comprehensive validation for all external service calls with proper error handling
- [x] **Exception Handling** - Implemented proper try-catch blocks with detailed logging for debugging and monitoring
- [x] **Eloquent Model Usage** - Replaced raw database queries with proper Eloquent model relationships
- [x] **Custom Form Requests** - Created dedicated `UploadDocumentRequest` with enhanced validation rules and security checks

**Laravel Best Practices Applied:**
- [x] **Proper Facades Usage** - Consistent use of `Auth::`, `Storage::`, `Log::`, and `DB::` facades
- [x] **Database Transactions** - Proper transaction handling with rollback on failures
- [x] **Resource Relationships** - Optimized eager loading to reduce database queries
- [x] **Error Logging** - Comprehensive error logging with contextual information for debugging
- [x] **File Storage Architecture** - Aligned with Supabase S3 storage architecture for scalability

**Security Features Added:**
- [x] **MIME Type Validation** - Strict validation against allowed document types (PDF, DOC, images only)
- [x] **File Size Limits** - 10MB maximum file size with proper validation
- [x] **Secure File Naming** - Generated secure filenames with timestamp, hash, and cleaned original name
- [x] **Storage Cleanup** - Automatic file cleanup on database transaction failures
- [x] **Audit Logging** - Complete audit trail for all document upload operations

### 4. CustomerManagementService Code Quality Fixes ✅ COMPLETED
*Laravel best practices and SQL optimization improvements*

**Code Quality Issues Fixed:**
- [x] **Removed Unused Dependency** - Eliminated unused OnboardingService injection from constructor
- [x] **Fixed SQL Operator** - Removed trailing space in comparison operator (`'<= '` → `'<='`) that could break SQL
- [x] **Corrected Pluck Usage** - Fixed aliased table reference in pluck (`'users.id'` → `'id'`) for proper query generation
- [x] **Clean Imports** - Removed unused service import to reduce memory footprint

**Technical Improvements:**
- [x] **Constructor Optimization** - Simplified service class with no unused dependencies
- [x] **SQL Query Optimization** - Ensured proper SQL syntax for database compatibility
- [x] **Eloquent Best Practices** - Used correct pluck syntax without unnecessary table prefixes
- [x] **Code Maintainability** - Cleaner codebase with proper dependency management

### 5. InviteProvidersRequest Security & Validation Enhancements ✅ COMPLETED
*Comprehensive authorization and validation improvements for provider invitation system*

**Security Issues Fixed:**
- [x] **Fixed Authorization Logic** - Replaced unsafe `auth()` calls with proper `Auth::facade` usage and permission-based access control
- [x] **Organization-Level Access Control** - Added proper authorization checks to ensure users can only invite providers to organizations they manage
- [x] **Permission-Based Authorization** - Implemented proper RBAC with `invite-providers` permission instead of hardcoded role checks
- [x] **Route Parameter Validation** - Added organization existence verification and ownership validation

**Validation Enhancements:**
- [x] **Enabled Facility Validation** - Uncommented and enhanced facility validation to prevent invalid facility references
- [x] **Duplicate Email Prevention** - Added comprehensive duplicate email validation within requests and against existing users/invitations
- [x] **NPI Number Validation** - Added proper NPI validation with duplicate prevention and format cleaning
- [x] **Enhanced Error Messages** - Improved validation error messages for better user experience

**Advanced Features Added:**
- [x] **Data Preprocessing** - Added `prepareForValidation()` method for email normalization and NPI formatting
- [x] **Custom Validator Logic** - Implemented `withValidator()` for complex cross-field validation scenarios
- [x] **Organization Scope Validation** - Ensured facility assignments belong to the target organization
- [x] **Comprehensive Unique Constraints** - Prevention of duplicate emails, NPIs, and pending invitations

**RBAC Compliance Features:**
- [x] **MSC Admin Access** - MSC Admins with `manage-all-organizations` permission can invite to any organization
- [x] **Office Manager Restrictions** - Office Managers can only invite providers to their own organization
- [x] **Permission-Based Middleware** - All authorization logic follows project RBAC patterns with no hardcoded role checks

### 6. ProviderProfileController Security & Code Quality Fixes ✅ COMPLETED
*File upload security hardening and JSON encoding fixes*

**Security Vulnerabilities Fixed:**
- [x] **Enhanced File Upload Security** - Added comprehensive MIME type validation with both extension and content verification
- [x] **File Size Restrictions** - Enforced 2MB maximum file size for professional photos
- [x] **Image Validation** - Added `getimagesize()` validation to prevent malicious file uploads disguised as images
- [x] **Secure File Storage** - Migrated from public storage to private Supabase S3 storage
- [x] **Secure Filename Generation** - Added timestamp and unique ID to prevent filename collisions and directory traversal

**Code Quality Improvements:**
- [x] **Eliminated Duplicate Controller** - Removed duplicate ProviderProfileController in V1 namespace
- [x] **Fixed Double JSON Encoding** - Added proper array handling for notification and practice preferences
- [x] **Enhanced Error Handling** - Added comprehensive validation for file upload operations
- [x] **Improved Data Type Safety** - Added type checking for JSON preference fields to prevent encoding issues

**Technical Enhancements:**
- [x] **MIME Type Verification** - Dual validation using both Laravel rules and native PHP functions
- [x] **File Integrity Checks** - Verification that uploaded files are actually valid images
- [x] **Preference Data Normalization** - Proper handling of JSON preferences to prevent double encoding
- [x] **Storage Architecture Alignment** - Consistent use of Supabase S3 for all file storage operations

### 7. Real Payer Integration 🔥 HIGH PRIORITY
*Live API connections for eligibility and prior authorization*

**What's Needed:**
- [ ] **Live Payer APIs**
  - Optum API integration
  - Availity API connection
  - Office Ally integration
  - Change Healthcare APIs
- [ ] **Real-time Eligibility Verification**
  - Replace mock responses with live data
  - Cache eligibility results appropriately
  - Handle API failures gracefully
- [ ] **Prior Authorization Management**
  - PA request submission APIs
  - Status tracking and updates
  - Document submission workflows
  - Appeal process management

**Security Considerations:**
- Implement secure credential management
- Add comprehensive audit logging
- Handle PHI data properly (Azure FHIR integration)
- Rate limiting and error handling

---

## 🎯 Medium Priority Features

### 8. Enhanced Clinical Features 📊 MEDIUM PRIORITY
*Advanced clinical decision support and documentation*

**Remaining Tasks:**
- [ ] **Clinical Photography System**
  - Secure image upload to Azure FHIR
  - Comparison tools (before/after)
  - Measurement overlay tools
  - HIPAA-compliant image handling
- [ ] **Advanced Reporting**
  - Outcome tracking reports
  - Compliance dashboards
  - Quality metrics visualization
  - Treatment effectiveness analysis
- [ ] **Clinical Decision Support**
  - Treatment protocol recommendations
  - Drug interaction checking
  - Best practice alerts
  - Evidence-based guidelines

### 9. Advanced Business Intelligence 📈 MEDIUM PRIORITY
*Enhanced analytics and reporting for all user roles*

**Remaining Tasks:**
- [ ] **Sales Analytics**
  - Territory performance tracking
  - Revenue forecasting
  - Commission trend analysis
  - Customer acquisition metrics
- [ ] **Clinical Analytics**
  - Healing rate statistics
  - Treatment effectiveness metrics
  - Cost-effectiveness analysis
  - Quality improvement indicators
- [ ] **Financial Reporting**
  - Revenue cycle management
  - Accounts receivable aging
  - Profit/loss analysis by product/territory

### 10. System Optimization 🔧 MEDIUM PRIORITY
*Performance, monitoring, and operational improvements*

**Remaining Tasks:**
- [ ] **Performance Optimization**
  - Database query optimization
  - Frontend code splitting
  - Image optimization and CDN
  - Caching strategy refinement
- [ ] **Monitoring & Alerting**
  - Application performance monitoring
  - Error tracking and notifications
  - System health dashboards
  - Automated alerts for critical issues
- [ ] **DevOps Improvements**
  - Automated testing pipeline
  - Security scanning integration
  - Environment management
  - Database migration automation

---

## 🔮 Future Enhancements (Low Priority)

### 11. Advanced Integration Features
- [ ] **Third-party EMR Integration**
  - Epic MyChart integration
  - Cerner APIs
  - Additional EHR connectors
- [ ] **Advanced Document Management**
  - DocuSeal e-signature automation
  - Document template management
  - Version control and audit trails
- [ ] **Machine Learning Features**
  - Predictive healing models
  - Risk stratification algorithms
  - Personalized treatment recommendations

### 12. Mobile & Accessibility
- [ ] **Native Mobile App**
  - React Native implementation
  - Offline capability
  - Push notifications
- [ ] **Accessibility Compliance**
  - WCAG 2.1 AA compliance
  - Screen reader optimization
  - Keyboard navigation support

---

## ⚠️ Known Technical Debt & Maintenance

### Critical Maintenance Items
- [ ] **Security Updates**
  - Regular dependency updates
  - Security patch management
  - Penetration testing
- [ ] **HIPAA Compliance Verification**
  - Regular compliance audits
  - Business Associate Agreement updates
  - Risk assessment documentation
- [ ] **Performance Monitoring**
  - Database performance tuning
  - API response time optimization
  - Frontend bundle size management

### Documentation Updates Needed
- [ ] **User Training Materials**
  - Role-specific user guides
  - Video tutorial creation
  - FAQ documentation
- [ ] **Technical Documentation**
  - Architecture decision records
  - Deployment procedures
  - Troubleshooting guides

---

## 🏆 Success Metrics & KPIs

### Technical Excellence
- **Code Quality**: 100% RBAC compliance maintained
- **Security**: Zero security vulnerabilities
- **Performance**: < 2 second page load times
- **Uptime**: 99.9% system availability

### Business Impact
- **User Adoption**: 90%+ user satisfaction scores
- **Process Efficiency**: 50% reduction in administrative time
- **Compliance**: 100% MAC validation accuracy
- **Revenue**: Measurable increase in order processing efficiency

---

## 📋 Best Practices & Development Guidelines

### RBAC Development Pattern
```php
// ✅ Correct: Permission-based middleware
$this->middleware('permission:view-financials')->only(['index']);

// ✅ Correct: Check user permissions
if ($request->user()->hasPermission('edit-products')) {
    // Allow action
}

// ❌ Wrong: Direct role checks
if ($user->hasRole('admin')) { // Don't do this
```

### Frontend Role Handling
```typescript
// ✅ Correct: Use roleRestrictions from backend
const { roleRestrictions } = usePage().props;
if (roleRestrictions.can_view_financials) {
    // Show financial data
}

// ❌ Wrong: Hardcoded role checks
if (user.role === 'admin') { // Don't do this
```

### API Development
- Use permission middleware for all protected routes
- Pass roleRestrictions to frontend in all API responses
- Filter sensitive data at the API level based on permissions
- Implement comprehensive audit logging for all actions

### Testing Strategy
- Write feature tests for all RBAC scenarios
- Test permission boundaries thoroughly
- Include integration tests for external APIs
- Maintain high test coverage (>80%)

---

**Last Updated**: January 2025  
**RBAC System**: Fully Implemented & Audited ✅  
**Overall Progress**: ~77% Complete  
**Next Sprint Focus**: Provider Portal Clinical Workflows
