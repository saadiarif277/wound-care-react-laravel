import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { ShieldOff, ArrowLeft, Home } from 'lucide-react';

interface Props {
  message?: string;
  requiredPermission?: string;
}

export default function NoAccess({ 
  message = "You don't have permission to access this feature.", 
  requiredPermission 
}: Props) {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  return (
    <MainLayout>
      <Head title="Access Denied | MSC Healthcare" />

      <div className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'} flex items-center justify-center p-6`}>
        <div className={`${t.glass.card} ${t.glass.border} p-8 max-w-md w-full text-center`}>
          <div className="mb-6">
            <div className="p-4 bg-red-500/20 rounded-full inline-block">
              <ShieldOff className="w-12 h-12 text-red-500" />
            </div>
          </div>

          <h1 className={`text-2xl font-bold ${t.text.primary} mb-4`}>Access Denied</h1>
          
          <p className={`${t.text.secondary} mb-6`}>
            {message}
          </p>

          {requiredPermission && (
            <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6`}>
              <p className={`text-sm ${t.text.muted}`}>
                Required permission: <span className="font-mono text-xs">{requiredPermission}</span>
              </p>
            </div>
          )}

          <div className="space-y-3">
            <button
              onClick={() => window.history.back()}
              className={`${t.button.secondary} w-full px-4 py-2 flex items-center justify-center space-x-2`}
            >
              <ArrowLeft className="w-4 h-4" />
              <span>Go Back</span>
            </button>

            <Link
              href={route('dashboard')}
              className={`${t.button.primary} w-full px-4 py-2 flex items-center justify-center space-x-2`}
            >
              <Home className="w-4 h-4" />
              <span>Go to Dashboard</span>
            </Link>
          </div>

          <p className={`text-sm ${t.text.muted} mt-6`}>
            If you believe you should have access to this feature, please contact your administrator.
          </p>
        </div>
      </div>
    </MainLayout>
  );
}