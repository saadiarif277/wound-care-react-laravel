import { useState, useEffect } from 'react';

/**
 * Hook to safely handle hydration mismatches
 * Ensures server and client render the same content initially
 * 
 * @param clientValue - The value to show after hydration on client
 * @param serverValue - The value to show during SSR (optional, defaults to null)
 * @returns The appropriate value based on hydration state
 */
export function useHydrationSafe<T>(clientValue: T, serverValue: T | null = null): T | null {
  const [hasMounted, setHasMounted] = useState(false);

  useEffect(() => {
    setHasMounted(true);
  }, []);

  return hasMounted ? clientValue : serverValue;
}

/**
 * Hook to check if component is mounted on client (post-hydration)
 * Useful for conditional rendering of client-only features
 */
export function useIsClient(): boolean {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  return isClient;
}

/**
 * Hook for safe browser API access
 * Returns null during SSR, actual API during client rendering
 */
export function useBrowserAPI<T>(apiGetter: () => T): T | null {
  const isClient = useIsClient();
  
  if (!isClient) return null;
  
  try {
    return apiGetter();
  } catch (error) {
    console.warn('Browser API access failed:', error);
    return null;
  }
}

/**
 * Hook for safe time-based rendering
 * Prevents hydration mismatches from server/client time differences
 */
export function useClientTime(updateInterval?: number): Date | null {
  const [currentTime, setCurrentTime] = useState<Date | null>(null);

  useEffect(() => {
    // Set initial time after mount
    setCurrentTime(new Date());

    // Set up interval if specified
    if (updateInterval) {
      const interval = setInterval(() => {
        setCurrentTime(new Date());
      }, updateInterval);

      return () => clearInterval(interval);
    }
  }, [updateInterval]);

  return currentTime;
} 