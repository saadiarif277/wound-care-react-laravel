# MSC Wound Portal - Revised Implementation Plan

Based on analysis of the current implementation against the Technical Alignment document (source of truth), this plan outlines what's implemented and what needs to be built.

## 📊 Executive Summary

The platform has strong foundational components in place but needs alignment with the streamlined workflow defined in the Technical Alignment document. Key areas needing work include:

1. **Database schema cleanup** - Remove unused fields, add missing ones
2. **Status workflow alignment** - Update enum values and implement transition rules
3. **IVR generation simplification** - Remove signature requirements, implement one-click generation
4. **Sales rep features** - Connect mock dashboards to real data with provider attribution
5. **90% auto-population** - Implement single-query data retrieval for IVR generation

## 🏗️ Current Implementation Status

### ✅ What's Already Built

1. **Core Infrastructure**
   - Laravel backend with API endpoints
   - React/TypeScript frontend with Inertia.js
   - Supabase for operational data
   - Azure FHIR for PHI storage
   - DocuSeal integration for document generation

2. **User & Organization Management**
   - Hybrid users table (authentication + provider data)
   - Organization → Facility → Provider relationships
   - Role-based access control (RBAC)
   - Multi-facility provider support

3. **Order Workflow**
   - ProductRequest model with status tracking
   - Admin Order Center with status management
   - IVR generation via DocuSeal (with signatures - needs update)
   - Manufacturer approval tracking

4. **DocuSeal Integration**
   - Services for document generation
   - Manufacturer-specific folder organization
   - Template management system
   - Submission tracking

5. **Sales Dashboards (UI Only)**
   - MscRepDashboard component with commission widgets
   - MscSubrepDashboard with provider tracking
   - Analytics API endpoints (returning mock data)

### ❌ What's Missing or Needs Updates

1. **Database Schema**
   - Remove unused product_requests fields
   - Add PTAN and default_place_of_service to facilities
   - Update order_status enum values
   - Add provider attribution fields to users

2. **Status Workflow**
   - Missing `manufacturer_approved` status
   - No transition validation rules
   - Status flow doesn't match Technical Alignment

3. **IVR Process**
   - Still includes signature functionality
   - Not truly one-click generation
   - Missing 90% auto-population query
   - Missing "Mark as Sent" tracking

4. **Sales Rep Features**
   - Dashboards use mock data only
   - No provider attribution system
   - No commission calculation from orders
   - Sub-rep management not functional

## 🎯 Implementation Phases

### Phase 1: Database & Status Alignment (Week 1)

#### 1.1 Create Migration for Schema Cleanup
```sql
-- Remove unused fields from product_requests
ALTER TABLE `product_requests` 
DROP COLUMN `medicare_part_b_authorized`,
DROP COLUMN `ivr_bypass_reason`, 
DROP COLUMN `ivr_bypassed_at`,
DROP COLUMN `ivr_bypassed_by`,
DROP COLUMN `ivr_signed_at`,
DROP COLUMN `pre_auth_submitted_at`,
DROP COLUMN `pre_auth_approved_at`,
DROP COLUMN `pre_auth_denied_at`;

-- Add missing fields to facilities
ALTER TABLE `facilities`
ADD COLUMN `ptan` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `default_place_of_service` VARCHAR(2) DEFAULT '11';

-- Add provider attribution to users
ALTER TABLE `users`
ADD COLUMN `acquired_by_rep_id` BIGINT UNSIGNED NULL,
ADD COLUMN `acquired_by_subrep_id` BIGINT UNSIGNED NULL,
ADD COLUMN `acquisition_date` TIMESTAMP NULL,
ADD INDEX idx_acquired_by (acquired_by_rep_id, acquired_by_subrep_id);
```

#### 1.2 Update Status Enum
- Change `ivr_confirmed` to `manufacturer_approved` throughout codebase
- Update ProductRequest model status constants
- Update Admin Order Center status displays

#### 1.3 Create Status Transition Service
```php
// App\Services\OrderStatusTransitionService.php
class OrderStatusTransitionService {
    private array $allowedTransitions = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['processing', 'cancelled'],
        'processing' => ['approved', 'sent_back', 'denied'],
        'approved' => ['pending_ivr', 'cancelled'],
        'pending_ivr' => ['ivr_sent', 'cancelled'],
        'ivr_sent' => ['manufacturer_approved', 'cancelled'],
        'manufacturer_approved' => ['submitted_to_manufacturer'],
        'submitted_to_manufacturer' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'denied' => [],
        'sent_back' => ['submitted']
    ];
}
```

### Phase 2: IVR Generation Streamlining (Week 2)

#### 2.1 Update IvrDocusealService
- Remove signature-related code
- Implement one-click generation (no modal)
- Add automatic PDF download response
- Remove `ivr_required` condition check

