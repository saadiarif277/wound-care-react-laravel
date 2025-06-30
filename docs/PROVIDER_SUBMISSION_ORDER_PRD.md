# Product Requirements Document (PRD) - Feature Name: Order Review & Summary Page + Submission

Purpose:  Provide a final review interface for Providers and Order Managers (OM) to verify all entered information before submitting an order for Admin review and submission to the manufacturer. This same page template will also serve as the ‘Order Details’ view (Providers, OMs and Admins) for any submitted orders.

Wireframe: order-review-complete-flow
Functional Scope:

1. Information Sections Displayed:
A. IVR Form
Status (Complete / Pending)
Submission Date (current date when submitting)
Actions:
view/download
B. Order Status
Status (Complete / Pending)
Submission Date (current date when submitting)
Actions:
view/download
C. Patient & Insurance Information
Full Name  
Date of Birth
Contact Info (phone, email)
Address
     Primary Insurance (payer name, plan)
Insurance Card (view/download, if provided)
Policy Number
Secondary Insurance (if any)
D. Provider Information
Provider Name
Facility Name and Address
Organization
E. Clinical Information
Wound Type
Wound Size
Diagnosis Codes including name
ICD-10 Codes including name
Procedure Information
Number of Prior applications
NUmber of anticipated application
Facility Information
F. Product Information
Product Name
Size(s)
Quantity
ASP total price
Amount to be billed (ASP less discounts → only viewable for Providers and Admins and NOT OMs)
coverage or eligibility warnings (if any) (FUTURE when coverage validations are added)
G. Submission Details / Audit Trail
Order Number
Order Created Date
Created By (User)
Current Order Status  

1. Available User Actions:
Action
Notes
Edit any section including patient info, clinical notes, product, IVR or order form
User has to go to prior steps by going back
Submit order
See requirements below
View Summary and Audit Log (Admin only)
Post-submission only

Submit Order Flow Requirements:
Preconditions: All required sections must be completed. System validates completeness before enabling the 'Submit Order' button.
Submit Order Button: Persistent on screen but disabled until all sections are complete.
User Clicks Submit Order:
Display a confirmation modal popup “Confirm Order Submission” with:
Statement: "By submitting this order, I consent to having the IVR form and Order form submitted to the manufacturer for review and approval. I understand that the order will not be placed with the manufacturer until IVR verification is completed and the order is fully approved."
Checkbox: "I confirm the information is accurate and complete." (not selected by default – user has to select)
Confirm button: "Place Order"
Cancel button: "Go Back" – take user back to summary screen
If user confirms:
System records submission date/timestamp.
System updates order status to "Order Pending" and IVR status to “IVR Pending” (if provided).
Audit log entry created: "Order submitted by [user name] on [timestamp]".
Post-submission Confirmation:
Brief popup modal:
Success:  
Title: Order Submitted Successfully
Body:  Your order has been submitted to Admin for review and processing. You will be notified once the order is fully approved and sent to the manufacturer.
Optional link: "Would you like to add a note for Admin?" → opens modal for free-form note capture.
Captured note is stored on Order record, visible to Admin, logged in audit trail.
Notifications to Admin team are triggered only after this optional note step is completed (or skipped).
Action Button: Okay or close out (x) top right
Auto-redirect to Order Dashboard after short delay (3-5 seconds).
Error Scenario:
If submission fails (backend error, file upload issue, network failure):
Display error popup: "Error submitting order. Please try again or contact support if the problem persists."
User remains on Order Review page with all data preserved.
System logs error event for troubleshooting.

3. Available User Actions (Order Details - Post-Submission):

Action
Notes
View Order Details
Read-only view of all sections (re-use order review details template)
View Submitted Documents
PDF preview/download of IVR and Order Forms
View/Download IVR

Admin Only – Change Status of IVR with option to comment
Not required
pending
Sent
Verified

Edit/ update IVR or Order form or patient details
Order Form, Patient, Insurance, Clinical
Only editable until order is approved or submitted to manufacturer (it is locked after submission unless it’s sent back by Admin)
IVR Form
only editable until IVR is submitted to manufacturer and when verified
View Audit Log (Admin only)
Full audit trail of all order activities
Comments/ Notes
Allow users to add internal notes/comments

4. UI/UX Considerations:

Use accordion panels or collapsible sections for each information block.
Persistent 'Submit Order' button visible when all mandatory fields are complete.
Timestamps visible for IVR Form and Order Form submissions (created or updated).
PDF document icons for quick access to forms?

5. Reuse for 'Order Details' Page:

Disable edit and submission actions post-submission.
Display full audit trail and status updates.
Allow Admin users with proper permissions to perform limited edits.
Allow Admin and Provider to add/view comments or notes on order.

Compliance Considerations:
All PHI data rendered from secure FHIR backend.
Full audit trail logging for every action.
RBAC enforced: Providers/ OMs can only access/edit their own orders.
