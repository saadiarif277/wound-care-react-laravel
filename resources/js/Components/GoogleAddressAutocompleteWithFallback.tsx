import React, { useEffect, useRef, useState } from 'react';
import { FiMapPin } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

// Interface for parsed address components
export interface ParsedAddressComponents {
  streetNumber?: string;
  streetName?: string;
  streetAddress?: string; // streetNumber + streetName combined
  subpremise?: string; // Apartment, suite, etc.
  city?: string;
  state?: string;
  stateAbbreviation?: string;
  zipCode?: string;
  country?: string;
  countryCode?: string;
  formattedAddress?: string;
  placeId?: string;
}

interface GoogleAddressAutocompleteWithFallbackProps {
  onPlaceSelect?: (place: google.maps.places.PlaceResult, parsedAddress?: ParsedAddressComponents) => void;
  value?: string;
  onChange?: (value: string) => void;
  defaultValue?: string;
  className?: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  // Option to automatically parse address components
  parseAddressComponents?: boolean;
}

declare global {
  interface Window {
    google: any;
    googleMapsLoaded?: boolean;
    googleMapsCallbacks?: (() => void)[];
  }
}

/**
 * Parse Google Places API address_components into structured data
 */
function parseAddressComponents(place: google.maps.places.PlaceResult): ParsedAddressComponents {
  const parsed: ParsedAddressComponents = {
    formattedAddress: place.formatted_address,
    placeId: place.place_id
  };

  if (!place.address_components) {
    return parsed;
  }

  for (const component of place.address_components) {
    const types = component.types;
    const longName = component.long_name;
    const shortName = component.short_name;

    if (types.includes('street_number')) {
      parsed.streetNumber = longName;
    } else if (types.includes('route')) {
      parsed.streetName = longName;
    } else if (types.includes('subpremise')) {
      parsed.subpremise = longName; // Apartment, suite, unit, etc.
    } else if (types.includes('locality')) {
      parsed.city = longName;
    } else if (types.includes('administrative_area_level_1')) {
      parsed.state = longName;
      parsed.stateAbbreviation = shortName;
    } else if (types.includes('postal_code')) {
      parsed.zipCode = longName;
    } else if (types.includes('country')) {
      parsed.country = longName;
      parsed.countryCode = shortName;
    }
  }

  // Combine street number and name for complete street address
  if (parsed.streetNumber && parsed.streetName) {
    parsed.streetAddress = `${parsed.streetNumber} ${parsed.streetName}`;
  } else if (parsed.streetName) {
    parsed.streetAddress = parsed.streetName;
  }

  return parsed;
}

