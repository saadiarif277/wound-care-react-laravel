# Finish Task: Complete IVR Field Mapping & Provider Product Integration

## Background Context

This document outlines the remaining work to complete the dynamic IVR field mapping system for the MSC Wound Care platform. The system needs to:

1. **Filter products based on provider onboarding** - Only show products the provider is authorized to order
2. **Apply complex insurance-based product rules** - Different products allowed based on insurance type, wound size, and patient state
3. **Dynamically map form fields to manufacturer-specific IVR forms** - Each manufacturer has different DocuSeal templates
4. **Show real-time field coverage** - Display which fields will be auto-filled vs require manual entry

## Current Implementation Status

### ✅ Completed Components

1. **Backend API Controllers**
   - `app/Http/Controllers/Api/ProviderProductController.php` - Get provider's onboarded Q-codes
   - `app/Http/Controllers/Api/IvrFieldController.php` - Get manufacturer fields and calculate coverage

2. **Frontend Components**
   - `ProductSelectorQuickRequest.tsx` - Complex product selection with insurance rules
   - `DocuSealEmbed.tsx` - DocuSeal form embedding component
   - Episode creation flow in `Step1CreateEpisode.tsx`

3. **Configuration**
   - `manufacturerFields.ts` - Frontend manufacturer field definitions

### 🔧 Needs Completion

1. **Fix Type Error in IvrFieldController**
   - Line 105: Change from `stdClass` to actual `ProductRequest` model
   - Need to properly instantiate ProductRequest or create a DTO

2. **Add API Routes**
   - Provider product endpoints
   - IVR field mapping endpoints

3. **Update Frontend Integration**
   - Wire up provider product filtering in Step5ProductSelection
   - Add dynamic IVR preview functionality
   - Connect DocuSeal with manufacturer-specific templates

4. **Complete User Onboarding Relationship**
   - Ensure User model has `onboardedProducts` relationship
   - Add proper pivot table attributes

## Manufacturer IVR Forms (From docs/newest/IVRS)

Each manufacturer has specific IVR forms that need to be mapped:

1. **ACZ Distribution** - `Updated Q2 IVR ACZ.pdf`
2. **Advanced Health** - `Template IVR Advanced Solution Universal REV2.0`
3. **MedLife (Amnio AMP)** - `AMNIO AMP MedLife IVR-fillable.pdf`
4. **Centurion (AmnioBand)** - `Centurion AmnioBand IVR` (STAT orders only)
5. **BioWerX** - `BioWerX Fillable IVR Apr 2024.pdf`
6. **BioWound** - `BioWound IVR v3` + `California-Non-HOPD-IVR-Form.pdf`
7. **Extremity Care** - Multiple forms for Q2/Q4 products
8. **Skye Biologics** - `WoundPlus.Patient.Insurance.Verification.Form`
9. **Total Ancillary** - `Universal_Benefits_Verification`

## Insurance Product Rules (From PRD)

```javascript
// These rules are already implemented in ProductSelectorQuickRequest.tsx
const INSURANCE_PRODUCT_RULES = {
  'ppo/commercial': ['Q4154'], // BioVance only
  'medicare': {
    '0-250': ['Q4250', 'Q4290'], // AmnioAMP OR Membrane Wrap Hydro
    '251-450': ['Q4290'], // Membrane Wrap Hydro only
    '>450': [] // Consultation required
  },
  'medicaid': {
    // State-specific rules
  }
};
```

## Implementation Steps

### Step 1: Fix IvrFieldController Type Error

