# IVR Field Discovery Enhancement Roadmap

## Phase 1: Immediate Enhancements (1-2 days)

### 1. Pattern-Based Bulk Mapping

**Problem**: Manually mapping "Physician NPI 1" through "Physician NPI 7" is tedious
**Solution**: Add pattern recognition and bulk mapping

```typescript
// Add to Templates.tsx
const applyPatternMapping = () => {
  // Detect patterns like "Physician NPI {n}"
  const patterns = detectFieldPatterns(fieldSuggestions);
  
  // Apply mapping to all matching fields
  patterns.forEach(pattern => {
    if (pattern.type === 'indexed') {
      // Map Physician NPI 1 → provider_npi_1, etc.
      applyIndexedMapping(pattern);
    }
  });
};
```

### 2. Smart Product Checkbox Handling

**Problem**: Product checkboxes (Q-codes) need special handling
**Solution**: Create product selection logic

```php
// In IvrFieldMappingService
private function mapProductCheckboxes($productRequest, $extractedFields) {
    $selectedProducts = $productRequest->products->pluck('hcpcs_code')->toArray();
    $checkboxValues = [];
    
    foreach ($extractedFields as $field) {
        if ($field['is_checkbox'] && preg_match('/Q\d{4}/', $field['field_name'], $matches)) {
            $qCode = $matches[0];
            $checkboxValues[$field['field_name']] = in_array($qCode, $selectedProducts);
        }
    }
    
    return $checkboxValues;
}
```

### 3. Field Grouping & Organization

**Problem**: 70+ fields are overwhelming in a flat list
**Solution**: Group fields by category with collapsible sections

```typescript
// Enhanced UI with grouped fields
<div className="space-y-4">
  {Object.entries(groupFieldsByCategory(fieldSuggestions)).map(([category, fields]) => (
    <CollapsibleSection key={category} title={category} count={fields.length}>
      <FieldMappingTable fields={fields} />
    </CollapsibleSection>
  ))}
</div>
```

## Phase 2: Advanced Features (3-5 days)

### 4. Template Versioning & History

**Problem**: IVR forms change over time
**Solution**: Track versions and compare changes

```sql
CREATE TABLE ivr_template_versions (
    id UUID PRIMARY KEY,
    template_id UUID REFERENCES docuseal_templates(id),
    version_number INT,
    field_mappings JSONB,
    extracted_fields JSONB,
    changed_fields JSONB,
    created_at TIMESTAMP
);
```

### 5. Conditional Field Logic

**Problem**: Some fields only appear based on other field values
**Solution**: Define conditional rules

```javascript
// Example: Show SNF fields only if "Is patient in SNF?" is checked
const conditionalRules = {
  "snf_days": {
    showWhen: { field: "snf_status", value: true }
  },
  "snf_over_100_days": {
    showWhen: { field: "snf_days", operator: ">", value: 100 }
  }
};
```

### 6. Field Validation Rules

**Problem**: Need to ensure data quality
**Solution**: Add validation per field type

```php
private function validateFieldValue($fieldName, $value, $fieldType) {
    switch ($fieldType) {
        case 'npi':
            return preg_match('/^\d{10}$/', $value);
        case 'date':
            return Carbon::parse($value)->isValid();
        case 'phone':
            return preg_match('/^\d{3}-\d{3}-\d{4}$/', $value);
        case 'zip':
            return preg_match('/^\d{5}(-\d{4})?$/', $value);
    }
}
```

## Phase 3: Intelligence Layer (1 week)

### 7. Machine Learning Field Matching

**Problem**: Fuzzy matching isn't always accurate
**Solution**: Train a model on successful mappings

```python
# Training data from successful mappings
training_data = [
    {"ivr_field": "Physician Name", "system_field": "provider_name", "confidence": 0.95},
    {"ivr_field": "MD Name", "system_field": "provider_name", "confidence": 0.90},
    # ... more examples
]

# Use similarity scoring with learned weights
def predict_mapping(ivr_field_name, context):
    features = extract_features(ivr_field_name, context)
    return model.predict(features)
```

### 8. Cross-Manufacturer Intelligence

