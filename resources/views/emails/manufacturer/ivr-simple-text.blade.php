IVR DOCUMENT FOR REVIEW - Order #{{ $orderNumber }}

Dear {{ $manufacturerName }} Team,

Please review the attached IVR (Insurance Verification Request) document for the following order. Your approval is required to proceed with processing.

ORDER DETAILS:
==============
Order Number: {{ $orderNumber }}
Patient ID: {{ $patientInitials }}
Product: {{ $productName }}
Quantity: {{ $quantity }}
Submission Date: {{ $submissionDate }}
@if(isset($providerName))
Provider: {{ $providerName }}
@endif
@if(isset($facilityName))
Facility: {{ $facilityName }}
@endif

ATTACHED DOCUMENT:
==================
Please review the attached IVR PDF document before making your decision.

QUICK RESPONSE:
===============
To respond quickly, click one of these links:

APPROVE: {{ $approveUrl }}
DENY: {{ $denyUrl }}
VIEW IN BROWSER: {{ $viewUrl }}

IMPORTANT: 
==========
This request expires in 72 hours ({{ $expiresAt }}). Please respond promptly to avoid delays in order processing.

If you need additional information or have questions, please reply to this email.

---
MSC Wound Care Platform
This is an automated message from the MSC platform. 