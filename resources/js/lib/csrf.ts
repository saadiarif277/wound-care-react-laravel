/**
 * CSRF Token Management Utilities
 */

export interface CSRFTokenResponse {
    token: string;
    session_id: string;
    timestamp: number;
}

/**
 * Get the current CSRF token from the meta tag
 */
export function getCurrentCSRFToken(): string | null {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag?.getAttribute('content') || null;
}

/**
 * Refresh the CSRF token from the server
 */
export async function refreshCSRFToken(): Promise<string | null> {
    try {
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data: CSRFTokenResponse = await response.json();

            // Update the meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', data.token);
            }

            console.log('CSRF token refreshed successfully', {
                session_id: data.session_id,
                timestamp: data.timestamp,
            });

            return data.token;
        } else {
            console.error('Failed to refresh CSRF token:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Error refreshing CSRF token:', error);
    }

    return null;
}

/**
 * Validate that we have a CSRF token before making a request
 */
export async function ensureValidCSRFToken(): Promise<string | null> {
    let token = getCurrentCSRFToken();

    if (!token) {
        console.log('No CSRF token found, attempting to refresh...');
        token = await refreshCSRFToken();
    }

    return token;
}

/**
 * Add CSRF token to FormData
 */
export function addCSRFTokenToFormData(formData: FormData, token?: string): void {
    const csrfToken = token || getCurrentCSRFToken();
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
}

/**
 * Add CSRF token to regular object data
 */
export function addCSRFTokenToData(data: Record<string, any>, token?: string): Record<string, any> {
    const csrfToken = token || getCurrentCSRFToken();
    if (csrfToken) {
        return {
            ...data,
            _token: csrfToken,
        };
    }
    return data;
}

/**
 * Test CSRF token validity
 */
export async function testCSRFToken(): Promise<boolean> {
    try {
        const token = getCurrentCSRFToken();
        if (!token) return false;

        const response = await fetch('/test/csrf', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ test: true }),
        });

        return response.ok;
    } catch (error) {
        console.error('Error testing CSRF token:', error);
        return false;
    }
}
