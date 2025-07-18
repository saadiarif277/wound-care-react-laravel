import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { UserWithRole } from '@/types/roles';

interface SuperAdminDashboardProps {
  user?: UserWithRole;
  dashboardData?: {
    system_metrics?: {
      uptime: number;
      api_response_time: number;
      active_users: number;
      database_performance: number;
      security_incidents: number;
      integration_health: number;
      error_rate: number;
      storage_utilization: number;
    };
    security_alerts?: Array<{
      id: string;
      type: string;
      severity: string;
      description: string;
      timestamp: string;
      status: string;
      affected_systems: string[];
    }>;
    system_health_components?: Array<{
      id: string;
      component: string;
      status: string;
      response_time: number;
      uptime: number;
      last_checked: string;
      details: string;
    }>;
    error_logs?: Array<{
      id: string;
      level: string;
      message: string;
      component: string;
      timestamp: string;
      count: number;
      resolved: boolean;
    }>;
    data_integrity_checks?: Array<{
      id: string;
      check: string;
      status: string;
      last_run: string;
      duration: string;
      records_checked: number;
      inconsistencies: number;
    }>;
  };
}

export default function SuperAdminDashboard({ user, dashboardData }: SuperAdminDashboardProps) {
  const systemMetrics = dashboardData?.system_metrics || {
    uptime: 0,
    api_response_time: 0,
    active_users: 0,
    database_performance: 0,
    security_incidents: 0,
    integration_health: 0,
    error_rate: 0,
    storage_utilization: 0,
  };

  const securityAlerts = dashboardData?.security_alerts || [];
  const systemHealthComponents = dashboardData?.system_health_components || [];
  const errorLogs = dashboardData?.error_logs || [];
  const dataIntegrityChecks = dashboardData?.data_integrity_checks || [];

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'healthy':
      case 'passed':
        return 'text-green-600 bg-green-100';
      case 'warning':
        return 'text-yellow-600 bg-yellow-100';
      case 'error':
      case 'critical':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'low':
        return 'text-blue-600 bg-blue-100';
      case 'medium':
        return 'text-yellow-600 bg-yellow-100';
      case 'high':
        return 'text-red-600 bg-red-100';
      case 'critical':
        return 'text-red-700 bg-red-200';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  return (
    <MainLayout>
      <Head title="Super Admin Dashboard" />

      <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">System Operations Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Monitor system health, security, and technical infrastructure. Manage critical operations and ensure platform integrity.
        </p>
      </div>

      {/* System Health Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">System Uptime</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">{systemMetrics.uptime}%</p>
          <p className="text-xs text-gray-600 mt-2">Last 30 days</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">API Response Time</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{systemMetrics.api_response_time}ms</p>
          <p className="text-xs text-gray-600 mt-2">Average response</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Users</h3>
          <p className="text-3xl font-bold text-indigo-600 mt-2">{systemMetrics.active_users}</p>
          <p className="text-xs text-gray-600 mt-2">Currently online</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Security Status</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">{systemMetrics.security_incidents}</p>
          <p className="text-xs text-gray-600 mt-2">Active incidents</p>
        </div>
      </div>

      {/* Security Monitoring */}
      {securityAlerts.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-red-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Security Monitoring</h2>
                  <p className="text-sm text-gray-600 mt-1">Active security alerts and incidents</p>
                </div>
              </div>
              <Link
                href="/superadmin/security"
                className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
              >
                Security Center
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {securityAlerts.map((alert) => (
              <div key={alert.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{alert.type}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getSeverityColor(alert.severity)}`}>
                        {alert.severity} severity
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        alert.status === 'resolved' ? 'bg-green-100 text-green-800' :
                        alert.status === 'investigating' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {alert.status}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{alert.description}</p>
                    <p className="text-xs text-gray-500 mt-2">
                      {alert.timestamp} | Affected: {alert.affected_systems.join(', ')}
                    </p>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                    Investigate
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* System Health Components */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-blue-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">System Health Monitor</h2>
                <p className="text-sm text-gray-600 mt-1">Real-time status of all system components</p>
              </div>
            </div>
            <Link
              href="/superadmin/system-health"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Detailed View
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {systemHealthComponents.length > 0 ? (
            systemHealthComponents.map((component) => (
              <div key={component.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{component.component}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(component.status)}`}>
                        {component.status}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Response Time</p>
                        <p className="text-sm font-medium">{component.response_time}ms</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Uptime</p>
                        <p className="text-sm font-medium">{component.uptime}%</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Last Checked</p>
                        <p className="text-sm font-medium">{component.last_checked}</p>
                      </div>
                    </div>
                    <p className="text-xs text-gray-600 mt-2">{component.details}</p>
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No system health data available
            </div>
          )}
        </div>
      </div>

      {/* Error & Issue Tracking */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-yellow-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Error & Issue Tracking</h2>
                <p className="text-sm text-gray-600 mt-1">Recent system errors and performance issues</p>
              </div>
            </div>
            <Link
              href="/superadmin/error-logs"
              className="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors"
            >
              View All Logs
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {errorLogs.length > 0 ? (
            errorLogs.map((error) => (
              <div key={error.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{error.component}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        error.level === 'error' ? 'bg-red-100 text-red-800' :
                        error.level === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {error.level}
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        error.resolved ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                      }`}>
                        {error.resolved ? 'resolved' : 'active'}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{error.message}</p>
                    <p className="text-xs text-gray-500 mt-2">
                      {error.timestamp} | Occurrences: {error.count}
                    </p>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                    Investigate
                  </button>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No recent errors to display
            </div>
          )}
        </div>
      </div>

      {/* Data Integrity Monitor */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Data Integrity Monitor</h2>
                <p className="text-sm text-gray-600 mt-1">Automated data validation and consistency checks</p>
              </div>
            </div>
            <button className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
              Run Checks
            </button>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {dataIntegrityChecks.length > 0 ? (
            dataIntegrityChecks.map((check) => (
              <div key={check.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{check.check}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(check.status)}`}>
                        {check.status}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-4 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Last Run</p>
                        <p className="text-sm font-medium">{check.last_run}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Duration</p>
                        <p className="text-sm font-medium">{check.duration}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Records Checked</p>
                        <p className="text-sm font-medium">{check.records_checked.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Issues Found</p>
                        <p className={`text-sm font-medium ${check.inconsistencies > 0 ? 'text-red-600' : 'text-green-600'}`}>
                          {check.inconsistencies}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No data integrity checks available
            </div>
          )}
        </div>
      </div>

      {/* Emergency Controls */}
      <div className="bg-white rounded-xl shadow-lg border border-red-200 overflow-hidden">
        <div className="p-6 border-b border-red-200 bg-red-50">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div className="ml-3">
              <h2 className="text-xl font-semibold text-gray-900">Emergency Controls</h2>
              <p className="text-sm text-gray-600 mt-1">Critical system controls for emergency situations</p>
            </div>
          </div>
        </div>
        <div className="p-6">
          <div className="grid gap-4 md:grid-cols-3">
            <button className="flex flex-col items-center p-4 border-2 border-red-300 rounded-lg hover:border-red-500 hover:bg-red-50 transition-all duration-200 group">
              <svg className="h-8 w-8 text-red-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <span className="text-sm font-medium text-gray-900">System Lockdown</span>
            </button>

            <button className="flex flex-col items-center p-4 border-2 border-yellow-300 rounded-lg hover:border-yellow-500 hover:bg-yellow-50 transition-all duration-200 group">
              <svg className="h-8 w-8 text-yellow-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span className="text-sm font-medium text-gray-900">Maintenance Mode</span>
            </button>

            <button className="flex flex-col items-center p-4 border-2 border-blue-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group">
              <svg className="h-8 w-8 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span className="text-sm font-medium text-gray-900">System Restart</span>
            </button>
          </div>
        </div>
      </div>
      </div>
    </MainLayout>
  );
}
