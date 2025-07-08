// components/ManualMappingUI.tsx
import React, { useState, useEffect } from 'react';
import { Search, AlertCircle, Check, X, ChevronDown, Info } from 'lucide-react';

interface ManualMappingProps {
  unmappedFields: string[];
  availableData: Record<string, any>;
  suggestedMappings: Map<string, FieldMapping[]>;
  onSave: (mappings: Record<string, any>) => void;
  onCancel: () => void;
}

export const ManualMappingUI: React.FC<ManualMappingProps> = ({
  unmappedFields,
  availableData,
  suggestedMappings,
  onSave,
  onCancel
}) => {
  const [mappings, setMappings] = useState<Record<string, string>>({});
  const [searchTerms, setSearchTerms] = useState<Record<string, string>>({});
  const [expandedFields, setExpandedFields] = useState<Set<string>>(new Set());
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  
  useEffect(() => {
    // Pre-fill with highest confidence suggestions
    const initialMappings: Record<string, string> = {};
    unmappedFields.forEach(field => {
      const suggestions = suggestedMappings.get(field);
      if (suggestions && suggestions.length > 0 && suggestions[0].confidence > 0.5) {
        initialMappings[field] = suggestions[0].sourceField;
      }
    });
    setMappings(initialMappings);
  }, [unmappedFields, suggestedMappings]);
  
  const handleFieldMapping = (templateField: string, dataField: string) => {
    setMappings(prev => ({
      ...prev,
      [templateField]: dataField
    }));
    
    // Clear validation error when field is mapped
    setValidationErrors(prev => {
      const updated = { ...prev };
      delete updated[templateField];
      return updated;
    });
  };
  
  const handleSearch = (field: string, term: string) => {
    setSearchTerms(prev => ({
      ...prev,
      [field]: term
    }));
  };
  
  const toggleFieldExpansion = (field: string) => {
    setExpandedFields(prev => {
      const updated = new Set(prev);
      if (updated.has(field)) {
        updated.delete(field);
      } else {
        updated.add(field);
      }
      return updated;
    });
  };
  
  const getFilteredDataFields = (searchTerm: string): Array<[string, any]> => {
    const entries = Object.entries(availableData);
    if (!searchTerm) return entries;
    
    const term = searchTerm.toLowerCase();
    return entries.filter(([key, value]) => 
      key.toLowerCase().includes(term) || 
      String(value).toLowerCase().includes(term)
    );
  };
  
  const validateMappings = (): boolean => {
    const errors: Record<string, string> = {};
    const requiredFields = unmappedFields.filter(field => 
      field.toLowerCase().includes('required') || 
      field.toLowerCase().includes('npi') ||
      field.toLowerCase().includes('name')
    );
    
    requiredFields.forEach(field => {
      if (!mappings[field]) {
        errors[field] = 'This field is required';
      }
    });
    
    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };
  
  const handleSave = () => {
    if (validateMappings()) {
      const finalMappings: Record<string, any> = {};
      Object.entries(mappings).forEach(([templateField, dataField]) => {
        if (dataField && availableData[dataField] !== undefined) {
          finalMappings[templateField] = availableData[dataField];
        }
      });
      onSave(finalMappings);
    }
  };
  
  const getSuggestionBadge = (field: string): React.ReactNode => {
    const suggestions = suggestedMappings.get(field);
    if (!suggestions || suggestions.length === 0) return null;
    
    const topSuggestion = suggestions[0];
    const confidencePercent = Math.round(topSuggestion.confidence * 100);
    const confidenceColor = 
      confidencePercent >= 80 ? 'text-green-600 bg-green-50' :
      confidencePercent >= 60 ? 'text-yellow-600 bg-yellow-50' :
      'text-red-600 bg-red-50';
    
    return (
      <span className={`ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${confidenceColor}`}>
        {topSuggestion.matchType} ({confidencePercent}%)
      </span>
    );
  };
  
  const mappedCount = Object.keys(mappings).filter(field => mappings[field]).length;
  const completionPercentage = Math.round((mappedCount / unmappedFields.length) * 100);
  
  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-hidden flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Manual Field Mapping Required
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                {unmappedFields.length} fields need manual mapping
              </p>
            </div>
            <button
              onClick={onCancel}
              className="text-gray-400 hover:text-gray-500"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
          
          {/* Progress bar */}
          <div className="mt-4">
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-500">Completion</span>
              <span className="font-medium text-gray-900">{completionPercentage}%</span>
            </div>
            <div className="mt-1 w-full bg-gray-200 rounded-full h-2">
              <div
                className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                style={{ width: `${completionPercentage}%` }}
              />
            </div>
          </div>
        </div>
        
        {/* Body */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          <div className="space-y-4">
            {unmappedFields.map((field) => {
              const isExpanded = expandedFields.has(field);
              const searchTerm = searchTerms[field] || '';
              const suggestions = suggestedMappings.get(field) || [];
              const hasError = !!validationErrors[field];
              
              return (
                <div
                  key={field}
                  className={`border rounded-lg p-4 ${
                    hasError ? 'border-red-300 bg-red-50' : 'border-gray-200'
                  }`}
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center">
                        <label className="text-sm font-medium text-gray-900">
                          {field}
                        </label>
                        {getSuggestionBadge(field)}
                      </div>
                      
                      {hasError && (
                        <p className="mt-1 text-sm text-red-600 flex items-center">
                          <AlertCircle className="h-4 w-4 mr-1" />
                          {validationErrors[field]}
                        </p>
                      )}
                      
                      {/* Current mapping or selection */}
                      <div className="mt-2">
                        {mappings[field] ? (
                          <div className="flex items-center justify-between p-2 bg-blue-50 rounded border border-blue-200">
                            <div className="flex-1">
                              <span className="text-sm font-medium text-blue-900">
                                {mappings[field]}
                              </span>
                              <span className="text-sm text-blue-700 ml-2">
                                → {String(availableData[mappings[field]]).substring(0, 50)}
                                {String(availableData[mappings[field]]).length > 50 && '...'}
                              </span>
                            </div>
                            <button
                              onClick={() => handleFieldMapping(field, '')}
                              className="ml-2 text-blue-600 hover:text-blue-700"
                            >
                              <X className="h-4 w-4" />
                            </button>
                          </div>
                        ) : (
                          <button
                            onClick={() => toggleFieldExpansion(field)}
                            className="flex items-center justify-between w-full p-2 text-left bg-gray-50 rounded border border-gray-300 hover:bg-gray-100"
                          >
                            <span className="text-sm text-gray-500">
                              Select a data field to map...
                            </span>
                            <ChevronDown
                              className={`h-4 w-4 text-gray-400 transition-transform ${
                                isExpanded ? 'transform rotate-180' : ''
                              }`}
                            />
                          </button>
                        )}
                      </div>
                      
                      {/* Suggestions */}
                      {suggestions.length > 0 && !mappings[field] && (
                        <div className="mt-2">
                          <p className="text-xs text-gray-500 mb-1">Suggestions:</p>
                          <div className="space-y-1">
                            {suggestions.slice(0, 3).map((suggestion, idx) => (
                              <button
                                key={idx}
                                onClick={() => handleFieldMapping(field, suggestion.sourceField)}
                                className="flex items-center justify-between w-full p-1.5 text-left text-sm bg-gray-50 rounded hover:bg-gray-100"
                              >
                                <span className="flex items-center">
                                  <span className="font-medium">{suggestion.sourceField}</span>
                                  <span className="ml-2 text-gray-500">
                                    {String(availableData[suggestion.sourceField]).substring(0, 30)}...
                                  </span>
                                </span>
                                <span className={`text-xs ${
                                  suggestion.confidence >= 0.8 ? 'text-green-600' :
                                  suggestion.confidence >= 0.6 ? 'text-yellow-600' :
                                  'text-red-600'
                                }`}>
                                  {Math.round(suggestion.confidence * 100)}%
                                </span>
                              </button>
                            ))}
                          </div>
                        </div>
                      )}
                      
                      {/* Expanded field selection */}
                      {isExpanded && !mappings[field] && (
                        <div className="mt-3 border border-gray-200 rounded-lg p-3 bg-white">
                          {/* Search */}
                          <div className="relative mb-3">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                            <input
                              type="text"
                              placeholder="Search available fields..."
                              value={searchTerm}
                              onChange={(e) => handleSearch(field, e.target.value)}
                              className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                          </div>
                          
                          {/* Field list */}
                          <div className="max-h-48 overflow-y-auto space-y-1">
                            {getFilteredDataFields(searchTerm).map(([dataField, value]) => (
                              <button
                                key={dataField}
                                onClick={() => {
                                  handleFieldMapping(field, dataField);
                                  toggleFieldExpansion(field);
                                }}
                                className="flex items-center justify-between w-full p-2 text-left text-sm hover:bg-gray-50 rounded"
                              >
                                <div className="flex-1 min-w-0">
                                  <div className="font-medium text-gray-900">
                                    {dataField}
                                  </div>
                                  <div className="text-gray-500 truncate">
                                    {String(value).substring(0, 50)}
                                    {String(value).length > 50 && '...'}
                                  </div>
                                </div>
                              </button>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
        
        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center text-sm text-gray-500">
              <Info className="h-4 w-4 mr-1" />
              <span>
                {mappedCount} of {unmappedFields.length} fields mapped
              </span>
            </div>
            <div className="flex gap-3">
              <button
                onClick={onCancel}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                disabled={mappedCount === 0}
                className={`px-4 py-2 text-sm font-medium text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${
                  mappedCount === 0
                    ? 'bg-gray-400 cursor-not-allowed'
                    : 'bg-blue-600 hover:bg-blue-700'
                }`}
              >
                Save Mappings ({mappedCount})
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};