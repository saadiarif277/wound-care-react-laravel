🧾 Product Requirements Document (PRD)
Feature Name: Admin Order Management Center
Module: Order Management
Target User: MSC Admin
Status: Draft
Priority: High
Owner: Product

🎯 Objective
Enable MSC Admin users to manage provider-submitted product request efficiently, prioritize actions, generate and track IVR submissions, view full order details, and support order lifecycles including approval (submission to manufacturer), rejection, and direct order entry.

🖥️ Landing Page – Order Center Dashboard  
When the user logs in, they land on the Order Center page.
Must-Have Dashboard Elements:
Table/List View of all orders with sortable columns
Sticky top filter or segmented tabs for:
Orders requiring action (default view)
All orders
Visible Columns:
Order ID
Provider Name
Patient Identifier (no PHI)
Order Status
Order Request Date (date requested by Provider / OM)
Manufacturer Name
Action Required (Yes/No)
Sort/Filter Options:
Status (see full status list below)
(Future) Date Range, Provider, Manufacturer
(Future) Search by Order ID or Provider 

UX/UI Considerations

Sticky Filters: Tabs for “Requiring Action” and “All Orders” should remain fixed on scroll.
Highlight Urgency: Use badges, icons, or color indicators (e.g., red dot, “⚠️”) for orders needing immediate admin action.

Clickable Rows: Entire row should be clickable to open the Order Detail view.

Pagination or Infinite Scroll: Support performance and large order volumes. – LOW priority
Bulk Actions (Future): Plan for multi-select and bulk IVR generation or approvals in later iterations.

👁️ View Order Details
Admins can click any order in the dashboard to view its full details.

Order Details Page Includes:
Header: Order ID, Status, Provider, Date Submitted
Patient Info: De-identified patient ID, DOB (optional), Insurance (non-PHI)
Order Details: Product requested, Quantity, Diagnosis, Wound Type, Location
Supporting Documents: Uploaded files (e.g., progress notes, images, prior auth forms)

Manufacturer Info: Name, Contact, IVR template? (template controlled by Docuseal)

Action History: Status change log with timestamps, actor, and notes
Actions from Detail View:
Generate IVR
Approve, Send Back, Deny
Submit to Manufacturer
Download documents? (future)
View/edit notes (future)
Acceptance Criteria:
Each order detail view is read-only except for Admin actions
Status history and documents clearly visible

All actions accessible from this view but only available based on pre-conditions (e.g. approve/ send back/ deny available only if IVR Confirmed; Submit to Manufacturer if Approved)

UX/UI Considerations:
Two-Column Layout:
Left side: Order metadata, patient identifier, provider details.
Right side: Order specifics (product, documents, notes, manufacturer info).
Sticky Header: Show Order ID + status + action buttons at the top when scrolling.
Collapsible Sections: Allow sections like “Supporting Documents” or “Notes” to collapse/expand to reduce visual clutter. – LOW Priority
Status History Timeline: Show a vertical stepper or timeline to visualize order progress (e.g., Submitted → IVR Sent → Approved). – LOW priority
Audit Log Tab or Side Panel: Access all logs (who did what + timestamp) without leaving the order.

🧠 Admin Order Actions
1. Generate IVR Document + Notify Manufacturer
Precondition: Order is marked as “Pending IVR”
Admin clicks “Generate IVR”
Present the Admin with a prompt like:  “Does this order require an IVR confirmation from the manufacturer?”
Yes → Proceed with standard IVR generation flow.
No → Allow Admin to skip IVR and jump directly to Approve, Send Back, or Deny.
System creates a pre-filled PDF form (IVR format) based on Manufacturer
System emails form to the manufacturer (using manufacturer contact info)
Status updates to IVR Sent
Acceptance Criteria:
PDF generated using specific Manufacturer template + order data
Email sent with IVR form attached
Status change logged with timestamp and user ID
UX/UI Considerations:
Auto-select manufacturer template.
Pre-filled preview before submission.
Use a modal with radio toggle:
🔘 IVR Required (default)
⚪ IVR Not Required → unlock next action buttons
Add a required “Justification” text box if IVR is bypassed.
Log: Admin [Name] skipped IVR on Order #123 due to [reason] at [timestamp].

