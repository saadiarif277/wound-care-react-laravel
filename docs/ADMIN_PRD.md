MSC Platform – Admin Order Processing Flow (PRD)

1. OVERVIEW
This document defines the product requirements for the Admin workflow after a Provider or Office Manager submits an order to the MSC platform. The Admin is responsible for reviewing and sending IVR and Order Forms to the manufacturer, updating statuses, uploading supporting documents, and notifying users as needed.
The platform should re-use shared UI templates for both the Order Dashboard and Order Details views across all user roles. Based on user permissions, each view should dynamically render applicable data fields and action buttons (e.g., status edits, uploads, DocuSeal send options).

2. ROLES & PERMISSIONS MATRIX
Role
View IVR Form
Upload / Replace IVR (remove + add)
View IVR Status
Edit IVR Status
View Order Form
Upload / Replace Order Form
View Order Status
Edit Order Status
View ASP
View Amount to be Billed
Send via DocuSeal
Admin
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
✅ Yes
Provider
✅ Yes
✅ Yes (if requestor)
✅ Yes
❌ No
✅ Yes
✅ Yes (if requestor)
✅ Yes
❌ No
✅ Yes
✅ Yes
❌ View only
Office Mgr
✅ Yes
✅ Yes (if requestor)
✅ Yes
❌ No
✅ Yes
✅ Yes (if requestor)
✅ Yes
❌ No
✅ Yes
❌ No
❌ View only


3. ORDER DASHBOARD
Fields Displayed:
Patient Name (Note: de-identified for Admins only; full name visible for Providers and Office Managers)
Order ID
Product Name
Provider Name
IVR Status
Order Status
Request Date (from Provider/OM)
Manufacturer Name
Behavior:
Clicking on an Order ID or row navigates to the Order Details view
Filters: Patient, Order Status

4. IVR PROCESS FLOW (ADMIN)

Admin is notified via email (with deep link to order)
Admin views Order Details page
Click to view/download IVR (opens in new tab)
Send IVR via DocuSeal (multi-recipient supported)
Receive verification from manufacturer
(Optional) Upload IVR result PDF
Manually update IVR status to Verified
(Optional) Notify requester with comments (logged in activity log)
Re-upload permissions:


IVR Status Options:
N/A
Pending
Sent
Verified
Rejected (IVR was reviewed and did not meet eligibility criteria)
IVR Status Change Requirements:
N/A: No IVR required. Requires reason. Re-upload allowed.
Pending: Awaiting Admin review. Re-upload allowed.
Sent: IVR sent to manufacturer via DocuSeal. No further edits allowed.
Verified: Manufacturer verified IVR. No further edits allowed.
Rejected: IVR did not meet eligibility. Admin must add rejection reason. Re-upload allowed by requestor or Admin.


5. ORDER FORM PROCESS FLOW (ADMIN)
View/download Order Form
Send via DocuSeal (multi-recipient supported)
Update status to Submitted to Manufacturer
When manufacturer confirms, update status to Confirmed by Manufacturer
(Optional) Upload packing slip PDF
(Optional) Enter carrier + tracking number  
(Optional) Notify requester with comments
Re-upload Permissions:
Users (requestor or Admin) can re-upload or replace Order Forms when status is:
Pending
Canceled
Rejected
Order Status Options:
Pending
Submitted to Manufacturer
Confirmed by Manufacturer
Rejected (Order was denied by the manufacturer or failed review)
Canceled

Order Status Change Requirements:
Pending: Default status after submission. Re-upload allowed.
Submitted to Manufacturer: Admin confirms form sent. No re-upload unless canceled or rejected.
Confirmed by Manufacturer: Manufacturer fulfillment confirmed. Optional: upload packing slip + tracking number.
Rejected: Order denied by manufacturer. Admin must add rejection reason and optionally notify requestor. Re-upload allowed by requestor or Admin.
Canceled: Admin cancels the order. Must provide cancellation reason. Re-upload allowed.


6. COMMENTING & NOTIFICATIONS

📨 1. Order Request Notification (Provider/OM → Admin)
Trigger: Provider or Office Manager submits an order
Recipient: Admin
Subject: New Order Submitted by [Provider/OM Name] – [Order ID]
Body:
A new order has been submitted by [Provider/OM Name].
Product: [Product Name]
Manufacturer: [Manufacturer Name]
Request Date: [MM/DD/YYYY]
Comments: [Optional Provider or OM Comments]

[View Order button] (deep link to Order Details page)

Thank you,
MSC Platform Team
For support, contact us at support@mscplatform.com 

📤 2. IVR Submission & Verification Notification (Admin → Provider/OM)
Trigger: Admin updates IVR status to Sent, Verified, or Rejected
Recipient: Provider/OM (requestor)
Subject: Order Update – [Order ID] IVR Status: [New Status]
Body:
The IVR for your order [Order ID] has been updated.
New IVR Status: [Sent / Verified / Rejected]
Comments: [Optional Admin Comments]

[View Order button] (deep link to Order Details page)

Thank you,
MSC Platform Team
Need help? support@mscplatform.com 

✅ 3. Order Submission & Confirmation Notification (Admin → Provider/OM)
Trigger: Admin updates Order status to Submitted to Manufacturer, Confirmed by Manufacturer, Rejected, or Canceled
Recipient: Provider/OM (requestor)
Subject: Order Update – [Order ID] Status: [New Status]
Body:
Your order [Order ID] has been updated.
New Order Status: [Submitted to Manufacturer / Confirmed / Rejected / Canceled]
Comments: [Optional Admin Comments]

[View Order button] (deep link to Order Details page)

Thank you,
MSC Platform Team
Questions? support@mscplatform.com 

Commenting Behavior:
All status updates allow optional comments
Notification checkbox defaults to “Send to Provider/OM”
All comments are appended to the Order Activity Log (not editable)


7. DOCUSEAL INTEGRATION
Role
View PDF
Send via DocuSeal
Multi-Recipient Support
Admin
✅ Yes
✅ Yes
✅ Yes
Provider/OM
✅ Yes
❌ No
❌ No


8. COMPLIANCE & DELETE POLICY
Deleted orders should be soft deleted (is_deleted = true)
Only Admins can view soft-deleted records
PHI retained unless purged by policy
⚠️ Flag for Compliance Review:
Define retention duration
Legal/compliance risk of retaining canceled PHI

9. DISCUSSION: PLATFORM EMAIL VS DOCUSEAL
Current:
All documents sent via DocuSeal for e-signature and tracking
Proposal:
Option to send IVR and Order Form via MSC platform email
Manufacturer replies with verification and attachments
Pros:
Faster response time
Easier for manufacturers to reply
Easier ingestion of returned documents
Cons:
No audit trail
Potential PHI in replies
Requires secure email handling
Next Step:
Technical + compliance review to determine feasibility
DocuSeal remains fallback/default