```php
// Replace the createMockProductRequest method with:
private function createMockProductRequest($formData)
{
    // Create a proper ProductRequest instance
    $productRequest = new ProductRequest();
    
    // Fill with form data
    $productRequest->fill([
        'provider_id' => $formData['provider_id'] ?? null,
        'facility_id' => $formData['facility_id'] ?? null,
        'patient_fhir_id' => $formData['patient_fhir_id'] ?? null,
        'expected_service_date' => $formData['expected_service_date'] ?? null,
        'wound_type' => $formData['wound_type'] ?? null,
        'payer_name_submitted' => $formData['payer_name_submitted'] ?? null,
    ]);
    
    // Create mock relationships
    $productRequest->setRelation('provider', new User([
        'id' => $formData['provider_id'] ?? null,
        'first_name' => $formData['provider_first_name'] ?? '',
        'last_name' => $formData['provider_last_name'] ?? '',
        'npi_number' => $formData['provider_npi'] ?? ''
    ]));
    
    $productRequest->setRelation('facility', new \App\Models\Fhir\Facility([
        'id' => $formData['facility_id'] ?? null,
        'name' => $formData['facility_name'] ?? '',
        'address' => $formData['facility_address'] ?? '',
        'city' => $formData['facility_city'] ?? '',
        'state' => $formData['facility_state'] ?? '',
        'zip_code' => $formData['facility_zip'] ?? '',
        'npi' => $formData['facility_npi'] ?? '',
        'tax_id' => $formData['facility_tax_id'] ?? '',
        'ptan' => $formData['facility_ptan'] ?? ''
    ]));
    
    // Add organization with sales rep
    $productRequest->setRelation('organization', new \App\Models\Organization([
        'id' => $formData['organization_id'] ?? null,
        'notification_emails' => $formData['notification_emails'] ?? ''
    ]));
    
    // Add products collection
    $products = [];
    if (isset($formData['selected_products']) && is_array($formData['selected_products'])) {
        foreach ($formData['selected_products'] as $productData) {
            $product = new \App\Models\Product([
                'name' => $productData['name'] ?? '',
                'code' => $productData['code'] ?? '',
                'q_code' => $productData['q_code'] ?? '',
                'manufacturer' => $productData['manufacturer'] ?? ''
            ]);
            $product->pivot = new \stdClass();
            $product->pivot->size = $productData['size'] ?? '';
            $product->pivot->quantity = $productData['quantity'] ?? 1;
            $products[] = $product;
        }
    }
    $productRequest->setRelation('products', collect($products));
    
    // Add episode with metadata
    $productRequest->setRelation('episode', new \App\Models\PatientManufacturerIVREpisode([
        'metadata' => [
            'extracted_data' => $formData['extracted_data'] ?? []
        ]
    ]));
    
    return $productRequest;
}
```

### Step 2: Add API Routes

```php
// In routes/api.php
Route::prefix('v1')->group(function () {
    // Provider product endpoints
    Route::get('providers/{providerId}/onboarded-products', [ProviderProductController::class, 'getOnboardedProducts']);
    Route::get('providers/all-products', [ProviderProductController::class, 'getAllProvidersProducts']);
    
    // IVR field mapping endpoints
    Route::get('ivr/manufacturers', [IvrFieldController::class, 'getManufacturers']);
    Route::get('ivr/manufacturers/{key}/fields', [IvrFieldController::class, 'getManufacturerFields']);
    Route::post('ivr/calculate-coverage', [IvrFieldController::class, 'calculateFieldCoverage']);
});
```

### Step 3: Add User Onboarded Products Relationship

```php
// In app/Models/User.php
public function onboardedProducts()
{
    return $this->belongsToMany(Product::class, 'provider_onboarded_products', 'provider_id', 'product_id')
        ->withPivot(['onboarding_status', 'expiration_date', 'notes'])
        ->withTimestamps();
}
```

### Step 4: Update Step5ProductSelection Component

```typescript
// In Step5ProductSelection.tsx
import { useState, useEffect } from 'react';
import { ProductSelectorQuickRequest } from '@/Pages/ProductRequest/Components/ProductSelectorQuickRequest';

interface Step5ProductSelectionProps {
    formData: any;
    updateFormData: (data: any) => void;
    onNext: () => void;
    onPrev: () => void;
    roleRestrictions: any;
}

export default function Step5ProductSelection({
    formData,
    updateFormData,
    onNext,
    onPrev,
    roleRestrictions
}: Step5ProductSelectionProps) {
    const [providerOnboardedProducts, setProviderOnboardedProducts] = useState<string[]>([]);
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        // Fetch provider's onboarded products
        const fetchProviderProducts = async () => {
            try {
                if (formData.provider_id) {
                    const response = await fetch(`/api/v1/providers/${formData.provider_id}/onboarded-products`);
                    const data = await response.json();
                    setProviderOnboardedProducts(data.q_codes || []);
                }
            } catch (error) {
                console.error('Error fetching provider products:', error);
            } finally {
                setLoading(false);
            }
        };
        
        fetchProviderProducts();
    }, [formData.provider_id]);
    
    const calculateWoundSize = (formData: any) => {
        // Extract wound size from clinical assessment
        const length = parseFloat(formData.wound_length || 0);
        const width = parseFloat(formData.wound_width || 0);
        return length * width;
    };
    
    const handleProductChange = (products: any[]) => {
        updateFormData({
            ...formData,
            selected_products: products
        });
    };
    
    if (loading) {
        return <div>Loading provider products...</div>;
    }
    
    return (
        <div className="space-y-6">
            <h2 className="text-2xl font-bold">Select Products</h2>
            
            <ProductSelectorQuickRequest
                insuranceType={formData.primary_plan_type || 'commercial'}
                patientState={formData.patient_state}
                woundSize={calculateWoundSize(formData)}
                providerOnboardedProducts={providerOnboardedProducts}
                onProductsChange={handleProductChange}
                roleRestrictions={roleRestrictions}
                selectedProducts={formData.selected_products || []}
            />
            
            <div className="flex justify-between mt-6">
                <button
                    onClick={onPrev}
                    className="px-4 py-2 bg-gray-200 rounded"
                >
                    Previous
                </button>
                <button
                    onClick={onNext}
                    disabled={!formData.selected_products?.length}
                    className="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
                >
                    Next
                </button>
            </div>
        </div>
    );
}
```

