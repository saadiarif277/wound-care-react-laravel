import React, { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { AlertTriangle, RefreshCw, Home, ArrowLeft } from 'lucide-react';

interface Props {
  status: number;
  message?: string;
  errors?: Record<string, string[]>;
  requires_refresh?: boolean;
}

const Error: React.FC<Props> = ({ status, message, errors, requires_refresh = false }) => {
  useEffect(() => {
    // If this is a CSRF error, automatically refresh the page after a delay
    if (requires_refresh) {
      const timer = setTimeout(() => {
        window.location.reload();
      }, 3000);

      return () => clearTimeout(timer);
    }
  }, [requires_refresh]);

  const getStatusInfo = () => {
    switch (status) {
      case 419:
        return {
          title: 'Session Expired',
          description: 'Your session has expired. Please refresh the page to continue.',
          icon: RefreshCw,
          color: 'text-yellow-600',
          bgColor: 'bg-yellow-50',
          borderColor: 'border-yellow-200',
        };
      case 401:
        return {
          title: 'Unauthorized',
          description: 'Please log in to access this page.',
          icon: AlertTriangle,
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
        };
      case 403:
        return {
          title: 'Access Denied',
          description: 'You do not have permission to access this resource.',
          icon: AlertTriangle,
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
        };
      case 404:
        return {
          title: 'Page Not Found',
          description: 'The page you are looking for could not be found.',
          icon: AlertTriangle,
          color: 'text-gray-600',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
        };
      case 422:
        return {
          title: 'Validation Error',
          description: 'Please check your input and try again.',
          icon: AlertTriangle,
          color: 'text-orange-600',
          bgColor: 'bg-orange-50',
          borderColor: 'border-orange-200',
        };
      case 500:
        return {
          title: 'Server Error',
          description: 'Something went wrong on our end. Please try again later.',
          icon: AlertTriangle,
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
        };
      default:
        return {
          title: 'Error',
          description: message || 'An unexpected error occurred.',
          icon: AlertTriangle,
          color: 'text-gray-600',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
        };
    }
  };

  const statusInfo = getStatusInfo();
  const IconComponent = statusInfo.icon;

  const handleRefresh = () => {
    window.location.reload();
  };

  const handleGoHome = () => {
    router.visit('/');
  };

  const handleGoBack = () => {
    window.history.back();
  };

  return (
    <MainLayout>
      <Head title={`Error ${status}`} />

      <div className="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full space-y-8">
          <div className={`${statusInfo.bgColor} ${statusInfo.borderColor} border rounded-lg p-6 text-center`}>
            <div className="flex justify-center">
              <IconComponent className={`h-12 w-12 ${statusInfo.color}`} />
            </div>

            <h1 className="mt-4 text-xl font-semibold text-gray-900">
              {statusInfo.title}
            </h1>

            <p className="mt-2 text-sm text-gray-600">
              {statusInfo.description}
            </p>

            {status === 419 && requires_refresh && (
              <div className="mt-4 p-3 bg-yellow-100 rounded-md">
                <p className="text-sm text-yellow-800">
                  Auto-refreshing in 3 seconds...
                </p>
              </div>
            )}

            {errors && Object.keys(errors).length > 0 && (
              <div className="mt-4 text-left">
                <h3 className="text-sm font-medium text-gray-900 mb-2">Validation Errors:</h3>
                <ul className="text-sm text-red-600 space-y-1">
                  {Object.entries(errors).map(([field, fieldErrors]) => (
                    <li key={field}>
                      {field}: {fieldErrors.join(', ')}
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="mt-6 flex flex-col space-y-3">
              {status === 419 && (
                <button
                  onClick={handleRefresh}
                  className="w-full flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Refresh Page
                </button>
              )}

              <button
                onClick={handleGoBack}
                className="w-full flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <ArrowLeft className="h-4 w-4 mr-2" />
                Go Back
              </button>

              <button
                onClick={handleGoHome}
                className="w-full flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <Home className="h-4 w-4 mr-2" />
                Go Home
              </button>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default Error;
