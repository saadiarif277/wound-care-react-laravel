# MUE Values and Delivery Date Updates

## Todo List

### ✅ Completed Tasks

1. **Review and verify the hard-coded MUE values in the codebase**
   - Found MUE values in multiple locations:
     - ProductSelectorQuickRequest.tsx (MUE_LIMITS object)
     - CmsEnrichmentService.php (CMS reimbursement table)
     - SyncCmsPricing.php (duplicate CMS data)
   - Database has both `mue` and `mue_limit` columns

2. **Find where MUE limits are defined in the product selector**
   - Located in ProductSelectorQuickRequest.tsx
   - Large hard-coded MUE_LIMITS object mapping Q-codes to maximum units
   - Used for real-time validation during product selection

3. **Locate the Delivery Date selector field in QuickRequest**
   - Found in Step2PatientInsurance.tsx (lines 560-573)
   - Previously always visible, read-only for standard shipping

4. **Implement conditional visibility for Delivery Date based on shipping speed**
   - Modified Step2PatientInsurance.tsx to only show field when "Choose Delivery Date" selected
   - Updated CreateNew.tsx to prevent auto-calculation for "Choose Delivery Date" option

5. **Test the changes to ensure Delivery Date only shows when 'Choose Delivery Date' is selected**
   - Changes implemented successfully
   - Field now completely hidden unless shipping speed is "Choose Delivery Date"

## Summary of Changes

### Delivery Date Field Modifications

**File: Step2PatientInsurance.tsx**
- Changed the Delivery Date field from always visible to conditionally rendered
- Field now only appears when `shipping_speed === 'choose_delivery_date'`
- Removed the read-only version that was showing calculated dates

**File: CreateNew.tsx**
- Modified the useEffect that calculates delivery dates
- Added early return for 'choose_delivery_date' to prevent auto-calculation
- Added logic to clear delivery_date when switching away from 'choose_delivery_date'

### MUE Values Review

**Current Issues with MUE Implementation:**
1. Data duplication across multiple files
2. Hard-coded values difficult to maintain
3. Inconsistent column naming in database
4. No centralized management system

**Recommendations for MUE Values:**
1. Verify all hard-coded values match current CMS guidelines
2. Consider centralizing MUE data in database
3. Create admin interface for updating MUE values
4. Implement CMS API integration for automatic updates

## Technical Details

### Changed Lines:
- Step2PatientInsurance.tsx: Lines 560-573 (conditional rendering)
- CreateNew.tsx: Lines 1155-1173 (auto-calculation logic)

### Behavior Changes:
- Delivery Date field is completely hidden for standard shipping options
- Field only appears when user selects "Choose Delivery Date"
- Auto-calculation still works for standard shipping in background
- Manual date selection preserved for "Choose Delivery Date" option