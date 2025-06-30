@component('mail::message')
# Welcome to Our Healthcare Platform!

Hello,

You've been invited to set up **{{ $organizationName }}** on our healthcare platform. This invitation will allow you to:

- Create your organization account
- Set up your primary facility
- Configure your provider profile
- Sign the required Business Associate Agreement (BAA)
- Begin processing orders immediately

@component('mail::button', ['url' => $invitationUrl])
Complete Your Setup
@endcomponent

## What to Expect

The setup process takes approximately 10-15 minutes and includes:

1. **Organization Details** - Basic information about your practice
2. **Facility Information** - Your primary location details
3. **Provider Profile** - Your professional credentials
4. **BAA Signing** - Electronic signature for HIPAA compliance
5. **Review & Confirm** - Verify all information before submission

## Important Information

- This invitation expires on **{{ $expiresAt->format('F j, Y at g:i A') }}**
- All information is securely stored and HIPAA compliant
- You'll have immediate access once setup is complete
- Additional facilities and providers can be added after initial setup

If you have any questions or need assistance, please don't hesitate to contact our support team.

Best regards,<br>
{{ config('app.name') }} Team

@component('mail::subcopy')
If you're having trouble clicking the "Complete Your Setup" button, copy and paste the URL below into your web browser: {{ $invitationUrl }}
@endcomponent
@endcomponent 