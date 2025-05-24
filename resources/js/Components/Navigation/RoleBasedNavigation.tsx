import React from 'react';
import { Link } from '@inertiajs/react';
import { IconType } from 'react-icons';
import {
  FiHome,
  FiClipboard,
  FiCheckCircle,
  FiShield,
  FiFileText,
  FiPackage,
  FiDollarSign,
  FiUsers,
  FiPlus,
  FiEye,
  FiStar,
  FiShoppingCart,
  FiSettings,
  FiDatabase,
  FiTool,
  FiUserPlus,
  FiUserCheck,
  FiBarChart,
  FiPieChart,
  FiTrendingUp,
  FiMapPin,
  FiTarget,
  FiActivity,
  FiCog,
  FiLock,
  FiArchive,
  FiBookOpen
} from 'react-icons/fi';
import { UserRole } from '@/types/roles';

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

const getMenuByRole = (role: UserRole): MenuItem[] => {
  switch (role) {
    case 'provider':
      return [
  {
    name: 'Dashboard',
          href: '/dashboard',
    icon: FiHome,
          roles: ['provider']
  },
  {
          name: 'Product Requests',
          href: '/product-requests',
          icon: FiClipboard,
          roles: ['provider'],
    children: [
      {
              name: 'New Request',
              href: '/product-requests/create',
        icon: FiPlus,
              roles: ['provider']
            },
            {
              name: 'My Requests',
              href: '/product-requests',
              icon: FiClipboard,
              roles: ['provider']
      },
      {
              name: 'Status',
              href: '/product-requests/status',
              icon: FiActivity,
              roles: ['provider']
      }
    ]
  },
  {
          name: 'MAC/Eligibility/PA',
    href: '/eligibility',
    icon: FiCheckCircle,
          roles: ['provider'],
          children: [
  {
    name: 'MAC Validation',
    href: '/mac-validation',
    icon: FiShield,
              roles: ['provider']
            },
            {
              name: 'Eligibility Check',
              href: '/eligibility',
              icon: FiCheckCircle,
              roles: ['provider']
            },
            {
              name: 'Pre-Authorization',
              href: '/pre-authorization',
              icon: FiUserCheck,
              roles: ['provider']
            }
          ]
        },
        {
          name: 'Product Catalog',
          href: '/products',
          icon: FiPackage,
          roles: ['provider']
        }
      ];

    case 'office_manager':
      return [
        {
          name: 'Dashboard',
          href: '/dashboard',
          icon: FiHome,
          roles: ['office_manager']
  },
  {
    name: 'Product Requests',
    href: '/product-requests',
          icon: FiClipboard,
          roles: ['office_manager'],
    children: [
      {
              name: 'New',
              href: '/product-requests/create',
              icon: FiPlus,
              roles: ['office_manager']
            },
            {
              name: 'Facility Requests',
              href: '/product-requests/facility',
        icon: FiClipboard,
              roles: ['office_manager']
      },
      {
              name: 'Provider Requests',
              href: '/product-requests/providers',
              icon: FiUsers,
              roles: ['office_manager']
      }
    ]
  },
  {
          name: 'MAC/Eligibility/PA',
          href: '/eligibility',
          icon: FiCheckCircle,
          roles: ['office_manager'],
          children: [
            {
              name: 'MAC Validation',
              href: '/mac-validation',
              icon: FiShield,
              roles: ['office_manager']
            },
            {
              name: 'Eligibility Check',
              href: '/eligibility',
              icon: FiCheckCircle,
              roles: ['office_manager']
            },
            {
              name: 'Pre-Authorization',
              href: '/pre-authorization',
              icon: FiUserCheck,
              roles: ['office_manager']
            }
          ]
  },
  {
    name: 'Product Catalog',
    href: '/products',
    icon: FiPackage,
          roles: ['office_manager']
        },
      {
          name: 'Provider Management',
          href: '/providers',
          icon: FiUsers,
          roles: ['office_manager']
        }
      ];

    case 'msc_rep':
      return [
        {
          name: 'Dashboard',
          href: '/dashboard',
          icon: FiHome,
          roles: ['msc_rep']
      },
      {
          name: 'Customer Orders',
          href: '/orders',
          icon: FiShoppingCart,
          roles: ['msc_rep']
  },
  {
          name: 'Commissions',
    href: '/commission',
    icon: FiDollarSign,
          roles: ['msc_rep'],
    children: [
      {
              name: 'My Earnings',
              href: '/commission/earnings',
        icon: FiDollarSign,
              roles: ['msc_rep']
      },
      {
              name: 'History',
              href: '/commission/history',
              icon: FiBarChart,
              roles: ['msc_rep']
      },
      {
        name: 'Payouts',
        href: '/commission/payouts',
        icon: FiTrendingUp,
              roles: ['msc_rep']
      }
    ]
  },
  {
          name: 'My Customers',
          href: '/customers',
          icon: FiUsers,
          roles: ['msc_rep'],
    children: [
      {
              name: 'Customer List',
              href: '/customers',
        icon: FiUsers,
              roles: ['msc_rep']
      },
      {
              name: 'My Team',
              href: '/customers/team',
              icon: FiTarget,
              roles: ['msc_rep']
            }
          ]
        }
      ];

    case 'msc_subrep':
      return [
  {
          name: 'Dashboard',
          href: '/dashboard',
          icon: FiHome,
          roles: ['msc_subrep']
        },
      {
          name: 'Customer Orders',
          href: '/orders',
          icon: FiShoppingCart,
        roles: ['msc_subrep']
      },
      {
          name: 'My Commissions',
          href: '/commission',
          icon: FiDollarSign,
        roles: ['msc_subrep']
        }
      ];

    case 'msc_admin':
      return [
        {
          name: 'Dashboard',
          href: '/dashboard',
          icon: FiHome,
          roles: ['msc_admin']
        },
        {
          name: 'Request Management',
          href: '/requests',
          icon: FiClipboard,
          roles: ['msc_admin']
        },
        {
          name: 'Order Management',
          href: '/orders',
          icon: FiShoppingCart,
          roles: ['msc_admin'],
          children: [
            {
              name: 'Create Manual Orders',
              href: '/orders/create',
              icon: FiPlus,
              roles: ['msc_admin']
            },
            {
              name: 'Manage All Orders',
              href: '/orders/manage',
              icon: FiSettings,
              roles: ['msc_admin']
            },
            {
              name: 'Product Management',
              href: '/products/manage',
              icon: FiPackage,
              roles: ['msc_admin']
            },
            {
              name: 'Engines',
              href: '/engines',
              icon: FiCog,
              roles: ['msc_admin'],
              children: [
                {
                  name: 'Clinical Opportunity Rules',
                  href: '/engines/clinical-rules',
                  icon: FiTool,
                  roles: ['msc_admin']
                },
                {
                  name: 'Product Recommendation Rules',
                  href: '/engines/recommendation-rules',
                  icon: FiStar,
                  roles: ['msc_admin']
                },
                {
                  name: 'Commission Management',
                  href: '/engines/commission',
                  icon: FiDollarSign,
                  roles: ['msc_admin']
                }
              ]
      }
    ]
  },
  {
          name: 'User & Org Management',
          href: '/management',
    icon: FiUsers,
          roles: ['msc_admin'],
          children: [
            {
              name: 'Access Requests',
              href: '/access-requests',
              icon: FiUserCheck,
              roles: ['msc_admin']
  },
  {
              name: 'Sub-Rep Approval Queue',
              href: '/subrep-approvals',
              icon: FiUserPlus,
              roles: ['msc_admin']
  },
  {
    name: 'User Management',
    href: '/users',
    icon: FiUsers,
              roles: ['msc_admin']
      },
      {
              name: 'Organization Management',
              href: '/organizations',
              icon: FiSettings,
              roles: ['msc_admin']
      }
    ]
  },
  {
          name: 'Settings',
          href: '/settings',
          icon: FiSettings,
          roles: ['msc_admin']
        }
      ];

    case 'superadmin':
      return [
        {
          name: 'Dashboard',
          href: '/dashboard',
          icon: FiHome,
          roles: ['superadmin']
        },
        {
          name: 'Request Management',
          href: '/requests',
          icon: FiClipboard,
          roles: ['superadmin']
        },
        {
          name: 'Order Management',
          href: '/orders',
          icon: FiShoppingCart,
          roles: ['superadmin']
        },
        {
          name: 'Commission Overview',
          href: '/commission/overview',
          icon: FiPieChart,
          roles: ['superadmin']
        },
        {
          name: 'User & Org Management',
          href: '/management',
          icon: FiUsers,
          roles: ['superadmin'],
    children: [
      {
              name: 'RBAC Configuration',
              href: '/rbac',
              icon: FiLock,
              roles: ['superadmin']
      },
      {
              name: 'All Users',
              href: '/users',
              icon: FiUsers,
              roles: ['superadmin']
      },
      {
              name: 'System Access Control',
              href: '/access-control',
              icon: FiShield,
              roles: ['superadmin']
            },
            {
              name: 'Role Management',
              href: '/roles',
              icon: FiUserCheck,
              roles: ['superadmin']
      }
    ]
  },
  {
          name: 'System Admin',
          href: '/system-admin',
          icon: FiCog,
          roles: ['superadmin'],
          children: [
            {
              name: 'Platform Configuration',
              href: '/system-admin/config',
    icon: FiSettings,
              roles: ['superadmin']
            },
            {
              name: 'Integration Settings',
              href: '/system-admin/integrations',
              icon: FiDatabase,
              roles: ['superadmin']
            },
            {
              name: 'API Management',
              href: '/system-admin/api',
              icon: FiTool,
              roles: ['superadmin']
            },
            {
              name: 'Audit Logs',
              href: '/system-admin/audit',
              icon: FiArchive,
              roles: ['superadmin']
  }
          ]
        }
      ];

    default:
      return [];
  }
};