#### 2.2 Implement 90% Auto-Population Query
```php
// Single query for IVR data
public function getIvrData($productRequestId) {
    return DB::table('product_requests as pr')
        ->join('users as u', 'pr.provider_id', '=', 'u.id')
        ->join('facilities as f', 'pr.facility_id', '=', 'f.id')
        ->join('organizations as o', 'f.organization_id', '=', 'o.id')
        ->where('pr.id', $productRequestId)
        ->select([
            // 15% from product request
            'pr.request_number', 'pr.expected_service_date', 
            'pr.wound_type', 'pr.payer_name_submitted',
            // 25% from provider
            'u.first_name as provider_first_name',
            'u.last_name as provider_last_name',
            'u.npi_number as provider_npi',
            'u.credentials', 'u.email as provider_email',
            // 30% from facility
            'f.name as facility_name', 'f.npi as facility_npi',
            'f.address', 'f.city', 'f.state', 'f.zip_code',
            'f.phone as facility_phone', 'f.ptan',
            'f.default_place_of_service',
            // 20% from organization
            'o.name as organization_name', 'o.tax_id'
        ])
        ->first();
}
```

#### 2.3 Update Admin Order Center
- Simplify IVR generation to one-click button
- Add "Mark as Sent to Manufacturer" action
- Add "Confirm Manufacturer Approval" with reference field
- Update status badges to match Technical Alignment

### Phase 3: Sales Rep Integration (Week 3)

#### 3.1 Connect Dashboards to Real Data
- Update SalesRepAnalyticsController to query actual data
- Implement provider attribution queries
- Calculate commissions from delivered orders

#### 3.2 Implement Provider Attribution
```php
// When onboarding a provider
public function attributeProvider($providerId, $repId, $subRepId = null) {
    User::where('id', $providerId)->update([
        'acquired_by_rep_id' => $repId,
        'acquired_by_subrep_id' => $subRepId,
        'acquisition_date' => now()
    ]);
}
```

#### 3.3 Commission Calculation Service
```php
public function calculateCommissions($repId, $month) {
    // Only 'delivered' orders generate paid commissions
    $paidOrders = ProductRequest::where('order_status', 'delivered')
        ->whereHas('provider', function($q) use ($repId) {
            $q->where('acquired_by_rep_id', $repId)
              ->orWhere('acquired_by_subrep_id', $repId);
        })
        ->whereMonth('delivered_at', $month)
        ->get();
        
    return $this->calculate($paidOrders);
}
```

### Phase 4: Testing & Optimization (Week 4)

#### 4.1 End-to-End Testing
- Test complete order flow from submission to delivery
- Verify 90-second provider workflow
- Validate 5.5-minute admin workflow
- Test commission calculations

#### 4.2 Performance Optimization
- Implement caching for frequently accessed data
- Optimize queries for dashboard loading
- Add indexes for common query patterns

#### 4.3 Documentation Updates
- Update API documentation
- Create user guides for new workflows
- Document commission calculation rules

## 📋 Implementation Checklist

### High Priority (Immediate)
- [ ] Create and run database migration for schema cleanup
- [ ] Update status enum values throughout codebase
- [ ] Remove signature requirements from IVR generation
- [ ] Implement status transition validation service
- [ ] Add "Mark as Sent" and "Confirm Approval" actions

### Medium Priority (Short-term)
- [ ] Implement 90% auto-population query
- [ ] Connect sales dashboards to real data
- [ ] Add provider attribution system
- [ ] Update Admin Order Center UI components
- [ ] Implement commission calculation from orders

### Lower Priority (Long-term)
- [ ] Add smart ICD-10/CPT code search
- [ ] Add advanced analytics and reporting
- [ ] Mobile optimization improvements

## 🚀 Success Metrics

1. **Provider Experience**
   - ✅ 90 seconds total request time
   - ✅ 90% IVR pre-population rate
   - ✅ Zero duplicate data entry

2. **Admin Efficiency**
   - ✅ One-click IVR generation
   - ✅ Clear action priorities
   - ✅ 5.5-minute total workflow

3. **Sales Performance**
   - ✅ Real-time commission tracking
   - ✅ Accurate provider attribution
   - ✅ Automated commission calculations

## 🔑 Key Dependencies

1. **Database Access**: Need migration permissions
2. **API Keys**: Ensure DocuSeal and Azure FHIR keys are current
3. **Testing Data**: Need realistic test scenarios
4. **Stakeholder Approval**: For workflow changes

## 📝 Notes

- All changes maintain backward compatibility where possible
- Focus on minimal breaking changes as specified
- Prioritize efficiency gains (85-90% time savings)
- Maintain HIPAA compliance throughout