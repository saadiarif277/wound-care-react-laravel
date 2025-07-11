import React, { useEffect, useState, Fragment, useMemo, useCallback } from 'react';
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
  Activity, Shield, Brain, Workflow, Grid, List,
  Moon, Sun, Loader2, Check
} from 'lucide-react';
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

export default function Templates() {
  // Core state
  const [templates, setTemplates] = useState<Template[]>([]);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [darkMode, setDarkMode] = useState(false);

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
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [filter, setFilter] = useState<string>('all');

  // Modal state
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [showSyncModal, setShowSyncModal] = useState(false);

  // Intelligence/Mapping state
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
  
  // Manufacturer section expansion state
  const [expandedManufacturers, setExpandedManufacturers] = useState<Record<string, boolean>>({});
  const [expandAll, setExpandAll] = useState(false);

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

      const matchesFilter = filter === 'all' || 
        (filter === 'active' && template.is_active) ||
        (filter === 'inactive' && !template.is_active) ||
        (filter === 'needs_attention' && (template.field_coverage_percentage || 0) < 70);

      return matchesSearch && matchesManufacturer && matchesDocType && matchesFilter;
    });
  }, [templates, searchTerm, selectedManufacturer, selectedDocType, filter]);

  // Group templates by manufacturer
  const templatesByManufacturer = useMemo(() => {
    const grouped = filteredTemplates.reduce((acc, template) => {
      const manufacturerName = template.manufacturer?.name || 'Unassigned';
      if (!acc[manufacturerName]) {
        acc[manufacturerName] = [];
      }
      acc[manufacturerName].push(template);
      return acc;
    }, {} as Record<string, Template[]>);
    
    // Sort manufacturers alphabetically, but put 'Unassigned' at the end
    const sortedManufacturers = Object.keys(grouped).sort((a, b) => {
      if (a === 'Unassigned') return 1;
      if (b === 'Unassigned') return -1;
      return a.localeCompare(b);
    });
    
    return sortedManufacturers.map(manufacturerName => ({
      name: manufacturerName,
      templates: grouped[manufacturerName],
      stats: {
        totalTemplates: grouped[manufacturerName].length,
        activeTemplates: grouped[manufacturerName].filter(t => t.is_active).length,
        avgCoverage: Math.round(
          grouped[manufacturerName].reduce((sum, t) => sum + (t.field_coverage_percentage || 0), 0) / 
          grouped[manufacturerName].length
        ),
        needsAttention: grouped[manufacturerName].filter(t => (t.field_coverage_percentage || 0) < 70).length
      }
    }));
  }, [filteredTemplates]);

  // API Functions
  const fetchTemplates = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get('/api/v1/admin/docuseal/templates');
      setTemplates(response.data.templates || []);
      setStats(response.data.stats);

      if (response.data.sync_status) {
        setSyncStatus(response.data.sync_status);
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Failed to load templates');
    } finally {
      setLoading(false);
    }
  }, []);

  const syncTemplates = useCallback(async (force = false) => {
    setSyncStatus(prev => ({ ...prev, is_syncing: true }));
    setError(null);

    try {
      const response = await axios.post('/api/v1/admin/docuseal/sync', {
        force,
        queue: true
      });

      if (response.data.success) {
        setSyncStatus({
          is_syncing: false,
          last_sync: new Date().toISOString(),
          templates_found: response.data.templates_found || 0,
          templates_updated: response.data.templates_updated || 0,
          errors: response.data.errors || 0
        });

        await fetchTemplates();
      }
    } catch (e: any) {
      setError(e.response?.data?.message || 'Sync failed');
      setSyncStatus(prev => ({ ...prev, is_syncing: false }));
    }
  }, [fetchTemplates]);

  const fetchCanonicalFields = useCallback(async () => {
    try {
      const response = await axios.get('/api/v1/admin/docuseal/canonical-fields');
      setCanonicalFields(response.data.fields || []);
    } catch (error) {
      console.error('Failed to load canonical fields:', error);
    }
  }, []);

  const fetchMappingStats = useCallback(async (templateIds: string[]) => {
    try {
      const stats: Record<string, MappingStatistics> = {};
      await Promise.all(
        templateIds.map(async (id) => {
          try {
            const response = await axios.get(`/api/v1/admin/docuseal/templates/${id}/mapping-stats`);
            stats[id] = response.data;
          } catch (err) {
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
              validationStatus: { valid: 0, warning: 0, error: 0 },
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
  }, []);

  const handleMappingUpdate = useCallback(async (templateId: string) => {
    await fetchMappingStats([templateId]);
    await fetchTemplates();
  }, [fetchMappingStats, fetchTemplates]);

  const validateTemplate = useCallback(async (template: Template) => {
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
  }, []);

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getStatusColor = (template: Template) => {
    const coverage = template.field_coverage_percentage || 0;
    if (coverage >= 90) return 'bg-green-500';
    if (coverage >= 70) return 'bg-blue-500';
    if (coverage >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
  };

  const getStatusText = (template: Template) => {
    if (!template.is_active) return 'Inactive';
    const coverage = template.field_coverage_percentage || 0;
    const fieldCount = Object.keys(template.field_mappings || {}).length;
    
    if (fieldCount === 0) return 'Not Configured';
    if (coverage >= 90) return 'Excellent';
    if (coverage >= 70) return 'Good';
    if (coverage >= 50) return 'Fair';
    return 'Needs Work';
  };

  // Manufacturer section helper functions
  const toggleManufacturer = (manufacturerName: string) => {
    setExpandedManufacturers(prev => ({
      ...prev,
      [manufacturerName]: !prev[manufacturerName]
    }));
  };

  const toggleExpandAll = () => {
    const newExpandAll = !expandAll;
    setExpandAll(newExpandAll);
    
    const newExpanded: Record<string, boolean> = {};
    templatesByManufacturer.forEach(({ name }) => {
      newExpanded[name] = newExpandAll;
    });
    setExpandedManufacturers(newExpanded);
  };

  const isManufacturerExpanded = (manufacturerName: string) => {
    return expandedManufacturers[manufacturerName] ?? false;
  };

  // Stats Component
  const StatCard = ({ icon: Icon, label, value, color }: any) => (
    <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl p-6 border ${darkMode ? 'border-gray-700' : 'border-gray-200'} hover:scale-105 transition-transform`}>
      <div className="flex items-center justify-between">
        <div>
          <p className={`text-sm ${darkMode ? 'text-gray-400' : 'text-gray-600'}`}>{label}</p>
          <p className="text-3xl font-bold mt-2">{value}</p>
        </div>
        <div className={`p-3 rounded-lg ${color}`}>
          <Icon className="w-6 h-6" />
        </div>
      </div>
    </div>
  );

  // Manufacturer Section Component
  const ManufacturerSection = ({ 
    name, 
    templates, 
    stats, 
    isExpanded, 
    onToggle 
  }: { 
    name: string; 
    templates: Template[]; 
    stats: any; 
    isExpanded: boolean; 
    onToggle: () => void;
  }) => {
    // Group templates by document type for better organization
    const templatesByType = templates.reduce((acc, template) => {
      const type = template.document_type;
      if (!acc[type]) {
        acc[type] = [];
      }
      acc[type].push(template);
      return acc;
    }, {} as Record<string, Template[]>);

    // Get folder info from first template with extraction metadata
    const folderInfo = templates.find(t => t.extraction_metadata?.folder_info)?.extraction_metadata?.folder_info;

    return (
      <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl border ${darkMode ? 'border-gray-700' : 'border-gray-200'} overflow-hidden`}>
        <div 
          className={`p-6 cursor-pointer ${darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50'} transition-colors`}
          onClick={onToggle}
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className={`p-2 rounded-lg ${name === 'Unassigned' ? 'bg-gray-500' : 'bg-blue-500'} bg-opacity-20`}>
                {name === 'Unassigned' ? (
                  <AlertTriangle className={`w-6 h-6 ${name === 'Unassigned' ? 'text-gray-500' : 'text-blue-500'}`} />
                ) : (
                  <Package className={`w-6 h-6 ${name === 'Unassigned' ? 'text-gray-500' : 'text-blue-500'}`} />
                )}
              </div>
              <div>
                <h3 className="text-xl font-semibold">{name}</h3>
                <div className="flex items-center gap-4">
                  <p className={`text-sm ${darkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    {stats.totalTemplates} templates • {stats.activeTemplates} active
                  </p>
                  {folderInfo && (
                    <span className={`text-xs px-2 py-1 rounded-full ${darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'}`}>
                      Folder: {folderInfo.folder_name}
                    </span>
                  )}
                </div>
                {/* Show template types summary */}
                <div className="flex items-center gap-2 mt-1">
                  {Object.entries(templatesByType).map(([type, typeTemplates]) => (
                    <span 
                      key={type} 
                      className={`text-xs px-2 py-1 rounded-full ${
                        type === 'InsuranceVerification' || type === 'IVR'
                          ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                          : type === 'OrderForm'
                          ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                          : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300'
                      }`}
                    >
                      {type === 'InsuranceVerification' ? 'IVR' : type} ({typeTemplates.length})
                    </span>
                  ))}
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-4">
              <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                  <div className="text-lg font-semibold text-blue-500">{stats.avgCoverage}%</div>
                  <div className={`text-xs ${darkMode ? 'text-gray-400' : 'text-gray-500'}`}>Avg Coverage</div>
                </div>
                <div>
                  <div className="text-lg font-semibold text-green-500">{stats.activeTemplates}</div>
                  <div className={`text-xs ${darkMode ? 'text-gray-400' : 'text-gray-500'}`}>Active</div>
                </div>
                <div>
                  <div className="text-lg font-semibold text-red-500">{stats.needsAttention}</div>
                  <div className={`text-xs ${darkMode ? 'text-gray-400' : 'text-gray-500'}`}>Need Work</div>
                </div>
              </div>
              
              <div className="flex items-center gap-2">
                <div className={`px-3 py-1 rounded-full text-sm font-medium ${
                  stats.avgCoverage >= 80 
                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' 
                    : stats.avgCoverage >= 60 
                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' 
                    : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                }`}>
                  {stats.avgCoverage >= 80 ? 'Excellent' : stats.avgCoverage >= 60 ? 'Good' : 'Needs Work'}
                </div>
                {isExpanded ? (
                  <ChevronDown className="w-5 h-5 text-gray-400" />
                ) : (
                  <ChevronRight className="w-5 h-5 text-gray-400" />
                )}
              </div>
            </div>
          </div>
        </div>
        
        {isExpanded && (
          <div className={`border-t ${darkMode ? 'border-gray-700' : 'border-gray-200'} p-6`}>
            {/* Group templates by type for better organization */}
            <div className="space-y-6">
              {Object.entries(templatesByType).map(([type, typeTemplates]) => (
                <div key={type}>
                  <div className="flex items-center gap-2 mb-4">
                    <div className={`p-2 rounded-lg ${
                      type === 'InsuranceVerification' || type === 'IVR'
                        ? 'bg-blue-500 bg-opacity-20'
                        : type === 'OrderForm'
                        ? 'bg-green-500 bg-opacity-20'
                        : 'bg-gray-500 bg-opacity-20'
                    }`}>
                      {type === 'InsuranceVerification' || type === 'IVR' ? (
                        <FileCheck className={`w-5 h-5 ${
                          type === 'InsuranceVerification' || type === 'IVR' ? 'text-blue-500' : 'text-gray-500'
                        }`} />
                      ) : type === 'OrderForm' ? (
                        <ClipboardCheck className="w-5 h-5 text-green-500" />
                      ) : (
                        <FileText className="w-5 h-5 text-gray-500" />
                      )}
                    </div>
                    <h4 className="text-lg font-semibold">
                      {type === 'InsuranceVerification' ? 'IVR Templates' : `${type} Templates`}
                    </h4>
                    <span className={`text-sm px-2 py-1 rounded-full ${darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'}`}>
                      {typeTemplates.length}
                    </span>
                  </div>
                  
                  <div className={viewMode === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' : 'space-y-4'}>
                    {typeTemplates.map(template => (
                      <TemplateCard key={template.id} template={template} />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    );
  };

  // Template Card Component
  const TemplateCard = ({ template }: { template: Template }) => {
    const statusColor = getStatusColor(template);
    const statusText = getStatusText(template);
    const coverage = template.field_coverage_percentage || 0;

    return (
      <div 
        className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl p-6 border ${darkMode ? 'border-gray-700' : 'border-gray-200'} hover:shadow-xl transition-all cursor-pointer group relative`}
        onClick={() => {
          setSelectedTemplate(template);
          setShowDetailsModal(true);
        }}
      >
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-500 bg-opacity-20 rounded-lg">
              <FileText className="w-6 h-6 text-blue-500" />
            </div>
            {template.is_default && (
              <div className="p-1 bg-yellow-500 bg-opacity-20 rounded-full">
                <Star className="w-4 h-4 text-yellow-500 fill-current" />
              </div>
            )}
          </div>
          <div className={`px-3 py-1 rounded-full ${statusColor} bg-opacity-20 flex items-center gap-1`}>
            <div className={`w-2 h-2 rounded-full ${statusColor}`} />
            <span className={`text-xs font-semibold ${statusColor.replace('bg-', 'text-')}`}>
              {statusText.toUpperCase()}
            </span>
          </div>
        </div>
        
        <h3 className="font-semibold text-lg mb-2 line-clamp-2">{template.template_name}</h3>
        
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <span className={`text-sm ${darkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {template.manufacturer?.name || 'Unknown'}
            </span>
            <span className={`text-xs px-2 py-1 rounded-full ${
              template.document_type === 'InsuranceVerification' || template.document_type === 'IVR'
                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                : template.document_type === 'OrderForm'
                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300'
            }`}>
              {template.document_type === 'InsuranceVerification' ? 'IVR' : template.document_type}
            </span>
          </div>
          
          <div className="flex items-center justify-between text-xs">
            <span className={`font-mono ${darkMode ? 'text-gray-400' : 'text-gray-500'}`}>
              ID: {template.docuseal_template_id}
            </span>
            <span className={darkMode ? 'text-gray-400' : 'text-gray-500'}>
              {formatDate(template.updated_at)}
            </span>
          </div>
          
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span className={darkMode ? 'text-gray-400' : 'text-gray-600'}>Field Coverage</span>
              <span className="font-semibold">{coverage}%</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full transition-all ${statusColor}`}
                style={{ width: `${coverage}%` }}
              />
            </div>
          </div>
          
          <div className="flex items-center justify-between text-xs text-gray-500">
            <span>{Object.keys(template.field_mappings || {}).length} fields</span>
          </div>
        </div>

        {/* Intelligence Actions */}
        {mappingMode === 'edit' && (
          <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" onClick={(e) => e.stopPropagation()}>
            <div className="flex gap-2">
              <button
                onClick={() => validateTemplate(template)}
                className="flex-1 px-3 py-2 bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-600 transition-colors flex items-center justify-center gap-2"
                title="Validate Mappings"
              >
                <Shield className="w-4 h-4" />
                Validate
              </button>
              <button
                onClick={() => {
                  setSelectedTemplateForAI(template);
                  setShowAIAnalyzer(true);
                }}
                className="flex-1 px-3 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-sm rounded-lg hover:from-purple-600 hover:to-blue-600 transition-colors flex items-center justify-center gap-2"
                title="AI Analysis"
              >
                <Brain className="w-4 h-4" />
                AI Analyze
              </button>
            </div>
          </div>
        )}

        <ChevronRight className="w-5 h-5 absolute bottom-6 right-6 opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
    );
  };

  useEffect(() => {
    fetchTemplates();
    fetchCanonicalFields();
  }, [fetchTemplates, fetchCanonicalFields]);

  useEffect(() => {
    if (templates.length > 0 && mappingMode === 'edit') {
      const templateIds = templates.map(t => t.id);
      fetchMappingStats(templateIds);
    }
  }, [templates, mappingMode, fetchMappingStats]);

  return (
    <MainLayout>
      <Head title="DocuSeal Templates" />

      <div className={`min-h-screen ${darkMode ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-900'} transition-colors`}>
        {/* Header */}
        <header className={`${darkMode ? 'bg-gray-800' : 'bg-white'} border-b ${darkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <div className="max-w-7xl mx-auto px-6 py-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-blue-500 rounded-lg">
                  <FileText className="w-6 h-6 text-white" />
                </div>
                <h1 className="text-2xl font-bold">DocuSeal Templates</h1>
                <span className="px-3 py-1 bg-blue-500 bg-opacity-20 text-blue-500 rounded-full text-xs font-semibold flex items-center gap-1">
                  <Sparkles className="w-3 h-3" />
                  AI-Powered
                </span>
              </div>
              
              <div className="flex items-center gap-4">
                <button
                  onClick={() => setDarkMode(!darkMode)}
                  className={`p-2 rounded-lg ${darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} transition-colors`}
                >
                  {darkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                </button>
                
                <button
                  onClick={() => setMappingMode(mappingMode === 'view' ? 'edit' : 'view')}
                  className={`px-4 py-2 ${mappingMode === 'edit' ? 'bg-purple-500 text-white' : 'bg-purple-100 text-purple-700 dark:bg-purple-800 dark:text-purple-200'} rounded-lg hover:bg-purple-600 hover:text-white transition-colors flex items-center gap-2`}
                >
                  <Brain className="w-4 h-4" />
                  {mappingMode === 'view' ? 'Enable Intelligence' : 'Intelligence Active'}
                </button>

                <button
                  onClick={() => syncTemplates(false)}
                  disabled={syncStatus.is_syncing}
                  className="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                  {syncStatus.is_syncing ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      Syncing...
                    </>
                  ) : (
                    <>
                      <RefreshCw className="w-4 h-4" />
                      Sync Templates
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </header>

        {/* Stats Section */}
        <div className="max-w-7xl mx-auto px-6 py-8">
          {stats && (
            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8">
              <StatCard 
                icon={FileText} 
                label="Total Templates" 
                value={stats.total_templates} 
                color="bg-blue-500 bg-opacity-20 text-blue-500" 
              />
              <StatCard 
                icon={CheckCircle2} 
                label="Active" 
                value={stats.active_templates} 
                color="bg-green-500 bg-opacity-20 text-green-500" 
              />
              <StatCard 
                icon={Package} 
                label="Manufacturers" 
                value={stats.manufacturers_covered} 
                color="bg-purple-500 bg-opacity-20 text-purple-500" 
              />
              <StatCard 
                icon={BarChart3} 
                label="Avg Coverage" 
                value={`${stats.avg_field_coverage}%`} 
                color="bg-yellow-500 bg-opacity-20 text-yellow-500" 
              />
              <StatCard 
                icon={TrendingUp} 
                label="Submissions" 
                value={stats.total_submissions} 
                color="bg-indigo-500 bg-opacity-20 text-indigo-500" 
              />
              <StatCard 
                icon={AlertTriangle} 
                label="Need Attention" 
                value={stats.templates_needing_attention} 
                color="bg-red-500 bg-opacity-20 text-red-500" 
              />
            </div>
          )}

          {/* Intelligence Stats Dashboard */}
          {mappingMode === 'edit' && stats && (
            <div className="mb-8">
              <MappingStatsDashboard
                templates={templates}
                mappingStats={mappingStats}
                canonicalFields={canonicalFields}
              />
            </div>
          )}

          {/* Controls */}
          <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl p-6 border ${darkMode ? 'border-gray-700' : 'border-gray-200'} mb-8`}>
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              <div className="flex items-center gap-4">
                <div className="relative">
                  <Search className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search templates..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className={`pl-10 pr-4 py-2 rounded-lg ${darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-200'} border focus:outline-none focus:border-blue-500`}
                  />
                </div>
                
                <select 
                  value={selectedManufacturer}
                  onChange={(e) => setSelectedManufacturer(e.target.value)}
                  className={`px-4 py-2 rounded-lg ${darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-200'} border focus:outline-none focus:border-blue-500`}
                >
                  <option value="all">All Manufacturers</option>
                  {manufacturers.map(mfg => (
                    <option key={mfg} value={mfg}>{mfg}</option>
                  ))}
                </select>

                <select 
                  value={selectedDocType}
                  onChange={(e) => setSelectedDocType(e.target.value)}
                  className={`px-4 py-2 rounded-lg ${darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-200'} border focus:outline-none focus:border-blue-500`}
                >
                  <option value="all">All Types</option>
                  {documentTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>

                <select 
                  value={filter}
                  onChange={(e) => setFilter(e.target.value)}
                  className={`px-4 py-2 rounded-lg ${darkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-200'} border focus:outline-none focus:border-blue-500`}
                >
                  <option value="all">All Templates</option>
                  <option value="active">Active Only</option>
                  <option value="inactive">Inactive Only</option>
                  <option value="needs_attention">Needs Attention</option>
                </select>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={toggleExpandAll}
                  className={`px-4 py-2 ${darkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-200 hover:bg-gray-300'} rounded-lg transition-colors flex items-center gap-2`}
                >
                  {expandAll ? (
                    <>
                      <ChevronDown className="w-4 h-4" />
                      Collapse All
                    </>
                  ) : (
                    <>
                      <ChevronRight className="w-4 h-4" />
                      Expand All
                    </>
                  )}
                </button>
                
                {mappingMode === 'edit' && (
                  <div className="flex items-center gap-2 mr-4">
                    <button
                      onClick={() => setShowBulkMappingModal(true)}
                      className="px-4 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors flex items-center gap-2"
                    >
                      <Layers className="w-4 h-4" />
                      Bulk Operations
                    </button>
                    <button
                      onClick={() => setShowAIAnalyzer(true)}
                      className="px-4 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-lg hover:from-purple-600 hover:to-blue-600 transition-colors flex items-center gap-2"
                    >
                      <Brain className="w-4 h-4" />
                      AI Analyzer
                    </button>
                  </div>
                )}
                
                <button
                  onClick={() => setViewMode('grid')}
                  className={`p-2 rounded-lg ${viewMode === 'grid' ? 'bg-blue-500 text-white' : darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} transition-colors`}
                >
                  <Grid className="w-5 h-5" />
                </button>
                <button
                  onClick={() => setViewMode('list')}
                  className={`p-2 rounded-lg ${viewMode === 'list' ? 'bg-blue-500 text-white' : darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} transition-colors`}
                >
                  <List className="w-5 h-5" />
                </button>
              </div>
            </div>
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

          {/* Templates Display */}
          {loading ? (
            <div className="text-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-blue-500 mx-auto mb-4" />
              <p className={darkMode ? 'text-gray-400' : 'text-gray-500'}>Loading templates...</p>
            </div>
          ) : filteredTemplates.length === 0 ? (
            <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl p-12 text-center`}>
              <FileText className="w-16 h-16 mx-auto mb-4 text-gray-400" />
              <h3 className="text-xl font-semibold mb-2">No templates found</h3>
              <p className={`${darkMode ? 'text-gray-400' : 'text-gray-600'} mb-4`}>
                {templates.length === 0
                  ? "No templates have been synced yet. Click 'Sync Templates' to get started."
                  : "No templates match your current filters. Try adjusting your search criteria."
                }
              </p>
              {templates.length === 0 && (
                <button
                  onClick={() => syncTemplates(false)}
                  className="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                >
                  Sync Templates Now
                </button>
              )}
            </div>
          ) : (
            <div className="space-y-6">
              {templatesByManufacturer.map(({ name, templates, stats }) => (
                <ManufacturerSection
                  key={name}
                  name={name}
                  templates={templates}
                  stats={stats}
                  isExpanded={isManufacturerExpanded(name)}
                  onToggle={() => toggleManufacturer(name)}
                />
              ))}
              
              {templatesByManufacturer.length === 0 && (
                <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl p-12 text-center`}>
                  <Package className="w-16 h-16 mx-auto mb-4 text-gray-400" />
                  <h3 className="text-xl font-semibold mb-2">No manufacturers found</h3>
                  <p className={`${darkMode ? 'text-gray-400' : 'text-gray-600'} mb-4`}>
                    Templates don't have manufacturer assignments yet.
                  </p>
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
            <div className="fixed inset-0 bg-black bg-opacity-50" />
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
                <Dialog.Panel className={`w-full max-w-4xl transform overflow-hidden rounded-2xl ${darkMode ? 'bg-gray-800' : 'bg-white'} p-6 text-left align-middle shadow-xl transition-all`}>
                  {selectedTemplate && (
                    <>
                      <div className="flex items-center justify-between mb-6">
                        <div>
                          <Dialog.Title className="text-2xl font-bold">
                            {selectedTemplate.template_name}
                          </Dialog.Title>
                          <p className={`${darkMode ? 'text-gray-400' : 'text-gray-600'} mt-1`}>
                            {selectedTemplate.manufacturer?.name} • {selectedTemplate.document_type}
                          </p>
                        </div>
                        <button
                          onClick={() => setShowDetailsModal(false)}
                          className={`${darkMode ? 'text-gray-400 hover:text-gray-200' : 'text-gray-400 hover:text-gray-600'}`}
                        >
                          <X className="w-6 h-6" />
                        </button>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="space-y-4">
                          <div className={`${darkMode ? 'bg-gray-700' : 'bg-gray-50'} rounded-lg p-4`}>
                            <h3 className="font-semibold mb-3">Template Information</h3>
                            <dl className="space-y-2 text-sm">
                              <div className="flex justify-between">
                                <dt className={darkMode ? 'text-gray-400' : 'text-gray-600'}>DocuSeal ID:</dt>
                                <dd className="font-mono">{selectedTemplate.docuseal_template_id}</dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className={darkMode ? 'text-gray-400' : 'text-gray-600'}>Status:</dt>
                                <dd>
                                  {selectedTemplate.is_active ? (
                                    <span className="text-green-600">Active</span>
                                  ) : (
                                    <span className="text-red-600">Inactive</span>
                                  )}
                                </dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className={darkMode ? 'text-gray-400' : 'text-gray-600'}>Default:</dt>
                                <dd>
                                  {selectedTemplate.is_default ? (
                                    <span className="text-blue-600">Yes</span>
                                  ) : (
                                    <span className={darkMode ? 'text-gray-400' : 'text-gray-500'}>No</span>
                                  )}
                                </dd>
                              </div>
                              <div className="flex justify-between">
                                <dt className={darkMode ? 'text-gray-400' : 'text-gray-600'}>Last Updated:</dt>
                                <dd>{formatDate(selectedTemplate.updated_at)}</dd>
                              </div>
                            </dl>
                          </div>

                          <div className={`${darkMode ? 'bg-blue-900' : 'bg-blue-50'} rounded-lg p-4`}>
                            <h3 className={`font-semibold ${darkMode ? 'text-blue-200' : 'text-blue-900'} mb-3`}>Field Mappings</h3>
                            <div className="space-y-2">
                              <div className="flex justify-between">
                                <span className={darkMode ? 'text-blue-300' : 'text-blue-700'}>Total Fields:</span>
                                <span className="font-semibold">{Object.keys(selectedTemplate.field_mappings || {}).length}</span>
                              </div>
                              <div className="flex justify-between">
                                <span className={darkMode ? 'text-blue-300' : 'text-blue-700'}>Coverage:</span>
                                <span className="font-semibold">{selectedTemplate.field_coverage_percentage || 0}%</span>
                              </div>
                              <div className="mt-3">
                                <div className={`${darkMode ? 'bg-blue-800' : 'bg-blue-200'} rounded-full h-3`}>
                                  <div
                                    className="bg-blue-600 h-3 rounded-full transition-all"
                                    style={{ width: `${selectedTemplate.field_coverage_percentage || 0}%` }}
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="space-y-4">
                          <div className={`${darkMode ? 'bg-green-900' : 'bg-green-50'} rounded-lg p-4`}>
                            <h3 className={`font-semibold ${darkMode ? 'text-green-200' : 'text-green-900'} mb-3`}>Sample Fields</h3>
                            <div className="space-y-2 max-h-64 overflow-y-auto">
                              {Object.entries(selectedTemplate.field_mappings || {}).slice(0, 10).map(([field, mapping]: [string, any]) => (
                                <div key={field} className="flex justify-between text-sm">
                                  <span className={`${darkMode ? 'text-green-300' : 'text-green-700'} font-mono`}>{field}</span>
                                  <span className={darkMode ? 'text-green-400' : 'text-green-600'}>{mapping?.system_field || 'unmapped'}</span>
                                </div>
                              ))}
                              {Object.keys(selectedTemplate.field_mappings || {}).length > 10 && (
                                <div className={`text-xs ${darkMode ? 'text-green-400' : 'text-green-600'} text-center`}>
                                  ... and {Object.keys(selectedTemplate.field_mappings || {}).length - 10} more fields
                                </div>
                              )}
                            </div>
                          </div>

                          <div className="space-y-3">
                            <button
                              onClick={() => window.open(`https://app.docuseal.com/templates/${selectedTemplate.docuseal_template_id}/edit`, '_blank')}
                              className="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                            >
                              <Edit3 className="w-5 h-5" />
                              Edit in DocuSeal
                            </button>
                            <button
                              onClick={() => window.open(`https://app.docuseal.com/templates/${selectedTemplate.docuseal_template_id}`, '_blank')}
                              className={`w-full ${darkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-600 hover:bg-gray-700'} text-white py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2`}
                            >
                              <ExternalLink className="w-5 h-5" />
                              View in DocuSeal
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

      {/* Bulk Mapping Modal */}
      {showBulkMappingModal && (
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
          if (selectedTemplateForAI && analysis.auto_mappings) {
            fetchTemplates();
          }
        }}
      />
    </MainLayout>
  );
}
