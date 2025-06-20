# QuickRequest to DocuSeal Integration Guide

## Implementation Flow

### 1. Frontend: After Product Selection

In your QuickRequest React component, after the user selects a product:

```typescript
// In Step6ManufacturerQuestions.tsx or similar
const handleProductSelection = async (productId: string) => {
    // Collect all form data up to this point
    const formData = {
        // Patient info
        patient_first_name: patientData.firstName,
        patient_last_name: patientData.lastName,
        patient_dob: patientData.dateOfBirth,
        // ... all other collected fields
        
        // Selected product
        selected_products: [{
            product_id: productId,
            product_name: selectedProduct.name,
            product_code: selectedProduct.hcpcs_code,
            quantity: 1,
            size: selectedSize
        }],
        
        // Include manufacturer-specific fields if any
        manufacturer_fields: manufacturerSpecificData
    };
    
    // Call the backend to create episode and get DocuSeal URL
    const response = await axios.post('/quick-requests/prepare-docuseal-ivr', {
        patient_id: patientId,
        patient_fhir_id: patientFhirId,
        patient_display_id: patientDisplayId,
        selected_product_id: productId,
        facility_id: selectedFacilityId,
        form_data: formData
    });
    
    if (response.data.success) {
        // Store episode ID for later use
        setEpisodeId(response.data.episode_id);
        setDocuSealSubmissionId(response.data.docuseal_submission_id);
        
        // Embed DocuSeal form
        showDocuSealForm(response.data.docuseal_url);
    }
};
```

### 2. Frontend: Embed DocuSeal Form

```typescript
// Component to embed DocuSeal
import React, { useEffect } from 'react';

const DocuSealEmbed: React.FC<{ embedUrl: string; onComplete: () => void }> = ({ 
    embedUrl, 
    onComplete 
}) => {
    useEffect(() => {
        // Load DocuSeal script
        const script = document.createElement('script');
        script.src = 'https://cdn.docuseal.com/js/form.js';
        script.async = true;
        document.body.appendChild(script);
        
        // Listen for completion event
        window.addEventListener('message', (event) => {
            if (event.data.event === 'completed' && event.data.submissionId) {
                onComplete();
            }
        });
        
        return () => {
            document.body.removeChild(script);
        };
    }, []);
    
    return (
        <div className="docuseal-container">
            <docuseal-form 
                src={embedUrl}
                data-preview="false"
                className="w-full h-[800px]"
            />
        </div>
    );
};
```

### 3. Frontend: Handle DocuSeal Completion

```typescript
const handleDocuSealComplete = async () => {
    // DocuSeal is complete, now submit the full request
    const finalData = {
        ...allCollectedFormData,
        docuseal_submission_id: docuSealSubmissionId,
        episode_id: episodeId
    };
    
    // Submit to your existing store endpoint
    const response = await axios.post('/quick-requests', finalData);
    
    if (response.data.success) {
        // Redirect to success page or order details
        router.visit(`/orders/${response.data.order_id}`);
    }
};
```

## Backend Webhook Handling

The webhook will automatically update the episode when DocuSeal completes:

1. DocuSeal sends POST to `/api/v1/webhooks/docuseal/quick-request`
2. Episode status updates to `ivr_sent`
3. IVR data is stored in episode metadata
4. Episode is now ready for manufacturer submission

## Testing Steps

1. Update your `.env` with DocuSeal credentials:

```
DOCUSEAL_API_KEY=your_actual_api_key
DOCUSEAL_API_URL=https://api.docuseal.com
```

2. Create templates in DocuSeal for each manufacturer
3. Update template IDs in `config/docuseal.php`
4. Test the flow:
   - Fill QuickRequest form
   - Select product (triggers episode creation)
   - Complete DocuSeal form
   - Verify episode is updated via webhook

## Key Points

- Episode is created immediately after product selection
- DocuSeal form is pre-filled with all collected data
- One IVR/Episode can be used for multiple orders
- Episode tracks IVR expiration (180 days default)
- Manufacturer-specific fields are handled dynamically
