# Permission Audit Summary - MSC Wound Care Portal

## Overview
This document summarizes the comprehensive permission audit and fixes applied to ensure proper role-based access control throughout the system.

## Role Hierarchy (Highest to Lowest)
1. **Super Admin** (level 100) - Complete system control
2. **MSC Admin** (level 80) - MSC business management
3. **MSC Rep** (level 60) - Sales and customer management
4. **MSC Sub-Rep** (level 50) - Limited sales access
5. **Office Manager** (level 40) - Facility administration
6. **Provider** (level 20) - Patient care and orders

## Permission Changes by Role

### 🏥 **Provider** (Healthcare Provider)
**Purpose**: Create and manage patient orders, view necessary pricing for decision-making

**Added Permissions**:
- ✅ `view-documents` - View clinical documents
- ✅ `view-provider-requests` - See their own requests
- ✅ `view-msc-pricing` - See MSC prices for informed decisions
- ✅ `view-discounts` - See their discount prices
- ✅ `view-order-totals` - See order totals
- ✅ `view-financials` - See their own financial data
- ✅ `view-phi` - View patient health information

**Key Capabilities**:
- Create product requests for patients
- View product catalog with MSC pricing
- Manage MAC validation and eligibility
- View their facilities
- See financial impact of their orders

**Cannot**:
- See commission information
- Manage other providers
- Access system settings
- View other providers' data

### 📋 **Office Manager**
**Purpose**: Administrative support for facilities without financial visibility

**Added Permissions**:
- ✅ `view-documents` - View clinical documents
- ✅ `manage-documents` - Upload and manage documents
- ✅ `view-team` - See facility team members
- ✅ `view-users` - Limited view of facility users
- ✅ `export-data` - Export reports

**Removed Permissions**:
- ❌ `view-financials` - No financial data access
- ❌ `view-msc-pricing` - No MSC pricing visibility
- ❌ `view-discounts` - No discount information
- ❌ `view-order-totals` - No order financial totals
- ❌ `view-commission` - No commission data

**Key Capabilities**:
- Manage facility requests and providers
- Handle document management
- View and export reports (non-financial)
- Coordinate facility operations

**Cannot**:
- See any pricing beyond National ASP
- View financial information
- See commission data
- Access PHI directly

### 💼 **MSC Sales Rep**
**Purpose**: Manage customer relationships and track commissions

**Added Permissions**:
- ✅ `view-product-requests` - See customer requests
- ✅ `view-facility-requests` - See facility requests
- ✅ `view-provider-requests` - See provider requests
- ✅ `view-facilities` - View all facilities
- ✅ `view-providers` - View all providers
- ✅ `view-documents` - View documents
- ✅ `create-product-requests` - Create on behalf of providers
- ✅ `view-organizations` - See assigned organizations
- ✅ `view-phi` - View patient information
- ✅ `view-commission-details` - Detailed commission breakdown

**Key Capabilities**:
- Full financial visibility for their accounts
- Commission tracking and reporting
- Customer relationship management
- Create orders on behalf of providers
- Export financial reports

### 👥 **MSC Sub-Rep**
**Purpose**: Limited sales support under parent rep

**Added Permissions**:
- ✅ `view-customers` - See their customers
- ✅ `view-facilities` - View facilities
- ✅ `view-product-requests` - See requests
- ✅ `view-organizations` - See assigned organizations

**Key Capabilities**:
- View orders and basic commission
- See product catalog with pricing
- Limited customer visibility
- Basic reporting access

**Cannot**:
- Manage customers directly
- See detailed commission breakdowns
- Create requests on behalf of others

### 👨‍💼 **MSC Admin**
**Purpose**: Complete MSC business management

