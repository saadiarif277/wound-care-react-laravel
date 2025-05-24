import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { UserRole } from '@/types/roles';
import { getRoleDisplayName } from '@/lib/roleUtils';

interface RoleTestSwitcherProps {
  currentRole: UserRole;
  onRoleChange?: (role: UserRole) => void;
}

const allRoles: UserRole[] = [
  'provider',
  'office_manager',
  'msc_rep',
  'msc_subrep',
  'msc_admin',
  'superadmin'
];

const STORAGE_KEY = 'dev_test_role';

export default function RoleTestSwitcher({ currentRole, onRoleChange }: RoleTestSwitcherProps) {
  const [isMinimized, setIsMinimized] = useState(false);
  const [activeRole, setActiveRole] = useState<UserRole>(currentRole);

  // Load role from localStorage on mount
  useEffect(() => {
    const savedRole = localStorage.getItem(STORAGE_KEY) as UserRole;
    if (savedRole && allRoles.includes(savedRole)) {
      setActiveRole(savedRole);
      if (onRoleChange && savedRole !== currentRole) {
        onRoleChange(savedRole);
      }
    }
  }, []);

  const handleRoleChange = (role: UserRole) => {
    // Save to localStorage for persistence
    localStorage.setItem(STORAGE_KEY, role);
    setActiveRole(role);

    // Update URL with role parameter to force page refresh with new role
    const currentUrl = window.location.pathname + window.location.search;
    const url = new URL(window.location.href);
    url.searchParams.set('test_role', role);

    // Use Inertia router to navigate with the new role
    router.visit(url.pathname + url.search, {
      preserveScroll: true,
      preserveState: false,
    });
  };

  const clearTestRole = () => {
    localStorage.removeItem(STORAGE_KEY);
    const url = new URL(window.location.href);
    url.searchParams.delete('test_role');
    router.visit(url.pathname + url.search, {
      preserveScroll: true,
      preserveState: false,
    });
  };

  if (isMinimized) {
    return (
      <div className="fixed bottom-4 right-4 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-2">
        <button
          onClick={() => setIsMinimized(false)}
          className="flex items-center space-x-2 text-sm text-gray-700 hover:text-gray-900"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
          </svg>
          <span className="font-medium">{getRoleDisplayName(activeRole)}</span>
        </button>
      </div>
    );
  }

  return (
    <div className="fixed bottom-4 right-4 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-4 max-w-xs">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-gray-900">Role Testing</h3>
        <button
          onClick={() => setIsMinimized(true)}
          className="text-gray-400 hover:text-gray-600 p-1"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>

      <div className="space-y-1 max-h-64 overflow-y-auto">
        {allRoles.map((role) => (
          <button
            key={role}
            onClick={() => handleRoleChange(role)}
            className={`w-full text-left px-3 py-2 text-sm rounded-md transition-colors ${
              activeRole === role
                ? 'bg-blue-100 text-blue-800 font-medium'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            {getRoleDisplayName(role)}
            {activeRole === role && ' ★'}
          </button>
        ))}
      </div>

      <div className="mt-3 pt-3 border-t border-gray-200 space-y-2">
        <button
          onClick={clearTestRole}
          className="w-full px-3 py-2 text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
        >
          Clear Test Role
        </button>
        <p className="text-xs text-gray-500 text-center">
          Development only • Persists across pages
        </p>
      </div>
    </div>
  );
}
