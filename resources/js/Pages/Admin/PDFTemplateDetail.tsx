import { useState, useEffect } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiArrowLeft, FiSave, FiFile, FiMapPin, FiTestTube, FiDownload, FiRefreshCw, FiTrash2, FiPlus, FiCpu, FiZap } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { fetchWithCSRF } from '@/utils/csrf';
import PDFFieldMapper from '@/Components/Admin/PDFFieldMapper';
import AIMappingSuggestions from '@/Components/Admin/AIMappingSuggestions';
import { useForm } from '@inertiajs/react';

interface FieldMapping {
  id?: number;
  pdf_field_name: string;
  data_source: string;
  field_type: 'text' | 'checkbox' | 'radio' | 'select' | 'signature' | 'date' | 'image';
  transform_function?: string;
  default_value?: string;
  is_required: boolean;
  display_order: number;
  validation_rules?: any;
  options?: any;
}

interface PDFTemplate {
  id: number;
  manufacturer_id: number;
  template_name: string;
  document_type: string;
  file_path: string;
  version: string;
  is_active: boolean;
  template_fields: string[] | null;
  metadata: any;
  created_at: string;
  updated_at: string;
  manufacturer: {
    id: number;
    name: string;
  };
  field_mappings: FieldMapping[];
}

interface PageProps {
  template: PDFTemplate;
  dataSources: Record<string, Record<string, string>>;
  sampleData: Record<string, any>;
}

