/**
 * Sanctum authentication helper
 */

/**
 * Get CSRF cookie from Sanctum
 * This must be called before making any authenticated requests
 */
export async function getCsrfCookie(): Promise<void> {
  try {
    const response = await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    if (!response.ok) {
      throw new Error(`Failed to get CSRF cookie: ${response.status}`);
    }
  } catch (error) {
    console.error('Failed to get CSRF cookie:', error);
    throw new Error('Failed to initialize authentication');
  }
}

/**
 * Initialize Sanctum authentication
 * Call this when the app loads or before making authenticated requests
 */
export async function initializeSanctum(): Promise<void> {
  await getCsrfCookie();
}

/**
 * Check if user is authenticated by making a test request
 */
export async function checkAuth(): Promise<boolean> {
  try {
    const response = await fetch('/api/user', {
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    return response.ok;
  } catch (error) {
    return false;
  }
}