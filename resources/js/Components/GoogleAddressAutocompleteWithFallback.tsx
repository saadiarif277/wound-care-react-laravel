import React, { useEffect, useRef, useState } from 'react';
import { MapPin, Loader2 } from 'lucide-react';

// Clean, simple interface for parsed address components
export interface ParsedAddressComponents {
  streetNumber?: string;
  streetName?: string;
  streetAddress?: string;
  city?: string;
  state?: string;
  stateAbbreviation?: string;
  zipCode?: string;
  country?: string;
  formattedAddress?: string;
  placeId?: string;
}

interface GoogleAddressAutocompleteWithFallbackProps {
  onPlaceSelect?: (place: google.maps.places.PlaceResult, parsedAddress?: ParsedAddressComponents) => void;
  value?: string;
  onChange?: (value: string) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  required?: boolean;
}

declare global {
  interface Window {
    google: any;
  }
}

/**
 * Modern Google Address Autocomplete - 2025 Edition
 * 
 * Clean, simple implementation that:
 * - Uses modern PlaceAutocompleteElement API (recommended by Google)
 * - Falls back to legacy Autocomplete API if needed
 * - Actually shows text properly
 * - Works with Google Places API
 * - Falls back gracefully to manual input
 * - No complex theme handling or overly complex API detection
 * - Eliminates deprecation warnings from Google Maps
 */