export default function RoleBasedNavigation({ userRole, currentPath, isCollapsed }: RoleBasedNavigationProps) {
  const menuItems = getMenuByRole(userRole);

  const renderMenuItem = (item: MenuItem, level: number = 0) => {
    const isActive = currentPath === item.href ||
      (item.children && item.children.some(child => currentPath === child.href));

    const hasActiveChild = item.children && item.children.some(child =>
      currentPath === child.href ||
      (child.children && child.children.some(grandchild => currentPath === grandchild.href))
    );

    return (
      <div key={item.name}>
        <Link
          href={item.href}
          className={`flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-200 group ${
            isActive
              ? 'bg-red-600 text-white shadow-lg'
              : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'
          } ${isCollapsed ? 'justify-center' : ''} ${level > 0 ? 'ml-6' : ''}`}
          title={isCollapsed ? item.name : ''}
        >
          <item.icon className={`flex-shrink-0 w-5 h-5 ${
            isCollapsed ? '' : 'mr-3'
          } ${isActive ? 'text-white' : 'text-gray-500 group-hover:text-blue-600'}`} />
          {!isCollapsed && <span className="truncate">{item.name}</span>}
        </Link>
        {item.children && (isActive || hasActiveChild) && !isCollapsed && (
          <div className="ml-6 mt-2 space-y-1">
            {item.children.map((child) => renderMenuItem(child, level + 1))}
          </div>
        )}
      </div>
    );
  };

  return (
    <nav className="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
      {menuItems.map(item => renderMenuItem(item))}
    </nav>
  );
}
