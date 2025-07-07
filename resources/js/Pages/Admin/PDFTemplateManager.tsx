import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiUpload, FiFile, FiCheck, FiX, FiEdit, FiTrash2, FiDownload, FiEye, FiRefreshCw, FiSettings, FiAlertCircle, FiInfo, FiChevronDown, FiChevronUp } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { fetchWithCSRF } from '@/utils/csrf';

interface PDFTemplate {
  id: number;
  manufacturer_id: number;
  template_name: string;
  document_type: string;
  file_path: string;
  version: string;
  is_active: boolean;
  template_fields: string[] | null;
  metadata: {
    uploaded_by?: number;
    uploaded_at?: string;
    file_size?: number;
    field_count?: number;
    [key: string]: any;
  } | null;
  created_at: string;
  updated_at: string;
  manufacturer: {
    id: number;
    name: string;
  };
  field_mappings: Array<{
    id: number;
    pdf_field_name: string;
    data_source: string;
    field_type: string;
    is_required: boolean;
  }>;
}

interface Manufacturer {
  id: number;
  name: string;
}

interface PageProps {
  templates: {
    data: PDFTemplate[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  manufacturers: Manufacturer[];
  filters: {
    manufacturer_id?: string;
    document_type?: string;
    is_active?: boolean;
  };
  flash?: {
    success?: string;
    error?: string;
    upload_debug?: any;
  };
}

export default function PDFTemplateManager({ templates, manufacturers, filters, flash }: PageProps) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [selectedTemplate, setSelectedTemplate] = useState<PDFTemplate | null>(null);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [showDebugInfo, setShowDebugInfo] = useState(false);
  const [debugMode, setDebugMode] = useState(false);
  const [uploadDebugInfo, setUploadDebugInfo] = useState<any>(flash?.upload_debug || null);
  const [showFlashMessage, setShowFlashMessage] = useState(true);

  const { data, setData, post, processing, errors, reset } = useForm({
    manufacturer_id: '',
    template_name: '',
    document_type: 'ivr',
    version: '1.0',
    pdf_file: null as File | null,
    is_active: false,
    metadata: {},
  });

  const documentTypes = [
    { value: 'ivr', label: 'IVR Form' },
    { value: 'order_form', label: 'Order Form' },
    { value: 'shipping_label', label: 'Shipping Label' },
    { value: 'invoice', label: 'Invoice' },
    { value: 'other', label: 'Other' },
  ];

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) {
      console.log('No file selected');
      return;
    }
    
    console.log('File selected:', {
      name: file.name,
      type: file.type,
      size: file.size,
      sizeMB: (file.size / 1024 / 1024).toFixed(2)
    });
    
    // Validate file type
    if (file.type !== 'application/pdf') {
      alert('Please select a valid PDF file');
      return;
    }
    
    // Check file size (10MB limit - PHP upload_max_filesize)
    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if (file.size > maxSize) {
      alert(`File size too large. Maximum allowed size is 10MB. Your file is ${(file.size / 1024 / 1024).toFixed(2)}MB.`);
      return;
    }
    
    setData('pdf_file', file);
    console.log('File set in form data');
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    
    console.log('Form submission started');
    console.log('Current form data:', {
      manufacturer_id: data.manufacturer_id,
      template_name: data.template_name,
      document_type: data.document_type,
      version: data.version,
      pdf_file: data.pdf_file ? data.pdf_file.name : 'No file',
      is_active: data.is_active
    });
    
    // Validate file is present
    if (!data.pdf_file) {
      console.error('No PDF file in form data');
      alert('Please select a PDF file before uploading');
      return;
    }
    
    // Create FormData manually
    const formData = new FormData();
    formData.append('manufacturer_id', data.manufacturer_id);
    formData.append('template_name', data.template_name);
    formData.append('document_type', data.document_type);
    formData.append('version', data.version);
    formData.append('is_active', data.is_active ? '1' : '0');
    // Don't send metadata if it's empty
    if (Object.keys(data.metadata).length > 0) {
      formData.append('metadata', JSON.stringify(data.metadata));
    }
    formData.append('pdf_file', data.pdf_file);
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    // Use direct fetch instead of Inertia
    const uploadUrl = debugMode 
      ? route('admin.pdf-templates.store') + '?debug=1'
      : route('admin.pdf-templates.store');
    
    console.log('Uploading to:', uploadUrl);
    
