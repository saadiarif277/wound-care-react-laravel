// Order Form Fuzzy Matching System
// Handles various manufacturer order form formats with intelligent field extraction

import Fuse from 'fuse.js';

// Types for order form data
interface OrderFormData {
  manufacturer?: string;
  facilityName?: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
  shippingAddress?: string;
  billingAddress?: string;
  orderDate?: string;
  deliveryDate?: string;
  poNumber?: string;
  products?: ProductOrder[];
  specialInstructions?: string;
  salesRep?: string;
  npiNumber?: string;
  patientInfo?: PatientInfo;
  shippingMethod?: string;
  paymentTerms?: string;
  signatures?: SignatureInfo[];
}

interface ProductOrder {
  productName?: string;
  productCode?: string;
  size?: string;
  quantity?: number;
  unitPrice?: number;
  totalPrice?: number;
  hcpcsCode?: string;
}

interface PatientInfo {
  patientName?: string;
  patientId?: string;
  dateOfBirth?: string;
  dateOfService?: string;
}

interface SignatureInfo {
  signatureRequired: boolean;
  signedBy?: string;
  signedDate?: string;
}

// Field mapping configurations for different manufacturers
const MANUFACTURER_PATTERNS = {
  'MedLife Solutions': {
    identifiers: ['MEDLIFE', 'AmnioAMP-MP', 'medlifesol.com'],
    fieldMappings: {
      facilityName: ['Company/Facility'],
      contactName: ['Contact Name'],
      contactPhone: ['Contact Phone'],
      shippingAddress: ['Address'],
      products: {
        pattern: /AmnioAMP-MP.*?(\d+)\s*sq\s*cm.*?(\d+x\d+)\s*cm/g,
        fields: ['productName', 'size', 'dimensions']
      }
    }
  },
  'Extremity Care': {
    identifiers: ['ExtremityCare', 'Restorigin', 'completeFT', 'extremitycare.com'],
    fieldMappings: {
      facilityName: ['Facility Name'],
      contactName: ['Requesting Provider'],
      orderDate: ['Order Date'],
      patientInfo: {
        patientName: ['Patient Name/Case ID'],
        dateOfService: ['Date of Service']
      },
      npiNumber: ['NPI Number']
    }
  },
  'ACZ Distribution': {
    identifiers: ['ACZ DISTRIBUTION', 'ACZandAssociates.com'],
    fieldMappings: {
      facilityName: ['Account Name'],
      contactName: ['Contact Name'],
      contactEmail: ['Contact e-mail'],
      contactPhone: ['Contact Number'],
      orderDate: ['Date of Order'],
      deliveryDate: ['Anticipated Application Date'],
      poNumber: ['PO#'],
      patientInfo: {
        patientId: ['Patient ID']
      }
    }
  },
  'Advanced Solution': {
    identifiers: ['ADVANCED SOLUTION', 'AdvancedSolution.Health'],
    fieldMappings: {
      facilityName: ['Facility Name'],
      contactName: ['Shipping Contact Name', 'Billing Contact Name'],
      shippingAddress: ['Shipping Address'],
      orderDate: ['Date of Case'],
      deliveryDate: ['Product Arrival Date & Time'],
      poNumber: ['Purchase Order Number']
    }
  },
  'BioWound Solutions': {
    identifiers: ['BioWound Solutions', 'biowound.com'],
    fieldMappings: {
      poNumber: ['PO#'],
      orderDate: ['DATE'],
      salesRep: ['SALESPERSON'],
      contactEmail: ['CONTACT EMAIL'],
      contactPhone: ['CONTACT PHONE'],
      deliveryDate: ['REQUESTED DELIVERY DATE'],
      paymentTerms: ['NET TERMS']
    }
  },
  'Imbed Biosciences': {
    identifiers: ['Imbed', 'BIOSCIENCES', 'Microlyte'],
    fieldMappings: {
      facilityName: ['Facility Name'],
      shippingAddress: ['Address'],
      contactEmail: ['Email'],
      contactPhone: ['Phone'],
      billingAddress: ['Billing Address'],
      orderDate: ['Order Date']
    }
  },
  'Skye Biologics': {
    identifiers: ['SKYE', 'skyebiologics.com', 'WoundPlus'],
    fieldMappings: {
      facilityName: ['Facility name of where procedure will be performed'],
      contactName: ['Physician Name', 'Contact Name'],
      npiNumber: ['NPI'],
      patientInfo: {
        patientName: ['Patient Name'],
        dateOfBirth: ['Date of Birth'],
        dateOfService: ['Date of Application']
      },
      salesRep: ['Skye Sales Rep']
    }
  }
};

