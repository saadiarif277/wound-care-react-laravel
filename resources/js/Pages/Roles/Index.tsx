import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiUsers,
  FiShield,
  FiLock,
  FiSettings,
  FiPlus,
  FiEdit3,
  FiTrash2,
  FiEye,
  FiToggleLeft,
  FiToggleRight,
  FiCheck,
  FiX,
  FiAlertTriangle
} from 'react-icons/fi';

interface Permission {
  id: number;
  name: string;
  slug: string;
  description: string;
}

interface Role {
  id: number;
  name: string;
  slug: string;
  description: string;
  permissions: Permission[];
  users_count?: number;
  created_at: string;
  updated_at: string;
}

interface Props {
  roles: Role[];
  permissions: Permission[];
}

export default function RolesIndex({ roles, permissions }: Props) {
  const [activeTab, setActiveTab] = useState<'roles' | 'permissions'>('roles');
  const [selectedRole, setSelectedRole] = useState<Role | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);

  const [newRole, setNewRole] = useState({
    name: '',
    slug: '',
    description: '',
    permissions: [] as number[]
  });

  const handleCreateRole = () => {
    // Implementation for creating role
    setShowCreateModal(false);
    setNewRole({ name: '', slug: '', description: '', permissions: [] });
  };

  const handleEditRole = (role: Role) => {
    setEditingRole(role);
    setNewRole({
      name: role.name,
      slug: role.slug,
      description: role.description,
      permissions: role.permissions.map(p => p.id)
    });
    setShowEditModal(true);
  };

  const handleUpdateRole = () => {
    // Implementation for updating role
    setShowEditModal(false);
    setEditingRole(null);
    setNewRole({ name: '', slug: '', description: '', permissions: [] });
  };

  const handleDeleteRole = (role: Role) => {
    if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
      // Implementation for deleting role
    }
  };

  const togglePermission = (permissionId: number) => {
    setNewRole(prev => ({
      ...prev,
      permissions: prev.permissions.includes(permissionId)
        ? prev.permissions.filter(id => id !== permissionId)
        : [...prev.permissions, permissionId]
    }));
  };

  const getRoleRiskLevel = (role: Role) => {
    const adminPermissions = role.permissions.filter(p =>
      p.slug.includes('admin') || p.slug.includes('delete') || p.slug.includes('manage')
    ).length;

    if (adminPermissions > 5) return 'high';
    if (adminPermissions > 2) return 'medium';
    return 'low';
  };

  const getRiskColor = (level: string) => {
    switch (level) {
      case 'high': return 'text-red-600 bg-red-50';
      case 'medium': return 'text-yellow-600 bg-yellow-50';
      case 'low': return 'text-green-600 bg-green-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  const Modal = ({ isOpen, onClose, title, children }: any) => {
    if (!isOpen) return null;

    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600"
            >
              <FiX className="h-6 w-6" />
            </button>
          </div>
          {children}
        </div>
      </div>
    );
  };

  return (
    <MainLayout>
      <Head title="Role Management" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 flex items-center">
              <FiLock className="mr-3 text-blue-600" />
              Role & Permission Management
            </h1>
            <p className="mt-2 text-gray-600">
              Configure roles and permissions to control user access across the platform
            </p>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="p-3 rounded-lg bg-blue-500">
                  <FiUsers className="h-6 w-6 text-white" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-600">Total Roles</p>
                  <p className="text-2xl font-semibold text-gray-900">{roles.length}</p>
                </div>
              </div>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="p-3 rounded-lg bg-green-500">
                  <FiLock className="h-6 w-6 text-white" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-600">Permissions</p>
                  <p className="text-2xl font-semibold text-gray-900">{permissions.length}</p>
                </div>
              </div>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center">
                <div className="p-3 rounded-lg bg-purple-500">
                  <FiShield className="h-6 w-6 text-white" />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-600">Active Users</p>
                  <p className="text-2xl font-semibold text-gray-900">
                    {roles.reduce((sum, role) => sum + (role.users_count || 0), 0)}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="border-b border-gray-200 mb-6">
            <nav className="-mb-px flex space-x-8">
              {[
                { id: 'roles', label: 'Roles', icon: FiUsers },
                { id: 'permissions', label: 'Permissions', icon: FiLock },
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
          {activeTab === 'roles' && (
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">System Roles</h3>
                    <p className="text-sm text-gray-600">Manage roles and their associated permissions</p>
                  </div>
                  <button
                    onClick={() => setShowCreateModal(true)}
                    className="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                  >
                    <FiPlus className="mr-2 h-4 w-4" />
                    Create Role
                  </button>
                </div>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {roles.map((role) => (
                    <div key={role.id} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          <div className={`px-2 py-1 rounded-full text-xs font-medium ${
                            getRiskColor(getRoleRiskLevel(role))
                          }`}>
                            {getRoleRiskLevel(role).toUpperCase()}
                          </div>
                          <div>
                            <h4 className="text-lg font-medium text-gray-900">{role.name}</h4>
                            <p className="text-sm text-gray-600">{role.description}</p>
                            <p className="text-xs text-gray-500 mt-1">
                              {role.permissions.length} permissions • {role.users_count || 0} users
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => setSelectedRole(role)}
                            className="p-2 text-gray-400 hover:text-gray-600"
                            title="View Details"
                          >
                            <FiEye className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => handleEditRole(role)}
                            className="p-2 text-gray-400 hover:text-gray-600"
                            title="Edit Role"
                          >
                            <FiEdit3 className="h-4 w-4" />
                          </button>
                          {role.slug !== 'super-admin' && (
                            <button
                              onClick={() => handleDeleteRole(role)}
                              className="p-2 text-gray-400 hover:text-red-600"
                              title="Delete Role"
                            >
                              <FiTrash2 className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>

                      {/* Role Permissions Preview */}
                      <div className="mt-4">
                        <div className="flex flex-wrap gap-2">
                          {role.permissions.slice(0, 5).map((permission) => (
                            <span
                              key={permission.id}
                              className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"
                            >
                              {permission.name}
                            </span>
                          ))}
                          {role.permissions.length > 5 && (
                            <span className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">
                              +{role.permissions.length - 5} more
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

          {activeTab === 'permissions' && (
            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">System Permissions</h3>
                <p className="text-sm text-gray-600">View all available permissions in the system</p>
              </div>
              <div className="p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {permissions.map((permission) => (
                    <div key={permission.id} className="border border-gray-200 rounded-lg p-4">
                      <h4 className="font-medium text-gray-900">{permission.name}</h4>
                      <p className="text-sm text-gray-600 mt-1">{permission.description}</p>
                      <div className="mt-3 flex items-center justify-between">
                        <span className="text-xs text-gray-500 font-mono bg-gray-100 px-2 py-1 rounded">
                          {permission.slug}
                        </span>
                        <span className="text-xs text-gray-500">
                          Used by {roles.filter(r => r.permissions.some(p => p.id === permission.id)).length} role(s)
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Create Role Modal */}
          <Modal
            isOpen={showCreateModal}
            onClose={() => setShowCreateModal(false)}
            title="Create New Role"
          >
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Role Name
                </label>
                <input
                  type="text"
                  value={newRole.name}
                  onChange={(e) => setNewRole(prev => ({ ...prev, name: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Enter role name"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Role Slug
                </label>
                <input
                  type="text"
                  value={newRole.slug}
                  onChange={(e) => setNewRole(prev => ({ ...prev, slug: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="role-slug"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <textarea
                  value={newRole.description}
                  onChange={(e) => setNewRole(prev => ({ ...prev, description: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  rows={3}
                  placeholder="Describe the role's purpose"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Permissions
                </label>
                <div className="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
                  <div className="space-y-2">
                    {permissions.map((permission) => (
                      <label key={permission.id} className="flex items-center">
                        <input
                          type="checkbox"
                          checked={newRole.permissions.includes(permission.id)}
                          onChange={() => togglePermission(permission.id)}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <div className="ml-3">
                          <span className="text-sm font-medium text-gray-900">{permission.name}</span>
                          <p className="text-xs text-gray-500">{permission.description}</p>
                        </div>
                      </label>
                    ))}
                  </div>
                </div>
              </div>
              <div className="flex justify-end space-x-3 pt-4">
                <button
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                  Cancel
                </button>
                <button
                  onClick={handleCreateRole}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  Create Role
                </button>
              </div>
            </div>
          </Modal>

          {/* Edit Role Modal */}
          <Modal
            isOpen={showEditModal}
            onClose={() => setShowEditModal(false)}
            title={`Edit Role: ${editingRole?.name}`}
          >
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Role Name
                </label>
                <input
                  type="text"
                  value={newRole.name}
                  onChange={(e) => setNewRole(prev => ({ ...prev, name: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Role Slug
                </label>
                <input
                  type="text"
                  value={newRole.slug}
                  onChange={(e) => setNewRole(prev => ({ ...prev, slug: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  disabled={editingRole?.slug === 'super-admin'}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <textarea
                  value={newRole.description}
                  onChange={(e) => setNewRole(prev => ({ ...prev, description: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  rows={3}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Permissions
                </label>
                <div className="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
                  <div className="space-y-2">
                    {permissions.map((permission) => (
                      <label key={permission.id} className="flex items-center">
                        <input
                          type="checkbox"
                          checked={newRole.permissions.includes(permission.id)}
                          onChange={() => togglePermission(permission.id)}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          disabled={editingRole?.slug === 'super-admin'}
                        />
                        <div className="ml-3">
                          <span className="text-sm font-medium text-gray-900">{permission.name}</span>
                          <p className="text-xs text-gray-500">{permission.description}</p>
                        </div>
                      </label>
                    ))}
                  </div>
                </div>
              </div>
              <div className="flex justify-end space-x-3 pt-4">
                <button
                  onClick={() => setShowEditModal(false)}
                  className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                  Cancel
                </button>
                <button
                  onClick={handleUpdateRole}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  Update Role
                </button>
              </div>
            </div>
          </Modal>

          {/* Role Details Modal */}
          {selectedRole && (
            <Modal
              isOpen={!!selectedRole}
              onClose={() => setSelectedRole(null)}
              title={`Role Details: ${selectedRole.name}`}
            >
              <div className="space-y-4">
                <div>
                  <h4 className="font-medium text-gray-900">Description</h4>
                  <p className="text-sm text-gray-600">{selectedRole.description}</p>
                </div>
                <div>
                  <h4 className="font-medium text-gray-900">Permissions ({selectedRole.permissions.length})</h4>
                  <div className="mt-2 space-y-2">
                    {selectedRole.permissions.map((permission) => (
                      <div key={permission.id} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <div>
                          <span className="text-sm font-medium text-gray-900">{permission.name}</span>
                          <p className="text-xs text-gray-500">{permission.description}</p>
                        </div>
                        <span className="text-xs text-gray-500 font-mono bg-white px-2 py-1 rounded">
                          {permission.slug}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="flex justify-end pt-4">
                  <button
                    onClick={() => setSelectedRole(null)}
                    className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                  >
                    Close
                  </button>
                </div>
              </div>
            </Modal>
          )}
        </div>
      </div>
    </MainLayout>
  );
}
