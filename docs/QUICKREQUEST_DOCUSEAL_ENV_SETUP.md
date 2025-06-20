# QuickRequest DocuSeal Environment Setup

## Required Environment Variables

Add these to your `.env` file:

```env
# DocuSeal API Configuration
DOCUSEAL_API_KEY=your_docuseal_api_key_here
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret_here

# Final Submission Template (for order submissions to MSC)
DOCUSEAL_FINAL_SUBMISSION_TEMPLATE_ID=template_final_submission_id

# Manufacturer IVR Templates (optional - can be managed in admin panel)
DOCUSEAL_ACZ_TEMPLATE_ID=template_acz_ivr_id
DOCUSEAL_MEDLIFE_TEMPLATE_ID=template_medlife_ivr_id
DOCUSEAL_BIOWOUND_TEMPLATE_ID=template_biowound_ivr_id
DOCUSEAL_ADVANCED_HEALTH_TEMPLATE_ID=template_advanced_health_ivr_id
```

## DocuSeal Template Setup

### 1. Final Submission Template

Create a DocuSeal template with these fields for the final order submission:

**Patient Information:**

- `patient_first_name`
- `patient_last_name`
- `patient_dob`
- `patient_gender`
- `patient_member_id`
- `patient_address`
- `patient_city`
- `patient_state`
- `patient_zip`
- `patient_phone`
- `patient_email`

**Provider Information:**

- `provider_name`
- `provider_npi`
- `facility_name`
- `facility_address`

**Clinical Information:**

- `wound_type`
- `wound_location`
- `wound_size`
- `wound_onset_date`
- `failed_conservative_treatment`
- `treatment_tried`
- `current_dressing`
- `expected_service_date`

**Insurance Information:**

- `primary_insurance`
- `primary_member_id`
- `primary_plan_type`
- `primary_payer_phone`
- `has_secondary_insurance`
- `secondary_insurance`
- `secondary_member_id`

**Product Information:**

- `selected_product_name`
- `selected_product_code`
- `selected_product_manufacturer`
- `product_quantity`
- `product_size`

**Shipping Information:**

- `shipping_same_as_patient`
- `shipping_address`
- `shipping_city`
- `shipping_state`
- `shipping_zip`
- `delivery_notes`

**Metadata:**

- `submission_date`
- `total_wound_area`

### 2. Testing Configuration

1. Set up a test DocuSeal account
2. Create the final submission template with all required fields
3. Get the template ID from DocuSeal admin panel
4. Update your `.env` file with the template ID
5. Test the QuickRequest flow end-to-end

### 3. Production Setup

1. Create production DocuSeal templates
2. Update environment variables with production values
3. Test webhooks and form submissions
4. Configure proper access controls and permissions

## Webhook Configuration

Set up a webhook in DocuSeal to handle form completions:

- **Webhook URL**: `https://yourdomain.com/api/v1/webhooks/docuseal/quick-request`
- **Events**: `submission.completed`
- **Secret**: Use the value from `DOCUSEAL_WEBHOOK_SECRET`

## Security Notes

- Store all sensitive keys in environment variables
- Use different API keys for development and production
- Regularly rotate webhook secrets
- Ensure HTTPS is used for all webhook URLs
- Implement proper access controls for DocuSeal templates
