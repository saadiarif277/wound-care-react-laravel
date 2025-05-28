import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
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
  FiLock,
  FiArchive,
  FiBookOpen,
  FiChevronRight,
  FiChevronDown,
  FiLink,
  FiAlertTriangle,
  FiBriefcase,
  FiAward,
  FiCalendar,
  FiGlobe
} from 'react-icons/fi';
import { UserRole } from '@/types/roles';

interface MenuItem {
  name: string;
  href: string;
  icon: IconType;
  current?: boolean;
  roles: UserRole[];
  children?: MenuItem[];
  description?: string;
}

interface RoleBasedNavigationProps {
  userRole: UserRole;
  currentPath: string;
  isCollapsed: boolean;
}

const getMenuByRole = (role: UserRole): MenuItem[] => {
  // Normalize superadmin to super-admin for consistency
  const normalizedRole = role === 'superadmin' ? 'super-admin' : role;

  switch (normalizedRole) {
    case 'provider':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['provider']
        },
        {
          name: 'Product Requests',
          href: '#',
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
              icon: FiEye,
              roles: ['provider']
            }
          ]
        },
        {
          name: 'MAC/Eligibility/PA',
          href: '#',
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
        },
        {
          name: 'My Credentials',
          href: '/providers/credentials',
          icon: FiAward,
          roles: ['provider'],
          description: 'Manage professional credentials'
        }
      ];

    case 'office-manager':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['office-manager']
        },
        {
          name: 'Product Requests',
          href: '#',
          icon: FiClipboard,
          roles: ['office-manager'],
          children: [
            {
              name: 'New',
              href: '/product-requests/create',
              icon: FiPlus,
              roles: ['office-manager']
            },
            {
              name: 'Facility Requests',
              href: '/product-requests/facility',
              icon: FiClipboard,
              roles: ['office-manager']
            },
            {
              name: 'Provider Requests',
              href: '/product-requests/providers',
              icon: FiUsers,
              roles: ['office-manager']
            }
          ]
        },
        {
          name: 'MAC/Eligibility/PA',
          href: '#',
          icon: FiCheckCircle,
          roles: ['office-manager'],
          children: [
            {
              name: 'MAC Validation',
              href: '/mac-validation',
              icon: FiShield,
              roles: ['office-manager']
            },
            {
              name: 'Eligibility Check',
              href: '/eligibility',
              icon: FiCheckCircle,
              roles: ['office-manager']
            },
            {
              name: 'Pre-Authorization',
              href: '/pre-authorization',
              icon: FiUserCheck,
              roles: ['office-manager']
            }
          ]
        },
        {
          name: 'Product Catalog',
          href: '/products',
          icon: FiPackage,
          roles: ['office-manager']
        },
        {
          name: 'Provider Management',
          href: '/providers',
          icon: FiUsers,
          roles: ['office-manager']
        },
        {
          name: 'Facility Management',
          href: '/facilities',
          icon: FiMapPin,
          roles: ['office-manager'],
          description: 'Manage facilities'
        },
        {
          name: 'Reports',
          href: '/reports',
          icon: FiBarChart,
          roles: ['office-manager'],
          description: 'View facility and provider reports'
        }
      ];

    case 'msc-rep':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['msc-rep']
        },
        {
          name: 'Customer Orders',
          href: '/orders',
          icon: FiShoppingCart,
          roles: ['msc-rep']
        },
        {
          name: 'Commissions',
          href: '#',
          icon: FiDollarSign,
          roles: ['msc-rep'],
          children: [
            {
              name: 'My Earnings',
              href: '/commission',
              icon: FiDollarSign,
              roles: ['msc-rep']
            },
            {
              name: 'History',
              href: '/commission/history',
              icon: FiBarChart,
              roles: ['msc-rep']
            },
            {
              name: 'Payouts',
              href: '/commission/payouts',
              icon: FiTrendingUp,
              roles: ['msc-rep']
            }
          ]
        },
        {
          name: 'My Customers',
          href: '#',
          icon: FiUsers,
          roles: ['msc-rep'],
          children: [
            {
              name: 'Customer List',
              href: '/customers',
              icon: FiUsers,
              roles: ['msc-rep']
            },
            {
              name: 'My Team',
              href: '/team',
              icon: FiUserPlus,
              roles: ['msc-rep']
            }
          ]
        },
        {
          name: 'Reports',
          href: '/reports',
          icon: FiBarChart,
          roles: ['msc-rep'],
          description: 'Sales and commission reports'
        }
      ];

    case 'msc-subrep':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['msc-subrep']
        },
        {
          name: 'Customer Orders',
          href: '/orders',
          icon: FiShoppingCart,
          roles: ['msc-subrep']
        },
        {
          name: 'My Commissions',
          href: '/commission',
          icon: FiDollarSign,
          roles: ['msc-subrep']
        }
      ];

    case 'msc-admin':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['msc-admin']
        },
        {
          name: 'Request Management',
          href: '#',
          icon: FiClipboard,
          roles: ['msc-admin'],
          children: [
            {
              name: 'All Requests',
              href: '/requests',
              icon: FiClipboard,
              roles: ['msc-admin']
            },
            {
              name: 'Product Request Review',
              href: '/admin/product-requests/review',
              icon: FiEye,
              roles: ['msc-admin'],
              description: 'Review and approve product requests'
            }
          ]
        },
        {
          name: 'Order Management',
          href: '#',
          icon: FiShoppingCart,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Create Manual Orders',
              href: '/orders/create',
              icon: FiPlus,
              roles: ['msc-admin']
            },
            {
              name: 'Manage All Orders',
              href: '/orders/manage',
              icon: FiSettings,
              roles: ['msc-admin']
            },
            {
              name: 'Product Management',
              href: '/products/manage',
              icon: FiPackage,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Customer Management',
          href: '#',
          icon: FiBriefcase,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Customer Dashboard',
              href: '/admin/customer-management',
              icon: FiBarChart,
              roles: ['msc-admin'],
              description: 'Customer overview and analytics'
            },
            {
              name: 'Organizations',
              href: '/organizations',
              icon: FiGlobe,
              roles: ['msc-admin'],
              description: 'Manage customer organizations'
            },
            {
              name: 'Create Organization',
              href: '/admin/customer-management/organizations/create',
              icon: FiPlus,
              roles: ['msc-admin'],
              description: 'Add new customer organizations'
            },
            {
              name: 'Onboarding Management',
              href: '/admin/onboarding',
              icon: FiCalendar,
              roles: ['msc-admin'],
              description: 'Track customer onboarding progress'
            }
          ]
        },
        {
          name: 'Engines',
          href: '#',
          icon: FiTool,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Clinical Opportunity Rules',
              href: '/engines/clinical-rules',
              icon: FiTarget,
              roles: ['msc-admin']
            },
            {
              name: 'Product Recommendation Rules',
              href: '/engines/recommendation-rules',
              icon: FiStar,
              roles: ['msc-admin']
            },
            {
              name: 'Commission Management',
              href: '/engines/commission',
              icon: FiDollarSign,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'User & Org Management',
          href: '#',
          icon: FiUsers,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Access Requests',
              href: '/access-requests',
              icon: FiUserCheck,
              roles: ['msc-admin']
            },
            {
              name: 'Sub-Rep Approval Queue',
              href: '/subrep-approvals',
              icon: FiUserPlus,
              roles: ['msc-admin']
            },
            {
              name: 'User Management',
              href: '/users',
              icon: FiUsers,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Analytics & Reports',
          href: '#',
          icon: FiPieChart,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Reports Dashboard',
              href: '/reports',
              icon: FiBarChart,
              roles: ['msc-admin']
            },
            {
              name: 'Order Analytics',
              href: '/orders/analytics',
              icon: FiTrendingUp,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Settings',
          href: '/settings',
          icon: FiSettings,
          roles: ['msc-admin']
        },
        {
          name: 'Role Management',
          href: '/rbac',
          icon: FiShield,
          roles: ['msc-admin'],
          description: 'Manage roles and permissions'
        }
      ];

    case 'super-admin':
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['super-admin', 'superadmin']
        },
        {
          name: 'Request Management',
          href: '#',
          icon: FiClipboard,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'All Requests',
              href: '/requests',
              icon: FiClipboard,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Product Request Review',
              href: '/admin/product-requests/review',
              icon: FiEye,
              roles: ['super-admin', 'superadmin'],
              description: 'Review and approve product requests'
            }
          ]
        },
        {
          name: 'Order Management',
          href: '/orders/manage',
          icon: FiShoppingCart,
          roles: ['super-admin', 'superadmin']
        },
        {
          name: 'Commission Overview',
          href: '/commission/overview',
          icon: FiDollarSign,
          roles: ['super-admin', 'superadmin']
        },
        {
          name: 'Customer Management',
          href: '#',
          icon: FiBriefcase,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Customer Dashboard',
              href: '/admin/customer-management',
              icon: FiBarChart,
              roles: ['super-admin', 'superadmin'],
              description: 'Customer overview and analytics'
            },
            {
              name: 'Organizations',
              href: '/organizations',
              icon: FiGlobe,
              roles: ['super-admin', 'superadmin'],
              description: 'Manage customer organizations'
            },
            {
              name: 'Create Organization',
              href: '/admin/customer-management/organizations/create',
              icon: FiPlus,
              roles: ['super-admin', 'superadmin'],
              description: 'Add new customer organizations'
            },
            {
              name: 'Onboarding Management',
              href: '/admin/onboarding',
              icon: FiCalendar,
              roles: ['super-admin', 'superadmin'],
              description: 'Track customer onboarding progress'
            }
          ]
        },
        {
          name: 'User & Org Management',
          href: '#',
          icon: FiUsers,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'RBAC Configuration',
              href: '/rbac',
              icon: FiLock,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'All Users',
              href: '/users',
              icon: FiUsers,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'System Access Control',
              href: '/access-control',
              icon: FiShield,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Role Management',
              href: '/rbac',
              icon: FiShield,
              roles: ['super-admin', 'superadmin'],
              description: 'Manage roles and permissions'
            }
          ]
        },
        {
          name: 'Analytics & Reports',
          href: '#',
          icon: FiPieChart,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Reports Dashboard',
              href: '/reports',
              icon: FiBarChart,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Order Analytics',
              href: '/orders/analytics',
              icon: FiTrendingUp,
              roles: ['super-admin', 'superadmin']
            }
          ]
        },
        {
          name: 'System Admin',
          href: '#',
          icon: FiSettings,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Platform Configuration',
              href: '/system-admin/config',
              icon: FiSettings,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Integration Settings',
              href: '/system-admin/integrations',
              icon: FiLink,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'API Management',
              href: '/system-admin/api',
              icon: FiTool,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Audit Logs',
              href: '/system-admin/audit',
              icon: FiFileText,
              roles: ['super-admin', 'superadmin']
            }
          ]
        }
      ];

    default:
      return [
        {
          name: 'Dashboard',
          href: '/',
          icon: FiHome,
          roles: ['provider']
        }
      ];
  }
};

