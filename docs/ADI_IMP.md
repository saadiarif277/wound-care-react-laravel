Excellent choice! Azure Document Intelligence is **PERFECT** for your IVR form extraction needs - much better than trying to use text-based models like davinci-002. Let me show you how to adapt this for your specific manufacturer IVR forms.

## 🎯 Enhanced IVR Form Extraction System

### Why This is the Right Approach:
- **Native PDF handling** - no pre-processing needed
- **Form field recognition** - specifically designed for forms like IVRs
- **Checkbox detection** - critical for your IVR forms
- **Table extraction** - handles structured data
- **Position awareness** - maintains layout context

Let me create a specialized implementation for your IVR forms:

```javascript
// IVR Form Extraction Service for MSC-MVP
const DocumentIntelligence = require("@azure-rest/ai-document-intelligence").default,
{ getLongRunningPoller, isUnexpected } = require("@azure-rest/ai-document-intelligence");

class IVRFormExtractor {
  constructor(endpoint, key) {
    this.client = DocumentIntelligence(endpoint, { key });
  }

  // Extract fields from manufacturer IVR form
  async extractIVRForm(formUrl, manufacturerId) {
    try {
      // Use prebuilt-document model for better form field extraction
      const initialResponse = await this.client
        .path("/documentModels/{modelId}:analyze", "prebuilt-document")
        .post({
          contentType: "application/json",
          body: {
            urlSource: formUrl
          },
        });

      if (isUnexpected(initialResponse)) {
        throw initialResponse.body.error;
      }

      const poller = getLongRunningPoller(this.client, initialResponse);
      const result = (await poller.pollUntilDone()).body.analyzeResult;

      // Process and structure the extracted data
      const extractedFields = this.processIVRFields(result);
      const mappingSuggestions = await this.generateMappingSuggestions(extractedFields, manufacturerId);

      return {
        rawExtraction: result,
        structuredFields: extractedFields,
        mappingSuggestions: mappingSuggestions,
        extractionMetadata: {
          pageCount: result.pages?.length || 0,
          tableCount: result.tables?.length || 0,
          fieldCount: extractedFields.length,
          extractedAt: new Date().toISOString()
        }
      };
    } catch (error) {
      console.error("IVR extraction failed:", error);
      throw error;
    }
  }

  // Process extracted data into structured IVR fields
  processIVRFields(analyzeResult) {
    const fields = [];
    const { keyValuePairs, tables, pages } = analyzeResult;

    // 1. Extract checkbox fields (your Q-code products)
    if (keyValuePairs) {
      keyValuePairs.forEach(kvp => {
        const key = this.getTextFromValue(kvp.key, analyzeResult.content);
        const value = kvp.value ? this.getTextFromValue(kvp.value, analyzeResult.content) : null;
        
        // Detect checkbox patterns
        if (key.includes("Check ") || key.includes("☐") || key.includes("☑")) {
          fields.push({
            fieldName: key.replace(/☐|☑|Check\s*/g, '').trim(),
            fieldType: 'checkbox',
            ivrOriginalText: key,
            extractedValue: value,
            confidence: kvp.confidence,
            category: this.categorizeField(key)
          });
        } else {
          fields.push({
            fieldName: key,
            fieldType: this.detectFieldType(key, value),
            ivrOriginalText: key,
            extractedValue: value,
            confidence: kvp.confidence,
            category: this.categorizeField(key)
          });
        }
      });
    }

    // 2. Extract table data (for NPI lists, etc.)
    if (tables) {
      tables.forEach((table, tableIndex) => {
        // Look for NPI tables
        if (this.isNPITable(table, analyzeResult.content)) {
          this.extractNPIFields(table, analyzeResult.content, fields);
        }
      });
    }

    return fields;
  }

  // Categorize fields for better mapping
  categorizeField(fieldName) {
    const categories = {
      'product': /Q\d{4}|Membrane|Amnio|Shield|maxx/i,
      'provider': /Physician|Doctor|Provider|NPI.*Physician|Specialty/i,
      'facility': /Facility|Hospital|Office|Center|POS|Place of Service/i,
      'patient': /Patient|DOB|Date of Birth|Address.*Patient/i,
      'insurance': /Insurance|Policy|Payer|Network|Primary|Secondary/i,
      'clinical': /Wound|ICD|Diagnosis|Size|Location|History/i,
      'billing': /Part A|Hospice|Global|Surgery|CPT/i,
      'contact': /Phone|Email|Fax|Contact/i,
      'authorization': /Permission|Prior Auth|Initiate/i
    };

    for (const [category, pattern] of Object.entries(categories)) {
      if (pattern.test(fieldName)) {
        return category;
      }
    }
    return 'other';
  }

  // Generate mapping suggestions using your existing data model
  async generateMappingSuggestions(extractedFields, manufacturerId) {
    const suggestions = [];

    for (const field of extractedFields) {
      const suggestion = {
        ivrFieldName: field.fieldName,
        fieldType: field.fieldType,
        category: field.category,
        suggestedMapping: null,
        mappingLogic: null,
        confidence: 0
      };

      // Product checkbox mappings
      if (field.category === 'product' && field.fieldType === 'checkbox') {
        const qCodeMatch = field.fieldName.match(/Q\d{4}/);
        if (qCodeMatch) {
          suggestion.suggestedMapping = null; // No direct mapping
          suggestion.mappingLogic = `checkIfProductSelected('${qCodeMatch[0]}')`;
          suggestion.confidence = 0.95;
        }
      }

      // Direct field mappings
      const directMappings = {
        'Physician Name': 'providers.first_name + " " + providers.last_name',
        'Physician Specialty': 'providers.credentials',
        'Facility Name': 'facilities.name',
        'Facility Address': 'facilities.shipping_address',
        'Patient Name': 'FHIR:Patient.name',
        'Patient DOB': 'FHIR:Patient.birthDate',
        'Primary Policy Number': 'orders.payer_id_submitted',
        'ICD-10 Codes': 'orders.primary_diagnosis_codes'
      };

      if (directMappings[field.fieldName]) {
        suggestion.suggestedMapping = directMappings[field.fieldName];
        suggestion.confidence = 0.9;
      }

      // NPI field mappings
      if (field.fieldName.includes('NPI')) {
        const npiNumber = field.fieldName.match(/\d+/)?.[0];
        if (field.fieldName.includes('Physician')) {
          suggestion.suggestedMapping = `providers[${npiNumber - 1}].individual_npi`;
          suggestion.mappingLogic = 'indexedProviderLookup';
          suggestion.confidence = 0.8;
        } else if (field.fieldName.includes('Facility')) {
          suggestion.suggestedMapping = `facilities.group_npi`;
          suggestion.confidence = 0.85;
        }
      }

      suggestions.push(suggestion);
    }

    return suggestions;
  }

  // Helper methods
  getTextFromValue(value, content) {
    if (value.content) {
      return value.content;
    } else if (value.spans && value.spans.length > 0) {
      return value.spans
        .map(span => content.slice(span.offset, span.offset + span.length))
        .join(' ');
    }
    return '';
  }

  detectFieldType(fieldName, value) {
    if (/date|DOB/i.test(fieldName)) return 'date';
    if (/phone|fax/i.test(fieldName)) return 'phone';
    if (/email/i.test(fieldName)) return 'email';
    if (/\$|cost|price|fee/i.test(fieldName)) return 'currency';
    if (/number|#|count|NPI|policy/i.test(fieldName)) return 'number';
    if (/yes|no/i.test(fieldName)) return 'boolean';
    return 'text';
  }

  isNPITable(table, content) {
    // Check if table headers contain NPI-related text
    if (table.cells.length > 0) {
      const firstRowText = table.cells
        .filter(cell => cell.rowIndex === 0)
        .map(cell => this.getCellText(cell, content))
        .join(' ');
      return /NPI|Provider|Physician/i.test(firstRowText);
    }
    return false;
  }

  extractNPIFields(table, content, fields) {
    // Group cells by row
    const rows = {};
    table.cells.forEach(cell => {
      if (!rows[cell.rowIndex]) rows[cell.rowIndex] = [];
      rows[cell.rowIndex].push(cell);
    });

    // Process each row (skip header)
    Object.entries(rows).forEach(([rowIndex, cells]) => {
      if (rowIndex > 0) {
        cells.forEach(cell => {
          const text = this.getCellText(cell, content);
          if (text && text.length > 0) {
            fields.push({
              fieldName: `Table_Row${rowIndex}_Col${cell.columnIndex}`,
              fieldType: 'text',
              ivrOriginalText: text,
              extractedValue: text,
              confidence: cell.confidence || 0.8,
              category: 'provider',
              tablePosition: { row: rowIndex, col: cell.columnIndex }
            });
          }
        });
      }
    });
  }

  getCellText(cell, content) {
    if (cell.content) return cell.content;
    if (cell.spans && cell.spans.length > 0) {
      return cell.spans
        .map(span => content.slice(span.offset, span.offset + span.length))
        .join(' ');
    }
    return '';
  }
}

// Integration with your order system
class IVRMappingService {
  constructor(formExtractor, supabaseClient) {
    this.formExtractor = formExtractor;
    this.supabase = supabaseClient;
  }

  // Process new manufacturer IVR form
  async processNewIVRForm(manufacturerId, formUrl) {
    // 1. Extract fields from PDF
    const extraction = await this.formExtractor.extractIVRForm(formUrl, manufacturerId);
    
    // 2. Save extraction results
    const { data: template, error } = await this.supabase
      .from('manufacturer_ivr_templates')
      .insert({
        manufacturer_id: manufacturerId,
        template_name: `Auto-extracted - ${new Date().toISOString()}`,
        version: '1.0',
        field_mappings: {
          extractedFields: extraction.structuredFields,
          mappingSuggestions: extraction.mappingSuggestions,
          unmappedFields: extraction.structuredFields.filter(f => 
            !extraction.mappingSuggestions.find(s => 
              s.ivrFieldName === f.fieldName && s.confidence > 0.7
            )
          )
        },
        conditional_fields: this.generateConditionalLogic(extraction.structuredFields),
        is_active: false, // Requires manual review
        extraction_metadata: extraction.extractionMetadata
      });

    return {
      templateId: template.id,
      summary: {
        totalFields: extraction.structuredFields.length,
        mappedFields: extraction.mappingSuggestions.filter(s => s.confidence > 0.7).length,
        unmappedFields: extraction.structuredFields.length - 
          extraction.mappingSuggestions.filter(s => s.confidence > 0.7).length,
        categories: this.summarizeByCategory(extraction.structuredFields)
      }
    };
  }

  // Generate conditional logic for dynamic fields
  generateConditionalLogic(fields) {
    const conditionals = [];

    // Find related checkbox/field pairs
    fields.forEach(field => {
      if (field.fieldType === 'checkbox') {
        // Look for related fields
        const relatedFields = fields.filter(f => 
          f.fieldName.includes(field.fieldName.replace('Check ', '')) &&
          f.fieldType !== 'checkbox'
        );
        
        if (relatedFields.length > 0) {
          conditionals.push({
            trigger: field.fieldName,
            condition: `${field.fieldName} === true`,
            showFields: relatedFields.map(f => f.fieldName),
            logic: 'checkbox_reveal'
          });
        }
      }
    });

    return conditionals;
  }

  summarizeByCategory(fields) {
    const summary = {};
    fields.forEach(field => {
      if (!summary[field.category]) {
        summary[field.category] = { count: 0, fields: [] };
      }
      summary[field.category].count++;
      summary[field.category].fields.push(field.fieldName);
    });
    return summary;
  }
}

// Usage in your order flow
async function enhanceOrderWithIVR(orderId, manufacturerId) {
  const extractor = new IVRFormExtractor(
    process.env.AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT,
    process.env.AZURE_DOCUMENT_INTELLIGENCE_KEY
  );
  
  const mappingService = new IVRMappingService(extractor, supabaseClient);
  
  // Get the active IVR template for this manufacturer
  const { data: template } = await supabaseClient
    .from('manufacturer_ivr_templates')
    .select('*')
    .eq('manufacturer_id', manufacturerId)
    .eq('is_active', true)
    .single();

  if (!template) {
    throw new Error('No active IVR template for manufacturer');
  }

  // Auto-populate fields based on mappings
  const ivrData = await populateIVRFromOrder(orderId, template.field_mappings);
  
  // Save IVR data
  await supabaseClient
    .from('order_ivr_data')
    .insert({
      order_id: orderId,
      manufacturer_id: manufacturerId,
      ivr_field_values: ivrData,
      mapping_completeness: calculateCompleteness(ivrData, template)
    });

  return ivrData;
}
```

