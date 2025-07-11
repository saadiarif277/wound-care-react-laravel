# Map-Forms CLI Tool

A comprehensive command-line tool for processing Form Recognizer output, applying Canal field mappings, and producing clean canonical JSON or CSV output with batch processing and dry-run validation capabilities.

## Overview

The `map-forms` tool automates the workflow of:
1. Ingesting Form Recognizer output (Azure Format or custom JSON)
2. Applying Canal field mappings to standardize field names
3. Transforming data using configurable rules
4. Validating field values
5. Emitting clean canonical JSON or CSV output

## Installation

1. Ensure Python 3.8+ is installed:
```bash
python3 --version
```

2. Install required dependencies:
```bash
pip install click pyyaml
```

3. Make the script executable:
```bash
chmod +x map-forms
```

4. (Optional) Add to PATH for system-wide access:
```bash
sudo ln -s /path/to/map-forms /usr/local/bin/map-forms
```

## Quick Start

### Process a single form:
```bash
./map-forms form_output.json -o mapped_form.json
```

### Batch process multiple forms:
```bash
./map-forms ./forms/ --batch --format csv -o results.csv
```

### Dry-run validation:
```bash
./map-forms form_output.json --dry-run --verbose
```

## Usage

```
map-forms [OPTIONS] INPUT_PATH

Options:
  -m, --mappings PATH     Path to Canal mappings JSON file [default: docs/mapping-final/canal_form_mapping.json]
  -r, --rules PATH        Path to transformation rules configuration
  -o, --output PATH       Output file path (auto-generated if not specified)
  -f, --format [json|csv|both]  Output format [default: json]
  -b, --batch             Process multiple files in batch mode
  -d, --dry-run           Perform dry-run validation without saving output
  -v, --verbose           Enable verbose logging
  -q, --quiet             Suppress non-error output
  --recursive             Process files recursively in batch mode
  -p, --pattern TEXT      File pattern for batch processing [default: *.json]
  -s, --summary           Show summary statistics after processing
  --help                  Show this message and exit.
```

## Features

### 1. Form Recognition Support
- Supports Azure Form Recognizer output format
- Handles custom JSON form data
- Extracts confidence scores when available

### 2. Canal Field Mappings
- Maps source fields to canonical field names
- Supports fuzzy matching for form identification
- Tracks mapping status (mapped/unmapped/error)

### 3. Data Transformation
- Phone number formatting: (555) 123-4567
- Date normalization: YYYY-MM-DD
- Case conversion: uppercase, lowercase, title case
- Whitespace normalization
- Alphanumeric filtering

### 4. Field Validation
- NPI number validation (10 digits)
- Phone number validation
- Email format validation
- Date format validation
- Required field checking

### 5. Batch Processing
- Process entire directories
- Recursive directory scanning
- Custom file patterns (e.g., *.json, form_*.json)
- Aggregate statistics

### 6. Output Formats
- **JSON**: Structured canonical data with metadata
- **CSV**: Tabular format for spreadsheet analysis
- **Both**: Generate both formats simultaneously

### 7. Dry-Run Mode
- Validate mappings without saving output
- Check for errors and warnings
- Test transformation rules

## Configuration

### Canal Mappings (canal_form_mapping.json)

The Canal mappings file defines how source fields map to canonical fields:

```json
{
  "version": "1.0",
  "forms": {
    "CENTURION_THERAPEUTICS_IVR": {
      "form_id": "centurion_therapeutics_ivr",
      "form_name": "CENTURION_THERAPEUTICS_IVR",
      "fields": {
        "patient_name": {
          "label": "Patient Name",
          "canonical_key": "patient_name",
          "type": "text",
          "required": false,
          "mapping_status": "mapped"
        }
      }
    }
  }
}
```

### Transformation Rules (form-transformation-rules.yaml)

Define how fields should be transformed:

```yaml
transformations:
  patient_phone:
    - type: normalize
      operation: numeric
    - type: format
      operation: phone
  
  patient_dob:
    - type: format
      operation: date
      format: "Y-m-d"
```

## Examples

### Example 1: Single Form Processing

Process a single Form Recognizer output file:

```bash
./map-forms form_recognizer_output.json \
  --mappings canal_mappings.json \
  --rules transformation_rules.yaml \
  --output patient_data.json \
  --verbose
```

### Example 2: Batch CSV Export

Process all JSON files in a directory and export to CSV:

```bash
./map-forms /data/forms/ \
  --batch \
  --format csv \
  --output all_patients.csv \
  --summary
```

### Example 3: Recursive Processing with Pattern

Process all form files recursively:

```bash
./map-forms /data/ \
  --batch \
  --recursive \
  --pattern "*_ivr_*.json" \
  --format both \
  --output processed_forms
```

### Example 4: Validation Only

Validate forms without creating output:

```bash
./map-forms /data/forms/ \
  --batch \
  --dry-run \
  --verbose \
  --summary
```

## Output Format

### JSON Output Structure

```json
{
  "metadata": {
    "form_id": "centurion_therapeutics_ivr",
    "form_name": "CENTURION_THERAPEUTICS_IVR",
    "timestamp": "2024-01-15T10:30:00",
    "processing_time": 0.125,
    "statistics": {
      "total_fields": 57,
      "mapped_fields": 45,
      "unmapped_fields": 12,
      "errors": 0,
      "warnings": 12
    }
  },
  "canonical_data": {
    "patient_name": "John Doe",
    "patient_dob": "1980-01-15",
    "patient_phone": "(555) 123-4567",
    "provider_npi": "1234567890"
  },
  "unmapped_fields": [
    {
      "field": "email_to",
      "value": "admin@example.com"
    }
  ],
  "errors": [],
  "warnings": ["Unmapped field: email_to"]
}
```

### CSV Output Structure

| form_id | form_name | timestamp | patient_name | patient_dob | patient_phone | provider_npi |
|---------|-----------|-----------|--------------|-------------|---------------|--------------|
| centurion_therapeutics_ivr | CENTURION_THERAPEUTICS_IVR | 2024-01-15T10:30:00 | John Doe | 1980-01-15 | (555) 123-4567 | 1234567890 |

## Error Handling

The tool provides detailed error reporting:

1. **Mapping Errors**: When fields cannot be mapped to canonical names
2. **Validation Errors**: When field values fail validation rules
3. **Transformation Errors**: When data transformation fails
4. **File Errors**: When input files cannot be read or parsed

## Performance Considerations

- **Batch Size**: Process up to 1000 forms per batch for optimal memory usage
- **File Size**: Individual form files should be < 10MB
- **Processing Time**: ~100ms per form on average hardware

## Troubleshooting

### Common Issues

1. **"No mapping found for form"**
   - Check that the form_id or form_name matches the Canal mappings
   - Use --verbose to see the fuzzy matching attempts

2. **"Invalid NPI format"**
   - Ensure NPI fields contain exactly 10 digits
   - Check for spaces or special characters

3. **"Failed to load mappings"**
   - Verify the mappings file path is correct
   - Check JSON syntax in the mappings file

### Debug Mode

Enable verbose logging for detailed debugging:

```bash
./map-forms form.json --dry-run --verbose
```

## Integration with Laravel

The tool can be integrated into the Laravel application:

```php
// Run from PHP
$output = shell_exec('./scripts/map-forms form.json --format json');
$result = json_decode($output, true);

// Process the canonical data
$canonicalData = $result['canonical_data'];
```

## Contributing

To extend the tool:

1. Add new transformation operations in `_apply_rule()`
2. Add new validation rules in `_validate_field()`
3. Extend the Form Recognizer parser for new formats
4. Add new output formats by extending the export methods

## License

This tool is part of the Medical Forms Processing System and follows the same license as the parent project.
