import React, { useState, useMemo, useRef, useEffect } from 'react';
import { FiSearch, FiX, FiChevronDown } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Option {
  value: string;
  label: string;
  suggestion?: boolean;
}

interface SearchableMultiSelectProps {
  options: Omit<Option, 'suggestion'>[];
  selected: string[];
  onChange: (selected: string[]) => void;
  label: string;
  placeholder?: string;
  searchPlaceholder?: string;
  className?: string;
  error?: boolean;
  required?: boolean;
  suggestedOptions?: string[];
}

export const SearchableMultiSelect: React.FC<SearchableMultiSelectProps> = ({
  options: initialOptions,
  selected,
  onChange,
  label,
  placeholder = 'Select options...',
  searchPlaceholder = 'Search...',
  className = '',
  error = false,
  required = false,
  suggestedOptions = [],
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const wrapperRef = useRef<HTMLDivElement>(null);

  const options = useMemo(() => {
    return initialOptions.map(opt => ({
        ...opt,
        suggestion: suggestedOptions.includes(opt.value)
    }));
  }, [initialOptions, suggestedOptions]);

  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback
  }

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setSearchTerm('');
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [wrapperRef]);

  const filteredOptions = useMemo(() => {
    if (!searchTerm) return options;
    const search = searchTerm.toLowerCase();
    return options.filter(
      option =>
        option.label.toLowerCase().includes(search) ||
        option.value.toLowerCase().includes(search)
    );
  }, [options, searchTerm]);

  const toggleOption = (value: string) => {
    const newSelected = selected.includes(value)
      ? selected.filter(item => item !== value)
      : [...selected, value];
    onChange(newSelected);
  };
  
  const selectedOptions = options.filter(opt => selected.includes(opt.value));

  return (
    <div className={`relative ${className}`}>
      <label className={cn("block text-sm font-medium mb-1", t.text.primary)}>
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      <div ref={wrapperRef}>
      <div
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          "w-full px-3 py-2 text-left rounded-md border transition-all duration-200 flex items-center justify-between cursor-pointer",
          theme === 'dark' 
            ? 'bg-gray-800 border-gray-700 text-white hover:border-gray-600' 
            : 'bg-white border-gray-300 text-gray-900 hover:border-gray-400',
          error && 'border-red-500',
          "focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2",
          theme === 'dark' && "focus:ring-offset-gray-900"
        )}
      >
        <div className="flex flex-wrap gap-1 items-center">
            {selectedOptions.length > 0 ? (
                selectedOptions.map(opt => (
                    <span key={opt.value} className={cn("inline-flex items-center px-2 py-0.5 rounded text-xs font-medium", t.background.base, t.text.primary)}>
                        {opt.label}
                        <button
                            type="button"
                            className="ml-1.5 flex-shrink-0 inline-flex items-center justify-center h-4 w-4 rounded-full text-indigo-400 hover:bg-indigo-200 hover:text-indigo-500 focus:outline-none focus:bg-indigo-500 focus:text-white"
                            onClick={(e) => {
                                e.stopPropagation();
                                toggleOption(opt.value);
                            }}
                        >
                            <span className="sr-only">Remove {opt.label}</span>
                            <FiX className="h-3 w-3" />
                        </button>
                    </span>
                ))
            ) : (
                <span className={cn("text-sm", t.text.tertiary)}>{placeholder}</span>
            )}
        </div>
        <FiChevronDown className={cn("ml-2 h-4 w-4 shrink-0", t.text.tertiary, isOpen && "transform rotate-180")} />
      </div>

      {isOpen && (
        <div className={cn(
          "absolute z-50 w-full mt-1 rounded-md shadow-lg max-h-96 overflow-hidden",
          theme === 'dark' ? 'bg-gray-800 border border-gray-700' : 'bg-white border border-gray-200'
        )}>
          <div className="p-2 border-b border-gray-200 dark:border-gray-700">
            <div className="relative">
              <FiSearch className={cn("absolute left-2 top-2.5 h-4 w-4", t.text.tertiary)} />
              <input
                type="text"
                placeholder={searchPlaceholder}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={cn(
                  "w-full pl-8 pr-3 py-2 rounded-md text-sm transition-all",
                  t.background.base,
                  t.text.primary,
                  t.background.base,
                  "focus:ring-blue-500 focus:border-blue-500"
                )}
                autoFocus
              />
            </div>
          </div>
          <ul className="py-1 overflow-y-auto max-h-60">
            {filteredOptions.map(option => (
              <li
                key={option.value}
                onClick={() => toggleOption(option.value)}
                className={cn(
                  "px-3 py-2 cursor-pointer flex items-center justify-between",
                  "hover:bg-gray-100 dark:hover:bg-gray-700",
                  selected.includes(option.value) && "bg-blue-500/10 text-blue-400"
                )}
              >
                <span className="flex items-center">
                    <input
                        type="checkbox"
                        checked={selected.includes(option.value)}
                        readOnly
                        className="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 mr-3"
                    />
                    {option.label}
                </span>
                {option.suggestion && (
                    <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500 text-white">
                        Suggested
                    </span>
                )}
              </li>
            ))}
            {filteredOptions.length === 0 && (
              <li className="px-3 py-2 text-sm text-gray-500">No results found.</li>
            )}
          </ul>
        </div>
      )}
      </div>
    </div>
  );
};