2. Order Approval Process
Precondition: Manufacturer responds with IVR Confirmation and Admin manually updates status to IVR Confirmed (future - can be systematically updated)
Admin can:
Approve Order → System submits order to manufacturer, status becomes Submitted to Manufacturer
Send Back → comment required; status becomes Sent Back
Deny → reason required; status becomes Denied
Acceptance Criteria:
Each action requires audit log (who, when, what comment/reason)
Provider notified via email of decision (optional toggle)
UX/UI Considerations:
Approve Order:  Show a quick summary of what will happen next before finalizing (e.g., “This order will now be submitted to [Manufacturer Name]”). Model to confirm?
Send Back / Deny:  Enforce required comment or reason before submission. NICE TO HAVE – Display past notes inline for reference.

3. Submit Approved Orders to Manufacturer
Precondition: Order is Admin-Approved
System submits Order via email or EDI (future)
Status updates to Submitted
Acceptance Criteria:
orders get submission timestamp and manufacturer submission confirmation if available

➕ Admin-Created Orders (On Behalf of Providers)
Admins must be able to:
Click “Create Order”
Select Provider + Patient (from FHIR DB)
Fill required fields identical to provider order flow
Proceed through same IVR → Confirmation → Approval flow
Acceptance Criteria:
Admin-created orders are tagged with creator info
All actions follow same audit/log rules as provider-submitted orders
UI flow is identical to providers except user selection fields

🔐 Access & Compliance
RBAC: Only Admins can access this module
Audit Trail: Every action (generate IVR, approve, deny, etc.) logged
No PHI: Use de-identified or masked patient info outside Azure

📌 Open Questions
Should Admins be able to override denied or sent-back statuses?

Suggested Order Statuses

Phase
Order Status
Who Acts
When / Why
1. Intake
Pending IVR
Provider/OM
Order submitted to MSC Admin and Needs IVR confirmation from manufacturer
2. Pre-Approval
IVR Sent
System/Admin
IVR form emailed to manufacturer


IVR Confirmed
Admin/manual input
Confirmation received from manufacturer
3. Review
Approved
Admin
Approved and ready to submit to manufacturer


Sent Back
Admin
Sent to Provider for corrections (comments required)


Denied
Admin
Rejected (reason required)
4. Submission
Submitted to Manufacturer
System/Admin
Order sent to manufacturer

UX/UI
Visual Indicators:
Pending IVR – Gray
IVR Sent – Blue
IVR Confirmed – Purple
Approved – Green
Denied – Red
Sent Back – Orange 
Submitted to Manufacturer – Dark Green
Why This Model Works Well
Clear accountability: Each status indicates who needs to act.
Visibility: Admins and providers understand where an order stands at a glance.
Simplicity: Only ~8 statuses, easy to support in filters and dashboards.
Extensible: Later, you can add timestamps or subtags (e.g., “Denial reason: missing records”) without changing the core flow.

🎨 UI/UX Considerations
General Design Principles
Actionable-first design: Prioritize tasks that need admin attention (e.g., show "Orders Requiring Action" first).
Consistency: Use consistent layout and language across views (e.g., status badges, action buttons).
Minimal cognitive load: Display only high-level data by default; use progressive disclosure (e.g., collapsible sections or detail modals).
Accessibility: Ensure color contrast, keyboard navigation, and screen reader compatibility (especially for status indicators and action buttons).
Responsiveness: Optimized for desktop and large tablet use cases—minimum 1280px layout width recommended.

Future Enhancements
Search by Order ID / Patient Identifier / Provider / Manufacturer 
Smart Suggestions (e.g., flag orders missing supporting docs)
Bulk Admin Tools
Embedded manufacturer status feed (if API support available)
Order Status Audit Tags: Tooltip or small text like “Last updated by Kamal @ 2:34 PM CST” for transparency.
