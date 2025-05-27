# Menu Structure 

## Healthcare Provider Portal
```
📊 Dashboard
📋 Product Requests (new request, my requests, status)
✅ MAC/Eligibility/PA
    └─ MAC Validation 
    └─ Eligibility Check
    └─ Pre-Authorization
📚 Product Catalog
```

## Office Manager Portal
```
📊 Dashboard
📋 Product Requests (new, facility requests, provider requests)
    └─ NO financial data visible
    └─ NO order totals
    └─ NO amounts owed
    └─ Products show National ASP ONLY
✅ MAC/Eligibility/PA
    └─ MAC Validation 
    └─ Eligibility Check
    └─ Pre-Authorization
📚 Product Catalog 
    └─ National ASP pricing ONLY
    └─ NO discounts visible
    └─ NO MSC pricing
👥 Provider Management (facility providers only)
```

## MSC Sales Representative Portal
```
📊 Dashboard
📋 Customer Orders (view only - product & commission, NO PHI)
💰 Commissions (my earnings, history, payouts)
👥 My Customers
    └─ Customer List
    └─ My Team (invite sub-reps with commission split proposals)
```

## MSC Sub-Representative Portal
```
📊 Dashboard
📋 Customer Orders (view only - product & commission, NO PHI)
💰 My Commissions
```

## MSC Administrator Portal
```
📊 Dashboard
📋 Request Management (approve/reject product requests)
📦 Order Management 
    └─ Create Manual Orders (FULL financial visibility)
    └─ Manage All Orders (FULL financial visibility)
    └─ Product Management (catalog, pricing, Q-codes)
    └─ Engines
        └─ Clinical Opportunity Rules
        └─ Product Recommendation Rules
        └─ Commission Management
👥 User & Org Management 
    └─ Access Requests
    └─ Sub-Rep Approval Queue
    └─ User Management
    └─ Organization Management
⚙️ Settings
```

## Super Administrator Portal
```
📊 Dashboard (system health, all metrics)
📋 Request Management
📦 Order Management (FULL financial visibility)
💰 Commission Overview (system-wide view)
👥 User & Org Management 
    └─ RBAC Configuration
    └─ All Users
    └─ System Access Control
    └─ Role Management
⚙️ System Admin
    └─ Platform Configuration
    └─ Integration Settings
    └─ API Management
    └─ Audit Logs
```

**Critical Office Manager Restrictions:**
- Product requests show ONLY National ASP pricing
- NO discounts, NO MSC pricing, NO special rates
- NO financial totals or amounts owed visible anywhere
- When viewing requests, financial data is completely hidden
- They facilitate clinical workflows but have zero financial visibility