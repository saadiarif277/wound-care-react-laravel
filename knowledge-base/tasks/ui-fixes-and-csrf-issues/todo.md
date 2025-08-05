# UI Fixes and CSRF Issues Task List

## Overview
Fix critical issues with the Quick Request flow including diagnosis code buttons UI problems, CSRF token redirects, and missing product sizes on the product selection page.

---

## Issue 1: Diagnosis Code Buttons UI Problems - FIXED ✅

### Problem Description
The diagnosis code wound type buttons on Step 4 (Clinical Billing) weren't showing because:
1. The API endpoint was returning an error (diagnosis_codes table didn't have data)
2. Only 4 wound types were in the database instead of 8
3. Dynamic Tailwind class generation was breaking the UI

### Solution Implemented
1. **Fixed Database Issues** ✅
   - Ran `DiagnosisCodesFromCsvSeeder` to populate diagnosis_codes table with 921 codes
   - Added missing 5 wound types to wound_types table (now has all 8 types)

2. **Updated Component** ✅
   - Added all 8 wound types to WOUND_CARE_GROUPS constant:
     - Diabetic Foot Ulcers
     - Venous Leg Ulcers  
     - Pressure Ulcers
     - Surgical Wound
     - Traumatic Wound
     - Arterial Ulcer
     - Chronic Ulcer
     - Other

3. **Fixed Dynamic Classes** ✅
   - Replaced `hover:${t.text.primary}` with static classes
   - Fixed all hover states to use conditional rendering
   - Now using `${theme === 'dark' ? 'hover:bg-gray-700/50' : 'hover:bg-gray-200/50'}`

### Files Modified
- `resources/js/Components/DiagnosisCode/DiagnosisCodeSelector.tsx` - Fixed all dynamic classes and added all wound types
- Database populated with proper data

---

## Issue 2: CSRF Token Redirect Issue

### Problem Description
CSRF token expires during long form sessions, causing a redirect back to step 1 when submitting the IVR.

### Tasks
- [ ] Review CSRF token handling in Inertia setup
- [ ] Implement token refresh on long-running forms
- [ ] Add better error handling for expired tokens
- [ ] Test retry logic is working properly
- [ ] Consider implementing auto-save to prevent data loss

### Current Implementation
- Retry logic exists in `resources/js/Services/api.ts`
- May need to extend to IVR submission specifically

---

## Issue 3: Product Sizes Not Showing - ATTEMPTED FIX ⚠️

### Problem Description
Product sizes weren't showing in the dropdown on product cards even though they exist in the database.

### Attempted Solutions
1. **Fixed Product Model Accessor** ✅
   - Updated `getAvailableSizesAttribute` to check if data is already decoded
   - Now properly handles both string and array data types
   - Maintains backward compatibility

2. **Updated UI** ✅
   - Removed separate SizeManager component
   - Added size dropdown directly to each product card
   - Improved button text ("Add" vs "Add Another Size")

3. **API Response Investigation** ✅
   - Removed problematic `.select()` statement from ProductController
   - Attempted manual JSON decoding
   - Fixed description and msc_price fields in API response

### Current Status
- **STILL NOT WORKING** - sizes continue to show as undefined/empty arrays
- Database has correct data (confirmed via Tinker)
- Issue appears to be in data retrieval or model casting
- Needs deeper investigation into Eloquent model behavior

### Files Modified
- `app/Models/Order/Product.php` - Fixed accessor method
- `app/Http/Controllers/ProductController.php` - Multiple attempts to fix data retrieval
- `resources/js/Components/ProductCatalog/ProductSelectorQuickRequest.tsx` - Updated UI

---

## Review Section

### Summary of Changes Made

1. **Diagnosis Code Buttons - COMPLETED** ✅
   - Database populated with 921 diagnosis codes
   - All 8 wound types added to database
   - Component updated with all wound types
   - Fixed all dynamic Tailwind class issues
   - Buttons now display correctly

2. **CSRF Token Issue - PENDING**  
   - Issue documented
   - Existing retry logic identified
   - Further testing needed

3. **Product Sizes Issue - STILL BROKEN** ❌
   - Multiple fixes attempted
   - Root cause still unclear
   - Needs fresh approach

### Next Steps
1. Test diagnosis code selection functionality
2. Implement CSRF token handling improvements
3. Take a new approach to the product sizes issue (possibly check middleware, caching, or create a minimal test case) 