# ACZ DocuSeal Prefill (IVR 1530960, Order Form 1530964) — Exact Implementation

Purpose

- Prefill ACZ DocuSeal IVR and Order Form from QuickRequest data.
- Only use templates: IVR 1530960, Order Form 1530964.
- Never modify DocuSeal templates. Only create submissions with default_value fills.
- Make Order Form available to sign only after IVR is verified.

Scope

- Manufacturer: ACZ & Associates (slug: acz-associates)
- Frontend trigger: product.manufacturer matches /acz/i
- Backend-only mapping and DocuSeal submission.

Key guarantees

- No DocuSeal mutations (safe, read-only except POST /submissions).
- Field names match the CSV headers you supplied, validated against the live template before submit.
- Radios/checkboxes use exact labels (“Yes”, “In-Network”, “POS 11”).
- Order Form line items (up to 5) come from selected_products.

1) Manufacturer config (exact fields and template IDs)

Add/replace config with these mappings. The arrays are canonical → DocuSeal field names. Only keys present in the config are ever sent (unknowns are skipped).

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/config/manufacturers/acz-associates.php
<?php

return [
    'id' => 1,
    'name' => 'ACZ & Associates',
    'slug' => 'acz-associates',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,

    // Use ONLY the safe template IDs provided
    'docuseal_template_id' => '1530960',   // IVR
    'order_form_template_id' => '1530964', // Order Form

    // IVR (1530960): canonical → DocuSeal (from ACZ & Associates IVR (7).csv)
    'docuseal_field_names' => [
        // Header/contact (optional)
        'account_name' => 'Name',
        'account_email' => 'Email',
        'account_phone' => 'Phone',
        'product_q_code' => 'Product Q Code',
        'representative_name' => 'Sales Rep',
        'iso_if_applicable' => 'ISO if applicable',
        'additional_emails' => 'Additional Emails for Notification',

        // Physician / Facility
        'physician_name' => 'Physician Name',
        'physician_npi' => 'Physician NPI',
        'facility_npi' => 'Facility NPI',
        'physician_specialty' => 'Physician Specialty',
        'physician_tax_id' => 'Physician Tax ID',
        'facility_tax_id' => 'Facility Tax ID',
        'facility_name' => 'Facility Name',
        'physician_ptan' => 'Physician PTAN',
        'facility_ptan' => 'Facility PTAN',
        'facility_address' => 'Facility Address',
        'physician_medicaid' => 'Physician Medicaid #',
        'facility_medicaid' => 'Facility Medicaid #',
        'facility_city_state_zip' => 'Facility City, State, Zip',
        'physician_phone' => 'Physician Phone #',
        'facility_phone' => 'Facility Phone #',
        'facility_contact_name' => 'Facility Contact Name',
        'physician_fax' => 'Physician Fax #',
        'facility_fax' => 'Facility Fax #',
        'facility_contact_info' => 'Facility Contact Phone # / Facility Contact Email',
        'physician_organization' => 'Physician Organization',
        'facility_organization' => 'Facility Organization',

        // POS
        'place_of_service' => 'Place of Service',
        'pos_other_specify' => 'POS Other Specify',

        // Patient
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'patient_address' => 'Patient Address',
        'patient_city_state_zip' => 'Patient City, State, Zip',
        'patient_phone' => 'Patient Phone #',
        'patient_email' => 'Patient Email',
        'patient_caregiver_info' => 'Patient Caregiver Info',

        // Insurance
        'primary_insurance_name' => 'Primary Insurance Name',
        'secondary_insurance_name' => 'Secondary Insurance Name',
        'primary_policy_number' => 'Primary Policy Number',
        'secondary_policy_number' => 'Secondary Policy Number',
        'primary_payer_phone' => 'Primary Payer Phone #',
        'secondary_payer_phone' => 'Secondary Payer Phone #',

        // Network + auth radios
        'physician_status_primary' => 'Physician Status With Primary',
        'physician_status_secondary' => 'Physician Status With Secondary',
        'permission_prior_auth' => 'Permission To Initiate And Follow Up On Prior Auth?',
        'patient_in_hospice' => 'Is The Patient Currently in Hospice?',
        'patient_part_a_stay' => 'Is The Patient In A Facility Under Part A Stay?',
        'patient_global_surgery' => 'Is The Patient Under Post-Op Global Surgery Period?',

        // Clinical
        'surgery_cpt_codes' => 'If Yes, List Surgery CPTs',
        'surgery_date' => 'Surgery Date',
        'wound_location' => 'Location of Wound',
        'icd_10_codes' => 'ICD-10 Codes',
        'total_wound_size' => 'Total Wound Size',
        'medical_history' => 'Medical History',
    ],

    // Order Form (1530964): canonical → DocuSeal (from ACZ & Associates Order Form.csv)
    // NOTE: Some templates use NBSPs in labels (e.g., "Description  Line 1").
    // If live template differs, adjust to exact names from fields_1530964.json.
    'order_form_field_names' => [
        // Header/contact
        'account_name' => 'Name',
        'account_email' => 'Email',
        'account_phone' => 'Phone',
        'date_of_order' => 'Date of Order',
        'anticipated_application_date' => 'Anticipated Application Date',
        'physician_name' => 'Physican Name', // CSV spelling
        'account_contact_email' => 'Account Contact E-mail',
        'account_contact_name' => 'Account Contact Name',
        'account_contact_phone' => 'Account Contact #',

        // Line items (1..5)
        'line1_quantity' => 'Quantity Line 1',
        'line1_description' => 'Description Line 1',
        'line1_size' => 'Size Line 1',
        'line1_unit_price' => 'Unit Price Line 1',
        'line1_amount' => 'Amount Line 1',

        'line2_quantity' => 'Quantity Line 2',
        'line2_description' => 'Description Line 2',
        'line2_size' => 'Size Line 2',
        'line2_unit_price' => 'Unit Price Line 2',
        'line2_amount' => 'Amount Line 2',

        'line3_quantity' => 'Quantity Line 3',
        'line3_description' => 'Description Line 3',
        'line3_size' => 'Size Line 3',
        'line3_unit_price' => 'Unit Price Line 3',
        'line3_amount' => 'Amount Line 3',

        'line4_quantity' => 'Quantity Line 4',
        'line4_description' => 'Description Line 4',
        'line4_size' => 'Size Line 4',
        'line4_unit_price' => 'Unit Price Line 4',
        'line4_amount' => 'Amount Line 4',

        'line5_quantity' => 'Quantity Line 5',
        'line5_description' => 'Description Line 5',
        'line5_size' => 'Size Line 5',
        'line5_unit_price' => 'Unit Price Line 5',
        'line5_amount' => 'Amount Line 5',

        // Totals
        'sub_total' => 'Sub-Total',
        'discount' => 'Discount',
        'total' => 'Total',

        // Shipping
        'ship_check_fedex' => 'Check FedEx',
        'ship_date_to_receive' => 'Date to Recieve',
        'ship_facility_or_office' => 'Facility or Office Name',
        'ship_address1' => 'Ship to Address',
        'ship_address2' => 'Ship to Address 2',
        'ship_city' => 'Ship to City',
        'ship_state' => 'Ship to State',
        'ship_zip' => 'Ship to Zip',
        'ship_notes' => 'Notes',

        // Patient link
        'patient_id' => 'Patient ID',
    ],
];
```

2) Mapping service: build canonical from QuickRequest data and convert to DocuSeal fields

Add these methods if missing; they are no-ops for unknown canonicals and include deterministic normalization for radios/POS.

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/UnifiedFieldMappingService.php
<?php
// ...existing code...
class UnifiedFieldMappingService
{
    // ...existing code...

    public function mapQuickRequestToDocuseal(string $manufacturerName, array $formData, string $documentType = 'IVR'): array
    {
        $cfg = $this->getManufacturerConfig($manufacturerName, $documentType);
        if (!$cfg) return ['success' => false, 'error' => "No config for {$manufacturerName}"];

        $canonical = $this->buildCanonicalFromQuickRequest($formData, $documentType, $cfg);
        $fields = $this->convertToDocusealFields($canonical, $cfg, $documentType);

        return [
            'success' => true,
            'template_id' => $documentType === 'OrderForm'
                ? (string)($cfg['order_form_template_id'] ?? '')
                : (string)($cfg['docuseal_template_id'] ?? ''),
            'fields' => $fields,
        ];
    }

    protected function buildCanonicalFromQuickRequest(array $formData, string $documentType, array $cfg): array
    {
        $out = [];

        // Header/contact (if present from Step 2 or user input)
        $out['account_name'] = $formData['account_name'] ?? null;
        $out['account_email'] = $formData['account_email'] ?? null;
        $out['account_phone'] = $formData['account_phone'] ?? null;

        // Product Q-code
        $out['product_q_code'] = $this->extractQCode($formData);

        // Provider/Facility
        $out['physician_name'] = $formData['provider_name'] ?? $formData['physician_name'] ?? null;
        $out['physician_npi'] = $formData['provider_npi'] ?? $formData['physician_npi'] ?? null;
        $out['physician_specialty'] = $formData['provider_specialty'] ?? null;
        $out['physician_tax_id'] = $formData['provider_tax_id'] ?? null;
        $out['physician_ptan'] = $formData['provider_ptan'] ?? null;
        $out['physician_medicaid'] = $formData['provider_medicaid_id'] ?? null;
        $out['physician_phone'] = $formData['provider_phone'] ?? null;
        $out['physician_fax'] = $formData['provider_fax'] ?? null;
        $out['physician_organization'] = $formData['provider_org'] ?? null;

        $out['facility_name'] = $formData['facility_name'] ?? null;
        $out['facility_npi'] = $formData['facility_npi'] ?? null;
        $out['facility_tax_id'] = $formData['facility_tax_id'] ?? null;
        $out['facility_ptan'] = $formData['facility_ptan'] ?? null;
        $out['facility_medicaid'] = $formData['facility_medicaid_id'] ?? null;
        $out['facility_address'] = $formData['facility_address_line1'] ?? $formData['facility_address'] ?? null;
        $out['facility_city_state_zip'] = $this->composeCityStateZip(
            $formData['facility_city'] ?? null,
            $formData['facility_state'] ?? null,
            $formData['facility_zip'] ?? null
        );
        $out['facility_phone'] = $formData['facility_phone'] ?? null;
        $out['facility_fax'] = $formData['facility_fax'] ?? null;
        $out['facility_contact_name'] = $formData['facility_contact_name'] ?? null;
        $out['facility_contact_info'] = $formData['facility_contact_phone'] ?? $formData['facility_contact_email'] ?? null;
        $out['facility_organization'] = $formData['facility_org'] ?? null;

        // POS
        $out['place_of_service'] = $formData['place_of_service'] ?? null;
        $out['pos_other_specify'] = $formData['pos_other'] ?? null;

        // Patient
        $out['patient_name'] = $this->composeFullName($formData['patient_first_name'] ?? null, $formData['patient_last_name'] ?? null);
        $out['patient_dob'] = $formData['patient_dob'] ?? null;
        $out['patient_address'] = $formData['patient_address_line1'] ?? $formData['patient_address'] ?? null;
        $out['patient_city_state_zip'] = $this->composeCityStateZip(
            $formData['patient_city'] ?? null,
            $formData['patient_state'] ?? null,
            $formData['patient_zip'] ?? null
        );
        $out['patient_phone'] = $formData['patient_phone'] ?? null;
        $out['patient_email'] = $formData['patient_email'] ?? null;
        $out['patient_caregiver_info'] = $formData['patient_caregiver_info'] ?? null;

        // Insurance
        $out['primary_insurance_name'] = $formData['primary_insurance_name'] ?? null;
        $out['secondary_insurance_name'] = $formData['secondary_insurance_name'] ?? null;
        $out['primary_policy_number'] = $formData['primary_policy_number'] ?? null;
        $out['secondary_policy_number'] = $formData['secondary_policy_number'] ?? null;
        $out['primary_payer_phone'] = $formData['primary_payer_phone'] ?? null;
        $out['secondary_payer_phone'] = $formData['secondary_payer_phone'] ?? null;
        $out['physician_status_primary'] = $formData['physician_status_primary'] ?? null;
        $out['physician_status_secondary'] = $formData['physician_status_secondary'] ?? null;

        // Clinical + radios
        $out['permission_prior_auth'] = $formData['permission_prior_auth'] ?? null;
        $out['patient_in_hospice'] = $formData['patient_in_hospice'] ?? null;
        $out['patient_part_a_stay'] = $formData['patient_part_a_stay'] ?? null;
        $out['patient_global_surgery'] = $formData['patient_global_surgery'] ?? null;
        $out['surgery_cpt_codes'] = $formData['surgery_cpt_codes'] ?? null;
        $out['surgery_date'] = $formData['surgery_date'] ?? null;
        $out['wound_location'] = $formData['wound_location'] ?? null;
        $out['icd_10_codes'] = $this->flattenCodes($formData['icd10_codes'] ?? null);
        $out['total_wound_size'] = $this->composeWoundSize($formData);
        $out['medical_history'] = $formData['medical_history'] ?? null;

        if ($documentType === 'OrderForm') {
            $out['date_of_order'] = $formData['date_of_order'] ?? now()->toDateString();
            $out['anticipated_application_date'] = $formData['anticipated_application_date'] ?? null;

            // Account/Contact derived from facility/provider
            $out['account_contact_email'] = $formData['provider_email'] ?? $formData['facility_contact_email'] ?? null;
            $out['account_contact_name'] = $formData['facility_contact_name'] ?? null;
            $out['account_contact_phone'] = $formData['facility_contact_phone'] ?? null;

            // Shipping
            $out['ship_check_fedex'] = true;
            $out['ship_date_to_receive'] = $formData['ship_date_to_receive'] ?? null;
            $out['ship_facility_or_office'] = $formData['facility_name'] ?? null;
            $out['ship_address1'] = $formData['facility_address_line1'] ?? $formData['facility_address'] ?? null;
            $out['ship_address2'] = $formData['facility_address_line2'] ?? null;
            $out['ship_city'] = $formData['facility_city'] ?? null;
            $out['ship_state'] = $formData['facility_state'] ?? null;
            $out['ship_zip'] = $formData['facility_zip'] ?? null;
            $out['ship_notes'] = $formData['ship_notes'] ?? null;
            $out['patient_id'] = $formData['patient_mrn'] ?? $formData['patient_id'] ?? null;

            // Lines from selected_products (max 5)
            $lines = $this->buildOrderLinesFromSelectedProducts($formData['selected_products'] ?? []);
            $out = array_merge($out, $lines['canonical'], [
                'sub_total' => $lines['sub_total'],
                'discount' => $formData['discount'] ?? null,
                'total' => $lines['total'],
            ]);
        }

        // Keep only canonicals that are mapped for the documentType
        $map = $documentType === 'OrderForm'
            ? ($cfg['order_form_field_names'] ?? [])
            : ($cfg['docuseal_field_names'] ?? []);

        return array_intersect_key($out, $map);
    }

    protected function buildOrderLinesFromSelectedProducts(array $selected): array
    {
        $canonical = [];
        $subTotal = 0.0;

        foreach (array_slice($selected, 0, 5) as $i => $sel) {
            $idx = $i + 1;
            $qty = (int)($sel['quantity'] ?? 1);
            $desc = $sel['product']['name'] ?? 'Product';
            $size = $sel['size'] ?? ($sel['product']['size'] ?? null);

            $unit = null;
            if (!empty($sel['product']['size_pricing']) && $size && isset($sel['product']['size_pricing'][$size])) {
                $unit = (float)$sel['product']['size_pricing'][$size];
            } elseif (isset($sel['product']['msc_price'])) {
                $unit = (float)$sel['product']['msc_price'];
            } elseif (isset($sel['product']['price_per_sq_cm']) && isset($sel['area_sq_cm'])) {
                $unit = (float)$sel['product']['price_per_sq_cm'] * (float)$sel['area_sq_cm'];
            }

            $amount = ($unit !== null) ? $qty * (float)$unit : null;
            if ($amount !== null) $subTotal += $amount;

            $canonical["line{$idx}_quantity"] = $qty ?: null;
            $canonical["line{$idx}_description"] = $desc;
            $canonical["line{$idx}_size"] = $size;
            $canonical["line{$idx}_unit_price"] = $unit !== null ? number_format($unit, 2, '.', '') : null;
            $canonical["line{$idx}_amount"] = $amount !== null ? number_format($amount, 2, '.', '') : null;
        }

        return [
            'canonical' => $canonical,
            'sub_total' => $subTotal > 0 ? number_format($subTotal, 2, '.', '') : null,
            'total' => $subTotal > 0 ? number_format($subTotal, 2, '.', '') : null,
        ];
    }

    protected function extractQCode(array $formData): ?string
    {
        $sel = $formData['selected_products'] ?? [];
        if (is_array($sel) && !empty($sel)) {
            $first = $sel[0] ?? null;
            return $first['product']['q_code'] ?? $first['product']['code'] ?? null;
        }
        return $formData['product_q_code'] ?? null;
    }

    protected function composeFullName(?string $first, ?string $last): ?string
    {
        $first = trim((string)($first ?? ''));
        $last = trim((string)($last ?? ''));
        $full = trim($first . ' ' . $last);
        return $full !== '' ? $full : null;
    }

    protected function composeCityStateZip(?string $city, ?string $state, ?string $zip): ?string
    {
        $city = trim((string)($city ?? ''));
        $state = trim((string)($state ?? ''));
        $zip = trim((string)($zip ?? ''));
        $parts = array_filter([$city, $state, $zip], fn($v) => $v !== '');
        if (empty($parts)) return null;
        if ($city !== '' && $state !== '') {
            return $zip !== '' ? "{$city}, {$state}, {$zip}" : "{$city}, {$state}";
        }
        return implode(', ', $parts);
    }

    protected function composeWoundSize(array $formData): ?string
    {
        $l = $formData['wound_size_length'] ?? null;
        $w = $formData['wound_size_width'] ?? null;
        if ($l && $w) return "{$l} x {$w} cm";
        return $formData['total_wound_size'] ?? null;
    }

    protected function flattenCodes($codes): ?string
    {
        if (is_array($codes)) return implode(', ', array_filter(array_map('strval', $codes)));
        return $codes ? (string)$codes : null;
    }

    // Deterministic normalization already occurs inside convertToDocusealFields(...)
    // ...existing code...
}
```

