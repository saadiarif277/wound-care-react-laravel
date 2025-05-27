# Clinical Opportunity Engine

## Overview

The Clinical Opportunity Engine is a decision support and insight module designed to identify real-time opportunities for improving both patient outcomes and practice revenue. It integrates tightly with the OrderForm, MAC validation engine, and patient documentation workflows.

---

## Goals

* Increase provider awareness of billable services and their clinical value
* Surface underutilized CPT/HCPCS codes based on current visit context
* Improve documentation completeness and compliance
* Connect clinical decisions to revenue and outcome impact

---

## Features

### 1. Opportunity Scanning Engine

* Maps CPT/HCPCS codes to:

  * ICD-10 diagnosis codes
  * MAC/NCD/LCD coverage requirements
  * Treatment timelines and frequency limits
* Flags codes based on missing modifiers, eligibility, or documentation gaps

### 2. Outcomes & ROI Overlay

* Displays estimated revenue and outcome uplift for each recommendation
* Sources include CMS fee schedules and published outcome metrics

### 3. UI Recommendation Panel

A React-based component example:

```tsx
<OpportunityCard
  code="G0177"
  title="Caregiver Education & Training"
  description="Teach patient/caregiver wound management. Covered once per 30 days."
  expectedRevenue="$78.60"
  outcomeImpact="+10% wound self-care score"
/>
```

### 4. Audit Trail

* Logs each recommendation:

  * Accepted or dismissed
  * Timestamp
  * Linked patient, user, and order
* Used for analytics and provider feedback

---

## Implementation Layers

| Layer         | Tool / Logic                                                            |
| ------------- | ----------------------------------------------------------------------- |
| **Data**      | CMS, MAC LCD/NCD, fee schedules, wound care studies                     |
| **Rules**     | JSON or DB table linking ICD-10 + care stage to CPT/HCPCS               |
| **Engine**    | Rule evaluator triggered by visit, form completion, or diagnosis change |
| **Frontend**  | Live recommendations inside OrderForm or PlanOfCare builder             |
| **Reporting** | Compare accepted vs. ignored recs for revenue/outcome lift              |

---

## Example Opportunities

| Code  | Description                  | Revenue   | Outcome Impact             |
| ----- | ---------------------------- | --------- | -------------------------- |
| G0177 | Caregiver education          | \~\$78.60 | +10% wound self-care score |
| 97597 | Wound debridement            | \~\$130   | Accelerates healing by 20% |
| G0511 | Chronic care management      | \~\$65/mo | Improves care coordination |
| 99497 | Advance care planning        | \~\$82    | Better goal-aligned care   |
| 11720 | Nail debridement (DM)        | \~\$35    | Reduces ulcer risk         |
| 99406 | Tobacco cessation counseling | \~\$15    | Better healing, lower risk |

---

## Next Steps

* Integrate with MAC validation engine
* Display recommendations post-eligibility check
* Enable dismiss/accept UI with optional audit trail
* Expand ruleset monthly using CMS updates and real-world usage patterns
