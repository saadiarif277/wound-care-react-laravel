import { useState } from 'react';
import { router } from '@inertiajs/react';
import { IconType } from 'react-icons';
import {
  FiHome,
  FiClipboard,
  FiShield,
  FiFileText,
  FiPackage,
  FiDollarSign,
  FiUsers,
  FiShoppingCart,
  FiSettings,
  FiUserPlus,
  FiUser,
  FiBarChart,
  FiPieChart,
  FiTrendingUp,
  FiMapPin,
  FiActivity,
  FiArchive,
  FiChevronRight,
  FiChevronDown,
  FiLink,
  FiGlobe,
  FiMail,
  FiSliders,
  FiCloudLightning,
  FiCheckSquare,
  FiCreditCard
} from 'react-icons/fi';
import { UserRole } from '@/types/roles';
import { themes, cn } from '@/theme/glass-theme';

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
  theme: 'dark' | 'light';
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
          name: 'Product Request',
          href: '/quick-requests/create-new',
          icon: FiClipboard,
          roles: ['provider']
        },
        {
          name: 'MAC Validation',
          href: '/mac-validation',
          icon: FiShield,
          roles: ['provider']
        },
        {
          name: 'Product Catalog',
          href: '/products',
          icon: FiPackage,
          roles: ['provider']
        },
        {
          name: 'My Profile',
          href: '/provider/profile',
          icon: FiUser,
          roles: ['provider']
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
          name: 'Product Request',
          href: '/quick-requests/create-new',
          icon: FiClipboard,
          roles: ['office-manager']
        },
        {
          name: 'MAC Validation',
          href: '/mac-validation',
          icon: FiShield,
          roles: ['office-manager']
        },
        {
          name: 'Management',
          href: '#',
          icon: FiSettings,
          roles: ['office-manager'],
          children: [
            {
              name: 'Providers',
              href: '/providers',
              icon: FiUsers,
              roles: ['office-manager']
            },
            {
              name: 'Facilities',
              href: '/facilities',
              icon: FiMapPin,
              roles: ['office-manager']
            },
            {
              name: 'Product Catalog',
              href: '/products',
              icon: FiPackage,
              roles: ['office-manager']
            }
          ]
        },
        {
          name: 'Reports',
          href: '/reports',
          icon: FiBarChart,
          roles: ['office-manager']
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
          name: 'Orders & Sales',
          href: '#',
          icon: FiTrendingUp,
          roles: ['msc-rep'],
          children: [
            {
              name: 'Order Management',
              href: '/orders/center',
              icon: FiShoppingCart,
              roles: ['msc-rep']
            },
            {
              name: 'Commission Tracking',
              href: '/commission/management',
              icon: FiDollarSign,
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
          roles: ['msc-rep']
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
          name: 'Orders & Sales',
          href: '#',
          icon: FiTrendingUp,
          roles: ['msc-subrep'],
          children: [
            {
              name: 'Order Management',
              href: '/orders/center',
              icon: FiShoppingCart,
              roles: ['msc-subrep']
            },
            {
              name: 'Commission Tracking',
              href: '/commission/management',
              icon: FiDollarSign,
              roles: ['msc-subrep']
            }
          ]
        },
        {
          name: 'Reports',
          href: '/reports',
          icon: FiBarChart,
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
          name: 'Operations',
          href: '#',
          icon: FiActivity,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Order Center',
              href: '/admin/orders',
              icon: FiShoppingCart,
              roles: ['msc-admin']
            },
            {
              name: 'Product Catalog',
              href: '/products/manage',
              icon: FiPackage,
              roles: ['msc-admin']
            },
            {
              name: 'Payments',
              href: '/admin/payments',
              icon: FiCreditCard,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Customer Management',
          href: '#',
          icon: FiUsers,
          roles: ['msc-admin'],
          children: [
            {
              name: 'Organizations',
              href: '/admin/organizations',
              icon: FiGlobe,
              roles: ['msc-admin']
            },
            {
              name: 'Providers',
              href: '/admin/providers',
              icon: FiUsers,
              roles: ['msc-admin']
            },
            {
              name: 'Facilities',
              href: '/admin/facilities',
              icon: FiMapPin,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Sales & Finance',
          href: '/commission/management',
          icon: FiDollarSign,
          roles: ['msc-admin']
        },
        {
          name: 'Documents',
          href: '#',
          icon: FiFileText,
          roles: ['msc-admin'],
          children: [
            {
              name: 'PDF Templates',
              href: '/admin/pdf-templates',
              icon: FiArchive,
              roles: ['msc-admin']
            },
            {
              name: 'PDF Reports',
              href: '/admin/pdf-reports',
              icon: FiPieChart,
              roles: ['msc-admin']
            }
          ]
        },
        {
          name: 'Administration',
          href: '#',
          icon: FiSettings,
          roles: ['msc-admin'],
          children: [
            {
              name: 'User Management',
              href: '/admin/users',
              icon: FiUsers,
              roles: ['msc-admin']
            },
            {
              name: 'Invitations',
              href: '/admin/invitations',
              icon: FiMail,
              roles: ['msc-admin']
            },
            {
              name: 'Roles & Permissions',
              href: '/rbac',
              icon: FiShield,
              roles: ['msc-admin']
            },
            {
              name: 'System Settings',
              href: '/settings',
              icon: FiSliders,
              roles: ['msc-admin']
            }
          ]
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
          name: 'Operations',
          href: '#',
          icon: FiActivity,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Order Center',
              href: '/admin/orders',
              icon: FiShoppingCart,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Patient IVR Status',
              href: '/admin/patients/ivr-status',
              icon: FiCheckSquare,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Product Catalog',
              href: '/products/manage',
              icon: FiPackage,
              roles: ['super-admin', 'superadmin']
            }
          ]
        },
        {
          name: 'Customer Management',
          href: '#',
          icon: FiUsers,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Organizations',
              href: '/admin/organizations',
              icon: FiGlobe,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Providers',
              href: '/admin/providers',
              icon: FiUsers,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Facilities',
              href: '/admin/facilities',
              icon: FiMapPin,
              roles: ['super-admin', 'superadmin']
            }
          ]
        },
        {
          name: 'Finance',
          href: '#',
          icon: FiDollarSign,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Commission Management',
              href: '/commission/management',
              icon: FiTrendingUp,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'Payments',
              href: '/admin/payments',
              icon: FiCreditCard,
              roles: ['super-admin', 'superadmin']
            }
          ]
        },
        {
          name: 'Documents',
          href: '#',
          icon: FiFileText,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'PDF Templates',
              href: '/admin/pdf-templates',
              icon: FiArchive,
              roles: ['super-admin', 'superadmin']
            },
            {
              name: 'PDF Reports',
              href: '/admin/pdf-reports',
              icon: FiPieChart,
              roles: ['super-admin', 'superadmin']
            }
          ]
        },
        {
          name: 'System Administration',
          href: '#',
          icon: FiSettings,
          roles: ['super-admin', 'superadmin'],
          children: [
            {
              name: 'Users & Access',
              href: '#',
              icon: FiUsers,
              roles: ['super-admin', 'superadmin'],
              children: [
                {
                  name: 'User Management',
                  href: '/admin/users',
                  icon: FiUsers,
                  roles: ['super-admin', 'superadmin']
                },
                {
                  name: 'Invitations',
                  href: '/admin/invitations',
                  icon: FiMail,
                  roles: ['super-admin', 'superadmin']
                },
                {
                  name: 'Role Management',
                  href: '/rbac',
                  icon: FiShield,
                  roles: ['super-admin', 'superadmin']
                }
              ]
            },
            {
              name: 'Platform Settings',
              href: '#',
              icon: FiSliders,
              roles: ['super-admin', 'superadmin'],
              children: [
                {
                  name: 'Configuration',
                  href: '/system-admin/config',
                  icon: FiSettings,
                  roles: ['super-admin', 'superadmin']
                },
                {
                  name: 'Integrations',
                  href: '/system-admin/integrations',
                  icon: FiLink,
                  roles: ['super-admin', 'superadmin']
                },
                {
                  name: 'API Management',
                  href: '/system-admin/api',
                  icon: FiCloudLightning,
                  roles: ['super-admin', 'superadmin']
                }
              ]
            },
            {
              name: 'Monitoring',
              href: '#',
              icon: FiActivity,
              roles: ['super-admin', 'superadmin'],
              children: [
                {
                  name: 'Audit Logs',
                  href: '/system-admin/audit',
                  icon: FiFileText,
                  roles: ['super-admin', 'superadmin']
                },
                {
                  name: 'System Health',
                  href: '/settings',
                  icon: FiActivity,
                  roles: ['super-admin', 'superadmin']
                }
              ]
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

export default function RoleBasedNavigation({ userRole, currentPath, isCollapsed, theme }: RoleBasedNavigationProps) {
  const menuItems = getMenuByRole(userRole);
  const [openMenus, setOpenMenus] = useState<string[]>([]);
  const t = themes[theme];

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
          className={cn(
            'flex items-center min-h-[48px] py-3 px-4 rounded-xl transition-all duration-200 group cursor-pointer backdrop-blur-sm',
            isActive
              ? theme === 'dark'
                ? 'bg-gradient-to-r from-[rgb(25,37,195)] to-[rgb(199,23,25)] text-white font-semibold shadow-lg shadow-blue-500/25'
                : 'bg-gradient-to-r from-[rgba(25,37,195,0.1)] to-[rgba(199,23,25,0.08)] text-[rgb(25,37,195)] font-semibold border-l-[3px] border-l-[rgb(25,37,195)] shadow-sm'
              : theme === 'dark'
                ? cn(t.text.secondary, 'hover:bg-white/[0.08] hover:text-white/95 hover:backdrop-blur-md')
                : 'text-gray-700 hover:bg-white/60 hover:text-gray-900 hover:shadow-sm hover:border hover:border-gray-200/50',
            isCollapsed ? 'justify-center' : '',
            level > 0 ? 'ml-6' : ''
          )}
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
          <item.icon className={cn(
            'flex-shrink-0 w-5 h-5',
            isCollapsed ? '' : 'mr-4',
            isActive
              ? theme === 'dark' ? 'text-white' : 'text-[rgb(25,37,195)]'
              : theme === 'dark' ? 'text-white/80 group-hover:text-white' : 'text-gray-600 group-hover:text-gray-900'
          )} />
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
          <div className="ml-4 mt-2 space-y-1">
            {accessibleChildren.map((child) => renderMenuItem(child, level + 1))}
          </div>
        )}
      </div>
    );
  };

  return (
    <nav className="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">
      {menuItems.map(item => renderMenuItem(item))}
    </nav>
  );
}
