📄 MSC Platform – Product Selection + Logic (PRD)
1. Overview
Feature Name: Product Selection for Order Request
 Module: Order Workflow – Provider & OM Portal
 Target Users: Office Managers (OMs), Providers
 Priority: High
 Owner: Product Team

2. Objective
Enable providers and OMs to select only eligible, reimbursable products they’ve been onboarded with, while applying payer-specific, size-based, and state-based filters to reduce claim denials and ensure compliance.

3. User Stories
As an OM or Provider, I want to view only products I’m eligible to order so I don’t mistakenly request items that may be denied or uncovered.
As a Provider, I want to see discount pricing to make informed decisions on cost-efficient care.
As a User, I want alerts when products require special review so I can follow up with the appropriate team.



4. Functional Requirements
ID
Requirement Description
F1
Filter (Backend) product list based on provider onboarding; hide non-onboarded products.
F2
Apply payer-based rules for product visibility (PPO, Medicare, Medicaid).
F3
Apply wound-size–based filtering logic for Medicare and Medicaid.
F4
Show product name, description, sizes (WxL), ASP price, and provider discount (if logged in as provider).
F5
Allow selection of only one product type per order, but multiple sizes/quantities.
F6
Allow adding/removing products from a separate cart UI.
F7
Trigger warnings for size/quantity thresholds and 24-hour reorders.
F8

FUTURE
Provide a “Consultation Required” flow for Medicare orders >450 sq cm.

Search and Filter
Ai Recommendations 


5. Logic Rules Summary
Insurance Type
Wound Size (sq cm)
Allowed Products
Special Conditions
PPO / Commercial
Any
BioVance
Needs confirmation across plans
Medicare
0–250
AmnioAMP OR Membrane Wrap Hydro
MAC validation required
Medicare
251–450
Membrane Wrap Hydro only
AmnioAMP excluded
Medicare
>450
Consultation Required
Trigger MSC Admin email / try to point user to full product catalog
Medicaid (X states)
Any
Membrane Wrap / 


 Membrane Wrap Hydro
TX, FL, GA, TN, NC, AL, OH, MI, IN, KY, MO, OK, SC, LA, MS

Washington, Oregan, Montana, South Dakota, UTAH Arisona, CA, CO
Medicaid (X states)
Any
Restorigen
Covered by: Texas, California, Louisiana and Maryland
Medicaid (All Other states not covered above)
Any
EpiFix or BioVance or AmnioBand
Not covered by above states


6. UX/UI Considerations
Area
Design Notes
Product List
Filtered by logic; include visual cues for warnings or restrictions
Cart Component
Separate from product list; displays selected product, sizes, quantities
Warnings
Soft validation banners (not hard stops) for size limits, repeat orders
Medicare >450 Flow
Modal popup with CTA to email MSC Admin
Role-Based Display
Provider sees ASP discount; OM does not
Product Catalog
Separate tab with full product catalog and request-for-access option


7. Acceptance Criteria
ID
Test Scenario
AC1
Non-onboarded products do not appear in the selection screen
AC2
PPO user always sees Amnioband, any wound size
AC3
Medicare user sees correct product options by wound size buckets
AC4
Medicaid user sees products based on patient’s state
AC5
Products >450 sq cm for Medicare trigger consultation alert
AC6
Cart allows different sizes of the same product but only one product type
AC7
Warnings appear if size exceeds max (MUE) or 24-hour rule is triggered
AC8
UI shows ASP price + discount to Providers only


8. Open Questions  
Should PPO coverage of BioVance be confirmed per plan or default to always shown?
Products may vary based on Payor 


Should AmnioAMP be grayed out (vs. hidden) when excluded by Medicare logic?
	- Hidden
Can providers override wound size range restrictions with a note or warning?
	- warning with permissions to proceed and needs confirmation from Ashley
Who handles review for “Consultation Required” workflows?
	- Routed to MSC Admin (Ashley)
Need confirmation on the states noted above. Are Medicaid state rules expected to change frequently?


Should MAC validation warnings be actionable or just informational?
