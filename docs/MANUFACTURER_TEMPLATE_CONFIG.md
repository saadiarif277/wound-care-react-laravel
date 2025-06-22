# Manufacturer Template Configuration

## Overview

The MSC Wound Portal integrates with DocuSeal for manufacturer-specific IVR forms. Each manufacturer has unique template IDs that must be configured in the environment file.

## Required Environment Variables

Add these to your `.env` file:

```env
# DocuSeal API Configuration
DOCUSEAL_API_KEY=your_docuseal_api_key
DOCUSEAL_API_URL=https://api.docuseal.com
DOCUSEAL_WEBHOOK_SECRET=your_webhook_secret

# Manufacturer Template IDs
DOCUSEAL_ACZ_TEMPLATE_ID=852440
DOCUSEAL_ACZ_FOLDER_ID=75423

DOCUSEAL_ADVANCED_HEALTH_TEMPLATE_ID=xxxxx
DOCUSEAL_ADVANCED_HEALTH_FOLDER_ID=xxxxx

DOCUSEAL_MEDLIFE_TEMPLATE_ID=xxxxx
DOCUSEAL_MEDLIFE_FOLDER_ID=xxxxx

DOCUSEAL_CENTURION_TEMPLATE_ID=xxxxx
DOCUSEAL_CENTURION_FOLDER_ID=xxxxx

DOCUSEAL_BIOWERX_TEMPLATE_ID=xxxxx
DOCUSEAL_BIOWERX_FOLDER_ID=xxxxx

DOCUSEAL_BIOWOUND_TEMPLATE_ID=xxxxx
DOCUSEAL_BIOWOUND_FOLDER_ID=xxxxx

DOCUSEAL_EXTREMITY_CARE_TEMPLATE_ID=xxxxx
DOCUSEAL_EXTREMITY_CARE_FOLDER_ID=xxxxx

DOCUSEAL_SKYE_BIOLOGICS_TEMPLATE_ID=xxxxx
DOCUSEAL_SKYE_BIOLOGICS_FOLDER_ID=xxxxx

DOCUSEAL_TOTAL_ANCILLARY_TEMPLATE_ID=xxxxx
DOCUSEAL_TOTAL_ANCILLARY_FOLDER_ID=xxxxx
```

## Manufacturer Mappings

The system maps product Q-codes to manufacturers:

| Q-Code | Manufacturer | IVR Form |
|--------|--------------|----------|
| Q4154 | ACZ Distribution | Updated Q2 IVR ACZ.pdf |
| Q4250 | MedLife | AMNIO AMP MedLife IVR-fillable.pdf |
| Q4290 | Extremity Care | Q2/Q4 specific forms |
| Q4121 | BioWerX | BioWerX Fillable IVR Apr 2024.pdf |
| Q4134 | BioWound | BioWound IVR v3 |
| Q4222 | Advanced Health | Template IVR Advanced Solution Universal REV2.0 |
| Q4220 | Centurion | Centurion AmnioBand IVR (STAT orders only) |
| Q4252 | Skye Biologics | WoundPlus.Patient.Insurance.Verification.Form |
| Q4217 | Total Ancillary | Universal_Benefits_Verification |

## How to Obtain Template IDs

1. Log into your DocuSeal account
2. Navigate to Templates section
3. Find or upload the manufacturer IVR forms
4. Copy the template ID from the URL or template details
5. Create folders for each manufacturer in DocuSeal
6. Copy the folder IDs

## Testing Configuration

After adding the environment variables:

1. Clear configuration cache:
   ```bash
   php artisan config:clear
   ```

2. Test IVR field mapping endpoint:
   ```bash
   curl -X GET /api/v1/ivr/manufacturers/ACZ_Distribution/fields
   ```

3. Verify template loading in the application

## Fallback Configuration

If template IDs are not set in environment, the system will use default values from `config/ivr-field-mappings.php`.

## Security Notes

- Never commit actual template IDs to version control
- Use different template IDs for staging/production
- Rotate API keys regularly
- Monitor DocuSeal webhook for security events