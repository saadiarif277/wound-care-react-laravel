import React, { useState, useCallback } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import axios from 'axios';
import {
  Upload, Brain, FileText, AlertCircle,
  Sparkles, ArrowRight, Loader2, Download, Eye,
  Zap, Target, TrendingUp, X, Info, FileSearch
} from 'lucide-react';
import { useDropzone } from 'react-dropzone';

interface AITemplateAnalyzerProps {
  show: boolean;
  onClose: () => void;
  templateId?: string;
  onAnalysisComplete?: (analysis: any) => void;
}

interface DetectedField {
  name: string;
  display_name: string;
  type: string;
  required: boolean;
  confidence: number;
  suggestions: Array<{
    canonical_field_id: number;
    category: string;
    field_name: string;
    display_name: string;
    confidence: number;
    reason: string;
  }>;
}

export const AITemplateAnalyzer: React.FC<AITemplateAnalyzerProps> = ({
  show,
  onClose,
  templateId,
  onAnalysisComplete
}) => {
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [analysisResult, setAnalysisResult] = useState<any>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'upload' | 'results' | 'test'>('upload');
  const [testFile, setTestFile] = useState<File | null>(null);
  const [isTestingExtraction, setIsTestingExtraction] = useState(false);
  const [extractionResult, setExtractionResult] = useState<any>(null);

  const onDrop = useCallback((acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      setSelectedFile(acceptedFiles[0] || null);
      setError(null);
    }
  }, []);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'application/pdf': ['.pdf'],
      'image/*': ['.png', '.jpg', '.jpeg']
    },
    maxFiles: 1,
    maxSize: 10 * 1024 * 1024 // 10MB
  });
  
  const onTestDrop = useCallback((acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      setTestFile(acceptedFiles[0] || null);
      setError(null);
    }
  }, []);

  const { getRootProps: getTestRootProps, getInputProps: getTestInputProps, isDragActive: isTestDragActive } = useDropzone({
    onDrop: onTestDrop,
    accept: {
      'application/pdf': ['.pdf'],
      'image/*': ['.png', '.jpg', '.jpeg']
    },
    maxFiles: 1,
    maxSize: 10 * 1024 * 1024 // 10MB
  });

  const analyzeTemplate = async () => {
    if (!selectedFile) {
      setError('Please select a file to analyze');
      return;
    }

    setIsAnalyzing(true);
    setError(null);

    const formData = new FormData();
    formData.append('file', selectedFile);
    if (templateId) {
      formData.append('template_id', templateId);
    }

    try {
      const response = await axios.post('/api/v1/document-intelligence/analyze-template', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });

      setAnalysisResult(response.data);
      setActiveTab('results');
      
      
      if (onAnalysisComplete) {
        onAnalysisComplete(response.data);
      }
    } catch (err: any) {
      console.error('Template analysis error:', err.response?.data);
      let errorMessage = err.response?.data?.message || 'Failed to analyze template';
      
      // Show debug info if available
      if (err.response?.data?.debug) {
        console.error('Debug info:', err.response.data.debug);
        errorMessage += ` (${err.response.data.debug.error})`;
      }
      
      setError(errorMessage);
    } finally {
      setIsAnalyzing(false);
    }
  };

  const applyAllSuggestions = () => {
    if (!analysisResult) return;

    const mappings: any[] = [];
    
    analysisResult.detected_fields.forEach((field: DetectedField) => {
      if (field.suggestions.length > 0 && field.suggestions[0]?.confidence > 70) {
        mappings.push({
          field_name: field.name,
          canonical_field_id: field.suggestions[0]?.canonical_field_id,
          confidence: field.suggestions[0]?.confidence
        });
      }
    });

    if (onAnalysisComplete) {
      onAnalysisComplete({
        ...analysisResult,
        auto_mappings: mappings
      });
    }
  };

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 80) return 'text-green-600';
    if (confidence >= 60) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getConfidenceBadge = (confidence: number) => {
    if (confidence >= 80) return 'bg-green-100 text-green-800';
    if (confidence >= 60) return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
  };
  
  const testExtraction = async () => {
    if (!testFile) {
      setError('Please select a filled form to test');
      return;
    }

    setIsTestingExtraction(true);
    setError(null);

    const formData = new FormData();
    formData.append('file', testFile);
    if (templateId) {
      formData.append('template_id', templateId);
    }
    // Send expected_fields as array elements for Laravel validation
    if (analysisResult?.detected_fields) {
      analysisResult.detected_fields.forEach((field: any, index: number) => {
        formData.append(`expected_fields[${index}][name]`, field.name);
        formData.append(`expected_fields[${index}][type]`, field.type);
        formData.append(`expected_fields[${index}][required]`, field.required.toString());
      });
    }

    try {
      const response = await axios.post('/api/v1/document-intelligence/extract-form-data', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });

      setExtractionResult(response.data);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to extract data from form');
    } finally {
      setIsTestingExtraction(false);
    }
  };

  return (
    <Transition appear show={show} as={Fragment}>
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
              <Dialog.Panel className="w-full max-w-6xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-xl flex items-center justify-center">
                      <Brain className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <Dialog.Title className="text-2xl font-bold text-gray-900">
                        AI Template Analyzer
                      </Dialog.Title>
                      <p className="text-gray-600">
                        Use artificial intelligence to understand and map your templates
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={onClose}
                    className="text-gray-400 hover:text-gray-600"
                  >
                    <X className="w-6 h-6" />
                  </button>
                </div>

                {/* Tabs */}
                <div className="flex gap-4 mb-6 border-b border-gray-200">
                  <button
                    onClick={() => setActiveTab('upload')}
                    className={`pb-3 px-1 font-medium transition-colors ${
                      activeTab === 'upload'
                        ? 'text-purple-600 border-b-2 border-purple-600'
                        : 'text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    <Upload className="w-4 h-4 inline mr-2" />
                    Upload Template
                  </button>
                  <button
                    onClick={() => setActiveTab('results')}
                    disabled={!analysisResult}
                    className={`pb-3 px-1 font-medium transition-colors ${
                      activeTab === 'results'
                        ? 'text-purple-600 border-b-2 border-purple-600'
                        : 'text-gray-500 hover:text-gray-700 disabled:opacity-50'
                    }`}
                  >
                    <FileSearch className="w-4 h-4 inline mr-2" />
                    Analysis Results
                  </button>
                  <button
                    onClick={() => setActiveTab('test')}
                    disabled={!analysisResult}
                    className={`pb-3 px-1 font-medium transition-colors ${
                      activeTab === 'test'
                        ? 'text-purple-600 border-b-2 border-purple-600'
                        : 'text-gray-500 hover:text-gray-700 disabled:opacity-50'
                    }`}
                  >
                    <Zap className="w-4 h-4 inline mr-2" />
                    Test Extraction
                  </button>
                </div>

                {/* Content */}
                <div className="min-h-[500px]">
                  {/* Upload Tab */}
                  {activeTab === 'upload' && (
                    <div className="space-y-6">
                      {/* Benefits */}
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-blue-50 rounded-lg p-4">
                          <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                            <Sparkles className="w-5 h-5 text-blue-600" />
                          </div>
                          <h3 className="font-semibold text-gray-900 mb-1">Smart Field Detection</h3>
                          <p className="text-sm text-gray-600">
                            AI automatically identifies all fields in your template
                          </p>
                        </div>
                        <div className="bg-purple-50 rounded-lg p-4">
                          <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-3">
                            <Target className="w-5 h-5 text-purple-600" />
                          </div>
                          <h3 className="font-semibold text-gray-900 mb-1">Intelligent Mapping</h3>
                          <p className="text-sm text-gray-600">
                            Get AI-powered suggestions for field mappings
                          </p>
                        </div>
                        <div className="bg-green-50 rounded-lg p-4">
                          <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-3">
                            <TrendingUp className="w-5 h-5 text-green-600" />
                          </div>
                          <h3 className="font-semibold text-gray-900 mb-1">Confidence Scoring</h3>
                          <p className="text-sm text-gray-600">
                            Know how accurate each mapping suggestion is
                          </p>
                        </div>
                      </div>

                      {/* Upload Area */}
                      <div
                        {...getRootProps()}
                        className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors ${
                          isDragActive
                            ? 'border-purple-400 bg-purple-50'
                            : 'border-gray-300 hover:border-purple-400'
                        }`}
                      >
                        <input {...getInputProps()} />
                        <FileText className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        {selectedFile ? (
                          <div>
                            <p className="text-lg font-medium text-gray-900">
                              {selectedFile.name}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">
                              {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                            </p>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                setSelectedFile(null);
                              }}
                              className="text-sm text-purple-600 hover:text-purple-700 mt-2"
                            >
                              Choose different file
                            </button>
                          </div>
                        ) : (
                          <>
                            <p className="text-lg font-medium text-gray-900">
                              {isDragActive
                                ? 'Drop your template here'
                                : 'Drag & drop your template here'}
                            </p>
                            <p className="text-sm text-gray-500 mt-2">
                              or click to browse (PDF, PNG, JPG up to 10MB)
                            </p>
                          </>
                        )}
                      </div>

                      {/* Error Display */}
                      {error && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                          <AlertCircle className="w-5 h-5 text-red-600 mr-3 flex-shrink-0" />
                          <span className="text-red-800">{error}</span>
                        </div>
                      )}

                      {/* Analyze Button */}
                      <div className="flex justify-end">
                        <button
                          onClick={analyzeTemplate}
                          disabled={!selectedFile || isAnalyzing}
                          className="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:from-purple-700 hover:to-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                          {isAnalyzing ? (
                            <>
                              <Loader2 className="w-5 h-5 animate-spin" />
                              Analyzing Template...
                            </>
                          ) : (
                            <>
                              <Brain className="w-5 h-5" />
                              Analyze with AI
                            </>
                          )}
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Results Tab */}
                  {activeTab === 'results' && analysisResult && (
                    <div className="space-y-6">
                      {/* Summary Stats */}
                      <div className="grid grid-cols-4 gap-4">
                        <div className="bg-gray-50 rounded-lg p-4">
                          <div className="text-2xl font-bold text-gray-900">
                            {analysisResult.summary.total_fields}
                          </div>
                          <div className="text-sm text-gray-600">Total Fields</div>
                        </div>
                        <div className="bg-blue-50 rounded-lg p-4">
                          <div className="text-2xl font-bold text-blue-600">
                            {analysisResult.summary.required_fields}
                          </div>
                          <div className="text-sm text-gray-600">Required Fields</div>
                        </div>
                        <div className="bg-green-50 rounded-lg p-4">
                          <div className="text-2xl font-bold text-green-600">
                            {analysisResult.summary.high_confidence_fields}
                          </div>
                          <div className="text-sm text-gray-600">High Confidence</div>
                        </div>
                        <div className="bg-purple-50 rounded-lg p-4">
                          <div className="text-2xl font-bold text-purple-600">
                            {analysisResult.document_info.page_count}
                          </div>
                          <div className="text-sm text-gray-600">Pages</div>
                        </div>
                      </div>

                      {/* Action Buttons */}
                      <div className="flex gap-3">
                        <button
                          onClick={applyAllSuggestions}
                          className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center gap-2"
                        >
                          <Sparkles className="w-4 h-4" />
                          Apply High-Confidence Suggestions
                        </button>
                        <button
                          onClick={() => {/* Export logic */}}
                          className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
                        >
                          <Download className="w-4 h-4" />
                          Export Analysis
                        </button>
                      </div>

                      {/* Detected Fields */}
                      <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900">Detected Fields</h3>
                        <div className="space-y-3 max-h-96 overflow-y-auto">
                          {analysisResult.detected_fields.map((field: DetectedField, index: number) => (
                            <div key={index} className="bg-white border border-gray-200 rounded-lg p-4">
                              <div className="flex items-start justify-between mb-3">
                                <div>
                                  <h4 className="font-medium text-gray-900">
                                    {field.display_name}
                                  </h4>
                                  <div className="flex items-center gap-3 mt-1">
                                    <span className="text-sm text-gray-500">
                                      Type: {field.type}
                                    </span>
                                    {field.required && (
                                      <span className="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">
                                        Required
                                      </span>
                                    )}
                                    <span className={`text-sm ${getConfidenceColor(field.confidence)}`}>
                                      {field.confidence}% confident
                                    </span>
                                  </div>
                                </div>
                              </div>

                              {/* Top Suggestions */}
                              {field.suggestions.length > 0 && (
                                <div className="mt-3 space-y-2">
                                  <p className="text-sm font-medium text-gray-700">
                                    Suggested Mappings:
                                  </p>
                                  {field.suggestions.slice(0, 3).map((suggestion, sIdx) => (
                                    <div
                                      key={sIdx}
                                      className="flex items-center justify-between bg-gray-50 rounded-lg p-3"
                                    >
                                      <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                          <span className="font-medium text-gray-900">
                                            {suggestion.category} → {suggestion.display_name}
                                          </span>
                                          <span className={`text-xs px-2 py-1 rounded-full ${getConfidenceBadge(suggestion.confidence)}`}>
                                            {suggestion.confidence}%
                                          </span>
                                        </div>
                                        <p className="text-sm text-gray-600 mt-1">
                                          {suggestion.reason}
                                        </p>
                                      </div>
                                      <button
                                        onClick={() => {/* Apply mapping */}}
                                        className="ml-3 text-purple-600 hover:text-purple-700"
                                      >
                                        <ArrowRight className="w-5 h-5" />
                                      </button>
                                    </div>
                                  ))}
                                </div>
                              )}
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Test Tab */}
                  {activeTab === 'test' && analysisResult && (
                    <div className="space-y-6">
                      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-start">
                          <Info className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
                          <div>
                            <h3 className="font-medium text-blue-900">Test Your Mappings</h3>
                            <p className="text-sm text-blue-800 mt-1">
                              Upload a filled sample form to see how well the AI can extract data
                              using your current field mappings.
                            </p>
                          </div>
                        </div>
                      </div>

                      <div
                        {...getTestRootProps()}
                        className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors ${
                          isTestDragActive
                            ? 'border-purple-400 bg-purple-50'
                            : 'border-gray-300 hover:border-purple-400'
                        }`}
                      >
                        <input {...getTestInputProps()} />
                        {testFile ? (
                          <div>
                            <FileText className="w-10 h-10 text-purple-600 mx-auto mb-3" />
                            <p className="text-lg font-medium text-gray-900">
                              {testFile.name}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">
                              {(testFile.size / 1024 / 1024).toFixed(2)} MB
                            </p>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                setTestFile(null);
                                setExtractionResult(null);
                              }}
                              className="text-sm text-purple-600 hover:text-purple-700 mt-2"
                            >
                              Choose different file
                            </button>
                          </div>
                        ) : (
                          <>
                            <Eye className="w-10 h-10 text-gray-400 mx-auto mb-3" />
                            <p className="text-gray-700">
                              {isTestDragActive
                                ? 'Drop your filled form here'
                                : 'Drop a filled form here to test extraction'}
                            </p>
                          </>
                        )}
                      </div>
                      
                      {testFile && (
                        <div className="flex justify-center mt-4">
                          <button
                            onClick={testExtraction}
                            disabled={isTestingExtraction}
                            className="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                          >
                            {isTestingExtraction ? (
                              <>
                                <Loader2 className="w-5 h-5 animate-spin" />
                                Testing Extraction...
                              </>
                            ) : (
                              <>
                                <Zap className="w-5 h-5" />
                                Test Extraction
                              </>
                            )}
                          </button>
                        </div>
                      )}
                      
                      {extractionResult && (
                        <div className="mt-6 space-y-4">
                          <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 className="font-medium text-green-900 mb-2">
                              ✅ Extraction Complete!
                            </h4>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                              <div>
                                <span className="text-gray-600">Fields Extracted:</span>
                                <span className="ml-2 font-medium text-gray-900">
                                  {extractionResult.summary?.total_fields_extracted || 0}
                                </span>
                              </div>
                              <div>
                                <span className="text-gray-600">Coverage:</span>
                                <span className="ml-2 font-medium text-gray-900">
                                  {extractionResult.coverage || 0}%
                                </span>
                              </div>
                            </div>
                            {extractionResult.message && (
                              <p className="text-sm text-gray-600 mt-2">
                                {extractionResult.message}
                              </p>
                            )}
                          </div>
                          
                          <div className="space-y-2 max-h-64 overflow-y-auto">
                            {extractionResult.extracted_data?.map((field: any, index: number) => (
                              <div key={index} className="bg-white border border-gray-200 rounded-lg p-3">
                                <div className="flex items-start justify-between">
                                  <div className="flex-1">
                                    <h5 className="font-medium text-gray-900">
                                      {field.field_name}
                                    </h5>
                                    <p className="text-sm text-gray-700 mt-1">
                                      {field.value || '(empty)'}
                                    </p>
                                  </div>
                                  <span className={`text-xs px-2 py-1 rounded-full ${getConfidenceBadge(field.confidence)}`}>
                                    {field.confidence}%
                                  </span>
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};