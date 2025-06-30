import React, { useEffect, useRef, useState } from 'react';
import { FiMapPin } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface GoogleAddressAutocompleteWithFallbackProps {
  onPlaceSelect?: (place: google.maps.places.PlaceResult) => void;
  value?: string;
  onChange?: (value: string) => void;
  defaultValue?: string;
  className?: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
}

declare global {
  interface Window {
    google: any;
    googleMapsLoaded?: boolean;
    googleMapsCallbacks?: (() => void)[];
  }
}

export default function GoogleAddressAutocompleteWithFallback({
  onPlaceSelect,
  value,
  onChange,
  defaultValue = '',
  className = '',
  placeholder = "Start typing an address...",
  required = false,
  disabled = false
}: GoogleAddressAutocompleteWithFallbackProps) {
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

  const [isGoogleMapsAvailable, setIsGoogleMapsAvailable] = useState(false);
  const [isChecking, setIsChecking] = useState(true);
  const inputRef = useRef<HTMLInputElement>(null);
  const autocompleteRef = useRef<any>(null);
  const [inputValue, setInputValue] = useState(value || defaultValue || '');

  useEffect(() => {
    if (disabled) {
      setIsChecking(false);
      return;
    }

    let mounted = true;
    let checkAttempts = 0;
    const maxAttempts = 10; // Check for 5 seconds max

    const checkGoogleMaps = () => {
      if (!mounted) return;
      
      checkAttempts++;
      
      if (window.google?.maps?.places?.Autocomplete) {
        console.log('Google Maps is available');
        if (mounted) {
          setIsGoogleMapsAvailable(true);
          setIsChecking(false);
          window.googleMapsLoaded = true;
          
          // Call any waiting callbacks
          if (window.googleMapsCallbacks?.length) {
            window.googleMapsCallbacks.forEach(cb => cb());
            window.googleMapsCallbacks = [];
          }
        }
      } else if (checkAttempts >= maxAttempts) {
        console.warn('Google Maps not available after 5 seconds, using fallback input');
        if (mounted) {
          setIsGoogleMapsAvailable(false);
          setIsChecking(false);
        }
      } else {
        // Check again in 500ms
        setTimeout(checkGoogleMaps, 500);
      }
    };

    // Check if already loaded
    if (window.googleMapsLoaded) {
      setIsGoogleMapsAvailable(true);
      setIsChecking(false);
    } else {
      // Start checking
      checkGoogleMaps();
    }

    return () => {
      mounted = false;
    };
  }, [disabled]);

  useEffect(() => {
    if (!isGoogleMapsAvailable || !inputRef.current || disabled || !onPlaceSelect) return;

    try {
      // Clean up existing autocomplete
      if (autocompleteRef.current) {
        if (window.google?.maps?.event?.clearInstanceListeners) {
          window.google.maps.event.clearInstanceListeners(autocompleteRef.current);
        }
      }

      // Create autocomplete
      const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
        types: ['address'],
        componentRestrictions: { country: 'us' },
        fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id']
      });

      autocompleteRef.current = autocomplete;

      // Handle place selection
      const placeChangedListener = autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (place && (place.formatted_address || place.address_components)) {
          onPlaceSelect(place);
          // Update the input value with the formatted address
          if (place.formatted_address) {
            setInputValue(place.formatted_address);
            if (onChange) {
              onChange(place.formatted_address);
            }
          }
        }
      });

      // Set bounds to US for better results
      if (window.google.maps.LatLngBounds) {
        const defaultBounds = new window.google.maps.LatLngBounds(
          new window.google.maps.LatLng(24.396308, -125.0), // SW corner of US
          new window.google.maps.LatLng(49.384358, -66.93)   // NE corner of US
        );
        autocomplete.setBounds(defaultBounds);
      }

      return () => {
        if (placeChangedListener) {
          window.google.maps.event.removeListener(placeChangedListener);
        }
      };
    } catch (error) {
      console.error('Error initializing autocomplete:', error);
      setIsGoogleMapsAvailable(false);
    }
  }, [isGoogleMapsAvailable, disabled, onPlaceSelect, onChange]);

  // Update internal value when prop changes
  useEffect(() => {
    setInputValue(value || defaultValue || '');
  }, [value, defaultValue]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setInputValue(newValue);
    if (onChange) {
      onChange(newValue);
    }
  };

  return (
    <div className="relative">
      <FiMapPin className={cn("absolute left-3 top-3 h-4 w-4 z-10", t.text.muted)} />
      <input
        ref={inputRef}
        type="text"
        value={inputValue}
        onChange={handleInputChange}
        placeholder={isChecking ? "Checking address services..." : placeholder}
        disabled={disabled || isChecking}
        required={required}
        className={cn(
          "w-full pl-10",
          isChecking && "opacity-50",
          className
        )}
      />
      {isChecking && (
        <div className="absolute right-3 top-3">
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
        </div>
      )}
      {!isChecking && !isGoogleMapsAvailable && !disabled && (
        <div className="mt-1 text-xs text-gray-500">
          Address autocomplete unavailable - manual entry only
        </div>
      )}
    </div>
  );
}