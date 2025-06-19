import React, { useState, useEffect, useRef } from 'react';
import { FiMapPin, FiX } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface AddressSuggestion {
  place_id: string;
  display_name: string;
  lat: string;
  lon: string;
  address?: {
    house_number?: string;
    road?: string;
    city?: string;
    town?: string;
    village?: string;
    state?: string;
    postcode?: string;
    country?: string;
  };
}

interface AddressAutocompleteProps {
  value: {
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
  };
  onChange: (address: {
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
  }) => void;
  placeholder?: string;
  required?: boolean;
  error?: string;
  disabled?: boolean;
}

export default function AddressAutocomplete({
  value,
  onChange,
  placeholder = "Start typing an address...",
  required = false,
  error,
  disabled = false
}: AddressAutocompleteProps) {
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

  const [searchQuery, setSearchQuery] = useState(value.line1 || '');
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(0);
  
  const dropdownRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowSuggestions(false);
      }
    };
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Search for addresses using Nominatim
  const searchAddresses = async (query: string) => {
    if (query.length < 3) {
      setSuggestions([]);
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/search?` +
        `format=json&` +
        `q=${encodeURIComponent(query)}&` +
        `countrycodes=us&` + // Limit to US addresses
        `addressdetails=1&` +
        `limit=5`,
        {
          headers: {
            'Accept': 'application/json',
            'User-Agent': 'MSC-Wound-Portal/1.0' // Required by Nominatim
          }
        }
      );
      
      if (response.ok) {
        const data = await response.json();
        setSuggestions(data);
        setSelectedIndex(0);
      }
    } catch (error) {
      console.error('Error fetching addresses:', error);
      setSuggestions([]);
    } finally {
      setIsLoading(false);
    }
  };

  // Debounced search
  useEffect(() => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }
    
    if (searchQuery && searchQuery !== value.line1) {
      debounceTimerRef.current = setTimeout(() => {
        searchAddresses(searchQuery);
      }, 300);
    }
    
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [searchQuery]);

  // Handle keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!showSuggestions || suggestions.length === 0) {
      if (e.key === 'ArrowDown' && searchQuery) {
        setShowSuggestions(true);
      }
      return;
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev => 
          prev < suggestions.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev => prev > 0 ? prev - 1 : 0);
        break;
      case 'Enter':
        e.preventDefault();
        if (suggestions[selectedIndex]) {
          selectAddress(suggestions[selectedIndex]);
        }
        break;
      case 'Escape':
        setShowSuggestions(false);
        break;
    }
  };

  // Select an address from suggestions
  const selectAddress = (suggestion: AddressSuggestion) => {
    const addr = suggestion.address || {};
    
    // Build street address
    let streetAddress = '';
    if (addr.house_number) {
      streetAddress = addr.house_number;
    }
    if (addr.road) {
      streetAddress += (streetAddress ? ' ' : '') + addr.road;
    }
    
    // Determine city (try different fields)
    const city = addr.city || addr.town || addr.village || '';
    
    // Update the form with parsed address
    onChange({
      line1: streetAddress || suggestion.display_name.split(',')[0],
      line2: value.line2 || '',
      city: city,
      state: addr.state || '',
      zip: addr.postcode || ''
    });
    
    setSearchQuery(streetAddress || suggestion.display_name.split(',')[0]);
    setShowSuggestions(false);
  };

  // Clear the address
  const clearAddress = () => {
    onChange({
      line1: '',
      line2: '',
      city: '',
      state: '',
      zip: ''
    });
    setSearchQuery('');
    inputRef.current?.focus();
  };

  return (
    <div ref={dropdownRef} className="relative">
      <div className="relative">
        <FiMapPin className={cn("absolute left-3 top-3 h-4 w-4", t.text.muted)} />
        <input
          ref={inputRef}
          type="text"
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            onChange({ ...value, line1: e.target.value });
            if (!showSuggestions) setShowSuggestions(true);
          }}
          onFocus={() => setShowSuggestions(true)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          required={required}
          className={cn(
            "w-full pl-10 pr-8",
            t.input.base,
            t.input.focus,
            error && "border-red-500",
            disabled && "opacity-50 cursor-not-allowed"
          )}
        />
        
        {searchQuery && !disabled && (
          <button
            type="button"
            onClick={clearAddress}
            title="Clear Address"
            className={cn(
              "absolute right-3 top-3 p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700",
              t.text.secondary
            )}
          >
            <FiX className="h-3 w-3" />
          </button>
        )}
      </div>

      {/* Suggestions dropdown */}
      {showSuggestions && !disabled && (
        <div className={cn(
          "absolute z-50 w-full mt-1 rounded-lg shadow-lg overflow-hidden",
          t.glass.card,
          "max-h-64 overflow-y-auto"
        )}>
          {isLoading ? (
            <div className={cn("p-3 text-center", t.text.secondary)}>
              <div className="inline-flex items-center">
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-500 mr-2"></div>
                Searching addresses...
              </div>
            </div>
          ) : suggestions.length === 0 && searchQuery.length >= 3 ? (
            <div className={cn("p-3 text-center", t.text.secondary)}>
              No addresses found
            </div>
          ) : suggestions.length > 0 ? (
            <ul className="py-1">
              {suggestions.map((suggestion, index) => (
                <li key={suggestion.place_id}>
                  <button
                    type="button"
                    onClick={() => selectAddress(suggestion)}
                    className={cn(
                      "w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800",
                      selectedIndex === index && "bg-gray-100 dark:bg-gray-800"
                    )}
                  >
                    <div className="flex items-start">
                      <FiMapPin className={cn("h-4 w-4 mt-0.5 mr-2 flex-shrink-0", t.text.secondary)} />
                      <div>
                        <div className={cn("text-sm font-medium", t.text.primary)}>
                          {suggestion.display_name.split(',')[0]}
                        </div>
                        <div className={cn("text-xs", t.text.secondary)}>
                          {suggestion.display_name.split(',').slice(1).join(',').trim()}
                        </div>
                      </div>
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          ) : searchQuery.length < 3 ? (
            <div className={cn("p-3 text-xs text-center", t.text.secondary)}>
              Type at least 3 characters to search
            </div>
          ) : null}
        </div>
      )}

      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}
    </div>
  );
}