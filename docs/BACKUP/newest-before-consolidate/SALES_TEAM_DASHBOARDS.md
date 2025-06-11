## Revised Sales Rep & Sub-Rep Dashboard Plan (Provider-Based Model)

### **Overview**
Sales reps and sub-reps earn commissions based on orders placed by the providers they bring to the platform. They don't create orders themselves - they manage relationships with healthcare providers who place orders.

---

## 1. **Sales Rep Dashboard Redesign**

### **Core Components**

#### A. **Commission Overview Widget**
- **Display**: Commissions from provider orders (PAID orders only)
- **Fields**:
  - Total Paid Commissions (MTD) from my providers
  - Pending Commissions (orders awaiting payment)
  - Next Payout Date
  - YTD Earnings
- **Visual**: Progress bar showing % of monthly target

#### B. **My Providers Section**
- **Provider Overview**:
  - Total active providers I've brought
  - MTD revenue from my providers
  - Top performing provider this month
- **Provider List** (simplified):
  - Provider Name, Facility, MTD Orders, MTD Revenue, Status
  - Click to view provider details and order history

#### C. **My Team Section**
- **Team Overview Card**:
  - Total active sub-reps
  - Combined team commissions (from paid orders)
  - Top performing sub-rep
- **Quick Actions**:
  - "Invite New Sub-Rep" button
- **Sub-Rep List**:
  - Name, # of Providers, MTD Commissions, Status
  - Click for detailed view

#### D. **Quick Stats Row**
- Total Providers Acquired
- MTD Revenue from My Providers
- Average Provider Order Value
- Provider Retention Rate %

---

## 2. **Sub-Rep Dashboard Redesign**

### **Core Components**

#### A. **Personal Commission Widget**
- **Display**: Earnings from provider orders (PAID only)
- **Fields**:
  - Current Month Paid Commissions
  - Pending Commissions
  - Commission Split Rate
  - Parent Rep: [Name]

#### B. **My Providers Section**
- **Provider Count**: Total providers I've brought
- **Top Providers**: List of top 5 by revenue
- **Recent Activity**: Latest orders from my providers

#### C. **Recent Paid Orders**
- **From My Providers**:
  - Provider, Product, Order Value, My Commission
  - Show only orders with payment_status = 'paid'
  - Last 10 orders with pagination

#### D. **Performance & Training**
- **Performance Metrics**: Providers acquired this month
- **Training Progress**: Completion percentage
- **Resources**: Quick links to materials

---

## 3. **Technical Implementation Plan**

### **Database Updates**

#### A. **Update/Add Fields**
```sql
-- Update users table to track who brought them
ALTER TABLE users ADD COLUMN acquired_by_rep_id BIGINT NULL;
ALTER TABLE users ADD COLUMN acquired_by_subrep_id BIGINT NULL;
ALTER TABLE users ADD COLUMN acquisition_date TIMESTAMP NULL;
ALTER TABLE users ADD INDEX idx_acquired_by (acquired_by_rep_id, acquired_by_subrep_id);

-- Add to msc_sales_reps table
ALTER TABLE msc_sales_reps ADD COLUMN monthly_target DECIMAL(10,2) DEFAULT 0;
ALTER TABLE msc_sales_reps ADD COLUMN provider_count INT DEFAULT 0;

-- Create provider_rep_relationships table for tracking
CREATE TABLE provider_rep_relationships (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    provider_id BIGINT NOT NULL,
    rep_id BIGINT NULL,
    subrep_id BIGINT NULL,
    relationship_type ENUM('primary', 'secondary') DEFAULT 'primary',
    commission_rate DECIMAL(5,2) NOT NULL,
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(id),
    FOREIGN KEY (rep_id) REFERENCES msc_sales_reps(id),
    FOREIGN KEY (subrep_id) REFERENCES msc_sales_reps(id),
    INDEX idx_provider_rep (provider_id, rep_id),
    INDEX idx_active_relationships (end_date, provider_id)
);

-- Sub-rep invitation table
CREATE TABLE sub_rep_invitations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    parent_rep_id BIGINT NOT NULL,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    invitation_token VARCHAR(255) UNIQUE,
    status ENUM('pending', 'accepted', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    FOREIGN KEY (parent_rep_id) REFERENCES msc_sales_reps(id)
);
```

#### B. **Commission Calculation Updates**
- Calculate commissions based on orders from providers linked to the rep/subrep
- Only include orders where `payment_status = 'paid'`
- Apply commission splits for sub-rep/parent rep relationships

### **API Endpoints Needed**

#### For Sales Reps:
```
GET /api/sales-rep/dashboard
Response: {
  commissions: {
    mtd_paid: 15000.00,
    mtd_pending: 4500.00,
    ytd_earnings: 180000.00,
    next_payout_date: "2024-02-15",
    monthly_target: 30000.00
  },
  providers: {
    total_active: 23,
    mtd_revenue: 125000.00,
    top_provider: { 
      name: "Dr. Smith", 
      facility: "Metro Clinic",
      mtd_revenue: 25000.00 
    }
  },
  team: {
    total_subreps: 5,
    team_mtd_commissions: 8500.00,
    top_subrep: { name: "Jane Doe", mtd_commissions: 3500.00 }
  }
}

GET /api/sales-rep/providers
GET /api/sales-rep/provider/{id}/orders
GET /api/sales-rep/team
POST /api/sales-rep/invite-subrep
GET /api/sales-rep/commission-details
```

