import React from 'react';
import { Link } from '@inertiajs/react';
import { useRoleAccess } from '@/Hooks/useRoleAccess';

const SideMenu = () => {
    const { hasRole } = useRoleAccess();

    const menuItems = [
        // Product Requests - Accessible by all roles
        {
            label: 'Product Requests',
            icon: 'shopping-cart',
            href: '/product-requests',
            roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin'],
            subItems: [
                {
                    label: 'My Requests',
                    href: '/product-requests',
                    roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin'],
                },
                {
                    label: 'Facility Requests',
                    href: '/product-requests/facility',
                    roles: ['office_manager', 'msc_rep', 'msc_subrep', 'msc_admin'],
                },
                {
                    label: 'Provider Requests',
                    href: '/product-requests/providers',
                    roles: ['office_manager', 'msc_rep', 'msc_subrep', 'msc_admin'],
                },
            ],
        },
        // Order Management - Accessible by admin roles
        {
            label: 'Order Management',
            icon: 'shopping-cart',
            href: '/orders/management',
            roles: ['msc_admin'],
        },
        // Organizations & Analytics - Accessible by admin roles
        {
            label: 'Organizations & Analytics',
            icon: 'globe',
            href: '/admin/organizations',
            roles: ['msc_admin'],
        },
        // Products - Accessible by all roles
        {
            label: 'Products',
            icon: 'box',
            href: '/products',
            roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin'],
        },
        // Sales Management - Accessible by MSC roles and office managers
        {
            label: 'Sales Management',
            icon: 'dollar-sign',
            href: '/commission/management',
            roles: ['msc_rep', 'msc_subrep', 'msc_admin', 'office_manager'],
        },
        // User Management - Admin only
        {
            label: 'User Management',
            icon: 'user-cog',
            href: '/admin/users',
            roles: ['msc_admin'],
        },
        // Role Management - Admin only
        {
            label: 'Role Management',
            icon: 'user-shield',
            href: '/rbac',
            roles: ['msc_admin'],
        },
    ];

    const renderMenuItem = (item, index) => {
        if (!hasRole(item.roles)) return null;

        return (
            <div key={index} className="mb-2">
                <Link
                    href={item.href}
                    className="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-100"
                >
                    <i className={`fas fa-${item.icon} w-6`}></i>
                    <span>{item.label}</span>
                </Link>
                {item.subItems && (
                    <div className="ml-8 mt-1">
                        {item.subItems.map((subItem, subIndex) => (
                            hasRole(subItem.roles) && (
                                <Link
                                    key={subIndex}
                                    href={subItem.href}
                                    className="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                                >
                                    {subItem.label}
                                </Link>
                            )
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="bg-white shadow-lg h-screen w-64 fixed left-0 top-0 overflow-y-auto">
            <div className="p-4 border-b">
                <h2 className="text-xl font-bold text-gray-800">MSC Wound Care</h2>
            </div>
            <nav className="mt-4">
                {menuItems.map((item, index) => renderMenuItem(item, index))}
            </nav>
        </div>
    );
};

export default SideMenu;
