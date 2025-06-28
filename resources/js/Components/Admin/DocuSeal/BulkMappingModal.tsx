import React, { useState } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import axios from 'axios';
import {
  X, Layers, Copy, RefreshCw, Zap, AlertTriangle,
  ArrowRight, FileText, Wand2, CheckCircle
} from 'lucide-react';
import { TransformationRule, AVAILABLE_TRANSFORMATION_RULES } from '@/types/field-mapping';

interface BulkMappingModalProps {
  show: boolean;
  onClose: () => void;
  templateId: string;
  onComplete: () => void;
}

interface Template {
  id: string;
  template_name: string;
  manufacturer?: {
    name: string;
  };
}

export const BulkMappingModal: React.FC<BulkMappingModalProps> = ({
  show,
  onClose,
  templateId,
  onComplete
}) => {
  const [operation, setOperation] = useState<string>('');
  const [processing, setProcessing] = useState(false);
  const [result, setResult] = useState<any>(null);
  
  // Operation-specific state
  const [pattern, setPattern] = useState('');
  const [canonicalFieldId, setCanonicalFieldId] = useState<number | null>(null);
  const [sourceTemplateId, setSourceTemplateId] = useState<string>('');
  const [category, setCategory] = useState<string>('');
  const [transformationRules, setTransformationRules] = useState<TransformationRule[]>([]);
  const [fieldNames, setFieldNames] = useState<string[]>([]);
  const [overwrite, setOverwrite] = useState(false);
  
  // Available templates for copy operation
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loadingTemplates, setLoadingTemplates] = useState(false);

  // Fetch templates when copy operation is selected
  React.useEffect(() => {
    if (operation === 'copy_from_template' && templates.length === 0) {
      fetchTemplates();
    }
  }, [operation]);

  const fetchTemplates = async () => {
    setLoadingTemplates(true);
    try {
      const response = await axios.get('/api/v1/admin/docuseal/templates');
      setTemplates(response.data.templates.filter((t: Template) => t.id !== templateId));
    } catch (error) {
      console.error('Failed to fetch templates:', error);
    } finally {
      setLoadingTemplates(false);
    }
  };

  const executeBulkOperation = async () => {
    if (!operation) return;

    setProcessing(true);
    setResult(null);

    const parameters: Record<string, any> = {};

    switch (operation) {
      case 'map_by_pattern':
        parameters.pattern = pattern;
        parameters.canonical_field_id = canonicalFieldId;
        parameters.transformation_rules = transformationRules;
        break;
      
      case 'copy_from_template':
        parameters.source_template_id = sourceTemplateId;
        parameters.overwrite = overwrite;
        break;
      
      case 'reset_category':
        parameters.category = category;
        break;
      
      case 'apply_transformation':
        parameters.field_names = fieldNames;
        parameters.transformation_rules = transformationRules;
        break;
    }

    try {
      const response = await axios.post(
        `/api/v1/admin/docuseal/templates/${templateId}/field-mappings/bulk`,
        {
          operation,
          parameters
        }
      );

      setResult(response.data);
      
      // Refresh parent data
      setTimeout(() => {
        onComplete();
        handleClose();
      }, 2000);

    } catch (error: any) {
      setResult({
        success: false,
        message: error.response?.data?.message || 'Operation failed'
      });
    } finally {
      setProcessing(false);
    }
  };

  const handleClose = () => {
    // Reset state
    setOperation('');
    setPattern('');
    setCanonicalFieldId(null);
    setSourceTemplateId('');
    setCategory('');
    setTransformationRules([]);
    setFieldNames([]);
    setOverwrite(false);
    setResult(null);
    
    onClose();
  };

  const addTransformationRule = () => {
    setTransformationRules([...transformationRules, {
      type: 'format',
      operation: 'uppercase',
      parameters: {}
    }]);
  };

  const updateTransformationRule = (index: number, updates: Partial<TransformationRule>) => {
    const newRules = [...transformationRules];
    newRules[index] = { ...newRules[index], ...updates };
    setTransformationRules(newRules);
  };

  const removeTransformationRule = (index: number) => {
    setTransformationRules(transformationRules.filter((_, i) => i !== index));
  };

  return (
    <Transition appear show={show} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={handleClose}>
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
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all">
                <div className="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-4 border-b border-gray-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <Layers className="w-5 h-5 text-indigo-600" />
                      </div>
                      <div>
                        <Dialog.Title className="text-xl font-bold text-gray-900">
                          Bulk Mapping Operations
                        </Dialog.Title>
                        <p className="text-sm text-gray-600 mt-1">
                          Apply mapping changes to multiple fields at once
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={handleClose}
                      className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                <div className="p-6">
                  {/* Operation Selection */}
                  <div className="mb-6">
                    <label className="block text-sm font-medium text-gray-700 mb-3">
                      Select Operation
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                      <button
                        onClick={() => setOperation('map_by_pattern')}
                        className={`p-4 rounded-lg border-2 transition-all text-left ${
                          operation === 'map_by_pattern'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <div className="flex items-start gap-3">
                          <Wand2 className="w-5 h-5 text-indigo-600 mt-0.5" />
                          <div>
                            <h4 className="font-semibold text-gray-900">Map by Pattern</h4>
                            <p className="text-sm text-gray-600 mt-1">
                              Map fields matching a pattern to a canonical field
                            </p>
                          </div>
                        </div>
                      </button>

                      <button
                        onClick={() => setOperation('copy_from_template')}
                        className={`p-4 rounded-lg border-2 transition-all text-left ${
                          operation === 'copy_from_template'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <div className="flex items-start gap-3">
                          <Copy className="w-5 h-5 text-indigo-600 mt-0.5" />
                          <div>
                            <h4 className="font-semibold text-gray-900">Copy from Template</h4>
                            <p className="text-sm text-gray-600 mt-1">
                              Copy mappings from another template
                            </p>
                          </div>
                        </div>
                      </button>

                      <button
                        onClick={() => setOperation('reset_category')}
                        className={`p-4 rounded-lg border-2 transition-all text-left ${
                          operation === 'reset_category'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <div className="flex items-start gap-3">
                          <RefreshCw className="w-5 h-5 text-indigo-600 mt-0.5" />
                          <div>
                            <h4 className="font-semibold text-gray-900">Reset Category</h4>
                            <p className="text-sm text-gray-600 mt-1">
                              Clear all mappings in a category
                            </p>
                          </div>
                        </div>
                      </button>

                      <button
                        onClick={() => setOperation('apply_transformation')}
                        className={`p-4 rounded-lg border-2 transition-all text-left ${
                          operation === 'apply_transformation'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <div className="flex items-start gap-3">
                          <Zap className="w-5 h-5 text-indigo-600 mt-0.5" />
                          <div>
                            <h4 className="font-semibold text-gray-900">Apply Transformation</h4>
                            <p className="text-sm text-gray-600 mt-1">
                              Add transformation rules to multiple fields
                            </p>
                          </div>
                        </div>
                      </button>
                    </div>
                  </div>

                  {/* Operation-specific inputs */}
                  {operation === 'map_by_pattern' && (
                    <div className="space-y-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Field Pattern (Regex)
                        </label>
                        <input
                          type="text"
                          value={pattern}
                          onChange={(e) => setPattern(e.target.value)}
                          placeholder="e.g., physician.*npi"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p className="text-xs text-gray-500 mt-1">
                          Use regular expressions to match field names
                        </p>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Target Canonical Field ID
                        </label>
                        <input
                          type="number"
                          value={canonicalFieldId || ''}
                          onChange={(e) => setCanonicalFieldId(e.target.value ? parseInt(e.target.value) : null)}
                          placeholder="Enter canonical field ID"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Transformation Rules (Optional)
                        </label>
                        <TransformationRulesBuilder
                          rules={transformationRules}
                          onAdd={addTransformationRule}
                          onUpdate={updateTransformationRule}
                          onRemove={removeTransformationRule}
                        />
                      </div>
                    </div>
                  )}

                  {operation === 'copy_from_template' && (
                    <div className="space-y-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Source Template
                        </label>
                        {loadingTemplates ? (
                          <div className="text-center py-4">
                            <RefreshCw className="w-6 h-6 animate-spin text-indigo-600 mx-auto" />
                          </div>
                        ) : (
                          <select
                            value={sourceTemplateId}
                            onChange={(e) => setSourceTemplateId(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          >
                            <option value="">Select a template...</option>
                            {templates.map(template => (
                              <option key={template.id} value={template.id}>
                                {template.template_name} ({template.manufacturer?.name || 'Unknown'})
                              </option>
                            ))}
                          </select>
                        )}
                      </div>

                      <div className="flex items-center gap-3">
                        <input
                          type="checkbox"
                          id="overwrite"
                          checked={overwrite}
                          onChange={(e) => setOverwrite(e.target.checked)}
                          className="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        />
                        <label htmlFor="overwrite" className="text-sm text-gray-700">
                          Overwrite existing mappings
                        </label>
                      </div>
                    </div>
                  )}

                  {operation === 'reset_category' && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Category to Reset
                      </label>
                      <input
                        type="text"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        placeholder="e.g., patientInformation"
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      />
                      <p className="text-xs text-red-600 mt-1 flex items-center gap-1">
                        <AlertTriangle className="w-3 h-3" />
                        This will remove all mappings in this category
                      </p>
                    </div>
                  )}

                  {operation === 'apply_transformation' && (
                    <div className="space-y-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Field Names (comma-separated)
                        </label>
                        <textarea
                          value={fieldNames.join(', ')}
                          onChange={(e) => setFieldNames(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                          placeholder="field1, field2, field3"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          rows={3}
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Transformation Rules
                        </label>
                        <TransformationRulesBuilder
                          rules={transformationRules}
                          onAdd={addTransformationRule}
                          onUpdate={updateTransformationRule}
                          onRemove={removeTransformationRule}
                        />
                      </div>
                    </div>
                  )}

                  {/* Result Display */}
                  {result && (
                    <div className={`mt-4 p-4 rounded-lg ${
                      result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
                    }`}>
                      <div className="flex items-start gap-3">
                        {result.success ? (
                          <CheckCircle className="w-5 h-5 text-green-600 mt-0.5" />
                        ) : (
                          <AlertTriangle className="w-5 h-5 text-red-600 mt-0.5" />
                        )}
                        <div className="flex-1">
                          <p className={`font-medium ${
                            result.success ? 'text-green-900' : 'text-red-900'
                          }`}>
                            {result.message}
                          </p>
                          {result.affected_fields > 0 && (
                            <p className="text-sm text-gray-600 mt-1">
                              {result.affected_fields} field{result.affected_fields !== 1 ? 's' : ''} updated
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* Footer */}
                <div className="bg-gray-50 px-6 py-4 border-t border-gray-200">
                  <div className="flex items-center justify-between">
                    <button
                      onClick={handleClose}
                      className="px-4 py-2 text-gray-700 hover:text-gray-900 transition-colors"
                    >
                      Cancel
                    </button>
                    
                    <button
                      onClick={executeBulkOperation}
                      disabled={!operation || processing}
                      className="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                      {processing ? (
                        <>
                          <RefreshCw className="w-4 h-4 animate-spin" />
                          Processing...
                        </>
                      ) : (
                        <>
                          <ArrowRight className="w-4 h-4" />
                          Execute Operation
                        </>
                      )}
                    </button>
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

// Transformation Rules Builder Component
interface TransformationRulesBuilderProps {
  rules: TransformationRule[];
  onAdd: () => void;
  onUpdate: (index: number, updates: Partial<TransformationRule>) => void;
  onRemove: (index: number) => void;
}

const TransformationRulesBuilder: React.FC<TransformationRulesBuilderProps> = ({
  rules,
  onAdd,
  onUpdate,
  onRemove
}) => {
  return (
    <div className="space-y-2">
      {rules.map((rule, index) => (
        <div key={index} className="flex items-center gap-2">
          <select
            value={rule.type}
            onChange={(e) => onUpdate(index, { type: e.target.value as any, operation: '' })}
            className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded"
          >
            {Object.keys(AVAILABLE_TRANSFORMATION_RULES).map(type => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>

          <select
            value={rule.operation}
            onChange={(e) => onUpdate(index, { operation: e.target.value })}
            className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded"
          >
            <option value="">Select operation...</option>
            {Object.entries(AVAILABLE_TRANSFORMATION_RULES[rule.type]?.operations || {}).map(([op, desc]) => (
              <option key={op} value={op}>{op}</option>
            ))}
          </select>

          <button
            onClick={() => onRemove(index)}
            className="p-1 text-red-600 hover:text-red-700"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      ))}

      <button
        onClick={onAdd}
        className="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-gray-400 hover:text-gray-700 transition-colors"
      >
        + Add Transformation Rule
      </button>
    </div>
  );
};