# Order Form Availability After IVR Verification

Goal

- Prefill and create the DocuSeal Order Form only after the IVR is verified, then expose the Order Form for embedded signing in Provider/Office Manager Order Details.

States

- IVR: pending → completed → verified
- Order Form: not_started → ready_to_sign → completed

Gate

- $ready = \text{isIvrRequired} \Rightarrow \text{isIvrVerified} \text{ else } true$

Flow

1) IVR completes (DocuSeal webhook or status callback).
2) Orchestrator finalizes episode and marks IVR verified.
   - [`App\Services\QuickRequest\QuickRequestOrchestrator::completeEpisode`](app/Services/QuickRequest/QuickRequestOrchestrator.php)
3) Orchestrator generates Order Form:
   - Gets manufacturer config (must contain `order_form_template_id` and `order_form_field_names`).
   - Builds mapped data via [`App\Services\UnifiedFieldMappingService`](app/Services/UnifiedFieldMappingService.php) using documentType='OrderForm'.
   - Converts mapped fields to DocuSeal API fields: [`UnifiedFieldMappingService::convertToDocusealFields`](app/Services/UnifiedFieldMappingService.php).
   - Calls DocuSeal API to create a submission (embedded signing, no email).
     - [`DocusealService::createSubmissionForOrderForm`](app/Services/DocusealService.php)
     - API: POST /submissions (see <https://www.docuseal.com/docs/api>)
4) Persist submission_id/url and set `order_form_status = "Ready to Sign"`.
5) Provider/OM Order Details shows “Order Form” view button when status is Ready to Sign.

DocuSeal API Payload (Order Form)

```json
{
  "template_id": 1530964,
  "send_email": false,
  "embed_signing": true,
  "fields": [
    { "name": "Exact Field Name", "default_value": "Value", "readonly": false }
  ],
  "external_id": "order-uuid",
  "metadata": { "order_id": "order-uuid", "episode_id": 123, "type": "OrderForm" }
}
```

UI Behavior

- Provider/OM:
  - Show Order Form button only when `order_form_status === "Ready to Sign"`.
  - Button opens embedded DocuSeal signing in the Order Details page.
- Admin:
  - Sees status and can re-generate if mapping changes.

Testing

- Unit: field mapping for Order Form via `tests/Feature/IvrFieldMappingTest.php`.
- Integration: end-to-end flow via `tests/Feature/IvrDocusealE2ETest.php`.
- Manual: follow [`tests/Manual/IVR-Testing-Guide.md`](tests/Manual/IVR-Testing-Guide.md).
