import React, { useEffect, useState, Fragment } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import { Upload, Loader2, CheckCircle, AlertCircle, X, ExternalLink, FileEdit, Eye, Database } from 'lucide-react';
import { DocuSealTemplateViewer } from '@/Components/DocuSeal/DocuSealTemplateViewer';

interface Template {
  id: string;
  template_name: string;
  docuseal_template_id: string;
  document_type: string;
  manufacturer_id: string;
  is_active: boolean;
  is_default: boolean;
  field_mappings: Record<string, any>;
  extraction_metadata?: Record<string, any>;
  last_extracted_at?: string;
  field_discovery_status?: string;
}

interface ExtractedField {
  field_name: string;
  original_text: string;
  field_type: string;
  extracted_value: string | null;
  confidence: number;
  category: string;
  is_checkbox: boolean;
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

interface FormMetadata {
  manufacturer: string;
  form_type: string;
  detected_products: Array<{
    code: string;
    name: string;
    confidence: number;
  }>;
  form_title: string | null;
  form_version: string | null;
  extraction_date: string;
}

export default function Templates() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [showPreviewModal, setShowPreviewModal] = useState(false);
  const [previewTemplate, setPreviewTemplate] = useState<Template | null>(null);
  
  // Field discovery states
  const [activeTab, setActiveTab] = useState<'current' | 'discovery'>('current');
  const [pdfFile, setPdfFile] = useState<File | null>(null);
  const [extracting, setExtracting] = useState(false);
  const [extractedFields, setExtractedFields] = useState<ExtractedField[]>([]);
  const [fieldSuggestions, setFieldSuggestions] = useState<FieldSuggestion[]>([]);
  const [discoverySummary, setDiscoverySummary] = useState<DiscoverySummary | null>(null);
  const [formMetadata, setFormMetadata] = useState<FormMetadata | null>(null);
  const [savingMappings, setSavingMappings] = useState(false);
  const [editedMappings, setEditedMappings] = useState<Record<string, string>>({});
  const [syncingDocuSeal, setSyncingDocuSeal] = useState(false);
  const [editingMetadata, setEditingMetadata] = useState(false);
  const [editedMetadata, setEditedMetadata] = useState<Partial<FormMetadata> | null>(null);

  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get('/api/v1/docuseal/templates');
      setTemplates(response.data.templates || []);
    } catch (e: any) {
      console.error('Error fetching templates:', e);
      console.error('Error response:', e.response);
      console.error('Error status:', e.response?.status);
      console.error('Error data:', e.response?.data);
      
      // Set a more specific error message
      if (e.response?.status === 401) {
        setError('Authentication failed. Please log in again.');
      } else if (e.response?.status === 403) {
        setError('Access denied. You need manage-orders permission or msc-admin role.');
      } else if (e.response?.status === 500) {
        setError(e.response?.data?.message || 'Server error. Please check if DocuSeal API key is configured.');
      } else {
        setError(e.response?.data?.message || e.message || 'Failed to load templates');
      }
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
      console.error('Error syncing templates:', e);
      setError(e.response?.data?.message || e.message || 'Failed to sync templates');
    } finally {
      setSyncing(false);
    }
  };

  const openFieldModal = (tpl: Template) => {
    setSelectedTemplate(tpl);
    setShowModal(true);
    
    // Load existing field discovery data if available
    if (tpl.extraction_metadata?.last_extraction) {
      const extraction = tpl.extraction_metadata.last_extraction;
      setFormMetadata(extraction);
      
      // Load field suggestions from extraction metadata if available
      if (tpl.extraction_metadata.field_suggestions) {
        setFieldSuggestions(tpl.extraction_metadata.field_suggestions);
        setDiscoverySummary(tpl.extraction_metadata.discovery_summary);
        setActiveTab('discovery');
      }
    }
  };

  const closeFieldModal = () => {
    setShowModal(false);
    setSelectedTemplate(null);
    // Reset field discovery states
    setActiveTab('current');
    setPdfFile(null);
    setExtractedFields([]);
    setFieldSuggestions([]);
    setDiscoverySummary(null);
    setFormMetadata(null);
    setEditedMappings({});
    setEditingMetadata(false);
    setEditedMetadata(null);
  };

  const viewTemplateInDocuSeal = (template: Template) => {
    // Show template in preview modal
    setPreviewTemplate(template);
    setShowPreviewModal(true);
  };

  const closePreviewModal = () => {
    setShowPreviewModal(false);
    setPreviewTemplate(null);
  };

  const editTemplateInDocuSeal = (template: Template) => {
    // Open DocuSeal template editor in new tab
    const docusealUrl = `https://app.docuseal.com/templates/${template.docuseal_template_id}/edit`;
    window.open(docusealUrl, '_blank');
  };

  const syncDocuSealFields = async () => {
    if (!selectedTemplate) return;
    
    setSyncingDocuSeal(true);
    setError(null);
    
    try {
      const response = await axios.post(`/api/v1/docuseal/templates/${selectedTemplate.id}/sync-fields`);
      
      if (response.data.success) {
        // Update the template in the list
        setTemplates(prev => prev.map(t => 
          t.id === selectedTemplate.id ? { ...t, field_mappings: response.data.field_mappings } : t
        ));
        
        // Update selected template
        setSelectedTemplate(prev => prev ? { ...prev, field_mappings: response.data.field_mappings } : null);
        
        alert(`Synced ${response.data.total_fields} fields from DocuSeal! New fields: ${response.data.new_fields.length}`);
      }
    } catch (e: any) {
      console.error('DocuSeal sync error:', e);
      setError(e.response?.data?.message || 'Failed to sync DocuSeal fields');
    } finally {
      setSyncingDocuSeal(false);
    }
  };

  const handlePdfUpload = async (file: File) => {
    if (!selectedTemplate) return;
    
    setPdfFile(file);
    setExtracting(true);
    setError(null);
    
    const formData = new FormData();
    formData.append('pdf', file);
    formData.append('template_id', selectedTemplate.id);
    formData.append('manufacturer_id', selectedTemplate.manufacturer_id);
    
    try {
      const response = await axios.post('/api/v1/docuseal/templates/extract-fields', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      if (response.data.success) {
        setExtractedFields(response.data.fields);
        setFieldSuggestions(response.data.suggestions);
        setDiscoverySummary(response.data.summary);
        setFormMetadata(response.data.metadata || null);
        
        // Save the discovery results to the template
        if (selectedTemplate) {
          const updatedTemplate = {
            ...selectedTemplate,
            extraction_metadata: {
              ...selectedTemplate.extraction_metadata,
              last_extraction: response.data.metadata,
              field_suggestions: response.data.suggestions,
              discovery_summary: response.data.summary,
              last_extracted_at: new Date().toISOString()
            }
          };
          setSelectedTemplate(updatedTemplate);
          setTemplates(prev => prev.map(t => 
            t.id === selectedTemplate.id ? updatedTemplate : t
          ));
        }
      } else {
        setError('Failed to extract fields from PDF');
      }
    } catch (e: any) {
      console.error('Field extraction error:', e);
      setError(e.response?.data?.message || 'Failed to extract fields');
    } finally {
      setExtracting(false);
    }
  };

  const handleMappingChange = (fieldName: string, value: string) => {
    setEditedMappings(prev => ({
      ...prev,
      [fieldName]: value
    }));
  };

  const saveMappings = async () => {
    if (!selectedTemplate) return;
    
    setSavingMappings(true);
    setError(null);
    
    // Prepare mappings to save
    const mappingsToSave = fieldSuggestions
      .filter(suggestion => {
        const editedValue = editedMappings[suggestion.ivr_field_name];
        return editedValue !== undefined && editedValue !== (suggestion.current_mapping?.local_field || '');
      })
      .map(suggestion => ({
        ivr_field_name: suggestion.ivr_field_name,
        system_field: editedMappings[suggestion.ivr_field_name],
        field_type: suggestion.field_type,
        mapping_type: 'manual'
      }));
    
    if (mappingsToSave.length === 0) {
      setError('No changes to save');
      setSavingMappings(false);
      return;
    }
    
    try {
      const response = await axios.post(
        `/api/v1/docuseal/templates/${selectedTemplate.id}/update-mappings`,
        { mappings: mappingsToSave }
      );
      
      if (response.data.success) {
        // Update the template in the list
        const updatedTemplate = response.data.template;
        setTemplates(prev => prev.map(t => 
          t.id === selectedTemplate.id ? updatedTemplate : t
        ));
        setSelectedTemplate(updatedTemplate);
        
        // Update field suggestions with new mappings
        const updatedSuggestions = fieldSuggestions.map(suggestion => {
          const fieldName = suggestion.ivr_field_name;
          const newMapping = updatedTemplate.field_mappings?.[fieldName];
          
          if (newMapping && newMapping.local_field) {
            return {
              ...suggestion,
              is_mapped: true,
              current_mapping: newMapping
            };
          }
          return suggestion;
        });
        setFieldSuggestions(updatedSuggestions);
        
        // Recalculate summary
        if (discoverySummary) {
          const newMappedCount = updatedSuggestions.filter(s => s.is_mapped).length;
          const newUnmappedCount = updatedSuggestions.filter(s => !s.is_mapped).length;
          const newSuggestedCount = updatedSuggestions.filter(s => !s.is_mapped && s.suggested_mapping).length;
          
          setDiscoverySummary({
            ...discoverySummary,
            mapped_fields: newMappedCount,
            unmapped_fields: newUnmappedCount,
            suggested_fields: newSuggestedCount,
            mapping_percentage: Math.round((newMappedCount / discoverySummary.total_fields) * 100)
          });
        }
        
        setEditedMappings({});
        // Show success message
        alert('Mappings saved successfully!');
      }
    } catch (e: any) {
      console.error('Save mappings error:', e);
      setError(e.response?.data?.message || 'Failed to save mappings');
    } finally {
      setSavingMappings(false);
    }
  };

  const applyAutoMappings = () => {
    const autoMappings: Record<string, string> = {};
    let mappedCount = 0;
    
    fieldSuggestions.forEach(suggestion => {
      if (!suggestion.is_mapped && suggestion.suggested_mapping && suggestion.confidence > 0.8) {
        autoMappings[suggestion.ivr_field_name] = suggestion.suggested_mapping;
        mappedCount++;
      }
    });
    
    setEditedMappings(prev => ({ ...prev, ...autoMappings }));
    
    if (mappedCount > 0) {
      alert(`Applied ${mappedCount} high-confidence mappings!`);
    } else {
      alert('No high-confidence mappings found to apply.');
    }
  };

  const groupFieldsByCategory = (suggestions: FieldSuggestion[]) => {
    const grouped: Record<string, FieldSuggestion[]> = {};
    
    suggestions.forEach(suggestion => {
      const category = suggestion.category || 'other';
      if (!grouped[category]) {
        grouped[category] = [];
      }
      grouped[category].push(suggestion);
    });
    
    // Sort categories for better display order
    const categoryOrder = ['product', 'provider', 'facility', 'patient', 'insurance', 'clinical', 'billing', 'authorization', 'contact', 'other'];
    const sortedGrouped: Record<string, FieldSuggestion[]> = {};
    
    categoryOrder.forEach(cat => {
      if (grouped[cat]) {
        sortedGrouped[cat] = grouped[cat];
      }
    });
    
    return sortedGrouped;
  };

  const detectAndApplyPatterns = () => {
    const patterns: Record<string, string[]> = {};
    const patternMappings: Record<string, string> = {};
    
    // Detect patterns like "Physician NPI 1", "Physician NPI 2", etc.
    fieldSuggestions.forEach(suggestion => {
      const match = suggestion.ivr_field_name.match(/^(.+?)\s+(\d+)$/);
      if (match) {
        const [, baseField, index] = match;
        if (!patterns[baseField]) {
          patterns[baseField] = [];
        }
        patterns[baseField].push(suggestion.ivr_field_name);
      }
    });
    
    // Apply pattern-based mappings
    Object.entries(patterns).forEach(([baseField, fields]) => {
      if (fields.length >= 2) { // Only apply if we have multiple fields in pattern
        fields.forEach(field => {
          const index = field.match(/\d+$/)?.[0];
          if (index) {
            // Generate mapping based on pattern
            if (baseField.includes('NPI') && baseField.includes('Physician')) {
              patternMappings[field] = `provider_npi_${index}`;
            } else if (baseField.includes('NPI') && baseField.includes('Facility')) {
              patternMappings[field] = `facility_npi_${index}`;
            } else if (baseField.includes('Phone')) {
              const prefix = baseField.toLowerCase().includes('physician') ? 'provider' : 'facility';
              patternMappings[field] = `${prefix}_phone_${index}`;
            }
          }
        });
      }
    });
    
    setEditedMappings(prev => ({ ...prev, ...patternMappings }));
    
    // Show success message
    if (Object.keys(patternMappings).length > 0) {
      alert(`Applied pattern mappings to ${Object.keys(patternMappings).length} fields!`);
    }
  };

  useEffect(() => {
    fetchTemplates();
  }, []);

  return (
    <MainLayout>
      <Head title="DocuSeal Templates" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white shadow-sm rounded-lg">
            <div className="p-6 border-b border-gray-200 flex items-center justify-between">
              <div>
                <h1 className="text-2xl font-bold text-gray-900">DocuSeal Templates</h1>
                <p className="mt-1 text-sm text-gray-600">
                  Manage document templates for automated signing workflows.
                </p>
              </div>
              <button
                className="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-60"
                onClick={syncTemplates}
                disabled={syncing}
              >
                {syncing ? 'Syncing...' : 'Sync Templates'}
              </button>
            </div>

            <div className="p-6">
              {loading ? (
                <div className="text-center text-gray-500">Loading templates...</div>
              ) : error ? (
                <div className="text-center text-red-600">{error}</div>
              ) : templates.length === 0 ? (
                <div className="text-center text-gray-500">No templates found.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead>
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Manufacturer</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fields</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-100">
                      {templates.map((tpl) => (
                        <tr key={tpl.id}>
                          <td className="px-4 py-2 font-semibold text-gray-900">{tpl.template_name}</td>
                          <td className="px-4 py-2 text-gray-700">{tpl.document_type}</td>
                          <td className="px-4 py-2 text-gray-700">{tpl.manufacturer_id}</td>
                          <td className="px-4 py-2">
                            <span className={tpl.is_active ? 'text-green-600 font-bold' : 'text-gray-400'}>
                              {tpl.is_active ? 'Yes' : 'No'}
                            </span>
                          </td>
                          <td className="px-4 py-2" onClick={() => openFieldModal(tpl)}>
                            <div className="flex items-center gap-2 cursor-pointer">
                              <span className="text-xs text-blue-600 underline">
                                {Object.keys(tpl.field_mappings || {}).length}
                              </span>
                              {tpl.extraction_metadata?.last_extraction && (
                                <div className="flex items-center gap-1">
                                  <Database className="w-3 h-3 text-green-600" title="Field discovery completed" />
                                  {tpl.extraction_metadata.last_extraction.detected_products?.length ? (
                                    <span className="text-xs text-gray-500">
                                      ({tpl.extraction_metadata.last_extraction.detected_products.length} products)
                                    </span>
                                  ) : null}
                                </div>
                              )}
                            </div>
                          </td>
                          <td className="px-4 py-2">
                            <div className="flex items-center gap-2">
                              <button
                                onClick={() => viewTemplateInDocuSeal(tpl)}
                                className="text-gray-600 hover:text-blue-600 transition"
                                title="View Template"
                              >
                                <Eye className="w-4 h-4" />
                              </button>
                              <button
                                onClick={() => editTemplateInDocuSeal(tpl)}
                                className="text-gray-600 hover:text-green-600 transition"
                                title="Edit in DocuSeal"
                              >
                                <FileEdit className="w-4 h-4" />
                              </button>
                              <button
                                onClick={() => window.open(`https://app.docuseal.com/templates/${tpl.docuseal_template_id}/submissions`, '_blank')}
                                className="text-gray-600 hover:text-purple-600 transition"
                                title="View Submissions"
                              >
                                <ExternalLink className="w-4 h-4" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
      {/* Field Mapping Modal */}
      <Transition appear show={showModal} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={closeFieldModal}>
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
                <Dialog.Panel className="w-full max-w-6xl transform overflow-hidden rounded-xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                  <div className="flex items-center justify-between mb-4">
                    <Dialog.Title as="h3" className="text-lg font-bold">
                      Field Mappings for {selectedTemplate?.template_name}
                    </Dialog.Title>
                    <button 
                      className="text-gray-400 hover:text-gray-700" 
                      onClick={closeFieldModal}
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  {/* Tabs */}
                  <div className="flex border-b mb-4">
                    <button
                      className={`px-4 py-2 font-medium text-sm ${
                        activeTab === 'current' 
                          ? 'text-blue-600 border-b-2 border-blue-600' 
                          : 'text-gray-500 hover:text-gray-700'
                      }`}
                      onClick={() => setActiveTab('current')}
                    >
                      Current Mappings ({Object.keys(selectedTemplate?.field_mappings || {}).length})
                    </button>
                    <button
                      className={`px-4 py-2 font-medium text-sm ${
                        activeTab === 'discovery' 
                          ? 'text-blue-600 border-b-2 border-blue-600' 
                          : 'text-gray-500 hover:text-gray-700'
                      }`}
                      onClick={() => setActiveTab('discovery')}
                    >
                      Field Discovery
                      {discoverySummary && (
                        <span className="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                          {discoverySummary.unmapped_fields} unmapped
                        </span>
                      )}
                    </button>
                  </div>

                  {error && (
                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center text-red-700">
                      <AlertCircle className="w-5 h-5 mr-2" />
                      {error}
                    </div>
                  )}
                  
                  {selectedTemplate && activeTab === 'current' ? (
                    <div className="space-y-4">
                      <div className="flex justify-between items-center">
                        <p className="text-sm text-gray-600">
                          These mappings connect DocuSeal template fields to your system fields.
                        </p>
                        <button
                          onClick={syncDocuSealFields}
                          disabled={syncingDocuSeal}
                          className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                        >
                          {syncingDocuSeal ? (
                            <>
                              <Loader2 className="w-4 h-4 animate-spin" />
                              Syncing...
                            </>
                          ) : (
                            <>
                              <ExternalLink className="w-4 h-4" />
                              Sync from DocuSeal
                            </>
                          )}
                        </button>
                      </div>
                      <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                        <thead>
                          <tr>
                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">DocuSeal Field</th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mapped Local Field</th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-100">
                          {Object.entries(selectedTemplate.field_mappings || {}).map(([field, mapping]: [string, any]) => (
                            <tr key={field}>
                              <td className="px-3 py-2 font-mono text-xs text-gray-900">{field}</td>
                              <td className="px-3 py-2 font-mono text-xs text-blue-700">
                                {mapping?.local_field || <span className="text-gray-400">(unmapped)</span>}
                              </td>
                              <td className="px-3 py-2 text-xs text-gray-600">{mapping?.type || 'text'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {/* Upload Section - Only show if no existing discovery data */}
                      {!extractedFields.length && !fieldSuggestions.length && (
                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-8">
                          <input
                            type="file"
                            accept=".pdf"
                            onChange={(e) => e.target.files?.[0] && handlePdfUpload(e.target.files[0])}
                            className="hidden"
                            id="pdf-upload"
                            disabled={extracting}
                          />
                          <label
                            htmlFor="pdf-upload"
                            className="cursor-pointer flex flex-col items-center"
                          >
                            {extracting ? (
                              <>
                                <Loader2 className="w-12 h-12 text-blue-500 animate-spin mb-4" />
                                <p className="text-gray-600">Extracting fields from PDF...</p>
                              </>
                            ) : (
                              <>
                                <Upload className="w-12 h-12 text-gray-400 mb-4" />
                                <p className="text-gray-600 text-center">
                                  Upload manufacturer IVR PDF to discover fields
                                </p>
                                <p className="text-sm text-gray-500 mt-2">
                                  Drop PDF here or click to browse
                                </p>
                              </>
                            )}
                          </label>
                        </div>
                      )}

                      {/* Form Metadata Section */}
                      {formMetadata && (
                        <div className="bg-blue-50 rounded-lg p-4 mb-4">
                          <div className="flex items-center justify-between mb-3">
                            <h4 className="font-semibold text-blue-900">Form Information</h4>
                            <button
                              onClick={() => {
                                setEditingMetadata(!editingMetadata);
                                if (!editingMetadata) {
                                  setEditedMetadata({
                                    form_type: formMetadata.form_type,
                                    manufacturer: formMetadata.manufacturer,
                                    detected_products: formMetadata.detected_products
                                  });
                                }
                              }}
                              className="text-sm px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                              {editingMetadata ? 'Cancel' : 'Edit'}
                            </button>
                          </div>
                          
                          {editingMetadata ? (
                            <div className="space-y-3">
                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-sm font-medium text-blue-700 mb-1">Form Type</label>
                                  <select
                                    value={editedMetadata?.form_type || formMetadata.form_type}
                                    onChange={(e) => setEditedMetadata(prev => ({ ...prev!, form_type: e.target.value }))}
                                    className="w-full px-3 py-2 border rounded-lg text-sm"
                                  >
                                    <option value="IVR">IVR</option>
                                    <option value="Order">Order</option>
                                    <option value="Onboarding">Onboarding</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="block text-sm font-medium text-blue-700 mb-1">Manufacturer</label>
                                  <input
                                    type="text"
                                    value={editedMetadata?.manufacturer || formMetadata.manufacturer}
                                    onChange={(e) => setEditedMetadata(prev => ({ ...prev!, manufacturer: e.target.value }))}
                                    className="w-full px-3 py-2 border rounded-lg text-sm"
                                  />
                                </div>
                              </div>
                              <div>
                                <label className="block text-sm font-medium text-blue-700 mb-1">Products (comma-separated)</label>
                                <textarea
                                  value={editedMetadata?.detected_products?.map(p => `${p.code}:${p.name}`).join(', ') || ''}
                                  onChange={(e) => {
                                    const input = e.target.value;
                                    if (!input.trim()) {
                                      setEditedMetadata(prev => ({ ...prev!, detected_products: [] }));
                                      return;
                                    }
                                    
                                    const products = input.split(',').map(p => {
                                      const trimmed = p.trim();
                                      if (!trimmed) return null;
                                      
                                      // Split by first colon only to allow colons in product names
                                      const colonIndex = trimmed.indexOf(':');
                                      if (colonIndex === -1) {
                                        // No colon, assume it's just a code
                                        return { code: trimmed, name: trimmed, confidence: 1.0 };
                                      }
                                      
                                      const code = trimmed.substring(0, colonIndex).trim();
                                      const name = trimmed.substring(colonIndex + 1).trim();
                                      
                                      return code ? { code, name: name || code, confidence: 1.0 } : null;
                                    }).filter(p => p !== null) as Array<{code: string, name: string, confidence: number}>;
                                    
                                    setEditedMetadata(prev => ({ ...prev!, detected_products: products }));
                                  }}
                                  placeholder="Q4205:Membrane Wrap, Q4239:Amnio-Maxx, Q4238:DermACELL AWM"
                                  className="w-full px-3 py-2 border rounded-lg text-sm"
                                  rows={3}
                                />
                                <p className="text-xs text-gray-600 mt-1">
                                  Format: CODE:Product Name (e.g., Q4205:Membrane Wrap)
                                </p>
                              </div>
                              <button
                                onClick={async () => {
                                  if (editedMetadata && selectedTemplate) {
                                    const updatedMetadata = {
                                      ...formMetadata,
                                      ...editedMetadata
                                    };
                                    setFormMetadata(updatedMetadata);
                                    
                                    // Update template locally
                                    const updatedTemplate = {
                                      ...selectedTemplate,
                                      extraction_metadata: {
                                        ...selectedTemplate.extraction_metadata,
                                        last_extraction: updatedMetadata
                                      }
                                    };
                                    setSelectedTemplate(updatedTemplate);
                                    setTemplates(prev => prev.map(t => 
                                      t.id === selectedTemplate.id ? updatedTemplate : t
                                    ));
                                    
                                    // Save to backend
                                    try {
                                      await axios.post(`/api/v1/docuseal/templates/${selectedTemplate.id}/update-metadata`, {
                                        metadata: updatedMetadata
                                      });
                                    } catch (error) {
                                      console.error('Failed to save metadata:', error);
                                    }
                                    
                                    setEditingMetadata(false);
                                    setEditedMetadata(null);
                                  }
                                }}
                                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                              >
                                Save Metadata
                              </button>
                            </div>
                          ) : (
                            <>
                              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                                <div>
                                  <p className="text-blue-700">Form Type</p>
                                  <p className="font-medium text-blue-900">{formMetadata.form_type}</p>
                                </div>
                                <div>
                                  <p className="text-blue-700">Manufacturer</p>
                                  <p className="font-medium text-blue-900">{formMetadata.manufacturer}</p>
                                </div>
                                <div>
                                  <p className="text-blue-700">Version</p>
                                  <p className="font-medium text-blue-900">{formMetadata.form_version || 'N/A'}</p>
                                </div>
                                <div>
                                  <p className="text-blue-700">Products</p>
                                  <p className="font-medium text-blue-900">{formMetadata.detected_products.length} detected</p>
                                </div>
                              </div>
                              {formMetadata.form_title && (
                                <p className="text-sm text-blue-800 mt-3">
                                  <span className="font-medium">Title:</span> {formMetadata.form_title}
                                </p>
                              )}
                              {formMetadata.detected_products.length > 0 && (
                                <div className="mt-3">
                                  <p className="text-sm font-medium text-blue-800 mb-1">Detected Products:</p>
                                  <div className="flex flex-wrap gap-2">
                                    {formMetadata.detected_products.map((product, idx) => (
                                      <span key={idx} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {product.code}: {product.name}
                                      </span>
                                    ))}
                                  </div>
                                </div>
                              )}
                            </>
                          )}
                        </div>
                      )}

                      {/* Summary Section */}
                      {discoverySummary && (
                        <div className="bg-gray-50 rounded-lg p-4">
                          <h4 className="font-semibold mb-3">Discovery Summary</h4>
                          <div className="grid grid-cols-4 gap-4 text-sm">
                            <div>
                              <p className="text-gray-500">Total Fields</p>
                              <p className="text-2xl font-bold">{discoverySummary.total_fields}</p>
                            </div>
                            <div>
                              <p className="text-gray-500">Already Mapped</p>
                              <p className="text-2xl font-bold text-green-600">
                                {discoverySummary.mapped_fields}
                                {discoverySummary.mapping_percentage > 0 && (
                                  <span className="text-sm font-normal text-gray-500 ml-1">
                                    ({discoverySummary.mapping_percentage}%)
                                  </span>
                                )}
                              </p>
                            </div>
                            <div>
                              <p className="text-gray-500">Suggestions</p>
                              <p className="text-2xl font-bold text-blue-600">{discoverySummary.suggested_fields}</p>
                            </div>
                            <div>
                              <p className="text-gray-500">Unmapped</p>
                              <p className="text-2xl font-bold text-red-600">{discoverySummary.unmapped_fields}</p>
                            </div>
                          </div>
                          {discoverySummary.has_product_checkboxes && (
                            <p className="text-sm text-blue-600 mt-3">
                              ✓ Product checkboxes detected
                            </p>
                          )}
                          {discoverySummary.has_multiple_npis && (
                            <p className="text-sm text-blue-600">
                              ✓ Multiple NPI fields detected
                            </p>
                          )}
                        </div>
                      )}

                      {/* Re-upload option if discovery was done */}
                      {fieldSuggestions.length > 0 && !extracting && (
                        <div className="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center justify-between">
                          <span className="text-sm text-yellow-800">
                            Field discovery completed on {formMetadata?.extraction_date ? new Date(formMetadata.extraction_date).toLocaleDateString() : 'unknown date'}
                          </span>
                          <label className="cursor-pointer px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                            <input
                              type="file"
                              accept=".pdf"
                              onChange={(e) => e.target.files?.[0] && handlePdfUpload(e.target.files[0])}
                              className="hidden"
                              disabled={extracting}
                            />
                            Re-analyze PDF
                          </label>
                        </div>
                      )}
                      
                      {/* Actions */}
                      {fieldSuggestions.length > 0 && (
                        <div className="flex justify-between items-center gap-2">
                          <div className="flex gap-2">
                            <button
                              onClick={applyAutoMappings}
                              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
                            >
                              Auto-Map High Confidence
                            </button>
                            <button
                              onClick={detectAndApplyPatterns}
                              className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm"
                            >
                              Apply Pattern Mappings
                            </button>
                          </div>
                          <button
                            onClick={saveMappings}
                            disabled={savingMappings || Object.keys(editedMappings).length === 0}
                            className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                          >
                            {savingMappings ? 'Saving...' : 'Save Mappings'}
                          </button>
                        </div>
                      )}

                      {/* Field Mappings Table */}
                      {fieldSuggestions.length > 0 && (
                        <div className="overflow-x-auto max-h-96">
                          <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50 sticky top-0">
                              <tr>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IVR Field</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current Mapping</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Suggested Mapping</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Confidence</th>
                              </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                              {fieldSuggestions.map((suggestion) => {
                                const editedValue = editedMappings[suggestion.ivr_field_name];
                                const currentValue = suggestion.current_mapping?.local_field || '';
                                const hasEdit = editedValue !== undefined && editedValue !== currentValue;
                                
                                return (
                                  <tr key={suggestion.ivr_field_name} className={hasEdit ? 'bg-yellow-50' : ''}>
                                    <td className="px-3 py-2 text-sm">
                                      {suggestion.is_checkbox && <span className="text-gray-400 mr-1">☐</span>}
                                      {suggestion.ivr_field_name}
                                    </td>
                                    <td className="px-3 py-2 text-sm">
                                      <span className={`px-2 py-1 text-xs rounded-full ${
                                        suggestion.field_type === 'checkbox' ? 'bg-purple-100 text-purple-800' :
                                        suggestion.field_type === 'date' ? 'bg-blue-100 text-blue-800' :
                                        suggestion.field_type === 'number' ? 'bg-green-100 text-green-800' :
                                        'bg-gray-100 text-gray-800'
                                      }`}>
                                        {suggestion.field_type}
                                      </span>
                                    </td>
                                    <td className="px-3 py-2 text-sm text-gray-600">{suggestion.category}</td>
                                    <td className="px-3 py-2 text-sm">
                                      {suggestion.is_mapped ? (
                                        <span className="text-green-600 font-mono text-xs">
                                          {currentValue}
                                        </span>
                                      ) : (
                                        <span className="text-gray-400">(unmapped)</span>
                                      )}
                                    </td>
                                    <td className="px-3 py-2">
                                      {suggestion.is_checkbox && suggestion.category === 'product' ? (
                                        <span className="text-xs text-purple-600">Product checkbox</span>
                                      ) : (
                                        <input
                                          type="text"
                                          value={editedValue !== undefined ? editedValue : (suggestion.suggested_mapping || '')}
                                          onChange={(e) => handleMappingChange(suggestion.ivr_field_name, e.target.value)}
                                          placeholder={suggestion.suggested_mapping || 'Enter mapping...'}
                                          className="w-full px-2 py-1 text-xs border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                      )}
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
                      )}
                    </div>
                  )}
                </Dialog.Panel>
              </Transition.Child>
            </div>
          </div>
        </Dialog>
      </Transition>

      {/* Template Preview Modal */}
      <Transition appear show={showPreviewModal} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={closePreviewModal}>
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
                  {previewTemplate && (
                    <DocuSealTemplateViewer
                      templateId={previewTemplate.id}
                      docusealTemplateId={previewTemplate.docuseal_template_id}
                      templateName={previewTemplate.template_name}
                      onClose={closePreviewModal}
                      className="w-full"
                    />
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
