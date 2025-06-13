import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { GlassTable, Table, Thead, Tbody, Tr, Th, Td } from '@/Components/ui/GlassTable';
import { glassTheme, themes, cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
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
  FiUserPlus,
  FiActivity
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

  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const filteredUsers = users.data.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         user.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesRole = selectedRole === 'all' || 
                       user.roles.some(role => role.slug === selectedRole);
    return matchesSearch && matchesRole;
  });

  const getStatusColor = (isActive: boolean) => {
    return isActive 
      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' 
      : 'bg-red-500/20 text-red-300 border border-red-500/30';
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
            <h1 className={cn("text-2xl font-semibold", t.text.primary)}>User Management</h1>
            <p className={cn("text-sm", t.text.secondary)}>Manage users, roles, and access permissions</p>
          </div>
          <div className="flex items-center space-x-3">
            <Link
              href="/admin/provider-invitations"
              className={cn(
                "inline-flex items-center px-4 py-2 border rounded-lg text-sm font-medium transition-colors",
                theme === 'dark' 
                  ? "border-white/20 text-white/90 bg-white/[0.05] hover:bg-white/[0.10]" 
                  : "border-gray-300 text-gray-700 bg-white hover:bg-gray-50"
              )}
            >
              <FiMail className="w-4 h-4 mr-2" />
              Provider Invitations
            </Link>
            <Link
              href="/admin/users/create"
              className={cn(
                "inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium transition-colors",
                theme === 'dark' 
                  ? "bg-blue-600 text-white hover:bg-blue-700" 
                  : "bg-blue-600 text-white hover:bg-blue-700"
              )}
            >
              <FiPlus className="w-4 h-4 mr-2" />
              Add User
            </Link>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div className={cn("overflow-hidden shadow rounded-lg", t.glass.card)}>
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiUsers className={cn("h-6 w-6", t.text.muted)} />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className={cn("text-sm font-medium truncate", t.text.secondary)}>Total Users</dt>
                    <dd className={cn("text-lg font-medium", t.text.primary)}>{stats.total_users}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className={cn("overflow-hidden shadow rounded-lg", t.glass.card)}>
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiUserCheck className="h-6 w-6 text-green-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className={cn("text-sm font-medium truncate", t.text.secondary)}>Active Users</dt>
                    <dd className={cn("text-lg font-medium", t.text.primary)}>{stats.active_users}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className={cn("overflow-hidden shadow rounded-lg", t.glass.card)}>
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiMail className="h-6 w-6 text-blue-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className={cn("text-sm font-medium truncate", t.text.secondary)}>Pending Invitations</dt>
                    <dd className={cn("text-lg font-medium", t.text.primary)}>{stats.pending_invitations}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div className={cn("overflow-hidden shadow rounded-lg", t.glass.card)}>
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiActivity className="h-6 w-6 text-purple-400" />
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className={cn("text-sm font-medium truncate", t.text.secondary)}>Recent Logins</dt>
                    <dd className={cn("text-lg font-medium", t.text.primary)}>{stats.recent_logins}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className={cn("shadow rounded-lg", t.glass.card)}>
          <div className={cn("px-6 py-4 border-b", theme === 'dark' ? 'border-white/10' : 'border-gray-200')}>
            <div className="flex items-center justify-between">
              <h3 className={cn("text-lg font-medium", t.text.primary)}>Users</h3>
              <div className="flex items-center space-x-4">
                {/* Search */}
                <div className="relative">
                  <FiSearch className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
                  <input
                    type="text"
                    placeholder="Search users..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className={cn(
                      "pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 transition-colors",
                      theme === 'dark'
                        ? "bg-white/[0.05] border-white/20 text-white placeholder-white/50 focus:border-blue-400"
                        : "bg-white border-gray-300 text-gray-900 placeholder-gray-500 focus:border-blue-500"
                    )}
                  />
                </div>
                {/* Role Filter */}
                <select
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value)}
                  className={cn(
                    "px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 transition-colors",
                    theme === 'dark'
                      ? "bg-white/[0.05] border-white/20 text-white focus:border-blue-400"
                      : "bg-white border-gray-300 text-gray-900 focus:border-blue-500"
                  )}
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
          <GlassTable>
            <Table>
              <Thead>
                <Tr>
                  <Th>User</Th>
                  <Th>Roles</Th>
                  <Th>Status</Th>
                  <Th>Last Login</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </Thead>
              <Tbody>
                {filteredUsers.map((user, index) => (
                  <Tr key={user.id} isEven={index % 2 === 0}>
                    <Td>
                      <div className="flex items-center">
                        <div className="flex-shrink-0 h-10 w-10">
                          <div className="h-10 w-10 rounded-full bg-gradient-to-r from-[#1925c3] to-[#c71719] flex items-center justify-center">
                            <span className="text-white font-medium text-sm">
                              {user.name.charAt(0).toUpperCase()}
                            </span>
                          </div>
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium">{user.name}</div>
                          <div className="text-sm opacity-75">{user.email}</div>
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="flex flex-wrap gap-1">
                        {user.roles.map((role) => (
                          <span
                            key={role.id}
                            className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30"
                          >
                            {role.display_name}
                          </span>
                        ))}
                      </div>
                    </Td>
                    <Td>
                      <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        getStatusColor(user.is_active)
                      }`}>
                        {user.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </Td>
                    <Td className="text-sm opacity-75">
                      {user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
                    </Td>
                    <Td className="text-right">
                      <div className="flex items-center justify-end space-x-2">
                        <button
                          onClick={() => handleUserAction('edit', user.id)}
                          className="text-blue-300 hover:text-blue-200 transition-colors"
                          title="Edit User"
                        >
                          <FiEdit3 className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => setSelectedUser(user)}
                          className={cn(
                            "transition-colors",
                            theme === 'dark' 
                              ? "text-white/60 hover:text-white/90" 
                              : "text-gray-600 hover:text-gray-900"
                          )}
                          title="View Details"
                        >
                          <FiEye className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => handleUserAction('deactivate', user.id)}
                          className="text-red-300 hover:text-red-200 transition-colors"
                          title="Deactivate User"
                        >
                          <FiUserX className="h-4 w-4" />
                        </button>
                      </div>
                    </Td>
                  </Tr>
                ))}
              </Tbody>
            </Table>
          </GlassTable>

          {/* Pagination */}
          {users.pagination.last_page > 1 && (
            <div className={cn("backdrop-blur-xl border px-6 py-3 mt-4 rounded-xl", t.glass.card)}>
              <div className="flex items-center justify-between">
                <p className={cn("text-sm", t.text.secondary)}>
                  Showing <span className={cn("font-medium", t.text.primary)}>{((users.pagination.current_page - 1) * users.pagination.per_page) + 1}</span> to{' '}
                  <span className={cn("font-medium", t.text.primary)}>{Math.min(users.pagination.current_page * users.pagination.per_page, users.pagination.total)}</span> of{' '}
                  <span className={cn("font-medium", t.text.primary)}>{users.pagination.total}</span> results
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
            className={cn(
              "relative group p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow",
              t.glass.card
            )}
          >
            <div>
              <span className={cn(
                "rounded-lg inline-flex p-3 text-blue-600 transition-colors",
                theme === 'dark'
                  ? "bg-blue-500/20 group-hover:bg-blue-500/30"
                  : "bg-blue-50 group-hover:bg-blue-100"
              )}>
                <FiUserCheck className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className={cn("text-lg font-medium", t.text.primary)}>Access Requests</h3>
              <p className={cn("mt-2 text-sm", t.text.secondary)}>
                Review and approve user access requests
              </p>
            </div>
          </Link>

          <Link
            href="/rbac"
            className={cn(
              "relative group p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow",
              t.glass.card
            )}
          >
            <div>
              <span className={cn(
                "rounded-lg inline-flex p-3 text-purple-600 transition-colors",
                theme === 'dark'
                  ? "bg-purple-500/20 group-hover:bg-purple-500/30"
                  : "bg-purple-50 group-hover:bg-purple-100"
              )}>
                <FiShield className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className={cn("text-lg font-medium", t.text.primary)}>Role Management</h3>
              <p className={cn("mt-2 text-sm", t.text.secondary)}>
                Configure roles and permissions
              </p>
            </div>
          </Link>

          <Link
            href="/subrep-approvals"
            className={cn(
              "relative group p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow",
              t.glass.card
            )}
          >
            <div>
              <span className={cn(
                "rounded-lg inline-flex p-3 text-green-600 transition-colors",
                theme === 'dark'
                  ? "bg-green-500/20 group-hover:bg-green-500/30"
                  : "bg-green-50 group-hover:bg-green-100"
              )}>
                <FiUserPlus className="h-6 w-6" />
              </span>
            </div>
            <div className="mt-4">
              <h3 className={cn("text-lg font-medium", t.text.primary)}>Sub-Rep Approvals</h3>
              <p className={cn("mt-2 text-sm", t.text.secondary)}>
                Approve pending sub-representative requests
              </p>
            </div>
          </Link>
        </div>
      </div>
    </MainLayout>
  );
} 