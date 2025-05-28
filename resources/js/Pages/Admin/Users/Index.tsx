import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiUsers,
  FiShield,
  FiUserCheck,
  FiUserX,
  FiEdit3,
  FiTrash2,
  FiPlus,
  FiSearch,
  FiFilter,
  FiMoreVertical,
  FiEye,
  FiSettings,
  FiMail,
  FiUserPlus
} from 'react-icons/fi';

interface UserRole {
  id: number;
  name: string;
  display_name: string;
  slug: string;
  description: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  roles: UserRole[];
  is_active: boolean;
  last_login: string;
  created_at: string;
}

interface Props {
  users: {
    data: User[];
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
  roles: UserRole[];
  stats: {
    total_users: number;
    active_users: number;
    pending_invitations: number;
    recent_logins: number;
  };
}

export default function AdminUsersIndex({ users, roles, stats }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedRole, setSelectedRole] = useState<string>('all');
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  const filteredUsers = users.data.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         user.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesRole = selectedRole === 'all' || 
                       user.roles.some(role => role.slug === selectedRole);
    return matchesSearch && matchesRole;
  });

  const getStatusColor = (isActive: boolean) => {
    return isActive 
      ? 'text-green-600 bg-green-50' 
      : 'text-red-600 bg-red-50';
  };

  const handleUserAction = (action: string, userId: number) => {
    switch (action) {
      case 'edit':
        router.get(`/admin/users/${userId}/edit`);
        break;
      case 'deactivate':
        router.patch(`/admin/users/${userId}/deactivate`);
        break;
      case 'resend-invitation':
        router.post(`/admin/users/${userId}/resend-invitation`);
        break;
    }
  };

  return (
    <MainLayout title="User Management">
      <Head title="User Management" />
      
      <div className="space-y-6">
        {/* Header with Stats */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">User Management</h1>
            <p className="text-sm text-gray-600">Manage users, roles, and access permissions</p>
          </div>
          <div className="flex items-center space-x-3">
            <Link
              href="/admin/provider-invitations"
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <FiMail className="w-4 h-4 mr-2" />
              Provider Invitations
            </Link>
            <Link
              href="/admin/users/create"
              className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-blue-700"
            >
              <FiPlus className="w-4 h-4 mr-2" />
              Add User
            </Link>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiUsers className="h-6 w-6 text-gray-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                    <dd className="text-lg font-medium text-gray-900">{stats.total_users}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiUserCheck className="h-6 w-6 text-green-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                    <dd className="text-lg font-medium text-gray-900">{stats.active_users}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiMail className="h-6 w-6 text-blue-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Pending Invitations</dt>
                    <dd className="text-lg font-medium text-gray-900">{stats.pending_invitations}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiActivity className="h-6 w-6 text-purple-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">Recent Logins</dt>
                    <dd className="text-lg font-medium text-gray-900">{stats.recent_logins}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-medium text-gray-900">Users</h3>
              <div className="flex items-center space-x-4">
                {/* Search */}
                <div className="relative">
                  <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                  <input
                    type="text"
                    placeholder="Search users..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
                {/* Role Filter */}
                <select
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Roles</option>
                  {roles.map((role) => (
                    <option key={role.id} value={role.slug}>{role.display_name}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Users Table */}
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    User
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Roles
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Last Login
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredUsers.map((user) => (
                  <tr key={user.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="flex-shrink-0 h-10 w-10">
                          <div className="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                            <span className="text-white font-medium text-sm">
                              {user.name.charAt(0).toUpperCase()}
                            </span>
                          </div>
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-gray-900">{user.name}</div>
                          <div className="text-sm text-gray-500">{user.email}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex flex-wrap gap-1">
                        {user.roles.map((role) => (
                          <span
                            key={role.id}
                            className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"
                          >
                            {role.display_name}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        getStatusColor(user.is_active)
                      }`}>
                        {user.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        <button
                          onClick={() => handleUserAction('edit', user.id)}
                          className="text-blue-600 hover:text-blue-900"
                          title="Edit User"
                        >
                          <FiEdit3 className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => setSelectedUser(user)}
                          className="text-gray-600 hover:text-gray-900"
                          title="View Details"
                        >
                          <FiEye className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => handleUserAction('deactivate', user.id)}
                          className="text-red-600 hover:text-red-900"
                          title="Deactivate User"
                        >
                          <FiUserX className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {users.pagination.last_page > 1 && (
            <div className="px-6 py-3 border-t border-gray-200 bg-gray-50">
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-700">
                  Showing {((users.pagination.current_page - 1) * users.pagination.per_page) + 1} to{' '}
                  {Math.min(users.pagination.current_page * users.pagination.per_page, users.pagination.total)} of{' '}
                  {users.pagination.total} results
                </p>
                {/* Add pagination controls here */}
              </div>
            </div>
          )}
        </div>

        {/* Quick Actions */}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
          <Link
            href="/access-requests"
            className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow"
          >
            <div>
              <span className="rounded-lg inline-flex p-3 bg-blue-50 text-blue-600 group-hover:bg-blue-100">
                <FiUserCheck className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className="text-lg font-medium text-gray-900">Access Requests</h3>
              <p className="mt-2 text-sm text-gray-500">
                Review and approve user access requests
              </p>
            </div>
          </Link>

          <Link
            href="/rbac"
            className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow"
          >
            <div>
              <span className="rounded-lg inline-flex p-3 bg-purple-50 text-purple-600 group-hover:bg-purple-100">
                <FiShield className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className="text-lg font-medium text-gray-900">Role Management</h3>
              <p className="mt-2 text-sm text-gray-500">
                Configure roles and permissions
              </p>
            </div>
          </Link>

          <Link
            href="/subrep-approvals"
            className="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow"
          >
            <div>
              <span className="rounded-lg inline-flex p-3 bg-green-50 text-green-600 group-hover:bg-green-100">
                <FiUserPlus className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className="text-lg font-medium text-gray-900">Sub-Rep Approvals</h3>
              <p className="mt-2 text-sm text-gray-500">
                Approve pending sub-representative requests
              </p>
            </div>
          </Link>
        </div>
      </div>
    </MainLayout>
  );
} 