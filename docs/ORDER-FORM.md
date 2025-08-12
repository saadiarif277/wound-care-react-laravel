# Order Form After IVR Verification — Exact Implementation (ACZ IVR 1530960 → Order Form 1530964)

Objective

- Create and prefill the DocuSeal Order Form only after the IVR is verified.
- Expose the Order Form for embedded signing in Provider/Office Manager Order Details.
- No edits to DocuSeal templates; only create submissions with default_value fills.

Templates (read-only)

- IVR: 1530960
- Order Form: 1530964

Gate

- If IVR is required, make Order Form available only when `isIvrVerified() === true`.

Implementation checklist

- Manufacturer config contains:
  - `docuseal_template_id = '1530960'`
  - `order_form_template_id = '1530964'`
  - `order_form_field_names` mapping (see docs/ACZ-DocuSeal-Prefill.md)
- UnifiedFieldMappingService can map QuickRequest → DocuSeal for `documentType='OrderForm'`.
- Orchestrator creates Order Form submission when IVR becomes verified.
- Persist `order_form_status`, `order_form_submission_id`, `order_form_link`.
- Provider/Office Manager UIs show the Order Form only when `order_form_status === 'Ready to Sign'`.

Backend: orchestrate creation on IVR verification

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/QuickRequest/QuickRequestOrchestrator.php
// ...existing code...
use App\Models\PatientManufacturerIVREpisode;

class QuickRequestOrchestrator
{
    // ...existing constructor deps: UnifiedFieldMappingService $fieldMappingService, DocusealService $docusealService...

    /**
     * Call after the IVR is verified to generate the Order Form submission for ACZ.
     * Safe: does not modify templates; creates a submission for template 1530964 only.
     */
    public function markIvrVerifiedAndPrepareOrderForm(PatientManufacturerIVREpisode $episode, array $formData): void
    {
        $order = $episode->productRequest ?? null;
        if (!$order) return;

        // Require IVR verified
        if (method_exists($order, 'isIvrRequired') && $order->isIvrRequired()) {
            if (!method_exists($order, 'isIvrVerified') || !$order->isIvrVerified()) {
                return;
            }
        }

        // ACZ only (safe scope)
        $manufacturerName = $order->manufacturer?->name ?? $episode->manufacturer?->name ?? '';
        if (!preg_match('/acz/i', (string)$manufacturerName)) return;

        // Build Order Form payload
        $mapped = $this->fieldMappingService->mapQuickRequestToDocuseal('acz-associates', $formData, 'OrderForm');
        if (($mapped['success'] ?? false) !== true || empty($mapped['template_id'])) return;

        // Create DocuSeal submission (embed signing, no email)
        $submission = $this->docusealService->createSubmissionForOrderForm(
            (string)$mapped['template_id'],   // 1530964
            $mapped['fields'],                // [{ name, default_value, readonly }]
            ['order_id' => $order->id, 'episode_id' => $episode->id]
        );

        // Persist for UI
        $order->order_form_status = 'Ready to Sign';
        $order->order_form_submission_id = $submission['id'] ?? ($submission['submission_id'] ?? null);
        $order->order_form_link = $submission['submissions'][0]['url'] ?? ($submission['embed_url'] ?? null);
        $order->save();
    }
    // ...existing code...
}
```

Backend: DocuSeal submission (read-only; embed signing)

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/DocusealService.php
// ...existing code...
class DocusealService
{
    // ...existing code...

    /**
     * Create an embedded-signing submission for the Order Form.
     * Payload conforms to https://www.docuseal.com/docs/api (POST /submissions).
     */
    public function createSubmissionForOrderForm(string $templateId, array $fields, array $meta = []): array
    {
        $payload = [
            'template_id' => (int)$templateId, // 1530964
            'send_email' => false,
            'embed_signing' => true,
            'fields' => $fields,          // [{ name, default_value, readonly }]
            'external_id' => $meta['order_id'] ?? null,
            'metadata' => [
                'order_id' => $meta['order_id'] ?? null,
                'episode_id' => $meta['episode_id'] ?? null,
                'type' => 'OrderForm',
            ],
        ];
        return $this->apiClient->createSubmission($payload);
    }
}
```

Mapping: QuickRequest → DocuSeal (Order Form)

- Implemented in `UnifiedFieldMappingService::mapQuickRequestToDocuseal('acz-associates', $formData, 'OrderForm')`.
- Uses `config/manufacturers/acz-associates.php` `order_form_field_names` and deterministic normalization.
- Builds line items from `selected_products` (max 5) and totals.

DocuSeal payload examples

```json
{
  "template_id": 1530964,
  "send_email": false,
  "embed_signing": true,
  "fields": [
    { "name": "Physican Name", "default_value": "Dr. Smith", "readonly": false },
    { "name": "Quantity Line 1", "default_value": "1", "readonly": false },
    { "name": "Description Line 1", "default_value": "ACZ Q4316 8cm", "readonly": false },
    { "name": "Unit Price Line 1", "default_value": "250.00", "readonly": false },
    { "name": "Amount Line 1", "default_value": "250.00", "readonly": false },
    { "name": "Sub-Total", "default_value": "250.00", "readonly": false }
  ],
  "external_id": 12345,
  "metadata": { "order_id": 12345, "episode_id": 678, "type": "OrderForm" }
}
```

UI: show only when ready

```tsx
// filepath: /home/rvalen/Projects/msc-woundcare-portal/resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx
// ...existing code...
const isReady = order.order_form_status === 'Ready to Sign';
{isReady && order.order_form_link && (
  <a href={order.order_form_link} className="btn btn-primary" target="_blank" rel="noreferrer">
    Open Order Form
  </a>
)}
// ...existing code...
```

Key safety notes

- No DocuSeal template updates; only POST /submissions to 1530964.
- Live field validation or KB inventory filtering prevents drift-related 422s.
- Radios/checkboxes normalized to exact labels (“Yes”, “In-Network”, “POS 11”).
- Email sending disabled; embedded signing only.

Test steps

1) Create a QuickRequest with an ACZ product.
2) Complete IVR (1530960) and mark verified (via your existing flow/webhook).
3) Confirm Order Form submission created:
   - order_form_status = “Ready to Sign”
   - order_form_submission_id not null
   - order_form_link resolves to DocuSeal embedded signing
4) Open Order Details as Provider/Office Manager; verify button appears and form is prefilled.

Rollback

- If needed, set `order_form_status = 'not_started'`, clear submission_id/link; no DocuSeal changes required.
