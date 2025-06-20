import React, { useEffect, useRef, useState } from 'react';
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
  const autocompleteElementRef = useRef<any>(null);
  const [isLoaded, setIsLoaded] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (disabled) return;

    let isMounted = true;

    const loadGoogleMapsScript = async () => {
      if (isLoading) return;
      setIsLoading(true);

      try {
        // Check if Google Maps is already loaded
        if (typeof window.google !== 'undefined' && window.google.maps && window.google.maps.places) {
          if (isMounted) {
            initializeNewAutocomplete();
            setIsLoaded(true);
          }
          return;
        }

        // Create script with async loading as recommended by Google
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${import.meta.env.VITE_GOOGLE_MAPS_API_KEY}&libraries=places&loading=async&v=weekly`;
        script.async = true;
        script.defer = true;

        script.onload = () => {
          if (isMounted) {
            // Wait a bit for Google Maps to fully initialize
            setTimeout(() => {
              if (isMounted) {
                initializeNewAutocomplete();
                setIsLoaded(true);
                setIsLoading(false);
              }
            }, 100);
          }
        };

        script.onerror = () => {
          console.error('Failed to load Google Maps script');
          if (isMounted) {
            setIsLoading(false);
          }
        };

        // Check if script already exists
        const existingScript = document.querySelector('script[src*="maps.googleapis.com"]');
        if (!existingScript) {
          document.head.appendChild(script);
        } else {
          // Script exists but might still be loading
          existingScript.addEventListener('load', () => {
            if (isMounted) {
              setTimeout(() => {
                if (isMounted) {
                  initializeNewAutocomplete();
                  setIsLoaded(true);
                  setIsLoading(false);
                }
              }, 100);
            }
          });
        }
      } catch (error) {
        console.error('Error loading Google Maps:', error);
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    const initializeNewAutocomplete = () => {
      if (!inputRef.current || !window.google?.maps?.places) {
        console.warn('Google Maps Places API not available');
        return;
      }

      try {
        // Use the new PlaceAutocompleteElement (recommended approach)
        if (window.google.maps.places.PlaceAutocompleteElement) {
          // Clean up any existing autocomplete element
          if (autocompleteElementRef.current) {
            autocompleteElementRef.current.remove();
          }

          // Create new autocomplete element
          const autocompleteElement = new window.google.maps.places.PlaceAutocompleteElement({
            componentRestrictions: { country: 'us' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id'],
            types: ['address']
          });

          autocompleteElementRef.current = autocompleteElement;

          // Style the autocomplete element to match our input
          autocompleteElement.style.width = '100%';
          autocompleteElement.style.height = '40px';
          autocompleteElement.style.border = 'none';
          autocompleteElement.style.outline = 'none';
          autocompleteElement.style.paddingLeft = '2.5rem';

          // Replace the input with the autocomplete element
          if (inputRef.current && inputRef.current.parentNode) {
            inputRef.current.style.display = 'none';
            inputRef.current.parentNode.insertBefore(autocompleteElement, inputRef.current.nextSibling);
          }

          // Listen for place selection
          autocompleteElement.addEventListener('gmp-placeselect', (event: any) => {
            const place = event.detail.place;
            if (place && onPlaceSelect) {
              // Convert to legacy PlaceResult format for compatibility
              const legacyPlace = {
                address_components: place.addressComponents,
                formatted_address: place.formattedAddress,
                geometry: place.geometry,
                name: place.displayName,
                place_id: place.id
              };
              onPlaceSelect(legacyPlace);
            }
          });

          // Set default value if provided
          if (defaultValue) {
            autocompleteElement.value = defaultValue;
          }

        } else {
          // Fallback to legacy Autocomplete with deprecation handling
          console.warn('Using deprecated Google Places Autocomplete - consider updating to PlaceAutocompleteElement');

          const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
            types: ['address'],
            componentRestrictions: { country: 'us' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id']
          });

          autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place && onPlaceSelect) {
              onPlaceSelect(place);
            }
          });

          autocompleteElementRef.current = autocomplete;
        }
      } catch (error) {
        console.error('Error initializing Google Places Autocomplete:', error);
      }
    };

    loadGoogleMapsScript();

    // Cleanup
    return () => {
      isMounted = false;
      if (autocompleteElementRef.current) {
        try {
          if (autocompleteElementRef.current.remove) {
            // New PlaceAutocompleteElement
            autocompleteElementRef.current.remove();
          } else if (window.google?.maps?.event?.clearInstanceListeners) {
            // Legacy Autocomplete
            window.google.maps.event.clearInstanceListeners(autocompleteElementRef.current);
          }
        } catch (error) {
          console.warn('Error cleaning up autocomplete:', error);
        }
        autocompleteElementRef.current = null;
      }
    };
  }, [disabled, onPlaceSelect, defaultValue]);

  return (
    <div className="relative">
      <FiMapPin className={cn("absolute left-3 top-3 h-4 w-4 z-10", t.text.muted)} />
      <input
        ref={inputRef}
        type="text"
        defaultValue={defaultValue}
        placeholder={isLoading ? "Loading Google Maps..." : placeholder}
        disabled={disabled || isLoading}
        required={required}
        className={cn(
          "w-full pl-10",
          isLoading && "opacity-50",
          className
        )}
      />
      {isLoading && (
        <div className="absolute right-3 top-3">
          <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
        </div>
      )}
    </div>
  );
}
