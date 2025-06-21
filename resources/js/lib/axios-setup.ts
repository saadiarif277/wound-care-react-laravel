import axios from 'axios';

// Helper function to get cookie by name
function getCookie(name: string): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null;
    return null;
}

// Helper function to refresh CSRF token
async function refreshCSRFToken(): Promise<string | null> {
    try {
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            // Update the meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', data.token);
            }
            return data.token;
        }
    } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
    }
    return null;
}

// Configure axios defaults for the application
export function setupAxios() {
    // Set base URL
    axios.defaults.baseURL = window.location.origin;

    // Always send cookies with requests
    axios.defaults.withCredentials = true;

    // Set default headers
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['Content-Type'] = 'application/json';

    // Get CSRF token from meta tag if available
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }

    // Get XSRF token from cookie if available
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    // Add request interceptor to ensure tokens are fresh
    axios.interceptors.request.use(
        config => {
            // Update CSRF token on each request
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                config.headers['X-CSRF-TOKEN'] = csrfToken;
            }

            // Update XSRF token if available
            const xsrfToken = getCookie('XSRF-TOKEN');
            if (xsrfToken) {
                config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
            }

            return config;
        },
        error => Promise.reject(error)
    );

    // Add response interceptor for error handling with retry logic
    axios.interceptors.response.use(
        response => response,
        async error => {
            if (error.response?.status === 401) {
                // Session expired, redirect to login
                console.log('Session expired, redirecting to login...');
                window.location.href = '/login';
            } else if (error.response?.status === 419) {
                // CSRF token mismatch, try to refresh and retry once
                console.log('CSRF token mismatch detected, attempting to refresh...');

                const newToken = await refreshCSRFToken();
                if (newToken && error.config && !error.config._retry) {
                    error.config._retry = true;
                    error.config.headers['X-CSRF-TOKEN'] = newToken;
                    console.log('Retrying request with new CSRF token...');
                    return axios.request(error.config);
                } else {
                    console.log('CSRF token refresh failed or retry limit reached, reloading page...');
                    window.location.reload();
                }
            }
            return Promise.reject(error);
        }
    );
}

// Export configured axios instance
export default axios;
