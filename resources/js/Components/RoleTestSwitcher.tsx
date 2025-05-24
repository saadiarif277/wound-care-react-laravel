import React from 'react';
import { Link } from '@inertiajs/react';
import { UserRole } from '@/types/roles';
import { getRoleDisplayName } from '@/lib/roleUtils';

interface RoleTestSwitcherProps {
  currentRole: UserRole;
}

const allRoles: UserRole[] = [
  'provider',
  'office_manager',
  'msc_rep',
  'msc_subrep',
  'msc_admin',
  'superadmin'
];

export default function RoleTestSwitcher({ currentRole }: RoleTestSwitcherProps) {
  return (
    <div className="fixed bottom-4 right-4 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-4">
      <h3 className="text-sm font-semibold text-gray-900 mb-3">Test Role Switching</h3>
      <div className="space-y-2">
        {allRoles.map((role) => (
          <Link
            key={role}
            href={`/?test_role=${role}`}
            className={`block px-3 py-2 text-sm rounded-md transition-colors ${
              currentRole === role
                ? 'bg-blue-100 text-blue-800 font-medium'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            {getRoleDisplayName(role)}
            {currentRole === role && ' (Current)'}
          </Link>
        ))}
      </div>
      <div className="mt-3 pt-3 border-t border-gray-200">
        <p className="text-xs text-gray-500">
          Development testing only
        </p>
      </div>
    </div>
  );
}