export default function RoleBasedNavigation({ userRole, currentPath, isCollapsed }: RoleBasedNavigationProps) {
  const menuItems = getMenuByRole(userRole);
  const [openMenus, setOpenMenus] = useState<string[]>([]);

  const toggleSubmenu = (itemName: string) => {
    setOpenMenus(prev =>
      prev.includes(itemName)
        ? prev.filter(name => name !== itemName)
        : [...prev, itemName]
    );
  };

  const canAccessMenuItem = (item: MenuItem): boolean => {
    // Normalize role for comparison
    const normalizedRole = userRole === 'superadmin' ? 'super-admin' : userRole;
    return item.roles.includes(normalizedRole);
  };

  const renderMenuItem = (item: MenuItem, level: number = 0) => {
    if (!canAccessMenuItem(item)) {
      return null;
    }

    const isActive = currentPath === item.href;
    const hasChildren = item.children && item.children.length > 0;
    const isOpen = openMenus.includes(item.name);

    const isActiveChild = item.children && item.children.some(child =>
      currentPath === child.href ||
      (child.children && child.children.some(grandchild => currentPath === grandchild.href))
    );

    const isOpenChild = item.children && item.children.some(child => openMenus.includes(child.name));

    // Filter children to only show accessible ones
    const accessibleChildren = item.children ? item.children.filter(canAccessMenuItem) : [];
    const hasAccessibleChildren = accessibleChildren.length > 0;

    return (
      <div key={item.name}>
        <div
          className={`flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-200 group cursor-pointer ${
            isActive
              ? 'bg-red-600 text-white shadow-lg'
              : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'
          } ${isCollapsed ? 'justify-center' : ''} ${level > 0 ? 'ml-6' : ''}`}
          title={isCollapsed ? item.name : ''}
          onClick={() => {
            if (hasAccessibleChildren && !isCollapsed) {
              toggleSubmenu(item.name);
            } else if (item.href && item.href !== '#') {
              // Only navigate if href is not a placeholder
              router.visit(item.href);
            }
          }}
        >
          <item.icon className={`flex-shrink-0 w-5 h-5 ${
            isCollapsed ? '' : 'mr-3'
          } ${isActive ? 'text-white' : 'text-gray-500 group-hover:text-blue-600'}`} />
          {!isCollapsed && (
            <>
              <span className="truncate flex-1">{item.name}</span>
              {hasAccessibleChildren && (
                <div className="ml-2">
                  {isOpen ? (
                    <FiChevronDown className="w-4 h-4" />
                  ) : (
                    <FiChevronRight className="w-4 h-4" />
                  )}
                </div>
              )}
            </>
          )}
        </div>
        {hasAccessibleChildren && isOpen && !isCollapsed && (
          <div className="ml-6 mt-2 space-y-1">
            {accessibleChildren.map((child) => renderMenuItem(child, level + 1))}
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
