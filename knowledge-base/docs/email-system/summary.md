# Notification System Summary

This is a concise, non-technical overview of the MSC Wound Care Portal notification system, the events we notify on, and the key improvements applied.

## What gets sent and to whom

- Invites — Admin → End Users — Email
  - Subject: 🚀 You're Invited to Join the MSC Wound Care Platform 🚀
  - Purpose: Onboard new users with activation link and role context

- Order Request Submitted — Provider/OM → Admin — Email
  - Subject: 📝 MSC: New Order Request Submitted by {{provider_name}}
  - Purpose: Alert admins that a new order request arrived; includes link and comments

- IVR Verified — Admin → Provider — Email
  - Subject: ✅ MSC: IVR Verification Complete for Order #{{order_id}}
  - Purpose: Notify provider that the IVR was verified by manufacturer; includes link and admin comments

- IVR Sent Back — Admin → Provider — Email
  - Subject: ❌ MSC: IVR Sent Back - #{{order_id}}
  - Purpose: Ask provider to fix IVR; shows denial reason/comments and link

- Order Form Submitted to Admin — Provider/OM → Admin — Email
  - Subject: 📝 MSC: New Order Form Submitted by {{provider_name}}
  - Purpose: Alert admins that a form was submitted; includes link and comments

- Order Submitted to Manufacturer — MSC Admin → Provider — Email
  - Subject: ✅ MSC: Order Submitted to Manufacturer - Order #{{order_id}}
  - Purpose: Confirm that the order was sent to the manufacturer; include comments and link

- Order Confirmed by Manufacturer — MSC Admin → Provider — Email
  - Subject: 📦 MSC: Order Confirmed by Manufacturer - Order #{{order_id}}
  - Purpose: Inform provider that manufacturer confirmed; includes link and details

- Order Rejected by Admin — MSC Admin → Provider — Email
  - Subject: ❌ MSC Order Rejected - #{{order_id}}
  - Purpose: Share rejection reason, provide link to review and resubmit

- Order Sent Back to Provider — MSC Admin → Provider — Email
  - Subject: 🔄 MSC Order #{{order_id}} Sent Back for Edits
  - Purpose: Request edits; includes summary, admin comment, submission date, and link

- Help & Support Requested — Provider/OM → Admin — Email
  - Subject: MSC Support Requested by {{provider_name}}
  - Purpose: Notify admins about support request with requester email and comment

All messages include a secure portal link (deep link) and optional comments.

## Key improvements

1) Attachments supported for provider notifications (e.g., IVR-related docs)
2) Queue by default for outbound mail; graceful fallback to direct send
3) Guardrails: null-safe recipients, skip when none, consistent logging
4) Idempotency: short cooldown to avoid duplicate blasts on rapid status flips
5) Deep links centralized; safe fallback URL if token generation fails
6) Template contracts standardized; consistent subject builder
7) Structured logs for success/failure (order_id, status, recipient, attachments_count)
8) One gateway service for all outbound emails

## What admins need to configure

- Environment variables per environment (production, staging, dev)
  - APP_ENV=production, APP_DEBUG=false, APP_KEY (generated)
  - Mail transport credentials (SMTP/API) and QUEUE_CONNECTION
  - Any domain-specific variables referenced in templates (e.g., SUPPORT_EMAIL)
- Monitoring & logging
  - Enable application logs and error tracking (e.g., Sentry, Application Insights)
  - Ensure mail transport has deliverability/metrics visibility

## Rollout checklist (all environments)

- Deploy changes via your CI/CD pipeline
- Verify environment variables for mail transport and queueing
- Trigger each notification path and confirm delivery + rendering
- Review logs/monitoring for errors and delivery issues
- Tune cooldown window or recipients as needed
