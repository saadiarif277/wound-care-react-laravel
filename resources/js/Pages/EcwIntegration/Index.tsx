import React, { useState, useEffect } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { FiActivity, FiDatabase, FiLink, FiUser, FiCheckCircle, FiAlertTriangle, FiSettings } from 'react-icons/fi';

interface EcwIntegrationPageProps {
  title?: string;
}

const EcwIntegrationPage: React.FC<EcwIntegrationPageProps> = ({ title = 'eClinicalWorks Integration' }) => {
  const [connectionStatus, setConnectionStatus] = useState<{
    connected: boolean;
    environment?: string;
    scope?: string;
    error?: string;
  }>({ connected: false });

  const handleConnectionChange = (connected: boolean) => {
    setConnectionStatus(prev => ({ ...prev, connected }));
  };

  return (
    <MainLayout title={title}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Header */}
        <div className="mb-8">
          <div className="flex items-center space-x-3">
            <div className="flex-shrink-0">
              <FiLink className="h-8 w-8 text-blue-600" />
            </div>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">eClinicalWorks Integration</h1>
              <p className="mt-2 text-gray-600">
                Connect to eClinicalWorks EHR system to streamline patient data and clinical workflows
              </p>
            </div>
          </div>
        </div>

        {/* Connection Management */}
        <div className="mb-8">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <div className="text-center">
              <FiLink className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-semibold text-gray-900 mb-2">eClinicalWorks Connection</h3>
              <p className="text-gray-600 mb-4">Connect to your eClinicalWorks system to enable integration features.</p>
              <button
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                onClick={() => setConnectionStatus(prev => ({ ...prev, connected: !prev.connected }))}
              >
                {connectionStatus.connected ? 'Disconnect' : 'Connect to eClinicalWorks'}
              </button>
            </div>
          </div>
        </div>

        {/* Integration Features Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          {/* Patient Data Integration */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <FiUser className="h-6 w-6 text-blue-600" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Patient Demographics</h3>
                <p className="text-gray-600 mb-4">
                  Automatically pull patient demographics and basic information when creating new orders.
                </p>
                <div className="space-y-2">
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Patient name and date of birth</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Insurance member ID and information</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Contact details and demographics</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Problem List Integration */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <FiDatabase className="h-6 w-6 text-green-600" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Clinical Problem List</h3>
                <p className="text-gray-600 mb-4">
                  Access patient's active problem list with ICD-10 codes to inform clinical decisions.
                </p>
                <div className="space-y-2">
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Active conditions and diagnoses</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">ICD-10 codes and descriptions</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Clinical status and verification</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Order Summary Push */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <FiActivity className="h-6 w-6 text-purple-600" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Order Summary Documentation</h3>
                <p className="text-gray-600 mb-4">
                  Automatically document order summaries back to eCW as clinical notes for continuity of care.
                </p>
                <div className="space-y-2">
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Product selection and quantities</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Clinical assessment notes</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Order date and provider information</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Security & Compliance */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <FiSettings className="h-6 w-6 text-amber-600" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Security & Compliance</h3>
                <p className="text-gray-600 mb-4">
                  Enterprise-grade security with full HIPAA compliance and audit trails.
                </p>
                <div className="space-y-2">
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">OAuth2 authentication with eCW</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">Encrypted token storage</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiCheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">HIPAA-compliant audit logging</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Integration Status */}
        {connectionStatus.connected && (
          <div className="mt-8">
            <div className="bg-green-50 border border-green-200 rounded-lg p-6">
              <div className="flex items-start space-x-4">
                <FiCheckCircle className="h-6 w-6 text-green-600 mt-0.5" />
                <div>
                  <h3 className="text-lg font-semibold text-green-900">Integration Active</h3>
                  <p className="text-green-800 mt-1">
                    Your eClinicalWorks integration is active and ready to use. You can now access patient data during the order creation process.
                  </p>
                  {connectionStatus.environment && (
                    <p className="text-sm text-green-700 mt-2">
                      Environment: <span className="font-medium">{connectionStatus.environment}</span>
                    </p>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Getting Started */}
        {!connectionStatus.connected && (
          <div className="mt-8">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <div className="flex items-start space-x-4">
                <FiAlertTriangle className="h-6 w-6 text-blue-600 mt-0.5" />
                <div>
                  <h3 className="text-lg font-semibold text-blue-900">Getting Started</h3>
                  <div className="text-blue-800 mt-2 space-y-2">
                    <p>To use the eClinicalWorks integration:</p>
                    <ol className="list-decimal list-inside space-y-1 ml-4">
                      <li>Click "Connect to eClinicalWorks" above</li>
                      <li>Authorize the connection in your eCW system</li>
                      <li>Start using integrated patient data in your orders</li>
                    </ol>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Help & Documentation */}
        <div className="mt-8">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <h4 className="font-medium text-gray-900 mb-2">Documentation</h4>
                <p className="text-sm text-gray-600 mb-2">
                  View our comprehensive integration guide for setup and troubleshooting.
                </p>
                <a
                  href="/docs/ecw-integration"
                  className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                >
                  View Documentation →
                </a>
              </div>
              <div>
                <h4 className="font-medium text-gray-900 mb-2">Support</h4>
                <p className="text-sm text-gray-600 mb-2">
                  Contact our support team for assistance with eCW integration issues.
                </p>
                <a
                  href="/support"
                  className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                >
                  Contact Support →
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default EcwIntegrationPage;
