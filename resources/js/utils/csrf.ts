/**
 * Enhanced CSRF Token Management Utility
 * Handles CSRF token refresh and validation to prevent 419 errors
 */

declare global {
    interface Window {
        Inertia?: {
            props?: {
                csrf_token?: string;
            };
        };
    }
}

let csrfToken: string | null = null;
let tokenRefreshInterval: NodeJS.Timeout | null = null;

/**
 * Get the current CSRF token with multiple fallback methods
 */
export function getCsrfToken(): string {
    if (!csrfToken) {
        // Method 1: Try to get from meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            csrfToken = metaTag.getAttribute('content') || '';
        }

        // Method 2: Try to get from Inertia props
        if (!csrfToken && window.Inertia?.props?.csrf_token) {
            csrfToken = window.Inertia.props.csrf_token;
        }

        // Method 3: Try to get from XSRF-TOKEN cookie
        if (!csrfToken) {
            const cookies = document.cookie.split(';');
            for (const cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'XSRF-TOKEN') {
                    csrfToken = decodeURIComponent(value);
                    break;
                }
            }
        }

        // Method 4: Try to get from _token cookie
        if (!csrfToken) {
            const cookies = document.cookie.split(';');
            for (const cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === '_token') {
                    csrfToken = decodeURIComponent(value);
                    break;
                }
            }
        }
    }

    return csrfToken || '';
}

/**
 * Refresh the CSRF token by making a request to get a new one
 */
export async function refreshCsrfToken(): Promise<string> {
    try {
        console.log('🔄 Refreshing CSRF token...');

        const response = await fetch('/csrf-token', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            csrfToken = data.token || '';

            // Update the meta tag if it exists
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag && csrfToken && csrfToken.length > 0) {
                metaTag.setAttribute('content', csrfToken as string);
            }

            console.log('✅ CSRF token refreshed successfully');
            return csrfToken;
        } else {
            console.warn('⚠️ Failed to refresh CSRF token:', response.status, response.statusText);
        }
    } catch (error) {
        console.warn('⚠️ Failed to refresh CSRF token:', error);
    }

    return getCsrfToken();
}

/**
 * Set up automatic CSRF token refresh for long-running forms
 */
export function setupCsrfTokenRefresh(): void {
    // Clear any existing interval
    if (tokenRefreshInterval) {
        clearInterval(tokenRefreshInterval);
    }

    // Refresh token every 10 minutes (more frequent for better reliability)
    tokenRefreshInterval = setInterval(async () => {
        await refreshCsrfToken();
    }, 10 * 60 * 1000);

    // Also refresh token when page becomes visible (user returns to tab)
    document.addEventListener('visibilitychange', async () => {
        if (!document.hidden) {
            await refreshCsrfToken();
        }
    });

    // Refresh token on page load
    refreshCsrfToken();

    // Refresh token before form submissions
    document.addEventListener('submit', async (event) => {
        const form = event.target as HTMLFormElement;
        if (form && !form.hasAttribute('data-csrf-refreshed')) {
            await refreshCsrfToken();
            form.setAttribute('data-csrf-refreshed', 'true');
        }
    });

    console.log('🛡️ CSRF token refresh system initialized');
}

/**
 * Get headers with CSRF token for fetch requests
 */
export function getCsrfHeaders(): Record<string, string> {
    const token = getCsrfToken();
    return {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
    };
}

/**
 * Enhanced fetch with automatic CSRF token handling and retry logic
 */
export async function fetchWithCsrf(
    url: string,
    options: RequestInit = {}
): Promise<Response> {
    const headers = {
        ...getCsrfHeaders(),
        ...options.headers,
    };

    let response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    });

    // If we get a 419 error, try refreshing the token and retry once
    if (response.status === 419) {
        console.log('🔄 CSRF token expired, refreshing and retrying...');
        await refreshCsrfToken();

        const retryHeaders = {
            ...getCsrfHeaders(),
            ...options.headers,
        };

        response = await fetch(url, {
            ...options,
            headers: retryHeaders,
            credentials: 'same-origin',
        });

        // If still getting 419, try one more time with a fresh token
        if (response.status === 419) {
            console.log('🔄 Second attempt failed, trying one more time...');
            await refreshCsrfToken();

            const finalRetryHeaders = {
                ...getCsrfHeaders(),
                ...options.headers,
            };

            response = await fetch(url, {
                ...options,
                headers: finalRetryHeaders,
                credentials: 'same-origin',
            });
        }
    }

    return response;
}

/**
 * Clean up the CSRF refresh interval
 */
export function cleanupCsrfRefresh(): void {
    if (tokenRefreshInterval) {
        clearInterval(tokenRefreshInterval);
        tokenRefreshInterval = null;
    }
}
