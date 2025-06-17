import React, { useEffect, useState, Fragment, useMemo, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import { 
  Upload, Loader2, CheckCircle, AlertCircle, X, ExternalLink, 
  FileEdit, Eye, Database, ChevronRight, Package, Settings,
  Zap, AlertTriangle, FileText, Hash, CheckSquare
} from 'lucide-react';

// Types
interface Template {
  id: string;
  template_name: string;
  docuseal_template_id: string;
  document_type: string;
  manufacturer_id: string;
  manufacturer_name?: string;
  is_active: boolean;
  is_default: boolean;
  field_mappings: Record<string, any>;
  extraction_metadata?: Record<string, any>;
  last_extracted_at?: string;
  field_discovery_status?: string;
  total_fields?: number;
  mapped_fields?: number;
  ivr_success_rate?: number;
  quick_request_enabled?: boolean;
}

interface FieldSuggestion {
  ivr_field_name: string;
  original_text: string;
  field_type: string;
  category: string;
  is_checkbox: boolean;
  suggested_mapping: string | null;
  mapping_type: string | null;
  confidence: number;
  is_mapped: boolean;
  current_mapping: any;
}

interface DiscoverySummary {
  total_fields: number;
  mapped_fields: number;
  suggested_fields: number;
  unmapped_fields: number;
  mapping_percentage: number;
  categories: Record<string, number>;
  has_product_checkboxes: boolean;
  has_multiple_npis: boolean;
}

// Main Component
export default function Templates() {
  // Core state
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  
  // Modal state
  const [activeModal, setActiveModal] = useState<'none' | 'configure' | 'preview'>('none');
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [modalStep, setModalStep] = useState<'overview' | 'discovery' | 'mapping' | 'embedded'>('overview');
  
  // Field discovery state
  const [discovering, setDiscovering] = useState(false);
  const [fieldSuggestions, setFieldSuggestions] = useState<FieldSuggestion[]>([]);
  const [discoverySummary, setDiscoverySummary] = useState<DiscoverySummary | null>(null);
  const [pendingChanges, setPendingChanges] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  // Group templates by manufacturer
  const groupedTemplates = useMemo(() => {
    const groups: Record<string, Template[]> = {};
    templates.forEach(template => {
      const mfg = template.manufacturer_id || 'Other';
      if (!groups[mfg]) groups[mfg] = [];
      groups[mfg].push(template);
    });
    return groups;
  }, [templates]);

  // Calculate template metrics
  const getTemplateMetrics = useCallback((template: Template) => {
    const totalFields = Object.keys(template.field_mappings || {}).length;
    const mappedFields = Object.values(template.field_mappings || {})
      .filter((mapping: any) => mapping?.local_field).length;
    const mappingPercentage = totalFields > 0 ? Math.round((mappedFields / totalFields) * 100) : 0;
    
    return {
      totalFields,
      mappedFields,
      mappingPercentage,
      isConfigured: mappingPercentage > 80,
      hasQuickRequest: template.extraction_metadata?.template_type === 'embedded_tags'
    };
  }, []);

  // API calls
  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get('/api/v1/docuseal/templates');
      setTemplates(response.data.templates || []);
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to load templates');
    } finally {
      setLoading(false);
    }
  };

  const syncTemplates = async () => {
    setSyncing(true);
    setError(null);
    try {
      const response = await axios.post('/api/v1/docuseal/templates/sync');
      setTemplates(response.data.templates || []);
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to sync templates');
    } finally {
      setSyncing(false);
    }
  };

  const discoverFields = async (template: Template, file: File) => {
    setDiscovering(true);
    setError(null);
    
    const formData = new FormData();
    formData.append('pdf', file);
    formData.append('template_id', template.id);
    formData.append('manufacturer_id', template.manufacturer_id);
    
    try {
      const response = await axios.post('/api/v1/docuseal/templates/extract-fields', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      if (response.data.success) {
        setFieldSuggestions(response.data.suggestions || []);
        setDiscoverySummary(response.data.summary);
        setModalStep('mapping');
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to discover fields');
    } finally {
      setDiscovering(false);
    }
  };

  const saveMappings = async () => {
    if (!selectedTemplate || Object.keys(pendingChanges).length === 0) return;
    
    setSaving(true);
    setError(null);
    
    const mappings = Object.entries(pendingChanges).map(([field, value]) => ({
      ivr_field_name: field,
      system_field: value,
      field_type: 'text',
      mapping_type: 'manual'
    }));
    
    try {
      const response = await axios.post(
        `/api/v1/docuseal/templates/${selectedTemplate.id}/update-mappings`,
        { mappings }
      );
      
      if (response.data.success) {
        setTemplates(prev => prev.map(t => 
          t.id === selectedTemplate.id ? response.data.template : t
        ));
        setPendingChanges({});
        setModalStep('overview');
        alert('Mappings saved successfully!');
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to save mappings');
    } finally {
      setSaving(false);
    }
  };

  // Modal handlers
  const openConfigureModal = (template: Template) => {
    setSelectedTemplate(template);
    setActiveModal('configure');
    setModalStep('overview');
    setPendingChanges({});
    
    // Load existing discovery data if available
    if (template.extraction_metadata?.field_suggestions) {
      setFieldSuggestions(template.extraction_metadata.field_suggestions);
      setDiscoverySummary(template.extraction_metadata.discovery_summary);
    }
  };

  const closeModal = () => {
    setActiveModal('none');
    setSelectedTemplate(null);
    setModalStep('overview');
    setFieldSuggestions([]);
    setDiscoverySummary(null);
    setPendingChanges({});
  };

  // Auto-mapping functions
  const applyAutoMappings = () => {
    const autoMappings: Record<string, string> = {};
    let count = 0;
    
    fieldSuggestions.forEach(suggestion => {
      if (!suggestion.is_mapped && suggestion.suggested_mapping && suggestion.confidence > 0.8) {
        autoMappings[suggestion.ivr_field_name] = suggestion.suggested_mapping;
        count++;
      }
    });
    
    setPendingChanges(prev => ({ ...prev, ...autoMappings }));
    if (count > 0) alert(`Applied ${count} high-confidence mappings!`);
  };

  useEffect(() => {
    fetchTemplates();
  }, []);

  // Render helpers
  const renderTemplateStatus = (template: Template) => {
    const metrics = getTemplateMetrics(template);
    
    if (metrics.hasQuickRequest) {
      return <span className="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Quick Request</span>;
    }
    if (metrics.isConfigured) {
      return <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Configured</span>;
    }
    if (metrics.totalFields > 0) {
      return <span className="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Needs Mapping</span>;
    }
    return <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Not Configured</span>;
  };

  return (
    <MainLayout>
      <Head title="DocuSeal Templates" />

      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <div className="bg-white shadow-sm border-b">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="py-6 flex items-center justify-between">
              <div>
                <h1 className="text-2xl font-bold text-gray-900">IVR Templates</h1>
                <p className="mt-1 text-sm text-gray-600">
                  Manage manufacturer IVR forms and Quick Request templates
                </p>
              </div>
              <button
                onClick={syncTemplates}
                disabled={syncing}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-60 flex items-center gap-2"
              >
                {syncing ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Syncing...
                  </>
                ) : (
                  <>
                    <ExternalLink className="w-4 h-4" />
                    Sync Templates
                  </>
                )}
              </button>
            </div>
          </div>
        </div>

        {/* Main Content */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {loading ? (
            <div className="text-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-blue-600 mx-auto mb-4" />
              <p className="text-gray-500">Loading templates...</p>
            </div>
          ) : error ? (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
              <AlertCircle className="w-5 h-5 text-red-600 mr-3" />
              <span className="text-red-800">{error}</span>
            </div>
          ) : Object.keys(groupedTemplates).length === 0 ? (
            <div className="text-center py-12">
              <FileText className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p className="text-gray-500">No templates found</p>
            </div>
          ) : (
            <div className="space-y-8">
              {Object.entries(groupedTemplates).map(([manufacturer, manufacturerTemplates]) => (
                <div key={manufacturer} className="bg-white rounded-lg shadow-sm overflow-hidden">
                  <div className="px-6 py-4 bg-gray-50 border-b">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <Package className="w-5 h-5 text-gray-600" />
                        <h2 className="text-lg font-semibold text-gray-900">{manufacturer}</h2>
                        <span className="text-sm text-gray-500">
                          {manufacturerTemplates.length} template{manufacturerTemplates.length !== 1 ? 's' : ''}
                        </span>
                      </div>
                      {manufacturerTemplates.some(t => getTemplateMetrics(t).hasQuickRequest) && (
                        <span className="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full flex items-center gap-1">
                          <Zap className="w-3 h-3" />
                          Quick Request Enabled
                        </span>
                      )}
                    </div>
                  </div>
                  
                  <div className="divide-y">
                    {manufacturerTemplates.map(template => {
                      const metrics = getTemplateMetrics(template);
                      
                      return (
                        <div key={template.id} className="px-6 py-4 hover:bg-gray-50 transition-colors">
                          <div className="flex items-center justify-between">
                            <div className="flex-1">
                              <div className="flex items-center gap-3">
                                <h3 className="font-medium text-gray-900">{template.template_name}</h3>
                                {renderTemplateStatus(template)}
                                {template.is_default && (
                                  <span className="text-xs text-gray-500">Default</span>
                                )}
                              </div>
                              <div className="mt-2 flex items-center gap-6 text-sm text-gray-600">
                                <span>Type: {template.document_type}</span>
                                {metrics.totalFields > 0 && (
                                  <span className="flex items-center gap-1">
                                    <Hash className="w-3 h-3" />
                                    {metrics.mappedFields}/{metrics.totalFields} fields mapped
                                    {metrics.mappingPercentage > 0 && (
                                      <span className="text-xs text-gray-500">
                                        ({metrics.mappingPercentage}%)
                                      </span>
                                    )}
                                  </span>
                                )}
                                {template.ivr_success_rate && (
                                  <span className="flex items-center gap-1">
                                    <CheckCircle className="w-3 h-3 text-green-600" />
                                    {template.ivr_success_rate}% success rate
                                  </span>
                                )}
                              </div>
                            </div>
                            
                            <div className="flex items-center gap-2">
                              <button
                                onClick={() => openConfigureModal(template)}
                                className="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Configure Template"
                              >
                                <Settings className="w-5 h-5" />
                              </button>
                              <button
                                onClick={() => window.open(`https://app.docuseal.com/templates/${template.docuseal_template_id}/edit`, '_blank')}
                                className="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                title="Edit in DocuSeal"
                              >
                                <FileEdit className="w-5 h-5" />
                              </button>
                              <button
                                onClick={() => window.open(`https://app.docuseal.com/templates/${template.docuseal_template_id}/submissions`, '_blank')}
                                className="p-2 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                title="View Submissions"
                              >
                                <Eye className="w-5 h-5" />
                              </button>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Configuration Modal */}
      <Transition appear show={activeModal === 'configure'} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={closeModal}>
          <Transition.Child
            as={Fragment}
            enter="ease-out duration-300"
            enterFrom="opacity-0"
            enterTo="opacity-100"
            leave="ease-in duration-200"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
          >
            <div className="fixed inset-0 bg-black bg-opacity-30" />
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
                <Dialog.Panel className="w-full max-w-5xl transform overflow-hidden rounded-xl bg-white shadow-xl transition-all">
                  {selectedTemplate && (
                    <>
                      {/* Modal Header */}
                      <div className="bg-gray-50 px-6 py-4 border-b">
                        <div className="flex items-center justify-between">
                          <div>
                            <Dialog.Title className="text-lg font-semibold text-gray-900">
                              Configure {selectedTemplate.template_name}
                            </Dialog.Title>
                            <p className="text-sm text-gray-600 mt-1">
                              {selectedTemplate.manufacturer_id} • {selectedTemplate.document_type}
                            </p>
                          </div>
                          <button onClick={closeModal} className="text-gray-400 hover:text-gray-600">
                            <X className="w-5 h-5" />
                          </button>
                        </div>
                      </div>

                      {/* Step Navigation */}
                      <div className="flex border-b">
                        {['overview', 'discovery', 'mapping', 'embedded'].map((step) => (
                          <button
                            key={step}
                            onClick={() => setModalStep(step as any)}
                            className={`flex-1 px-6 py-3 text-sm font-medium capitalize transition-colors ${
                              modalStep === step
                                ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50'
                                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                            }`}
                          >
                            {step === 'embedded' ? 'Quick Request' : step}
                          </button>
                        ))}
                      </div>

                      {/* Modal Content */}
                      <div className="p-6 max-h-[60vh] overflow-y-auto">
                        {error && (
                          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center text-red-700">
                            <AlertCircle className="w-5 h-5 mr-2 flex-shrink-0" />
                            {error}
                          </div>
                        )}

                        {/* Overview Step */}
                        {modalStep === 'overview' && (
                          <div className="space-y-6">
                            <div className="grid grid-cols-2 gap-6">
                              <div className="bg-gray-50 rounded-lg p-4">
                                <h4 className="font-medium text-gray-900 mb-3">Template Information</h4>
                                <dl className="space-y-2 text-sm">
                                  <div className="flex justify-between">
                                    <dt className="text-gray-600">DocuSeal ID:</dt>
                                    <dd className="font-mono text-gray-900">{selectedTemplate.docuseal_template_id}</dd>
                                  </div>
                                  <div className="flex justify-between">
                                    <dt className="text-gray-600">Status:</dt>
                                    <dd>{selectedTemplate.is_active ? 
                                      <span className="text-green-600">Active</span> : 
                                      <span className="text-gray-400">Inactive</span>
                                    }</dd>
                                  </div>
                                  <div className="flex justify-between">
                                    <dt className="text-gray-600">Created:</dt>
                                    <dd>{new Date(selectedTemplate.last_extracted_at || '').toLocaleDateString()}</dd>
                                  </div>
                                </dl>
                              </div>

                              <div className="bg-blue-50 rounded-lg p-4">
                                <h4 className="font-medium text-blue-900 mb-3">Field Mapping Status</h4>
                                <div className="space-y-3">
                                  {(() => {
                                    const metrics = getTemplateMetrics(selectedTemplate);
                                    return (
                                      <>
                                        <div className="flex justify-between items-center">
                                          <span className="text-sm text-blue-700">Total Fields</span>
                                          <span className="font-semibold text-blue-900">{metrics.totalFields}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                          <span className="text-sm text-blue-700">Mapped Fields</span>
                                          <span className="font-semibold text-blue-900">{metrics.mappedFields}</span>
                                        </div>
                                        <div className="mt-3 pt-3 border-t border-blue-200">
                                          <div className="flex justify-between items-center">
                                            <span className="text-sm font-medium text-blue-700">Completion</span>
                                            <span className="font-bold text-blue-900">{metrics.mappingPercentage}%</span>
                                          </div>
                                          <div className="mt-2 bg-blue-200 rounded-full h-2 overflow-hidden">
                                            <div 
                                              className="bg-blue-600 h-full transition-all duration-500"
                                              style={{ width: `${metrics.mappingPercentage}%` }}
                                            />
                                          </div>
                                        </div>
                                      </>
                                    );
                                  })()}
                                </div>
                              </div>
                            </div>

                            <div className="border-t pt-6 flex justify-center">
                              <button
                                onClick={() => setModalStep('discovery')}
                                className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                              >
                                Start Field Discovery
                                <ChevronRight className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        )}

                        {/* Discovery Step */}
                        {modalStep === 'discovery' && (
                          <div className="space-y-6">
                            <div className="text-center">
                              <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Upload IVR PDF for Field Discovery
                              </h3>
                              <p className="text-sm text-gray-600 mb-6">
                                Upload the manufacturer's IVR form to automatically discover and map fields
                              </p>
                            </div>

                            <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                              <input
                                type="file"
                                accept=".pdf"
                                onChange={(e) => {
                                  const file = e.target.files?.[0];
                                  if (file && selectedTemplate) {
                                    discoverFields(selectedTemplate, file);
                                  }
                                }}
                                className="hidden"
                                id="pdf-upload"
                                disabled={discovering}
                              />
                              <label htmlFor="pdf-upload" className="cursor-pointer">
                                {discovering ? (
                                  <>
                                    <Loader2 className="w-12 h-12 text-blue-500 animate-spin mx-auto mb-4" />
                                    <p className="text-gray-600">Analyzing PDF...</p>
                                  </>
                                ) : (
                                  <>
                                    <Upload className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                                    <p className="text-gray-600 mb-2">Drop PDF here or click to browse</p>
                                    <p className="text-sm text-gray-500">
                                      Supports {selectedTemplate.manufacturer_id} IVR forms
                                    </p>
                                  </>
                                )}
                              </label>
                            </div>

                            {fieldSuggestions.length > 0 && (
                              <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <p className="text-green-800 flex items-center gap-2">
                                  <CheckCircle className="w-5 h-5" />
                                  Previous discovery found {fieldSuggestions.length} fields
                                </p>
                                <button
                                  onClick={() => setModalStep('mapping')}
                                  className="mt-2 text-sm text-green-700 underline hover:no-underline"
                                >
                                  View existing mappings →
                                </button>
                              </div>
                            )}
                          </div>
                        )}

                        {/* Mapping Step */}
                        {modalStep === 'mapping' && fieldSuggestions.length > 0 && (
                          <div className="space-y-6">
                            {discoverySummary && (
                              <div className="bg-gray-50 rounded-lg p-4">
                                <div className="grid grid-cols-4 gap-4 text-center">
                                  <div>
                                    <p className="text-2xl font-bold text-gray-900">{discoverySummary.total_fields}</p>
                                    <p className="text-sm text-gray-600">Total Fields</p>
                                  </div>
                                  <div>
                                    <p className="text-2xl font-bold text-green-600">{discoverySummary.mapped_fields}</p>
                                    <p className="text-sm text-gray-600">Mapped</p>
                                  </div>
                                  <div>
                                    <p className="text-2xl font-bold text-blue-600">{discoverySummary.suggested_fields}</p>
                                    <p className="text-sm text-gray-600">Suggestions</p>
                                  </div>
                                  <div>
                                    <p className="text-2xl font-bold text-red-600">{discoverySummary.unmapped_fields}</p>
                                    <p className="text-sm text-gray-600">Unmapped</p>
                                  </div>
                                </div>
                              </div>
                            )}

                            <div className="flex justify-between items-center">
                              <h4 className="font-medium text-gray-900">Field Mappings</h4>
                              <button
                                onClick={applyAutoMappings}
                                className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                              >
                                Auto-Map High Confidence
                              </button>
                            </div>

                            <div className="overflow-x-auto">
                              <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                  <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IVR Field</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Suggested</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Confidence</th>
                                  </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-100">
                                  {fieldSuggestions.map((suggestion) => {
                                    const hasChange = pendingChanges[suggestion.ivr_field_name] !== undefined;
                                    
                                    return (
                                      <tr key={suggestion.ivr_field_name} className={hasChange ? 'bg-yellow-50' : ''}>
                                        <td className="px-3 py-2 text-sm">
                                          {suggestion.is_checkbox && <CheckSquare className="w-3 h-3 inline mr-1" />}
                                          {suggestion.ivr_field_name}
                                        </td>
                                        <td className="px-3 py-2">
                                          <span className={`px-2 py-1 text-xs rounded-full ${
                                            suggestion.field_type === 'checkbox' ? 'bg-purple-100 text-purple-800' :
                                            suggestion.field_type === 'date' ? 'bg-blue-100 text-blue-800' :
                                            'bg-gray-100 text-gray-800'
                                          }`}>
                                            {suggestion.field_type}
                                          </span>
                                        </td>
                                        <td className="px-3 py-2 text-sm">
                                          {suggestion.is_mapped ? (
                                            <span className="text-green-600 font-mono text-xs">
                                              {suggestion.current_mapping?.local_field}
                                            </span>
                                          ) : (
                                            <span className="text-gray-400">unmapped</span>
                                          )}
                                        </td>
                                        <td className="px-3 py-2">
                                          <input
                                            type="text"
                                            value={pendingChanges[suggestion.ivr_field_name] ?? suggestion.suggested_mapping ?? ''}
                                            onChange={(e) => setPendingChanges(prev => ({
                                              ...prev,
                                              [suggestion.ivr_field_name]: e.target.value
                                            }))}
                                            placeholder="Enter mapping..."
                                            className="w-full px-2 py-1 text-xs border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                          />
                                        </td>
                                        <td className="px-3 py-2 text-sm text-center">
                                          {suggestion.confidence > 0 && (
                                            <span className={`font-semibold ${
                                              suggestion.confidence > 0.8 ? 'text-green-600' :
                                              suggestion.confidence > 0.6 ? 'text-yellow-600' :
                                              'text-red-600'
                                            }`}>
                                              {Math.round(suggestion.confidence * 100)}%
                                            </span>
                                          )}
                                        </td>
                                      </tr>
                                    );
                                  })}
                                </tbody>
                              </table>
                            </div>

                            <div className="flex justify-end gap-3">
                              <button
                                onClick={() => setModalStep('discovery')}
                                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                              >
                                Back to Discovery
                              </button>
                              <button
                                onClick={saveMappings}
                                disabled={saving || Object.keys(pendingChanges).length === 0}
                                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                              >
                                {saving ? 'Saving...' : 'Save Mappings'}
                              </button>
                            </div>
                          </div>
                        )}

                        {/* Quick Request Step */}
                        {modalStep === 'embedded' && (
                          <div className="space-y-6">
                            <div className="bg-green-50 rounded-lg p-6">
                              <h3 className="text-lg font-semibold text-green-900 mb-4">
                                Quick Request Configuration
                              </h3>
                              <p className="text-sm text-green-800 mb-4">
                                This template supports embedded field tags for Quick Request IVR generation.
                              </p>
                              
                              <div className="grid grid-cols-2 gap-6">
                                <div>
                                  <h4 className="font-medium text-green-900 mb-2">Standard Fields</h4>
                                  <div className="space-y-1 text-xs font-mono bg-white rounded p-3">
                                    <div>{`{patient_first_name}`}</div>
                                    <div>{`{patient_last_name}`}</div>
                                    <div>{`{patient_dob}`}</div>
                                    <div>{`{patient_member_id}`}</div>
                                    <div>{`{provider_name}`}</div>
                                    <div>{`{provider_npi}`}</div>
                                    <div>{`{product_name}`}</div>
                                    <div>{`{product_code}`}</div>
                                  </div>
                                </div>
                                
                                <div>
                                  <h4 className="font-medium text-green-900 mb-2">Checkbox Fields</h4>
                                  <div className="space-y-1 text-xs font-mono bg-white rounded p-3 text-purple-600">
                                    <div>{`{failed_conservative_treatment}`}</div>
                                    <div>{`{information_accurate}`}</div>
                                    <div>{`{medical_necessity_established}`}</div>
                                    <div>{`{maintain_documentation}`}</div>
                                    <div>{`{authorize_prior_auth}`}</div>
                                  </div>
                                </div>
                              </div>

                              <div className="mt-6 flex gap-3">
                                <button
                                  onClick={() => window.open('/docs/quick-request-guide.pdf', '_blank')}
                                  className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                                >
                                  View Field Guide
                                </button>
                                <button
                                  onClick={() => {/* Test submission logic */}}
                                  className="px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50"
                                >
                                  Test Submission
                                </button>
                              </div>
                            </div>
                          </div>
                        )}
                      </div>
                    </>
                  )}
                </Dialog.Panel>
              </Transition.Child>
            </div>
          </div>
        </Dialog>
      </Transition>
    </MainLayout>
  );
}