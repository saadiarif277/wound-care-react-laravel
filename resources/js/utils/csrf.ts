/**
 * CSRF Token Management Utility
 * Handles automatic CSRF token refresh and validation
 */

interface CSRFTokenInfo {
  token: string;
  expires: number;
  refreshPromise?: Promise<string>;
}

class CSRFTokenManager {
  private static instance: CSRFTokenManager;
  private tokenCache: CSRFTokenInfo | null = null;
  private readonly TOKEN_REFRESH_THRESHOLD = 5 * 60 * 1000; // 5 minutes in milliseconds
  
  private constructor() {}

  static getInstance(): CSRFTokenManager {
    if (!CSRFTokenManager.instance) {
      CSRFTokenManager.instance = new CSRFTokenManager();
    }
    return CSRFTokenManager.instance;
  }

  /**
   * Get a fresh CSRF token, refreshing if needed
   */
  async getToken(): Promise<string> {
    // Check if we have a cached token that's still valid
    if (this.tokenCache && this.isTokenValid(this.tokenCache)) {
      return this.tokenCache.token;
    }

    // Check if there's already a refresh in progress
    if (this.tokenCache?.refreshPromise) {
      return await this.tokenCache.refreshPromise;
    }

    // Start a new refresh
    const refreshPromise = this.refreshToken();
    if (this.tokenCache) {
      this.tokenCache.refreshPromise = refreshPromise;
    }

    const token = await refreshPromise;
    
    // Clear the refresh promise
    if (this.tokenCache) {
      this.tokenCache.refreshPromise = undefined;
    }

    return token;
  }

  /**
   * Refresh the CSRF token from the server
   */
  private async refreshToken(): Promise<string> {
    try {
      // Try to get token from meta tag first
      const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (metaToken) {
        this.tokenCache = {
          token: metaToken,
          expires: Date.now() + (60 * 60 * 1000), // Assume 1 hour validity
        };
        return metaToken;
      }

      // Fallback: fetch a new token from the server
      const response = await fetch('/api/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error(`Failed to refresh CSRF token: ${response.status}`);
      }

      const data = await response.json();
      const token = data.token || data.csrf_token;

      if (!token) {
        throw new Error('No CSRF token received from server');
      }

      this.tokenCache = {
        token,
        expires: Date.now() + (60 * 60 * 1000), // 1 hour validity
      };

      // Update the meta tag for other scripts
      const metaTag = document.querySelector('meta[name="csrf-token"]');
      if (metaTag) {
        metaTag.setAttribute('content', token);
      }

      return token;
    } catch (error) {
      console.error('Failed to refresh CSRF token:', error);
      throw error;
    }
  }

  /**
   * Check if a token is still valid
   */
  private isTokenValid(tokenInfo: CSRFTokenInfo): boolean {
    const now = Date.now();
    const timeUntilExpiry = tokenInfo.expires - now;
    
    // Token is valid if it has more than the threshold time remaining
    return timeUntilExpiry > this.TOKEN_REFRESH_THRESHOLD;
  }

  /**
   * Clear the cached token (useful for testing or when token is known to be invalid)
   */
  clearCache(): void {
    this.tokenCache = null;
  }

  /**
   * Check if CSRF token is available
   */
  isAvailable(): boolean {
    return !!document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  }
}

// Export the singleton instance
export const csrfTokenManager = CSRFTokenManager.getInstance();

/**
 * Convenience function to get a fresh CSRF token
 */
export async function getCSRFToken(): Promise<string> {
  return await csrfTokenManager.getToken();
}

/**
 * Enhanced fetch function with automatic CSRF token handling
 */
export async function fetchWithCSRF(url: string, options: RequestInit = {}): Promise<Response> {
  const token = await getCSRFToken();
  
  const headers = new Headers(options.headers);
  headers.set('X-CSRF-TOKEN', token);
  headers.set('X-Requested-With', 'XMLHttpRequest');
  
  // Ensure credentials are included for cookie-based auth
  const enhancedOptions: RequestInit = {
    ...options,
    headers,
    credentials: 'same-origin',
  };

  const response = await fetch(url, enhancedOptions);
  
  // If we get a 419 (CSRF token mismatch), clear cache and retry once
  if (response.status === 419) {
    console.warn('CSRF token mismatch detected, attempting to refresh...');
    csrfTokenManager.clearCache();
    
    const freshToken = await getCSRFToken();
    headers.set('X-CSRF-TOKEN', freshToken);
    
    const retryOptions = {
      ...enhancedOptions,
      headers,
    };
    
    return await fetch(url, retryOptions);
  }
  
  return response;
}

/**
 * Check if user has required permissions
 */
export function hasPermission(permission: string): boolean {
  // Get user permissions from window or data attribute
  const userPermissions = (window as any).userPermissions || 
                         JSON.parse(document.body.getAttribute('data-user-permissions') || '[]');
  
  return Array.isArray(userPermissions) && userPermissions.includes(permission);
}

/**
 * Enhanced error handling for API responses
 */
export function handleAPIError(response: Response, context: string = 'API request'): string {
  if (response.status === 419) {
    return 'Your session has expired. Please refresh the page and try again.';
  }
  
  if (response.status === 403) {
    return 'You do not have permission to perform this action. Please contact your administrator.';
  }
  
  if (response.status === 500) {
    return 'A server error occurred. Please try again or contact support if the problem persists.';
  }
  
  if (response.status === 422) {
    return 'The submitted data is invalid. Please check your inputs and try again.';
  }
  
  return `${context} failed with status ${response.status}. Please try again.`;
} 