### Step 5: Create Dynamic IVR Preview Component

```typescript
// New component: IvrFieldPreview.tsx
import { useState, useEffect } from 'react';

interface IvrFieldPreviewProps {
    formData: any;
    manufacturer: string;
}

interface FieldCoverage {
    total_fields: number;
    filled_fields: number;
    missing_fields: string[];
    extracted_fields: string[];
    percentage: number;
    coverage_level: 'excellent' | 'good' | 'fair' | 'poor';
}

export function IvrFieldPreview({ formData, manufacturer }: IvrFieldPreviewProps) {
    const [fields, setFields] = useState<any[]>([]);
    const [coverage, setCoverage] = useState<FieldCoverage | null>(null);
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        const fetchFieldsAndCoverage = async () => {
            try {
                // Get manufacturer fields
                const fieldsResponse = await fetch(`/api/v1/ivr/manufacturers/${manufacturer}/fields`);
                const fieldsData = await fieldsResponse.json();
                setFields(fieldsData.fields || []);
                
                // Calculate coverage
                const coverageResponse = await fetch('/api/v1/ivr/calculate-coverage', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        manufacturer_key: manufacturer,
                        form_data: formData,
                        patient_data: formData.patient_fhir_data || {}
                    })
                });
                const coverageData = await coverageResponse.json();
                setCoverage(coverageData.coverage);
            } catch (error) {
                console.error('Error fetching IVR data:', error);
            } finally {
                setLoading(false);
            }
        };
        
        fetchFieldsAndCoverage();
    }, [manufacturer, formData]);
    
    if (loading) {
        return <div>Loading IVR field preview...</div>;
    }
    
    const getCoverageColor = (level: string) => {
        switch (level) {
            case 'excellent': return 'text-green-600';
            case 'good': return 'text-blue-600';
            case 'fair': return 'text-yellow-600';
            case 'poor': return 'text-red-600';
            default: return 'text-gray-600';
        }
    };
    
    return (
        <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold mb-4">IVR Field Coverage Preview</h3>
            
            {coverage && (
                <div className="mb-6">
                    <div className="flex items-center gap-4 mb-2">
                        <span className="text-3xl font-bold">{coverage.percentage}%</span>
                        <span className={`text-sm font-medium ${getCoverageColor(coverage.coverage_level)}`}>
                            {coverage.coverage_level.charAt(0).toUpperCase() + coverage.coverage_level.slice(1)} Coverage
                        </span>
                    </div>
                    <div className="text-sm text-gray-600">
                        {coverage.filled_fields} of {coverage.total_fields} fields will be auto-filled
                    </div>
                    
                    {/* Progress bar */}
                    <div className="mt-2 bg-gray-200 rounded-full h-2">
                        <div 
                            className="bg-blue-600 h-2 rounded-full"
                            style={{ width: `${coverage.percentage}%` }}
                        />
                    </div>
                </div>
            )}
            
            {/* Field status list */}
            <div className="space-y-4">
                <div>
                    <h4 className="font-medium text-green-600 mb-2">
                        ✓ Auto-filled Fields ({coverage?.extracted_fields.length || 0})
                    </h4>
                    <ul className="text-sm space-y-1">
                        {coverage?.extracted_fields.map((field, idx) => (
                            <li key={idx} className="text-gray-600">• {field}</li>
                        ))}
                    </ul>
                </div>
                
                {coverage?.missing_fields.length > 0 && (
                    <div>
                        <h4 className="font-medium text-amber-600 mb-2">
                            ⚠ Manual Entry Required ({coverage.missing_fields.length})
                        </h4>
                        <ul className="text-sm space-y-1">
                            {coverage.missing_fields.map((field, idx) => (
                                <li key={idx} className="text-gray-600">• {field}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}
```

### Step 6: Integrate DocuSeal with Manufacturer Templates

