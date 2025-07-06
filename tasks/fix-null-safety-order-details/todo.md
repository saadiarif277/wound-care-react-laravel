# Fix Null Safety in Order Details

## Problem Statement
The ProductRequestController was throwing errors when trying to access properties on null objects:
1. "Undefined array key 'primaryMemberId'" - accessing array keys that might not exist
2. "Attempt to read property 'name' on null" - accessing properties on null manufacturer objects

## Todo List
- [x] Fix undefined array key access for insurance member IDs
- [x] Add null-safe operator for manufacturer access
- [x] Add null-safe operator for products collection
- [x] Fix provider property access with null safety
- [x] Fix facility property access with null safety

## Changes Made

### 1. Fixed Insurance Array Key Access
Changed from using `$clinicalSummary['insurance']['primaryMemberId']` directly to using `isset()` checks:
```php
// Before
($clinicalSummary['insurance']['primaryName'] ?? 'N/A') . ($clinicalSummary['insurance']['primaryMemberId'] ? ' - ' . $clinicalSummary['insurance']['primaryMemberId'] : '')

// After  
($clinicalSummary['insurance']['primaryName'] ?? 'N/A') . (isset($clinicalSummary['insurance']['primaryMemberId']) ? ' - ' . $clinicalSummary['insurance']['primaryMemberId'] : '')
```

### 2. Added Null-Safe Operators for Object Access
Updated all object property access to use PHP 8's null-safe operator (`?->`):

#### Products and Manufacturer:
```php
// Before
$productRequest->products->first()->manufacturer->name ?? 'N/A'

// After
$productRequest->products->first()?->manufacturer?->name ?? 'N/A'
```

#### Provider Access:
```php
// Before
$productRequest->provider->first_name . ' ' . $productRequest->provider->last_name

// After
($productRequest->provider?->first_name ?? '') . ' ' . ($productRequest->provider?->last_name ?? '')
```

#### Facility Access:
```php
// Before
$productRequest->facility->name

// After
$productRequest->facility?->name ?? 'N/A'
```

### 3. Updated All Occurrences
Applied null-safe operators throughout the controller in both:
- The main orderData structure
- The orderInterfaceData structure
- The alternate order branch

## Review

The implementation successfully prevents null reference errors by:
1. Using `isset()` for array key checks before accessing
2. Using null-safe operators (`?->`) for all object property access
3. Providing sensible defaults when values are null

This ensures the order details page can handle cases where:
- Products don't have manufacturers assigned
- Insurance data is incomplete
- Provider or facility relationships are missing
- Product collections are empty

The page will now display "N/A" or empty strings instead of throwing errors.