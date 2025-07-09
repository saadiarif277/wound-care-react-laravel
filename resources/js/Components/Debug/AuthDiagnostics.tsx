import React, { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import api from '@/lib/api';

interface DiagnosticResult {
  check: string;
  status: 'pass' | 'fail' | 'warning';
  details: any;
  timestamp: string;
}

export default function AuthDiagnostics() {
  const [diagnostics, setDiagnostics] = useState<DiagnosticResult[]>([]);
  const [isRunning, setIsRunning] = useState(false);
  const { props } = usePage();

  const addDiagnostic = (check: string, status: 'pass' | 'fail' | 'warning', details: any) => {
    const diagnostic: DiagnosticResult = {
      check,
      status,
      details,
      timestamp: new Date().toISOString()
    };
    setDiagnostics(prev => [...prev, diagnostic]);
  };

  const runComprehensiveDiagnostics = async () => {
    setIsRunning(true);
    setDiagnostics([]);

    // 1. Check Inertia Props Auth State
    addDiagnostic('Inertia Auth Props', 
      props.auth?.user ? 'pass' : 'fail', 
      {
        hasAuth: !!props.auth,
        hasUser: !!props.auth?.user,
        userEmail: props.auth?.user?.email,
        userRole: props.userRole,
        permissions: props.permissions
      }
    );

    // 2. Check Cookies
    const cookies = document.cookie;
    const hasSessionCookie = cookies.includes('laravel_session');
    const hasXsrfCookie = cookies.includes('XSRF-TOKEN');
    
    addDiagnostic('Browser Cookies',
      hasSessionCookie && hasXsrfCookie ? 'pass' : 'fail',
      {
        laravel_session: hasSessionCookie,
        xsrf_token: hasXsrfCookie,
        allCookies: cookies.split(';').map(c => c.trim().split('=')[0])
      }
    );

    // 3. Check CSRF Meta Token
    const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    addDiagnostic('CSRF Meta Token',
      csrfMeta ? 'pass' : 'fail',
      { token: csrfMeta ? 'present' : 'missing', value: csrfMeta?.substring(0, 10) + '...' }
    );

    // 4. Test /sanctum/csrf-cookie endpoint
    try {
      const csrfResponse = await fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      
      addDiagnostic('Sanctum CSRF Cookie',
        csrfResponse.ok ? 'pass' : 'fail',
        {
          status: csrfResponse.status,
          statusText: csrfResponse.statusText,
          headers: Object.fromEntries(csrfResponse.headers.entries())
        }
      );
    } catch (error: any) {
      addDiagnostic('Sanctum CSRF Cookie', 'fail', {
        error: error.message,
        type: error.constructor.name
      });
    }

    // 5. Test /api/user endpoint (Sanctum auth check)
    try {
      const userResponse = await fetch('/api/user', {
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      
      if (userResponse.ok) {
        const userData = await userResponse.json();
        addDiagnostic('/api/user endpoint', 'pass', {
          status: userResponse.status,
          user: userData?.email,
          id: userData?.id
        });
      } else {
        addDiagnostic('/api/user endpoint', 'fail', {
          status: userResponse.status,
          statusText: userResponse.statusText
        });
      }
    } catch (error: any) {
      addDiagnostic('/api/user endpoint', 'fail', {
        error: error.message,
        type: error.constructor.name
      });
    }

    // 6. Test the specific failing endpoint
    try {
      const permissionsResponse = await api.get('/api/quick-request/user-permissions');
      addDiagnostic('/api/quick-request/user-permissions', 'pass', {
        status: 'success',
        data: permissionsResponse.data
      });
    } catch (error: any) {
      addDiagnostic('/api/quick-request/user-permissions', 'fail', {
        status: error.response?.status,
        statusText: error.response?.statusText,
        message: error.message,
        config: {
          url: error.config?.url,
          method: error.config?.method,
          headers: error.config?.headers
        }
      });
    }

    // 7. Check Axios Configuration
    addDiagnostic('Axios Configuration', 'pass', {
      baseURL: api.defaults.baseURL,
      withCredentials: api.defaults.withCredentials,
      xsrfCookieName: api.defaults.xsrfCookieName,
      xsrfHeaderName: api.defaults.xsrfHeaderName,
      commonHeaders: api.defaults.headers.common
    });

    // 8. Test Laravel session info endpoint
    try {
      const sessionResponse = await fetch('/test-csrf', {
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      
      if (sessionResponse.ok) {
        const sessionData = await sessionResponse.json();
        addDiagnostic('Laravel Session Info', 'pass', sessionData);
      } else {
        addDiagnostic('Laravel Session Info', 'warning', {
          status: sessionResponse.status,
          note: 'Test endpoint not available'
        });
      }
    } catch (error: any) {
      addDiagnostic('Laravel Session Info', 'warning', {
        error: error.message,
        note: 'Test endpoint not available'
      });
    }

    setIsRunning(false);
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pass': return 'text-green-600 bg-green-50';
      case 'fail': return 'text-red-600 bg-red-50';
      case 'warning': return 'text-yellow-600 bg-yellow-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pass': return '✅';
      case 'fail': return '❌';
      case 'warning': return '⚠️';
      default: return '❓';
    }
  };

  return (
    <div className="fixed top-4 right-4 max-w-md bg-white shadow-lg rounded-lg border p-4 max-h-96 overflow-y-auto z-50">
      <div className="flex justify-between items-center mb-4">
        <h3 className="font-bold text-lg">🔍 Auth Diagnostics</h3>
        <button 
          onClick={runComprehensiveDiagnostics}
          disabled={isRunning}
          className="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50"
        >
          {isRunning ? 'Running...' : 'Run Diagnostics'}
        </button>
      </div>
      
      <div className="space-y-2">
        {diagnostics.map((diagnostic, index) => (
          <div key={index} className={`p-2 rounded text-xs ${getStatusColor(diagnostic.status)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="font-medium">
                {getStatusIcon(diagnostic.status)} {diagnostic.check}
              </span>
              <span className="text-xs opacity-75">
                {new Date(diagnostic.timestamp).toLocaleTimeString()}
              </span>
            </div>
            <pre className="text-xs overflow-x-auto">
              {JSON.stringify(diagnostic.details, null, 2)}
            </pre>
          </div>
        ))}
      </div>
      
      {diagnostics.length === 0 && !isRunning && (
        <p className="text-gray-500 text-sm text-center">
          Click "Run Diagnostics" to check authentication state
        </p>
      )}
    </div>
  );
} 