```typescript
// In the final submission step, use manufacturer-specific template
import { DocuSealEmbed } from '@/Components/DocuSeal/DocuSealEmbed';
import { IvrFieldPreview } from '@/Components/IVR/IvrFieldPreview';

const ReviewAndSubmitStep = ({ formData, onSubmit }) => {
    const [showDocuSeal, setShowDocuSeal] = useState(false);
    const [manufacturerConfig, setManufacturerConfig] = useState(null);
    
    const getManufacturerFromProduct = (product: any) => {
        // Map product to manufacturer key
        const manufacturerMap = {
            'Q4154': 'ACZ_Distribution',
            'Q4250': 'MedLife',
            'Q4290': 'Extremity_Care',
            // Add more mappings
        };
        return manufacturerMap[product.q_code] || product.manufacturer;
    };
    
    const handleSubmit = async () => {
        // Get manufacturer from selected product
        const manufacturer = getManufacturerFromProduct(formData.selected_products[0]);
        
        // Fetch manufacturer configuration
        const response = await fetch(`/api/v1/ivr/manufacturers/${manufacturer}/fields`);
        const config = await response.json();
        setManufacturerConfig(config);
        
        // Show DocuSeal embed
        setShowDocuSeal(true);
    };
    
    const handleDocuSealComplete = (submissionId: string) => {
        // Update form data with DocuSeal submission
        formData.docuseal_submission_id = submissionId;
        formData.ivr_sent_at = new Date().toISOString();
        
        // Submit the complete order
        onSubmit(formData);
    };
    
    return (
        <div className="space-y-6">
            <h2 className="text-2xl font-bold">Review & Submit</h2>
            
            {/* Show IVR field preview */}
            {formData.selected_products?.length > 0 && (
                <IvrFieldPreview 
                    formData={formData}
                    manufacturer={getManufacturerFromProduct(formData.selected_products[0])}
                />
            )}
            
            {/* Order summary */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="font-semibold mb-3">Order Summary</h3>
                <dl className="space-y-2">
                    <div className="flex justify-between">
                        <dt>Patient ID:</dt>
                        <dd>{formData.patient_display_id}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Provider:</dt>
                        <dd>{formData.provider_name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Product:</dt>
                        <dd>{formData.selected_products?.[0]?.name}</dd>
                    </div>
                </dl>
            </div>
            
            {!showDocuSeal ? (
                <button
                    onClick={handleSubmit}
                    className="w-full py-3 bg-blue-600 text-white rounded-lg font-medium"
                >
                    Proceed to IVR Form
                </button>
            ) : (
                <DocuSealEmbed
                    templateId={manufacturerConfig?.template_id}
                    userEmail={formData.provider_email}
                    templateName={`${manufacturerConfig?.name} IVR Form`}
                    documentUrls={formData.uploaded_documents || []}
                    onComplete={handleDocuSealComplete}
                />
            )}
        </div>
    );
};
```

## Testing Checklist

1. [ ] Provider can only see their onboarded products
2. [ ] Insurance rules filter products correctly
3. [ ] any >450 sq cm shows consultation modal
4. [ ] Medicaid state-specific rules work
5. [ ] IVR field preview shows correct coverage %
6. [ ] DocuSeal loads correct manufacturer template
7. [ ] All form data maps to IVR fields properly
8. [ ] Episode is created with correct manufacturer
9. [ ] IVR completion updates ProductRequest with submission ID
10. [ ] Admin can track IVR status in Order Management

## Database Migrations Needed

```php
// Create provider_onboarded_products table if not exists
Schema::create('provider_onboarded_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('provider_id')->constrained('users');
    $table->foreignId('product_id')->constrained('products');
    $table->enum('onboarding_status', ['active', 'pending', 'expired', 'suspended'])->default('active');
    $table->date('expiration_date')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->unique(['provider_id', 'product_id']);
});
```

## Environment Variables

Ensure these are set in `.env`:
```
DOCUSEAL_API_KEY=your_key
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_secret
```

## Manufacturer Template IDs

These need to be configured in the database or environment:
```
ACZ_DISTRIBUTION_TEMPLATE_ID=xxx
ADVANCED_HEALTH_TEMPLATE_ID=xxx
MEDLIFE_TEMPLATE_ID=xxx
CENTURION_TEMPLATE_ID=xxx
BIOWERX_TEMPLATE_ID=xxx
BIOWOUND_TEMPLATE_ID=xxx
EXTREMITY_CARE_TEMPLATE_ID=xxx
SKYE_BIOLOGICS_TEMPLATE_ID=xxx
TOTAL_ANCILLARY_TEMPLATE_ID=xxx
```

## Final Notes

- The system supports 9 different manufacturers with unique IVR forms
- Each manufacturer has specific field requirements and DocuSeal templates
- Provider onboarding determines which products can be ordered
- Insurance rules further filter available products
- The goal is 90%+ field auto-fill from episode data + document extraction
- IVR status tracking is integrated into the ProductRequest model

## Priority Order

1. Fix the type error in IvrFieldController (Step 1)
2. Add the API routes (Step 2)
3. Add User relationship for onboarded products (Step 3)
4. Update Step5ProductSelection to use ProductSelectorQuickRequest (Step 4)
5. Create IvrFieldPreview component (Step 5)
6. Integrate DocuSeal with manufacturer templates (Step 6)
7. Create migration for provider_onboarded_products if needed
8. Test the complete flow

This will complete the dynamic IVR field mapping system and enable providers to efficiently create orders with minimal manual data entry.
