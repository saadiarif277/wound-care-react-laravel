# Order Form Fuzzy Matching System Documentation

## Overview

The Order Form Fuzzy Matching System is a sophisticated solution designed to automatically extract and parse data from various manufacturer order forms in the wound care industry. It handles different formats, layouts, and naming conventions across multiple manufacturers, providing a unified interface for order processing.

## Key Features

- **Multi-Manufacturer Support**: Recognizes and processes order forms from 7+ major manufacturers
- **Intelligent Field Extraction**: Uses fuzzy matching algorithms to extract data even with variations in field names
- **Product Recognition**: Automatically identifies and extracts product information including sizes, quantities, and prices
- **Confidence Scoring**: Provides confidence levels for manufacturer identification and data extraction
- **Format Flexibility**: Handles text, OCR output, and structured/unstructured data
- **Normalization**: Automatically normalizes phone numbers, dates, and addresses
- **Validation**: Built-in validation and warning system for missing or suspicious data

## Supported Manufacturers

### Currently Supported

1. **MedLife Solutions LLC**
   - Products: AmnioAMP-MP
   - Identifiers: MEDLIFE, medlifesol.com
   - Order format: Table-based with size grid

2. **Extremity Care**
   - Products: Restorigin™, completeFT™
   - Identifiers: ExtremityCare, Q4191, Q4271
   - Order format: SKU-based product listing

3. **ACZ Distribution**
   - Identifiers: ACZ DISTRIBUTION, ACZandAssociates.com
   - Order format: Generic table format

4. **Advanced Solution**
   - Identifiers: ADVANCED SOLUTION, AdvancedSolution.Health
   - Order format: Shipping/billing split format

5. **BioWound Solutions Inc**
   - Identifiers: BioWound Solutions, biowound.com
   - Order format: Purchase order style

6. **Imbed Biosciences**
   - Products: Microlyte
   - Identifiers: Imbed, BIOSCIENCES
   - Order format: Size-based pricing table

7. **Skye Biologics**
   - Products: WoundPlus™ (Q4277)
   - Identifiers: SKYE, skyebiologics.com
   - Order format: Insurance verification form

## Technical Architecture

### TypeScript/JavaScript Implementation

```typescript
import { OrderFormFuzzyMatcher } from './order-form-fuzzy-matcher';

const matcher = new OrderFormFuzzyMatcher();
const result = matcher.extractOrderFormData(orderFormText);
```

### PHP/Laravel Implementation

```php
use App\Services\OrderFormFuzzyMatchingService;

$service = new OrderFormFuzzyMatchingService();
$result = $service->extractOrderFormData($orderFormText);
```

### Data Structure

```typescript
interface OrderFormData {
  manufacturer?: string;           // Identified manufacturer name
  confidence_score?: number;       // 0-100 confidence in identification
  extracted_fields?: {
    facility_name?: string;
    contact_name?: string;
    contact_phone?: string;
    contact_email?: string;
    shipping_address?: string;
    billing_address?: string;
    order_date?: string;
    delivery_date?: string;
    po_number?: string;
    // ... additional fields
  };
  products?: ProductOrder[];       // Extracted product list
  warnings?: string[];            // Any extraction warnings
}
```

## Integration Guide

### 1. Frontend Integration (React)

```jsx
import OrderFormFuzzyMatcher from '@/components/OrderFormFuzzyMatcher';

function OrderProcessing() {
  const handleExtractedData = (data) => {
    // Process extracted data
    console.log('Manufacturer:', data.manufacturer);
    console.log('Products:', data.products);
    
    // Create order in system
    createOrder(data);
  };

  return (
    <OrderFormFuzzyMatcher 
      onDataExtracted={handleExtractedData}
      allowedManufacturers={['Extremity Care', 'MedLife Solutions']}
    />
  );
}
```

### 2. Backend Integration (Laravel)

