import React from 'react';
import { usePage } from '@inertiajs/react';

interface RoleDebugInfoProps {
  className?: string;
}

export default function RoleDebugInfo({ className = '' }: RoleDebugInfoProps) {
  const { props } = usePage();

  // Only show in development
  if (process.env.NODE_ENV !== 'development') {
    return null;
  }

  return (
    <div className={`fixed bottom-4 right-4 bg-black bg-opacity-90 text-white p-4 rounded-lg text-xs font-mono z-50 max-w-md ${className}`}>
      <div className="font-bold text-yellow-400 mb-2">🐛 Role Debug Info</div>

      <div className="space-y-1">
        <div><span className="text-blue-300">User Role:</span> {props.userRole || 'undefined'}</div>
        <div><span className="text-blue-300">User Email:</span> {props.auth?.user?.email || 'undefined'}</div>
        <div><span className="text-blue-300">User Owner:</span> {props.user?.owner ? 'true' : 'false'}</div>
        <div><span className="text-blue-300">Role Display:</span> {props.user?.role_display_name || 'undefined'}</div>
      </div>

      {props.roleRestrictions && (
        <div className="mt-3">
          <div className="font-bold text-green-400 mb-1">Role Restrictions:</div>
          <div className="space-y-1 text-xs">
            <div><span className="text-blue-300">Financial Access:</span> {props.roleRestrictions.can_view_financials ? 'Yes' : 'No'}</div>
            <div><span className="text-blue-300">Commission Access:</span> {props.roleRestrictions.commission_access_level || 'none'}</div>
            <div><span className="text-blue-300">Pricing Access:</span> {props.roleRestrictions.pricing_access_level || 'limited'}</div>
            <div><span className="text-blue-300">Can Manage Products:</span> {props.roleRestrictions.can_manage_products ? 'Yes' : 'No'}</div>
          </div>
        </div>
      )}

      {props.dashboardData && (
        <div className="mt-3">
          <div className="font-bold text-purple-400 mb-1">Dashboard Data:</div>
          <div className="text-xs">
            <div><span className="text-blue-300">Recent Requests:</span> {props.dashboardData.recent_requests?.length || 0}</div>
            <div><span className="text-blue-300">Action Items:</span> {props.dashboardData.action_items?.length || 0}</div>
          </div>
        </div>
      )}

      <div className="mt-3 text-xs text-gray-400">
        Current URL: {window.location.pathname}
      </div>
    </div>
  );
}
