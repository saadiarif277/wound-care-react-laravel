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
  FiX,
  FiChevronLeft,
  FiChevronRight,
} from 'react-icons/fi';
import { ThemeProvider, useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { ThemeToggleCompact } from '@/components/ThemeToggle';

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
            "fixed md:relative inset-y-0 left-0 z-50",
            "transition-all duration-300 ease-in-out transform",
            "m-4 md:m-4 md:my-4 md:ml-4 md:mr-0",
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            "md:translate-x-0",
            isCollapsed ? 'md:w-20' : 'md:w-72',
            theme === 'dark' ? t.navigation.container : cn(t.navigation.container, t.shadows.glass)
          )}
        >
          <div className="flex flex-col h-full">
            {/* Brand Logo */}
            <div
              className={cn(
                "flex items-center px-6 py-5 rounded-t-2xl border-b",
                theme === 'dark' ? `${t.glass.frost} border-white/10` : 'bg-white/90 border-gray-200',
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

              {/* Desktop Toggle Buttons */}
              <div className={cn(
                "hidden md:flex items-center space-x-2",
                isCollapsed ? 'ml-0' : 'ml-4'
              )}>
                {/* Theme Toggle */}
                <ThemeToggleCompact />

                {/* Sidebar Toggle */}
                <button
                  onClick={toggleSidebar}
                  className={cn(
                    "flex items-center justify-center w-8 h-8 rounded-full transition-all",
                    theme === 'dark'
                      ? `${t.text.secondary} ${t.glass.hover}`
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                  )}
                >
                  {isCollapsed ? (
                    <FiChevronRight className="w-4 h-4" />
                  ) : (
                    <FiChevronLeft className="w-4 h-4" />
                  )}
                </button>
              </div>

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
              theme === 'dark' ? 'border-white/10' : 'border-gray-200'
            )}>
              {!isCollapsed ? (
                <>
                  <div className={cn(
                    "flex items-center mb-4 p-3 rounded-lg",
                    theme === 'dark' ? t.glass.base : 'bg-gray-50 border border-gray-200'
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
                  </div>

                  {/* Logout Button */}
                  <button
                    onClick={async () => {
                      try {
                        await fetch(route('logout'), {
                          method: 'DELETE',
                          headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                          },
                          credentials: 'same-origin',
                        });
                      } catch (e) {}
                      window.location.href = '/login';
                    }}
                    className={cn(
                      "flex items-center w-full px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200",
                      "focus:outline-none focus:ring-2 focus:ring-red-500/50",
                      theme === 'dark' ? t.button.danger : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'
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

                  {/* Collapsed Logout Button */}
                  <button
                    onClick={async () => {
                      try {
                        await fetch(route('logout'), {
                          method: 'DELETE',
                          headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                          },
                          credentials: 'same-origin',
                        });
                      } catch (e) {}
                      window.location.href = '/login';
                    }}
                    className={cn(
                      "flex items-center justify-center w-8 h-8 rounded-lg transition-all duration-200",
                      "focus:outline-none focus:ring-2 focus:ring-red-500/50",
                      theme === 'dark' ? t.button.danger : 'bg-red-50 text-red-700 hover:bg-red-100'
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
              <div className="flex items-center space-x-2">
                <ThemeToggleCompact />
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

// Main export with theme provider
export default function MainLayout(props: MainLayoutProps) {
  return (
    <ThemeProvider>
      <ThemedLayout {...props} />
    </ThemeProvider>
  );
}
