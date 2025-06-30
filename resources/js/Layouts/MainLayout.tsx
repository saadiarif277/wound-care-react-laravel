import { Head, Link, usePage } from '@inertiajs/react';
import FlashMessages from '@/Components/Messages/FlashMessages';
import RoleBasedNavigation from '@/Components/Navigation/RoleBasedNavigation';
import { UserRole } from '@/types/roles';
import { getRoleDisplayName } from '@/lib/roleUtils';
import { AIOverlay, FloatingAIButton } from '@/Components/GhostAiUi';
import { Toaster } from '@/Components/GhostAiUi/ui/toaster';
import React, { useEffect } from 'react';
import {
  FiLogOut,
  FiMenu,
  FiHelpCircle,
  FiX,
  FiChevronLeft,
  FiChevronRight,
} from 'react-icons/fi';
import { ThemeProvider, useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { ThemeToggleCompact } from '@/Components/ThemeToggle';

interface MainLayoutProps {
  title?: string;
  children: React.ReactNode;
}

interface PageProps extends Record<string, unknown> {
  userRole?: UserRole;
  permissions?: string[];
  user?: any;
  auth?: {
    user?: {
      id: number;
      first_name: string;
      last_name: string;
      email: string;
      photo?: string;
    };
  };
}

// Inner component that uses theme
function ThemedLayout({ title, children }: MainLayoutProps) {
  const { theme } = useTheme();
  const { props } = usePage<PageProps>();
  const [isCollapsed, setIsCollapsed] = React.useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = React.useState(false);
  const [currentUserRole, setCurrentUserRole] = React.useState<UserRole>(props.userRole || 'provider');
  const [isAIOverlayVisible, setIsAIOverlayVisible] = React.useState(false);

  const currentPath = window.location.pathname;
  const user = props.auth?.user;
  const userName = user ? `${user.first_name} ${user.last_name}` : 'User';
  const roleDisplayName = currentUserRole ? getRoleDisplayName(currentUserRole) : 'User';
  const t = themes[theme]; // Current theme

  React.useEffect(() => {
    if (props.userRole) setCurrentUserRole(props.userRole);
  }, [props.userRole]);

  const toggleSidebar = () => setIsCollapsed(!isCollapsed);
  const toggleMobileMenu = () => setIsMobileMenuOpen(!isMobileMenuOpen);

  return (
    <>
      <Head title={title} />
      <div className={cn(
        "flex min-h-screen relative custom-scrollbar",
        theme === 'dark'
          ? 'bg-gradient-to-br from-[#0a0f1c] via-[#121829] to-[#1a1f2e]'
          : t.background.base
      )}>
        {/* Mobile Menu Overlay */}
        {isMobileMenuOpen && (
          <div
            className={cn(
              "fixed inset-0 z-40 md:hidden",
              theme === 'dark' ? 'bg-black/60 backdrop-blur-sm' : 'bg-gray-900/30 backdrop-blur-sm'
            )}
            onClick={() => setIsMobileMenuOpen(false)}
          />
        )}

        {/* Sidebar Navigation */}
        <div
          className={cn(
<<<<<<< HEAD
            "fixed md:relative inset-y-0 left-0 z-50",
            "transition-all duration-300 ease-in-out transform",
            "m-4 md:m-4 md:my-4 md:ml-4 md:mr-0",
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            "md:translate-x-0",
=======
            "fixed md:sticky inset-y-0 left-0 z-50",
            "transition-all duration-300 ease-in-out transform",
            "m-4 md:m-4 md:my-4 md:ml-4 md:mr-0",
            "h-screen md:h-[calc(100vh-2rem)]", // Fixed height that accounts for margin
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            "md:translate-x-0 md:top-4", // Add top positioning for sticky
>>>>>>> origin/provider-side
            isCollapsed ? 'md:w-20' : 'md:w-72',
            theme === 'dark'
              ? `${t.glass.card} ${t.glass.border} ${t.shadows.glass}`
              : `${t.glass.card} ${t.glass.border} ${t.shadows.glass}`
          )}
        >
<<<<<<< HEAD
          <div className="flex flex-col h-full">
=======
          <div className="flex flex-col h-full max-h-full overflow-hidden">
>>>>>>> origin/provider-side
            {/* Brand Logo */}
            <div
              className={cn(
                "flex items-center px-6 py-5 rounded-t-2xl border-b backdrop-blur-xl",
                theme === 'dark'
                  ? `${t.glass.frost} border-white/10`
                  : 'bg-white/70 border-gray-200/60',
                isCollapsed ? 'justify-center' : 'justify-between'
              )}
            >
              <Link href="/" className="flex items-center">
                <img
                  src="/MSC-logo.png"
                  alt="MSC Wound Care"
                  className={cn(
                    "w-auto transition-all duration-300",
                    isCollapsed ? 'h-8' : 'h-12'
                  )}
                />
              </Link>

              {/* Desktop Sidebar Toggle */}
              {!isCollapsed && (
                <button
                  onClick={toggleSidebar}
                  className={cn(
                    "hidden md:flex items-center justify-center w-8 h-8 rounded-full transition-all",
                    theme === 'dark'
                      ? `${t.text.secondary} ${t.glass.hover}`
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                  )}
                >
                  <FiChevronLeft className="w-4 h-4" />
                </button>
              )}

              {isCollapsed && (
                <button
                  onClick={toggleSidebar}
                  className={cn(
                    "hidden md:flex items-center justify-center w-8 h-8 rounded-full transition-all ml-2",
                    theme === 'dark'
                      ? `${t.text.secondary} ${t.glass.hover}`
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                  )}
                >
                  <FiChevronRight className="w-4 h-4" />
                </button>
              )}

              {/* Mobile Close Button */}
              <button
                onClick={toggleMobileMenu}
                className={cn(
                  "md:hidden p-2 rounded-full",
                  theme === 'dark'
                    ? `${t.text.secondary} ${t.glass.hover}`
                    : 'text-gray-600 hover:bg-gray-100'
                )}
              >
                <FiX className="w-5 h-5" />
              </button>
            </div>

<<<<<<< HEAD
            {/* Navigation Menu */}
            <RoleBasedNavigation
              userRole={currentUserRole}
              currentPath={currentPath}
              isCollapsed={isCollapsed}
              theme={theme}
            />

            {/* User Profile and Logout */}
            <div className={cn(
              "p-4 border-t",
=======
            {/* Navigation Menu - Scrollable middle section */}
            <div className="flex-1 overflow-y-auto custom-scrollbar">
              <RoleBasedNavigation
                userRole={currentUserRole}
                currentPath={currentPath}
                isCollapsed={isCollapsed}
                theme={theme}
              />
            </div>

            {/* User Profile and Logout - Always visible at bottom */}
            <div className={cn(
              "flex-shrink-0 p-4 border-t mt-auto", // Added flex-shrink-0 and mt-auto
>>>>>>> origin/provider-side
              theme === 'dark' ? 'border-white/10' : 'border-gray-200'
            )}>
              {!isCollapsed ? (
                <>
                  <div className={cn(
                    "flex items-center mb-4 p-3 rounded-xl backdrop-blur-md",
                    theme === 'dark'
                      ? `${t.glass.base} ${t.glass.border}`
                      : 'bg-white/60 border border-gray-200/50 shadow-sm'
                  )}>
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 rounded-full ring-2 ring-blue-500/50 bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center">
                        {user?.photo ? (
                          <img
                            className="w-10 h-10 rounded-full"
                            src={user.photo}
                            alt="User profile"
                          />
                        ) : (
                          <span className="text-sm font-medium text-white">
                            {userName.split(' ').map(n => n[0]).join('').slice(0, 2)}
                          </span>
                        )}
                      </div>
                    </div>
                    <div className="ml-3 flex-1 min-w-0">
                      <p className={cn("text-sm font-semibold truncate", t.text.primary)}>{userName}</p>
                      <p className={cn("text-xs truncate", t.text.secondary)}>{roleDisplayName}</p>
                    </div>
                    <ThemeToggleCompact className="ml-2" />
                  </div>

                  {/* Help & Support Button */}
                  <button
                    onClick={() => {
                      // Open help/support - could be a modal, external link, or page
                      window.open('mailto:support@mschealthcare.com', '_blank');
                    }}
                    className={cn(
                      "flex items-center w-full px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200 mb-2",
                      "focus:outline-none focus:ring-2 focus:ring-blue-500/50",
                      theme === 'dark'
                        ? `${t.glass.base} ${t.glass.border} ${t.glass.hover} backdrop-blur-md text-blue-400 hover:text-blue-300`
                        : 'bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 shadow-sm'
                    )}
                  >
                    <FiHelpCircle className="flex-shrink-0 w-5 h-5 mr-3" />
                    <span>Help & Support</span>
                  </button>

                  {/* Logout Button */}
                  <button
                    onClick={async () => {
                      try {
                        const response = await fetch(route('logout'), {
                          method: 'DELETE',
                          headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                          },
                          credentials: 'same-origin',
                        });

                        if (response.ok) {
                          // Clear any local storage or session storage
                          localStorage.clear();
                          sessionStorage.clear();

                          // Redirect to login page
                          window.location.href = '/login';
                        } else {
                          console.error('Logout failed:', response.statusText);
                          // Force redirect anyway
                          window.location.href = '/login';
                        }
                      } catch (error) {
                        console.error('Logout error:', error);
                        // Force redirect on error
                        window.location.href = '/login';
                      }
                    }}
                    className={cn(
                      "flex items-center w-full px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200",
                      "focus:outline-none focus:ring-2 focus:ring-red-500/50",
                      theme === 'dark'
                        ? `${t.button.danger.base} ${t.button.danger.hover} backdrop-blur-md`
                        : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 shadow-sm'
                    )}
                  >
                    <FiLogOut className="flex-shrink-0 w-5 h-5 mr-3" />
                    <span>Sign Out</span>
                  </button>
                </>
              ) : (
                <div className="flex flex-col items-center space-y-3">
                  {/* Collapsed User Avatar */}
                  <div
                    className="w-8 h-8 rounded-full ring-2 ring-blue-500/50 bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center"
                    title={`${userName} - ${roleDisplayName}`}
                  >
                    {user?.photo ? (
                      <img
                        className="w-8 h-8 rounded-full"
                        src={user.photo}
                        alt="User profile"
                      />
                    ) : (
                      <span className="text-xs font-medium text-white">
                        {userName.split(' ').map(n => n[0]).join('').slice(0, 2)}
                      </span>
                    )}
                  </div>

                  {/* Theme Toggle in Collapsed State */}
                  <ThemeToggleCompact />

                  {/* Collapsed Help & Support Button */}
                  <button
                    onClick={() => {
                      window.open('mailto:support@mschealthcare.com', '_blank');
                    }}
                    className={cn(
                      "flex items-center justify-center w-8 h-8 rounded-xl transition-all duration-200",
                      "focus:outline-none focus:ring-2 focus:ring-blue-500/50",
                      theme === 'dark'
                        ? `${t.glass.base} ${t.glass.border} ${t.glass.hover} text-blue-400`
                        : 'bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200'
                    )}
                    title="Help & Support"
                  >
                    <FiHelpCircle className="w-4 h-4" />
                  </button>

                  {/* Collapsed Logout Button */}
                  <button
                    onClick={async () => {
                      try {
                        const response = await fetch(route('logout'), {
                          method: 'DELETE',
                          headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                          },
                          credentials: 'same-origin',
                        });

                        if (response.ok) {
                          // Clear any local storage or session storage
                          localStorage.clear();
                          sessionStorage.clear();

                          // Redirect to login page
                          window.location.href = '/login';
                        } else {
                          console.error('Logout failed:', response.statusText);
                          // Force redirect anyway
                          window.location.href = '/login';
                        }
                      } catch (error) {
                        console.error('Logout error:', error);
                        // Force redirect on error
                        window.location.href = '/login';
                      }
                    }}
                    className={cn(
                      "flex items-center justify-center w-8 h-8 rounded-xl transition-all duration-200",
                      "focus:outline-none focus:ring-2 focus:ring-red-500/50",
                      theme === 'dark'
                        ? `${t.button.danger.base} ${t.button.danger.hover}`
                        : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'
                    )}
                    title="Sign Out"
                  >
                    <FiLogOut className="w-4 h-4" />
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Main Content Area */}
        <div className="flex-1 flex flex-col">
          {/* Top Header Bar for Mobile */}
          <div className={cn(
            "md:hidden px-4 py-3 border-b",
            theme === 'dark'
              ? `${t.glass.base} border-white/10`
              : 'bg-white/80 backdrop-blur-md border-gray-200'
          )}>
            <div className="flex items-center justify-between">
              <img
                src="/MSC-logo.png"
                alt="MSC Wound Care"
                className="h-8 w-auto"
              />
              <button
                onClick={toggleMobileMenu}
                className={cn(
                  "p-2 rounded-md",
                  theme === 'dark'
                    ? `${t.text.secondary} ${t.glass.hover}`
                    : 'text-gray-600 hover:bg-gray-100'
                )}
              >
                <FiMenu className="w-6 h-6" />
              </button>
            </div>
          </div>

          {/* Page Content */}
          <main className={cn(
            "flex-1 overflow-y-auto",
            theme === 'dark' ? 'bg-transparent' : 'bg-white/30 backdrop-blur-sm'
          )}>
            <div className={cn(
              "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:pt-20",
              theme === 'light' && '[&_.card]:bg-white [&_.card]:shadow-sm [&_.card]:border-gray-200'
            )}>
              {children}
            </div>
          </main>
        </div>

        <FlashMessages />

        {/* AI Overlay System */}
        <FloatingAIButton onClick={() => setIsAIOverlayVisible(true)} />
        <AIOverlay
          isVisible={isAIOverlayVisible}
          onClose={() => setIsAIOverlayVisible(false)}
        />

        {/* Toast System */}
        <Toaster />
      </div>
    </>
  );
}

// Main export - ThemeProvider is now at app level
export default function MainLayout(props: MainLayoutProps) {
  return <ThemedLayout {...props} />;
}
