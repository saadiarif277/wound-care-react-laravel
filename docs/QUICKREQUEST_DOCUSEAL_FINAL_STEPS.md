# Final Integration Steps for QuickRequest + DocuSeal + Episode

## 1. Add Episode Creation to QuickRequest Flow

Create a new method in QuickRequestController to handle episode creation after product selection:

```php
// In QuickRequestController.php
public function createEpisodeForDocuSeal(Request $request)
{
    $validated = $request->validate([
        'patient_id' => 'required|string',
        'patient_fhir_id' => 'required|string', 
        'patient_display_id' => 'required|string',
        'manufacturer_id' => 'required|exists:manufacturers,id',
        'form_data' => 'required|array',
    ]);
    
    // Create or find episode
    $episode = PatientManufacturerIVREpisode::firstOrCreate([
        'patient_fhir_id' => $validated['patient_fhir_id'],
        'manufacturer_id' => $validated['manufacturer_id'],
        'status' => '!=' . PatientManufacturerIVREpisode::STATUS_COMPLETED,
    ], [
        'patient_display_id' => $validated['patient_display_id'],
        'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
        'metadata' => [
            'facility_id' => $validated['form_data']['facility_id'],
            'provider_id' => auth()->id(),
            'created_from' => 'quick_request',
        ]
    ]);
    
    return response()->json([
        'episode_id' => $episode->id,
        'manufacturer_id' => $validated['manufacturer_id']
    ]);
}
```

## 2. Update Frontend Flow to Include DocuSeal

In `CreateNew.tsx`, add the DocuSeal step:

```typescript
// Update sections array
const sections = [
    { title: 'Context & Request', icon: FiUser, estimatedTime: '15 seconds' },
    { title: 'Patient & Insurance', icon: FiPackage, estimatedTime: '30 seconds' },
    { title: 'Clinical & Billing', icon: FiActivity, estimatedTime: '20 seconds' },
    { title: 'Product Selection', icon: FiShoppingCart, estimatedTime: '15 seconds' },
    { title: 'Manufacturer IVR', icon: FiFileText, estimatedTime: '30 seconds' }, // NEW
    { title: 'Review & Submit', icon: FiFileText, estimatedTime: '10 seconds' }
];

// Add in the section rendering
{currentSection === 4 && (
    <Step7DocuSealIVR
        formData={formData}
        updateFormData={updateFormData}
        products={products}
        providers={providers}
        facilities={facilities}
        errors={errors}
    />
)}
```

## 3. Create Episode After Product Selection

In your product selection step, add this logic when moving to next step:

```typescript
// In Step5ProductSelection or in handleNext()
const handleProductSelectionComplete = async () => {
    const selectedProduct = products.find(p => p.id === formData.selected_products[0].product_id);
    
    // Create episode for DocuSeal
    const response = await fetch('/api/quick-request/create-episode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
            patient_id: formData.patient_id,
            patient_fhir_id: formData.patient_fhir_id,
            patient_display_id: formData.patient_display_id,
            manufacturer_id: selectedProduct.manufacturer_id,
            form_data: formData
        })
    });
    
    const data = await response.json();
    updateFormData({ 
        episode_id: data.episode_id,
        manufacturer_id: data.manufacturer_id 
    });
};
```

## 4. Update DocuSealIVRForm to Use Real Template IDs

In `DocuSealIVRForm.tsx`, update the template ID resolution:

```typescript
// Get manufacturer and template
const manufacturer = await fetch(`/api/v1/quick-request/manufacturer/${formData.manufacturer}/fields`);
const manufacturerData = await manufacturer.json();
const templateId = manufacturerData.template_id;

// Use this template ID in the submission
const response = await fetch('/quickrequest/docuseal/create-submission', {
    method: 'POST',
    body: JSON.stringify({
        template_id: templateId, // Use real template ID from DB
        // ... rest of the data
    })
});
```

## 5. Handle DocuSeal Completion

Update the final submission to include the DocuSeal submission ID:

```typescript
// In handleSubmit()
if (!formData.docuseal_submission_id) {
    alert('Please complete the IVR form before submitting');
    return;
}

// Include in submission
submitData.append('docuseal_submission_id', formData.docuseal_submission_id);
submitData.append('episode_id', formData.episode_id);
```

## 6. Add Missing Routes

Add these routes to `web.php`:

