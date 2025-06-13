import React from 'react';
import { Sun, Moon } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';

const ThemeToggle: React.FC<{ className?: string }> = ({ className }) => {
  const { theme, toggleTheme } = useTheme();
  const Icon = theme === 'dark' ? Sun : Moon;

  return (
    <button
      type="button"
      aria-label="Toggle theme"
      onClick={toggleTheme}
      className={`p-2 rounded-full hover:bg-white/10 dark:hover:bg-slate-700 transition-colors ${className || ''}`}
    >
      <Icon className="h-5 w-5 text-msc-blue-500 dark:text-msc-red-500" />
    </button>
  );
};

export default ThemeToggle;