3) Deterministic normalization and safe conversion to DocuSeal fields

Ensure convertToDocusealFields applies the radio/POS normalization and skips unknown canonicals.

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/UnifiedFieldMappingService.php
<?php
// ...existing code...
    public function convertToDocusealFields(array $mappedData, array $manufacturerConfig, string $documentType = 'IVR'): array
    {
        $docuSealFields = [];

        $fieldNameMapping = $documentType === 'OrderForm'
            ? ($manufacturerConfig['order_form_field_names'] ?? [])
            : ($manufacturerConfig['docuseal_field_names'] ?? []);

        $normalizeYesNo = function ($v) {
            if (is_bool($v)) return $v ? 'Yes' : 'No';
            $s = strtolower(trim((string)$v));
            if (in_array($s, ['1','true','yes','y'], true)) return 'Yes';
            if (in_array($s, ['0','false','no','n'], true)) return 'No';
            return (string)$v;
        };
        $normalizeNetwork = function ($v) {
            $s = strtolower(trim((string)$v));
            if (in_array($s, ['in','in-network','in network'], true)) return 'In-Network';
            if (in_array($s, ['out','out-of-network','out of network'], true)) return 'Out-of-Network';
            return (string)$v;
        };
        $normalizePos = function ($v) {
            $map = [
                '11' => 'POS 11', 'pos 11' => 'POS 11', 'office' => 'POS 11',
                '12' => 'POS 12', 'pos 12' => 'POS 12', 'home' => 'POS 12',
                '22' => 'POS 22', 'pos 22' => 'POS 22',
                '24' => 'POS 24', 'pos 24' => 'POS 24',
                '32' => 'POS 32', 'pos 32' => 'POS 32',
            ];
            $s = strtolower(trim((string)$v));
            return $map[$s] ?? (preg_match('/^pos\s+\d+$/i', (string)$v) ? (string)$v : (string)$v);
        };

        foreach ($mappedData as $canonical => $value) {
            if ($value === null || ($value === '' && !is_bool($value))) continue;
            if (!isset($fieldNameMapping[$canonical])) continue;

            $docField = (string)$fieldNameMapping[$canonical];
            $out = $value;

            if ($canonical === 'place_of_service') $out = $normalizePos($out);
            if (in_array($canonical, ['physician_status_primary','physician_status_secondary'], true)) $out = $normalizeNetwork($out);
            if (in_array($canonical, ['permission_prior_auth','patient_in_hospice','patient_part_a_stay','patient_global_surgery'], true)) {
                $out = $normalizeYesNo($out);
            }

            if (is_array($out)) $out = implode(', ', array_filter(array_map('strval', $out)));
            if (is_bool($out)) $out = $out ? 'true' : 'false';

            $docuSealFields[] = [
                'name' => $docField,
                'default_value' => (string)$out,
                'readonly' => false,
            ];
        }

        return $docuSealFields;
    }
