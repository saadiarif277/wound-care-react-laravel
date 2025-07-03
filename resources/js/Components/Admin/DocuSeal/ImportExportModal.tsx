import React, { useState } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import axios from 'axios';
import {
  X, Download, Upload, FileJson, AlertTriangle,
  CheckCircle, FileText, RefreshCw
} from 'lucide-react';
import { MappingExportData } from '@/types/field-mapping';

interface ImportExportModalProps {
  show: boolean;
  onClose: () => void;
  templateId: string;
  templateName: string;
  onImportComplete: () => void;
}

export const ImportExportModal: React.FC<ImportExportModalProps> = ({
  show,
  onClose,
  templateId,
  templateName,
  onImportComplete
}) => {
  const [mode, setMode] = useState<'export' | 'import'>('export');
  const [exporting, setExporting] = useState(false);
  const [importing, setImporting] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importMode, setImportMode] = useState<'replace' | 'merge'>('merge');
  const [importPreview, setImportPreview] = useState<MappingExportData | null>(null);
  const [result, setResult] = useState<any>(null);

  const handleExport = async () => {
    setExporting(true);
    setResult(null);

    try {
      const response = await axios.get(
        `/api/v1/admin/docuseal/field-mappings/export/${templateId}`,
        { responseType: 'blob' }
      );

      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `mapping-export-${templateName.replace(/\s+/g, '-')}-${new Date().toISOString().split('T')[0]}.json`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      setResult({
        success: true,
        message: 'Mapping configuration exported successfully'
      });

    } catch (error: any) {
      setResult({
        success: false,
        message: error.response?.data?.message || 'Export failed'
      });
    } finally {
      setExporting(false);
    }
  };

  const handleFileSelect = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setImportFile(file);
    setImportPreview(null);
    setResult(null);

    // Try to parse and preview the file
    try {
      const text = await file.text();
      const data = JSON.parse(text) as MappingExportData;
      
      // Validate structure
      if (!data.mappings || !Array.isArray(data.mappings)) {
        throw new Error('Invalid mapping file format');
      }

      setImportPreview(data);
    } catch (error) {
      setResult({
        success: false,
        message: 'Invalid file format. Please select a valid mapping export file.'
      });
      setImportFile(null);
    }
  };

  const handleImport = async () => {
    if (!importFile) return;

    setImporting(true);
    setResult(null);

    const formData = new FormData();
    formData.append('file', importFile);
    formData.append('template_id', templateId);
    formData.append('mode', importMode);

    try {
      const response = await axios.post(
        '/api/v1/admin/docuseal/field-mappings/import',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        }
      );

      setResult({
        success: true,
        message: response.data.message || 'Import successful',
        importedCount: response.data.imported_count
      });

      // Refresh parent data
      setTimeout(() => {
        onImportComplete();
        handleClose();
      }, 2000);

    } catch (error: any) {
      setResult({
        success: false,
        message: error.response?.data?.message || 'Import failed'
      });
    } finally {
      setImporting(false);
    }
  };

  const handleClose = () => {
    setMode('export');
    setImportFile(null);
    setImportPreview(null);
    setImportMode('merge');
    setResult(null);
    onClose();
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
                <div className="bg-gradient-to-r from-green-50 to-blue-50 px-6 py-4 border-b border-gray-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <FileJson className="w-5 h-5 text-green-600" />
                      </div>
                      <div>
                        <Dialog.Title className="text-xl font-bold text-gray-900">
                          Import/Export Mappings
                        </Dialog.Title>
                        <p className="text-sm text-gray-600 mt-1">
                          {templateName}
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
                  {/* Mode Selection */}
                  <div className="flex gap-3 mb-6">
                    <button
                      onClick={() => setMode('export')}
                      className={`flex-1 p-4 rounded-lg border-2 transition-all ${
                        mode === 'export'
                          ? 'border-green-500 bg-green-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <Download className="w-6 h-6 text-green-600 mx-auto mb-2" />
                      <h3 className="font-semibold text-gray-900">Export</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        Download current mapping configuration
                      </p>
                    </button>

                    <button
                      onClick={() => setMode('import')}
                      className={`flex-1 p-4 rounded-lg border-2 transition-all ${
                        mode === 'import'
                          ? 'border-blue-500 bg-blue-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <Upload className="w-6 h-6 text-blue-600 mx-auto mb-2" />
                      <h3 className="font-semibold text-gray-900">Import</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        Load mapping configuration from file
                      </p>
                    </button>
                  </div>

                  {/* Export Mode */}
                  {mode === 'export' && (
                    <div className="space-y-4">
                      <div className="bg-gray-50 rounded-lg p-4">
                        <h3 className="font-medium text-gray-900 mb-3">Export Information</h3>
                        <div className="space-y-2 text-sm">
                          <p className="text-gray-600">
                            The export will include:
                          </p>
                          <ul className="list-disc list-inside space-y-1 text-gray-600 ml-2">
                            <li>All field mappings and their canonical assignments</li>
                            <li>Transformation rules for each field</li>
                            <li>Validation status and confidence scores</li>
                            <li>Template metadata and statistics</li>
                          </ul>
                        </div>
                      </div>

                      <div className="bg-blue-50 rounded-lg p-4">
                        <div className="flex items-start gap-3">
                          <FileText className="w-5 h-5 text-blue-600 mt-0.5" />
                          <div className="text-sm">
                            <p className="font-medium text-blue-900">File Format</p>
                            <p className="text-blue-700 mt-1">
                              The configuration will be exported as a JSON file that can be imported
                              into other templates or stored as a backup.
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Import Mode */}
                  {mode === 'import' && (
                    <div className="space-y-4">
                      {/* File Upload */}
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Select Mapping File
                        </label>
                        <div className="relative">
                          <input
                            type="file"
                            accept=".json"
                            onChange={handleFileSelect}
                            className="block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                          />
                        </div>
                      </div>

                      {/* Import Preview */}
                      {importPreview && (
                        <div className="bg-gray-50 rounded-lg p-4">
                          <h3 className="font-medium text-gray-900 mb-3">Import Preview</h3>
                          <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                              <span className="text-gray-600">From Template:</span>
                              <span className="font-medium text-gray-900">
                                {importPreview.template.name}
                              </span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Exported By:</span>
                              <span className="font-medium text-gray-900">
                                {importPreview.exported_by}
                              </span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Export Date:</span>
                              <span className="font-medium text-gray-900">
                                {new Date(importPreview.exported_at).toLocaleDateString()}
                              </span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Total Mappings:</span>
                              <span className="font-medium text-gray-900">
                                {importPreview.mappings.length}
                              </span>
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Import Mode Selection */}
                      {importFile && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            Import Mode
                          </label>
                          <div className="space-y-2">
                            <label className="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                              <input
                                type="radio"
                                value="merge"
                                checked={importMode === 'merge'}
                                onChange={(e) => setImportMode(e.target.value as any)}
                                className="mt-1"
                              />
                              <div>
                                <p className="font-medium text-gray-900">Merge</p>
                                <p className="text-sm text-gray-600">
                                  Add new mappings and update existing ones, keeping unmapped fields
                                </p>
                              </div>
                            </label>
                            <label className="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                              <input
                                type="radio"
                                value="replace"
                                checked={importMode === 'replace'}
                                onChange={(e) => setImportMode(e.target.value as any)}
                                className="mt-1"
                              />
                              <div>
                                <p className="font-medium text-gray-900">Replace</p>
                                <p className="text-sm text-gray-600">
                                  Remove all existing mappings and replace with imported configuration
                                </p>
                              </div>
                            </label>
                          </div>
                        </div>
                      )}

                      {importMode === 'replace' && (
                        <div className="bg-yellow-50 rounded-lg p-4">
                          <div className="flex items-start gap-3">
                            <AlertTriangle className="w-5 h-5 text-yellow-600 mt-0.5" />
                            <div className="text-sm">
                              <p className="font-medium text-yellow-900">Warning</p>
                              <p className="text-yellow-700 mt-1">
                                Replace mode will remove all existing mappings. This action cannot be undone.
                              </p>
                            </div>
                          </div>
                        </div>
                      )}
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
                          {result.importedCount && (
                            <p className="text-sm text-gray-600 mt-1">
                              {result.importedCount} mapping{result.importedCount !== 1 ? 's' : ''} imported
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
                    
                    {mode === 'export' ? (
                      <button
                        onClick={handleExport}
                        disabled={exporting}
                        className="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                      >
                        {exporting ? (
                          <>
                            <RefreshCw className="w-4 h-4 animate-spin" />
                            Exporting...
                          </>
                        ) : (
                          <>
                            <Download className="w-4 h-4" />
                            Export Configuration
                          </>
                        )}
                      </button>
                    ) : (
                      <button
                        onClick={handleImport}
                        disabled={!importFile || importing}
                        className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                      >
                        {importing ? (
                          <>
                            <RefreshCw className="w-4 h-4 animate-spin" />
                            Importing...
                          </>
                        ) : (
                          <>
                            <Upload className="w-4 h-4" />
                            Import Configuration
                          </>
                        )}
                      </button>
                    )}
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