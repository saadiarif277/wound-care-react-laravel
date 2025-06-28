import React, { useState } from 'react';
import axios from 'axios';
import { 
  Eye, Code, Brain, Zap, AlertCircle, CheckCircle, 
  ChevronRight, ChevronDown, Copy, Download, RefreshCw 
} from 'lucide-react';

interface FieldMappingDebuggerProps {
  templateId: string;
  manufacturerId: string;
  sampleData?: Record<string, any>;
  onMappingComplete?: (mappedData: any) => void;
}

export const FieldMappingDebugger: React.FC<FieldMappingDebuggerProps> = ({
  templateId,
  manufacturerId,
  sampleData = {},
  onMappingComplete
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [mappingResult, setMappingResult] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set(['coverage', 'preview']));
  const [useAI, setUseAI] = useState(true);

  const toggleSection = (section: string) => {
    const newExpanded = new Set(expandedSections);
    if (newExpanded.has(section)) {
      newExpanded.delete(section);
    } else {
      newExpanded.add(section);
    }
    setExpandedSections(newExpanded);
  };

  const testMapping = async () => {
    setIsLoading(true);
    setError(null);
    
    try {
      const response = await axios.post('/api/v1/docuseal/preview-mapping', {
        template_id: templateId,
        form_data: sampleData,
        use_ai: useAI
      });

      setMappingResult(response.data);
      
      if (onMappingComplete && response.data.mapped_data) {
        onMappingComplete(response.data.mapped_data);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to preview mapping');
    } finally {
      setIsLoading(false);
    }
  };

  const copyToClipboard = (data: any) => {
    navigator.clipboard.writeText(JSON.stringify(data, null, 2));
  };

  const downloadAsJson = (data: any, filename: string) => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <div className="mb-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Field Mapping Debugger</h3>
        <p className="text-sm text-gray-600">
          Test and preview how your form data will be mapped to DocuSeal fields
        </p>
      </div>

      {/* Controls */}
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={useAI}
              onChange={(e) => setUseAI(e.target.checked)}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span className="text-sm font-medium text-gray-700 flex items-center gap-1">
              <Brain className="w-4 h-4 text-purple-600" />
              Use AI Mapping
            </span>
          </label>
        </div>
        
        <button
          onClick={testMapping}
          disabled={isLoading}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          {isLoading ? (
            <>
              <RefreshCw className="w-4 h-4 animate-spin" />
              Testing...
            </>
          ) : (
            <>
              <Zap className="w-4 h-4" />
              Test Mapping
            </>
          )}
        </button>
      </div>

      {/* Error Display */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex items-center gap-2 text-red-700">
            <AlertCircle className="w-5 h-5" />
            <span className="font-medium">Error</span>
          </div>
          <p className="mt-1 text-sm text-red-600">{error}</p>
        </div>
      )}

      {/* Results */}
      {mappingResult && (
        <div className="space-y-4">
          {/* Summary */}
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <p className="text-gray-600">Method</p>
                <p className="font-semibold flex items-center gap-1">
                  {mappingResult.mapping_method === 'AI' ? (
                    <>
                      <Brain className="w-4 h-4 text-purple-600" />
                      AI-Powered
                    </>
                  ) : (
                    <>
                      <Code className="w-4 h-4 text-gray-600" />
                      Static Rules
                    </>
                  )}
                </p>
              </div>
              <div>
                <p className="text-gray-600">Input Fields</p>
                <p className="font-semibold">{mappingResult.input_fields}</p>
              </div>
              <div>
                <p className="text-gray-600">Template Fields</p>
                <p className="font-semibold">{mappingResult.template_fields}</p>
              </div>
              <div>
                <p className="text-gray-600">Mapped Fields</p>
                <p className="font-semibold text-green-600">{mappingResult.mapped_fields}</p>
              </div>
            </div>
          </div>

          {/* Coverage Analysis */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={() => toggleSection('coverage')}
              className="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between text-left"
            >
              <span className="font-medium text-gray-900">Coverage Analysis</span>
              {expandedSections.has('coverage') ? (
                <ChevronDown className="w-5 h-5 text-gray-500" />
              ) : (
                <ChevronRight className="w-5 h-5 text-gray-500" />
              )}
            </button>
            
            {expandedSections.has('coverage') && mappingResult.coverage && (
              <div className="p-4 space-y-3">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Overall Coverage</p>
                    <div className="mt-1">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            className="h-full bg-blue-600 transition-all duration-300"
                            style={{ width: `${mappingResult.coverage.coverage_percentage}%` }}
                          />
                        </div>
                        <span className="text-sm font-medium text-gray-700">
                          {mappingResult.coverage.coverage_percentage}%
                        </span>
                      </div>
                    </div>
                  </div>
                  
                  <div>
                    <p className="text-sm text-gray-600">Required Fields Coverage</p>
                    <div className="mt-1">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            className={`h-full transition-all duration-300 ${
                              mappingResult.coverage.required_coverage_percentage === 100 
                                ? 'bg-green-600' 
                                : 'bg-yellow-600'
                            }`}
                            style={{ width: `${mappingResult.coverage.required_coverage_percentage}%` }}
                          />
                        </div>
                        <span className="text-sm font-medium text-gray-700">
                          {mappingResult.coverage.required_coverage_percentage}%
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
                
                {mappingResult.coverage.unmapped_input_fields.length > 0 && (
                  <div className="mt-3 p-3 bg-yellow-50 rounded-lg">
                    <p className="text-sm font-medium text-yellow-800 mb-1">
                      Unmapped Input Fields ({mappingResult.coverage.unmapped_input_fields.length})
                    </p>
                    <p className="text-xs text-yellow-700">
                      {mappingResult.coverage.unmapped_input_fields.join(', ')}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Field Preview */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={() => toggleSection('preview')}
              className="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between text-left"
            >
              <span className="font-medium text-gray-900">Field Preview</span>
              {expandedSections.has('preview') ? (
                <ChevronDown className="w-5 h-5 text-gray-500" />
              ) : (
                <ChevronRight className="w-5 h-5 text-gray-500" />
              )}
            </button>
            
            {expandedSections.has('preview') && mappingResult.preview && (
              <div className="p-4">
                <div className="max-h-96 overflow-y-auto">
                  <table className="w-full text-sm">
                    <thead className="text-left border-b border-gray-200">
                      <tr>
                        <th className="pb-2 font-medium text-gray-700">Field</th>
                        <th className="pb-2 font-medium text-gray-700">Type</th>
                        <th className="pb-2 font-medium text-gray-700">Required</th>
                        <th className="pb-2 font-medium text-gray-700">Mapped Value</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {mappingResult.preview.map((field: any, index: number) => (
                        <tr key={index} className={field.has_value ? '' : 'opacity-50'}>
                          <td className="py-2">
                            <div>
                              <p className="font-medium text-gray-900">{field.field_label}</p>
                              <p className="text-xs text-gray-500">{field.field_name}</p>
                            </div>
                          </td>
                          <td className="py-2 text-gray-600">{field.field_type}</td>
                          <td className="py-2">
                            {field.required ? (
                              <span className="text-red-600">Yes</span>
                            ) : (
                              <span className="text-gray-400">No</span>
                            )}
                          </td>
                          <td className="py-2">
                            {field.has_value ? (
                              <div className="flex items-center gap-2">
                                <CheckCircle className="w-4 h-4 text-green-600" />
                                <span className="text-gray-900 truncate max-w-xs" title={field.mapped_value}>
                                  {field.mapped_value}
                                </span>
                              </div>
                            ) : (
                              <span className="text-gray-400">Not mapped</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>

          {/* Raw Data */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={() => toggleSection('raw')}
              className="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between text-left"
            >
              <span className="font-medium text-gray-900">Raw Mapped Data</span>
              {expandedSections.has('raw') ? (
                <ChevronDown className="w-5 h-5 text-gray-500" />
              ) : (
                <ChevronRight className="w-5 h-5 text-gray-500" />
              )}
            </button>
            
            {expandedSections.has('raw') && mappingResult.mapped_data && (
              <div className="p-4">
                <div className="flex items-center justify-end gap-2 mb-3">
                  <button
                    onClick={() => copyToClipboard(mappingResult.mapped_data)}
                    className="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center gap-1"
                  >
                    <Copy className="w-3 h-3" />
                    Copy
                  </button>
                  <button
                    onClick={() => downloadAsJson(mappingResult.mapped_data, 'mapped-fields.json')}
                    className="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center gap-1"
                  >
                    <Download className="w-3 h-3" />
                    Download
                  </button>
                </div>
                <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs">
                  {JSON.stringify(mappingResult.mapped_data, null, 2)}
                </pre>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};