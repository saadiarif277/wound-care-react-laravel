# Notification System Implementation Guide

This document outlines how notifications are implemented across the portal and the concrete improvements to apply. It’s environment-agnostic and focuses on maintainable patterns, safety, and reliability.

## Scope

- Channel: Email (user invites, order lifecycle, IVR, help requests)
- Audience: Providers, Office Managers, MSC Admins
- Source components: Controllers, Jobs, Services, Events/Listeners, Blade templates

## Event Catalog (what we send)

- Invites — Admin → End Users — Subject: 🚀 You're Invited to Join the MSC Wound Care Platform 🚀
- Order Request Submitted — Provider/OM → Admin — Subject: 📝 MSC: New Order Request Submitted by {{provider_name}}
- IVR Verified — Admin → Provider — Subject: ✅ MSC: IVR Verification Complete for Order #{{order_id}}
- IVR Sent Back — Admin → Provider — Subject: ❌ MSC: IVR Sent Back - #{{order_id}}
- Order Form Submitted to Admin — Provider/OM → Admin — Subject: 📝 MSC: New Order Form Submitted by {{provider_name}}
- Order Submitted to Manufacturer — MSC Admin → Provider — Subject: ✅ MSC: Order Submitted to Manufacturer - Order #{{order_id}}
- Order Confirmed by Manufacturer — MSC Admin → Provider — Subject: 📦 MSC: Order Confirmed by Manufacturer - Order #{{order_id}}
- Order Rejected by Admin — MSC Admin → Provider — Subject: ❌ MSC Order Rejected - #{{order_id}}
- Order Sent Back to Provider — MSC Admin → Provider — Subject: 🔄 MSC Order #{{order_id}} Sent Back for Edits
- Help & Support Requested — Provider/OM → Admin — Subject: MSC Support Requested by {{provider_name}}

See `summary.md` for a non-technical overview and intended purpose of each event.

## Architecture

- Central service: EmailNotificationService (e.g., `app/Services/EmailNotificationService.php`)
- Queuing: Prefer queued mail; fall back to direct send if queues are not configured
- Templates: Blade views per category
  - Provider status updates: `emails/order/status-update-provider`
  - Admin submissions: `emails/order/new-order-admin`
  - User management: `emails/user/invitation` (example)
- Deep links: Single helper that builds secure order links (JWT where applicable) with a safe fallback
- Observability: Structured logs for success/failure; optional email log storage

## Immediate Improvements (apply now)

1. Attachments for provider notifications

  Accept `UploadedFile|string` inputs; normalize to `attach`/`attachData`. Include filename and MIME type where available.

1. Queue by default

  Use Mail queue or Mailable + ShouldQueue for all outbound sends; fallback to direct send when queue isn’t available.

1. Recipient guardrails

  Null-safe lookups; skip send when recipient is missing; log once at WARN level.

1. Idempotency cooldown

  Basic cooldown (e.g., 2 minutes) per (order_id + status + recipient); implement via notes/cache/simple table.

1. Centralized deep links

  One method to generate JWT deep links with a safe URL fallback if token generation fails.

1. Template contracts

  Validate required variables; consistent subject builder per status/event.

1. Structured logging

  Context keys: order_id, order_number, status, recipient, attachments_count.

1. Single gateway

  Route all sends through EmailNotificationService; isolate/remove legacy direct Mail usage for consistency.

## Minimal Method Contract

```php
public function sendStatusChangeNotification(
    ProductRequest $order,
    string $newStatus,
    ?string $comments = null,
    ?array $attachments = null, // UploadedFile|string paths
    bool $queue = true
): bool;
```

Behavior:

- Queues by default; safe-fallback to direct send
- Normalizes attachments
- Validates recipient; returns false when none
- Logs success/failure with structured context
- Applies cooldown to prevent duplicates

## Wiring and Usage

- Status change flow (example):
  - Controller collects: `send_notification` flag, `notification_documents[]` files
  - StatusChangeService persists the new status, logs history, and when `send_notification` is true, calls `sendStatusChangeNotification($order, $newStatus, $notes, $notificationDocuments, true)`
- Jobs/Listeners: For high-volume/status-driven notifications, dispatch a queued job that invokes the service

## Template Mapping and Variables

- Provider status updates: `emails/order/status-update-provider`
  - Variables: order, order_id, provider_name, manufacturer_name, admin_name, comments, denial_reason, order_link, status_type, status_emoji
- Admin submissions: `emails/order/new-order-admin`
  - Variables: order, order_id, provider_name, date, comment, order_link, submission_type
- User invites: `emails/user/invitation` (or equivalent)
  - Variables: user, first_name, role, login_url

Ensure template variable names match the service payload; validate before sending.

## Configuration

- Environment variables
  - APP_ENV, APP_DEBUG=false in production, APP_KEY
  - Mail transport (SMTP/API), credentials, from/reply-to, QUEUE_CONNECTION
  - SUPPORT_EMAIL or related template variables
- Monitoring
  - Enable application logs; optional Sentry/App Insights for error tracking
- Cooldown
  - Configurable window (e.g., NOTIFY_COOLDOWN_MIN=2)

## Testing & Verification

- Unit tests
  - Success path: queued send with valid recipient
  - Missing recipient: returns false without exception
  - Attachments: assert `attach`/`attachData` invoked with expected filenames
  - Cooldown: duplicate call within window does not send twice
- Manual checks
  - Trigger each event via portal; verify subjects, variables, links, and HTML rendering
  - Confirm attachments render correctly in recipients’ email clients

## Operations

- Common failure modes
  - Invalid SMTP/API credentials → verify env vars and transport
  - Missing template variables → service validation error; check logs
  - Queue not running → fallback to direct send or start workers
- Troubleshooting
  - Inspect structured logs by `order_id` and `status`
  - Use mail provider dashboard for deliverability details

## Rollout Checklist

- Deploy changes via CI/CD
- Verify environment variables for mail + queues
- Exercise each event path in a non-production environment
- Monitor logs and delivery metrics; adjust cooldown/recipients if needed

## Appendix: Subject Builder

- ivr_verified → "IVR Verification Complete - MSC Wound Care Portal"
- ivr_sent_back → "IVR Requires Revision - MSC Wound Care Portal"
- submitted_to_manufacturer → "Order Submitted to Manufacturer - MSC Wound Care Portal"
- manufacturer_confirmed → "Manufacturer Confirmed Order - MSC Wound Care Portal"
- denied → "Order Denied - MSC Wound Care Portal"
- default → "Order Status Update - MSC Wound Care Portal"
