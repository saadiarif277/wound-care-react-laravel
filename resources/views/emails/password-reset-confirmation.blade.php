@extends('emails.layout')

@section('content')
<h1 style="margin: 0 0 20px 0; font-size: 24px; color: #111827;">
    Password Successfully Reset
</h1>

<p style="margin: 0 0 20px 0; color: #6b7280;">
    Hi {{ $user->first_name ?? 'there' }},
</p>

<p style="margin: 0 0 20px 0; color: #6b7280;">
    Your MSC Wound Care Portal password has been successfully reset. You can now log in with your new password.
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td align="center" style="padding: 30px 0;">
            <a href="{{ url('/login') }}" class="button" style="background-color: #1925c3; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600; font-size: 16px;">
                Login to Portal
            </a>
        </td>
    </tr>
</table>

<div style="background-color: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #1925c3;">
    <p style="margin: 0 0 10px 0; color: #374151; font-weight: 600;">
        Security Tips:
    </p>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li>Keep your password secure and don't share it with anyone</li>
        <li>Use a strong, unique password for your account</li>
        <li>Log out when using shared or public computers</li>
        <li>Contact support if you notice any suspicious activity</li>
    </ul>
</div>

<p style="margin: 20px 0 0 0; color: #6b7280;">
    If you didn't request this password reset, please contact our support team immediately.
</p>

<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

<p style="margin: 20px 0 0 0; font-size: 14px; color: #9ca3af;">
    Need help? Reach us at <a href="mailto:support@msc-platform.com" style="color: #1925c3;">support@msc-platform.com</a>.
</p>

<p style="margin: 10px 0 0 0; font-size: 14px; color: #6b7280;">
    Thank you,<br>
    MSC Wound Care Team
</p>

{!! $trackingPixel !!}
@endsection
