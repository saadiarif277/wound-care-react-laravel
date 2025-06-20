# DocuSeal Field Requirements Summary

## Organization Fields

### ✅ Currently Available:
- `name` - Organization name
- `tax_id` - EIN/Tax ID
- `phone` - Phone number
- `billing_address` - Billing street address
- `billing_city` - Billing city
- `billing_state` - Billing state
- `billing_zip` - Billing ZIP code
- `email` - Organization email

### Status: **All Required Fields Available** ✅

## Provider Fields

### ✅ Currently Available:
- `first_name` / `last_name` / `full_name` - Provider name
- `email` - Provider email
- `npi_number` - NPI (in users table)
- `credentials` - Credentials array
- **In provider_profiles table:**
  - `npi` - NPI (duplicate)
  - `tax_id` - Provider Tax ID
  - `ptan` - PTAN
  - `specialty` - Medical specialty

### ❌ Missing Fields (Added in Migration):
- `phone` - Provider phone number
- `fax` - Provider fax number
- `medicaid_number` - Medicaid provider number

### Status: **Migration Required** - Run `2025_01_21_000002_add_missing_docuseal_fields.php`

## Facility Fields

### ✅ Currently Available:
- `name` - Facility name
- `address` - Street address (single field)
- `city` - City
- `state` - State
- `zip_code` - ZIP code (note: named `zip_code` not `zip`)
- `npi` - Facility NPI
- `tax_id` - Facility Tax ID
- `phone` - Facility phone
- `contact_name` - Contact person name
- `contact_phone` - Contact phone
- `contact_email` - Contact email
- `contact_fax` - Contact fax
- `ptan` - PTAN

### ❌ Missing Fields (Added in Migration):
- `address_line1` - First line of address
- `address_line2` - Second line of address
- `fax` - General facility fax

### Status: **Migration Required** - Run `2025_01_21_000002_add_missing_docuseal_fields.php`

## Admin UI Updates Needed

After running the migration, update the admin pages to include:

### 1. Provider Edit Page (`/admin/providers/{id}/edit`):
- Add phone field
- Add fax field
- Add Medicaid number field

### 2. Facility Edit Page (`/admin/facilities/{id}/edit`):
- Split address into address_line1 and address_line2
- Add general fax field (separate from contact_fax)

### 3. Organization Edit Page:
- No changes needed - all fields already present

## DocuSeal Integration Notes

The integration now handles fallbacks properly:
- Uses `address` if `address_line1` is not available
- Uses `zip_code` or `zip` depending on what's available
- Uses `contact_fax` as fallback for facility fax
- Checks multiple locations for provider NPI
- Falls back to organization tax_id if facility doesn't have one

## Next Steps

1. Run the migration: `php artisan migrate`
2. Update admin UI forms to include new fields
3. Update model fillable arrays if needed
4. Test DocuSeal integration with complete data