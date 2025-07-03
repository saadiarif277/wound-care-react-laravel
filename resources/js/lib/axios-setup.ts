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

// --- Authentication Token Helpers ---
function getAuthToken(): string | null {
    return localStorage.getItem('auth_token');
}

async function fetchAuthToken(): Promise<string | null> {
    try {
        const resp = await fetch('/auth/token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });
        if (resp.ok) {
            const data = await resp.json();
            localStorage.setItem('auth_token', data.token);
            return data.token as string;
        }
    } catch (e) {
        console.error('Failed to fetch auth token', e);
    }
    return null;
}

// --- Docuseal JWT Token Helpers ---
function getDocusealToken(): string | null {
    return sessionStorage.getItem('docuseal_jwt');
}

async function fetchDocusealToken(): Promise<string | null> {
    try {
        const resp = await fetch('/api/v1/docuseal/token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        if (resp.ok) {
            const data = await resp.json();
            const token = data.token as string;
            if (token) {
                sessionStorage.setItem('docuseal_jwt', token);
                return token;
            }
        }
    } catch (e) {
        console.error('Failed to fetch Docuseal token', e);
    }
    return null;
}

// Configure axios defaults for the application
export function setupAxios() {
    // Set base URL
    axios.defaults.baseURL = window.location.origin;

    // Always send cookies with requests
    axios.defaults.withCredentials = true;

    // Attempt to ensure we have an auth token available (fire and forget)
    if (!getAuthToken()) {
        // silent fetch – errors already logged inside helper
        fetchAuthToken();
    }

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
        async config => {
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

                        // Detect if the request is going to Docuseal API
            const docuSealRegex = /(?:^https?:\/\/)?(?:[^\/]*\.)?docuseal\.com/i;
            const isDocusealRequest = docuSealRegex.test((config.url ?? '').toString());

            if (isDocusealRequest) {
                // Remove Laravel-specific headers that Docuseal does not expect
                delete config.headers['X-Requested-With'];
                delete config.headers['X-CSRF-TOKEN'];
                delete config.headers['X-XSRF-TOKEN'];

                // Ensure JSON accept header
                config.headers['Accept'] = 'application/json';

                // Attach Docuseal JWT token
                let dsToken = getDocusealToken();
                if (!dsToken) {
                    dsToken = await fetchDocusealToken();
                }
                if (dsToken) {
                    config.headers['Authorization'] = `Bearer ${dsToken}`;
                }
            } else {
                // Attach Authorization header if we have a Sanctum token (our own API)
                let authToken = getAuthToken();
                if (!authToken) {
                    authToken = await fetchAuthToken();
                }
                if (authToken) {
                    config.headers['Authorization'] = `Bearer ${authToken}`;
                }
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
                // Try refreshing auth token once
                if (!error.config?._authRetry) {
                    const newToken = await fetchAuthToken();
                    if (newToken) {
                        error.config._authRetry = true;
                        error.config.headers['Authorization'] = `Bearer ${newToken}`;
                        return axios.request(error.config);
                    }
                }
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
