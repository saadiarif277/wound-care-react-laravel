# Clinical Opportunity Engine

## Overview

The Clinical Opportunity Engine is a sophisticated system within the MSC Wound Care Portal that automatically identifies care improvement opportunities by analyzing patient clinical data from multiple sources. It combines rule-based logic with AI enhancement to proactively surface actionable insights for healthcare providers.

## Architecture

### Core Components

1. **Clinical Context Builder Service** (`ClinicalContextBuilderService.php`)
   - Aggregates patient data from FHIR and operational sources
   - Builds comprehensive clinical context including:
     - Demographics
     - Active conditions and diagnoses
     - Wound characteristics
     - Care history and utilization
     - Risk factors
     - Quality metrics
     - Payer information

2. **Clinical Rule Evaluator Service** (`ClinicalRuleEvaluatorService.php`)
   - Evaluates predefined clinical rules against patient context
   - Supports multiple condition types:
     - Diagnosis-based conditions
     - Wound characteristics
     - Risk factors
     - Care gaps
     - Utilization patterns
     - Quality metrics
     - Payer requirements

3. **Clinical Opportunity Service** (`ClinicalOpportunityService.php`)
   - Main orchestration service
   - Coordinates context building and rule evaluation
   - Enhances opportunities with AI insights
   - Manages opportunity lifecycle
   - Handles action execution

### Data Model

```sql
-- Main opportunities table
clinical_opportunities
├── id
├── patient_id
├── provider_id
├── rule_id
├── type
├── category
├── priority (1-10)
├── confidence_score (0-1)
├── composite_score
├── status (identified|action_taken|resolved|dismissed)
└── data (JSON)

-- Actions taken on opportunities
clinical_opportunity_actions
├── id
├── clinical_opportunity_id
├── user_id
├── action_type
├── action_data (JSON)
└── result (JSON)

-- Rule definitions
clinical_opportunity_rules
├── id
├── rule_id
├── name
├── type
├── category
├── conditions (JSON)
└── actions (JSON)
```

## Opportunity Categories

### 1. Wound Care
- **Non-healing wounds**: Identifies wounds not progressing after 4+ weeks
- **Infection risk**: Detects high infection risk requiring intervention
- **Product optimization**: Suggests advanced wound care products

### 2. Diabetes Management
- **Diabetic foot risk**: Identifies patients at risk for foot ulcers
- **Glycemic control**: Monitors blood sugar management
- **Preventive care gaps**: Highlights missing screenings

### 3. Quality Improvement
- **Readmission prevention**: Identifies high readmission risk
- **Care coordination**: Highlights coordination opportunities
- **Outcome optimization**: Suggests interventions to improve outcomes

### 4. Preventive Care
- **Risk assessments**: Identifies missing assessments
- **Screening reminders**: Tracks preventive screenings
- **Education opportunities**: Highlights patient education needs

## API Endpoints

### Patient Opportunities

```http
GET /api/v1/clinical-opportunities/patients/{patientId}/opportunities
```

**Query Parameters:**
- `categories[]`: Filter by opportunity categories
- `min_confidence`: Minimum confidence score (0-1)
- `limit`: Maximum results (default: 20)
- `use_ai`: Enable AI enhancement (default: true)
- `force_refresh`: Force cache refresh

**Response:**
```json
{
  "success": true,
  "patient_id": "12345",
  "opportunities": [
    {
      "rule_id": "wc_001",
      "type": "non_healing_wound",
      "category": "wound_care",
      "priority": 9,
      "title": "Non-Healing Wound Requiring Advanced Treatment",
      "description": "Patient has a non-healing diabetic foot ulcer that may benefit from advanced wound care products",
      "confidence_score": 0.92,
      "composite_score": 0.875,
      "actions": [
        {
          "type": "order_product",
          "priority": "high",
          "description": "Consider advanced wound care products",
          "details": {
            "recommended_products": ["Collagen matrix", "Growth factor therapy"]
          }
        }
      ],
      "evidence": [
        "Wound meets criteria: wound size 12cm²",
        "4 wound treatments identified: Standard dressing changes"
      ],
      "potential_impact": {
        "healing_acceleration": "40-60%",
        "cost_savings": "$2,000-5,000",
        "quality_improvement": "Reduced healing time by 4-6 weeks"
      }
    }
  ],
  "summary": {
    "total_opportunities": 5,
    "by_category": {
      "wound_care": 3,
      "diabetes_management": 1,
      "quality_improvement": 1
    },
    "urgent_actions": 2,
    "potential_cost_savings": 15500
  },
  "generated_at": "2025-01-31T10:30:00Z"
}
```

### Take Action

```http
POST /api/v1/clinical-opportunities/opportunities/{opportunityId}/actions
```

**Request Body:**
```json
{
  "type": "order_product",
  "data": {
    "product_ids": ["prod_123", "prod_456"],
    "urgency": "routine"
  },
  "notes": "Discussed with patient, proceeding with advanced therapy"
}
```

### Dashboard Analytics

```http
GET /api/v1/clinical-opportunities/dashboard
```

**Query Parameters:**
- `date_from`: Start date filter
- `date_to`: End date filter
- `provider_id`: Filter by provider
- `category`: Filter by category

## Rule Engine

### Rule Structure