**Problem**: Same data, different field names across manufacturers
**Solution**: Build a unified mapping database

```sql
CREATE TABLE field_synonyms (
    id UUID PRIMARY KEY,
    canonical_field VARCHAR(255),
    manufacturer_id UUID,
    manufacturer_field_name VARCHAR(255),
    confidence DECIMAL(3,2)
);

-- Example data
INSERT INTO field_synonyms VALUES
('Provider NPI', 'ACZ', 'Physician NPI', 0.95),
('Provider NPI', 'BioWound', 'NPI (Physician)', 0.95),
('Provider NPI', 'Extremity', 'Provider NPI', 1.00);
```

### 9. Auto-Learning from Corrections

**Problem**: Manual corrections aren't captured for future use
**Solution**: Learn from user adjustments

```php
public function recordMappingCorrection($template, $field, $oldMapping, $newMapping) {
    MappingCorrection::create([
        'template_id' => $template->id,
        'field_name' => $field,
        'auto_suggested' => $oldMapping,
        'user_corrected' => $newMapping,
        'user_id' => auth()->id()
    ]);
    
    // Update confidence scores based on corrections
    $this->updateConfidenceScores($field, $oldMapping, $newMapping);
}
```

## Phase 4: Workflow Automation (1 week)

### 10. Bulk IVR Processing

**Problem**: Processing one IVR at a time is slow
**Solution**: Queue-based bulk processing

```php
// Process multiple product requests
public function processBulkIvrGeneration($productRequestIds) {
    foreach ($productRequestIds as $id) {
        GenerateIvrJob::dispatch($id)->onQueue('ivr-generation');
    }
}
```

### 11. Pre-Flight Validation

**Problem**: Errors discovered after submission
**Solution**: Validate before generating IVR

```typescript
const validateIvrData = async (productRequest) => {
  const validation = await axios.post('/api/ivr/validate', {
    product_request_id: productRequest.id,
    manufacturer_id: productRequest.manufacturer_id
  });
  
  return {
    isValid: validation.data.valid,
    errors: validation.data.errors,
    warnings: validation.data.warnings,
    missingFields: validation.data.missing_fields
  };
};
```

### 12. Smart Defaults & Auto-Population

**Problem**: Repetitive data entry
**Solution**: Learn common patterns and suggest defaults

```php
public function suggestDefaults($provider, $facility, $productType) {
    // Analyze historical data
    $commonValues = DB::table('product_requests')
        ->where('provider_id', $provider->id)
        ->where('facility_id', $facility->id)
        ->where('product_type', $productType)
        ->select(DB::raw('
            MODE() WITHIN GROUP (ORDER BY place_of_service) as common_pos,
            MODE() WITHIN GROUP (ORDER BY wound_type) as common_wound_type,
            AVG(wound_size_total) as avg_wound_size
        '))
        ->first();
    
    return [
        'place_of_service' => $commonValues->common_pos,
        'wound_type' => $commonValues->common_wound_type,
        'estimated_wound_size' => $commonValues->avg_wound_size
    ];
}
```

## Implementation Priority

### Quick Wins (Do First)

1. **Pattern-based bulk mapping** - Huge time saver
2. **Field grouping UI** - Better user experience
3. **Product checkbox handling** - Critical for accuracy

### High Impact (Do Next)

4. **Conditional field logic** - Reduces errors
5. **Pre-flight validation** - Catches issues early
6. **Template versioning** - Handles form changes

### Long Term (Plan For)

7. **ML field matching** - Improves over time
8. **Cross-manufacturer intelligence** - Scales better
9. **Auto-learning** - Gets smarter with use

## Success Metrics

- Time to map new manufacturer: <5 minutes (from 30+ minutes)
- Field mapping accuracy: >95% (from 80%)
- IVR rejection rate: <5% (from 15%)
- User satisfaction: Track corrections needed per IVR

## Next Steps

1. Start with pattern-based bulk mapping
2. Implement field grouping in the UI
3. Add product checkbox logic
4. Set up analytics to track improvement

Would you like me to implement any of these enhancements right now?