```php
// Controller method
public function processOrderForm(Request $request)
{
    $request->validate([
        'order_text' => 'required|string',
        'file' => 'nullable|file|mimes:txt,pdf'
    ]);

    $text = $request->order_text;
    
    // If file uploaded, extract text
    if ($request->hasFile('file')) {
        $text = $this->extractTextFromFile($request->file('file'));
    }

    $service = new OrderFormFuzzyMatchingService();
    $extractedData = $service->extractOrderFormData($text);

    // Map to order model
    $orderData = $service->mapToOrderData($extractedData);

    // Create order
    $order = Order::create($orderData);

    // Create line items
    foreach ($orderData['line_items'] as $item) {
        $order->lineItems()->create($item);
    }

    return response()->json([
        'order_id' => $order->id,
        'extracted_data' => $extractedData,
        'confidence' => $extractedData['confidence_score']
    ]);
}
```

### 3. API Endpoint

```php
// routes/api.php
Route::post('/api/orders/extract', 'OrderController@extractOrderForm');

// Request
POST /api/orders/extract
Content-Type: application/json
{
  "order_text": "ExtremityCare Order Form...",
  "validate_only": false
}

// Response
{
  "manufacturer": "Extremity Care",
  "confidence_score": 95,
  "extracted_fields": {
    "facility_name": "Advanced Wound Care Center",
    "contact_name": "Dr. John Smith"
  },
  "products": [
    {
      "name": "Restorigin™ Amnion Patch",
      "size": "2x2cm",
      "quantity": 4,
      "unit_price": 940.15
    }
  ]
}
```

## Advanced Features

### 1. Custom Field Mappings

Add custom field mappings for specific manufacturers:

```typescript
const customMappings = {
  'CustomManufacturer': {
    identifiers: ['CUSTOM', 'custom.com'],
    fieldMappings: {
      facility_name: ['Hospital Name', 'Medical Center'],
      custom_field: ['Special Field Name']
    }
  }
};

matcher.addManufacturerMappings(customMappings);
```

### 2. Confidence Thresholds

Adjust confidence thresholds for different use cases:

```typescript
const options = {
  minConfidence: 70,        // Minimum confidence to accept
  fuzzyThreshold: 0.3,      // Fuzzy matching threshold (0-1)
  requireManufacturer: true // Reject if manufacturer unknown
};

const result = matcher.extractOrderFormData(text, options);
```

### 3. Field Validation

Add custom validation rules:

```php
$service->addValidationRule('facility_name', function($value) {
    return strlen($value) >= 3 && strlen($value) <= 100;
});

$service->addValidationRule('po_number', function($value) {
    return preg_match('/^PO-\d{4}-\d{3}$/', $value);
});
```

### 4. Product Matching

Match extracted products to your product catalog:

```php
$productMatcher = new ProductMatcher($productCatalog);

foreach ($extractedData['products'] as $extractedProduct) {
    $matchedProduct = $productMatcher->findBestMatch(
        $extractedProduct['name'],
        $extractedProduct['size']
    );
    
    if ($matchedProduct && $matchedProduct->confidence > 0.8) {
        $extractedProduct['product_id'] = $matchedProduct->id;
        $extractedProduct['verified_price'] = $matchedProduct->price;
    }
}
```

## Performance Optimization

### 1. Caching

Cache manufacturer patterns and field mappings:

```php
Cache::remember('manufacturer_patterns', 3600, function () {
    return ManufacturerPattern::all()->keyBy('name')->toArray();
});
```

### 2. Batch Processing

Process multiple order forms efficiently:

```typescript
const batchProcessor = new BatchOrderProcessor(matcher);
const results = await batchProcessor.processOrders(orderForms, {
  concurrency: 5,
  timeout: 30000
});
```

### 3. Text Preprocessing

Optimize text before processing:

