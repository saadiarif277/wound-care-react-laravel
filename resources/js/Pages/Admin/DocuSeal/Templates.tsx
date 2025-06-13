import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { apiGet, handleApiResponse } from '@/lib/api';
import { Dialog } from '@headlessui/react';

interface Template {
  id: string;
  template_name: string;
  document_type: string;
  manufacturer_id: string;
  is_active: boolean;
  is_default: boolean;
  field_mappings: Record<string, any>;
}

export default function Templates() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [showModal, setShowModal] = useState(false);

  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiGet('/api/v1/docuseal/templates');
      const data = await handleApiResponse<{ success: boolean; templates: Template[] }>(res);
      setTemplates(data.templates);
    } catch (e: any) {
      setError(e.message || 'Failed to load templates');
    } finally {
      setLoading(false);
    }
  };

  const syncTemplates = async () => {
    setSyncing(true);
    setError(null);
    try {
      const res = await apiGet('/api/v1/docuseal/templates/sync', { method: 'POST' });
      const data = await handleApiResponse<{ success: boolean; templates: Template[] }>(res);
      setTemplates(data.templates);
    } catch (e: any) {
      setError(e.message || 'Failed to sync templates');
    } finally {
      setSyncing(false);
    }
  };

  const openFieldModal = (tpl: Template) => {
    setSelectedTemplate(tpl);
    setShowModal(true);
  };

  const closeFieldModal = () => {
    setShowModal(false);
    setSelectedTemplate(null);
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
                          <td className="px-4 py-2 text-xs text-blue-600 underline cursor-pointer" onClick={() => openFieldModal(tpl)}>
                            {Object.keys(tpl.field_mappings || {}).length}
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
      <Dialog open={showModal} onClose={closeFieldModal} className="fixed z-50 inset-0 overflow-y-auto">
        <div className="flex items-center justify-center min-h-screen px-4">
          <Dialog.Overlay className="fixed inset-0 bg-black bg-opacity-30" />
          <div className="relative bg-white rounded-xl shadow-xl max-w-2xl w-full mx-auto p-6 z-10">
            <Dialog.Title className="text-lg font-bold mb-2">Field Mappings for {selectedTemplate?.template_name}</Dialog.Title>
            <button className="absolute top-3 right-3 text-gray-400 hover:text-gray-700" onClick={closeFieldModal}>&times;</button>
            {selectedTemplate && (
              <div className="overflow-x-auto mt-2">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">DocuSeal Field</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mapped Local Field</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-100">
                    {Object.entries(selectedTemplate.field_mappings || {}).map(([field, mapping]) => (
                      <tr key={field}>
                        <td className="px-3 py-2 font-mono text-xs text-gray-900">{field}</td>
                        <td className="px-3 py-2 font-mono text-xs text-blue-700">{mapping.local_field || <span className="text-gray-400">(unmapped)</span>}</td>
                        <td className="px-3 py-2 text-xs text-gray-600">{mapping.type}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </Dialog>
    </MainLayout>
  );
}
