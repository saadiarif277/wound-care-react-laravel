import React from 'react';
import { Link } from '@inertiajs/react';
import { IconType } from 'react-icons';
import {
  FiHome,
  FiShoppingCart,
  FiCheckCircle,
  FiFileText,
  FiPackage,
  FiDollarSign,
  FiPieChart,
  FiUsers,
  FiSettings,
  FiPlus,
  FiClipboard,
  FiShield,
  FiActivity,
  FiTrendingUp,
  FiBarChart,
  FiTarget,
  FiUserCheck,
  FiEdit
} from 'react-icons/fi';
import { UserRole } from '@/types/roles';
import { filterNavigationByRole } from '@/lib/roleUtils';

interface MenuItem {
  name: string;
  href: string;
  icon: IconType;
  current?: boolean;
  roles: UserRole[];
  children?: MenuItem[];
}

interface RoleBasedNavigationProps {
  userRole: UserRole;
  currentPath: string;
  isCollapsed: boolean;
}

const baseMenuItems: MenuItem[] = [
  {
    name: 'Dashboard',
    href: '/',
    icon: FiHome,
    roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin']
  },
  {
    name: 'Order Management',
    href: '/orders',
    icon: FiShoppingCart,
    roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'],
    children: [
      {
        name: 'Create Order',
        href: '/orders/create',
        icon: FiPlus,
        roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin']
      },
      {
        name: 'Order Approval',
        href: '/orders/approvals',
        icon: FiCheckCircle,
        roles: ['msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'Eligibility Check',
    href: '/eligibility',
    icon: FiCheckCircle,
    roles: ['provider', 'office_manager', 'msc_admin', 'superadmin']
  },
  {
    name: 'MAC Validation',
    href: '/mac-validation',
    icon: FiShield,
    roles: ['provider', 'office_manager', 'msc_admin', 'superadmin']
  },
  {
    name: 'Product Requests',
    href: '/product-requests',
    icon: FiEdit,
    roles: ['provider', 'office_manager'],
    children: [
      {
        name: 'My Requests',
        href: '/product-requests',
        icon: FiClipboard,
        roles: ['provider', 'office_manager']
      },
      {
        name: 'New Request',
        href: '/product-requests/create',
        icon: FiPlus,
        roles: ['provider', 'office_manager']
      }
    ]
  },
  {
    name: 'Form Assistant',
    href: '/forms',
    icon: FiFileText,
    roles: ['provider', 'office_manager']
  },
  {
    name: 'Product Catalog',
    href: '/products',
    icon: FiPackage,
    roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'],
    children: [
      {
        name: 'Browse Products',
        href: '/products',
        icon: FiPackage,
        roles: ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin']
      },
      {
        name: 'Add Product',
        href: '/products/create',
        icon: FiPlus,
        roles: ['msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'Commission Tracking',
    href: '/commission',
    icon: FiDollarSign,
    roles: ['msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'],
    children: [
      {
        name: 'My Commission',
        href: '/commission',
        icon: FiDollarSign,
        roles: ['msc_rep', 'msc_subrep']
      },
      {
        name: 'Commission Management',
        href: '/commission/management',
        icon: FiPieChart,
        roles: ['msc_admin', 'superadmin']
      },
      {
        name: 'Payouts',
        href: '/commission/payouts',
        icon: FiTrendingUp,
        roles: ['msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'Sales & Territory',
    href: '/sales',
    icon: FiTarget,
    roles: ['msc_rep', 'msc_admin', 'superadmin'],
    children: [
      {
        name: 'Customer Management',
        href: '/sales/customers',
        icon: FiUsers,
        roles: ['msc_rep', 'msc_admin', 'superadmin']
      },
      {
        name: 'Territory Analytics',
        href: '/sales/analytics',
        icon: FiBarChart,
        roles: ['msc_rep', 'msc_admin', 'superadmin']
      },
      {
        name: 'Sub-Rep Management',
        href: '/sales/subreps',
        icon: FiUserCheck,
        roles: ['msc_rep', 'msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'Customer Support',
    href: '/support',
    icon: FiActivity,
    roles: ['msc_subrep'],
    children: [
      {
        name: 'Customer Interactions',
        href: '/support/customers',
        icon: FiUsers,
        roles: ['msc_subrep']
      },
      {
        name: 'My Activities',
        href: '/support/activities',
        icon: FiClipboard,
        roles: ['msc_subrep']
      }
    ]
  },
  {
    name: 'Contacts',
    href: '/contacts',
    icon: FiUsers,
    roles: ['office_manager', 'msc_admin', 'superadmin']
  },
  {
    name: 'Organizations',
    href: '/organizations',
    icon: FiSettings,
    roles: ['msc_admin', 'superadmin']
  },
  {
    name: 'User Management',
    href: '/users',
    icon: FiUsers,
    roles: ['msc_admin', 'superadmin'],
    children: [
      {
        name: 'Manage Users',
        href: '/users',
        icon: FiUsers,
        roles: ['msc_admin', 'superadmin']
      },
      {
        name: 'Access Requests',
        href: '/access-requests',
        icon: FiUserCheck,
        roles: ['msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'Reports',
    href: '/reports',
    icon: FiBarChart,
    roles: ['provider', 'office_manager', 'msc_rep', 'msc_admin', 'superadmin'],
    children: [
      {
        name: 'Clinical Reports',
        href: '/reports/clinical',
        icon: FiFileText,
        roles: ['provider', 'office_manager']
      },
      {
        name: 'Sales Reports',
        href: '/reports/sales',
        icon: FiTrendingUp,
        roles: ['msc_rep', 'msc_admin', 'superadmin']
      },
      {
        name: 'System Reports',
        href: '/reports/system',
        icon: FiSettings,
        roles: ['msc_admin', 'superadmin']
      }
    ]
  },
  {
    name: 'System Configuration',
    href: '/system-config',
    icon: FiSettings,
    roles: ['msc_admin', 'superadmin']
  }
];

export default function RoleBasedNavigation({ userRole, currentPath, isCollapsed }: RoleBasedNavigationProps) {
  // Filter menu items based on user role
  const filteredMenuItems = baseMenuItems.filter(item => {
    return item.roles.includes(userRole);
  }).map(item => ({
    ...item,
    children: item.children?.filter(child => child.roles.includes(userRole))
  }));

  const renderMenuItem = (item: MenuItem) => {
    const isActive = currentPath === item.href ||
      (item.children && item.children.some(child => currentPath === child.href));

    return (
      <div key={item.name}>
        <Link
          href={item.href}
          className={`flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-200 group ${
            isActive
              ? 'bg-red-600 text-white shadow-lg'
              : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'
          } ${isCollapsed ? 'justify-center' : ''}`}
          title={isCollapsed ? item.name : ''}
        >
          <item.icon className={`flex-shrink-0 w-5 h-5 ${
            isCollapsed ? '' : 'mr-3'
          } ${isActive ? 'text-white' : 'text-gray-500 group-hover:text-blue-600'}`} />
          {!isCollapsed && <span className="truncate">{item.name}</span>}
        </Link>
        {item.children && isActive && !isCollapsed && (
          <div className="ml-6 mt-2 space-y-1">
            {item.children.map((child) => (
              <Link
                key={child.name}
                href={child.href}
                className={`flex items-center px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 ${
                  currentPath === child.href
                    ? 'bg-red-500 text-white shadow-md'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                }`}
              >
                <child.icon className={`flex-shrink-0 w-4 h-4 mr-3 ${
                  currentPath === child.href ? 'text-white' : 'text-gray-400'
                }`} />
                <span className="truncate">{child.name}</span>
              </Link>
            ))}
          </div>
        )}
      </div>
    );
  };

  return (
    <nav className="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
      {filteredMenuItems.map(renderMenuItem)}
    </nav>
  );
}
