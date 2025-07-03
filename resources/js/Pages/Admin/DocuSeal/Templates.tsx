import React, { useEffect, useState, Fragment, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import {
  RefreshCw, Search, Filter, Plus, Settings, ExternalLink,
  FileText, Database, CheckCircle2, AlertCircle, Clock,
  Zap, Package, Users, TrendingUp, BarChart3, Eye,
  Download, Upload, Edit3, Trash2, Copy, Star,
  ChevronDown, ChevronRight, X, Calendar, Hash,
  CheckSquare, AlertTriangle, Info, MapIcon,
  MapPin, GitBranch, Wand2, FileCheck, ArrowLeftRight,
  Layers, Target, Sparkles, ClipboardCheck, FileJson,
  Activity, Shield, Brain, Workflow
} from 'lucide-react';
// import { FieldMappingInterface } from '@/Components/Admin/Docuseal/FieldMappingInterface';
import { MappingStatsDashboard } from '@/Components/Admin/DocuSeal/MappingStatsDashboard';
import { BulkMappingModal } from '@/Components/Admin/DocuSeal/BulkMappingModal';
import { ValidationReportModal } from '@/Components/Admin/DocuSeal/ValidationReportModal';
import { ImportExportModal } from '@/Components/Admin/DocuSeal/ImportExportModal';
import { AITemplateAnalyzer } from '@/Components/Admin/DocuSeal/AITemplateAnalyzer';
import type { CanonicalField, MappingStatistics, ValidationResult } from '@/types/field-mapping';

// Enhanced Types
interface Template {
  id: string;
  template_name: string;
  docuseal_template_id: string;
  document_type: 'IVR' | 'OnboardingForm' | 'OrderForm' | 'InsuranceVerification';
  manufacturer_id: string;
  manufacturer?: {
    id: string;
    name: string;
    contact_email?: string;
    is_active: boolean;
  };
  is_active: boolean;
  is_default: boolean;
  field_mappings: Record<string, any>;
  extraction_metadata?: {
    total_fields?: number;
    mapped_fields?: number;
    extraction_confidence?: number;
    last_sync_at?: string;
    folder_info?: {
      folder_name?: string;
      is_top_level?: boolean;
    };
  };
  last_extracted_at?: string;
  created_at: string;
  updated_at: string;
  // Computed metrics
  field_coverage_percentage?: number;
  submission_count?: number;
  success_rate?: number;
}

interface DashboardStats {
  total_templates: number;
  active_templates: number;
  manufacturers_covered: number;
  avg_field_coverage: number;
  total_submissions: number;
  templates_needing_attention: number;
}

interface SyncStatus {
  is_syncing: boolean;
  last_sync: string | null;
  templates_found: number;
  templates_updated: number;
  errors: number;
}

// Main Component
export default function Templates() {
  // Core state
  const [templates, setTemplates] = useState<Template[]>([]);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Sync state
  const [syncStatus, setSyncStatus] = useState<SyncStatus>({
    is_syncing: false,
    last_sync: null,
    templates_found: 0,
    templates_updated: 0,
    errors: 0
  });

  // UI state
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedManufacturer, setSelectedManufacturer] = useState<string>('all');
  const [selectedDocType, setSelectedDocType] = useState<string>('all');
  const [showFilters, setShowFilters] = useState(false);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

  // Modal state
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [showSyncModal, setShowSyncModal] = useState(false);

  // Mapping-specific state
  const [mappingMode, setMappingMode] = useState<'view' | 'edit'>('view');
  const [selectedTemplateForMapping, setSelectedTemplateForMapping] = useState<Template | null>(null);
  const [showMappingModal, setShowMappingModal] = useState(false);
  const [showBulkMappingModal, setShowBulkMappingModal] = useState(false);
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [showImportExportModal, setShowImportExportModal] = useState(false);
  const [mappingStats, setMappingStats] = useState<Record<string, MappingStatistics>>({});
  const [canonicalFields, setCanonicalFields] = useState<CanonicalField[]>([]);
  const [currentValidationResult, setCurrentValidationResult] = useState<ValidationResult | null>(null);
  const [bulkMappingTemplateId, setBulkMappingTemplateId] = useState<string>('');
  const [importExportTemplate, setImportExportTemplate] = useState<Template | null>(null);
  const [showAIAnalyzer, setShowAIAnalyzer] = useState(false);
  const [selectedTemplateForAI, setSelectedTemplateForAI] = useState<Template | null>(null);

  // Computed values
  const manufacturers = useMemo(() => {
    const unique = Array.from(new Set(templates.map(t => t.manufacturer?.name).filter(Boolean)));
    return unique.sort();
  }, [templates]);

  const documentTypes = useMemo(() => {
    const unique = Array.from(new Set(templates.map(t => t.document_type)));
    return unique.sort();
  }, [templates]);

  const filteredTemplates = useMemo(() => {
    return templates.filter(template => {
      const matchesSearch = !searchTerm ||
        template.template_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        template.manufacturer?.name?.toLowerCase().includes(searchTerm.toLowerCase());

      const matchesManufacturer = selectedManufacturer === 'all' ||
        template.manufacturer?.name === selectedManufacturer;

      const matchesDocType = selectedDocType === 'all' ||
        template.document_type === selectedDocType;

      return matchesSearch && matchesManufacturer && matchesDocType;
    });
  }, [templates, searchTerm, selectedManufacturer, selectedDocType]);

  // Group templates by manufacturer for grid view
  const groupedTemplates = useMemo(() => {
    const groups: Record<string, Template[]> = {};
    filteredTemplates.forEach(template => {
      const mfg = template.manufacturer?.name || 'Unknown';
      if (!groups[mfg]) groups[mfg] = [];
      groups[mfg].push(template);
    });
    return groups;
  }, [filteredTemplates]);

  // API Functions
  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get('/api/v1/admin/docuseal/templates');
      setTemplates(response.data.templates || []);
      setStats(response.data.stats);

      // Update sync status if available
      if (response.data.sync_status) {
        setSyncStatus(response.data.sync_status);
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to load templates');
    } finally {
      setLoading(false);
    }
  };

  const syncTemplates = async (force = false) => {
    setSyncStatus(prev => ({ ...prev, is_syncing: true }));
    setError(null);

    try {
      const response = await axios.post('/api/v1/admin/docuseal/sync', {
        force,
        queue: true // Use queue for large syncs
      });

      if (response.data.success) {
        setSyncStatus({
          is_syncing: false,
          last_sync: new Date().toISOString(),
          templates_found: response.data.templates_found || 0,
          templates_updated: response.data.templates_updated || 0,
          errors: response.data.errors || 0
        });

        // Refresh templates after sync
        await fetchTemplates();
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Sync failed');
      setSyncStatus(prev => ({ ...prev, is_syncing: false }));
    }
  };

  const testSync = async () => {
    try {
      const response = await axios.post('/api/v1/admin/docuseal/test-sync');
      alert(`Sync Test Results:\n${response.data.message}`);
    } catch (e: any) {
      alert(`Test failed: ${e.response?.data?.message || e.message}`);
    }
  };

  // Helper Functions
  const getTemplateStatusColor = (template: Template) => {
    const coverage = template.field_coverage_percentage || 0;
    if (coverage >= 90) return 'text-green-600 bg-green-50 border-green-200';
    if (coverage >= 70) return 'text-blue-600 bg-blue-50 border-blue-200';
    if (coverage >= 50) return 'text-yellow-600 bg-yellow-50 border-yellow-200';
    return 'text-red-600 bg-red-50 border-red-200';
  };

  const getTemplateStatusText = (template: Template) => {
    if (!template.is_active) return 'Inactive';

    const coverage = template.field_coverage_percentage || 0;
    const fieldCount = Object.keys(template.field_mappings || {}).length;

    if (fieldCount === 0) return 'Not Configured';
    if (coverage >= 90) return 'Excellent';
    if (coverage >= 70) return 'Good';
    if (coverage >= 50) return 'Fair';
    return 'Needs Work';
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Mapping-related functions
  const fetchCanonicalFields = async () => {
    try {
      const response = await axios.get('/api/v1/admin/docuseal/canonical-fields');
      setCanonicalFields(response.data.fields || []);
    } catch (error) {
      console.error('Failed to load canonical fields:', error);
    }
  };

  const fetchMappingStats = async (templateIds: string[]) => {
    try {
      const stats: Record<string, MappingStatistics> = {};
      await Promise.all(
        templateIds.map(async (id) => {
          try {
            const response = await axios.get(`/api/v1/admin/docuseal/templates/${id}/mapping-stats`);
            stats[id] = response.data;
          } catch (err) {
            // Handle individual template errors without failing all
            console.warn(`Failed to load mapping stats for template ${id}:`, err);
            stats[id] = {
              totalFields: 0,
              mappedFields: 0,
              unmappedFields: 0,
              activeFields: 0,
              requiredFieldsMapped: 0,
              totalRequiredFields: 0,
              optionalFieldsMapped: 0,
              coveragePercentage: 0,
              requiredCoveragePercentage: 0,
              highConfidenceCount: 0,
              validationStatus: {
                valid: 0,
                warning: 0,
                error: 0
              },
              lastUpdated: null,
              lastUpdatedBy: null
            };
          }
        })
      );
      setMappingStats(stats);
    } catch (error) {
      console.error('Failed to load mapping statistics:', error);
    }
  };

  const openMappingInterface = (template: Template) => {
    setSelectedTemplateForMapping(template);
    setShowMappingModal(true);
    setMappingMode('edit');
  };

  const handleMappingUpdate = async (templateId: string) => {
    // Refresh stats after mapping changes
    await fetchMappingStats([templateId]);
    await fetchTemplates();
  };

  const validateTemplate = async (template: Template) => {
    try {
      const response = await axios.post(
        `/api/v1/admin/docuseal/templates/${template.id}/field-mappings/validate`
      );
      setCurrentValidationResult(response.data);
      setSelectedTemplateForMapping(template);
      setShowValidationModal(true);
    } catch (error) {
      console.error('Failed to validate template:', error);
    }
  };

  useEffect(() => {
    fetchTemplates();
    fetchCanonicalFields();
  }, []);

  useEffect(() => {
    if (templates.length > 0 && mappingMode === 'edit') {
      const templateIds = templates.map(t => t.id);
      fetchMappingStats(templateIds);
    }
  }, [templates, mappingMode]);

  return (
    <MainLayout>
      <Head title="Docuseal Templates" />

      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <div className="bg-white border-b border-gray-200">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="py-6">
              <div className="flex items-center justify-between">
                <div>
                  <h1 className="text-3xl font-bold text-gray-900">Docuseal Templates</h1>
                  <p className="mt-2 text-gray-600">
                    Manage manufacturer IVR forms and automation templates
                  </p>
                </div>

                <div className="flex items-center gap-3">
                  <button
                    onClick={() => setShowSyncModal(true)}
                    className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2"
                  >
                    <Settings className="w-4 h-4" />
                    Sync Options
                  </button>

                  <button
                    onClick={testSync}
                    className="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors flex items-center gap-2"
                  >
                    <Zap className="w-4 h-4" />
                    Test Sync
                  </button>

                  <button
                    onClick={() => syncTemplates(false)}
                    disabled={syncStatus.is_syncing}
                    className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                  >
                    {syncStatus.is_syncing ? (
                      <>
                        <RefreshCw className="w-4 h-4 animate-spin" />
                        Syncing...
                      </>
                    ) : (
                      <>
                        <RefreshCw className="w-4 h-4" />
                        Sync Templates
                      </>
                    )}
                  </button>

                  {/* Mapping Controls */}
                  <div className="flex items-center gap-2 ml-4 pl-4 border-l border-gray-300">
                    <button
                      onClick={() => setMappingMode(mappingMode === 'view' ? 'edit' : 'view')}
                      className="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors flex items-center gap-2"
                    >
                      <MapIcon className="w-4 h-4" />
                      {mappingMode === 'view' ? 'Edit Mappings' : 'View Mode'}
                    </button>

                    {mappingMode === 'edit' && (
                      <>
                        <button
                          onClick={() => {
                            const firstTemplate = filteredTemplates[0];
                            if (firstTemplate) {
                              setBulkMappingTemplateId(firstTemplate.id);
                              setShowBulkMappingModal(true);
                            }
                          }}
                          className="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors flex items-center gap-2"
                        >
                          <Layers className="w-4 h-4" />
                          Bulk Operations
                        </button>

                        <button
                          onClick={() => {
                            const firstTemplate = filteredTemplates[0];
                            if (firstTemplate) {
                              setImportExportTemplate(firstTemplate);
                              setShowImportExportModal(true);
                            }
                          }}
                         className="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors flex items-center gap-2"
                        >
                          <FileJson className="w-4 h-4" />
                          Import/Export
                        </button>

                        <button
                          onClick={() => {
                            setShowAIAnalyzer(true);
                          }}
                          className="px-4 py-2 bg-gradient-to-r from-purple-100 to-blue-100 text-purple-700 rounded-lg hover:from-purple-200 hover:to-blue-200 transition-colors flex items-center gap-2"
                        >
                          <Brain className="w-4 h-4" />
                          AI Analyzer
                        </button>
                      </>
                    )}
                  </div>
                </div>
              </div>

              {/* Stats Dashboard */}
              {stats && (
                <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
                  <div className="bg-blue-50 rounded-xl p-4 border border-blue-100">
                    <div className="flex items-center">
                      <FileText className="w-8 h-8 text-blue-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-blue-900">Total Templates</p>
                        <p className="text-2xl font-bold text-blue-600">{stats.total_templates}</p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-green-50 rounded-xl p-4 border border-green-100">
                    <div className="flex items-center">
                      <CheckCircle2 className="w-8 h-8 text-green-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-green-900">Active</p>
                        <p className="text-2xl font-bold text-green-600">{stats.active_templates}</p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-purple-50 rounded-xl p-4 border border-purple-100">
                    <div className="flex items-center">
                      <Package className="w-8 h-8 text-purple-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-purple-900">Manufacturers</p>
                        <p className="text-2xl font-bold text-purple-600">{stats.manufacturers_covered}</p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-yellow-50 rounded-xl p-4 border border-yellow-100">
                    <div className="flex items-center">
                      <BarChart3 className="w-8 h-8 text-yellow-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-yellow-900">Avg Coverage</p>
                        <p className="text-2xl font-bold text-yellow-600">{stats.avg_field_coverage}%</p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                    <div className="flex items-center">
                      <TrendingUp className="w-8 h-8 text-indigo-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-indigo-900">Submissions</p>
                        <p className="text-2xl font-bold text-indigo-600">{stats.total_submissions}</p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-red-50 rounded-xl p-4 border border-red-100">
                    <div className="flex items-center">
                      <AlertTriangle className="w-8 h-8 text-red-600" />
                      <div className="ml-3">
                        <p className="text-sm font-medium text-red-900">Need Attention</p>
                        <p className="text-2xl font-bold text-red-600">{stats.templates_needing_attention}</p>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Mapping Stats Dashboard */}
              {mappingMode === 'edit' && stats && (
                <MappingStatsDashboard
                  templates={templates}
                  mappingStats={mappingStats}
                  canonicalFields={canonicalFields}
                />
              )}
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="bg-white rounded-lg border border-gray-200 p-4 mb-6">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              {/* Search */}
              <div className="relative flex-1 max-w-md">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search templates..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              {/* Filters */}
              <div className="flex items-center gap-3">
                <select
                  value={selectedManufacturer}
                  onChange={(e) => setSelectedManufacturer(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Manufacturers</option>
                  {manufacturers.map(mfg => (
                    <option key={mfg} value={mfg}>{mfg}</option>
                  ))}
                </select>

                <select
                  value={selectedDocType}
                  onChange={(e) => setSelectedDocType(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Types</option>
                  {documentTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>

                {/* View Mode Toggle */}
                <div className="flex rounded-lg border border-gray-300 p-1">
                  <button
                    onClick={() => setViewMode('grid')}
                    className={`p-2 rounded ${viewMode === 'grid' ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
                  >
                    <Package className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => setViewMode('list')}
                    className={`p-2 rounded ${viewMode === 'list' ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
                  >
                    <FileText className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            {/* Active Filters Display */}
            {(searchTerm || selectedManufacturer !== 'all' || selectedDocType !== 'all') && (
              <div className="mt-3 flex items-center gap-2 text-sm">
                <span className="text-gray-500">Active filters:</span>
                {searchTerm && (
                  <span className="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                    Search: "{searchTerm}"
                  </span>
                )}
                {selectedManufacturer !== 'all' && (
                  <span className="bg-purple-100 text-purple-800 px-2 py-1 rounded-full">
                    {selectedManufacturer}
                  </span>
                )}
                {selectedDocType !== 'all' && (
                  <span className="bg-green-100 text-green-800 px-2 py-1 rounded-full">
                    {selectedDocType}
                  </span>
                )}
                <button
                  onClick={() => {
                    setSearchTerm('');
                    setSelectedManufacturer('all');
                    setSelectedDocType('all');
                  }}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            )}
          </div>

          {/* Error Display */}
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
              <AlertCircle className="w-5 h-5 text-red-600 mr-3 flex-shrink-0" />
              <span className="text-red-800">{error}</span>
              <button
                onClick={() => setError(null)}
                className="ml-auto text-red-400 hover:text-red-600"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          )}

          {/* Loading State */}
          {loading ? (
            <div className="text-center py-12">
              <RefreshCw className="w-8 h-8 animate-spin text-blue-600 mx-auto mb-4" />
              <p className="text-gray-500">Loading templates...</p>
            </div>
          ) : (
            /* Templates Display */
            <div className="space-y-6">
              {filteredTemplates.length === 0 ? (
                <div className="text-center py-12 bg-white rounded-lg border border-gray-200">
                  <FileText className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No templates found</h3>
                  <p className="text-gray-500 mb-4">
                    {templates.length === 0
                      ? "No templates have been synced yet. Click 'Sync Templates' to get started."
                      : "No templates match your current filters. Try adjusting your search criteria."
                    }
                  </p>
                  {templates.length === 0 && (
                    <button
                      onClick={() => syncTemplates(false)}
                      className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                      Sync Templates Now
                    </button>
                  )}
                </div>
              ) : viewMode === 'grid' ? (
                /* Grid View */
                Object.entries(groupedTemplates).map(([manufacturer, manufacturerTemplates]) => (
                  <div key={manufacturer} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <Package className="w-5 h-5 text-blue-600" />
                          </div>
                          <div>
                            <h2 className="text-xl font-bold text-gray-900">{manufacturer}</h2>
                            <p className="text-sm text-gray-600">
                              {manufacturerTemplates.length} template{manufacturerTemplates.length !== 1 ? 's' : ''}
                            </p>
                          </div>
                        </div>

                        <div className="text-right">
                          <div className="text-sm text-gray-500">Avg Coverage</div>
                          <div className="text-lg font-bold text-gray-900">
                            {Math.round(manufacturerTemplates.reduce((sum, t) => sum + (t.field_coverage_percentage || 0), 0) / manufacturerTemplates.length)}%
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="p-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {manufacturerTemplates.map(template => (
                          <div
                            key={template.id}
                            className="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-md transition-all cursor-pointer"
                            onClick={() => {
                              setSelectedTemplate(template);
                              setShowDetailsModal(true);
                            }}
                          >
                            <div className="flex items-start justify-between mb-3">
                              <div className="flex-1">
                                <h3 className="font-semibold text-gray-900 line-clamp-2 mb-1">
                                  {template.template_name}
                                </h3>
                                <span className="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                  {template.document_type}
                                </span>
                              </div>
                              {template.is_default && (
                                <Star className="w-4 h-4 text-yellow-500 flex-shrink-0 ml-2" />
                              )}
                            </div>

                            <div className="space-y-2">
                              <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-600">Field Coverage</span>
                                <span className="font-semibold text-gray-900">
                                  {template.field_coverage_percentage || 0}%
                                </span>
                              </div>
                              <div className="w-full bg-gray-200 rounded-full h-2">
                                <div
                                  className="bg-blue-600 h-2 rounded-full transition-all"
                                  style={{ width: `${template.field_coverage_percentage || 0}%` }}
                                />
                              </div>

                              <div className="flex items-center justify-between text-xs text-gray-500">
                                <span>{Object.keys(template.field_mappings || {}).length} fields</span>
                                <span className={`px-2 py-1 rounded-full border ${getTemplateStatusColor(template)}`}>
                                  {getTemplateStatusText(template)}
                                </span>
                              </div>
                            </div>

                            {/* Mapping Section */}
                            {mappingMode === 'edit' && (
                              <div className="mt-3 pt-3 border-t border-gray-200" onClick={(e) => e.stopPropagation()}>
                                <div className="flex items-center justify-between mb-2">
                                  <span className="text-sm font-medium text-gray-700">Field Mapping</span>
                                  <span className={`text-sm font-bold ${
                                    (mappingStats[template.id]?.coveragePercentage || 0) >= 80 ? 'text-green-600' : 'text-orange-600'
                                  }`}>
                                    {mappingStats[template.id]?.coveragePercentage || 0}%
                                  </span>
                                </div>

                                <div className="space-y-2">
                                  <div className="flex items-center gap-2 text-xs text-gray-600">
                                    <Target className="w-3 h-3" />
                                    <span>{mappingStats[template.id]?.mappedFields || 0} / {mappingStats[template.id]?.totalFields || 0} fields mapped</span>
                                  </div>

                                  {mappingStats[template.id]?.validationStatus?.error != null && mappingStats[template.id]?.validationStatus?.error! > 0 && (
                                    <div className="flex items-center gap-2 text-xs text-red-600">
                                      <AlertCircle className="w-3 h-3" />
                                      <span>{mappingStats[template.id]?.validationStatus.error} validation errors</span>
                                    </div>
                                  )}

                                  <div className="flex gap-2">
                                    {/* Configure button temporarily disabled after cleanup */}
                                    {/* <button
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        openMappingInterface(template);
                                      }}
                                      className="flex-1 px-3 py-1.5 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition-colors flex items-center justify-center gap-2"
                                    >
                                      <MapPin className="w-3 h-3" />
                                      Configure
                                    </button> */}

                                    <button
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        validateTemplate(template);
                                      }}
                                      className="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
                                      title="Validate Mappings"
                                    >
                                      <Shield className="w-3 h-3" />
                                    </button>

                                    <button
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        setSelectedTemplateForAI(template);
                                        setShowAIAnalyzer(true);
                                      }}
                                      className="px-3 py-1.5 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-sm rounded-lg hover:from-purple-700 hover:to-blue-700 transition-colors"
                                      title="AI Analysis"
                                    >
                                      <Brain className="w-3 h-3" />
                                    </button>
                                  </div>
                                </div>
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                /* List View */
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Template
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Manufacturer
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Coverage
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Updated
                          </th>
                          <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {filteredTemplates.map(template => (
                          <tr key={template.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <FileText className="w-5 h-5 text-gray-400 mr-3" />
                                <div>
                                  <div className="text-sm font-medium text-gray-900">
                                    {template.template_name}
                                  </div>
                                  <div className="text-sm text-gray-500">
                                    ID: {template.docuseal_template_id}
                                  </div>
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <Package className="w-4 h-4 text-gray-400 mr-2" />
                                <span className="text-sm text-gray-900">
                                  {template.manufacturer?.name || 'Unknown'}
                                </span>
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span className="inline-flex px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                {template.document_type}
                              </span>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <div className="w-16 bg-gray-200 rounded-full h-2 mr-3">
                                  <div
                                    className="bg-blue-600 h-2 rounded-full"
                                    style={{ width: `${template.field_coverage_percentage || 0}%` }}
                                  />
                                </div>
                                <span className="text-sm text-gray-900">
                                  {template.field_coverage_percentage || 0}%
                                </span>
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span className={`inline-flex px-2 py-1 text-xs rounded-full border ${getTemplateStatusColor(template)}`}>
                                {getTemplateStatusText(template)}
                              </span>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {formatDate(template.updated_at)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                              <div className="flex items-center justify-end gap-2">
                                <button
                                  onClick={() => {
                                    setSelectedTemplate(template);
                                    setShowDetailsModal(true);
                                  }}
                                  className="text-blue-600 hover:text-blue-900"
                                  title="View Details"
                                >
                                  <Eye className="w-4 h-4" />
                                </button>
                                {mappingMode === 'edit' && (
                                  <>
                                    {/* Configure Mappings button temporarily disabled after cleanup */}
                                    {/* <button
                                      onClick={() => openMappingInterface(template)}
                                      className="text-purple-600 hover:text-purple-900"
                                      title="Configure Mappings"
                                    >
                                      <MapPin className="w-4 h-4" />
                                    </button> */}
                                    <button
                                      onClick={() => validateTemplate(template)}
                                      className="text-indigo-600 hover:text-indigo-900"
                                      title="Validate Mappings"
                                    >
                                      <Shield className="w-4 h-4" />
                                    </button>
                                    <button
                                      onClick={() => {
                                        setSelectedTemplateForAI(template);
                                        setShowAIAnalyzer(true);
                                      }}
                                      className="text-purple-600 hover:text-purple-900"
                                      title="AI Analysis"
                                    >
                                      <Brain className="w-4 h-4" />
                                    </button>
                                  </>
                                )}
                                <button
                                  onClick={() => window.open(`https://app.docuseal.com/templates/${template.docuseal_template_id}/edit`, '_blank')}
                                  className="text-green-600 hover:text-green-900"
                                  title="Edit in Docuseal"
                                >
                                  <Edit3 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => window.open(`https://app.docuseal.com/templates/${template.docuseal_template_id}`, '_blank')}
                                  className="text-gray-600 hover:text-gray-900"
                                  title="Open in Docuseal"
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
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Template Details Modal */}
      <Transition appear show={showDetailsModal} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={() => setShowDetailsModal(false)}>
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
                <Dialog.Panel className="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                  {selectedTemplate && (
                    <>
                      <div className="flex items-center justify-between mb-6">
                        <div>
                          <Dialog.Title className="text-2xl font-bold text-gray-900">
                            {selectedTemplate.template_name}
                          </Dialog.Title>
                          <p className="text-gray-600 mt-1">
                            {selectedTemplate.manufacturer?.name} • {selectedTemplate.document_type}
                          </p>
                        </div>
                        <button
                          onClick={() => setShowDetailsModal(false)}
                          className="text-gray-400 hover:text-gray-600"
                        >
                          <X className="w-6 h-6" />
                        </button>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Template Info */}
                        <div className="space-y-4">
                          <div className="bg-gray-50 rounded-lg p-4">
                            <h3 className="font-semibold text-gray-900 mb-3">Template Information</h3>
                            <dl className="space-y-2 text-sm">
                              <div className="flex justify-between">
                                <dt className="text-gray-600">Docuseal ID:</dt>
                                <dd className="font-mono text-gray-900">{selectedTemplate.docuseal_template_id}</dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className="text-gray-600">Status:</dt>
                                <dd>
                                  {selectedTemplate.is_active ? (
                                    <span className="text-green-600">Active</span>
                                  ) : (
                                    <span className="text-red-600">Inactive</span>
                                  )}
                                </dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className="text-gray-600">Default:</dt>
                                <dd>
                                  {selectedTemplate.is_default ? (
                                    <span className="text-blue-600">Yes</span>
                                  ) : (
                                    <span className="text-gray-500">No</span>
                                  )}
                                </dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className="text-gray-600">Last Updated:</dt>
                                <dd>{formatDate(selectedTemplate.updated_at)}</dd>
                              </div>
                            </dl>
                          </div>

                          {/* Field Mappings Preview */}
                          <div className="bg-blue-50 rounded-lg p-4">
                            <h3 className="font-semibold text-blue-900 mb-3">Field Mappings</h3>
                            <div className="space-y-2">
                              <div className="flex justify-between">
                                <span className="text-blue-700">Total Fields:</span>
                                <span className="font-semibold">{Object.keys(selectedTemplate.field_mappings || {}).length}</span>
                              </div>
                              <div className="flex justify-between">
                                <span className="text-blue-700">Coverage:</span>
                                <span className="font-semibold">{selectedTemplate.field_coverage_percentage || 0}%</span>
                              </div>
                              <div className="mt-3">
                                <div className="bg-blue-200 rounded-full h-3">
                                  <div
                                    className="bg-blue-600 h-3 rounded-full transition-all"
                                    style={{ width: `${selectedTemplate.field_coverage_percentage || 0}%` }}
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Sample Fields */}
                        <div className="space-y-4">
                          <div className="bg-green-50 rounded-lg p-4">
                            <h3 className="font-semibold text-green-900 mb-3">Sample Fields</h3>
                            <div className="space-y-2 max-h-64 overflow-y-auto">
                              {Object.entries(selectedTemplate.field_mappings || {}).slice(0, 10).map(([field, mapping]: [string, any]) => (
                                <div key={field} className="flex justify-between text-sm">
                                  <span className="text-green-700 font-mono">{field}</span>
                                  <span className="text-green-600">{mapping?.system_field || 'unmapped'}</span>
                                </div>
                              ))}
                              {Object.keys(selectedTemplate.field_mappings || {}).length > 10 && (
                                <div className="text-xs text-green-600 text-center">
                                  ... and {Object.keys(selectedTemplate.field_mappings || {}).length - 10} more fields
                                </div>
                              )}
                            </div>
                          </div>

                          {/* Actions */}
                          <div className="space-y-3">
                            <button
                              onClick={() => window.open(`https://app.docuseal.com/templates/${selectedTemplate.docuseal_template_id}/edit`, '_blank')}
                              className="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                            >
                              <Edit3 className="w-4 h-4" />
                              Edit in Docuseal
                            </button>
                            <button
                              onClick={() => window.open(`https://app.docuseal.com/templates/${selectedTemplate.docuseal_template_id}`, '_blank')}
                              className="w-full bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors flex items-center justify-center gap-2"
                            >
                              <ExternalLink className="w-4 h-4" />
                              View in Docuseal
                            </button>
                          </div>
                        </div>
                      </div>
                    </>
                  )}
                </Dialog.Panel>
              </Transition.Child>
            </div>
          </div>
        </Dialog>
      </Transition>

      {/* Sync Options Modal */}
      <Transition appear show={showSyncModal} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={() => setShowSyncModal(false)}>
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
                <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                  <div className="flex items-center justify-between mb-4">
                    <Dialog.Title className="text-lg font-semibold text-gray-900">
                      Sync Options
                    </Dialog.Title>
                    <button
                      onClick={() => setShowSyncModal(false)}
                      className="text-gray-400 hover:text-gray-600"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  <div className="space-y-4">
                    <div className="bg-blue-50 rounded-lg p-4">
                      <h3 className="font-medium text-blue-900 mb-2">Sync Status</h3>
                      <div className="text-sm space-y-1">
                        <div className="flex justify-between">
                          <span className="text-blue-700">Last Sync:</span>
                          <span>{syncStatus.last_sync ? formatDate(syncStatus.last_sync) : 'Never'}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-blue-700">Templates Found:</span>
                          <span>{syncStatus.templates_found}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-blue-700">Updated:</span>
                          <span>{syncStatus.templates_updated}</span>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-3">
                      <button
                        onClick={() => {
                          syncTemplates(false);
                          setShowSyncModal(false);
                        }}
                        disabled={syncStatus.is_syncing}
                        className="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                      >
                        <RefreshCw className="w-4 h-4" />
                        Regular Sync
                      </button>

                      <button
                        onClick={() => {
                          syncTemplates(true);
                          setShowSyncModal(false);
                        }}
                        disabled={syncStatus.is_syncing}
                        className="w-full bg-orange-600 text-white py-2 px-4 rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                      >
                        <RefreshCw className="w-4 h-4" />
                        Force Sync (Update All)
                      </button>

                      <button
                        onClick={() => {
                          testSync();
                          setShowSyncModal(false);
                        }}
                        className="w-full bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors flex items-center justify-center gap-2"
                      >
                        <Zap className="w-4 h-4" />
                        Test Connection
                      </button>
                    </div>
                  </div>
                </Dialog.Panel>
              </Transition.Child>
            </div>
          </div>
        </Dialog>
      </Transition>

      {/* Field Mapping Interface Modal - Temporarily disabled after cleanup */}
      {/* {selectedTemplateForMapping && showMappingModal && (
        <FieldMappingInterface
          show={showMappingModal}
          onClose={() => {
            setShowMappingModal(false);
            setSelectedTemplateForMapping(null);
          }}
          templateId={selectedTemplateForMapping.id}
          templateName={selectedTemplateForMapping.template_name}
          onUpdate={() => handleMappingUpdate(selectedTemplateForMapping.id)}
        />
      )} */}

      {/* Bulk Mapping Modal */}
      {showBulkMappingModal && bulkMappingTemplateId && (
        <BulkMappingModal
          show={showBulkMappingModal}
          onClose={() => setShowBulkMappingModal(false)}
          templateId={bulkMappingTemplateId}
          onComplete={() => {
            handleMappingUpdate(bulkMappingTemplateId);
            setShowBulkMappingModal(false);
          }}
        />
      )}

      {/* Validation Report Modal */}
      {showValidationModal && selectedTemplateForMapping && (
        <ValidationReportModal
          show={showValidationModal}
          onClose={() => {
            setShowValidationModal(false);
            setCurrentValidationResult(null);
          }}
          validationResult={currentValidationResult}
          templateName={selectedTemplateForMapping.template_name}
        />
      )}

      {/* Import/Export Modal */}
      {showImportExportModal && importExportTemplate && (
        <ImportExportModal
          show={showImportExportModal}
          onClose={() => setShowImportExportModal(false)}
          templateId={importExportTemplate.id}
          templateName={importExportTemplate.template_name}
          onImportComplete={() => {
            handleMappingUpdate(importExportTemplate.id);
          }}
        />
      )}

      {/* AI Template Analyzer Modal */}
      <AITemplateAnalyzer
        show={showAIAnalyzer}
        onClose={() => {
          setShowAIAnalyzer(false);
          setSelectedTemplateForAI(null);
        }}
        templateId={selectedTemplateForAI?.id}
        onAnalysisComplete={(analysis) => {
          console.log('AI Analysis completed:', analysis);
          // Handle the analysis results - could auto-apply mappings, etc.
          if (selectedTemplateForAI && analysis.auto_mappings) {
            // You could automatically apply the mappings here
            // or just close the modal and refresh
            fetchTemplates();
          }
        }}
      />
    </MainLayout>
  );
}
