import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Role, Permission } from '@/types';
import {
    ShieldCheckIcon,
    UserGroupIcon,
    KeyIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    CheckIcon,
    XMarkIcon
} from '@heroicons/react/24/outline';
import { toast } from 'react-hot-toast';

interface RoleManagementProps {
    roles: Role[];
    permissions: Permission[];
    roleStats: {
        id: number;
        name: string;
        slug: string;
        description: string;
        user_count: number;
        permissions_count: number;
        permissions: string[];
        is_active: boolean;
    }[];
    permissionStats: {
        id: number;
        name: string;
        slug: string;
        description: string;
        roles_count: number;
        roles: string[];
    }[];
}

interface RoleFormData {
    name: string;
    slug: string;
    description: string;
    permissions: number[];
    reason: string;
}

export default function RoleManagement({ roles, permissions, roleStats, permissionStats }: RoleManagementProps) {
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);
    const [isEditing, setIsEditing] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [filteredPermissions, setFilteredPermissions] = useState(permissions);
    const [searchTerm, setSearchTerm] = useState('');

    const { data, setData, put, post, delete: destroy, processing, errors, reset } = useForm<RoleFormData>({
        name: '',
        slug: '',
        description: '',
        permissions: [],
        reason: '',
    });

    // Filter permissions based on search term
    useEffect(() => {
        if (searchTerm) {
            const filtered = permissions.filter(permission =>
                permission.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permission.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
            setFilteredPermissions(filtered);
        } else {
            setFilteredPermissions(permissions);
        }
    }, [searchTerm, permissions]);

    const handleEditRole = (role: Role) => {
        setSelectedRole(role);
        setData({
            name: role.name,
            slug: role.slug,
            description: role.description,
            permissions: role.permissions.map(p => p.id),
            reason: '',
        });
        setIsEditing(true);
    };

    const handleCreateRole = () => {
        setSelectedRole(null);
        setData({
            name: '',
            slug: '',
            description: '',
            permissions: [],
            reason: '',
        });
        setIsEditing(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (selectedRole) {
            put(route('roles.update', selectedRole.id), {
                onSuccess: () => {
                    toast.success('Role updated successfully');
                    setIsEditing(false);
                    reset();
                },
                onError: () => {
                    toast.error('Failed to update role');
                },
            });
        } else {
            post(route('roles.store'), {
                onSuccess: () => {
                    toast.success('Role created successfully');
                    setIsEditing(false);
                    reset();
                },
                onError: () => {
                    toast.error('Failed to create role');
                },
            });
        }
    };

    const handleDeleteRole = (role: Role) => {
        setSelectedRole(role);
        setShowDeleteConfirm(true);
    };

    const confirmDelete = () => {
        if (selectedRole) {
            destroy(route('roles.destroy', selectedRole.id), {
                onSuccess: () => {
                    toast.success('Role deleted successfully');
                    setShowDeleteConfirm(false);
                    setSelectedRole(null);
                },
                onError: () => {
                    toast.error('Failed to delete role');
                },
            });
        }
    };

    const togglePermission = (permissionId: number) => {
        setData(prev => ({
            ...prev,
            permissions: prev.permissions.includes(permissionId)
                ? prev.permissions.filter(id => id !== permissionId)
                : [...prev.permissions, permissionId]
        }));
    };

    return (
        <MainLayout>
            <Head title="Role Management" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Header */}
                            <div className="flex justify-between items-center mb-6">
                                <div>
                                    <h2 className="text-2xl font-semibold text-gray-900">Role Management</h2>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Manage roles and their permissions
                                    </p>
                                </div>
                                <button
                                    onClick={handleCreateRole}
                                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <PlusIcon className="h-4 w-4 mr-2" />
                                    Create Role
                                </button>
                            </div>

                            {/* Role List */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                {roleStats.map((role) => (
                                    <div
                                        key={role.id}
                                        className="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200"
                                    >
                                        <div className="p-4">
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <h3 className="text-lg font-medium text-gray-900">{role.name}</h3>
                                                    <p className="text-sm text-gray-500">{role.description}</p>
                                                </div>
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={() => handleEditRole(roles.find(r => r.id === role.id)!)}
                                                        className="text-gray-400 hover:text-gray-500"
                                                    >
                                                        <PencilIcon className="h-5 w-5" />
                                                    </button>
                                                    {role.slug !== 'super-admin' && (
                                                        <button
                                                            onClick={() => handleDeleteRole(roles.find(r => r.id === role.id)!)}
                                                            className="text-gray-400 hover:text-red-500"
                                                        >
                                                            <TrashIcon className="h-5 w-5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="mt-4 grid grid-cols-2 gap-4">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-500">Users</p>
                                                    <p className="mt-1 text-lg font-semibold text-gray-900">
                                                        {role.user_count}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-500">Permissions</p>
                                                    <p className="mt-1 text-lg font-semibold text-gray-900">
                                                        {role.permissions_count}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Edit/Create Role Form */}
                            {isEditing && (
                                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                                    <div className="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                                        <div className="p-6">
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                                {selectedRole ? 'Edit Role' : 'Create Role'}
                                            </h3>
                                            <form onSubmit={handleSubmit}>
                                                <div className="space-y-4">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700">
                                                            Name
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={data.name}
                                                            onChange={e => setData('name', e.target.value)}
                                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        />
                                                        {errors.name && (
                                                            <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                                        )}
                                                    </div>

                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700">
                                                            Slug
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={data.slug}
                                                            onChange={e => setData('slug', e.target.value)}
                                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        />
                                                        {errors.slug && (
                                                            <p className="mt-1 text-sm text-red-600">{errors.slug}</p>
                                                        )}
                                                    </div>

                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700">
                                                            Description
                                                        </label>
                                                        <textarea
                                                            value={data.description}
                                                            onChange={e => setData('description', e.target.value)}
                                                            rows={3}
                                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        />
                                                        {errors.description && (
                                                            <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                                                        )}
                                                    </div>

                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                                            Permissions
                                                        </label>
                                                        <div className="mb-2">
                                                            <input
                                                                type="text"
                                                                placeholder="Search permissions..."
                                                                value={searchTerm}
                                                                onChange={e => setSearchTerm(e.target.value)}
                                                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            />
                                                        </div>
                                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border rounded-md">
                                                            {filteredPermissions.map((permission) => (
                                                                <label
                                                                    key={permission.id}
                                                                    className="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded cursor-pointer"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={data.permissions.includes(permission.id)}
                                                                        onChange={() => togglePermission(permission.id)}
                                                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    <div>
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
                                                        {errors.permissions && (
                                                            <p className="mt-1 text-sm text-red-600">{errors.permissions}</p>
                                                        )}
                                                    </div>

                                                    {selectedRole && (
                                                        <div>
                                                            <label className="block text-sm font-medium text-gray-700">
                                                                Reason for Change
                                                            </label>
                                                            <textarea
                                                                value={data.reason}
                                                                onChange={e => setData('reason', e.target.value)}
                                                                rows={2}
                                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                placeholder="Please provide a reason for this change..."
                                                            />
                                                            {errors.reason && (
                                                                <p className="mt-1 text-sm text-red-600">{errors.reason}</p>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="mt-6 flex justify-end space-x-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setIsEditing(false);
                                                            reset();
                                                        }}
                                                        className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                    >
                                                        {processing ? 'Saving...' : selectedRole ? 'Update Role' : 'Create Role'}
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Delete Confirmation Modal */}
                            {showDeleteConfirm && selectedRole && (
                                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                                    <div className="bg-white rounded-lg max-w-md w-full p-6">
                                        <div className="text-center">
                                            <TrashIcon className="mx-auto h-12 w-12 text-red-500" />
                                            <h3 className="mt-2 text-lg font-medium text-gray-900">
                                                Delete Role
                                            </h3>
                                            <p className="mt-2 text-sm text-gray-500">
                                                Are you sure you want to delete the role "{selectedRole.name}"? This action cannot be undone.
                                            </p>
                                        </div>
                                        <div className="mt-6 flex justify-end space-x-3">
                                            <button
                                                type="button"
                                                onClick={() => setShowDeleteConfirm(false)}
                                                className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="button"
                                                onClick={confirmDelete}
                                                className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
