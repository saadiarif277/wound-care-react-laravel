import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Props {
  debugData: any;
}

export default function DebugFacilities({ debugData }: Props) {
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  return (
    <MainLayout>
      <Head title="Debug Facilities" />
      
      <div className="min-h-screen">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <h1 className={cn("text-3xl font-bold mb-8", t.text.primary)}>
            Debug Facilities for Quick Request
          </h1>

          <div className={cn("p-6 rounded-xl mb-6", t.glass.panel)}>
            <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
              Current User
            </h2>
            <pre className={cn("p-4 rounded-lg overflow-x-auto text-sm", 
              theme === 'dark' ? 'bg-black/30' : 'bg-gray-100'
            )}>
              {JSON.stringify(debugData.user, null, 2)}
            </pre>
          </div>

          <div className={cn("p-6 rounded-xl mb-6", t.glass.panel)}>
            <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
              getUserFacilities Result
            </h2>
            <p className={cn("mb-2", t.text.secondary)}>
              Count: {debugData.getUserFacilities_result?.count || 0}
            </p>
            <pre className={cn("p-4 rounded-lg overflow-x-auto text-sm", 
              theme === 'dark' ? 'bg-black/30' : 'bg-gray-100'
            )}>
              {JSON.stringify(debugData.getUserFacilities_result, null, 2)}
            </pre>
          </div>

          {Object.entries(debugData.facility_queries || {}).map(([key, value]: [string, any]) => (
            <div key={key} className={cn("p-6 rounded-xl mb-6", t.glass.panel)}>
              <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
                {key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
              </h2>
              <p className={cn("mb-2", t.text.secondary)}>
                Count: {value.count}
              </p>
              <pre className={cn("p-4 rounded-lg overflow-x-auto text-sm", 
                theme === 'dark' ? 'bg-black/30' : 'bg-gray-100'
              )}>
                {JSON.stringify(value, null, 2)}
              </pre>
            </div>
          ))}

          <div className={cn("p-6 rounded-xl mb-6", t.glass.panel)}>
            <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
              Facility User Table Records
            </h2>
            <pre className={cn("p-4 rounded-lg overflow-x-auto text-sm", 
              theme === 'dark' ? 'bg-black/30' : 'bg-gray-100'
            )}>
              {JSON.stringify(debugData.facility_user_table, null, 2)}
            </pre>
          </div>

          <div className={cn("p-6 rounded-xl", t.glass.panel)}>
            <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
              Recommendations
            </h2>
            <div className={cn("space-y-2", t.text.secondary)}>
              {debugData.getUserFacilities_result?.count === 0 && (
                <>
                  <p>• No facilities are available for this user</p>
                  {debugData.user?.roles?.includes('provider') && (
                    <p>• Provider needs to be associated with facilities in the facility_user table</p>
                  )}
                  {debugData.user?.roles?.includes('office-manager') && (
                    <p>• Office manager needs to be associated with facilities</p>
                  )}
                  {!debugData.user?.current_organization_id && (
                    <p>• User has no current_organization_id set</p>
                  )}
                </>
              )}
              {debugData.facility_queries?.all_facilities?.count === 0 && (
                <p>• No facilities exist in the database. Run seeders to create test data.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}