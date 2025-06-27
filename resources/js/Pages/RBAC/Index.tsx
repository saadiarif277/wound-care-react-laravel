import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
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
import { toast } from 'react-hot-toast';
import axios from 'axios';
import { Modal } from '@/Components/Modal';
import { useForm } from '@inertiajs/react';

interface UserRole {
  id: number;
  name: string;
  display_name: string;
  description: string;
  user_count: number;
  hierarchy_level: number;
  is_active: boolean;
  permissions: string[];
  dashboard_config: {
    financial_access?: boolean;
    pricing_access?: string;
    admin_capabilities?: string[];
    customer_data_restrictions?: string[];
  };
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
  first_name?: string;
  last_name?: string;
  name?: string;
  email: string;
  roles?: any[];
  is_active?: boolean;
  last_login?: string;
  last_login_at?: string;
  created_at: string;
  access_requests_count?: number;
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
  const [editingRole, setEditingRole] = useState<UserRole | null>(null);
  const [showRoleModal, setShowRoleModal] = useState(false);
  const [loading, setLoading] = useState(false);
  const [auditLogs, setAuditLogs] = useState<any[]>([]);
  const [loadingAudit, setLoadingAudit] = useState(false);

  const getRiskLevel = (role: UserRole) => {
          if (role.name === 'super-admin') return 'critical';
      if (role.name === 'msc-admin') return 'high';
      if (['msc-rep', 'provider'].includes(role.name)) return 'medium';
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

  const handleToggleRoleStatus = async (role: UserRole) => {
    if (loading) return;
    
    // Prevent disabling critical roles
    if (['super-admin', 'msc-admin'].includes(role.name) && role.is_active) {
      toast.error('System roles cannot be disabled');
      return;
    }

    setLoading(true);
    try {
      await router.post(`/rbac/role/${role.id}/toggle-status`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Role ${role.is_active ? 'disabled' : 'enabled'} successfully`);
        },
        onError: () => {
          toast.error('Failed to update role status');
        }
      });
    } finally {
      setLoading(false);
    }
  };

  const [showViewModal, setShowViewModal] = useState(false);
  
  const handleViewRole = (role: UserRole) => {
    setSelectedRole(role);
    setShowViewModal(true);
  };

  const handleEditRole = (role: UserRole) => {
    setEditingRole(role);
    setShowRoleModal(true);
  };

  const handleCloseModal = () => {
    setShowRoleModal(false);
    setEditingRole(null);
  };

  const loadAuditLogs = async () => {
    setLoadingAudit(true);
    try {
      const response = await axios.get('/rbac/security-audit');
      setAuditLogs(response.data.audit_logs?.data || []);
    } catch (error) {
      toast.error('Failed to load audit logs');
    } finally {
      setLoadingAudit(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'audit' && auditLogs.length === 0) {
      loadAuditLogs();
    }
  }, [activeTab]);

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
                            <h4 className="text-lg font-medium text-gray-900">{role.display_name || role.name}</h4>
                            <p className="text-sm text-gray-600">{role.description || ''}</p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-4">
                          <div className="text-right">
                            <p className="text-sm font-medium text-gray-900">{role.user_count} users</p>
                            <p className="text-xs text-gray-500">Level {role.hierarchy_level}</p>
                          </div>
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleViewRole(role)}
                              className="p-2 text-gray-400 hover:text-gray-600"
                              title="View role details"
                            >
                              <FiEye className="h-4 w-4" />
                            </button>
                            <button 
                              onClick={() => handleEditRole(role)}
                              className="p-2 text-gray-400 hover:text-gray-600"
                              title="Edit role permissions"
                            >
                              <FiEdit3 className="h-4 w-4" />
                            </button>
                            <button 
                              onClick={() => handleToggleRoleStatus(role)}
                              className="p-2 text-gray-400 hover:text-gray-600"
                              title={role.is_active ? 'Disable role' : 'Enable role'}
                              disabled={loading}
                            >
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
                {loadingAudit ? (
                  <div className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Loading audit logs...</p>
                  </div>
                ) : auditLogs.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    <FiActivity className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No audit logs found</p>
                    <p className="text-sm">Security events will appear here when they occur</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {auditLogs.map((log: any) => (
                      <div key={log.id} className={`p-4 rounded-lg border ${
                        log.risk_level === 'high' ? 'border-red-200 bg-red-50' :
                        log.risk_level === 'medium' ? 'border-yellow-200 bg-yellow-50' :
                        'border-gray-200 bg-gray-50'
                      }`}>
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <div className="flex items-center">
                              <span className={`text-sm font-medium ${
                                log.risk_level === 'high' ? 'text-red-700' :
                                log.risk_level === 'medium' ? 'text-yellow-700' :
                                'text-gray-700'
                              }`}>
                                {log.event_type}
                              </span>
                              {log.risk_level && (
                                <span className={`ml-2 px-2 py-1 text-xs rounded-full ${
                                  log.risk_level === 'high' ? 'bg-red-100 text-red-700' :
                                  log.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                                  'bg-gray-100 text-gray-700'
                                }`}>
                                  {log.risk_level} risk
                                </span>
                              )}
                            </div>
                            <p className="text-sm text-gray-600 mt-1">
                              {log.entity_type}: {log.entity_name}
                            </p>
                            {log.performed_by && (
                              <p className="text-xs text-gray-500 mt-1">
                                By: {log.performed_by.name} ({log.performed_by.email})
                              </p>
                            )}
                            {log.reason && (
                              <p className="text-xs text-gray-500 mt-1">
                                Reason: {log.reason}
                              </p>
                            )}
                          </div>
                          <div className="text-xs text-gray-500">
                            {new Date(log.created_at).toLocaleString()}
                          </div>
                        </div>
                        {log.changes && Object.keys(log.changes).length > 0 && (
                          <div className="mt-2 text-xs text-gray-600">
                            Changes: {JSON.stringify(log.changes)}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Role Edit Modal */}
      <RoleEditModal
        show={showRoleModal}
        role={editingRole}
        permissions={permissions}
        onClose={handleCloseModal}
        onSuccess={() => {
          handleCloseModal();
          router.reload({ only: ['userRoles', 'roleStats'] });
        }}
      />

      {/* Role View Modal */}
      <RoleViewModal
        show={showViewModal}
        role={selectedRole}
        permissions={permissions}
        onClose={() => {
          setShowViewModal(false);
          setSelectedRole(null);
        }}
      />
    </MainLayout>
  );
}

// Role Edit Modal Component
interface RoleEditModalProps {
  show: boolean;
  role: UserRole | null;
  permissions: Permission[];
  onClose: () => void;
  onSuccess: () => void;
}

function RoleEditModal({ show, role, permissions, onClose, onSuccess }: RoleEditModalProps) {
  const { data, setData, put, processing, errors, reset } = useForm({
    permission_ids: [] as number[],
    reason: ''
  });

  useEffect(() => {
    if (role) {
      // Get permission IDs from the role's permissions
      const permissionIds = permissions
        .filter(p => role.permissions.includes(p.slug))
        .map(p => p.id);
      setData('permission_ids', permissionIds);
    }
  }, [role, permissions]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!role) return;

    put(`/rbac/role/${role.id}/permissions`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Role permissions updated successfully');
        reset();
        onSuccess();
      },
      onError: () => {
        toast.error('Failed to update role permissions');
      }
    });
  };

  const togglePermission = (permissionId: number) => {
    setData('permission_ids', 
      data.permission_ids.includes(permissionId)
        ? data.permission_ids.filter(id => id !== permissionId)
        : [...data.permission_ids, permissionId]
    );
  };

  if (!role) return null;

  // Group permissions by category
  const permissionGroups = permissions.reduce((groups, permission) => {
    const category = permission.slug.split('-')[0];
    if (!groups[category]) {
      groups[category] = [];
    }
    groups[category].push(permission);
    return groups;
  }, {} as Record<string, Permission[]>);

  return (
    <Modal show={show} onClose={onClose} maxWidth="2xl">
      <form onSubmit={handleSubmit}>
        <div className="p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            Edit Role: {role.display_name}
          </h2>
          <p className="text-sm text-gray-600 mb-6">
            {role.description}
          </p>

          {/* Permission Groups */}
          <div className="space-y-6 max-h-96 overflow-y-auto">
            {Object.entries(permissionGroups).map(([category, perms]) => (
              <div key={category} className="border rounded-lg p-4">
                <h3 className="font-medium text-gray-900 mb-3 capitalize">
                  {category} Permissions
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {perms.map((permission) => (
                    <label
                      key={permission.id}
                      className="flex items-start space-x-3 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        checked={data.permission_ids.includes(permission.id)}
                        onChange={() => togglePermission(permission.id)}
                        className="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        disabled={['super-admin', 'msc-admin'].includes(role.name) && 
                                 ['manage-rbac', 'manage-all-organizations'].includes(permission.slug)}
                      />
                      <div className="flex-1">
                        <p className="text-sm font-medium text-gray-900">
                          {permission.name}
                        </p>
                        <p className="text-xs text-gray-500">
                          {permission.description}
                        </p>
                      </div>
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {/* Reason for Change */}
          <div className="mt-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Reason for Change (Required)
            </label>
            <textarea
              value={data.reason}
              onChange={(e) => setData('reason', e.target.value)}
              rows={3}
              className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Please provide a reason for these permission changes..."
              required
            />
            {errors.reason && (
              <p className="mt-1 text-sm text-red-600">{errors.reason}</p>
            )}
          </div>
        </div>

        {/* Modal Footer */}
        <div className="bg-gray-50 px-6 py-4 flex justify-between items-center">
          <div className="text-sm text-gray-600">
            {role.user_count} user{role.user_count !== 1 ? 's' : ''} will be affected
          </div>
          <div className="flex space-x-3">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={processing || !data.reason}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {processing ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </div>
      </form>
    </Modal>
  );
}

// Role View Modal Component
interface RoleViewModalProps {
  show: boolean;
  role: UserRole | null;
  permissions: Permission[];
  onClose: () => void;
}

function RoleViewModal({ show, role, permissions, onClose }: RoleViewModalProps) {
  if (!role) return null;

  // Get full permission details for this role
  const rolePermissions = permissions.filter(p => role.permissions.includes(p.slug));

  // Group permissions by category
  const permissionGroups = rolePermissions.reduce((groups, permission) => {
    const category = permission.slug.split('-')[0];
    if (!groups[category]) {
      groups[category] = [];
    }
    groups[category].push(permission);
    return groups;
  }, {} as Record<string, Permission[]>);

  return (
    <Modal show={show} onClose={onClose} maxWidth="2xl">
      <div className="p-6">
        <div className="flex items-start justify-between mb-6">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">
              {role.display_name}
            </h2>
            <p className="text-sm text-gray-600 mt-1">
              {role.description}
            </p>
          </div>
          <div className={`px-3 py-1 rounded-full text-xs font-medium ${
            role.is_active 
              ? 'bg-green-100 text-green-800' 
              : 'bg-red-100 text-red-800'
          }`}>
            {role.is_active ? 'Active' : 'Inactive'}
          </div>
        </div>

        {/* Role Statistics */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-gray-50 rounded-lg p-4 text-center">
            <p className="text-2xl font-semibold text-gray-900">{role.user_count}</p>
            <p className="text-sm text-gray-600">Users</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-4 text-center">
            <p className="text-2xl font-semibold text-gray-900">{role.permissions.length}</p>
            <p className="text-sm text-gray-600">Permissions</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-4 text-center">
            <p className="text-2xl font-semibold text-gray-900">Level {role.hierarchy_level}</p>
            <p className="text-sm text-gray-600">Hierarchy</p>
          </div>
        </div>

        {/* Dashboard Configuration */}
        <div className="mb-6">
          <h3 className="text-sm font-medium text-gray-900 mb-3">Dashboard Configuration</h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-center space-x-2">
              <div className={`w-3 h-3 rounded-full ${
                role.dashboard_config.financial_access ? 'bg-green-500' : 'bg-red-500'
              }`} />
              <span className="text-sm text-gray-700">Financial Access</span>
            </div>
            <div className="flex items-center space-x-2">
              <div className={`w-3 h-3 rounded-full ${
                role.dashboard_config.pricing_access === 'full' ? 'bg-green-500' : 
                role.dashboard_config.pricing_access === 'limited' ? 'bg-yellow-500' : 
                'bg-red-500'
              }`} />
              <span className="text-sm text-gray-700">
                Pricing Access: {role.dashboard_config.pricing_access || 'None'}
              </span>
            </div>
            {role.dashboard_config.admin_capabilities && role.dashboard_config.admin_capabilities.length > 0 && (
              <div className="col-span-2">
                <p className="text-sm text-gray-700 mb-1">Admin Capabilities:</p>
                <div className="flex flex-wrap gap-2">
                  {role.dashboard_config.admin_capabilities.map((cap, idx) => (
                    <span key={idx} className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                      {cap.replace(/_/g, ' ')}
                    </span>
                  ))}
                </div>
              </div>
            )}
            {role.dashboard_config.customer_data_restrictions && role.dashboard_config.customer_data_restrictions.length > 0 && (
              <div className="col-span-2">
                <p className="text-sm text-gray-700 mb-1">Data Restrictions:</p>
                <div className="flex flex-wrap gap-2">
                  {role.dashboard_config.customer_data_restrictions.map((restriction, idx) => (
                    <span key={idx} className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">
                      {restriction.replace(/_/g, ' ')}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Permissions by Category */}
        <div>
          <h3 className="text-sm font-medium text-gray-900 mb-3">Permissions ({rolePermissions.length})</h3>
          <div className="space-y-4 max-h-64 overflow-y-auto">
            {Object.entries(permissionGroups).map(([category, perms]) => (
              <div key={category} className="border rounded-lg p-3">
                <h4 className="font-medium text-gray-800 mb-2 capitalize">
                  {category} ({perms.length})
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                  {perms.map((permission) => (
                    <div key={permission.id} className="text-sm">
                      <p className="font-medium text-gray-700">{permission.name}</p>
                      <p className="text-xs text-gray-500">{permission.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Modal Footer */}
      <div className="bg-gray-50 px-6 py-4 flex justify-end">
        <button
          type="button"
          onClick={onClose}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Close
        </button>
      </div>
    </Modal>
  );
}