    try {
      const response = await fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      
      const result = await response.json();
      
      if (response.ok) {
        console.log('Upload successful');
        // Redirect to the show page using Inertia
        router.visit(result.redirect || route('admin.pdf-templates.index'));
      } else if (response.status === 422) {
        // Validation errors
        console.error('Validation errors:', result.errors);
        Object.keys(result.errors).forEach(key => {
          errors[key] = result.errors[key];
        });
        if (result.upload_debug) {
          setUploadDebugInfo(result.upload_debug);
        }
      } else {
        console.error('Upload failed:', result);
        alert(result.message || 'Upload failed. Please try again.');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Network error. Please check your connection and try again.');
    }
  };

  const handleActivate = async (template: PDFTemplate) => {
    if (!confirm(`Activate template "${template.template_name}"? This will deactivate other templates of the same type.`)) {
      return;
    }

    router.post(route('admin.pdf-templates.activate', template.id), {}, {
      preserveScroll: true,
    });
  };

  const handleDeactivate = async (template: PDFTemplate) => {
    if (!confirm(`Deactivate template "${template.template_name}"?`)) {
      return;
    }

    router.post(route('admin.pdf-templates.deactivate', template.id), {}, {
      preserveScroll: true,
    });
  };

  const handleDelete = async (template: PDFTemplate) => {
    if (!confirm(`Delete template "${template.template_name}"? This action cannot be undone.`)) {
      return;
    }

    router.delete(route('admin.pdf-templates.destroy', template.id), {
      preserveScroll: true,
    });
  };

  const handleExtractFields = async (template: PDFTemplate) => {
    try {
      const response = await fetchWithCSRF(route('admin.pdf-templates.extract-fields', template.id), {
        method: 'POST',
      });

      const result = await response.json();
      
      if (result.success) {
        alert(`Successfully extracted ${result.count} fields from the PDF.`);
        router.reload({ preserveScroll: true });
      } else {
        alert(`Failed to extract fields: ${result.error}`);
      }
    } catch (error) {
      console.error('Error extracting fields:', error);
      alert('Failed to extract fields from the PDF.');
    }
  };

