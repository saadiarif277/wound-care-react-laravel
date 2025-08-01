import { useState, useCallback, useMemo } from 'react';
import { mapFormDataToDocuSealFields, validateDocuSealTemplate, getFieldMappingStats } from '@/utils/docusealFieldMapper';

/**
 * React Hook for DocuSeal Field Mapping
 *
 * This hook provides a clean interface for mapping form data to DocuSeal fields
 * and integrates with the existing DocusealEmbed component system.
 */
export function useDocusealFieldMapping(options = {}) {
  const {
    manufacturer = 'ACZ',
    useEnhancedMapping = true,
    debug = false,
    autoValidate = true
  } = options;

  const [mappingState, setMappingState] = useState({
    isMapping: false,
    mappedFields: [],
    validation: null,
    stats: null,
    error: null
  });

  /**
   * Map form data to DocuSeal fields
   */
  const mapFields = useCallback(async (formData, docuSealTemplate, mappingOptions = {}) => {
    setMappingState(prev => ({ ...prev, isMapping: true, error: null }));

    try {
      if (debug) {
        console.log('🔄 Starting field mapping with hook:', {
          formDataKeys: Object.keys(formData),
          templateFields: docuSealTemplate?.fields?.length || 0,
          manufacturer,
          useEnhancedMapping
        });
      }

      // Validate template if auto-validate is enabled
      let validation = null;
      if (autoValidate && docuSealTemplate) {
        validation = validateDocuSealTemplate(docuSealTemplate);
        if (!validation.valid) {
          console.warn('DocuSeal template validation failed:', validation.errors);
        }
      }

      // Map the fields
      const mappedFields = mapFormDataToDocuSealFields(
        formData,
        docuSealTemplate,
        {
          manufacturer,
          useEnhancedMapping,
          debug,
          ...mappingOptions
        }
      );

      // Get mapping statistics
      const stats = getFieldMappingStats(mappedFields, docuSealTemplate);

      if (debug) {
        console.log('✅ Field mapping completed:', {
          mappedFields: mappedFields.length,
          stats,
          validation
        });
      }

      setMappingState({
        isMapping: false,
        mappedFields,
        validation,
        stats,
        error: null
      });

      return {
        success: true,
        mappedFields,
        validation,
        stats
      };

    } catch (error) {
      console.error('❌ Field mapping error:', error);

      setMappingState({
        isMapping: false,
        mappedFields: [],
        validation: null,
        stats: null,
        error: error.message
      });

      return {
        success: false,
        error: error.message
      };
    }
  }, [manufacturer, useEnhancedMapping, debug, autoValidate]);

  /**
   * Reset mapping state
   */
  const resetMapping = useCallback(() => {
    setMappingState({
      isMapping: false,
      mappedFields: [],
      validation: null,
      stats: null,
      error: null
    });
  }, []);

  /**
   * Get mapping progress/completeness
   */
  const mappingProgress = useMemo(() => {
    if (!mappingState.stats) return 0;

    const { mappingCoverage } = mappingState.stats;
    return Math.min(mappingCoverage, 100);
  }, [mappingState.stats]);

  /**
   * Check if mapping is complete and valid
   */
  const isMappingComplete = useMemo(() => {
    return !mappingState.isMapping &&
           mappingState.mappedFields.length > 0 &&
           !mappingState.error;
  }, [mappingState]);

  /**
   * Get validation status
   */
  const validationStatus = useMemo(() => {
    if (!mappingState.validation) return 'not_validated';

    if (mappingState.validation.valid) {
      return mappingState.validation.warnings.length > 0 ? 'valid_with_warnings' : 'valid';
    }

    return 'invalid';
  }, [mappingState.validation]);

  return {
    // State
    ...mappingState,

    // Actions
    mapFields,
    resetMapping,

    // Computed values
    mappingProgress,
    isMappingComplete,
    validationStatus,

    // Helper methods
    getFieldCount: () => mappingState.mappedFields.length,
    getMappedFieldNames: () => mappingState.mappedFields.map(f => f.name || f.uuid),
    hasField: (fieldName) => mappingState.mappedFields.some(f => f.name === fieldName || f.uuid === fieldName)
  };
}

/**
 * Enhanced hook for ACZ-specific field mapping
 */
export function useACZFieldMapping(options = {}) {
  return useDocusealFieldMapping({
    manufacturer: 'ACZ',
    useEnhancedMapping: true,
    ...options
  });
}

/**
 * Hook for testing field mapping with sample data
 */
export function useFieldMappingTest() {
  const [testResults, setTestResults] = useState([]);

  const runTest = useCallback(async (testCases) => {
    const results = [];

    for (const testCase of testCases) {
      const { name, formData, template, expectedFields } = testCase;

      try {
        const { mapFields } = useDocusealFieldMapping({ debug: false });
        const result = await mapFields(formData, template);

        const passed = result.success &&
                      result.mappedFields.length >= (expectedFields || 0);

        results.push({
          name,
          passed,
          actualFields: result.mappedFields.length,
          expectedFields,
          error: result.error
        });
      } catch (error) {
        results.push({
          name,
          passed: false,
          error: error.message
        });
      }
    }

    setTestResults(results);
    return results;
  }, []);

  return {
    testResults,
    runTest,
    clearResults: () => setTestResults([])
  };
}

export default {
  useDocusealFieldMapping,
  useACZFieldMapping,
  useFieldMappingTest
};