## 🏗️ Complete Integration Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                 IVR FORM PROCESSING PIPELINE                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Upload New IVR Form                                         │
│     └─> Azure Blob Storage                                     │
│                                                                 │
│  2. Extract Fields                                              │
│     └─> Azure Document Intelligence                            │
│           ├─> Key-Value Pairs                                   │
│           ├─> Tables (NPI lists)                               │
│           └─> Checkboxes                                       │
│                                                                 │
│  3. Generate Mapping Template                                   │
│     └─> IVRMappingService                                      │
│           ├─> Auto-map obvious fields (90% confidence)         │
│           ├─> Suggest probable mappings (70-90%)               │
│           └─> Flag unmapped fields for review                  │
│                                                                 │
│  4. Admin Review & Approval                                     │
│     └─> Mapping Review UI                                      │
│           ├─> Confirm auto-mappings                            │
│           ├─> Adjust suggestions                               │
│           └─> Define conditional logic                         │
│                                                                 │
│  5. Runtime Usage                                               │
│     └─> Order Flow Integration                                 │
│           ├─> Load manufacturer template                       │
│           ├─> Auto-populate from order data                    │
│           ├─> Show unmapped fields                            │
│           └─> Generate completed IVR via DocuSeal              │
└─────────────────────────────────────────────────────────────────┘
```