#### For Sub-Reps:
```
GET /api/sub-rep/dashboard
Response: {
  commission: {
    mtd_paid: 3500.00,
    mtd_pending: 800.00,
    commission_split: 50.0,
    parent_rep: "Michael Thompson"
  },
  providers: {
    total: 8,
    acquired_this_month: 2,
    top_providers: [...]
  },
  recent_orders: [...],
  training: {
    completion_percentage: 75
  }
}

GET /api/sub-rep/providers
GET /api/sub-rep/orders
```

### **Key Relationships**

#### **Provider Attribution Model**:
```
Provider (User) registers
    ↓
Linked to Sales Rep OR Sub-Rep who brought them
    ↓
Provider places orders
    ↓
Commission calculated based on:
- If linked to Sales Rep: Rep gets full commission rate
- If linked to Sub-Rep: Commission split between Sub-Rep and Parent Rep
```

#### **Commission Flow**:
1. Provider places order
2. Order gets paid → `payment_status = 'paid'`
3. System checks provider's linked rep/subrep
4. Calculates commission based on order value and rates
5. If sub-rep: splits commission with parent rep
6. Records commission in commission_records table

### **Frontend Components Structure**

```
resources/js/Pages/Dashboard/Sales/
├── SalesRep/
│   ├── Index.tsx (Main dashboard)
│   ├── Components/
│   │   ├── CommissionOverview.tsx
│   │   ├── ProvidersOverview.tsx
│   │   ├── TeamOverview.tsx
│   │   └── QuickStats.tsx
│   ├── Providers/
│   │   ├── Index.tsx (All providers list)
│   │   └── ProviderDetail.tsx
│   └── Team/
│       ├── Index.tsx
│       └── InviteSubRep.tsx
│
└── SubRep/
    ├── Index.tsx (Main dashboard)
    ├── Components/
    │   ├── PersonalCommission.tsx
    │   ├── MyProviders.tsx
    │   ├── RecentOrders.tsx
    │   └── TrainingProgress.tsx
    └── Providers/
        └── Index.tsx
```

---

## 4. **Simplified Dashboard Examples**

### **Sales Rep Dashboard**:
```
┌─────────────────────────────────────────────────────┐
│ Sales Representative Dashboard                       │
├─────────────────────────────────────────────────────┤
│ ┌─────────────────┐ ┌─────────────────┐             │
│ │ Paid Commission │ │ My Providers    │             │
│ │ $15,000 MTD    │ │ 23 Active       │             │
│ │ ████████░░ 50% │ │ $125k Revenue   │             │
│ └─────────────────┘ └─────────────────┘             │
│                                                      │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Top Performing Providers         [View All]     │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ Provider      │ Facility   │ MTD Rev │ Orders  │ │
│ │ Dr. Smith     │ Metro      │ $25,000 │ 12      │ │
│ │ Dr. Johnson   │ Valley     │ $18,500 │ 8       │ │
│ └─────────────────────────────────────────────────┘ │
│                                                      │
│ ┌─────────────────────────────────────────────────┐ │
│ │ My Team (5 Sub-Reps)              [Invite New]  │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ Name        │ Providers │ MTD Comm │ Status    │ │
│ │ Jane Smith  │ 8         │ $3,500   │ Active    │ │
│ │ John Doe    │ 6         │ $2,800   │ Active    │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

### **Sub-Rep Dashboard**:
```
┌─────────────────────────────────────────────────────┐
│ Sub-Representative Dashboard                         │
│ Parent Rep: Michael Thompson                         │
├─────────────────────────────────────────────────────┤
│ ┌─────────────────┐ ┌─────────────────┐             │
│ │ My Commission   │ │ My Providers    │             │
│ │ $3,500 MTD     │ │ 8 Active        │             │
│ │ 50% Split      │ │ 2 New This Mo.  │             │
│ └─────────────────┘ └─────────────────┘             │
│                                                      │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Recent Orders from My Providers                  │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ Dr. Lee  │ Product XYZ │ $2,500 │ Comm: $125   │ │
│ │ Dr. Park │ Product ABC │ $1,800 │ Comm: $90    │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

---

## 5. **Implementation Phases**

### **Phase 1 (Week 1-2): Foundation**
- Database schema updates
- Provider-rep relationship tracking
- Basic dashboard APIs

### **Phase 2 (Week 3-4): Commission System**
- Commission calculation from provider orders
- Parent/sub-rep split logic
- Paid vs pending tracking

### **Phase 3 (Week 5-6): Team Management**
- Sub-rep invitation system
- Team overview features
- Performance metrics

### **Phase 4 (Week 7-8): Polish**
- Mobile optimization
- Performance tuning
- Testing & documentation

---

Would you like me to start implementing the database updates and core dashboard APIs for this provider-based commission model?