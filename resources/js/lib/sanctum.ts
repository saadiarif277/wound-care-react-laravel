/**
 * Sanctum authentication helper - Simplified for Inertia.js
 * 
 * Note: Inertia.js handles sessions automatically, so most of these 
 * functions are not needed in a typical Inertia application.
 */

/**
 * Get CSRF cookie from Sanctum (only needed for direct API calls)
 * This must be called before making any authenticated requests outside of Inertia
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