// Fuzzy matching utilities
class OrderFormFuzzyMatcher {
  private fuseOptions: Fuse.IFuseOptions<any> = {
    includeScore: true,
    threshold: 0.3,
    location: 0,
    distance: 100,
    minMatchCharLength: 2,
    keys: []
  };

  // Identify manufacturer from text
  identifyManufacturer(text: string): string | null {
    const normalizedText = text.toUpperCase();
    
    for (const [manufacturer, config] of Object.entries(MANUFACTURER_PATTERNS)) {
      const identifiers = config.identifiers.map(id => id.toUpperCase());
      if (identifiers.some(id => normalizedText.includes(id))) {
        return manufacturer;
      }
    }
    
    return null;
  }

  // Extract field value using fuzzy matching
  extractFieldValue(
    text: string,
    fieldNames: string[],
    options: { multiline?: boolean; numeric?: boolean } = {}
  ): string | null {
    const lines = text.split('\n');
    
    for (const fieldName of fieldNames) {
      // Create fuzzy matcher for field name
      const fuse = new Fuse([fieldName], {
        ...this.fuseOptions,
        threshold: 0.4
      });
      
      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const words = line.split(/\s+/);
        
        // Check each word sequence for match
        for (let j = 0; j < words.length; j++) {
          const phrase = words.slice(j, j + fieldName.split(/\s+/).length).join(' ');
          const result = fuse.search(phrase);
          
          if (result.length > 0 && result[0].score! < 0.4) {
            // Found a match, extract the value
            let value = '';
            
            // Check same line after the field name
            const remainingLine = words.slice(j + fieldName.split(/\s+/).length).join(' ').trim();
            if (remainingLine && remainingLine !== ':') {
              value = remainingLine.replace(/^:?\s*/, '');
            }
            
            // Check next line if multiline or no value found
            if ((!value || options.multiline) && i + 1 < lines.length) {
              const nextLine = lines[i + 1].trim();
              if (nextLine && !this.looksLikeFieldName(nextLine)) {
                value = value ? `${value} ${nextLine}` : nextLine;
              }
            }
            
            if (value) {
              return this.cleanFieldValue(value, options);
            }
          }
        }
      }
    }
    
