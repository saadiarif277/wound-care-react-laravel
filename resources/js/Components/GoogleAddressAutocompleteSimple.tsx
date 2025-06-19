import React, { useEffect, useRef } from 'react';
import { FiMapPin } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface GoogleAddressAutocompleteSimpleProps {
  onPlaceSelect: (place: google.maps.places.PlaceResult) => void;
  defaultValue?: string;
  className?: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
}

declare global {
  interface Window {
    google: any;
  }
}

export default function GoogleAddressAutocompleteSimple({
  onPlaceSelect,
  defaultValue = '',
  className = '',
  placeholder = "Start typing an address...",
  required = false,
  disabled = false
}: GoogleAddressAutocompleteSimpleProps) {
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
  const autocompleteRef = useRef<google.maps.places.Autocomplete | null>(null);

  useEffect(() => {
    // Load Google Maps script if not already loaded
    const loadGoogleMapsScript = () => {
      if (typeof window.google === 'undefined' || !window.google.maps) {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${import.meta.env.VITE_GOOGLE_MAPS_API_KEY} &libraries=places&loading=async`;
        script.async = true;
        script.defer = true;
        script.onload = initializeAutocomplete;
        document.head.appendChild(script);
      } else {
        initializeAutocomplete();
      }
    };

    const initializeAutocomplete = () => {
      if (!inputRef.current || disabled) return;

      try {
        // Initialize autocomplete
        autocompleteRef.current = new window.google.maps.places.Autocomplete(inputRef.current, {
          types: ['address'],
          componentRestrictions: { country: 'us' }
        });

        // Listen for place selection
        autocompleteRef.current.addListener('place_changed', () => {
          const place = autocompleteRef.current?.getPlace();
          if (place && onPlaceSelect) {
            onPlaceSelect(place);
          }
        });
      } catch (error) {
        console.error('Error initializing Google Places Autocomplete:', error);
      }
    };

    loadGoogleMapsScript();

    // Cleanup
    return () => {
      if (autocompleteRef.current) {
        window.google.maps.event.clearInstanceListeners(autocompleteRef.current);
      }
    };
  }, [disabled, onPlaceSelect]);

  return (
    <div className="relative">
      <FiMapPin className={cn("absolute left-3 top-3 h-4 w-4", t.text.muted)} />
      <input
        ref={inputRef}
        type="text"
        defaultValue={defaultValue}
        placeholder={placeholder}
        disabled={disabled}
        required={required}
        className={cn(
          "w-full pl-10",
          className
        )}
      />
    </div>
  );
}