```json
{
  "id": "wc_001",
  "type": "non_healing_wound",
  "priority": 9,
  "conditions": [
    {
      "type": "wound_characteristic",
      "min_duration_weeks": 4,
      "healing_status": "stalled"
    },
    {
      "type": "care_gap",
      "gap_type": "wound_treatments"
    }
  ],
  "actions": [
    {
      "type": "order_product",
      "priority": "high",
      "description": "Consider advanced wound care products"
    }
  ]
}
```

### Condition Types

1. **Diagnosis Conditions**
   - Match by ICD-10 codes
   - Match by diagnosis categories

2. **Wound Characteristic Conditions**
   - Wound type, size, duration
   - Healing progress status

3. **Risk Factor Conditions**
   - Diabetes risk score
   - Infection risk score
   - Readmission risk score

4. **Care Gap Conditions**
   - Missing assessments
   - Overdue procedures
   - Medication gaps

## AI Enhancement

The engine can enhance rule-based opportunities with AI insights:

1. **Confidence Adjustment**: AI validates and adjusts confidence scores
2. **Additional Insights**: AI provides contextual insights
3. **Pattern Recognition**: AI identifies patterns not captured by rules
4. **Predictive Analysis**: AI predicts likely outcomes

## Implementation Guide

### 1. Basic Usage

```php
// Identify opportunities for a patient
$opportunities = $clinicalOpportunityService->identifyOpportunities($patientId, [
    'categories' => ['wound_care', 'diabetes_management'],
    'min_confidence' => 0.7,
    'use_ai' => true
]);

// Take action on an opportunity
$result = $clinicalOpportunityService->takeAction($opportunityId, [
    'type' => 'order_product',
    'user_id' => Auth::id(),
    'data' => ['product_ids' => ['prod_123']]
]);
```

### 2. Adding Custom Rules

```php
// Create a new rule
$rule = [
    'rule_id' => 'custom_001',
    'name' => 'Custom Care Opportunity',
    'type' => 'custom_type',
    'category' => 'quality_improvement',
    'priority' => 7,
    'conditions' => [
        [
            'type' => 'diagnosis',
            'icd10_codes' => ['E11.9', 'E11.65']
        ],
        [
            'type' => 'risk_factor',
            'risk_type' => 'readmission_risk',
            'threshold' => 0.6
        ]
    ],
    'actions' => [
        [
            'type' => 'update_care_plan',
            'priority' => 'medium',
            'description' => 'Implement intensive monitoring'
        ]
    ]
];
```

### 3. Frontend Integration

```javascript
// React component example
const ClinicalOpportunities = ({ patientId }) => {
  const { data, loading } = useQuery(
    ['clinical-opportunities', patientId],
    () => fetchOpportunities(patientId)
  );

  const handleAction = async (opportunityId, action) => {
    await takeOpportunityAction(opportunityId, action);
    queryClient.invalidateQueries(['clinical-opportunities']);
  };

  return (
    <OpportunityList
      opportunities={data?.opportunities || []}
      onAction={handleAction}
      loading={loading}
    />
  );
};
```

## Security & Compliance

### HIPAA Compliance
- All PHI data accessed through secure FHIR APIs
- Audit logging for all opportunity actions
- Role-based access control

### Data Separation
- PHI stored in Azure Health Data Services
- Operational data in Supabase
- Referential integrity maintained

### Access Control
- Provider role required for viewing opportunities
- Admin role required for rule management
- Patient consent verified before data access

## Performance Optimization

### Caching Strategy
- Opportunities cached for 30 minutes
- Cache invalidated on patient data changes
- Force refresh available via API

### Query Optimization
- Indexed database columns for fast lookups
- Composite indexes for common query patterns
- Pagination for large result sets

### Scalability
- Asynchronous processing for complex evaluations
- Horizontal scaling of rule evaluation
- Rate limiting on API endpoints

## Monitoring & Analytics

### Key Metrics
- Opportunities identified per category
- Action completion rates
- Time to resolution
- Outcome improvements

### Logging
- All API calls logged with context
- Error tracking with stack traces
- Performance metrics tracked

### Dashboards
- Real-time opportunity trends
- Provider performance metrics
- Outcome analytics

## Best Practices

1. **Regular Rule Updates**
   - Review and update rules monthly
   - Incorporate feedback from providers
   - Validate against clinical guidelines

2. **Action Follow-up**
   - Track action outcomes
   - Document resolution status
   - Measure impact on patient care

3. **Provider Training**
   - Train providers on opportunity types
   - Explain confidence scores
   - Demonstrate action workflows

## Troubleshooting

### Common Issues

1. **No opportunities found**
   - Verify patient has clinical data
   - Check rule activation status
   - Review confidence threshold

2. **Low confidence scores**
   - Ensure complete patient data
   - Verify rule conditions
   - Check AI service status

3. **Action failures**
   - Validate action permissions
   - Check integration endpoints
   - Review error logs

## Future Enhancements

1. **Machine Learning Models**
   - Custom models for opportunity prediction
   - Outcome prediction algorithms
   - Personalized recommendations

2. **Integration Expansions**
   - Direct EHR integration
   - Automated action execution
   - Third-party clinical tools

3. **Advanced Analytics**
   - Population health insights
   - Predictive risk modeling
   - Cost-benefit analysis