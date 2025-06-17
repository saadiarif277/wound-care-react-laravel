import React, { useState } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

export default function TestInsuranceCard() {
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

  const [status, setStatus] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  const checkStatus = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/insurance-card/status');
      const data = await response.json();
      setStatus(data);
    } catch (error) {
      setStatus({ error: error.message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-6">
      <h2 className={cn("text-xl font-bold mb-4", t.text.primary)}>
        Azure Document Intelligence Status Check
      </h2>
      
      <button
        onClick={checkStatus}
        disabled={loading}
        className={cn(
          "px-4 py-2 rounded-lg",
          "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
        )}
      >
        {loading ? 'Checking...' : 'Check Status'}
      </button>

      {status && (
        <div className={cn("mt-4 p-4 rounded-lg", t.glass.panel)}>
          <pre className={cn("text-sm", t.text.secondary)}>
            {JSON.stringify(status, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}