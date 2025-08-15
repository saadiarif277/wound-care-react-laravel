import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiUser,
  FiMail,
  FiLock,
  FiShield,
  FiArrowLeft,
  FiSave,
  FiX
} from 'react-icons/fi';

interface UserRole {
  id: number;
  name: string;
  display_name: string;
  slug: string;
}

interface Props {
  roles: UserRole[];
}

export default function AdminUsersCreate({ roles }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as number[],
    is_verified: true
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [processing, setProcessing] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  const handleRoleChange = (roleId: number, checked: boolean) => {
    setFormData(prev => ({
      ...prev,
      roles: checked
        ? [...prev.roles, roleId]
        : prev.roles.filter(id => id !== roleId)
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Validate that at least one role is selected
    if (formData.roles.length === 0) {
      setErrors(prev => ({ ...prev, roles: 'Please select at least one role' }));
      return;
    }

    setProcessing(true);

    router.post('/admin/users', formData, {
      preserveScroll: true,
      onError: (errors) => {
        setErrors(errors);
        setProcessing(false);
      },
      onSuccess: () => {
        setProcessing(false);
      }
    });
  };

  const handleCancel = () => {
    router.visit('/admin/users');
  };

  return (
    <MainLayout>
      <Head title="Create User" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <Link
              href="/admin/users"
              className={cn(
                "inline-flex items-center text-sm transition-colors",
                t.text.secondary,
                "hover:" + t.text.primary
              )}
            >
              <FiArrowLeft className="mr-2" />
              Back to Users
            </Link>
            <h1 className={cn("mt-2 text-3xl font-bold", t.text.primary)}>
              Create User
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Add a new user to the system
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-blue-500/20 mr-3">
                <FiUser className="w-5 h-5 text-blue-400" />
              </div>
              Basic Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  First Name *
                </label>
                <input
                  type="text"
                  name="first_name"
                  value={formData.first_name}
                  onChange={handleChange}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    errors.first_name
                      ? "border-red-500 focus:ring-red-500/20"
                      : "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                  placeholder="John"
                  required
                />
                {errors.first_name && (
                  <p className="mt-1 text-sm text-red-500">{errors.first_name}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  Last Name *
                </label>
                <input
                  type="text"
                  name="last_name"
                  value={formData.last_name}
                  onChange={handleChange}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    errors.last_name
                      ? "border-red-500 focus:ring-red-500/20"
                      : "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                  placeholder="Doe"
                  required
                />
                {errors.last_name && (
                  <p className="mt-1 text-sm text-red-500">{errors.last_name}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  Email Address *
                </label>
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    errors.email
                      ? "border-red-500 focus:ring-red-500/20"
                      : "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                  placeholder="user@example.com"
                  required
                />
                {errors.email && (
                  <p className="mt-1 text-sm text-red-500">{errors.email}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  Status
                </label>
                <select
                  name="is_verified"
                  value={formData.is_verified ? '1' : '0'}
                  onChange={(e) => setFormData(prev => ({ ...prev, is_verified: e.target.value === '1' }))}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                >
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
          </div>

          {/* Account Setup */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-green-500/20 mr-3">
                <FiLock className="w-5 h-5 text-green-400" />
              </div>
              Account Setup
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  Password *
                </label>
                <input
                  type="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    errors.password
                      ? "border-red-500 focus:ring-red-500/20"
                      : "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                  placeholder="••••••••"
                  required
                  minLength={8}
                />
                {errors.password && (
                  <p className="mt-1 text-sm text-red-500">{errors.password}</p>
                )}
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                  Confirm Password *
                </label>
                <input
                  type="password"
                  name="password_confirmation"
                  value={formData.password_confirmation}
                  onChange={handleChange}
                  className={cn(
                    "w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2",
                    errors.password_confirmation
                      ? "border-red-500 focus:ring-red-500/20"
                      : "border-gray-300 focus:ring-blue-500/20",
                    t.input.base
                  )}
                  placeholder="••••••••"
                  required
                />
                {errors.password_confirmation && (
                  <p className="mt-1 text-sm text-red-500">{errors.password_confirmation}</p>
                )}
              </div>
            </div>
          </div>

          {/* Roles */}
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <h2 className={cn("text-xl font-semibold mb-6 flex items-center", t.text.primary)}>
              <div className="p-2 rounded-xl bg-purple-500/20 mr-3">
                <FiShield className="w-5 h-5 text-purple-400" />
              </div>
              Roles & Permissions
            </h2>

            <div className="space-y-3">
              {roles.map((role) => (
                <label key={role.id} className="flex items-center space-x-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.roles.includes(role.id)}
                    onChange={(e) => handleRoleChange(role.id, e.target.checked)}
                    className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                  <span className={cn("text-sm font-medium", t.text.primary)}>
                    {role.display_name}
                  </span>
                  <span className={cn("text-xs", t.text.secondary)}>
                    ({role.slug})
                  </span>
                </label>
              ))}
              {errors.roles && (
                <p className="mt-1 text-sm text-red-500">{errors.roles}</p>
              )}
            </div>
          </div>

          {/* Form Actions */}
          <div className="flex items-center justify-end space-x-3">
            <button
              type="button"
              onClick={handleCancel}
              className={cn(
                "px-4 py-2 border rounded-lg text-sm font-medium transition-colors",
                "border-gray-300 text-gray-700 bg-white hover:bg-gray-50"
              )}
            >
              <FiX className="w-4 h-4 mr-2 inline" />
              Cancel
            </button>
            <button
              type="submit"
              disabled={processing}
              className={cn(
                "px-4 py-2 border border-transparent rounded-lg text-sm font-medium transition-colors",
                "bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
              )}
            >
              <FiSave className="w-4 h-4 mr-2 inline" />
              {processing ? 'Creating...' : 'Create User'}
            </button>
          </div>
        </form>
      </div>
    </MainLayout>
  );
}
