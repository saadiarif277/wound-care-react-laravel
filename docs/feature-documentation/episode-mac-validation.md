# Episode MAC Validation Feature Implementation Guide

## Overview
This feature adds Medicare Administrative Contractor (MAC) validation information directly to Episode cards, providing immediate visibility into coverage risks and compliance status for wound care orders.

## Features Added

### 1. Visual MAC Validation Panel
- **Risk Score Display**: Shows denial risk percentage (0-100%)
- **Coverage Status**: Indicates if products are covered, conditional, or require prior auth
- **MAC Contractor Info**: Displays the responsible MAC and jurisdiction
- **LCD Compliance**: Shows compliance status and missing documentation
- **Financial Impact**: Displays potential denial amounts and estimated reimbursements
- **Actionable Recommendations**: Provides prioritized actions to reduce denial risk

### 2. Components Created

#### MacValidationPanel.tsx
- Location: `resources/js/Components/Episodes/MacValidationPanel.tsx`
- Purpose: Displays comprehensive MAC validation data within episode cards
- Features:
  - Real-time API data fetching
  - Color-coded risk indicators
  - Expandable detail sections
  - Loading and error states

#### EpisodeMacValidationController.php
- Location: `app/Http/Controllers/Api/EpisodeMacValidationController.php`
- Purpose: API endpoint for MAC validation data
- Features:
  - Risk analysis based on products, value, and frequency
  - LCD compliance checking
  - Financial impact calculations
  - Smart recommendation generation
  - 4-hour caching for performance

### 3. Integration Points

#### Updated Files:
1. **EpisodeCard.tsx**
   - Added `showMacValidation` prop (default: true)
   - Integrated MAC validation panel in expandable section
   - Added MAC validation indicator in main card view

2. **routes/api.php**
   - Added route: `GET /api/episodes/{episode}/mac-validation`
   - Protected with `view-orders` permission

3. **MedicareMacValidationService.php**
   - Added `getMacContractorByState()` method

4. **types/episode.ts**
   - Added `MacValidationData` interface
   - Added `EpisodeWithMacValidation` type

## Usage

### Basic Implementation
```tsx
// In your Episode listing page
<EpisodeCard 
  episode={episode}
  showMacValidation={true}  // Enable MAC validation display
  onRefresh={handleRefresh}
/>
```

### Disable MAC Validation (if needed)
```tsx
<EpisodeCard 
  episode={episode}
  showMacValidation={false}  // Hide MAC validation
/>
```

## Risk Scoring Algorithm

The system calculates risk based on:

1. **Product Risk (30 points)**
   - High-risk HCPCS codes (Q41xx, 152xx, 157xx)
   - Skin substitutes and biologics

2. **Financial Risk (20 points)**
   - Episodes over $5,000

3. **Frequency Risk (15 points)**
   - More than 3 orders in an episode

4. **Jurisdiction Risk (10 points)**
   - Strict MAC jurisdictions (JL - Novitas, JJ - Palmetto)

Risk Levels:
- Low: 0-29%
- Medium: 30-49%
- High: 50-69%
- Critical: 70-100%

## API Response Example

```json
{
  "success": true,
  "data": {
    "risk_score": 45,
    "risk_level": "medium",
    "coverage_status": "conditional",
    "contractor": {
      "name": "Novitas Solutions",
      "jurisdiction": "JL"
    },
    "lcd_compliance": {
      "status": "partial",
      "missing_criteria": ["Prior auth not verified"],
      "documentation_required": ["Failed conservative treatment documentation"]
    },
    "denial_prediction": {
      "probability": 0.45,
      "top_risk_factors": [
        {
          "factor": "High-risk product category",
          "impact": "high",
          "mitigation": "Ensure complete documentation"
        }
      ]
    },
    "financial_impact": {
      "potential_denial_amount": 2250.00,
      "approval_confidence": 55,
      "estimated_reimbursement": 2750.00
    },
    "recommendations": [
      {
        "priority": "high",
        "action": "Complete all required documentation",
        "impact": "Required for claim approval"
      }
    ]
  }
}
```

## Performance Considerations

1. **Caching**: MAC validation data is cached for 4 hours per episode
2. **Lazy Loading**: Panel only fetches data when episode card is on screen
3. **Error Handling**: Graceful fallback if API fails

## Future Enhancements

1. **Real-time LCD Updates**: WebSocket integration for policy changes
2. **Batch Validation**: Validate multiple episodes at once
3. **Export Reports**: Generate MAC validation reports for compliance
4. **Prior Auth Integration**: Direct submission to payers
5. **ML Improvements**: Learn from actual denial/approval outcomes

## Testing

Run the following to test:

```bash
# Test the API endpoint
curl -X GET http://localhost:8000/api/episodes/{episode-id}/mac-validation \
  -H "Authorization: Bearer {token}"

# Run PHP tests
php artisan test --filter=EpisodeMacValidation

# Run React component tests
npm run test -- MacValidationPanel
```

## Deployment Checklist

- [ ] Run migrations (none needed for this feature)
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Build assets: `npm run build`
- [ ] Test API endpoint
- [ ] Verify UI displays correctly
- [ ] Check performance with production data
- [ ] Monitor error logs for first 24 hours

## Support

For issues or questions:
1. Check browser console for API errors
2. Verify user has `view-orders` permission
3. Check Laravel logs for backend errors
4. Ensure episode has associated orders and products