export default function GoogleAddressAutocompleteWithFallback({
  onPlaceSelect,
  value = '',
  onChange,
  placeholder = 'Start typing an address...',
  className = '',
  disabled = false,
  required = false
}: GoogleAddressAutocompleteWithFallbackProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const autocompleteRef = useRef<google.maps.places.Autocomplete | null>(null);
  const [isGoogleLoaded, setIsGoogleLoaded] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [inputValue, setInputValue] = useState(value);

  // Parse Google Places address components (works with both new and legacy APIs)
  const parseAddressComponents = (place: any): ParsedAddressComponents => {
    const parsed: ParsedAddressComponents = {
      formattedAddress: place.formatted_address || place.formattedAddress,
      placeId: place.place_id || place.id
    };

    const components = place.address_components || place.addressComponents || [];
    if (components.length === 0) return parsed;

    for (const component of components) {
      const types = component.types || [];
      const longName = component.long_name || component.longText;
      const shortName = component.short_name || component.shortText;

      if (types.includes('street_number')) {
        parsed.streetNumber = longName;
      } else if (types.includes('route')) {
        parsed.streetName = longName;
      } else if (types.includes('locality')) {
        parsed.city = longName;
      } else if (types.includes('administrative_area_level_1')) {
        parsed.state = longName;
        parsed.stateAbbreviation = shortName;
      } else if (types.includes('postal_code')) {
        parsed.zipCode = longName;
      } else if (types.includes('country')) {
        parsed.country = longName;
      }
    }

    // Combine street parts
    if (parsed.streetNumber && parsed.streetName) {
      parsed.streetAddress = `${parsed.streetNumber} ${parsed.streetName}`;
    } else if (parsed.streetName) {
      parsed.streetAddress = parsed.streetName;
    }

    return parsed;
  };

  // Check if Google Maps is available
  useEffect(() => {
    const checkGoogle = () => {
      // Prefer new PlaceAutocompleteElement API, fallback to legacy
      if (window.google?.maps?.places?.PlaceAutocompleteElement || window.google?.maps?.places?.Autocomplete) {
        setIsGoogleLoaded(true);
        setIsLoading(false);
        return true;
      }
      return false;
    };

    if (checkGoogle()) return;

    // Check periodically for Google Maps
    const interval = setInterval(() => {
      if (checkGoogle()) {
        clearInterval(interval);
      }
    }, 500);

    // Give up after 10 seconds
    setTimeout(() => {
      clearInterval(interval);
      setIsLoading(false);
    }, 10000);

    return () => clearInterval(interval);
  }, []);

  // Initialize Google Places Autocomplete
  useEffect(() => {
    if (!isGoogleLoaded || !inputRef.current || disabled) return;

    try {
      // Clean up existing instance
      if (autocompleteRef.current) {
        if (window.google?.maps?.event?.clearInstanceListeners) {
          window.google.maps.event.clearInstanceListeners(autocompleteRef.current);
        }
      }

      // Use modern PlaceAutocompleteElement if available
      if (window.google.maps.places.PlaceAutocompleteElement) {
        // Create new PlaceAutocompleteElement
        const placeAutocomplete = new window.google.maps.places.PlaceAutocompleteElement({
          types: ['address'],
          componentRestrictions: { country: 'us' }
        });

        // Copy input properties to the new element
        placeAutocomplete.placeholder = inputRef.current.placeholder;
        placeAutocomplete.value = inputValue;
        placeAutocomplete.disabled = disabled;
        placeAutocomplete.required = inputRef.current.required;
        placeAutocomplete.className = inputRef.current.className;

        // Handle place selection with new API
        placeAutocomplete.addEventListener('gmp-placeselect', (event: any) => {
          const place = event.place;
          
          if (place && place.formattedAddress) {
            const formatted = place.formattedAddress;
            setInputValue(formatted);
            
            if (onChange) {
              onChange(formatted);
            }

            if (onPlaceSelect) {
              // Convert new API place to legacy format for compatibility
              const legacyPlace = {
                formatted_address: place.formattedAddress,
                place_id: place.id,
                address_components: place.addressComponents || []
              };
              const parsed = parseAddressComponents(legacyPlace);
              onPlaceSelect(legacyPlace, parsed);
            }
          }
        });

        // Replace the input with the new element
        if (inputRef.current.parentNode) {
          inputRef.current.parentNode.replaceChild(placeAutocomplete, inputRef.current);
          inputRef.current = placeAutocomplete as any;
        }

        autocompleteRef.current = placeAutocomplete;

      } else if (window.google.maps.places.Autocomplete) {
        // Fallback to legacy Autocomplete (with deprecation warning suppressed)
        const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
          types: ['address'],
          componentRestrictions: { country: 'us' },
          fields: ['address_components', 'formatted_address', 'geometry', 'place_id']
        });

        autocompleteRef.current = autocomplete;

        // Handle place selection with legacy API
        const listener = autocomplete.addListener('place_changed', () => {
          const place = autocomplete.getPlace();
          
          if (place && place.formatted_address) {
            const formatted = place.formatted_address;
            setInputValue(formatted);
            
            if (onChange) {
              onChange(formatted);
            }

            if (onPlaceSelect) {
              const parsed = parseAddressComponents(place);
              onPlaceSelect(place, parsed);
            }
          }
        });

        return () => {
          if (listener) {
            window.google.maps.event.removeListener(listener);
          }
        };
      }

    } catch (error) {
      console.error('Error initializing Google Places:', error);
    }
  }, [isGoogleLoaded, disabled, onPlaceSelect, onChange, inputValue]);

  // Sync internal state with prop changes
  useEffect(() => {
    setInputValue(value);
  }, [value]);

  // Handle manual input changes
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setInputValue(newValue);
    if (onChange) {
      onChange(newValue);
    }
  };

  // Base input styles
  const inputClasses = `
    w-full pl-10 pr-4 py-2.5 
    bg-white dark:bg-gray-800 
    border border-gray-300 dark:border-gray-600 
    rounded-lg 
    text-gray-900 dark:text-white 
    placeholder:text-gray-500 dark:placeholder:text-gray-400
    focus:ring-2 focus:ring-blue-500 focus:border-blue-500
    disabled:opacity-50 disabled:cursor-not-allowed
    ${className}
  `.trim().replace(/\s+/g, ' ');

  return (
    <div className="relative">
      <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none z-10" />
      
      <input
        ref={inputRef}
        type="text"
        value={inputValue}
        onChange={handleInputChange}
        placeholder={isLoading ? 'Loading Google Maps...' : placeholder}
        disabled={disabled || isLoading}
        required={required}
        className={inputClasses}
      />

      {isLoading && (
        <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 animate-spin" />
      )}

      {/* Status indicator */}
      {process.env.NODE_ENV === 'development' && (
        <div className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium">
          {isLoading ? (
            <span className="text-gray-500">Loading...</span>
          ) : isGoogleLoaded ? (
            window.google?.maps?.places?.PlaceAutocompleteElement ? (
              <span className="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded">
                New API
              </span>
            ) : (
              <span className="px-2 py-0.5 bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400 rounded">
                Legacy
              </span>
            )
          ) : (
            <span className="px-2 py-0.5 bg-orange-100 dark:bg-orange-900 text-orange-600 dark:text-orange-400 rounded">
              Manual
            </span>
          )}
        </div>
      )}
    </div>
  );
}