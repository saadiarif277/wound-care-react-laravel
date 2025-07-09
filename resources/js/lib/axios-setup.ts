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

    // Axios will automatically handle the XSRF-TOKEN cookie.
    // No need to manually set X-CSRF-TOKEN or X-XSRF-TOKEN here.

    // Add request interceptor to handle DocuSeal's specific auth
    axios.interceptors.request.use(
        async config => {
            const docuSealRegex = /(?:^https?:\/\/)?(?:[^\/]*\.)?docuseal\.com/i;
            const isDocusealRequest = docuSealRegex.test((config.url ?? '').toString());

            if (isDocusealRequest) {
                // Remove Laravel-specific headers that Docuseal does not expect
                delete config.headers['X-Requested-With'];

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
                // CSRF token mismatch, usually indicates an expired session state.
                // The safest action is to reload the page to get a fresh session and token.
                console.log('CSRF token mismatch (419), reloading page...');
                window.location.reload();
            }
            return Promise.reject(error);
        }
    );
}

// Export configured axios instance
export default axios;
