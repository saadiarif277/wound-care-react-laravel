import React, { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

interface EcwConnectionProps {
  onConnectionChange?: (connected: boolean) => void;
}

interface ConnectionStatus {
  connected: boolean;
  environment?: string;
  error?: string;
  token_valid?: boolean;
  scope?: string;
}

const EcwConnection: React.FC<EcwConnectionProps> = ({ onConnectionChange }) => {
  const [status, setStatus] = useState<ConnectionStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [testing, setTesting] = useState(false);

  const checkConnectionStatus = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/ecw/status', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (response.ok) {
        const data = await response.json();
        setStatus(data);
        onConnectionChange?.(data.connected);
      } else {
        setStatus({ connected: false, error: 'Failed to check status' });
        onConnectionChange?.(false);
      }
    } catch (error) {
      console.error('Failed to check eCW connection status:', error);
      setStatus({ connected: false, error: 'Connection check failed' });
      onConnectionChange?.(false);
    } finally {
      setLoading(false);
    }
  };

  const testConnection = async () => {
    try {
      setTesting(true);
      const response = await fetch('/api/ecw/test', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (response.ok) {
        const data = await response.json();
        setStatus(data);
        onConnectionChange?.(data.connected);
      }
    } catch (error) {
      console.error('Failed to test eCW connection:', error);
    } finally {
      setTesting(false);
    }
  };

  const disconnect = async () => {
    if (!confirm('Are you sure you want to disconnect from eClinicalWorks?')) {
      return;
    }

    try {
      const response = await fetch('/api/ecw/disconnect', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (response.ok) {
        setStatus({ connected: false });
        onConnectionChange?.(false);
      }
    } catch (error) {
      console.error('Failed to disconnect from eCW:', error);
    }
  };

  useEffect(() => {
    checkConnectionStatus();
  }, []);

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center space-x-3">
          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
          <span className="text-gray-600">Checking eClinicalWorks connection...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-medium text-gray-900">eClinicalWorks Integration</h3>
        <div className="flex items-center space-x-2">
          <div className={`w-3 h-3 rounded-full ${status?.connected ? 'bg-green-400' : 'bg-red-400'}`}></div>
          <span className={`text-sm font-medium ${status?.connected ? 'text-green-600' : 'text-red-600'}`}>
            {status?.connected ? 'Connected' : 'Disconnected'}
          </span>
        </div>
      </div>

      {status?.connected ? (
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 rounded-md p-4">
            <div className="flex">
              <div className="ml-3">
                <h4 className="text-sm font-medium text-green-800">
                  Successfully connected to eClinicalWorks
                </h4>
                <div className="mt-2 text-sm text-green-700">
                  <ul className="list-disc list-inside space-y-1">
                    <li>Environment: <span className="font-medium">{status.environment}</span></li>
                    {status.scope && (
                      <li>Permissions: <span className="font-medium">{status.scope}</span></li>
                    )}
                    <li>Status: <span className="font-medium">Active</span></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div className="flex space-x-3">
            <button
              onClick={testConnection}
              disabled={testing}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {testing ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 mr-2"></div>
                  Testing...
                </>
              ) : (
                'Test Connection'
              )}
            </button>

            <button
              onClick={disconnect}
              className="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              Disconnect
            </button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <div className="flex">
              <div className="ml-3">
                <h4 className="text-sm font-medium text-yellow-800">
                  Connect to eClinicalWorks
                </h4>
                <div className="mt-2 text-sm text-yellow-700">
                  <p>Connect your account to access patient data from eClinicalWorks EHR system.</p>
                  {status?.error && (
                    <p className="mt-1 text-red-600">Error: {status.error}</p>
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
            <div className="ml-3">
              <h4 className="text-sm font-medium text-blue-800">What you'll get access to:</h4>
              <ul className="mt-2 text-sm text-blue-700 list-disc list-inside space-y-1">
                <li>Patient demographics and contact information</li>
                <li>Medical observations and vital signs</li>
                <li>Clinical documents and notes</li>
                <li>FHIR-compliant data exchange</li>
              </ul>
            </div>
          </div>

          <a
            href="/api/ecw/auth"
            className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <svg className="-ml-1 mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            Connect to eClinicalWorks
          </a>

          <div className="text-xs text-gray-500">
            <p>This will redirect you to eClinicalWorks for secure authentication.</p>
            <p>Your credentials are never stored by our application.</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default EcwConnection;
