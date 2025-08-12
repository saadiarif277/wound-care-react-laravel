# ACZ DocuSeal Prefill (IVR 1530960, Order Form 1530964)

This document describes how QuickRequest safely prefills ACZ DocuSeal forms using backend mapping only—no edits to DocuSeal templates.

## Scope

- Manufacturer: ACZ & Associates
- Templates (read-only):
  - IVR: 1530960
  - Order Form: 1530964
- Trigger: Selected product manufacturer matches ACZ
- Output: DocuSeal submission payload with default_value field fills

## Key guarantees

- No changes to DocuSeal templates; API calls are read-only except for creating a submission.
- Field names match exported CSVs exactly.
- Radios/checkboxes use DocuSeal’s exact text values (e.g., "Yes", "In-Network", "POS 11").
- Order Form line items (up to 5) are built from selected_products.

## Backend flow

1. Detect ACZ manufacturer from selected_products.
2. Build canonical QuickRequest data (provider, facility, patient, insurance, clinical, product q_code).
3. Map canonical → DocuSeal names using `config/manufacturers/acz-associates.php`:
   - IVR mapping: `docuseal_field_names`
   - Order form mapping: `order_form_field_names`
4. Normalize values:
   - Dates → `YYYY-MM-DD` (or printable format per template)
   - Phone → digits only (or `(###) ###-####` if needed)
   - POS, Network, Yes/No → exact labels
   - City/State/Zip → `City, ST, ZIP`
5. Prefill payload:
   - IVR: `template_id=1530960`
   - Order form: `template_id=1530964`
   - `fields: [{ name, default_value, readonly:false }]`
6. Submit via DocuSeal API `/submissions`.

## Field mapping (IVR 1530960)

Source of truth: `ACZ & Associates IVR (7).csv` header row

Examples (canonical → DocuSeal field name):

- product_q_code → Product Q Code
- representative_name → Sales Rep
- iso_if_applicable → ISO if applicable
- additional_emails → Additional Emails for Notification
- physician_name → Physician Name
- physician_npi → Physician NPI
- facility_name → Facility Name
- facility_address → Facility Address
- facility_city_state_zip → Facility City, State, Zip
- place_of_service → Place of Service
- pos_other_specify → POS Other Specify
- patient_name → Patient Name
- patient_dob → Patient DOB
- primary_insurance_name → Primary Insurance Name
- physician_status_primary → Physician Status With Primary
- permission_prior_auth → Permission To Initiate And Follow Up On Prior Auth?
- patient_in_hospice → Is The Patient Currently in Hospice?
- patient_part_a_stay → Is The Patient In A Facility Under Part A Stay?
- patient_global_surgery → Is The Patient Under Post-Op Global Surgery Period?
- surgery_cpt_codes → If Yes, List Surgery CPTs
- surgery_date → Surgery Date
- wound_location → Location of Wound
- icd_10_codes → ICD-10 Codes
- total_wound_size → Total Wound Size
- medical_history → Medical History

## Field mapping (Order Form 1530964)

Source of truth: `ACZ & Associates Order Form.csv` header row

Header/contact

- account_name → Name
- account_email → Email
- account_phone → Phone
- date_of_order → Date of Order
- anticipated_application_date → Anticipated Application Date
- physician_name → Physican Name (CSV spelling)
- account_contact_email → Account Contact E-mail
- account_contact_name → Account Contact Name
- account_contact_phone → Account Contact #

Line items (max 5)

- lineX_quantity → Quantity Line X
- lineX_description → Description Line X
- lineX_size → Size Line X
- lineX_unit_price → Unit Price Line X
- lineX_amount → Amount Line X

Totals

- sub_total → Sub-Total
- discount → Discount
- total → Total

Shipping

- ship_check_fedex → Check FedEx
- ship_date_to_receive → Date to Recieve
- ship_facility_or_office → Facility or Office Name
- ship_address1 → Ship to Address
- ship_address2 → Ship to Address 2
- ship_city → Ship to City
- ship_state → Ship to State
- ship_zip → Ship to Zip
- ship_notes → Notes

Patient link

- patient_id → Patient ID

## Normalization rules

- POS: map known values (e.g., `"11"|"POS 11" → "POS 11"`; include `pos_other_specify` when POS=Other)
- Network: `in|in-network → "In-Network"`, `out|out-of-network → "Out-of-Network"`
- Yes/No radios: use exact `"Yes"` or `"No"`
- Dates: prefer ISO `YYYY-MM-DD` unless template expects printable; we can switch per-field if needed
- Phone: strip to 10 digits or format `(###) ###-####`
- ICD-10: list joined by ", "
- City/State/Zip: `City, ST, ZIP`

## Validation & safety

- Uses TemplateInventoryService/DocuSealApiClient to fetch live fields and validate prior to submit.
- Any unmapped/missing fields are logged; we only send known field names.
- No mutation of templates. All operations are additive (submission only).

## Artifacts

- Field inventories: `knowledge-base/data/IVRs/MSC Forms/fields_1530960.json`, `fields_1530964.json`, merged `fields.json`
- Manufacturer config: `config/manufacturers/acz-associates.php`
- Export helper: `php artisan docuseal:export-fields 1530960 1530964 --manufacturer="MSC Forms" --doc=IVRs --format=json`

## Example submission payload (IVR)

```json
{
  "template_id": "1530960",
  "fields": [
    { "name": "Patient Name", "default_value": "Jane Doe", "readonly": false },
    { "name": "Patient DOB", "default_value": "1972-03-05", "readonly": false },
    { "name": "Product Q Code", "default_value": "Q4316", "readonly": false },
    { "name": "Place of Service", "default_value": "POS 11", "readonly": false }
  ]
}
```

## Testing steps

1. Ensure env has valid DOCUSEAL_* values and the two templates exist under your account.
2. Export fields (read-only):
   - `php artisan docuseal:export-fields 1530960 1530964 --manufacturer="MSC Forms" --doc=IVRs --format=json --dry-run`
3. Submit a test mapping in dev/UAT with an ACZ product in selected_products.
4. Verify DocuSeal prefill matches expected fields; radios show correct selections.

## Risks & mitigations

- Field label drift (NBSPs, typos): always validate against live template before sending.
- Price/size variability: line item builder prefers size-specific pricing > MSC price > derived.
- Radio labels: treat as exact strings per current CSVs; adjust normalization if templates change.

## Next steps

- Wire controller route to call the mapper for ACZ when product.manufacturer ~ /acz/i.
- Add a unit test for IVR and Order Form canonical→DocuSeal conversions.
