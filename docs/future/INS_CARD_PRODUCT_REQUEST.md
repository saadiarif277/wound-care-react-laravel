# Insurance Card PDF Extraction for Product Requests

**Status:** 🔄 Future Enhancement (Post-Pilot)  
**Priority:** High  
**Estimated Implementation:** 3-5 days  
**ROI:** 3-4 minutes saved per product request  

## Executive Summary

Implement Azure Document Intelligence to automatically extract insurance information from PDF scans of insurance cards, eliminating manual data entry errors and reducing product request creation time by 70%.

## Problem Statement

### Current Pain Points
1. **Manual Data Entry:** Office managers manually type 10-15 fields from insurance card PDFs
2. **High Error Rate:** ~15% of IVR rejections due to insurance data typos
3. **Time Consuming:** 3-4 minutes per product request just for insurance data
4. **Context Switching:** Constant switching between PDF viewer and web form
5. **Bulk Processing:** No way to handle multiple patients efficiently

### User Feedback
- "I spend half my day typing insurance numbers"
- "One typo in the policy number and the whole IVR gets rejected"
- "We have PDFs for all our patients but still have to type everything"

## Proposed Solution

### Core Features
1. **PDF Upload:** Drag & drop insurance card PDFs (front/back)
2. **Auto-Extraction:** Azure DI extracts all insurance fields
3. **Auto-Population:** Form fields populate instantly
4. **Bulk Processing:** Upload multiple PDFs for batch processing
5. **Validation:** Verify extracted data against known insurers

### Technical Approach
```typescript
// Simple user flow
1. Upload insurance card PDF → 
2. Azure extracts data → 
3. Fields auto-populate → 
4. User verifies → 
5. Submit
```

## Implementation Plan

### Phase 1: MVP (3 days)
```yaml
Day 1:
  - Set up Azure Document Intelligence
  - Create extraction API endpoint
  - Test with sample PDFs

Day 2:
  - Add upload component to ProductRequest form
  - Wire up auto-population
  - Basic error handling

Day 3:
  - Test with real insurance PDFs
  - Handle edge cases
  - Deploy to staging
```

### Phase 2: Enhanced Features (2 days)
```yaml
Day 4:
  - Add bulk upload capability
  - Implement validation rules
  - Add support for secondary insurance

Day 5:
  - Polish UX with loading states
  - Add extraction confidence scores
  - Production deployment
```

## Technical Architecture

### Backend Service
```php
// app/Services/InsuranceCardExtractorService.php
class InsuranceCardExtractorService
{
    public function extractFromPDF($pdfPath): array
    {
        $result = $this->azureClient->analyzeDocument(
            'prebuilt-insurance-card',
            file_get_contents($pdfPath)
        );
        
        return [
            'primary_insurance_name' => $result->fields['insurer'],
            'primary_policy_number' => $result->fields['memberId'],
            'primary_group_number' => $result->fields['groupNumber'],
            'primary_payer_phone' => $result->fields['payerPhone'],
            'primary_plan_type' => $this->detectPlanType($result->fields['planName']),
            'rx_bin' => $result->fields['rxBin'],
            'rx_pcn' => $result->fields['rxPcn'],
        ];
    }
}
```

### Frontend Component
```typescript
// resources/js/Components/InsuranceCardUpload.tsx
const InsuranceCardUpload: React.FC<{onExtract: (data: any) => void}> = ({ onExtract }) => {
    const [extracting, setExtracting] = useState(false);
    
    const handleUpload = async (file: File) => {
        setExtracting(true);
        const formData = new FormData();
        formData.append('insurance_card', file);
        
        const response = await axios.post('/api/insurance-card/extract', formData);
        onExtract(response.data);
        setExtracting(false);
    };
    
    return (
        <div className="border-2 border-dashed border-gray-300 rounded-lg p-6">
            <input type="file" accept=".pdf" onChange={(e) => handleUpload(e.target.files[0])} />
            {extracting && <Loader2 className="animate-spin" />}
        </div>
    );
};
```

## ROI Analysis

### Time Savings
```
Current Process: 3-4 minutes manual entry
New Process: 15 seconds upload + verify
Time Saved: ~3.5 minutes per request

Weekly Impact (100 requests):
- Time saved: 350 minutes (5.8 hours)
- Error reduction: 15 fewer rejections
- Staff satisfaction: Significant increase
```

### Cost Breakdown
```
Development: 3-5 days × $1,000/day = $3,000-5,000
Azure DI: ~$50/month ongoing
Break-even: 2-3 months
```

## Success Metrics

1. **Adoption Rate:** % of product requests using PDF extraction
2. **Time Reduction:** Average time to complete insurance section
3. **Error Rate:** Reduction in insurance-related IVR rejections
4. **User Satisfaction:** Survey feedback from office managers

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|---------|------------|
| Poor PDF quality | Low extraction accuracy | Implement confidence scoring |
| Non-standard cards | Fields not extracted | Manual fallback always available |
| HIPAA concerns | Compliance issues | Don't store PDFs, only extract data |

## Future Enhancements

### Phase 3: Advanced Features
- OCR for handwritten policy numbers
- Auto-match against payer database
- Extract from faxed documents
- Mobile app scanning

### Phase 4: Broader Document Support
- Referral letters
- Prior authorization forms
- Clinical notes
- Wound photos with measurements

## User Stories

### Office Manager
> "As an office manager, I want to upload insurance card PDFs so that I don't have to manually type policy numbers"

### Sales Rep
> "As a sales rep, I want to bulk upload insurance PDFs from a new facility so that I can quickly create multiple product requests"

### MSC Admin
> "As an admin, I want to reduce insurance data errors so that we have fewer IVR rejections"

## Conclusion

This feature represents a quick win with high ROI. It solves a real pain point that affects daily operations and directly impacts the bottom line through reduced errors and faster processing. The technology is mature, implementation is straightforward, and user adoption will be high due to clear time savings.

**Recommendation:** Prioritize for Q2 2025 implementation immediately after pilot success.