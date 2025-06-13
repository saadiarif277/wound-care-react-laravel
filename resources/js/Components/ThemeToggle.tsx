import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { FiSun, FiMoon } from 'react-icons/fi';

interface ThemeToggleProps {
  className?: string;
  showLabel?: boolean;
}

export const ThemeToggle: React.FC<ThemeToggleProps> = ({ className = '', showLabel = false }) => {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {showLabel && (
        <span className={`text-sm font-medium ${isDark ? 'text-white/75' : 'text-gray-700'}`}>
          {isDark ? 'Dark' : 'Light'}
        </span>
      )}
      
      <button
        onClick={toggleTheme}
        className={`
          relative w-14 h-7 rounded-full p-1 transition-all duration-300
          ${isDark ? 'bg-white/10 border border-white/20' : 'bg-gray-200 border border-gray-300'}
          hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2
          ${isDark ? 'focus:ring-white/30 focus:ring-offset-transparent' : 'focus:ring-gray-400 focus:ring-offset-white'}
        `}
        aria-label={`Switch to ${isDark ? 'light' : 'dark'} theme`}
      >
        <div 
          className={`
            absolute inset-1 w-5 h-5 rounded-full transition-all duration-300 flex items-center justify-center
            ${isDark ? 'translate-x-7 bg-white' : 'translate-x-0 bg-gray-700'}
          `}
        >
          {isDark ? (
            <FiMoon className="w-3 h-3 text-gray-900" />
          ) : (
            <FiSun className="w-3 h-3 text-white" />
          )}
        </div>
        
        {/* Background icons */}
        <div className="flex justify-between items-center w-full px-1">
          <FiSun className={`w-3 h-3 transition-opacity duration-300 ${isDark ? 'opacity-30 text-white/50' : 'opacity-0'}`} />
          <FiMoon className={`w-3 h-3 transition-opacity duration-300 ${isDark ? 'opacity-0' : 'opacity-30 text-gray-500'}`} />
        </div>
      </button>
    </div>
  );
};

// Compact version for tight spaces
export const ThemeToggleCompact: React.FC<{ className?: string }> = ({ className = '' }) => {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  return (
    <button
      onClick={toggleTheme}
      className={`
        p-2 rounded-lg transition-all duration-200
        ${isDark 
          ? 'text-white/75 hover:text-white hover:bg-white/10' 
          : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
        }
        focus:outline-none focus:ring-2 
        ${isDark ? 'focus:ring-white/30' : 'focus:ring-gray-400'}
        ${className}
      `}
      aria-label={`Switch to ${isDark ? 'light' : 'dark'} theme`}
    >
      {isDark ? (
        <FiSun className="w-5 h-5" />
      ) : (
        <FiMoon className="w-5 h-5" />
      )}
    </button>
  );
};