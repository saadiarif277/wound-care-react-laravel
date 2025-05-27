# Project Memory

This file stores project-specific knowledge, conventions, and user preferences learned by the AI assistant.

## User Preferences

- **Visual Design**: User prefers modern, clean aesthetics with proper contrast and readability
- **Logo**: MSC-logo.png should be used consistently throughout the application (located in public/MSC-logo.png)

## Technical Decisions

- **Development Stack:** Laravel + Inertia.js + React + TypeScript + Vite
- **CSS Framework:** Tailwind CSS with utility classes (with line-clamp plugin for text truncation)
- **Build Tool:** Vite
- **Brand Colors**:
  - Primary blue: #1822cf (used for primary actions, active states, focus states)
  - Secondary colors follow Tailwind's default palette

## RBAC System Implementation

### **Current System Architecture**
- **ONLY** uses robust RBAC system with `Role.php` and `Permission.php` models
- **Database Tables**: `roles`, `permissions`, `permission_role`, `role_user` pivot tables
- **User Model**: Uses `HasPermissions` trait, relationships with roles via pivot table
- **Legacy UserRole.php**: COMPLETELY REMOVED - do not reference or recreate

### **Role Structure (Database Slugs)**
All roles use hyphenated slugs to match database:
- `provider` - Healthcare Provider
- `office-manager` - Office Manager  
- `msc-rep` - MSC Sales Representative
- `msc-subrep` - MSC Sub-Representative
- `msc-admin` - MSC Administrator
- `super-admin` - Super Administrator

### **Permission System (19 Total Permissions)**
Granular permissions control all system access:
- `view-users`, `edit-users`, `delete-users`
- `view-financials`, `view-msc-pricing`, `view-discounts`, `view-order-totals`
- `create-orders`, `approve-orders`, `process-orders`
- `manage-products`, `manage-commission`
- `view-commission`, `view-reports`
- `manage-settings`, `manage-system`, `view-phi`
- `super-admin-access`, `msc-admin-access`

### **Backend Security Patterns**
- **Permission Checks**: Always use `$user->hasPermission('permission-name')`
- **Middleware Protection**: `$this->middleware('permission:permission-name')->only(['methods'])`
- **Role Restrictions**: Passed from controllers to frontend via `roleRestrictions`
- **NO Role Checks**: Avoid direct role checks in business logic - use permissions only

### **Frontend Implementation**
- **Role Types**: Use hyphenated slugs (`'office-manager'`, `'super-admin'`) to match backend
- **Navigation**: Role-based navigation using permission checks
- **Components**: All UI components use permission-based visibility
- **Legacy Support**: Handle `'superadmin'` string for backward compatibility

## Development Approach

- **Permission-First Development**: All new features must implement permission-based access control
- **NO Legacy References**: Never reference UserRole.php or legacy RBAC patterns
- **Database-Driven**: All role and permission data comes from database, not hardcoded
- **Granular Control**: Use specific permissions rather than broad role checks

## Commission Access Control

**Current Implementation:**
- **Provider/Office Manager**: No commission data access (`commission_access_level: 'none'`)
- **MSC SubRep**: Limited commission access (`commission_access_level: 'limited'`)  
- **MSC Rep/Admin/Super Admin**: Full commission access (`commission_access_level: 'full'`)

## Product Management

**Administrative Controls:**
- **Add/Edit Products**: Restricted to users with `manage-products` permission
- **Product Visibility**: All roles can view products, management limited by permission
- **Role-Appropriate UI**: Different dashboard stats and controls per role capability 