**Added Permissions**:
- ✅ `view-msc-pricing` - Full pricing visibility
- ✅ `view-discounts` - All discount information
- ✅ `view-national-asp` - National pricing data
- ✅ `create-product-requests` - Create any request
- ✅ `manage-roles` - Role management
- ✅ `manage-permissions` - Permission management
- ✅ `view-commission` - Commission visibility
- ✅ `create-orders` - Create orders
- ✅ `view-products` - Product catalog access
- ✅ `view-pre-authorization` - Pre-auth visibility
- ✅ `view-eligibility` - Eligibility status
- ✅ `view-mac-validation` - MAC validation data
- ✅ `view-phi` - Patient information access
- ✅ `manage-phi` - Manage patient data
- ✅ `view-commission-details` - Detailed commissions
- ✅ `bypass-ivr` - Bypass IVR requirements
- ✅ `approve-orders` - Approve submitted orders
- ✅ `manage-manufacturers` - Manufacturer settings
- ✅ `view-manufacturers` - Manufacturer information

**Key Capabilities**:
- Complete system management (except super-admin functions)
- Financial rule configuration
- User and role management
- Full reporting and analytics
- System integration management

### 🔐 **Super Admin**
**Purpose**: Complete system control

**Has All Permissions** including:
- `manage-all-organizations` - Cross-organization management
- System-level configuration
- Database management
- Security settings

## New Permissions Created

1. **`view-phi`** - View Protected Health Information
   - Assigned to: Provider, MSC Rep, MSC Admin, Super Admin

2. **`manage-phi`** - Manage Protected Health Information
   - Assigned to: MSC Admin, Super Admin

3. **`export-data`** - Export data and reports
   - Assigned to: Office Manager, MSC Rep, MSC Admin, Super Admin

4. **`view-commission-details`** - View detailed commission breakdowns
   - Assigned to: MSC Rep, MSC Admin, Super Admin

5. **`bypass-ivr`** - Bypass IVR requirements
   - Assigned to: MSC Admin, Super Admin

6. **`approve-orders`** - Approve submitted orders
   - Assigned to: MSC Admin, Super Admin

7. **`manage-manufacturers`** - Manage manufacturer settings
   - Assigned to: MSC Admin, Super Admin

8. **`view-manufacturers`** - View manufacturer information
   - Assigned to: All roles

## Security Principles Applied

1. **Principle of Least Privilege**: Each role has only the permissions necessary for their function

2. **Financial Data Separation**: 
   - Office Managers have NO financial visibility
   - Providers see only relevant pricing for decision-making
   - Full financial data restricted to MSC roles

3. **PHI Protection**:
   - PHI access explicitly controlled via permissions
   - Office Managers cannot directly access PHI
   - Audit logging for all PHI access

4. **Hierarchical Access**:
   - Higher roles include lower role permissions
   - Clear escalation path for permissions
   - No permission inversions

## Implementation Notes

### Migrations Created:
1. `2025_01_18_160000_fix_financial_permissions_for_roles.php` - Financial permission fixes
2. `2025_01_18_170000_comprehensive_permission_audit_fixes.php` - Complete audit implementation

### Middleware Updates:
- `HandleInertiaRequests` - Added commission and pricing access levels
- `FinancialAccessControl` - Applied to product and commission routes

### Frontend Components:
- `PricingDisplay` - Respects permission-based visibility
- `OrderTotalDisplay` - Hides financials from unauthorized roles
- `CommissionDisplay` - Shows commission based on access level

## Testing Checklist

- [ ] Office Manager cannot see MSC pricing
- [ ] Office Manager cannot see order totals
- [ ] Provider can see MSC pricing and discounts
- [ ] Provider can see their order totals
- [ ] MSC Rep can see all financial data
- [ ] MSC Admin can manage all settings
- [ ] PHI access is properly restricted
- [ ] Export functionality works per role
- [ ] Commission visibility follows hierarchy

## Future Considerations

1. **Granular Facility Permissions**: Consider facility-specific permissions for multi-facility organizations
2. **Time-based Permissions**: Temporary permission elevation for specific tasks
3. **Delegation System**: Allow temporary permission delegation
4. **Audit Trail Enhancement**: More detailed permission usage tracking