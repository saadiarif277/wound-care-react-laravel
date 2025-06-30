import React from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import {
  X, AlertTriangle, CheckCircle, Info, AlertCircle,
  FileText, Target, Shield, TrendingUp
} from 'lucide-react';
import { ValidationResult } from '@/types/field-mapping';

interface ValidationReportModalProps {
  show: boolean;
  onClose: () => void;
  validationResult: ValidationResult | null;
  templateName: string;
}

export const ValidationReportModal: React.FC<ValidationReportModalProps> = ({
  show,
  onClose,
  validationResult,
  templateName
}) => {
  if (!validationResult) return null;

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'valid':
        return <CheckCircle className="w-5 h-5 text-green-600" />;
      case 'warning':
        return <AlertTriangle className="w-5 h-5 text-yellow-600" />;
      case 'error':
        return <AlertCircle className="w-5 h-5 text-red-600" />;
      case 'unmapped':
        return <Info className="w-5 h-5 text-gray-600" />;
      default:
        return null;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'valid':
        return 'bg-green-50 border-green-200 text-green-900';
      case 'warning':
        return 'bg-yellow-50 border-yellow-200 text-yellow-900';
      case 'error':
        return 'bg-red-50 border-red-200 text-red-900';
      case 'unmapped':
        return 'bg-gray-50 border-gray-200 text-gray-900';
      default:
        return 'bg-gray-50 border-gray-200 text-gray-900';
    }
  };

  const overallStatus = validationResult.is_complete ? 'complete' : 
    validationResult.summary.errors > 0 ? 'has-errors' :
    validationResult.summary.warnings > 0 ? 'has-warnings' : 'incomplete';

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
              <Dialog.Panel className="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all">
                {/* Header */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <Shield className="w-5 h-5 text-blue-600" />
                      </div>
                      <div>
                        <Dialog.Title className="text-xl font-bold text-gray-900">
                          Validation Report
                        </Dialog.Title>
                        <p className="text-sm text-gray-600 mt-1">
                          {templateName}
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={onClose}
                      className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <X className="w-6 h-6" />
                    </button>
                  </div>
                </div>

                {/* Summary Section */}
                <div className="p-6 border-b border-gray-200">
                  <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">Total Fields</p>
                      <p className="text-2xl font-bold text-gray-900">
                        {validationResult.summary.total_fields}
                      </p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">Valid</p>
                      <p className="text-2xl font-bold text-green-600">
                        {validationResult.summary.valid}
                      </p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">Warnings</p>
                      <p className="text-2xl font-bold text-yellow-600">
                        {validationResult.summary.warnings}
                      </p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">Errors</p>
                      <p className="text-2xl font-bold text-red-600">
                        {validationResult.summary.errors}
                      </p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">Unmapped</p>
                      <p className="text-2xl font-bold text-gray-600">
                        {validationResult.summary.unmapped}
                      </p>
                    </div>
                  </div>

                  {/* Coverage Progress */}
                  <div className="mt-6">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-gray-700">Overall Coverage</span>
                      <span className="text-sm font-bold text-gray-900">
                        {validationResult.coverage_percentage}%
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div
                        className={`h-3 rounded-full transition-all ${
                          validationResult.coverage_percentage >= 80 ? 'bg-green-600' :
                          validationResult.coverage_percentage >= 60 ? 'bg-yellow-600' :
                          'bg-red-600'
                        }`}
                        style={{ width: `${validationResult.coverage_percentage}%` }}
                      />
                    </div>
                  </div>

                  {/* Overall Status */}
                  <div className={`mt-6 p-4 rounded-lg border ${
                    overallStatus === 'complete' ? 'bg-green-50 border-green-200' :
                    overallStatus === 'has-errors' ? 'bg-red-50 border-red-200' :
                    overallStatus === 'has-warnings' ? 'bg-yellow-50 border-yellow-200' :
                    'bg-gray-50 border-gray-200'
                  }`}>
                    <div className="flex items-center gap-3">
                      {overallStatus === 'complete' ? (
                        <>
                          <CheckCircle className="w-6 h-6 text-green-600" />
                          <div>
                            <p className="font-semibold text-green-900">Validation Complete</p>
                            <p className="text-sm text-green-700 mt-1">
                              All required fields are mapped and validated successfully.
                            </p>
                          </div>
                        </>
                      ) : overallStatus === 'has-errors' ? (
                        <>
                          <AlertCircle className="w-6 h-6 text-red-600" />
                          <div>
                            <p className="font-semibold text-red-900">Validation Failed</p>
                            <p className="text-sm text-red-700 mt-1">
                              There are {validationResult.summary.errors} error{validationResult.summary.errors !== 1 ? 's' : ''} that need to be resolved.
                            </p>
                          </div>
                        </>
                      ) : overallStatus === 'has-warnings' ? (
                        <>
                          <AlertTriangle className="w-6 h-6 text-yellow-600" />
                          <div>
                            <p className="font-semibold text-yellow-900">Validation Has Warnings</p>
                            <p className="text-sm text-yellow-700 mt-1">
                              There are {validationResult.summary.warnings} warning{validationResult.summary.warnings !== 1 ? 's' : ''} to review.
                            </p>
                          </div>
                        </>
                      ) : (
                        <>
                          <Info className="w-6 h-6 text-gray-600" />
                          <div>
                            <p className="font-semibold text-gray-900">Validation Incomplete</p>
                            <p className="text-sm text-gray-700 mt-1">
                              {validationResult.summary.unmapped} field{validationResult.summary.unmapped !== 1 ? 's are' : ' is'} not mapped.
                            </p>
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                </div>

                {/* Missing Required Fields */}
                {validationResult.missing_required_fields.length > 0 && (
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                      <Target className="w-5 h-5 text-red-600" />
                      Missing Required Fields
                    </h3>
                    <div className="space-y-2">
                      {validationResult.missing_required_fields.map((field, index) => (
                        <div key={index} className="flex items-start gap-3 p-3 bg-red-50 rounded-lg">
                          <AlertCircle className="w-5 h-5 text-red-600 mt-0.5" />
                          <div className="flex-1">
                            <p className="font-medium text-red-900">
                              {field.category} → {field.field}
                            </p>
                            {field.description && (
                              <p className="text-sm text-red-700 mt-1">{field.description}</p>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Field Validation Details */}
                <div className="p-6 max-h-96 overflow-y-auto">
                  <h3 className="font-semibold text-gray-900 mb-4">Field Validation Details</h3>
                  <div className="space-y-3">
                    {Object.entries(validationResult.validation_results)
                      .filter(([_, result]) => result.status !== 'valid')
                      .map(([fieldName, result]) => (
                        <div
                          key={fieldName}
                          className={`p-4 rounded-lg border ${getStatusColor(result.status)}`}
                        >
                          <div className="flex items-start gap-3">
                            {getStatusIcon(result.status)}
                            <div className="flex-1">
                              <p className="font-medium">{fieldName}</p>
                              <div className="mt-2 space-y-1">
                                {result.messages.map((message, index) => (
                                  <p key={index} className="text-sm">
                                    • {message}
                                  </p>
                                ))}
                              </div>
                            </div>
                          </div>
                        </div>
                      ))}
                  </div>
                </div>

                {/* Footer */}
                <div className="bg-gray-50 px-6 py-4 border-t border-gray-200">
                  <div className="flex items-center justify-end">
                    <button
                      onClick={onClose}
                      className="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                    >
                      Close
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