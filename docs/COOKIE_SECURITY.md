# Cookie Security Configuration

This document explains how cookie security is configured in the MSC Wound Portal application.

## Overview

The application implements secure cookie handling to ensure HIPAA compliance and protect user sessions. Cookies are automatically configured with appropriate security attributes based on the environment and request protocol.

## Security Attributes

All session cookies include the following security attributes:

1. **Secure Flag**: Cookies are only transmitted over HTTPS in production
2. **HttpOnly Flag**: Prevents JavaScript access to cookies
3. **SameSite Attribute**: Protects against CSRF attacks (default: 'lax')

## Configuration

### Environment Variables

```bash
# In production (.env)
APP_ENV=production
SESSION_SECURE_COOKIE=true  # Optional - defaults to true in production

# In local development (.env)
APP_ENV=local
SESSION_SECURE_COOKIE=false  # Required for HTTP development
```

### How It Works

1. **Automatic Detection**: The `SecureCookies` middleware automatically detects whether the request is using HTTPS and adjusts cookie attributes accordingly.

2. **Development Environment**: When running locally on HTTP (e.g., http://localhost:8000), the secure flag is automatically removed to allow cookies to work properly.

3. **Production Environment**: In production with HTTPS, all cookies are automatically secured with the appropriate flags.

## Implementation Details

### Session Configuration (config/session.php)

```php
'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
```

This configuration:
- Defaults to `true` in production
- Can be overridden with `SESSION_SECURE_COOKIE` environment variable
- Automatically sets to `false` in non-production environments

### SecureCookies Middleware

The `App\Http\Middleware\SecureCookies` middleware:
- Inspects all outgoing cookies
- Adds security attributes based on the request protocol
- Ensures consistent cookie handling across environments

## Troubleshooting

### Cookies Not Working in Development

If cookies aren't working in your local development environment:

1. Ensure `SESSION_SECURE_COOKIE=false` is set in your `.env` file
2. Clear your browser cookies and cache
3. Restart your development server

### Security Warnings in Production

If you see cookie security warnings in production:

1. Ensure your application is served over HTTPS
2. Check that `APP_ENV=production` in your `.env` file
3. Verify SSL/TLS certificate is properly configured

## Browser Developer Tools

When inspecting cookies in browser developer tools:

- **Development**: Cookies will NOT have the `Secure` flag (expected behavior)
- **Production**: All cookies MUST have the `Secure` flag

## Compliance

This configuration ensures:
- HIPAA compliance for protected health information
- OWASP security best practices for session management
- Protection against common web vulnerabilities

## Testing

To test cookie security:

```bash
# Check cookie headers in development
curl -I http://localhost:8000/login

# Check cookie headers in production (example)
curl -I https://your-production-domain.com/login
```

Look for the `Set-Cookie` header and verify appropriate attributes are present based on the environment. 