export default function PDFTemplateDetail({ template, dataSources, sampleData }: PageProps) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [mappings, setMappings] = useState<FieldMapping[]>(template.field_mappings || []);
  const [testData, setTestData] = useState(sampleData);
  const [isSaving, setIsSaving] = useState(false);
  const [isTestingFill, setIsTestingFill] = useState(false);
  const [showTestDataModal, setShowTestDataModal] = useState(false);
  const [isAnalyzingWithAI, setIsAnalyzingWithAI] = useState(false);
  const [aiAnalysisResult, setAiAnalysisResult] = useState<any>(null);

  // Initialize mappings for template fields that don't have mappings yet
  useEffect(() => {
    if (template.template_fields && template.template_fields.length > 0) {
      const existingFieldNames = mappings.map(m => m.pdf_field_name);
      const newMappings: FieldMapping[] = [];

      template.template_fields.forEach((field, index) => {
        if (!existingFieldNames.includes(field)) {
          newMappings.push({
            pdf_field_name: field,
            data_source: '',
            field_type: 'text',
            is_required: false,
            display_order: mappings.length + index,
          });
        }
      });

      if (newMappings.length > 0) {
        setMappings([...mappings, ...newMappings]);
      }
    }
  }, [template.template_fields]);

  const handleMappingChange = (index: number, updates: Partial<FieldMapping>) => {
    const newMappings = [...mappings];
    newMappings[index] = { ...newMappings[index], ...updates };
    setMappings(newMappings);
  };

  const handleAddMapping = () => {
    setMappings([...mappings, {
      pdf_field_name: '',
      data_source: '',
      field_type: 'text',
      is_required: false,
      display_order: mappings.length,
    }]);
  };

  const handleRemoveMapping = (index: number) => {
    setMappings(mappings.filter((_, i) => i !== index));
  };

  const handleSaveMappings = async () => {
    setIsSaving(true);

    try {
      const response = await fetchWithCSRF(route('admin.pdf-templates.update', template.id), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          field_mappings: mappings.filter(m => m.pdf_field_name && m.data_source),
        }),
      });

      if (response.ok) {
        router.reload({ preserveScroll: true });
      } else {
        const error = await response.json();
        alert(error.message || 'Failed to save mappings');
      }
    } catch (error) {
      console.error('Error saving mappings:', error);
      alert('Failed to save mappings');
    } finally {
      setIsSaving(false);
    }
  };

  const handleTestFill = async () => {
    setIsTestingFill(true);

    try {
      const response = await fetchWithCSRF(route('admin.pdf-templates.test-fill', template.id), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          test_data: testData,
        }),
      });

      if (response.ok) {
        // Get the blob from response
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `test-${template.template_name}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
      } else {
        const error = await response.json();
        alert(error.error || 'Failed to generate test PDF');
      }
    } catch (error) {
      console.error('Error testing fill:', error);
      alert('Failed to generate test PDF');
    } finally {
      setIsTestingFill(false);
    }
  };

  const handleAnalyzeWithAI = async () => {
    setIsAnalyzingWithAI(true);
    setAiAnalysisResult(null);

    try {
      const response = await fetchWithCSRF(route('admin.pdf-templates.analyze-with-ai', template.id), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        setAiAnalysisResult(data);
        // Refresh the page to show updated fields
        router.reload({ preserveScroll: true });
      } else {
        alert(data.error || 'Failed to analyze template with AI');
      }
    } catch (error) {
      console.error('Error analyzing with AI:', error);
      alert('Failed to analyze template with AI');
    } finally {
      setIsAnalyzingWithAI(false);
    }
  };

  const handleSuggestionsApplied = () => {
    // Reload the page to show updated mappings
    router.reload({ preserveScroll: true });
  };

  const transformFunctions = [
    { value: '', label: 'None' },
    { value: 'date:m/d/Y', label: 'Date (MM/DD/YYYY)' },
    { value: 'date:Y-m-d', label: 'Date (YYYY-MM-DD)' },
    { value: 'uppercase', label: 'UPPERCASE' },
    { value: 'lowercase', label: 'lowercase' },
    { value: 'phone', label: 'Phone Format' },
    { value: 'ssn', label: 'SSN Format' },
    { value: 'boolean:Yes/No', label: 'Boolean (Yes/No)' },
    { value: 'boolean:Y/N', label: 'Boolean (Y/N)' },
    { value: 'boolean:X/', label: 'Boolean (X/)' },
  ];

  const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'date', label: 'Date' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'radio', label: 'Radio' },
    { value: 'select', label: 'Select' },
    { value: 'signature', label: 'Signature' },
    { value: 'image', label: 'Image' },
  ];

  return (
    <MainLayout>
      <Head title={`PDF Template - ${template.template_name}`} />

      <div className={cn("min-h-screen", t.background.base, t.background.noise)}>
        <div className="max-w-7xl mx-auto p-6">
          {/* Header */}
          <div className="mb-8">
            <Link
              href={route('admin.pdf-templates.index')}
              className={cn(
                "inline-flex items-center gap-2 text-sm mb-4",
                t.text.secondary,
                "hover:text-blue-500 transition-colors"
              )}
            >
              <FiArrowLeft className="h-4 w-4" />
              Back to Templates
            </Link>

            <div className="flex items-start justify-between">
              <div>
                <h1 className={cn("text-3xl font-bold mb-2", t.text.primary)}>
                  {template.template_name}
                </h1>
                <div className="flex items-center gap-4 text-sm">
                  <span className={cn(t.text.secondary)}>
                    {template.manufacturer.name}
                  </span>
                  <span className={cn(t.text.muted)}>•</span>
                  <span className={cn(t.text.secondary)}>
                    Version {template.version}
                  </span>
                  <span className={cn(t.text.muted)}>•</span>
                  <span className={cn(
                    "px-2 py-0.5 rounded-full text-xs font-medium",
                    template.is_active
                      ? 'bg-green-500/20 text-green-400'
                      : 'bg-gray-500/20 text-gray-400'
                  )}>
                    {template.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
              </div>

              <div className="flex gap-2">
                <button
                  onClick={handleAnalyzeWithAI}
                  disabled={isAnalyzingWithAI}
                  className={cn(
                    "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors",
                    "bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700",
                    "text-white shadow-lg hover:shadow-xl",
                    'disabled:opacity-50 disabled:cursor-not-allowed'
                  )}
                >
                  <FiCpu className="h-4 w-4" />
                  {isAnalyzingWithAI ? 'Analyzing...' : 'AI Analysis'}
                </button>
                <button
                  onClick={() => setShowTestDataModal(true)}
                  disabled={isTestingFill}
                  className={cn(
                    "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors",
                    theme === 'dark'
                      ? 'bg-purple-700 hover:bg-purple-600 text-white disabled:bg-gray-700'
                      : 'bg-purple-600 hover:bg-purple-700 text-white disabled:bg-gray-300',
                    'disabled:cursor-not-allowed'
                  )}
                >
                  <FiTestTube className="h-4 w-4" />
                  Test Fill
                </button>
                <button
                  onClick={handleSaveMappings}
                  disabled={isSaving}
                  className={cn(
                    "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors",
                    theme === 'dark'
                      ? 'bg-blue-700 hover:bg-blue-600 text-white disabled:bg-gray-700'
                      : 'bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-300',
                    'disabled:cursor-not-allowed'
                  )}
                >
                  <FiSave className="h-4 w-4" />
                  {isSaving ? 'Saving...' : 'Save Mappings'}
                </button>
              </div>
            </div>
          </div>

          {/* Template Info */}
          <div className={cn("mb-6 p-6 rounded-xl", t.glass.card)}>
            <div className="flex items-center justify-between mb-4">
              <h2 className={cn("text-lg font-semibold", t.text.primary)}>
                Template Information
              </h2>
              {template.metadata?.ai_analyzed_at && (
                <div className="flex items-center gap-2 text-sm">
                  <FiZap className="h-4 w-4 text-yellow-500" />
                  <span className={cn(t.text.secondary)}>
                    AI Analyzed • {Math.round((template.metadata.ai_confidence || 0) * 100)}% confidence
                  </span>
                </div>
              )}
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <p className={cn("text-sm", t.text.secondary)}>Document Type</p>
                <p className={cn("font-medium", t.text.primary)}>
                  {template.document_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </p>
              </div>
              <div>
                <p className={cn("text-sm", t.text.secondary)}>Template Fields</p>
                <p className={cn("font-medium flex items-center gap-2", t.text.primary)}>
                  {template.template_fields ? template.template_fields.length : 0}
                  {template.metadata?.ai_field_count && (
                    <span className="text-xs text-purple-500">
                      (AI: {template.metadata.ai_field_count})
                    </span>
                  )}
                </p>
              </div>
              <div>
                <p className={cn("text-sm", t.text.secondary)}>Mapped Fields</p>
                <p className={cn("font-medium", t.text.primary)}>
                  {mappings.filter(m => m.data_source).length}
                </p>
              </div>
              <div>
                <p className={cn("text-sm", t.text.secondary)}>Last Updated</p>
                <p className={cn("font-medium", t.text.primary)}>
                  {new Date(template.updated_at).toLocaleDateString()}
                </p>
              </div>
            </div>
          </div>

          {/* Field Mappings */}
          <div className={cn("p-6 rounded-xl", t.glass.card)}>
            <div className="flex items-center justify-between mb-6">
              <h2 className={cn("text-lg font-semibold", t.text.primary)}>
                Field Mappings
              </h2>
              <div className="flex items-center gap-3">
                <AIMappingSuggestions
                  templateId={template.id}
                  onSuggestionsApplied={handleSuggestionsApplied}
                  theme={theme}
                />
                <button
                  onClick={handleAddMapping}
                  className={cn(
                    "flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors",
                    theme === 'dark'
                      ? 'bg-gray-700 hover:bg-gray-600 text-white'
                      : 'bg-gray-200 hover:bg-gray-300 text-gray-800'
                  )}
                >
                  <FiPlus className="h-4 w-4" />
                  Add Mapping
                </button>
              </div>
            </div>

            {template.template_fields && template.template_fields.length === 0 ? (
              <div className={cn(
                "text-center py-12 rounded-lg border-2 border-dashed",
                theme === 'dark' ? 'border-gray-700' : 'border-gray-300'
              )}>
                <FiFile className={cn("h-12 w-12 mx-auto mb-3", t.text.muted)} />
                <p className={cn("text-sm", t.text.secondary)}>
                  No form fields detected in this PDF template.
                </p>
                <p className={cn("text-xs mt-1", t.text.muted)}>
                  Make sure the PDF contains fillable form fields.
                </p>
              </div>
            ) : (
              <PDFFieldMapper
                mappings={mappings}
                dataSources={dataSources}
                onMappingChange={handleMappingChange}
                onRemoveMapping={handleRemoveMapping}
                transformFunctions={transformFunctions}
                fieldTypes={fieldTypes}
                theme={theme}
              />
            )}
          </div>
        </div>

        {/* Test Data Modal */}
        {showTestDataModal && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className={cn("max-w-2xl w-full rounded-xl p-6", t.glass.card)}>
              <h2 className={cn("text-xl font-bold mb-4", t.text.primary)}>
                Test Fill PDF
              </h2>

              <div className="space-y-4 max-h-[60vh] overflow-y-auto">
                {Object.entries(dataSources).map(([category, fields]) => (
                  <div key={category}>
                    <h3 className={cn("text-sm font-semibold mb-2 capitalize", t.text.secondary)}>
                      {category} Data
                    </h3>
                    <div className="grid grid-cols-2 gap-3">
                      {Object.entries(fields).map(([field, label]) => (
                        <div key={field}>
                          <label className={cn("block text-xs mb-1", t.text.muted)}>
                            {label}
                          </label>
                          <input
                            type="text"
                            value={testData[field] || ''}
                            onChange={(e) => setTestData({
                              ...testData,
                              [field]: e.target.value,
                            })}
                            className={cn(
                              "w-full px-2 py-1 text-sm rounded border",
                              theme === 'dark'
                                ? 'bg-gray-800 border-gray-700 text-white'
                                : 'bg-white border-gray-300 text-gray-900'
                            )}
                          />
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>

              <div className="flex gap-3 mt-6">
                <button
                  onClick={handleTestFill}
                  disabled={isTestingFill}
                  className={cn(
                    "flex-1 px-4 py-2 rounded-lg font-medium transition-colors",
                    theme === 'dark'
                      ? 'bg-purple-700 hover:bg-purple-600 text-white disabled:bg-gray-700'
                      : 'bg-purple-600 hover:bg-purple-700 text-white disabled:bg-gray-300',
                    'disabled:cursor-not-allowed'
                  )}
                >
                  {isTestingFill ? 'Generating...' : 'Generate Test PDF'}
                </button>
                <button
                  onClick={() => setShowTestDataModal(false)}
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
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}