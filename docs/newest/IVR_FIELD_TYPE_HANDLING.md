# IVR Field Type Handling Guide

## Overview

The MSC Wound Portal handles various field types for DocuSeal IVR forms with automatic detection and formatting based on field names and manufacturer requirements.

## Field Type Detection & Formatting

### 1. **Checkboxes**

Checkboxes are automatically detected when field names contain keywords like:
- `status`, `is_`, `has_`, `permission`, `required`, `attached`

**Input Formats Accepted:**
- Boolean: `true/false`
- String: `"yes"/"no"`, `"y"/"n"`, `"checked"/"unchecked"`, `"x"/""`, `"1"/"0"`
- Numeric: `1/0`

**Output Formats (varies by manufacturer):**
```php
// Standard format
true → "Yes"
false → "No"

// ACZ Distribution (uppercase)
true → "YES"
false → "NO"

// Advanced Health (checkmark)
true → "✓"
false → ""

// Amnio AMP (X mark)
true → "X"
false → ""
```

**Example Fields:**
- `Is Patient in Hospice` → Checkbox
- `Global Period Status` → Checkbox
- `Prior Auth Permission` → Checkbox
- `Insurance Cards Attached` → Checkbox

### 2. **Dates**

Date fields are detected by keywords: `date`, `dob`, `_at`, `birth`

**Input Formats Accepted:**
- ISO: `"2024-01-15"`
- US Format: `"01/15/2024"`
- DateTime objects
- Carbon instances

**Output Formats:**
```php
// Standard (most manufacturers)
"2024-01-15" → "01/15/2024"

// BioWound (uses dashes)
"2024-01-15" → "01-15-2024"
```

**Example Fields:**
- `Patient DOB` → Date
- `Surgery Date` → Date
- `Anticipated Treatment Date` → Date

### 3. **Phone Numbers**

Phone fields are detected by keywords: `phone`, `fax`, `tel`

**Input Formats Accepted:**
- `"3055551234"`
- `"305-555-1234"`
- `"(305) 555-1234"`
- `"1-305-555-1234"`

**Output Format:**
```php
// Standard US format
"3055551234" → "(305) 555-1234"
```

**Example Fields:**
- `Phone #` → Phone
- `Patient Phone` → Phone
- `Primary Payer Phone` → Phone

### 4. **Select/Dropdown Fields**

Single-choice fields detected by keywords: `type`, `status`, `place_of_service`, `plan_type`

**Place of Service Mapping:**
```php
"11" → "Physician Office (POS 11)"
"22" → "Hospital Outpatient (POS 22)"  
"24" → "Ambulatory Surgical Center (POS 24)"
"12" → "Home (POS 12)"
"31" → "Skilled Nursing Facility (POS 31)"
```

**Network Status Mapping:**
```php
"in_network" → "In-Network"
"out_of_network" → "Out-of-Network"
"unknown" → "Not Sure (Please verify)"
```

### 5. **Multi-Select Fields**

Multiple-choice fields detected by keywords: `codes`, `products`, `services`

**Input Formats:**
- Array: `["L97.419", "E11.621"]`
- Comma-separated: `"L97.419, E11.621"`

**Output Format:**
```php
["L97.419", "E11.621"] → "L97.419, E11.621"
```

**Example Fields:**
- `ICD-10 Codes` → Multi-select
- `Application CPT(s)` → Multi-select
- `Selected Products` → Multi-select

### 6. **Number Fields**

Numeric fields detected by keywords: `number`, `count`, `qty`, `quantity`, `days`, `size`

**Formatting:**
- Removes non-numeric characters except decimal
- Defaults to "0" if empty

**Example Fields:**
- `Total Wound Size` → Number
- `Number of Days in SNF` → Number
- `Number of Applications` → Number

### 7. **Currency Fields**

Money fields detected by keywords: `price`, `cost`, `amount`, `fee`, `charge`

**Formatting:**
```php
"1234.5" → "$1,234.50"
"" → "$0.00"
```

### 8. **Text/Textarea Fields**

Default field type for all other fields.

**Formatting:**
- Trims whitespace
- Handles special quotes and apostrophes
- Preserves line breaks for textarea

## Manufacturer-Specific Rules

### ACZ Distribution
- Checkboxes: Uppercase `YES/NO`
- Permission fields: `YES` or blank (no `NO`)
- Standard date format: `MM/DD/YYYY`

### Advanced Health Solutions
- Checkboxes: Checkmark `✓` or blank
- Contact permission uses checkmark
- Standard phone formatting

### Amnio AMP / MedLife
- Checkboxes: `X` for checked, blank for unchecked
- Insurance card attachment uses `X` mark
- Standard date format

### BioWound Solutions
- Dates use dashes: `MM-DD-YYYY`
- Standard checkbox format
- Multi-line address support

## Usage Example

```php
// In IvrFieldMappingService
$mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
    $productRequest,
    'ACZ_Distribution'
);

// Result:
[
    'Is Patient in Hospice' => 'NO',        // Checkbox → uppercase
    'Patient DOB' => '01/15/1950',          // Date → MM/DD/YYYY
    'Phone #' => '(305) 555-0100',          // Phone → formatted
    'Place of Service' => 'Physician Office (POS 11)', // Select → mapped
    'ICD-10 Codes' => 'L97.419, E11.621',   // Multi-select → comma-separated
    'Total Wound Size' => '6',               // Number → cleaned
    'Prior Auth Permission' => 'YES',        // Checkbox → YES or blank
]
```

## Adding New Field Types

To add a new field type:

1. Add detection logic in `DocuSealFieldFormatterService::detectFieldType()`
2. Add formatting logic in `DocuSealFieldFormatterService::formatFieldValue()`
3. Add manufacturer-specific rules in `IvrFieldMappingService::applyManufacturerSpecificFormatting()`
4. Update field type definitions in `IvrFieldMappingService::getFieldTypes()`

## Testing Field Types

```php
// Test checkbox formatting
$formatter = new DocuSealFieldFormatterService();

// Various input formats
$formatter->formatFieldValue(true, 'checkbox');        // "Yes"
$formatter->formatFieldValue('1', 'checkbox');         // "Yes"
$formatter->formatFieldValue('checked', 'checkbox');   // "Yes"
$formatter->formatFieldValue(false, 'checkbox');       // "No"
$formatter->formatFieldValue('', 'checkbox');          // "No"

// Test date formatting
$formatter->formatFieldValue('2024-01-15', 'date');    // "01/15/2024"
$formatter->formatFieldValue('Jan 15, 2024', 'date');  // "01/15/2024"

// Test phone formatting  
$formatter->formatFieldValue('3055551234', 'phone');   // "(305) 555-1234"
$formatter->formatFieldValue('1-305-555-1234', 'phone'); // "(305) 555-1234"
```