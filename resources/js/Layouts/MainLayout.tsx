import { Head, Link, usePage } from '@inertiajs/react';
import FlashMessages from '@/Components/Messages/FlashMessages';
import RoleBasedNavigation from '@/Components/Navigation/RoleBasedNavigation';
import RoleTestSwitcher from '@/Components/RoleTestSwitcher';
import { UserRole } from '@/types/roles';
import React, { useState, useEffect } from 'react';
import {
  FiLogOut,
  FiMenu,
  FiX,
  FiChevronLeft,
  FiChevronRight
} from 'react-icons/fi';

interface MainLayoutProps {
  title?: string;
  children: React.ReactNode;
}

interface PageProps extends Record<string, unknown> {
  userRole?: UserRole;
  user?: any;
  showRoleTestSwitcher?: boolean;
}

export default function MainLayout({ title, children }: MainLayoutProps) {
  const { props } = usePage<PageProps>();
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [currentUserRole, setCurrentUserRole] = useState<UserRole>(props.userRole || 'provider');

  // Get current path to determine active menu item
  const currentPath = window.location.pathname;

  // Check for test role in URL params or localStorage on mount
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const testRole = urlParams.get('test_role') as UserRole;
    const savedRole = localStorage.getItem('dev_test_role') as UserRole;

    // Priority: URL param > localStorage > props.userRole > default
    const effectiveRole = testRole || savedRole || props.userRole || 'provider';

    // Validate role
    const validRoles: UserRole[] = ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'];
    if (validRoles.includes(effectiveRole)) {
      setCurrentUserRole(effectiveRole);

      // Save to localStorage if it came from URL
      if (testRole) {
        localStorage.setItem('dev_test_role', testRole);
      }
    }
  }, [props.userRole]);

  const handleRoleChange = (newRole: UserRole) => {
    setCurrentUserRole(newRole);
  };

  const toggleSidebar = () => {
    setIsCollapsed(!isCollapsed);
  };

  const toggleMobileMenu = () => {
    setIsMobileMenuOpen(!isMobileMenuOpen);
  };

  return (
    <>
      <Head title={title} />
      <div className="flex min-h-screen bg-gray-100">
        {/* Mobile Menu Overlay */}
        {isMobileMenuOpen && (
          <div
            className="fixed inset-0 z-40 bg-black bg-opacity-50 md:hidden"
            onClick={() => setIsMobileMenuOpen(false)}
          />
        )}

        {/* Sidebar Navigation */}
        <div className={`
          fixed md:relative inset-y-0 left-0 z-50 bg-white shadow-xl border-r border-gray-200
          transition-all duration-300 ease-in-out transform
          ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}
          md:translate-x-0
          ${isCollapsed ? 'md:w-20' : 'md:w-72'}
        `}>
          <div className="flex flex-col h-full">
            {/* Brand Logo */}
            <div className={`flex items-center h-20 px-6 bg-white border-b border-gray-200 ${
              isCollapsed ? 'justify-center' : 'justify-between'
            }`}>
              <Link href="/" className="flex items-center">
                <img
                  src="/MSC-logo.png"
                  alt="MSC Wound Care"
                  className={`w-auto transition-all duration-300 ${
                    isCollapsed ? 'h-8' : 'h-12'
                  }`}
                />
              </Link>

              {/* Desktop Toggle Button */}
              <button
                onClick={toggleSidebar}
                className={`hidden md:flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors ${
                  isCollapsed ? 'ml-0' : 'ml-4'
                }`}
              >
                {isCollapsed ? (
                  <FiChevronRight className="w-4 h-4" />
                ) : (
                  <FiChevronLeft className="w-4 h-4" />
                )}
              </button>

              {/* Mobile Close Button */}
              <button
                onClick={toggleMobileMenu}
                className="md:hidden p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full"
              >
                <FiX className="w-5 h-5" />
              </button>
            </div>

            {/* Navigation Menu */}
            <RoleBasedNavigation
              userRole={currentUserRole}
              currentPath={currentPath}
              isCollapsed={isCollapsed}
            />

            {/* User Profile and Logout */}
            <div className="p-4 bg-gray-50 border-t border-gray-200">
              {!isCollapsed ? (
                <>
                  <div className="flex items-center mb-4 p-3 bg-white rounded-lg shadow-sm">
                    <div className="flex-shrink-0">
                      <img
                        className="w-10 h-10 rounded-full ring-2 ring-blue-500 ring-opacity-20"
                        src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                        alt="User profile"
                      />
                    </div>
                    <div className="ml-3 flex-1 min-w-0">
                      <p className="text-sm font-semibold text-gray-900 truncate">John Doe</p>
                      <p className="text-xs text-gray-500 truncate">Administrator</p>
                    </div>
                  </div>

                  {/* Logout Button */}
                  <Link
                    href={route('logout')}
                    method="delete"
                    as="button"
                    className="flex items-center w-full px-4 py-3 text-sm font-medium text-gray-700 rounded-lg transition-all duration-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-20"
                  >
                    <FiLogOut className="flex-shrink-0 w-5 h-5 mr-3 text-gray-500 group-hover:text-red-600" />
                    <span>Sign Out</span>
                  </Link>
                </>
              ) : (
                <div className="flex flex-col items-center space-y-3">
                  {/* Collapsed User Avatar */}
                  <img
                    className="w-8 h-8 rounded-full ring-2 ring-blue-500 ring-opacity-20"
                    src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                    alt="User profile"
                    title="John Doe - Administrator"
                  />

                  {/* Collapsed Logout Button */}
                  <Link
                    href={route('logout')}
                    method="delete"
                    as="button"
                    className="flex items-center justify-center w-8 h-8 text-gray-700 rounded-lg transition-all duration-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-20"
                    title="Sign Out"
                  >
                    <FiLogOut className="w-4 h-4" />
                  </Link>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Main Content Area */}
        <div className="flex-1 flex flex-col overflow-hidden">
          {/* Top Header Bar for Mobile */}
          <div className="md:hidden bg-white shadow-sm border-b border-gray-200 px-4 py-3">
            <div className="flex items-center justify-between">
              <img
                src="/MSC-logo.png"
                alt="MSC Wound Care"
                className="h-8 w-auto"
              />
              <button
                onClick={toggleMobileMenu}
                className="p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100"
              >
                <FiMenu className="w-6 h-6" />
              </button>
            </div>
          </div>

          {/* Flash Messages */}
          <div className="flex-shrink-0">
            <FlashMessages />
          </div>

          {/* Page Content */}
          <main className="flex-1 overflow-y-auto bg-gray-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
              <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                {children}
              </div>
            </div>
          </main>
        </div>

        {/* Global Role Test Switcher (Development and Staging) */}
        {props.showRoleTestSwitcher && (
          <RoleTestSwitcher
            currentRole={currentUserRole}
            onRoleChange={handleRoleChange}
          />
        )}
      </div>
    </>
  );
}