```php
Route::post('/api/quick-request/create-episode', [QuickRequestController::class, 'createEpisodeForDocuSeal'])
    ->middleware(['auth', 'permission:create-product-requests']);
```

## Testing Checklist

1. [ ] Create a test manufacturer with DocuSeal template in admin panel
2. [ ] Start a QuickRequest flow
3. [ ] Verify episode is created after product selection
4. [ ] Verify DocuSeal form loads with correct template
5. [ ] Verify form is pre-filled with patient/provider data
6. [ ] Complete DocuSeal form and verify submission ID is captured
7. [ ] Submit final request and verify episode linkage

## Environment Setup

Make sure these are configured in `.env`:

```
DOCUSEAL_API_KEY=your_api_key
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret
```

## Current Issues & Resolutions (January 2025)

### 1. File Encoding Issue with Step6ReviewSubmit.tsx

**Issue**: The file has BOM (Byte Order Mark) characters causing parsing errors:

```
Unexpected character ''. (1:0)
> 1 | import { useState, useEffect } from 'react';
```

**Resolution**: The file needs to be saved without BOM:

```bash
# Remove the file and recreate it
rm resources/js/Pages/QuickRequest/Components/Step6ReviewSubmit.tsx
# Then recreate the file with proper UTF-8 encoding (no BOM)
```

### 2. Product Sizing System Changes

**Current Issue**: The system uses numeric square centimeters but needs to support size labels like "2x2", "4x4".

**Required Changes**:

#### Backend (ProductController.php)

- Modify `available_sizes` to return string labels instead of numeric values
- Update the product search endpoint to include size options

#### Frontend Components to Update

- `ProductSelectorQuickRequest.tsx` - Change size selector from numeric input to dropdown
- `Step5ProductSelection.tsx` - Update to handle string sizes
- `Step6ReviewSubmit.tsx` - Update price calculations to handle size strings

#### Database Migration Needed

```php
// Add to products table
Schema::table('products', function (Blueprint $table) {
    $table->json('available_size_labels')->nullable(); // ["2x2", "2x4", "4x4", etc.]
    $table->json('size_pricing')->nullable(); // {"2x2": 4, "2x4": 8, "4x4": 16}
});
```

### 3. DocuSeal Integration Updates

**Current Status**:

- ✅ DocuSeal templates are stored in database
- ✅ Manufacturer fields are dynamically loaded
- ✅ Step6ManufacturerQuestions uses database templates
- ❌ Need to ensure Step4Confirmation is used for IVR (not Step6)

**Required Fix**:
According to Ashley's workflow, IVR should happen in Step 4, not Step 6:

```typescript
// In CreateNew.tsx, ensure Step4Confirmation is included:
{currentSection === 3 && (
    <Step4Confirmation
        formData={formData}
        updateFormData={updateFormData}
        products={products}
        errors={errors}
    />
)}
```

### 4. API Error Fixes

**Payer Search Error** (500 Internal Server Error):

```
GET https://wound-care-react-laravel.test/api/payers/search?q=&limit=50 500
```

- Check if PayerController exists and has proper search method
- Ensure payers table is seeded

### 5. Google Maps API Warnings

The console shows Google Maps Autocomplete deprecation warnings. Update `GoogleAddressAutocompleteSimple.tsx` to use the new `PlaceAutocompleteElement` API instead of the deprecated `Autocomplete` API.

## Final Implementation Checklist

1. **Fix Encoding Issues**
   - [ ] Remove and recreate Step6ReviewSubmit.tsx without BOM
   - [ ] Verify all QuickRequest component files are UTF-8 encoded

2. **Complete Product Sizing Changes**
   - [ ] Create migration for size labels
   - [ ] Update ProductController to return size labels
   - [ ] Update all frontend components to use size dropdowns
   - [ ] Test price calculations with new size format

3. **DocuSeal Flow Corrections**
   - [ ] Ensure Step4Confirmation is the IVR step (not Step6)
   - [ ] Verify episode creation happens after product selection
   - [ ] Test complete flow with real DocuSeal templates

4. **API Fixes**
   - [ ] Fix payer search endpoint
   - [ ] Ensure all required seeders have run
   - [ ] Test all API endpoints used by QuickRequest

5. **Final Testing**
   - [ ] Complete end-to-end test of QuickRequest flow
   - [ ] Verify DocuSeal form submission and webhook handling
   - [ ] Test episode creation and order submission
   - [ ] Verify all data is properly saved
