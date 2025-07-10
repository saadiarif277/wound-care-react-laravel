import axios from 'axios';

// Helper function to get cookie by name
function getCookie(name: string): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null;
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

    // Set default headers
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['Content-Type'] = 'application/json';

    // Set CSRF token from meta tag for Laravel
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        console.log('🔐 CSRF token set from meta tag:', csrfToken.substring(0, 10) + '...');
    } else {
        console.warn('⚠️ No CSRF token found in meta tag!');
    }

    // Add request interceptor to handle DocuSeal's specific auth and CSRF tokens
    axios.interceptors.request.use(
        async config => {
            const docuSealRegex = /(?:^https?:\/\/)?(?:[^\/]*\.)?docuseal\.com/i;
            const isDocusealRequest = docuSealRegex.test((config.url ?? '').toString());

            if (isDocusealRequest) {
                // Remove Laravel-specific headers that Docuseal does not expect
                delete config.headers['X-Requested-With'];
                delete config.headers['X-CSRF-TOKEN'];

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
                // For Laravel requests, ensure CSRF token is fresh
                const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                if (csrfToken) {
                    config.headers['X-CSRF-TOKEN'] = csrfToken;
                    console.log('🔄 Fresh CSRF token added to request:', csrfToken.substring(0, 10) + '...', 'for URL:', config.url);
                } else {
                    console.warn('⚠️ No fresh CSRF token available for request to:', config.url);
                }
            }
            return config;
        },
        error => Promise.reject(error)
    );

    // Add response interceptor for unified error handling
    axios.interceptors.response.use(
        response => response,
        async error => {
            const status = error.response?.status;

            if (status === 401) {
                // Session expired, redirect to login
                console.log('Session expired (401), redirecting to login...');
                window.location.href = '/login';
            } else if (status === 419) {
                // CSRF token mismatch, try to refresh the token first
                console.warn('CSRF token mismatch (419), refreshing token...');
                const newCsrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                if (newCsrfToken) {
                    axios.defaults.headers.common['X-CSRF-TOKEN'] = newCsrfToken;
                    console.log('CSRF token refreshed, you can retry the request');
                } else {
                    console.error('Could not find fresh CSRF token, page reload may be needed');
                }
                // Don't auto-reload - let the application handle the error gracefully
            }
            return Promise.reject(error);
        }
    );
}

// Export configured axios instance
export default axios;
