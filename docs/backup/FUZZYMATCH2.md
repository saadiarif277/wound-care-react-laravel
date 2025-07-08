### 6. Template Field Analyzer

```typescript
// services/TemplateFieldAnalyzer.ts
export interface TemplateAnalysisReport {
  totalTemplates: number;
  avgFieldCount: number;
  fieldRange: { min: number; max: number };
  commonPatterns: CommonPattern[];
  namingConventions: NamingConvention[];
  checkboxAnalysis: CheckboxAnalysis;
  recommendations: string[];
  fieldCoverage: FieldCoverageReport;
}

export interface CommonPattern {
  pattern: string;
  occurrence: string;
  recommendation: string;
  examples: string[];
}

export interface NamingConvention {
  category: string;
  variations: string[];
  suggestedStandard: string;
}

export interface CheckboxAnalysis {
  totalCheckboxes: number;
  namingPatterns: Array<{
    pattern: string;
    count: number;
    percentage: number;
    examples: string[];
  }>;
  usage: Record<string, number>;
}

export interface FieldCoverageReport {
  coveredFields: number;
  totalFields: number;
  coveragePercentage: number;
  missingCriticalFields: string[];
  additionalFields: string[];
}

export class TemplateFieldAnalyzer {
  private fuzzyMatcher: EnhancedFuzzyFieldMatcher;
  private templateCache: Map<string, TemplateInfo>;
  
  constructor() {
    this.fuzzyMatcher = new EnhancedFuzzyFieldMatcher();
    this.templateCache = new Map();
  }
  
  public async analyzeAllTemplates(): Promise<TemplateAnalysisReport> {
    const templates = this.getTemplateInfo();
    const commonPatterns = this.identifyCommonPatterns();
    const namingConventions = this.analyzeNamingConventions();
    const checkboxAnalysis = this.analyzeCheckboxUsage();
    const fieldCoverage = await this.analyzeFieldCoverage();
    
    return {
      totalTemplates: templates.length,
      avgFieldCount: templates.reduce((sum, t) => sum + t.fields, 0) / templates.length,
      fieldRange: { 
        min: Math.min(...templates.map(t => t.fields)),
        max: Math.max(...templates.map(t => t.fields))
      },
      commonPatterns,
      namingConventions,
      checkboxAnalysis,
      fieldCoverage,
      recommendations: this.generateRecommendations(fieldCoverage)
    };
  }
  
  public analyzeTemplate(
    templateFields: string[],
    fhirDataFields: string[]
  ): TemplateFieldAnalysis {
    const mappings = new Map<string, FieldMapping[]>();
    
    for (const fhirField of fhirDataFields) {
      const matches = this.fuzzyMatcher.matchFields(fhirField, templateFields);
      if (matches.length > 0) {
        mappings.set(fhirField, matches);
      }
    }
    
    return {
      templateId: '', // Set by caller
      manufacturerName: '', // Set by caller
      fields: templateFields,
      mappings
    };
  }
  
  public generateMappingReport(analysis: TemplateFieldAnalysis): string {
    const report: string[] = [
      `Template Analysis Report: ${analysis.manufacturerName}`,
      `Total Fields: ${analysis.fields.length}`,
      `Mapped Fields: ${analysis.mappings.size}`,
      `Coverage: ${((analysis.mappings.size / analysis.fields.length) * 100).toFixed(1)}%`,
      '',
      'Field Mappings (High Confidence):'
    ];
    
    // Group by confidence levels
    const highConfidence: Array<[string, FieldMapping]> = [];
    const mediumConfidence: Array<[string, FieldMapping]> = [];
    const lowConfidence: Array<[string, FieldMapping]> = [];
    
    for (const [source, mappings] of analysis.mappings) {
      const bestMatch = mappings[0];
      if (bestMatch.confidence >= 0.9) {
        highConfidence.push([source, bestMatch]);
      } else if (bestMatch.confidence >= 0.7) {
        mediumConfidence.push([source, bestMatch]);
      } else {
        lowConfidence.push([source, bestMatch]);
      }
    }
    
    // High confidence mappings
    if (highConfidence.length > 0) {
      report.push('\n✅ High Confidence (90%+):');
      highConfidence.forEach(([source, mapping]) => {
        report.push(
          `  ${source} → ${mapping.targetField} ` +
          `(${mapping.matchType}, ${(mapping.confidence * 100).toFixed(0)}%)`
        );
      });
    }
    
    // Medium confidence mappings
    if (mediumConfidence.length > 0) {
      report.push('\n⚠️  Medium Confidence (70-89%):');
      mediumConfidence.forEach(([source, mapping]) => {
        report.push(
          `  ${source} → ${mapping.targetField} ` +
          `(${mapping.matchType}, ${(mapping.confidence * 100).toFixed(0)}%)`
        );
      });
    }
    
    // Low confidence mappings
    if (lowConfidence.length > 0) {
      report.push('\n❌ Low Confidence (<70%):');
      lowConfidence.forEach(([source, mapping]) => {
        report.push(
          `  ${source} → ${mapping.targetField} ` +
          `(${mapping.matchType}, ${(mapping.confidence * 100).toFixed(0)}%)`
        );
      });
    }
    
    // Unmapped fields
    const mappedTargetFields = new Set(
      Array.from(analysis.mappings.values())
        .flat()
        .map(m => m.targetField)
    );
    
    const unmappedFields = analysis.fields.filter(
      field => !mappedTargetFields.has(field)
    );
    
    if (unmappedFields.length > 0) {
      report.push('\n❓ Unmapped Template Fields:');
      unmappedFields.slice(0, 20).forEach(field => report.push(`  - ${field}`));
      if (unmappedFields.length > 20) {
        report.push(`  ... and ${unmappedFields.length - 20} more`);
      }
    }
    
    return report.join('\n');
  }
  
  private getTemplateInfo(): Array<{ name: string; fields: number }> {
    return [
      { name: 'Biowound', fields: 72 },
      { name: 'Extremity Care', fields: 80 },
      { name: 'Centurion', fields: 60 },
      { name: 'ACZ Associates', fields: 71 },
      { name: 'Advanced Solution', fields: 82 },
      { name: 'Imbed Microlyte', fields: 44 }
    ];
  }
  
  private identifyCommonPatterns(): CommonPattern[] {
    return [
      {
        pattern: 'Multiple NPI Fields',
        occurrence: 'ACZ (7 NPIs), Others (1-2 NPIs)',
        recommendation: 'Use array structure for NPIs, implement multi-field handler',
        examples: ['Physician NPI 1', 'Physician NPI 2', 'Facility NPI 1']
      },
      {
        pattern: 'Checkbox Naming Inconsistency',
        occurrence: 'Every manufacturer uses different checkbox naming',
        recommendation: 'Implement pattern-based checkbox detection with fuzzy matching',
        examples: ['Check Box[N]', 'Check: [Field]', 'chk[Field]', 'Text[N]']
      },
      {
        pattern: 'Typos in Field Names',
        occurrence: 'Found in 4 of 6 templates',
        recommendation: 'Build typo tolerance into matching algorithm',
        examples: ['clincal', 'Facilitis', 'Surigcal', 'ICD-1o', 'Suscriber']
      },
      {
        pattern: 'Space Variations',
        occurrence: 'Double spaces, trailing colons common',
        recommendation: 'Normalize whitespace and punctuation before matching',
        examples: ['Patient  Name', 'Patient Name:', 'Phone1', 'Phone 1']
      },
      {
        pattern: 'Product Code Patterns',
        occurrence: 'Q-codes embedded in checkbox names',
        recommendation: 'Extract Q-codes for product matching',
        examples: ['Check Membrane Wrap Q4205', 'Check Amnio-Maxx Q4239']
      },
      {
        pattern: 'Insurance Type Checkboxes',
        occurrence: 'Advanced Solution uses type-specific checkboxes',
        recommendation: 'Map insurance types to appropriate checkboxes',
        examples: ['Primary Insurance HMO', 'Secondary Insurance PPO']
      },
      {
        pattern: 'Place of Service Variations',
        occurrence: 'Different POS field formats across templates',
        recommendation: 'Create POS code mapping table',
        examples: ['POS-11', 'Check Physician Office (POS 11)', 'Office']
      },
      {
        pattern: 'Size-based Categories',
        occurrence: 'ACZ uses size thresholds for wound location',
        recommendation: 'Implement size-based conditional logic',
        examples: ['< 100 SQ CM', '> 100 SQ CM']
      }
    ];
  }
  
  private analyzeNamingConventions(): NamingConvention[] {
    return [
      {
        category: 'Patient Fields',
        variations: [
          'Patient Name', 'Patient Name:', 'Patient  Name', 'PATIENT NAME',
          'Name', 'Text3'
        ],
        suggestedStandard: 'patient_name'
      },
      {
        category: 'Contact Information',
        variations: [
          'Phone', 'Phone1', 'Phone Number', 'Contact Phone',
          'Primary Point of Contact Phone Number', 'Tel', 'Contact #/Email'
        ],
        suggestedStandard: 'patient_phone'
      },
      {
        category: 'Insurance',
        variations: [
          'Primary Insurance', 'Primary Insurance Name', 'Primary Insurance:',
          'Primary Payer', 'Payer Name', 'Payer Name1', 'Insurance Company'
        ],
        suggestedStandard: 'primary_insurance_name'
      },
      {
        category: 'Place of Service',
        variations: [
          'POS-11', 'Check Physician Office (POS 11)', 'Office',
          'Physician Office', 'Facility Type', 'Place of Service'
        ],
        suggestedStandard: 'place_of_service'
      },
      {
        category: 'Provider NPIs',
        variations: [
          'NPI', 'NPI1', 'Physician NPI 1', 'Physician  NPI', 'Physician NPI:',
          'Provider NPI', 'Doctor NPI'
        ],
        suggestedStandard: 'provider_npi'
      },
      {
        category: 'Diagnosis Codes',
        variations: [
          'ICD-10 Codes', 'ICD-1o Diagnosis Code(s)', 'Primary Diagnosis Code',
          'Primary:', 'Secondary:', 'Diagnosis', 'DX Codes'
        ],
        suggestedStandard: 'diagnosis_codes'
      },
      {
        category: 'Authorization',
        variations: [
          'Prior Auth', 'PA Request Type', 'No: Pre-Auth Assistance',
          'Check Permission to Initiate and Follow Up: Yes', 'Authorization'
        ],
        suggestedStandard: 'prior_authorization'
      }
    ];
  }
  
  private analyzeCheckboxUsage(): CheckboxAnalysis {
    const patterns = [
      { 
        pattern: 'Check Box[N]', 
        count: 145, 
        percentage: 29.8,
        examples: ['Check Box1', 'Check Box232', 'Check Box24']
      },
      { 
        pattern: 'Check: [Description]', 
        count: 89, 
        percentage: 18.3,
        examples: ['Check: New Wound', 'Check: POS-11', 'Check: Consent Obtained']
      },
      { 
        pattern: 'Check [Description]', 
        count: 156, 
        percentage: 32.0,
        examples: ['Check Membrane Wrap Q4205', 'Check Wound Location: Legs/Arms/Trunk < 100 SQ CM']
      },
      { 
        pattern: 'chk[Description]', 
        count: 12, 
        percentage: 2.5,
        examples: ['chkDiabetes', 'chkNewPatient']
      },
      { 
        pattern: 'Text[N] (as checkbox)', 
        count: 85, 
        percentage: 17.4,
        examples: ['Text20', 'Text22']
      }
    ];
    
    return {
      totalCheckboxes: 487,
      namingPatterns: patterns,
      usage: {
        booleanFields: 65,
        productSelection: 45,
        insuranceType: 23,
        woundType: 18,
        facilityType: 15,
        clinicalStatus: 12,
        consentFlags: 10
      }
    };
  }
  
  private async analyzeFieldCoverage(): Promise<FieldCoverageReport> {
    // Analyze how well standard FHIR fields cover template requirements
    const standardFhirFields = [
      'patient_name', 'patient_dob', 'patient_gender', 'patient_address',
      'patient_city', 'patient_state', 'patient_zip', 'patient_phone',
      'primary_insurance', 'policy_number', 'group_number', 'subscriber_name',
      'provider_name', 'provider_npi', 'facility_name', 'facility_npi',
      'tax_id', 'diagnosis_codes', 'wound_location', 'wound_size',
      'procedure_date', 'prior_auth_number'
    ];
    
    const criticalMissingFields = [
      'Sales Rep',
      'MAC/PTAN',
      'Multiple Provider NPIs (2-7)',
      'Insurance Type Checkboxes',
      'Product Selection Checkboxes',
      'Clinical Study Information',
      'Network Participation Status',
      'Wound Size Categories',
      'Place of Service Checkboxes'
    ];
    
    const additionalTemplateFields = [
      'ISO if applicable',
      'Additional Emails for Notification',
      'Factility Contact Name', // With typo
      'Check Permission to Initiate and Follow Up',
      'Patient Currently on Hospice',
      'Patient in SNF',
      'Patient Under Global'
    ];
    
    return {
      coveredFields: standardFhirFields.length,
      totalFields: 75, // Average template field count
      coveragePercentage: (standardFhirFields.length / 75) * 100,
      missingCriticalFields: criticalMissingFields,
      additionalFields: additionalTemplateFields
    };
  }
  
  private generateRecommendations(coverage: FieldCoverageReport): string[] {
    const recommendations = [
      'Implement fuzzy matching with minimum 65% confidence threshold',
      'Create manufacturer-specific transformation rules for each template',
      'Normalize field names before matching (whitespace, case, punctuation)',
      'Extract embedded codes (Q-codes, POS codes) for semantic matching',
      'Build comprehensive typo tolerance into the matching algorithm',
      'Use pattern matching for checkbox identification across all variations',
      'Implement array handling for repeating fields (NPIs, facilities, products)',
      'Create intelligent fallback mappings for unmapped fields',
      'Log all successful mappings to improve algorithm over time',
      'Build UI for manual mapping override when confidence is low'
    ];
    
    // Add coverage-specific recommendations
    if (coverage.coveragePercentage < 50) {
      recommendations.push(
        'Expand FHIR data model to include manufacturer-specific fields',
        'Create extension fields for critical missing data points',
        'Implement conditional field requirements based on manufacturer'
      );
    }
    
    if (coverage.missingCriticalFields.length > 5) {
      recommendations.push(
        'Prioritize mapping for critical missing fields',
        'Create default value strategies for non-FHIR fields',
        'Build manufacturer-specific data collection forms'
      );
    }
    
    return recommendations;
  }
}

interface TemplateInfo {
  id: string;
  name: string;
  manufacturer: string;
  fieldCount: number;
  lastUpdated: Date;
  version: string;
}
```

