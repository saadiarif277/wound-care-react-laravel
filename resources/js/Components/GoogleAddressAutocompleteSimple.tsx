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

  const [isLoaded, setIsLoaded] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [apiType, setApiType] = useState<'new' | 'legacy' | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const autocompleteElementRef = useRef<any>(null);

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
            initializeAutocomplete();
            setIsLoaded(true);
            setIsLoading(false);
          }
          return;
        }

        // Create script with async loading to prevent deprecation warnings
        const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
        if (!apiKey) {
          console.error('VITE_GOOGLE_MAPS_API_KEY environment variable not found');
          setIsLoading(false);
          return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&loading=async&v=weekly`;
        script.async = true;
        script.defer = true;

        script.onload = () => {
          if (isMounted) {
            // Wait a bit for Google Maps to fully initialize
            setTimeout(() => {
              if (isMounted) {
                initializeAutocomplete();
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
                  initializeAutocomplete();
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

    const initializeAutocomplete = () => {
      if (!inputRef.current) {
        console.warn('Input ref not available for Google Places Autocomplete');
        return;
      }

      if (!window.google?.maps?.places) {
        console.warn('Google Maps Places API not available');
        return;
      }

      try {
        // Clean up any existing autocomplete
        if (autocompleteElementRef.current) {
          if (typeof autocompleteElementRef.current.remove === 'function') {
            autocompleteElementRef.current.remove();
          } else if (window.google?.maps?.event?.clearInstanceListeners) {
            window.google.maps.event.clearInstanceListeners(autocompleteElementRef.current);
          }
          autocompleteElementRef.current = null;
        }

        // Try to use the new PlaceAutocompleteElement (recommended) first
        if (window.google.maps.places.PlaceAutocompleteElement) {
          try {
            // Create the new PlaceAutocompleteElement without the invalid 'fields' property
            const placeAutocomplete = new window.google.maps.places.PlaceAutocompleteElement({
              componentRestrictions: { country: 'us' },
              types: ['address']
            });

            // Configure the element after creation
            if (placeAutocomplete.requestedFields) {
              placeAutocomplete.requestedFields = ['address_components', 'formatted_address', 'geometry', 'name', 'place_id'];
            }

            // Replace the input with the new element
            if (inputRef.current && inputRef.current.parentNode) {
              // Copy input styling and attributes to the new element
              const inputClasses = inputRef.current.className;
              const inputPlaceholder = inputRef.current.placeholder;
              const inputRequired = inputRef.current.required;
              const inputDisabled = inputRef.current.disabled;

              inputRef.current.parentNode.replaceChild(placeAutocomplete, inputRef.current);

              // Apply styling to the new element
              if (placeAutocomplete.style) {
                placeAutocomplete.className = inputClasses;
                placeAutocomplete.placeholder = inputPlaceholder;
                placeAutocomplete.required = inputRequired;
                placeAutocomplete.disabled = inputDisabled;
              }

              autocompleteElementRef.current = placeAutocomplete;

              // Set up event listener for place selection
              placeAutocomplete.addEventListener('gmp-placeselect', (event: any) => {
                const place = event.place;
                if (place && onPlaceSelect) {
                  onPlaceSelect(place);
                }
              });

              // Update the ref to point to the new element
              inputRef.current = placeAutocomplete as any;
            }

            console.log('Successfully initialized new PlaceAutocompleteElement API');
            setApiType('new');
            return;
          } catch (error) {
            console.warn('Failed to use new PlaceAutocompleteElement, falling back to legacy Autocomplete:', error);
          }
        }

        // Fallback to legacy Autocomplete API
        console.log('Using legacy Google Places Autocomplete API');
        if (!window.google.maps.places.Autocomplete) {
          console.error('Neither PlaceAutocompleteElement nor legacy Autocomplete available');
          return;
        }

        const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
          types: ['address'],
          componentRestrictions: { country: 'us' },
          fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id']
        });

        autocomplete.addListener('place_changed', () => {
          const place = autocomplete.getPlace();
          if (place && onPlaceSelect) {
            // Ensure we have the required data
            if (place.formatted_address || place.address_components) {
              onPlaceSelect(place);
            } else {
              console.warn('Incomplete place data received');
            }
          }
        });

        autocompleteElementRef.current = autocomplete;
        setApiType('legacy');

        // Set bounds to US for better results
        if (window.google.maps.LatLngBounds) {
          const defaultBounds = new window.google.maps.LatLngBounds(
            new window.google.maps.LatLng(24.396308, -125.0), // SW corner of US
            new window.google.maps.LatLng(49.384358, -66.93)   // NE corner of US
          );
          autocomplete.setBounds(defaultBounds);
        }

      } catch (error) {
        console.error('Error initializing Google Places Autocomplete:', error);
        setIsLoading(false);
      }
    };

    loadGoogleMapsScript();

    // Cleanup
    return () => {
      isMounted = false;
      if (autocompleteElementRef.current) {
        try {
          if (window.google?.maps?.event?.clearInstanceListeners) {
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
      {/* Development debug info - remove in production */}
      {process.env.NODE_ENV === 'development' && isLoaded && apiType && (
        <div className="absolute -bottom-5 left-0 text-xs text-gray-500">
          Google Maps API: {apiType === 'new' ? 'PlaceAutocompleteElement (new)' : 'Autocomplete (legacy)'}
        </div>
      )}
    </div>
  );
}
