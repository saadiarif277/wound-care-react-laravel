import React, { Component, ErrorInfo, ReactNode } from 'react';
import { AlertCircle, RefreshCw, Home, ArrowLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Props {
  children: ReactNode;
  stepName?: string;
  onReset?: () => void;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
  errorCount: number;
}

class QuickRequestErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
      errorCount: 0,
    };
  }

  static getDerivedStateFromError(error: Error): State {
    return {
      hasError: true,
      error,
      errorInfo: null,
      errorCount: 0,
    };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Quick Request Error:', error, errorInfo);

    // Log to error tracking service (e.g., Sentry)
    if ((window as any).Sentry) {
      (window as any).Sentry.captureException(error, {
        contexts: {
          react: {
            componentStack: errorInfo.componentStack,
          },
        },
        tags: {
          component: 'QuickRequestErrorBoundary',
          step: this.props.stepName || 'unknown',
        },
      });
    }

    // Log to server
    this.logErrorToServer(error, errorInfo);

    this.setState(prevState => ({
      errorInfo,
      errorCount: prevState.errorCount + 1,
    }));
  }

  logErrorToServer = async (error: Error, errorInfo: ErrorInfo) => {
    try {
      await fetch('/api/v1/errors/log', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          message: error.message,
          stack: error.stack,
          componentStack: errorInfo.componentStack,
          step: this.props.stepName,
          url: window.location.href,
          userAgent: navigator.userAgent,
          timestamp: new Date().toISOString(),
        }),
      });
    } catch (logError) {
      console.error('Failed to log error to server:', logError);
    }
  };

  handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });

    if (this.props.onReset) {
      this.props.onReset();
    }
  };

  handleReload = () => {
    window.location.reload();
  };

  handleGoBack = () => {
    window.history.back();
  };

  render() {
    if (this.state.hasError) {
      // Use custom fallback if provided
      if (this.props.fallback) {
        return this.props.fallback;
      }

      const { error, errorInfo, errorCount } = this.state;
      const isDevelopment = process.env.NODE_ENV === 'development';

      return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
          <div className="max-w-2xl w-full">
            <div className="bg-white rounded-2xl shadow-xl p-8">
              {/* Error Icon */}
              <div className="flex justify-center mb-6">
                <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center">
                  <AlertCircle className="w-10 h-10 text-red-600" />
                </div>
              </div>

              {/* Error Title */}
              <h1 className="text-2xl font-bold text-gray-900 text-center mb-4">
                Oops! Something went wrong
              </h1>

              {/* Error Description */}
              <p className="text-gray-600 text-center mb-8">
                We encountered an unexpected error while processing your request.
                {this.props.stepName && (
                  <span className="block mt-2">
                    Error occurred in: <strong>{this.props.stepName}</strong>
                  </span>
                )}
              </p>

              {/* Error Details (Development Only) */}
              {isDevelopment && error && (
                <div className="mb-8">
                  <details className="bg-gray-50 rounded-lg p-4">
                    <summary className="font-semibold text-gray-700 cursor-pointer mb-2">
                      Error Details (Development Only)
                    </summary>
                    <div className="space-y-2">
                      <div className="bg-red-50 p-3 rounded border border-red-200">
                        <p className="text-sm font-mono text-red-800">{error.message}</p>
                      </div>
                      {error.stack && (
                        <pre className="text-xs bg-gray-800 text-gray-100 p-3 rounded overflow-x-auto">
                          {error.stack}
                        </pre>
                      )}
                      {errorInfo?.componentStack && (
                        <details className="bg-gray-100 rounded p-3">
                          <summary className="text-sm font-semibold cursor-pointer">
                            Component Stack
                          </summary>
                          <pre className="text-xs mt-2 overflow-x-auto">
                            {errorInfo.componentStack}
                          </pre>
                        </details>
                      )}
                    </div>
                  </details>
                </div>
              )}

              {/* Error Count Warning */}
              {errorCount > 2 && (
                <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                  <p className="text-sm text-amber-800">
                    <strong>Multiple errors detected.</strong> If this continues, please contact support.
                  </p>
                </div>
              )}

              {/* Action Buttons */}
              <div className="flex flex-col sm:flex-row gap-3 justify-center">
                <button
                  onClick={this.handleReset}
                  className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Try Again
                </button>

                <button
                  onClick={this.handleGoBack}
                  className="inline-flex items-center justify-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors"
                >
                  <ArrowLeft className="w-4 h-4 mr-2" />
                  Go Back
                </button>

                <Link
                  href="/dashboard"
                  className="inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors"
                >
                  <Home className="w-4 h-4 mr-2" />
                  Dashboard
                </Link>
              </div>

              {/* Support Information */}
              <div className="mt-8 pt-6 border-t border-gray-200">
                <p className="text-sm text-gray-500 text-center">
                  If this problem persists, please contact support at{' '}
                  <a
                    href="mailto:support@mscwoundcare.com"
                    className="text-blue-600 hover:underline"
                  >
                    support@mscwoundcare.com
                  </a>
                  {' '}or call{' '}
                  <a
                    href="tel:1-800-MSC-WOUND"
                    className="text-blue-600 hover:underline"
                  >
                    1-800-MSC-WOUND
                  </a>
                </p>
                {error && (
                  <p className="text-xs text-gray-400 text-center mt-2">
                    Error ID: {btoa(error.message).slice(0, 10).toUpperCase()}
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

// Hook for using error boundary
export function useErrorHandler() {
  const [error, setError] = React.useState<Error | null>(null);

  React.useEffect(() => {
    if (error) {
      throw error;
    }
  }, [error]);

  const resetError = () => setError(null);
  const throwError = (error: Error) => setError(error);

  return { resetError, throwError };
}

// Wrapper component for individual steps
interface StepErrorBoundaryProps {
  children: ReactNode;
  stepName: string;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

export function StepErrorBoundary({ children, stepName, onError }: StepErrorBoundaryProps) {
  return (
    <QuickRequestErrorBoundary
      stepName={stepName}
      onReset={() => {
        // Optionally reset specific step state
        console.log(`Resetting ${stepName} step`);
      }}
    >
      {children}
    </QuickRequestErrorBoundary>
  );
}

// HOC for wrapping components with error boundary
export function withErrorBoundary<P extends object>(
  Component: React.ComponentType<P>,
  stepName?: string
) {
  return (props: P) => (
    <QuickRequestErrorBoundary stepName={stepName}>
      <Component {...props} />
    </QuickRequestErrorBoundary>
  );
}

export default QuickRequestErrorBoundary;