  const applyFilters = (newFilters: Partial<typeof filters>) => {
    router.get(route('admin.pdf-templates.index'), {
      ...filters,
      ...newFilters,
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <MainLayout>
      <Head title="PDF Template Manager" />

      <div className={cn("min-h-screen", t.background.base, t.background.noise)}>
        <div className="max-w-7xl mx-auto p-6">
          {/* Flash Messages */}
          {showFlashMessage && flash && (
            <>
              {flash.success && (
                <div className={cn(
                  "mb-6 p-4 rounded-lg border flex items-start justify-between",
                  theme === 'dark'
                    ? 'bg-green-900/20 border-green-800 text-green-300'
                    : 'bg-green-50 border-green-200 text-green-700'
                )}>
                  <div className="flex items-start gap-2">
                    <FiCheck className="h-5 w-5 mt-0.5 flex-shrink-0" />
                    <p>{flash.success}</p>
                  </div>
                  <button
                    onClick={() => setShowFlashMessage(false)}
                    className="text-current hover:opacity-70"
                  >
                    <FiX className="h-4 w-4" />
                  </button>
                </div>
              )}
              {flash.error && (
                <div className={cn(
                  "mb-6 p-4 rounded-lg border",
                  theme === 'dark'
                    ? 'bg-red-900/20 border-red-800 text-red-300'
                    : 'bg-red-50 border-red-200 text-red-700'
                )}>
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-2 flex-1">
                      <FiAlertCircle className="h-5 w-5 mt-0.5 flex-shrink-0" />
                      <div className="flex-1">
                        <p>{flash.error}</p>
                        {flash.upload_debug && (
                          <button
                            onClick={() => setShowDebugInfo(!showDebugInfo)}
                            className={cn(
                              "mt-2 flex items-center gap-1 text-xs font-medium",
                              theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
                            )}
                          >
                            <FiInfo className="h-3 w-3" />
                            View Debug Information
                            {showDebugInfo ? <FiChevronUp className="h-3 w-3" /> : <FiChevronDown className="h-3 w-3" />}
                          </button>
                        )}
                        {showDebugInfo && flash.upload_debug && (
                          <div className={cn(
                            "mt-2 p-3 rounded-lg text-xs font-mono overflow-x-auto",
                            theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                          )}>
                            <pre>{JSON.stringify(flash.upload_debug, null, 2)}</pre>
                          </div>
                        )}
                      </div>
                    </div>
                    <button
                      onClick={() => setShowFlashMessage(false)}
                      className="text-current hover:opacity-70 ml-4"
                    >
                      <FiX className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              )}
            </>
          )}

          {/* Header */}
          <div className="mb-8">
            <h1 className={cn("text-3xl font-bold mb-2", t.text.primary)}>
              PDF Template Manager
            </h1>
            <p className={cn(t.text.secondary)}>
              Manage PDF templates for manufacturer IVR forms and documents
            </p>
          </div>

          {/* Filters and Actions */}
          <div className={cn("mb-6 p-6 rounded-xl", t.glass.card)}>
            <div className="flex flex-wrap gap-4 items-center justify-between">
              <div className="flex flex-wrap gap-4">
                {/* Manufacturer Filter */}
                <select
                  value={filters.manufacturer_id || ''}
                  onChange={(e) => applyFilters({ manufacturer_id: e.target.value || undefined })}
                  className={cn(
                    "px-4 py-2 rounded-lg border",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white'
                      : 'bg-white border-gray-300 text-gray-900'
                  )}
                >
                  <option value="">All Manufacturers</option>
                  {manufacturers.map((manufacturer) => (
                    <option key={manufacturer.id} value={manufacturer.id}>
                      {manufacturer.name}
                    </option>
                  ))}
                </select>

                {/* Document Type Filter */}
                <select
                  value={filters.document_type || ''}
                  onChange={(e) => applyFilters({ document_type: e.target.value || undefined })}
                  className={cn(
                    "px-4 py-2 rounded-lg border",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white'
                      : 'bg-white border-gray-300 text-gray-900'
                  )}
                >
                  <option value="">All Types</option>
                  {documentTypes.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>

                {/* Active Status Filter */}
                <select
                  value={filters.is_active === undefined ? '' : filters.is_active ? '1' : '0'}
                  onChange={(e) => applyFilters({ 
                    is_active: e.target.value === '' ? undefined : e.target.value === '1' 
                  })}
                  className={cn(
                    "px-4 py-2 rounded-lg border",
                    theme === 'dark'
                      ? 'bg-gray-800 border-gray-700 text-white'
                      : 'bg-white border-gray-300 text-gray-900'
                  )}
                >
                  <option value="">All Status</option>
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              {/* Upload Button */}
              <button
                onClick={() => setShowUploadModal(true)}
                className={cn(
                  "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors",
                  theme === 'dark'
                    ? 'bg-blue-700 hover:bg-blue-600 text-white'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                )}
              >
                <FiUpload className="h-4 w-4" />
                Upload Template
              </button>
            </div>
          </div>

          {/* Templates Table */}
          <div className={cn("rounded-xl overflow-hidden", t.glass.card)}>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className={cn("border-b", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
                    <th className={cn("text-left p-4", t.text.secondary)}>Template Name</th>
                    <th className={cn("text-left p-4", t.text.secondary)}>Manufacturer</th>
                    <th className={cn("text-left p-4", t.text.secondary)}>Type</th>
                    <th className={cn("text-left p-4", t.text.secondary)}>Version</th>
                    <th className={cn("text-center p-4", t.text.secondary)}>Fields</th>
                    <th className={cn("text-center p-4", t.text.secondary)}>Status</th>
                    <th className={cn("text-left p-4", t.text.secondary)}>Uploaded</th>
                    <th className={cn("text-right p-4", t.text.secondary)}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {templates.data.map((template) => (
                    <tr key={template.id} className={cn(
                      "border-b transition-colors",
                      theme === 'dark' 
                        ? 'border-gray-800 hover:bg-gray-800/50' 
                        : 'border-gray-100 hover:bg-gray-50'
                    )}>
                      <td className="p-4">
                        <div className="flex items-center gap-2">
                          <FiFile className={cn("h-4 w-4", t.text.muted)} />
                          <span className={cn("font-medium", t.text.primary)}>
                            {template.template_name}
                          </span>
                        </div>
                      </td>
                      <td className={cn("p-4", t.text.secondary)}>
                        {template.manufacturer.name}
                      </td>
                      <td className={cn("p-4", t.text.secondary)}>
                        <span className={cn(
                          "px-2 py-1 text-xs font-medium rounded-full",
                          theme === 'dark' ? 'bg-gray-700' : 'bg-gray-200'
                        )}>
                          {documentTypes.find(t => t.value === template.document_type)?.label || template.document_type}
                        </span>
                      </td>
                      <td className={cn("p-4", t.text.secondary)}>
                        v{template.version}
                      </td>
                      <td className="p-4 text-center">
                        {template.template_fields ? (
                          <span className={cn(
                            "px-2 py-1 text-xs font-medium rounded-full",
                            theme === 'dark' ? 'bg-blue-900/20 text-blue-400' : 'bg-blue-100 text-blue-700'
                          )}>
                            {template.template_fields.length}
                          </span>
                        ) : (
                          <button
                            onClick={() => handleExtractFields(template)}
                            className={cn(
                              "text-xs font-medium hover:underline",
                              theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
                            )}
                          >
                            Extract
                          </button>
                        )}
                      </td>
                      <td className="p-4 text-center">
                        {template.is_active ? (
                          <span className="flex items-center justify-center gap-1 text-green-500">
                            <FiCheck className="h-4 w-4" />
                            <span className="text-xs">Active</span>
                          </span>
                        ) : (
                          <span className="flex items-center justify-center gap-1 text-gray-400">
                            <FiX className="h-4 w-4" />
                            <span className="text-xs">Inactive</span>
                          </span>
                        )}
                      </td>
                      <td className={cn("p-4 text-sm", t.text.secondary)}>
                        {new Date(template.created_at).toLocaleDateString()}
                      </td>
                      <td className="p-4">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => router.visit(route('admin.pdf-templates.show', template.id))}
                            className={cn(
                              "p-1.5 rounded hover:bg-gray-700/50 transition-colors",
                              t.text.secondary
                            )}
                            title="View Details"
                          >
                            <FiEye className="h-4 w-4" />
                          </button>

                          {template.is_active ? (
                            <button
                              onClick={() => handleDeactivate(template)}
                              className="p-1.5 rounded hover:bg-gray-700/50 transition-colors text-yellow-500"
                              title="Deactivate"
                            >
                              <FiX className="h-4 w-4" />
                            </button>
                          ) : (
                            <button
                              onClick={() => handleActivate(template)}
                              className="p-1.5 rounded hover:bg-gray-700/50 transition-colors text-green-500"
                              title="Activate"
                            >
                              <FiCheck className="h-4 w-4" />
                            </button>
                          )}

                          <button
                            onClick={() => handleDelete(template)}
                            className="p-1.5 rounded hover:bg-gray-700/50 transition-colors text-red-500"
                            title="Delete"
                          >
                            <FiTrash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {templates.last_page > 1 && (
              <div className={cn(
                "flex items-center justify-between p-4 border-t",
                theme === 'dark' ? 'border-gray-700' : 'border-gray-200'
              )}>
                <div className={cn("text-sm", t.text.secondary)}>
                  Showing {templates.data.length} of {templates.total} templates
                </div>
                <div className="flex gap-2">
                  {Array.from({ length: templates.last_page }, (_, i) => i + 1).map((page) => (
                    <button
                      key={page}
                      onClick={() => router.get(route('admin.pdf-templates.index'), { 
                        ...filters, 
                        page 
                      }, { 
                        preserveState: true,
                        preserveScroll: true,
                      })}
                      className={cn(
                        "px-3 py-1 rounded text-sm font-medium transition-colors",
                        page === templates.current_page
                          ? theme === 'dark'
                            ? 'bg-blue-700 text-white'
                            : 'bg-blue-600 text-white'
                          : cn(t.text.secondary, "hover:bg-gray-700/50")
                      )}
                    >
                      {page}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Upload Modal */}
        {showUploadModal && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className={cn("max-w-lg w-full rounded-xl p-6", t.glass.card)}>
              <h2 className={cn("text-xl font-bold mb-4", t.text.primary)}>
                Upload PDF Template
              </h2>

              <form onSubmit={handleUpload} className="space-y-4">
                {/* Test Upload Button (Temporary Debug) */}
                {debugMode && (
                  <div className="mb-4 p-3 bg-yellow-500/10 rounded-lg">
                    <p className="text-sm text-yellow-600 mb-2">Debug: Test file upload directly</p>
                    <button
                      type="button"
                      onClick={async () => {
                        if (!data.pdf_file) {
                          alert('Select a file first');
                          return;
                        }
                        
                        const formData = new FormData();
                        formData.append('pdf_file', data.pdf_file);
                        formData.append('test', 'true');
                        
                        try {
                          const response = await fetch(route('admin.test-upload'), {
                            method: 'POST',
                            body: formData,
                            headers: {
                              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                              'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                          });
                          
                          const result = await response.json();
                          console.log('Test upload result:', result);
                          alert('Check console for debug info');
                        } catch (error) {
                          console.error('Test upload error:', error);
                        }
                      }}
                      className="px-3 py-1 bg-yellow-600 text-white rounded text-sm"
                    >
                      Test File Detection
                    </button>
                  </div>
                )}
                {/* General Error Display */}
                {Object.keys(errors).length > 0 && !errors.pdf_file && (
                  <div className={cn(
                    "p-4 rounded-lg border",
                    theme === 'dark' 
                      ? 'bg-red-900/20 border-red-800 text-red-300'
                      : 'bg-red-50 border-red-200 text-red-700'
                  )}>
                    <div className="flex items-start gap-2">
                      <FiAlertCircle className="h-5 w-5 mt-0.5 flex-shrink-0" />
                      <div>
                        <p className="font-medium">Upload Error</p>
                        <ul className="mt-1 text-sm space-y-1">
                          {Object.entries(errors).map(([field, message]) => (
                            field !== 'pdf_file' && field !== 'upload_debug' && (
                              <li key={field}>• {message}</li>
                            )
                          ))}
                        </ul>
                      </div>
                    </div>
                  </div>
                )}

                {/* Manufacturer */}
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Manufacturer
                  </label>
                  <select
                    value={data.manufacturer_id}
                    onChange={(e) => setData('manufacturer_id', e.target.value)}
                    className={cn(
                      "w-full px-3 py-2 rounded-lg border",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                    required
                  >
                    <option value="">Select Manufacturer</option>
                    {manufacturers.map((manufacturer) => (
                      <option key={manufacturer.id} value={manufacturer.id}>
                        {manufacturer.name}
                      </option>
                    ))}
                  </select>
                  {errors.manufacturer_id && (
                    <p className="mt-1 text-sm text-red-500 flex items-center gap-1">
                      <FiAlertCircle className="h-3 w-3" />
                      {errors.manufacturer_id}
                    </p>
                  )}
                </div>

                {/* Template Name */}
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Template Name
                  </label>
                  <input
                    type="text"
                    value={data.template_name}
                    onChange={(e) => setData('template_name', e.target.value)}
                    className={cn(
                      "w-full px-3 py-2 rounded-lg border",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                    placeholder="e.g., IVR Form - January 2024"
                    required
                  />
                  {errors.template_name && (
                    <p className="mt-1 text-sm text-red-500 flex items-center gap-1">
                      <FiAlertCircle className="h-3 w-3" />
                      {errors.template_name}
                    </p>
                  )}
                </div>

                {/* Document Type */}
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Document Type
                  </label>
                  <select
                    value={data.document_type}
                    onChange={(e) => setData('document_type', e.target.value)}
                    className={cn(
                      "w-full px-3 py-2 rounded-lg border",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                    required
                  >
                    {documentTypes.map((type) => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </select>
                  {errors.document_type && (
                    <p className="mt-1 text-sm text-red-500 flex items-center gap-1">
                      <FiAlertCircle className="h-3 w-3" />
                      {errors.document_type}
                    </p>
                  )}
                </div>

                {/* Version */}
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    Version
                  </label>
                  <input
                    type="text"
                    value={data.version}
                    onChange={(e) => setData('version', e.target.value)}
                    className={cn(
                      "w-full px-3 py-2 rounded-lg border",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                    placeholder="e.g., 1.0"
                    required
                  />
                  {errors.version && (
                    <p className="mt-1 text-sm text-red-500 flex items-center gap-1">
                      <FiAlertCircle className="h-3 w-3" />
                      {errors.version}
                    </p>
                  )}
                </div>

                {/* PDF File */}
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
                    PDF File
                  </label>
                  <div className={cn(
                    "border-2 border-dashed rounded-lg p-4 text-center",
                    theme === 'dark'
                      ? 'border-gray-700 hover:border-blue-500'
                      : 'border-gray-300 hover:border-blue-500'
                  )}>
                    <input
                      type="file"
                      accept=".pdf,application/pdf"
                      onChange={handleFileChange}
                      className="hidden"
                      id="pdf-file"
                      name="pdf_file"
                    />
                    <label
                      htmlFor="pdf-file"
                      className="cursor-pointer flex flex-col items-center gap-2"
                    >
                      <FiUpload className={cn("h-8 w-8", t.text.muted)} />
                      {data.pdf_file ? (
                        <div className={cn("text-sm", t.text.primary)}>
                          <div className="font-medium">{data.pdf_file.name}</div>
                          <div className={cn("text-xs mt-1", t.text.secondary)}>
                            {(data.pdf_file.size / 1024 / 1024).toFixed(2)}MB
                          </div>
                        </div>
                      ) : (
                        <div className={cn("text-sm", t.text.secondary)}>
                          <div>Click to select PDF file</div>
                          <div className="text-xs mt-1">Max size: 10MB</div>
                        </div>
                      )}
                    </label>
                  </div>
                  {errors.pdf_file && (
                    <div className="mt-2">
                      <div className="flex items-start gap-2">
                        <FiAlertCircle className="h-4 w-4 text-red-500 mt-0.5 flex-shrink-0" />
                        <div className="flex-1">
                          <p className="text-sm text-red-500">{errors.pdf_file}</p>
                          {uploadDebugInfo && (
                            <div className="mt-2">
                              <button
                                type="button"
                                onClick={() => setShowDebugInfo(!showDebugInfo)}
                                className={cn(
                                  "flex items-center gap-1 text-xs font-medium",
                                  theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
                                )}
                              >
                                <FiInfo className="h-3 w-3" />
                                Debug Information
                                {showDebugInfo ? <FiChevronUp className="h-3 w-3" /> : <FiChevronDown className="h-3 w-3" />}
                              </button>
                              {showDebugInfo && (
                                <div className={cn(
                                  "mt-2 p-3 rounded-lg text-xs font-mono overflow-x-auto",
                                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                                )}>
                                  <pre>{JSON.stringify(uploadDebugInfo, null, 2)}</pre>
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* Active Checkbox */}
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="rounded border-gray-300"
                  />
                  <label htmlFor="is_active" className={cn("text-sm", t.text.secondary)}>
                    Set as active template
                  </label>
                </div>

                {/* Debug Mode Checkbox (only show in development) */}
                {process.env.NODE_ENV === 'development' && (
                  <div className="flex items-center gap-2 pt-2 border-t border-gray-700">
                    <input
                      type="checkbox"
                      id="debug_mode"
                      checked={debugMode}
                      onChange={(e) => setDebugMode(e.target.checked)}
                      className="rounded border-gray-300"
                    />
                    <label htmlFor="debug_mode" className={cn("text-sm", t.text.muted)}>
                      Enable debug mode (verbose error reporting)
                    </label>
                  </div>
                )}

                {/* Progress Bar */}
                {processing && uploadProgress > 0 && (
                  <div className="mt-4">
                    <div className="flex items-center justify-between mb-1">
                      <span className={cn("text-sm", t.text.secondary)}>Uploading...</span>
                      <span className={cn("text-sm", t.text.secondary)}>{uploadProgress}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                      <div 
                        className="h-full bg-blue-600 transition-all duration-300"
                        style={{ width: `${uploadProgress}%` }}
                      />
                    </div>
                  </div>
                )}

                {/* Actions */}
                <div className="flex gap-3 pt-4">
                  <button
                    type="submit"
                    disabled={processing}
                    className={cn(
                      "flex-1 px-4 py-2 rounded-lg font-medium transition-colors",
                      theme === 'dark'
                        ? 'bg-blue-700 hover:bg-blue-600 text-white disabled:bg-gray-700'
                        : 'bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-300',
                      'disabled:cursor-not-allowed'
                    )}
                  >
                    {processing ? 'Uploading...' : 'Upload Template'}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setShowUploadModal(false);
                      reset();
                      setUploadDebugInfo(null);
                      setShowDebugInfo(false);
                      setDebugMode(false);
                    }}
                    className={cn(
                      "px-4 py-2 rounded-lg font-medium transition-colors",
                      theme === 'dark'
                        ? 'bg-gray-700 hover:bg-gray-600 text-white'
                        : 'bg-gray-200 hover:bg-gray-300 text-gray-800'
                    )}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}