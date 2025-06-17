import React, { useState, useEffect, useRef } from 'react';
import { FiSearch, FiX, FiChevronDown } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Payer {
  name: string;
  payer_id: string;
  display: string;
}

interface PayerSearchInputProps {
  value: {
    name: string;
    id: string;
  };
  onChange: (payer: { name: string; id: string }) => void;
  placeholder?: string;
  error?: string;
  required?: boolean;
}

export default function PayerSearchInput({ 
  value, 
  onChange, 
  placeholder = "Search by payer name or ID...",
  error,
  required = false
}: PayerSearchInputProps) {
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
  
  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState(value.name || '');
  
  // Update search query when value changes (e.g., from Azure auto-fill)
  useEffect(() => {
    if (value.name && value.name !== searchQuery) {
      setSearchQuery(value.name);
    }
  }, [value.name]);
  const [payers, setPayers] = useState<Payer[]>([]);
  const [loading, setLoading] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  
  const dropdownRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout>();
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);
  
  // Search payers
  const searchPayers = async (query: string) => {
    setLoading(true);
    try {
      const response = await fetch(`/api/payers/search?q=${encodeURIComponent(query)}&limit=50`);
      const data = await response.json();
      setPayers(data.data || []);
      setHighlightedIndex(0);
    } catch (error) {
      console.error('Error searching payers:', error);
      setPayers([]);
    } finally {
      setLoading(false);
    }
  };
  
  // Debounced search
  useEffect(() => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }
    
    if (searchQuery || isOpen) {
      debounceTimerRef.current = setTimeout(() => {
        searchPayers(searchQuery);
      }, 300);
    }
    
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [searchQuery, isOpen]);
  
  // Handle keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen) {
      if (e.key === 'ArrowDown' || e.key === 'Enter') {
        setIsOpen(true);
      }
      return;
    }
    
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev < payers.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => prev > 0 ? prev - 1 : 0);
        break;
      case 'Enter':
        e.preventDefault();
        if (payers[highlightedIndex]) {
          selectPayer(payers[highlightedIndex]);
        }
        break;
      case 'Escape':
        setIsOpen(false);
        break;
    }
  };
  
  const selectPayer = (payer: Payer) => {
    onChange({
      name: payer.name,
      id: payer.payer_id
    });
    setSearchQuery(payer.name);
    setIsOpen(false);
  };
  
  const clearSelection = () => {
    onChange({ name: '', id: '' });
    setSearchQuery('');
    inputRef.current?.focus();
  };
  
  return (
    <div ref={dropdownRef} className="relative">
      <div className="relative">
        <input
          ref={inputRef}
          type="text"
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            if (!isOpen) setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          className={cn(
            "w-full pl-10 pr-8",
            t.input.base,
            t.input.focus,
            error && "border-red-500"
          )}
          required={required}
        />
        
        <FiSearch className={cn("absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4", t.text.muted)} />
        
        <div className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
          {value.name && (
            <button
              type="button"
              onClick={clearSelection}
              className={cn(
                "p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700",
                t.text.secondary
              )}
            >
              <FiX className="h-3 w-3" />
            </button>
          )}
          <FiChevronDown className={cn(
            "h-4 w-4 transition-transform",
            isOpen && "rotate-180",
            t.text.muted
          )} />
        </div>
      </div>
      
      {/* Display selected payer ID */}
      {value.id && (
        <p className={cn("text-xs mt-1", t.text.secondary)}>
          Payer ID: {value.id}
        </p>
      )}
      
      {/* Dropdown */}
      {isOpen && (
        <div className={cn(
          "absolute z-50 w-full mt-1 rounded-lg shadow-lg overflow-hidden",
          t.glass.card,
          "max-h-96 overflow-y-auto"
        )}>
          {loading ? (
            <div className={cn("p-3 text-center", t.text.secondary)}>
              <div className="inline-flex items-center">
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-500 mr-2"></div>
                Searching...
              </div>
            </div>
          ) : payers.length === 0 ? (
            <div className={cn("p-3 text-center", t.text.secondary)}>
              No payers found
            </div>
          ) : (
            <div>
              {payers.length === 50 && (
                <div className={cn("px-3 py-2 text-xs sticky top-0", t.glass.panel, t.text.secondary)}>
                  Showing first 50 results. Type more to refine search.
                </div>
              )}
              <ul className="py-1">
              {payers.map((payer, index) => (
                <li key={`${payer.payer_id}-${index}`}>
                  <button
                    type="button"
                    onClick={() => selectPayer(payer)}
                    className={cn(
                      "w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800",
                      highlightedIndex === index && "bg-gray-100 dark:bg-gray-800"
                    )}
                  >
                    <div className={cn("font-medium text-sm", t.text.primary)}>
                      {payer.name}
                    </div>
                    <div className={cn("text-xs", t.text.secondary)}>
                      ID: {payer.payer_id}
                    </div>
                  </button>
                </li>
              ))}
            </ul>
            </div>
          )}
        </div>
      )}
      
      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}
    </div>
  );
}