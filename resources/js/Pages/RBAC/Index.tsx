import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiUsers,
  FiShield,
  FiLock,
  FiSettings,
  FiEye,
  FiEdit3,
  FiToggleLeft,
  FiToggleRight,
  FiAlertTriangle,
  FiCheckCircle,
  FiXCircle,
  FiBarChart,
  FiActivity,
  FiClock
} from 'react-icons/fi';

interface UserRole {
  id: number;
  name: string;
  display_name: string;
  description: string;
  user_count: number;
  hierarchy_level: number;
  is_active: boolean;
  permissions: string[];
  dashboard_config: any;
}

interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string;
  roles_count: number;
  roles: string[];
}

interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  is_active: boolean;
  last_login: string;
  created_at: string;
  access_requests_count: number;
}

interface RoleStats {
  role: string;
  count: number;
  percentage: number;
}

interface Props {
  userRoles: UserRole[];
  permissions: Permission[];
  users: User[];
  roleStats: UserRole[];
  permissionStats: Permission[];
  userDistribution: RoleStats[];
  totalUsers: number;
  totalRoles: number;
  totalPermissions: number;
}

export default function RBACIndex({
  userRoles,
  permissions,
  users,
  roleStats,
  permissionStats,
  userDistribution,
  totalUsers,
  totalRoles,
  totalPermissions
}: Props) {
  const [activeTab, setActiveTab] = useState<'overview' | 'roles' | 'permissions' | 'audit'>('overview');
  const [selectedRole, setSelectedRole] = useState<UserRole | null>(null);

  const getRiskLevel = (role: UserRole) => {
    if (role.name === 'super_admin') return 'critical';
    if (role.name === 'msc_admin') return 'high';
    if (['msc_rep', 'provider'].includes(role.name)) return 'medium';
    return 'low';
  };

  const getRiskColor = (level: string) => {
    switch (level) {
      case 'critical': return 'text-red-600 bg-red-50';
      case 'high': return 'text-orange-600 bg-orange-50';
      case 'medium': return 'text-yellow-600 bg-yellow-50';
      default: return 'text-green-600 bg-green-50';
    }
  };

  const StatCard = ({ title, value, icon: Icon, color }: any) => (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center">
        <div className={`p-3 rounded-lg ${color}`}>
          <Icon className="h-6 w-6 text-white" />
        </div>
        <div className="ml-4">
          <p className="text-sm font-medium text-gray-600">{title}</p>
          <p className="text-2xl font-semibold text-gray-900">{value}</p>
        </div>
      </div>
    </div>
  );

  return (
    <MainLayout>
      <Head title="RBAC Configuration" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 flex items-center">
              <FiShield className="mr-3 text-blue-600" />
              Role-Based Access Control
            </h1>
            <p className="mt-2 text-gray-600">
              Manage user roles, permissions, and access control across the platform
            </p>
          </div>

          {/* Stats Overview */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <StatCard
              title="Total Users"
              value={totalUsers}
              icon={FiUsers}
              color="bg-blue-500"
            />
            <StatCard
              title="Active Roles"
              value={totalRoles}
              icon={FiLock}
              color="bg-green-500"
            />
            <StatCard
              title="Permissions"
              value={totalPermissions}
              icon={FiSettings}
              color="bg-purple-500"
            />
            <StatCard
              title="Security Level"
              value="High"
              icon={FiShield}
              color="bg-orange-500"
            />
          </div>

          {/* Navigation Tabs */}
          <div className="border-b border-gray-200 mb-6">
            <nav className="-mb-px flex space-x-8">
              {[
                { id: 'overview', label: 'Overview', icon: FiBarChart },
                { id: 'roles', label: 'Role Management', icon: FiUsers },
                { id: 'permissions', label: 'Permissions', icon: FiLock },
                { id: 'audit', label: 'Security Audit', icon: FiActivity },
              ].map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as any)}
                  className={`flex items-center py-2 px-1 border-b-2 font-medium text-sm ${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <tab.icon className="mr-2 h-4 w-4" />
                  {tab.label}
                </button>
              ))}
            </nav>
          </div>

          {/* Tab Content */}
          {activeTab === 'overview' && (
            <div className="space-y-6">
              {/* Role Distribution Chart */}
              <div className="bg-white rounded-lg shadow p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Role Distribution</h3>
                <div className="space-y-4">
                  {userDistribution.map((item, index) => (
                    <div key={index} className="flex items-center justify-between">
                      <div className="flex items-center">
                        <div className="w-4 h-4 rounded-full bg-blue-500 mr-3"></div>
                        <span className="text-sm font-medium text-gray-900">{item.role}</span>
                      </div>
                      <div className="flex items-center space-x-4">
                        <span className="text-sm text-gray-600">{item.count} users</span>
                        <span className="text-sm font-medium text-gray-900">{item.percentage}%</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Recent Activity */}
              <div className="bg-white rounded-lg shadow p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                  <FiClock className="mr-2" />
                  Recent RBAC Changes
                </h3>
                <div className="space-y-3">
                  <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                    <div className="flex items-center">
                      <FiAlertTriangle className="h-4 w-4 text-yellow-600 mr-3" />
                      <span className="text-sm text-gray-900">Role permissions updated for MSC Admin</span>
                    </div>
                    <span className="text-xs text-gray-500">2 hours ago</span>
                  </div>
                  <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div className="flex items-center">
                      <FiCheckCircle className="h-4 w-4 text-green-600 mr-3" />
                      <span className="text-sm text-gray-900">New user assigned Office Manager role</span>
                    </div>
                    <span className="text-xs text-gray-500">1 day ago</span>
                  </div>
                  <div className="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div className="flex items-center">
                      <FiXCircle className="h-4 w-4 text-red-600 mr-3" />
                      <span className="text-sm text-gray-900">User access revoked for security violation</span>
                    </div>
                    <span className="text-xs text-gray-500">3 days ago</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'roles' && (
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Role Management</h3>
                <p className="text-sm text-gray-600">Configure roles and their permissions</p>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {roleStats.map((role) => (
                    <div key={role.id} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          <div className={`px-2 py-1 rounded-full text-xs font-medium ${getRiskColor(getRiskLevel(role))}`}>
                            {getRiskLevel(role).toUpperCase()}
                          </div>
                          <div>
                            <h4 className="text-lg font-medium text-gray-900">{role.display_name}</h4>
                            <p className="text-sm text-gray-600">{role.description}</p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-4">
                          <div className="text-right">
                            <p className="text-sm font-medium text-gray-900">{role.user_count} users</p>
                            <p className="text-xs text-gray-500">Level {role.hierarchy_level}</p>
                          </div>
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => setSelectedRole(role)}
                              className="p-2 text-gray-400 hover:text-gray-600"
                            >
                              <FiEye className="h-4 w-4" />
                            </button>
                            <button className="p-2 text-gray-400 hover:text-gray-600">
                              <FiEdit3 className="h-4 w-4" />
                            </button>
                            <button className="p-2 text-gray-400 hover:text-gray-600">
                              {role.is_active ? (
                                <FiToggleRight className="h-4 w-4 text-green-500" />
                              ) : (
                                <FiToggleLeft className="h-4 w-4 text-gray-400" />
                              )}
                            </button>
                          </div>
                        </div>
                      </div>

                      {/* Role Capabilities */}
                      <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                        <div className="flex items-center">
                          <span className={`w-2 h-2 rounded-full mr-2 ${
                            role.dashboard_config?.financial_access ? 'bg-green-500' : 'bg-red-500'
                          }`}></span>
                          Financial Access
                        </div>
                        <div className="flex items-center">
                          <span className={`w-2 h-2 rounded-full mr-2 ${
                            role.dashboard_config?.pricing_access === 'full' ? 'bg-green-500' : 'bg-yellow-500'
                          }`}></span>
                          Pricing: {role.dashboard_config?.pricing_access || 'Limited'}
                        </div>
                        <div className="flex items-center">
                          <span className={`w-2 h-2 rounded-full mr-2 ${
                            role.dashboard_config?.admin_capabilities?.length > 0 ? 'bg-green-500' : 'bg-gray-400'
                          }`}></span>
                          Admin Capabilities
                        </div>
                        <div className="flex items-center">
                          <span className={`w-2 h-2 rounded-full mr-2 ${
                            !role.dashboard_config?.customer_data_restrictions?.includes('no_phi') ? 'bg-green-500' : 'bg-red-500'
                          }`}></span>
                          PHI Access
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === 'permissions' && (
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Permission Management</h3>
                <p className="text-sm text-gray-600">View and manage system permissions</p>
              </div>
              <div className="p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {permissionStats.map((permission) => (
                    <div key={permission.id} className="border border-gray-200 rounded-lg p-4">
                      <h4 className="font-medium text-gray-900">{permission.name}</h4>
                      <p className="text-sm text-gray-600 mt-1">{permission.description}</p>
                      <div className="mt-3 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                          Used by {permission.roles_count} role{permission.roles_count !== 1 ? 's' : ''}
                        </span>
                        <div className="flex flex-wrap gap-1">
                          {permission.roles.slice(0, 2).map((role, index) => (
                            <span key={index} className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                              {role}
                            </span>
                          ))}
                          {permission.roles.length > 2 && (
                            <span className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">
                              +{permission.roles.length - 2}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === 'audit' && (
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Security Audit Log</h3>
                <p className="text-sm text-gray-600">Monitor RBAC changes and security events</p>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {/* Placeholder for audit logs */}
                  <div className="text-center py-8 text-gray-500">
                    <FiActivity className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>Security audit logs will appear here</p>
                    <p className="text-sm">Track role changes, permission updates, and access modifications</p>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
}