export default function GoogleAddressAutocompleteWithFallback({
  onPlaceSelect,
  value,
  onChange,
  defaultValue = '',
  className = '',
  placeholder = "Start typing an address...",
  required = false,
  disabled = false,
  parseAddressComponents: shouldParseAddress = true
}: GoogleAddressAutocompleteWithFallbackProps) {
  console.log('🏗️ GoogleAddressAutocompleteWithFallback initialized with:', {
    hasOnPlaceSelect: !!onPlaceSelect,
    shouldParseAddress,
    value,
    defaultValue,
    disabled
  });
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
  const [apiType, setApiType] = useState<'new' | 'legacy' | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const autocompleteRef = useRef<any>(null);
  const [inputValue, setInputValue] = useState(value || defaultValue || '');

  // Enhanced place selection handler with address parsing
  const handlePlaceSelection = (place: google.maps.places.PlaceResult) => {
    if (!place || (!place.formatted_address && !place.address_components)) return;

    console.log('🏠 Address selected from Google Places:', place.formatted_address);
    console.log('📍 Raw place data:', place);

    let parsedAddress: ParsedAddressComponents | undefined;
    
    if (shouldParseAddress) {
      parsedAddress = parseAddressComponents(place);
      
      // Always log parsed components for debugging
      console.log('✅ Parsed address components:', parsedAddress);
      
      // Show a more detailed breakdown
      if (parsedAddress) {
        console.log('📋 Parsed breakdown:', {
          'Street Address': parsedAddress.streetAddress || 'Not found',
          'City': parsedAddress.city || 'Not found',
          'State': parsedAddress.state || 'Not found',
          'State Abbreviation': parsedAddress.stateAbbreviation || 'Not found',
          'ZIP Code': parsedAddress.zipCode || 'Not found',
          'Country': parsedAddress.country || 'Not found'
        });
      }
    }

    // Call the callback with both original place and parsed components
    if (onPlaceSelect) {
      console.log('🔄 Calling onPlaceSelect callback with parsed data');
      onPlaceSelect(place, parsedAddress);
    }

    // Update the input value with the formatted address
    if (place.formatted_address) {
      setInputValue(place.formatted_address);
      if (onChange) {
        onChange(place.formatted_address);
      }
    }
  };

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
      
      if (window.google?.maps?.places) {
        console.log('Google Maps is available');
        if (mounted) {
          setIsGoogleMapsAvailable(true);
          setIsChecking(false);
          window.googleMapsLoaded = true;
          
          // Determine which API to use (prefer new API over legacy)
          if (window.google.maps.places.PlaceAutocompleteElement) {
            setApiType('new');
            console.log('Using new PlaceAutocompleteElement API (recommended)');
          } else if (window.google.maps.places.Autocomplete) {
            setApiType('legacy');
            console.log('Using legacy Autocomplete API');
          }
          
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
        setTimeout(checkGoogleMaps, 500);
      }
    };

    checkGoogleMaps();

    return () => {
      mounted = false;
    };
  }, [disabled]);

  // Initialize Google Places Autocomplete
  useEffect(() => {
    if (!isGoogleMapsAvailable || !inputRef.current || disabled) return;

    let autocompleteInstance: any = null;
    let mounted = true;

    const initializeAutocomplete = () => {
      if (!mounted || !inputRef.current) return;

      try {
        // Clean up existing instance
        if (autocompleteInstance) {
          if (window.google?.maps?.event?.clearInstanceListeners) {
            window.google.maps.event.clearInstanceListeners(autocompleteInstance);
          }
          autocompleteInstance = null;
        }

        // Try to use the new PlaceAutocompleteElement API first
        if (apiType === 'new' && window.google.maps.places.PlaceAutocompleteElement) {
          console.log('🏗️ Initializing new PlaceAutocompleteElement API');
          
          // Create the new PlaceAutocompleteElement
          const placeAutocomplete = new window.google.maps.places.PlaceAutocompleteElement({
            componentRestrictions: { country: 'us' },
            types: ['address']
          });
          
          // Set up the element properties
          placeAutocomplete.placeholder = inputRef.current.placeholder || 'Start typing an address...';
          placeAutocomplete.className = inputRef.current.className;
          placeAutocomplete.required = inputRef.current.required;
          placeAutocomplete.disabled = disabled;
          placeAutocomplete.value = inputValue;
          
          // Set up event listener for place selection
          placeAutocomplete.addEventListener('gmp-placeselect', (event: any) => {
            const place = event.place;
            if (place && mounted) {
              console.log('🏠 Place selected via new API:', place.displayName);
              handlePlaceSelection(place);
            }
          });
          
          // Replace the input with the new element
          if (inputRef.current.parentNode) {
            inputRef.current.parentNode.replaceChild(placeAutocomplete, inputRef.current);
            inputRef.current = placeAutocomplete as any;
          }
          
          autocompleteInstance = placeAutocomplete;
          
        } else if (apiType === 'legacy' && window.google.maps.places.Autocomplete) {
          console.log('🏗️ Initializing legacy Autocomplete API');
          
          // Use legacy API as fallback
          const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
            types: ['address'],
            componentRestrictions: { country: 'us' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id']
          });

          // Set up event listener for place selection
          autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place && mounted) {
              console.log('🏠 Place selected via legacy API:', place.formatted_address);
              handlePlaceSelection(place);
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

          autocompleteInstance = autocomplete;
        }

      } catch (error) {
        console.error('Error initializing Google Places:', error);
        setIsGoogleMapsAvailable(false);
      }
    };

    // Initialize with a small delay to ensure DOM is ready
    const timer = setTimeout(initializeAutocomplete, 100);

    return () => {
      mounted = false;
      clearTimeout(timer);
      
      if (autocompleteInstance && window.google?.maps?.event?.clearInstanceListeners) {
        try {
          window.google.maps.event.clearInstanceListeners(autocompleteInstance);
        } catch (error) {
          console.warn('Error cleaning up autocomplete:', error);
        }
      }
    };
  }, [isGoogleMapsAvailable, apiType, disabled, inputValue]);

  // Update internal value when prop changes
  useEffect(() => {
    setInputValue(value || defaultValue || '');
  }, [value, defaultValue]);

  // Handle manual input changes
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setInputValue(value);
    if (onChange) {
      onChange(value);
    }
  };

  // Fallback input (when Google Maps is not available)
  if (!isGoogleMapsAvailable && !isChecking) {
    return (
      <div className="relative">
        <FiMapPin className={`absolute left-3 top-3 h-4 w-4 ${t.text.muted}`} />
        <input
          ref={inputRef}
          type="text"
          value={inputValue}
          onChange={handleInputChange}
          placeholder={placeholder}
          disabled={disabled}
          required={required}
          className={cn(
            "w-full pl-10",
            t.input.base,
            t.input.focus,
            disabled && "opacity-50 cursor-not-allowed",
            className
          )}
        />
        <div className="absolute right-3 top-3 text-xs text-orange-500">
          Manual
        </div>
      </div>
    );
  }

  // Loading state
  if (isChecking) {
    return (
      <div className="relative">
        <FiMapPin className={`absolute left-3 top-3 h-4 w-4 ${t.text.muted}`} />
        <input
          type="text"
          placeholder="Loading Google Maps..."
          disabled
          value=""
          className={cn(
            "w-full pl-10 pr-10 opacity-50",
            t.input.base,
            className
          )}
        />
        <div className="absolute right-3 top-3">
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
        </div>
      </div>
    );
  }

  // Google Maps is available - render the input that will be replaced by autocomplete
  return (
    <div className="relative">
      <FiMapPin className={`absolute left-3 top-3 h-4 w-4 ${t.text.muted} z-10`} />
      <input
        ref={inputRef}
        type="text"
        value={inputValue}
        onChange={handleInputChange}
        placeholder={placeholder}
        disabled={disabled}
        required={required}
        className={cn(
          "w-full pl-10",
          t.input.base,
          t.input.focus,
          disabled && "opacity-50 cursor-not-allowed",
          className
        )}
      />
      
      {/* Show API type indicator in development */}
      {process.env.NODE_ENV === 'development' && apiType && (
        <div className="absolute right-3 top-3 text-xs text-green-500">
          {apiType === 'new' ? 'New API' : 'Legacy'}
        </div>
      )}
    </div>
  );
}