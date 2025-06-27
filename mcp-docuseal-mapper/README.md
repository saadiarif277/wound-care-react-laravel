# DocuSeal Mapper MCP Server

An MCP (Model Context Protocol) server that helps map QuickRequest form data to DocuSeal templates for various wound care manufacturers.

## Features

- **Manufacturer Configuration**: Get configuration details for each manufacturer including template IDs and field mappings
- **Form Data Mapping**: Automatically map QuickRequest form fields to DocuSeal template fields
- **Validation**: Validate that all required fields are present before submission
- **IVR Form Paths**: Locate IVR form PDFs for each manufacturer
- **Product Mapping**: Map product codes to their respective manufacturers

## Installation

1. Navigate to the MCP server directory:
```bash
cd mcp-docuseal-mapper
```

2. Install dependencies:
```bash
npm install
```

3. Make the script executable (Linux/Mac):
```bash
chmod +x src/index.js
```

## Setup with Claude Code

1. Add the server to Claude Code:
```bash
claude mcp add docuseal-mapper ./mcp-docuseal-mapper/src/index.js
```

2. Verify the server is configured:
```bash
claude mcp list
```

## Available Tools

### 1. `get_manufacturer_config`
Get manufacturer configuration by name or product code.

**Input:**
- `identifier` (string): Manufacturer name or product code (e.g., "ACZ" or "EMP001")

**Example:**
```json
{
  "identifier": "ACZ"
}
```

### 2. `list_manufacturers`
List all available manufacturers and their configurations.

**No input required**

Returns a summary of all manufacturers with:
- Name
- ID
- Template ID
- Signature requirement status
- Order form availability
- Number of mapped fields

### 3. `map_form_data`
Map QuickRequest form data to DocuSeal template fields.

**Input:**
- `manufacturer` (string): Manufacturer name
- `formData` (object): Form data from QuickRequest

**Example:**
```json
{
  "manufacturer": "ACZ",
  "formData": {
    "patient_first_name": "John",
    "patient_last_name": "Doe",
    "patient_dob": "1980-01-01",
    "primary_insurance_name": "Medicare",
    "primary_member_id": "123456789"
  }
}
```

### 4. `validate_mapping`
Validate if all required fields are mapped for a manufacturer.

**Input:**
- `manufacturer` (string): Manufacturer name
- `formData` (object): Form data to validate

Returns validation status and any missing required fields.

### 5. `get_ivr_form_path`
Get the IVR form PDF path for a manufacturer.

**Input:**
- `manufacturer` (string): Manufacturer name

Returns the relative path to the IVR form PDF.

## Supported Manufacturers

- ACZ (Template ID: 852440)
- Advanced Health
- MedLife
- Centurion Therapeutics
- BioWerX
- BioWound
- Extremity Care
- SKYE Biologics
- Total Ancillary

## Field Mapping Logic

The server supports several field mapping patterns:

1. **Direct Mapping**: `patient_name` → `patient_first_name`
2. **Concatenation**: `patient_name` → `patient_first_name + patient_last_name`
3. **Calculation**: `wound_size` → `wound_size_length * wound_size_width`
4. **Fallback**: `diagnosis_code` → `primary_diagnosis_code || diagnosis_code`

## Development

To run in development mode with auto-reload:
```bash
npm run dev
```

## Troubleshooting

1. **Server not starting**: Ensure Node.js 18+ is installed
2. **Permission denied**: Make sure the script is executable
3. **MCP connection failed**: Check that the path to the server is correct

## Example Usage in Claude Code

Once configured, you can use the tools in Claude Code:

```
// Get manufacturer config for a product
Tool: get_manufacturer_config
Input: { "identifier": "EMP001" }

// Map form data for DocuSeal
Tool: map_form_data
Input: {
  "manufacturer": "ACZ",
  "formData": { ...your form data... }
}

// Validate before submission
Tool: validate_mapping
Input: {
  "manufacturer": "ACZ",
  "formData": { ...your form data... }
}
```