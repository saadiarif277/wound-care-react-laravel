import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import axios from 'axios';
import {
  X, Search, Save, RefreshCw, AlertTriangle, CheckCircle,
  Wand2, ArrowRight, Filter, ChevronDown, ChevronRight,
  Info, Target, Shuffle, Download, Upload, Eye, EyeOff,
  Layers, AlertCircle, Sparkles, MapPin
} from 'lucide-react';
import {
  FieldMapping,
  CanonicalField,
  TransformationRule,
  MappingSuggestion,
  ValidationResult,
  AVAILABLE_TRANSFORMATION_RULES,
  FieldMappingInterfaceProps
} from '@/types/field-mapping';

export const FieldMappingInterface: React.FC<FieldMappingInterfaceProps> = ({
  templateId,
  onClose,
  onUpdate
}) => {
  // State management
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [mappings, setMappings] = useState<FieldMapping[]>([]);
  const [canonicalFields, setCanonicalFields] = useState<Record<string, CanonicalField[]>>({});
  const [suggestions, setSuggestions] = useState<Record<string, MappingSuggestion[]>>({});
  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);
  
  // UI state
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [expandedCategories, setExpandedCategories] = useState<Set<string>>(new Set());
  const [selectedField, setSelectedField] = useState<string | null>(null);
  const [showUnmappedOnly, setShowUnmappedOnly] = useState(false);
  const [showValidationErrors, setShowValidationErrors] = useState(false);

  // Fetch initial data
  useEffect(() => {
    fetchData();
  }, [templateId]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [mappingsRes, canonicalRes] = await Promise.all([
        axios.get(`/api/v1/admin/docuseal/templates/${templateId}/field-mappings`),
        axios.get('/api/v1/admin/docuseal/canonical-fields')
      ]);

      setMappings(mappingsRes.data.mappings || []);
      setCanonicalFields(canonicalRes.data.by_category || {});
      
      // Auto-expand categories with mapped fields
      const categoriesWithMappings = new Set<string>();
      mappingsRes.data.mappings.forEach((mapping: FieldMapping) => {
        if (mapping.canonical_field) {
          categoriesWithMappings.add(mapping.canonical_field.category);
        }
      });
      setExpandedCategories(categoriesWithMappings);

    } catch (error) {
      console.error('Failed to fetch mapping data:', error);
    } finally {
      setLoading(false);
    }
  };

  // Get suggestions for unmapped fields
  const fetchSuggestions = async () => {
    const unmappedFields = mappings
      .filter(m => !m.canonical_field_id)
      .map(m => m.field_name);

    if (unmappedFields.length === 0) return;

    try {
      const response = await axios.post(
        `/api/v1/admin/docuseal/templates/${templateId}/field-mappings/suggest`,
        { field_names: unmappedFields }
      );
      setSuggestions(response.data.suggestions || {});
    } catch (error) {
      console.error('Failed to fetch suggestions:', error);
    }
  };

  // Validate all mappings
  const validateMappings = async () => {
    try {
      const response = await axios.post(
        `/api/v1/admin/docuseal/templates/${templateId}/field-mappings/validate`
      );
      setValidationResult(response.data);
    } catch (error) {
      console.error('Failed to validate mappings:', error);
    }
  };

  // Save mappings
  const saveMappings = async () => {
    setSaving(true);
    try {
      await axios.post(
        `/api/v1/admin/docuseal/templates/${templateId}/field-mappings`,
        { mappings }
      );
      
      onUpdate(templateId);
      await fetchData();
      await validateMappings();
      
    } catch (error) {
      console.error('Failed to save mappings:', error);
    } finally {
      setSaving(false);
    }
  };

  // Update a single mapping
  const updateMapping = (fieldName: string, updates: Partial<FieldMapping>) => {
    setMappings(prev => prev.map(mapping => 
      mapping.field_name === fieldName
        ? { ...mapping, ...updates }
        : mapping
    ));
  };

  // Apply suggestion
  const applySuggestion = (fieldName: string, suggestion: MappingSuggestion) => {
    updateMapping(fieldName, {
      canonical_field_id: suggestion.canonical_field_id,
      canonical_field: suggestion.canonical_field,
      confidence_score: suggestion.confidence
    });
  };

  // Add transformation rule
  const addTransformationRule = (fieldName: string, rule: TransformationRule) => {
    const mapping = mappings.find(m => m.field_name === fieldName);
    if (mapping) {
      updateMapping(fieldName, {
        transformation_rules: [...mapping.transformation_rules, rule]
      });
    }
  };

  // Remove transformation rule
  const removeTransformationRule = (fieldName: string, ruleIndex: number) => {
    const mapping = mappings.find(m => m.field_name === fieldName);
    if (mapping) {
      const newRules = [...mapping.transformation_rules];
      newRules.splice(ruleIndex, 1);
      updateMapping(fieldName, {
        transformation_rules: newRules
      });
    }
  };

  // Filter mappings based on search and filters
  const filteredMappings = useMemo(() => {
    return mappings.filter(mapping => {
      // Search filter
      if (searchTerm && !mapping.field_name.toLowerCase().includes(searchTerm.toLowerCase())) {
        return false;
      }
      
      // Category filter
      if (selectedCategory !== 'all' && mapping.canonical_field?.category !== selectedCategory) {
        return false;
      }
      
      // Unmapped filter
      if (showUnmappedOnly && mapping.canonical_field_id) {
        return false;
      }
      
      // Validation errors filter
      if (showValidationErrors && mapping.validation_status !== 'error') {
        return false;
      }
      
      return true;
    });
  }, [mappings, searchTerm, selectedCategory, showUnmappedOnly, showValidationErrors]);

  // Group mappings by category
  const groupedMappings = useMemo(() => {
    const groups: Record<string, FieldMapping[]> = {};
    
    filteredMappings.forEach(mapping => {
      const category = mapping.canonical_field?.category || 'unmapped';
      if (!groups[category]) {
        groups[category] = [];
      }
      groups[category].push(mapping);
    });
    
    return groups;
  }, [filteredMappings]);

  // Get validation status color
  const getValidationColor = (status: string) => {
    switch (status) {
      case 'valid':
        return 'text-green-600 bg-green-50 border-green-200';
      case 'warning':
        return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 'error':
        return 'text-red-600 bg-red-50 border-red-200';
      default:
        return 'text-gray-600 bg-gray-50 border-gray-200';
    }
  };

  return (
    <Transition appear show as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black bg-opacity-25" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-7xl transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all">
                {/* Header */}
                <div className="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <MapPin className="w-5 h-5 text-purple-600" />
                      </div>
                      <div>
                        <Dialog.Title className="text-xl font-bold text-gray-900">
                          Field Mapping Configuration
                        </Dialog.Title>
                        <p className="text-sm text-gray-600 mt-1">
                          Map template fields to canonical structure
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={onClose}
                      className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>

                  {/* Actions Bar */}
                  <div className="mt-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {/* Search */}
                      <div className="relative">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                          type="text"
                          placeholder="Search fields..."
                          value={searchTerm}
                          onChange={(e) => setSearchTerm(e.target.value)}
                          className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 w-64"
                        />
                      </div>

                      {/* Filters */}
                      <button
                        onClick={() => setShowUnmappedOnly(!showUnmappedOnly)}
                        className={`px-4 py-2 rounded-lg transition-colors flex items-center gap-2 ${
                          showUnmappedOnly
                            ? 'bg-purple-100 text-purple-700'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                      >
                        {showUnmappedOnly ? <Eye className="w-4 h-4" /> : <EyeOff className="w-4 h-4" />}
                        Unmapped Only
                      </button>

                      <button
                        onClick={() => setShowValidationErrors(!showValidationErrors)}
                        className={`px-4 py-2 rounded-lg transition-colors flex items-center gap-2 ${
                          showValidationErrors
                            ? 'bg-red-100 text-red-700'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                      >
                        <AlertTriangle className="w-4 h-4" />
                        Errors Only
                      </button>
                    </div>

                    <div className="flex items-center gap-3">
                      <button
                        onClick={fetchSuggestions}
                        className="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors flex items-center gap-2"
                      >
                        <Sparkles className="w-4 h-4" />
                        Get AI Suggestions
                      </button>

                      <button
                        onClick={validateMappings}
                        className="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors flex items-center gap-2"
                      >
                        <CheckCircle className="w-4 h-4" />
                        Validate
                      </button>

                      <button
                        onClick={saveMappings}
                        disabled={saving}
                        className="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                      >
                        {saving ? (
                          <>
                            <RefreshCw className="w-4 h-4 animate-spin" />
                            Saving...
                          </>
                        ) : (
                          <>
                            <Save className="w-4 h-4" />
                            Save Mappings
                          </>
                        )}
                      </button>
                    </div>
                  </div>

                  {/* Stats */}
                  {validationResult && (
                    <div className="mt-4 flex items-center gap-6 text-sm">
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-green-500 rounded-full" />
                        <span className="text-gray-600">
                          Valid: <span className="font-semibold text-gray-900">{validationResult.summary.valid}</span>
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-yellow-500 rounded-full" />
                        <span className="text-gray-600">
                          Warnings: <span className="font-semibold text-gray-900">{validationResult.summary.warnings}</span>
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-red-500 rounded-full" />
                        <span className="text-gray-600">
                          Errors: <span className="font-semibold text-gray-900">{validationResult.summary.errors}</span>
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-gray-500 rounded-full" />
                        <span className="text-gray-600">
                          Unmapped: <span className="font-semibold text-gray-900">{validationResult.summary.unmapped}</span>
                        </span>
                      </div>
                      <div className="ml-auto">
                        <span className="text-gray-600">
                          Coverage: <span className="font-bold text-purple-600">{validationResult.coverage_percentage}%</span>
                        </span>
                      </div>
                    </div>
                  )}
                </div>

                {/* Content */}
                <div className="flex h-[600px]">
                  {/* Sidebar - Categories */}
                  <div className="w-64 bg-gray-50 border-r border-gray-200 p-4 overflow-y-auto">
                    <h3 className="font-semibold text-gray-900 mb-3">Categories</h3>
                    <div className="space-y-1">
                      <button
                        onClick={() => setSelectedCategory('all')}
                        className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                          selectedCategory === 'all'
                            ? 'bg-purple-100 text-purple-700'
                            : 'hover:bg-gray-100 text-gray-700'
                        }`}
                      >
                        All Fields
                      </button>
                      {Object.keys(canonicalFields).map(category => (
                        <button
                          key={category}
                          onClick={() => setSelectedCategory(category)}
                          className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                            selectedCategory === category
                              ? 'bg-purple-100 text-purple-700'
                              : 'hover:bg-gray-100 text-gray-700'
                          }`}
                        >
                          {category}
                        </button>
                      ))}
                      <button
                        onClick={() => setSelectedCategory('unmapped')}
                        className={`w-full text-left px-3 py-2 rounded-lg transition-colors ${
                          selectedCategory === 'unmapped'
                            ? 'bg-purple-100 text-purple-700'
                            : 'hover:bg-gray-100 text-gray-700'
                        }`}
                      >
                        Unmapped
                      </button>
                    </div>
                  </div>

                  {/* Main Content - Mappings */}
                  <div className="flex-1 overflow-y-auto">
                    {loading ? (
                      <div className="flex items-center justify-center h-full">
                        <RefreshCw className="w-8 h-8 animate-spin text-purple-600" />
                      </div>
                    ) : (
                      <div className="p-6">
                        {Object.entries(groupedMappings).map(([category, categoryMappings]) => (
                          <div key={category} className="mb-6">
                            <button
                              onClick={() => {
                                const newExpanded = new Set(expandedCategories);
                                if (newExpanded.has(category)) {
                                  newExpanded.delete(category);
                                } else {
                                  newExpanded.add(category);
                                }
                                setExpandedCategories(newExpanded);
                              }}
                              className="flex items-center gap-2 mb-3 text-lg font-semibold text-gray-900 hover:text-purple-600 transition-colors"
                            >
                              {expandedCategories.has(category) ? (
                                <ChevronDown className="w-5 h-5" />
                              ) : (
                                <ChevronRight className="w-5 h-5" />
                              )}
                              {category} ({categoryMappings.length})
                            </button>

                            {expandedCategories.has(category) && (
                              <div className="space-y-3">
                                {categoryMappings.map(mapping => (
                                  <MappingRow
                                    key={mapping.field_name}
                                    mapping={mapping}
                                    canonicalFields={Object.values(canonicalFields).flat()}
                                    suggestions={suggestions[mapping.field_name] || []}
                                    onUpdate={(updates) => updateMapping(mapping.field_name, updates)}
                                    onApplySuggestion={(suggestion) => applySuggestion(mapping.field_name, suggestion)}
                                    onAddRule={(rule) => addTransformationRule(mapping.field_name, rule)}
                                    onRemoveRule={(index) => removeTransformationRule(mapping.field_name, index)}
                                    isSelected={selectedField === mapping.field_name}
                                    onSelect={() => setSelectedField(mapping.field_name)}
                                  />
                                ))}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};

// Individual mapping row component
interface MappingRowProps {
  mapping: FieldMapping;
  canonicalFields: CanonicalField[];
  suggestions: MappingSuggestion[];
  onUpdate: (updates: Partial<FieldMapping>) => void;
  onApplySuggestion: (suggestion: MappingSuggestion) => void;
  onAddRule: (rule: TransformationRule) => void;
  onRemoveRule: (index: number) => void;
  isSelected: boolean;
  onSelect: () => void;
}

const MappingRow: React.FC<MappingRowProps> = ({
  mapping,
  canonicalFields,
  suggestions,
  onUpdate,
  onApplySuggestion,
  onAddRule,
  onRemoveRule,
  isSelected,
  onSelect
}) => {
  const [showTransformations, setShowTransformations] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);

  const getValidationIcon = () => {
    switch (mapping.validation_status) {
      case 'valid':
        return <CheckCircle className="w-4 h-4 text-green-600" />;
      case 'warning':
        return <AlertTriangle className="w-4 h-4 text-yellow-600" />;
      case 'error':
        return <AlertCircle className="w-4 h-4 text-red-600" />;
      default:
        return null;
    }
  };

  return (
    <div
      className={`border rounded-lg p-4 transition-all ${
        isSelected ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:border-gray-300'
      }`}
      onClick={onSelect}
    >
      <div className="flex items-start gap-4">
        {/* Field Name */}
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-2">
            <span className="font-medium text-gray-900">{mapping.field_name}</span>
            {getValidationIcon()}
            {mapping.confidence_score > 0 && (
              <span className="text-xs text-gray-500">
                {mapping.confidence_score}% confidence
              </span>
            )}
          </div>
          
          {/* Canonical Field Selector */}
          <select
            value={mapping.canonical_field_id || ''}
            onChange={(e) => {
              const fieldId = e.target.value ? parseInt(e.target.value) : null;
              const canonicalField = canonicalFields.find(f => f.id === fieldId);
              onUpdate({
                canonical_field_id: fieldId,
                canonical_field: canonicalField
              });
            }}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
            onClick={(e) => e.stopPropagation()}
          >
            <option value="">-- Select Canonical Field --</option>
            {canonicalFields.map(field => (
              <option key={field.id} value={field.id}>
                {field.category} → {field.field_name}
              </option>
            ))}
          </select>

          {/* Validation Messages */}
          {mapping.validation_messages.length > 0 && (
            <div className="mt-2 space-y-1">
              {mapping.validation_messages.map((message, index) => (
                <p key={index} className="text-sm text-red-600 flex items-center gap-1">
                  <Info className="w-3 h-3" />
                  {message}
                </p>
              ))}
            </div>
          )}

          {/* Suggestions */}
          {suggestions.length > 0 && !mapping.canonical_field_id && (
            <div className="mt-2">
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  setShowSuggestions(!showSuggestions);
                }}
                className="text-sm text-purple-600 hover:text-purple-700 flex items-center gap-1"
              >
                <Sparkles className="w-3 h-3" />
                {suggestions.length} suggestion{suggestions.length !== 1 ? 's' : ''} available
              </button>
              
              {showSuggestions && (
                <div className="mt-2 space-y-1">
                  {suggestions.map((suggestion, index) => (
                    <button
                      key={index}
                      onClick={(e) => {
                        e.stopPropagation();
                        onApplySuggestion(suggestion);
                        setShowSuggestions(false);
                      }}
                      className="w-full text-left px-3 py-2 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors text-sm"
                    >
                      <div className="flex items-center justify-between">
                        <span>
                          {suggestion.canonical_field.category} → {suggestion.canonical_field.field_name}
                        </span>
                        <span className="text-purple-600 font-medium">
                          {suggestion.confidence}%
                        </span>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Transformation Rules */}
          {mapping.canonical_field_id && (
            <div className="mt-2">
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  setShowTransformations(!showTransformations);
                }}
                className="text-sm text-indigo-600 hover:text-indigo-700 flex items-center gap-1"
              >
                <Shuffle className="w-3 h-3" />
                Transformation Rules ({mapping.transformation_rules.length})
              </button>
              
              {showTransformations && (
                <TransformationRulesEditor
                  rules={mapping.transformation_rules}
                  onAdd={onAddRule}
                  onRemove={onRemoveRule}
                />
              )}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex items-center gap-2">
          <button
            onClick={(e) => {
              e.stopPropagation();
              onUpdate({ is_active: !mapping.is_active });
            }}
            className={`p-2 rounded-lg transition-colors ${
              mapping.is_active
                ? 'bg-green-100 text-green-600 hover:bg-green-200'
                : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
            }`}
            title={mapping.is_active ? 'Active' : 'Inactive'}
          >
            <Target className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
};

// Transformation Rules Editor Component
interface TransformationRulesEditorProps {
  rules: TransformationRule[];
  onAdd: (rule: TransformationRule) => void;
  onRemove: (index: number) => void;
}

const TransformationRulesEditor: React.FC<TransformationRulesEditorProps> = ({
  rules,
  onAdd,
  onRemove
}) => {
  const [selectedType, setSelectedType] = useState<string>('');
  const [selectedOperation, setSelectedOperation] = useState<string>('');

  const handleAdd = () => {
    if (selectedType && selectedOperation) {
      onAdd({
        type: selectedType as any,
        operation: selectedOperation,
        parameters: {}
      });
      setSelectedType('');
      setSelectedOperation('');
    }
  };

  return (
    <div className="mt-2 p-3 bg-gray-50 rounded-lg" onClick={(e) => e.stopPropagation()}>
      {/* Existing Rules */}
      {rules.length > 0 && (
        <div className="space-y-1 mb-3">
          {rules.map((rule, index) => (
            <div key={index} className="flex items-center justify-between bg-white px-3 py-2 rounded border border-gray-200">
              <span className="text-sm">
                <span className="font-medium">{rule.type}:</span> {rule.operation}
              </span>
              <button
                onClick={() => onRemove(index)}
                className="text-red-600 hover:text-red-700"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Add New Rule */}
      <div className="flex items-center gap-2">
        <select
          value={selectedType}
          onChange={(e) => {
            setSelectedType(e.target.value);
            setSelectedOperation('');
          }}
          className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded"
        >
          <option value="">Select type...</option>
          {Object.keys(AVAILABLE_TRANSFORMATION_RULES).map(type => (
            <option key={type} value={type}>{type}</option>
          ))}
        </select>

        {selectedType && (
          <select
            value={selectedOperation}
            onChange={(e) => setSelectedOperation(e.target.value)}
            className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded"
          >
            <option value="">Select operation...</option>
            {Object.entries(AVAILABLE_TRANSFORMATION_RULES[selectedType]?.operations || {}).map(([op, desc]) => (
              <option key={op} value={op}>{op}</option>
            ))}
          </select>
        )}

        <button
          onClick={handleAdd}
          disabled={!selectedType || !selectedOperation}
          className="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Add
        </button>
      </div>
    </div>
  );
};