    return null;
  }

  // Check if a line looks like a field name
  private looksLikeFieldName(line: string): boolean {
    const fieldIndicators = [':', 'Name', 'Date', 'Phone', 'Email', 'Address', 'Number'];
    return fieldIndicators.some(indicator => line.includes(indicator));
  }

  // Clean extracted field value
  private cleanFieldValue(value: string, options: { numeric?: boolean } = {}): string {
    // Remove common separators and clean up
    value = value.replace(/[\|\t]+/g, ' ').trim();
    value = value.replace(/\s+/g, ' ');
    value = value.replace(/^[:_-]+\s*/, '');
    
    if (options.numeric) {
      // Extract numeric value
      const match = value.match(/[\d,]+\.?\d*/);
      return match ? match[0].replace(/,/g, '') : value;
    }
    
    return value;
  }

  // Extract product information
  extractProducts(text: string, manufacturer: string): ProductOrder[] {
    const products: ProductOrder[] = [];
    const config = MANUFACTURER_PATTERNS[manufacturer as keyof typeof MANUFACTURER_PATTERNS];
    
    if (!config) return products;
    
    // Common product patterns
    const productPatterns = [
      // Pattern: Product Name | Size | Quantity | Price
      /([A-Za-z\s\-™®]+)\s*\|\s*(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm)?)\s*\|\s*(\d+)\s*\|\s*\$?([\d,]+(?:\.\d+)?)/g,
      // Pattern: SKU Product Description Size Units Price
      /([A-Z\-\d]+)\s+([A-Za-z\s\-™®]+)\s+(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm)?)\s+(\d+)\s+\$?([\d,]+(?:\.\d+)?)/g,
      // Pattern for tables with separate columns
      /(\w+(?:\s+\w+)*)\s+(\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:\s*cm|mm|sq\s*cm)?)\s+.*?\s+(\d+)\s+.*?\$?([\d,]+(?:\.\d+)?)/g
    ];
    
    for (const pattern of productPatterns) {
      let match;
      while ((match = pattern.exec(text)) !== null) {
        const product: ProductOrder = {
          productCode: match[1].includes('-') ? match[1] : undefined,
          productName: match[2] || match[1],
          size: match[3] || match[2],
          quantity: parseInt(match[4] || match[3]) || 0,
          unitPrice: parseFloat((match[5] || match[4] || '0').replace(/,/g, ''))
        };
        
        if (product.quantity && product.unitPrice) {
          product.totalPrice = product.quantity * product.unitPrice;
        }
        
        products.push(product);
      }
    }
    
    return products;
  }

  // Extract phone number with normalization
  extractPhoneNumber(text: string, fieldNames: string[]): string | null {
    const value = this.extractFieldValue(text, fieldNames);
    if (!value) return null;
    
    // Normalize phone number
    const digits = value.replace(/\D/g, '');
    if (digits.length === 10) {
      return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    } else if (digits.length === 11 && digits[0] === '1') {
      return `(${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7)}`;
    }
    
    return value;
  }

  // Extract date with normalization
  extractDate(text: string, fieldNames: string[]): string | null {
    const value = this.extractFieldValue(text, fieldNames);
    if (!value) return null;
    
    // Common date patterns
    const datePatterns = [
      /(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/,
      /(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/,
      /(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\s+(\d{1,2}),?\s+(\d{4})/i
    ];
    
    for (const pattern of datePatterns) {
      const match = value.match(pattern);
      if (match) {
        // Normalize to YYYY-MM-DD format
        // Implementation depends on the matched pattern
        return value; // Simplified for example
      }
    }
    
    return value;
  }

  // Main extraction method
  extractOrderFormData(text: string): OrderFormData {
    const data: OrderFormData = {};
    
    // Identify manufacturer
    const manufacturer = this.identifyManufacturer(text);
    data.manufacturer = manufacturer || 'Unknown';
    
    if (manufacturer && MANUFACTURER_PATTERNS[manufacturer as keyof typeof MANUFACTURER_PATTERNS]) {
      const config = MANUFACTURER_PATTERNS[manufacturer as keyof typeof MANUFACTURER_PATTERNS];
      const mappings = config.fieldMappings;
      
      // Extract simple fields
      if (mappings.facilityName) {
        data.facilityName = this.extractFieldValue(text, mappings.facilityName as string[]);
      }
      
      if (mappings.contactName) {
        data.contactName = this.extractFieldValue(text, mappings.contactName as string[]);
      }
      
      if (mappings.contactPhone) {
        data.contactPhone = this.extractPhoneNumber(text, mappings.contactPhone as string[]);
      }
      
      if (mappings.contactEmail) {
        data.contactEmail = this.extractFieldValue(text, mappings.contactEmail as string[]);
      }
      
      if (mappings.orderDate) {
        data.orderDate = this.extractDate(text, mappings.orderDate as string[]);
      }
      
      // Extract patient info if present
      if (mappings.patientInfo && typeof mappings.patientInfo === 'object') {
        data.patientInfo = {};
        const patientMappings = mappings.patientInfo as any;
        
        if (patientMappings.patientName) {
          data.patientInfo.patientName = this.extractFieldValue(text, patientMappings.patientName);
        }
        
        if (patientMappings.dateOfBirth) {
          data.patientInfo.dateOfBirth = this.extractDate(text, patientMappings.dateOfBirth);
        }
      }
    }
    
    // Extract products
    data.products = this.extractProducts(text, manufacturer || '');
    
    // Extract common fields using general patterns
    if (!data.poNumber) {
      data.poNumber = this.extractFieldValue(text, ['PO#', 'PO Number', 'Purchase Order', 'Order Number']);
    }
    
    if (!data.shippingAddress) {
      data.shippingAddress = this.extractFieldValue(text, ['Shipping Address', 'Ship to Address', 'Address'], { multiline: true });
    }
    
    if (!data.specialInstructions) {
      data.specialInstructions = this.extractFieldValue(text, ['Notes', 'Special Instructions', 'Comments'], { multiline: true });
    }
    
    return data;
  }

  // Calculate similarity score between two order forms
  calculateSimilarity(form1: OrderFormData, form2: OrderFormData): number {
    let score = 0;
    let totalFields = 0;
    
    // Compare string fields
    const stringFields: (keyof OrderFormData)[] = [
      'facilityName', 'contactName', 'contactEmail', 'shippingAddress'
    ];
    
    for (const field of stringFields) {
      if (form1[field] && form2[field]) {
        totalFields++;
        const fuse = new Fuse([form1[field] as string], this.fuseOptions);
        const result = fuse.search(form2[field] as string);
        if (result.length > 0) {
          score += 1 - (result[0].score || 0);
        }
      }
    }
    
    // Compare products
    if (form1.products && form2.products) {
      totalFields++;
      const productScore = this.compareProducts(form1.products, form2.products);
      score += productScore;
    }
    
    return totalFields > 0 ? score / totalFields : 0;
  }

  // Compare product lists
  private compareProducts(products1: ProductOrder[], products2: ProductOrder[]): number {
    if (products1.length === 0 || products2.length === 0) return 0;
    
    let matchScore = 0;
    const maxLength = Math.max(products1.length, products2.length);
    
    for (const product1 of products1) {
      let bestMatch = 0;
      
      for (const product2 of products2) {
        let productScore = 0;
        let fields = 0;
        
        // Compare product fields
        if (product1.productName && product2.productName) {
          fields++;
          const fuse = new Fuse([product1.productName], this.fuseOptions);
          const result = fuse.search(product2.productName);
          if (result.length > 0) {
            productScore += 1 - (result[0].score || 0);
          }
        }
        
        if (product1.size === product2.size) {
          fields++;
          productScore += 1;
        }
        
        if (product1.quantity === product2.quantity) {
          fields++;
          productScore += 1;
        }
        
        if (fields > 0) {
          bestMatch = Math.max(bestMatch, productScore / fields);
        }
      }
      
      matchScore += bestMatch;
    }
    
    return matchScore / maxLength;
  }
}

// Usage example
const matcher = new OrderFormFuzzyMatcher();

// Example: Extract data from an order form
const orderFormText = `
EXTREMITY CARE
Order Form
Requesting Provider: Dr. John Smith
Facility Name: Advanced Wound Care Center
Order Date: 03/15/2024
Patient Name/Case ID: Jane Doe / WC-2024-001

Restorigin™ Amnion Patch, Thin 2x2cm 4 units $3,760.61
Restorigin™ Amnion Patch, Thin 4x4cm 16 units $15,042.43
`;

const extractedData = matcher.extractOrderFormData(orderFormText);
console.log('Extracted Data:', extractedData);

// Example: Compare two order forms
const form1 = matcher.extractOrderFormData(orderFormText);
const form2 = matcher.extractOrderFormData(`
ExtremityCare LLC
Requesting Provider: Dr. J Smith
Facility Name: Advanced Wound Center
Order Date: 2024-03-15
`);

const similarity = matcher.calculateSimilarity(form1, form2);
console.log('Similarity Score:', similarity);

// Export for use in other modules
export { OrderFormFuzzyMatcher, OrderFormData, ProductOrder, PatientInfo };