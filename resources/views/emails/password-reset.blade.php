@extends('emails.layout')

@section('content')
<h1 style="margin: 0 0 20px 0; font-size: 24px; color: #111827;">
    Password Reset Request
</h1>

<p style="margin: 0 0 20px 0; color: #6b7280;">
    Hi {{ $user->first_name ?? 'there' }},
</p>

<p style="margin: 0 0 20px 0; color: #6b7280;">
    We received a request to reset your password for your MSC Wound Care Portal account. If you didn't make this request, you can safely ignore this email.
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td align="center" style="padding: 30px 0;">
            <a href="{{ $resetLink }}" class="button" style="background-color: #1925c3; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600; font-size: 16px;">
                Reset Password
            </a>
        </td>
    </tr>
</table>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; color: #374151; font-weight: 600;">
        Security Information:
    </p>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li>This link will expire in 60 minutes</li>
        <li>For security, this link can only be used once</li>
        <li>If you need a new link, please request another password reset</li>
    </ul>
</div>

<p style="margin: 20px 0 0 0; font-size: 14px; color: #9ca3af;">
    If you're having trouble clicking the button, copy and paste this URL into your browser:
</p>
<p style="margin: 5px 0 0 0; font-size: 12px; color: #9ca3af; word-break: break-all;">
    {{ $resetLink }}
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