```typescript
function preprocessOrderText(text: string): string {
  // Remove excessive whitespace
  text = text.replace(/\s+/g, ' ');
  
  // Fix common OCR errors
  text = text.replace(/\bl\b/g, '1');  // l -> 1
  text = text.replace(/\bO\b/g, '0');  // O -> 0
  
  // Normalize line endings
  text = text.replace(/\r\n/g, '\n');
  
  return text;
}
```

## Error Handling

### Common Issues and Solutions

1. **Low Confidence Scores**
   - Check for OCR quality issues
   - Verify manufacturer identifiers are present
   - Ensure text formatting is preserved

2. **Missing Fields**
   - Some manufacturers may not include all fields
   - Use default values where appropriate
   - Flag for manual review if critical fields missing

3. **Product Extraction Failures**
   - Check for non-standard product formats
   - Add manufacturer-specific patterns
   - Consider manual product entry fallback

### Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "EXTRACTION_FAILED",
    "message": "Unable to identify manufacturer",
    "details": {
      "confidence_scores": {
        "MedLife Solutions": 15,
        "Extremity Care": 20
      },
      "warnings": [
        "No manufacturer identifiers found",
        "Product format unrecognized"
      ]
    }
  }
}
```

## Testing

### Unit Tests

```typescript
describe('Order Form Extraction', () => {
  test('should extract Extremity Care order', () => {
    const orderText = loadTestFile('extremity-care-order.txt');
    const result = matcher.extractOrderFormData(orderText);
    
    expect(result.manufacturer).toBe('Extremity Care');
    expect(result.confidence_score).toBeGreaterThan(80);
    expect(result.products).toHaveLength(2);
  });
});
```

### Integration Tests

```php
public function testOrderFormProcessing()
{
    $response = $this->post('/api/orders/extract', [
        'order_text' => file_get_contents(__DIR__ . '/fixtures/order.txt')
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'manufacturer',
            'confidence_score',
            'extracted_fields',
            'products'
        ]);
}
```

## Monitoring and Analytics

### Key Metrics to Track

1. **Extraction Success Rate**
   - Percentage of successful extractions by manufacturer
   - Average confidence scores

2. **Field Coverage**
   - Which fields are most commonly missing
   - Field extraction accuracy

3. **Performance Metrics**
   - Average processing time
   - Memory usage
   - Error rates

### Logging

```php
Log::channel('order_extraction')->info('Order form processed', [
    'manufacturer' => $result['manufacturer'],
    'confidence' => $result['confidence_score'],
    'field_count' => count($result['extracted_fields']),
    'product_count' => count($result['products']),
    'processing_time' => $endTime - $startTime
]);
```

## Future Enhancements

### Planned Features

1. **Machine Learning Integration**
   - Train models on successful extractions
   - Improve accuracy over time
   - Handle new formats automatically

2. **PDF Native Support**
   - Direct PDF parsing without OCR
   - Better layout preservation
   - Image-based order form support

3. **Real-time Validation**
   - Validate against manufacturer catalogs
   - Price verification
   - Inventory checking

4. **Multi-language Support**
   - Spanish language order forms
   - Automatic language detection
   - Translation integration

## Support and Maintenance

### Adding New Manufacturers

1. Identify unique identifiers (company name, logo text, website)
2. Collect sample order forms
3. Map field names to standard fields
4. Add product patterns
5. Test with multiple samples
6. Deploy and monitor

### Troubleshooting Guide

| Issue | Possible Cause | Solution |
|-------|----------------|----------|
| Manufacturer not identified | Missing identifiers | Add more identifier patterns |
| Fields not extracted | Different field names | Update field mappings |
| Products missing | New format | Add product patterns |
| Low confidence | Poor text quality | Improve OCR/preprocessing |

## Conclusion

The Order Form Fuzzy Matching System provides a robust solution for automating order form processing across multiple manufacturers. With proper configuration and monitoring, it can significantly reduce manual data entry while maintaining high accuracy and providing valuable insights into order patterns.