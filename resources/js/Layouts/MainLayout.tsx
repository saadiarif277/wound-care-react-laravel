import { Head, Link } from '@inertiajs/react';
import FlashMessages from '@/Components/Messages/FlashMessages';
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
  FiLogOut,
  FiPlus,
  FiClipboard,
  FiShield,
  FiActivity
} from 'react-icons/fi';

interface MainLayoutProps {
  title?: string;
  children: React.ReactNode;
}

interface MenuItem {
  name: string;
  href: string;
  icon: IconType;
  current?: boolean;
  children?: MenuItem[];
}

export default function MainLayout({ title, children }: MainLayoutProps) {
  const menuItems: MenuItem[] = [
    { name: 'Dashboard', href: '/', icon: FiHome },
    {
      name: 'Order Management',
      href: '/orders',
      icon: FiShoppingCart,
      children: [
        { name: 'Create Order', href: '/orders/create', icon: FiPlus },
        { name: 'Order Approval', href: '/orders/approvals', icon: FiCheckCircle }
      ]
    },

    { name: 'Eligibility Check', href: '/eligibility', icon: FiCheckCircle },
    { name: 'MAC Validation', href: '/mac-validation', icon: FiShield },
    { name: 'Form Assistant', href: '/forms', icon: FiFileText },
    { name: 'Product Management', href: '/products', icon: FiPackage },
    { name: 'Sales Management', href: '/sales', icon: FiDollarSign },
    { name: 'Commission Management', href: '/commission/records', icon: FiPieChart },
    { name: 'Users Management', href: '/users', icon: FiUsers },
    { name: 'Settings', href: '/settings', icon: FiSettings }
  ];

  // Get current path to determine active menu item
  const currentPath = window.location.pathname;

  return (
    <>
      <Head title={title} />
      <div className="flex flex-col min-h-screen bg-gray-50">
        {/* Sidebar and Main Content Container */}
        <div className="flex flex-1 overflow-hidden">
          {/* Sidebar Navigation */}
          <div className="hidden w-64 overflow-y-auto bg-gradient-to-b from-indigo-700 to-indigo-800 md:block">
            <div className="flex flex-col h-full">
              {/* Brand Logo */}
              <div className="flex items-center justify-center h-16 px-4 border-b border-indigo-600">
                <div className="text-xl font-bold text-white">MSC WOUND CARE</div>
              </div>

              {/* Navigation Menu */}
              <nav className="flex-1 px-2 py-4 space-y-1">
                {menuItems.map((item) => {
                  const isActive = currentPath === item.href ||
                    (item.children && item.children.some(child => currentPath === child.href));

                  return (
                    <div key={item.name}>
                      <Link
                        href={item.href}
                        className={`flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors duration-200 ${
                          isActive
                            ? 'bg-indigo-600 text-white'
                            : 'text-indigo-100 hover:bg-indigo-600 hover:bg-opacity-75'
                        }`}
                      >
                        <item.icon className="flex-shrink-0 w-5 h-5 mr-3" />
                        {item.name}
                      </Link>
                      {item.children && isActive && (
                        <div className="ml-8 mt-1 space-y-1">
                          {item.children.map((child) => (
                            <Link
                              key={child.name}
                              href={child.href}
                              className={`flex items-center px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 ${
                                currentPath === child.href
                                  ? 'bg-indigo-500 text-white'
                                  : 'text-indigo-100 hover:bg-indigo-600 hover:bg-opacity-75'
                              }`}
                            >
                              <child.icon className="flex-shrink-0 w-4 h-4 mr-2" />
                              {child.name}
                            </Link>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </nav>

              {/* User Profile and Logout */}
              <div className="p-4 border-t border-indigo-600">
                <div className="flex items-center mb-3">
                  <div className="flex-shrink-0">
                    <img
                      className="w-8 h-8 rounded-full"
                      src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                      alt="User profile"
                    />
                  </div>
                  <div className="ml-3">
                    <p className="text-sm font-medium text-white">John Doe</p>
                    <p className="text-xs font-medium text-indigo-200">Admin</p>
                  </div>
                </div>

                {/* Logout Button */}
                <Link
                  href={route('logout')}
                  method="delete"
                  as="button"
                  className="flex items-center w-full px-4 py-2 text-sm font-medium text-indigo-100 rounded-md hover:bg-indigo-600 hover:text-white transition-colors duration-200"
                >
                  <FiLogOut className="flex-shrink-0 w-5 h-5 mr-3" />
                  Logout
                </Link>
              </div>
            </div>
          </div>

          {/* Main Content Area */}
          <div className="flex-1 overflow-auto focus:outline-none" tabIndex={0}>
            {/* Flash Messages */}
            <FlashMessages />

            {/* Page Content */}
            <main className="flex-1 p-4 md:p-6">
              {children}
            </main>
          </div>
        </div>
      </div>
    </>
  );
}