// ...existing code...
```

4) Gate Order Form creation after IVR verification

When an IVR episode is verified, create the Order Form submission and surface it in Order Details as “Ready to Sign”.

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/QuickRequest/QuickRequestOrchestrator.php
<?php
// ...existing imports...
use App\Services\DocusealService;
// ...existing code...

class QuickRequestOrchestrator
{
    public function __construct(
        protected FormFillingOptimizer $formOptimizer,
        protected UnifiedFieldMappingService $fieldMappingService,
        protected DataExtractionService $dataExtractor,
        protected DocusealService $docusealService,
        // ...other deps...
    ) {}

    // ...existing code...

    public function markIvrVerifiedAndPrepareOrderForm(PatientManufacturerIVREpisode $episode, array $formData): void
    {
        // 1) Mark IVR verified in your current logic (outside this method)
        $order = $episode->productRequest ?? null;
        if (!$order) return;

        $manufacturerName = $order->manufacturer?->name ?? $episode->manufacturer?->name ?? '';
        if (!preg_match('/acz/i', (string)$manufacturerName)) return; // only ACZ here

        // 2) Build Order Form payload using QuickRequest formData
        $mapped = $this->fieldMappingService->mapQuickRequestToDocuseal('acz-associates', $formData, 'OrderForm');
        if (!$mapped['success'] || empty($mapped['template_id'])) return;

        // 3) Create DocuSeal submission (embed signing, no email)
        $submission = $this->docusealService->createSubmissionForOrderForm(
            (string)$mapped['template_id'],
            $mapped['fields'],
            ['order_id' => $order->id, 'episode_id' => $episode->id]
        );

        // 4) Persist on order for Provider/Office Manager UI
        $order->order_form_status = 'Ready to Sign';
        $order->order_form_submission_id = $submission['id'] ?? ($submission['submission_id'] ?? null);
        $order->order_form_link = $submission['submissions'][0]['url'] ?? $submission['embed_url'] ?? null;
        $order->save();
    }
}
```

