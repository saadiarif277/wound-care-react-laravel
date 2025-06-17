import React, { useState, useEffect, useRef } from 'react';
import { FiMapPin, FiX } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

// This component requires Google Places API
// Add to your .env file: VITE_GOOGLE_MAPS_API_KEY=your-api-key
// Add to your HTML: <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>

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

declare global {
  interface Window {
    google: any;
  }
}

export default function GoogleAddressAutocomplete({
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

  const inputRef = useRef<HTMLInputElement>(null);
  const autocompleteRef = useRef<any>(null);
  const [isGoogleLoaded, setIsGoogleLoaded] = useState(false);

  useEffect(() => {
    // Check if Google Maps is loaded
    const checkGoogleMaps = () => {
      if (window.google && window.google.maps && window.google.maps.places) {
        setIsGoogleLoaded(true);
        return true;
      }
      return false;
    };

    // Check immediately
    if (checkGoogleMaps()) return;

    // Set up an interval to check periodically
    const intervalId = setInterval(() => {
      if (checkGoogleMaps()) {
        clearInterval(intervalId);
      }
    }, 500);

    // Clean up after 10 seconds
    const timeoutId = setTimeout(() => {
      clearInterval(intervalId);
      console.warn('Google Maps API not loaded after 10 seconds. Address autocomplete will be disabled.');
    }, 10000);

    return () => {
      clearInterval(intervalId);
      clearTimeout(timeoutId);
    };
  }, []);

  useEffect(() => {
    if (!isGoogleLoaded || !inputRef.current || disabled) return;

    // Create autocomplete instance
    const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
      types: ['address'],
      componentRestrictions: { country: 'us' },
      fields: ['address_components', 'formatted_address']
    });

    autocompleteRef.current = autocomplete;

    // Handle place selection
    const placeChangedListener = autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      
      if (!place.address_components) {
        console.warn('No address components found');
        return;
      }

      // Parse address components
      let streetNumber = '';
      let streetName = '';
      let city = '';
      let state = '';
      let zip = '';

      place.address_components.forEach((component: any) => {
        const types = component.types;
        
        if (types.includes('street_number')) {
          streetNumber = component.long_name;
        } else if (types.includes('route')) {
          streetName = component.long_name;
        } else if (types.includes('locality')) {
          city = component.long_name;
        } else if (types.includes('administrative_area_level_1')) {
          state = component.short_name;
        } else if (types.includes('postal_code')) {
          zip = component.long_name;
        }
      });

      // Update form
      onChange({
        line1: `${streetNumber} ${streetName}`.trim(),
        line2: value.line2 || '',
        city,
        state,
        zip
      });
    });

    // Cleanup
    return () => {
      if (placeChangedListener) {
        window.google.maps.event.removeListener(placeChangedListener);
      }
    };
  }, [isGoogleLoaded, disabled, onChange]);

  // Clear the address
  const clearAddress = () => {
    onChange({
      line1: '',
      line2: '',
      city: '',
      state: '',
      zip: ''
    });
    if (inputRef.current) {
      inputRef.current.value = '';
    }
  };

  return (
    <div className="relative">
      <div className="relative">
        <FiMapPin className={cn("absolute left-3 top-3 h-4 w-4", t.text.muted)} />
        <input
          ref={inputRef}
          type="text"
          defaultValue={value.line1}
          placeholder={placeholder}
          disabled={disabled || !isGoogleLoaded}
          required={required}
          className={cn(
            "w-full pl-10 pr-8",
            t.input.base,
            t.input.focus,
            error && "border-red-500",
            disabled && "opacity-50 cursor-not-allowed"
          )}
        />
        
        {value.line1 && !disabled && (
          <button
            type="button"
            onClick={clearAddress}
            className={cn(
              "absolute right-3 top-3 p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700",
              t.text.secondary
            )}
          >
            <FiX className="h-3 w-3" />
          </button>
        )}
      </div>

      {!isGoogleLoaded && !disabled && (
        <p className={cn("mt-1 text-xs", t.text.secondary)}>
          Google Maps API not loaded. Manual entry only.
        </p>
      )}

      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}
    </div>
  );
}