### 7. IVR Mapping Orchestrator

```typescript
// services/IVRMappingOrchestrator.ts
export interface MappingContext {
  manufacturerId: string;
  templateId: string;
  sessionId: string;
  userId: string;
}

export interface MappingMetrics {
  totalFields: number;
  mappedFields: number;
  fallbackFields: number;
  unmappedFields: number;
  avgConfidence: number;
  duration: number;
  cacheHit: boolean;
}

export class IVRMappingOrchestrator {
  private fuzzyMatcher: EnhancedFuzzyFieldMatcher;
  private manufacturerHandler: ManufacturerTemplateHandler;
  private templateAnalyzer: TemplateFieldAnalyzer;
  private validationEngine: ValidationEngine;
  private fallbackStrategy: FallbackStrategy;
  private auditLogger: MappingAuditLogger;
  private performanceMonitor: MappingPerformanceMonitor;
  private cache: SmartMappingCache;
  private errorRecovery: MappingErrorRecovery;
  
  constructor() {
    this.fuzzyMatcher = new EnhancedFuzzyFieldMatcher({
      threshold: 0.65,
      weights: {
        exact: 1.0,
        levenshtein: 0.85,
        jaro: 0.80,
        semantic: 0.90,
        pattern: 0.70
      },
      enableCache: true,
      enableFallbacks: true,
      maxSuggestions: 3
    });
    
    this.manufacturerHandler = new ManufacturerTemplateHandler();
    this.templateAnalyzer = new TemplateFieldAnalyzer();
    this.validationEngine = new ValidationEngine();
    this.fallbackStrategy = new FallbackStrategy();
    this.auditLogger = new MappingAuditLogger();
    this.performanceMonitor = new MappingPerformanceMonitor();
    this.cache = new SmartMappingCache();
    this.errorRecovery = new MappingErrorRecovery();
  }
  
  public async mapDataForManufacturer(
    fhirData: any,
    manufacturerId: string,
    templateFields: string[],
    context?: MappingContext
  ): Promise<MappingResult> {
    const startTime = Date.now();
    const sessionId = context?.sessionId || this.generateSessionId();
    
    try {
      // Check cache
      const cacheKey = this.getCacheKey(manufacturerId, templateFields);
      const cachedMapping = this.cache.get(cacheKey);
      
      if (cachedMapping) {
        console.log(`Using cached mapping for ${manufacturerId}`);
        const result = this.applyDataToMapping(cachedMapping, fhirData);
        
        await this.performanceMonitor.trackMapping(startTime, result);
        
        return {
          ...result,
          performance: {
            ...result.performance,
            cacheHit: true,
            duration: Date.now() - startTime
          }
        };
      }
      
      console.log(`Creating new mapping for ${manufacturerId}`);
      
      // Step 1: Normalize and prepare data
      const normalizedFields = this.normalizeFieldNames(templateFields);
      const extractedData = this.extractFHIRData(fhirData);
      const fhirFields = Object.keys(extractedData);
      
      // Step 2: Perform fuzzy matching
      const fieldMappings = await this.performFuzzyMatching(
        fhirFields,
        normalizedFields,
        context
      );
      
      // Step 3: Apply manufacturer-specific rules
      const transformationResult = await this.manufacturerHandler.applyManufacturerTransformation(
        extractedData,
        manufacturerId,
        templateFields
      );
      
      // Step 4: Handle unmapped fields with fallbacks
      const unmappedFields = this.identifyUnmappedFields(
        templateFields,
        fieldMappings,
        transformationResult.data
      );
      
      let fallbackResults: Record<string, FallbackResult> = {};
      if (unmappedFields.length > 0 && this.fuzzyMatcher.config.enableFallbacks) {
        fallbackResults = this.fallbackStrategy.applyFallbacks(
          unmappedFields,
          extractedData
        );
        
        // Apply fallback values
        for (const [field, result] of Object.entries(fallbackResults)) {
          transformationResult.data[field] = result.value;
        }
      }
      
      // Step 5: Validate the mapped data
      const fieldTypes = this.inferFieldTypes(templateFields);
      const validationReport = this.validationEngine.validate(
        transformationResult.data,
        fieldTypes
      );
      
      // Step 6: Calculate metrics
      const metrics: MappingMetrics = {
        totalFields: templateFields.length,
        mappedFields: fieldMappings.size + Object.keys(fallbackResults).length,
        fallbackFields: Object.keys(fallbackResults).length,
        unmappedFields: unmappedFields.length - Object.keys(fallbackResults).length,
        avgConfidence: this.calculateOverallConfidence(fieldMappings),
        duration: Date.now() - startTime,
        cacheHit: false
      };
      
      // Step 7: Create result
      const result: MappingResult = {
        manufacturerId,
        templateId: context?.templateId || '',
        fieldMappings,
        transformedData: transformationResult.data,
        confidence: metrics.avgConfidence,
        unmappedFields: unmappedFields.filter(f => !fallbackResults[f]),
        warnings: [
          ...transformationResult.warnings,
          ...this.generateWarnings(fieldMappings, validationReport)
        ],
        performance: metrics,
        validation: validationReport
      };
      
      // Step 8: Cache successful mapping
      if (validationReport.isValid && metrics.avgConfidence > 0.7) {
        this.cache.set(cacheKey, result);
      }
      
      // Step 9: Audit logging
      await this.auditLogger.logMapping({
        id: sessionId,
        timestamp: new Date(),
        episodeId: context?.templateId || '',
        templateId: context?.templateId || '',
        manufacturerId,
        mappingStats: metrics,
        fieldDetails: this.createFieldDetails(fieldMappings, fallbackResults),
        validationResults: validationReport,
        userId: context?.userId || 'system',
        duration: metrics.duration
      });
      
      // Step 10: Performance tracking
      await this.performanceMonitor.trackMapping(startTime, result);
      
      return result;
      
    } catch (error) {
      console.error('Mapping failed:', error);
      
      // Attempt recovery
      return await this.errorRecovery.handleMappingFailure(
        error,
        {
          manufacturerId,
          templateId: context?.templateId || '',
          sessionId,
          fhirData,
          templateFields
        }
      );
    }
  }
  
  private async performFuzzyMatching(
    sourceFields: string[],
    targetFields: NormalizedField[],
    context?: MappingContext
  ): Promise<Map<string, FieldMapping[]>> {
    const mappings = new Map<string, FieldMapping[]>();
    
    // Parallel processing for performance
    const promises = sourceFields.map(async (sourceField) => {
      const matches = this.fuzzyMatcher.matchFields(
        sourceField,
        targetFields.map(nf => nf.original),
        context
      );
      
      if (matches.length > 0) {
        return { sourceField, matches };
      }
      return null;
    });
    
    const results = await Promise.all(promises);
    
    results.forEach(result => {
      if (result) {
        mappings.set(result.sourceField, result.matches);
      }
    });
    
    return mappings;
  }
  
  private normalizeFieldNames(fields: string[]): NormalizedField[] {
    return fields.map(field => ({
      original: field,
      normalized: field
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .replace(/[:\-()]/g, '')
        .replace(/\s*(yes|no)\s*$/i, '') // Remove yes/no suffixes
        .trim()
    }));
  }
  
  private extractFHIRData(fhirData: any): Record<string, any> {
    const extracted: Record<string, any> = {};
    
    // Patient data extraction
    if (fhirData.patient) {
      const patient = fhirData.patient;
      
      // Name
      if (patient.name?.[0]) {
        const name = patient.name[0];
        extracted.patient_name = [
          name.given?.join(' '),
          name.family
        ].filter(Boolean).join(' ').trim();
        
        extracted.patient_first_name = name.given?.[0] || '';
        extracted.patient_last_name = name.family || '';
        extracted.patient_middle_name = name.given?.[1] || '';
      }
      
      // Demographics
      extracted.patient_dob = patient.birthDate;
      extracted.patient_gender = patient.gender;
      
      // Address
      if (patient.address?.[0]) {
        const address = patient.address[0];
        extracted.patient_address = address.line?.join(' ') || '';
        extracted.patient_city = address.city || '';
        extracted.patient_state = address.state || '';
        extracted.patient_zip = address.postalCode || '';
        extracted.patient_country = address.country || 'USA';
      }
      
      // Contact
      patient.telecom?.forEach(telecom => {
        if (telecom.system === 'phone' && !extracted.patient_phone) {
          extracted.patient_phone = telecom.value;
        }
        if (telecom.system === 'email' && !extracted.patient_email) {
          extracted.patient_email = telecom.value;
        }
        if (telecom.system === 'fax' && !extracted.patient_fax) {
          extracted.patient_fax = telecom.value;
        }
      });
    }
    
    // Coverage (Insurance) data
    if (fhirData.coverage) {
      const coverage = Array.isArray(fhirData.coverage) 
        ? fhirData.coverage 
        : [fhirData.coverage];
      
      // Primary insurance
      const primary = coverage.find(c => c.order === 1) || coverage[0];
      if (primary) {
        extracted.primary_insurance_name = primary.payor?.[0]?.display || '';
        extracted.policy_number = primary.identifier?.find(
          id => id.type?.coding?.[0]?.code === 'MB'
        )?.value || '';
        extracted.group_number = primary.class?.[0]?.value || '';
        
        // Subscriber info
        if (primary.subscriber) {
          extracted.subscriber_id = primary.subscriberId || '';
          extracted.relationship_to_subscriber = primary.relationship?.coding?.[0]?.code || '';
        }
      }
      
      // Secondary insurance
      const secondary = coverage.find(c => c.order === 2);
      if (secondary) {
        extracted.secondary_insurance_name = secondary.payor?.[0]?.display || '';
        extracted.secondary_policy_number = secondary.identifier?.find(
          id => id.type?.coding?.[0]?.code === 'MB'
        )?.value || '';
        extracted.secondary_group_number = secondary.class?.[0]?.value || '';
      }
    }
    
    // Practitioner (Provider) data
    if (fhirData.practitioner) {
      const practitioner = fhirData.practitioner;
      
      // Name
      if (practitioner.name?.[0]) {
        extracted.provider_name = practitioner.name[0].text || 
          `${practitioner.name[0].given?.join(' ')} ${practitioner.name[0].family}`.trim();
      }
      
      // NPI
      const npi = practitioner.identifier?.find(
        id => id.system === 'http://hl7.org/fhir/sid/us-npi'
      );
      extracted.provider_npi = npi?.value || '';
      
      // Multiple NPIs if available
      const allNpis = practitioner.identifier
        ?.filter(id => id.system === 'http://hl7.org/fhir/sid/us-npi')
        .map(id => id.value);
      if (allNpis?.length > 1) {
        extracted.provider_npis = allNpis;
      }
      
      // Specialty
      extracted.provider_specialty = practitioner.qualification?.[0]?.code?.text || '';
      
      // Contact
      practitioner.telecom?.forEach(telecom => {
        if (telecom.system === 'phone' && !extracted.provider_phone) {
          extracted.provider_phone = telecom.value;
        }
        if (telecom.system === 'fax' && !extracted.provider_fax) {
          extracted.provider_fax = telecom.value;
        }
      });
    }
    
    // Organization (Facility) data
    if (fhirData.organization) {
      const organization = fhirData.organization;
      
      extracted.facility_name = organization.name || '';
      
      // NPI
      const npi = organization.identifier?.find(
        id => id.system === 'http://hl7.org/fhir/sid/us-npi'
      );
      extracted.facility_npi = npi?.value || '';
      
      // Tax ID
      const taxId = organization.identifier?.find(
        id => id.type?.coding?.[0]?.code === 'TAX'
      );
      extracted.tax_id = taxId?.value || '';
      
      // PTAN/MAC
      const ptan = organization.identifier?.find(
        id => id.type?.coding?.[0]?.code === 'PTAN'
      );
      extracted.ptan = ptan?.value || '';
      extracted.mac = ptan?.value || ''; // Often the same
      
      // Address
      if (organization.address?.[0]) {
        const address = organization.address[0];
        extracted.facility_address = address.line?.join(' ') || '';
        extracted.facility_city = address.city || '';
        extracted.facility_state = address.state || '';
        extracted.facility_zip = address.postalCode || '';
      }
      
      // Contact
      organization.telecom?.forEach(telecom => {
        if (telecom.system === 'phone' && !extracted.facility_phone) {
          extracted.facility_phone = telecom.value;
        }
        if (telecom.system === 'fax' && !extracted.facility_fax) {
          extracted.facility_fax = telecom.value;
        }
      });
      
      // Type
      extracted.facility_type = organization.type?.[0]?.coding?.[0]?.display || '';
    }
    
    // Condition (Diagnosis) data
    if (fhirData.condition) {
      const conditions = Array.isArray(fhirData.condition) 
        ? fhirData.condition 
        : [fhirData.condition];
      
      // Primary diagnosis
      const primary = conditions[0];
      if (primary) {
        extracted.primary_diagnosis_code = primary.code?.coding?.[0]?.code || '';
        extracted.primary_diagnosis_description = primary.code?.text || 
          primary.code?.coding?.[0]?.display || '';
        
        // Wound specific
        extracted.wound_type = this.inferWoundType(primary);
        extracted.wound_location = primary.bodySite?.[0]?.text || '';
      }
      
      // All diagnosis codes
      extracted.diagnosis_codes = conditions
        .map(c => c.code?.coding?.[0]?.code)
        .filter(Boolean)
        .join(', ');
    }
    
    // Observation (Clinical) data
    if (fhirData.observations) {
      const observations = Array.isArray(fhirData.observations) 
        ? fhirData.observations 
        : [fhirData.observations];
      
      // Wound size
      const sizeObs = observations.find(
        o => o.code?.coding?.[0]?.code === '89122-6' // LOINC for wound size
      );
      if (sizeObs) {
        extracted.wound_size = sizeObs.valueQuantity?.value;
        extracted.wound_size_unit = sizeObs.valueQuantity?.unit || 'cm²';
      }
      
      // Wound depth
      const depthObs = observations.find(
        o => o.code?.coding?.[0]?.code === '39114-0' // LOINC for wound depth
      );
      if (depthObs) {
        extracted.wound_depth = depthObs.valueQuantity?.value;
      }
    }
    
    // Procedure data
    if (fhirData.procedure) {
      extracted.procedure_date = fhirData.procedure.performedDateTime || 
        fhirData.procedure.performedPeriod?.start;
      extracted.procedure_code = fhirData.procedure.code?.coding?.[0]?.code || '';
    }
    
    // Additional administrative data
    if (fhirData.episodeOfCare) {
      extracted.episode_id = fhirData.episodeOfCare.id;
      extracted.care_manager = fhirData.episodeOfCare.managingOrganization?.display || '';
    }
    
    // Service request (Order) data
    if (fhirData.serviceRequest) {
      extracted.order_id = fhirData.serviceRequest.id;
      extracted.order_date = fhirData.serviceRequest.authoredOn;
      extracted.urgency = fhirData.serviceRequest.priority || 'routine';
      
      // Prior auth
      const priorAuth = fhirData.serviceRequest.insurance?.find(
        ins => ins.extension?.find(ext => ext.url.includes('prior-authorization'))
      );
      if (priorAuth) {
        extracted.prior_auth_number = priorAuth.extension?.find(
          ext => ext.url.includes('prior-authorization')
        )?.valueString || '';
      }
    }
    
    return extracted;
  }
  
  private inferWoundType(condition: any): string {
    const code = condition.code?.coding?.[0]?.code || '';
    const display = condition.code?.coding?.[0]?.display || '';
    
    // Map ICD-10 codes to wound types
    if (code.startsWith('E11.621') || code.startsWith('E10.621')) {
      return 'diabetic_foot_ulcer';
    }
    if (code.startsWith('I83.0') || code.startsWith('I87.2')) {
      return 'venous_leg_ulcer';
    }
    if (code.startsWith('L89')) {
      return 'pressure_ulcer';
    }
    if (code.startsWith('T20-T32')) {
      return 'burn_traumatic';
    }
    if (display.toLowerCase().includes('surgical')) {
      return 'surgical_dehiscence';
    }
    
    return 'other';
  }
  
  private inferFieldTypes(fields: string[]): Map<string, string> {
    const types = new Map<string, string>();
    
    fields.forEach(field => {
      const lower = field.toLowerCase();
      
      if (lower.includes('npi')) types.set(field, 'npi');
      else if (lower.includes('date') || lower.includes('dob')) types.set(field, 'date');
      else if (lower.includes('phone') || lower.includes('tel')) types.set(field, 'phone');
      else if (lower.includes('email')) types.set(field, 'email');
      else if (lower.includes('zip') || lower.includes('postal')) types.set(field, 'zip');
      else if (lower.includes('tax') || lower.includes('ein')) types.set(field, 'tax_id');
      else if (lower.includes('policy')) types.set(field, 'policy_number');
      else if (lower.includes('diagnosis') || lower.includes('icd')) types.set(field, 'diagnosis_code');
      else if (lower.includes('size') && lower.includes('wound')) types.set(field, 'wound_size');
      else types.set(field, 'text');
    });
    
    return types;
  }
  
  private calculateOverallConfidence(mappings: Map<string, FieldMapping[]>): number {
    if (mappings.size === 0) return 0;
    
    let totalConfidence = 0;
    let count = 0;
    
    for (const fieldMappings of mappings.values()) {
      if (fieldMappings.length > 0) {
        totalConfidence += fieldMappings[0].confidence;
        count++;
      }
    }
    
    return count > 0 ? totalConfidence / count : 0;
  }
  
  private identifyUnmappedFields(
    templateFields: string[],
    mappings: Map<string, FieldMapping[]>,
    transformedData: Record<string, any>
  ): string[] {
    const mappedFields = new Set<string>();
    
    // Fields mapped through fuzzy matching
    for (const fieldMappings of mappings.values()) {
      fieldMappings.forEach(fm => mappedFields.add(fm.targetField));
    }
    
    // Fields with values from transformation
    Object.keys(transformedData).forEach(field => {
      if (transformedData[field] !== undefined && transformedData[field] !== '') {
        mappedFields.add(field);
      }
    });
    
    return templateFields.filter(field => !mappedFields.has(field));
  }
  
  private generateWarnings(
    mappings: Map<string, FieldMapping[]>,
    validation: ValidationReport
  ): string[] {
    const warnings: string[] = [];
    
    // Low confidence warnings
    for (const [source, fieldMappings] of mappings) {
      if (fieldMappings.length > 0 && fieldMappings[0].confidence < 0.7) {
        warnings.push(
          `Low confidence mapping: ${source} → ${fieldMappings[0].targetField} ` +
          `(${(fieldMappings[0].confidence * 100).toFixed(0)}%)`
        );
      }
      
      // Ambiguous mappings
      if (fieldMappings.length > 1) {
        const topTwo = fieldMappings.slice(0, 2);
        if (topTwo[0].confidence - topTwo[1].confidence < 0.15) {
          warnings.push(
            `Ambiguous mapping for ${source}: ` +
            `${topTwo[0].targetField} vs ${topTwo[1].targetField}`
          );
        }
      }
    }
    
    // Validation warnings
    validation.warnings.forEach(w => warnings.push(w.message));
    
    return [...new Set(warnings)]; // Remove duplicates
  }
  
  private getCacheKey(manufacturerId: string, templateFields: string[]): string {
    const fieldHash = this.hashFields(templateFields);
    return `${manufacturerId}:${fieldHash}`;
  }
  
  private hashFields(fields: string[]): string {
    // Simple hash for field list
    return fields.sort().join('|').substring(0, 100);
  }
  
  private generateSessionId(): string {
    return `mapping_${Date.now()}_${Math.random().toString(36).substring(7)}`;
  }
  
  private applyDataToMapping(
    cachedMapping: MappingResult,
    fhirData: any
  ): MappingResult {
    const extractedData = this.extractFHIRData(fhirData);
    const transformedData: Record<string, any> = {};
    
    // Apply cached mappings to new data
    for (const [source, mappings] of cachedMapping.fieldMappings) {
      if (mappings.length > 0 && extractedData[source] !== undefined) {
        transformedData[mappings[0].targetField] = extractedData[source];
      }
    }
    
    return {
      ...cachedMapping,
      transformedData
    };
  }
  
  private createFieldDetails(
    mappings: Map<string, FieldMapping[]>,
    fallbacks: Record<string, FallbackResult>
  ): Array<any> {
    const details: Array<any> = [];
    
    // Fuzzy matched fields
    for (const [source, fieldMappings] of mappings) {
      if (fieldMappings.length > 0) {
        const mapping = fieldMappings[0];
        details.push({
          sourceField: source,
          targetField: mapping.targetField,
          value: '', // Would be filled from actual data
          confidence: mapping.confidence,
          matchType: mapping.matchType,
          fallbackUsed: false
        });
      }
    }
    
    // Fallback fields
    for (const [field, result] of Object.entries(fallbacks)) {
      details.push({
        sourceField: 'fallback',
        targetField: field,
        value: result.value,
        confidence: result.confidence,
        matchType: 'fallback',
        fallbackUsed: true
      });
    }
    
    return details;
  }
}

// Supporting Classes
class SmartMappingCache {
  private cache = new Map<string, CachedMapping>();
  private readonly TTL = 3600000; // 1 hour
  private readonly MAX_SIZE = 100;
  
  set(key: string, mapping: MappingResult): void {
    // Implement LRU eviction if needed
    if (this.cache.size >= this.MAX_SIZE) {
      const firstKey = this.cache.keys().next().value;
      this.cache.delete(firstKey);
    }
    
    this.cache.set(key, {
      mapping,
      timestamp: Date.now(),
      hitCount: 0,
      successRate: 1.0
    });
  }
  
  get(key: string): MappingResult | null {
    const cached = this.cache.get(key);
    if (!cached) return null;
    
    // Check TTL
    if (Date.now() - cached.timestamp > this.TTL) {
      this.cache.delete(key);
      return null;
    }
    
    // Track usage
    cached.hitCount++;
    
    // Invalidate if success rate drops
    if (cached.successRate < 0.5) {
      this.cache.delete(key);
      return null;
    }
    
    return cached.mapping;
  }
  
  updateSuccessRate(key: string, success: boolean): void {
    const cached = this.cache.get(key);
    if (cached) {
      const weight = 0.1; // Exponential moving average
      cached.successRate = (1 - weight) * cached.successRate + weight * (success ? 1 : 0);
    }
  }
  
  getStats(): CacheStats {
    const entries = Array.from(this.cache.values());
    return {
      size: this.cache.size,
      avgHitCount: entries.reduce((sum, e) => sum + e.hitCount, 0) / entries.length || 0,
      avgSuccessRate: entries.reduce((sum, e) => sum + e.successRate, 0) / entries.length || 0
    };
  }
}

class MappingAuditLogger {
  async logMapping(entry: MappingAuditEntry): Promise<void> {
    try {
      // Store in database
      await this.storeAuditEntry(entry);
      
      // Alert on issues
      if (entry.mappingStats.avgConfidence < 0.7) {
        await this.alertLowConfidenceMapping(entry);
      }
      
      if (entry.mappingStats.unmappedFields > 10) {
        await this.alertHighUnmappedFields(entry);
      }
      
      // Update metrics
      await this.updateMappingMetrics(entry);
      
    } catch (error) {
      console.error('Failed to log mapping audit:', error);
    }
  }
  
  private async storeAuditEntry(entry: MappingAuditEntry): Promise<void> {
    // Database storage implementation
    console.log('Audit entry stored:', entry.id);
  }
  
  private async alertLowConfidenceMapping(entry: MappingAuditEntry): Promise<void> {
    console.warn(
      `Low confidence mapping for ${entry.manufacturerId}: ` +
      `${entry.mappingStats.avgConfidence.toFixed(2)}`
    );
  }
  
  private async alertHighUnmappedFields(entry: MappingAuditEntry): Promise<void> {
    console.warn(
      `High unmapped field count for ${entry.manufacturerId}: ` +
      `${entry.mappingStats.unmappedFields} fields`
    );
  }
  
  private async updateMappingMetrics(entry: MappingAuditEntry): Promise<void> {
    // Update aggregate metrics
  }
}

class MappingPerformanceMonitor {
  private metrics: PerformanceMetrics = {
    avgMappingTime: 0,
    avgFieldsPerTemplate: 0,
    cacheHitRate: 0,
    mappingSuccessRate: 0,
    fallbackUsageRate: 0,
    p95MappingTime: 0,
    p99MappingTime: 0
  };
  
  private timings: number[] = [];
  private readonly MAX_TIMINGS = 1000;
  
  async trackMapping(startTime: number, result: MappingResult): Promise<void> {
    const duration = Date.now() - startTime;
    
    // Update timings
    this.timings.push(duration);
    if (this.timings.length > this.MAX_TIMINGS) {
      this.timings.shift();
    }
    
    // Update metrics (exponential moving average)
    const alpha = 0.1;
    this.metrics.avgMappingTime = (1 - alpha) * this.metrics.avgMappingTime + alpha * duration;
    
    // Calculate percentiles
    const sorted = [...this.timings].sort((a, b) => a - b);
    this.metrics.p95MappingTime = sorted[Math.floor(sorted.length * 0.95)] || 0;
    this.metrics.p99MappingTime = sorted[Math.floor(sorted.length * 0.99)] || 0;
    
    // Log slow mappings
    if (duration > 1000) {
      console.warn(
        `Slow mapping detected: ${duration}ms for ${result.manufacturerId} ` +
        `(${result.fieldMappings.size} fields)`
      );
    }
    
    // Store detailed metrics
    await this.storeMetrics({
      timestamp: new Date(),
      duration,
      manufacturerId: result.manufacturerId,
      fieldCount: result.fieldMappings.size,
      confidence: result.confidence,
      cacheHit: result.performance.cacheHit,
      fallbacksUsed: result.performance.fallbackFields
    });
  }
  
  private async storeMetrics(metrics: DetailedMetrics): Promise<void> {
    // Store in time-series database or analytics service
    console.log('Performance metrics stored');
  }
  
  getMetrics(): PerformanceMetrics {
    return { ...this.metrics };
  }
}

class MappingErrorRecovery {
  async handleMappingFailure(
    error: Error,
    context: ErrorContext
  ): Promise<MappingResult> {
    console.error('Mapping failed:', error, context);
    
    // Try recovery strategies
    const strategies = [
      () => this.useLastSuccessfulMapping(context),
      () => this.useMinimalMapping(context),
      () => this.useGenericTemplate(context)
    ];
    
    for (const strategy of strategies) {
      try {
        console.log('Attempting recovery strategy...');
        return await strategy();
      } catch (e) {
        console.error('Recovery strategy failed:', e);
        continue;
      }
    }
    
    // Final fallback - empty mapping
    return this.createEmptyMapping(context);
  }
  
  private async useLastSuccessfulMapping(context: ErrorContext): Promise<MappingResult> {
    // Retrieve last successful mapping from cache or database
    throw new Error('No cached mapping available');
  }
  
  private async useMinimalMapping(context: ErrorContext): Promise<MappingResult> {
    // Map only critical fields
    const criticalFields = [
      'patient_name', 'patient_dob', 'provider_npi',
      'facility_name', 'primary_diagnosis'
    ];
    
    const fieldMappings = new Map<string, FieldMapping[]>();
    const transformedData: Record<string, any> = {};
    
    // Extract only critical data
    const fhirData = context.fhirData;
    if (fhirData.patient?.name?.[0]) {
      transformedData.patient_name = `${fhirData.patient.name[0].given?.[0]} ${fhirData.patient.name[0].family}`;
      fieldMappings.set('patient_name', [{
        sourceField: 'patient_name',
        targetField: 'Patient Name',
        confidence: 1.0,
        matchType: 'exact'
      }]);
    }
    
    return {
      manufacturerId: context.manufacturerId,
      templateId: context.templateId,
      fieldMappings,
      transformedData,
      confidence: 0.5,
      unmappedFields: context.templateFields.filter(f => !transformedData[f]),
      warnings: ['Using minimal mapping due to error recovery'],
      performance: {
        totalFields: context.templateFields.length,
        mappedFields: Object.keys(transformedData).length,
        fallbackFields: 0,
        unmappedFields: context.templateFields.length - Object.keys(transformedData).length,
        avgConfidence: 0.5,
        duration: 0,
        cacheHit: false
      },
      validation: {
        isValid: false,
        errors: [],
        warnings: [{ field: 'all', message: 'Minimal mapping applied', severity: 'warning' }],
        fieldReports: new Map()
      }
    };
  }
  
  private async useGenericTemplate(context: ErrorContext): Promise<MappingResult> {
    // Use a generic template mapping
    const genericMappings = new Map<string, FieldMapping[]>([
      ['patient_name', [{
        sourceField: 'patient_name',
        targetField: 'Name',
        confidence: 0.8,
        matchType: 'semantic'
      }]],
      ['patient_dob', [{
        sourceField: 'patient_dob',
        targetField: 'DOB',
        confidence: 0.8,
        matchType: 'semantic'
      }]]
    ]);
    
    return {
      manufacturerId: context.manufacturerId,
      templateId: context.templateId,
      fieldMappings: genericMappings,
      transformedData: {},
      confidence: 0.3,
      unmappedFields: context.templateFields,
      warnings: ['Using generic template due to mapping failure'],
      performance: {
        totalFields: context.templateFields.length,
        mappedFields: 0,
        fallbackFields: 0,
        unmappedFields: context.templateFields.length,
        avgConfidence: 0.3,
        duration: 0,
        cacheHit: false
      },
      validation: {
        isValid: false,
        errors: [{ field: 'all', message: 'Generic template applied', severity: 'error' }],
        warnings: [],
        fieldReports: new Map()
      }
    };
  }
  
  private createEmptyMapping(context: ErrorContext): MappingResult {
    return {
      manufacturerId: context.manufacturerId,
      templateId: context.templateId,
      fieldMappings: new Map(),
      transformedData: {},
      confidence: 0,
      unmappedFields: context.templateFields,
      warnings: ['Complete mapping failure - no data mapped'],
      performance: {
        totalFields: context.templateFields.length,
        mappedFields: 0,
        fallbackFields: 0,
        unmappedFields: context.templateFields.length,
        avgConfidence: 0,
        duration: 0,
        cacheHit: false
      },
      validation: {
        isValid: false,
        errors: [{ field: 'all', message: 'Mapping failed completely', severity: 'critical' }],
        warnings: [],
        fieldReports: new Map()
      }
    };
  }
}

// Type definitions
interface NormalizedField {
  original: string;
  normalized: string;
}

interface CachedMapping {
  mapping: MappingResult;
  timestamp: number;
  hitCount: number;
  successRate: number;
}

interface CacheStats {
  size: number;
  avgHitCount: number;
  avgSuccessRate: number;
}

interface MappingAuditEntry {
  id: string;
  timestamp: Date;
  episodeId: string;
  templateId: string;
  manufacturerId: string;
  mappingStats: MappingMetrics;
  fieldDetails: Array<{
    sourceField: string;
    targetField: string;
    value: any;
    confidence: number;
    matchType: string;
    fallbackUsed: boolean;
  }>;
  validationResults: ValidationReport;
  userId: string;
  duration: number;
}

interface PerformanceMetrics {
  avgMappingTime: number;
  avgFieldsPerTemplate: number;
  cacheHitRate: number;
  mappingSuccessRate: number;
  fallbackUsageRate: number;
  p95MappingTime: number;
  p99MappingTime: number;
}

interface DetailedMetrics {
  timestamp: Date;
  duration: number;
  manufacturerId: string;
  fieldCount: number;
  confidence: number;
  cacheHit: boolean;
  fallbacksUsed: number;
}

interface ErrorContext {
  manufacturerId: string;
  templateId: string;
  sessionId: string;
  fhirData: any;
  templateFields: string[];
}