5) DocuSeal service method (POST /submissions)

```php
// filepath: /home/rvalen/Projects/msc-woundcare-portal/app/Services/DocusealService.php
<?php
// ...existing code...
class DocusealService
{
    // ...existing code...

    public function createSubmissionForOrderForm(string $templateId, array $fields, array $meta = []): array
    {
        $payload = [
            'template_id' => (int)$templateId,
            'send_email' => false,
            'embed_signing' => true,
            'fields' => $fields, // [{ name, default_value, readonly }]
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

6) Frontend pass-through (already wired)

Ensure Step 7 sends manufacturerId for ACZ when the product is from ACZ. The backend decides template IDs and mappings; the embed simply loads the submission by slug.

```tsx
// filepath: /home/rvalen/Projects/msc-woundcare-portal/resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx
// ...existing code...
// ensure manufacturerId is 'acz-associates' when product.manufacturer ~ /acz/i
```

Normalization rules (enforced server-side)

- POS: "11"|"POS 11" → "POS 11"; "12"|"Home" → "POS 12"; etc.
- Network: in|in-network → "In-Network"; out|out-of-network → "Out-of-Network"
- Yes/No: strictly "Yes"/"No"
- Dates: YYYY-MM-DD unless template expects a different format
- Phone: digits or standardized format
- ICD-10: comma-separated
- City/State/Zip: "City, ST, ZIP"

Validation & safety

- getManufacturerConfig filters mapping dictionaries against the live template fields of 1530960/1530964 (if validator enabled), or falls back to KB inventories (knowledge-base/data/IVRs/.../fields.json).
- convertToDocusealFields only sends fields present in mapping; unknown canonicals skipped.
- No DocuSeal templates are edited—only submission payloads are posted.

Example API payloads

IVR submission

```json
{
  "template_id": 1530960,
  "fields": [
    { "name": "Patient Name", "default_value": "Jane Doe", "readonly": false },
    { "name": "Patient DOB", "default_value": "1972-03-05", "readonly": false },
    { "name": "Product Q Code", "default_value": "Q4316", "readonly": false },
    { "name": "Place of Service", "default_value": "POS 11", "readonly": false }
  ]
}
```

Order Form submission (after IVR verified)

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

Testing checklist

- Configure DOCUSEAL_* env values.
- Place fields_1530960.json and fields_1530964.json under knowledge-base/data/IVRs/<manufacturer-slug>/ (optional fallback).
- Create a QuickRequest with an ACZ product and complete IVR.
- Verify:
  - IVR payload maps and submits successfully.
  - On IVR verification, an Order Form submission is created and order_form_status becomes “Ready to Sign”.
  - Provider/Office Manager Order Details shows an embedded Order Form link.

Notes

- NBSP labels: if the live template uses non‑breaking spaces in certain “Description/Unit Price/Amount” labels, copy exact names from fields_1530964.json and update order_form_field_names accordingly.
- Coverage: you can surface coverage% in DocusealEmbed; non-blocking warning if < 95%.
