import axios from 'axios';

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
            return config;
        },
        error => Promise.reject(error)
    );
    
    // Add response interceptor for error handling
    axios.interceptors.response.use(
        response => response,
        error => {
            if (error.response?.status === 401) {
                // Session expired, redirect to login
                window.location.href = '/login';
            } else if (error.response?.status === 419) {
                // CSRF token mismatch, refresh the page
                window.location.reload();
            }
            return Promise.reject(error);
        }
    );
}

// Helper function to get cookie value
function getCookie(name: string): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() || null;
    }
    return null;
}

// Export configured axios instance
export default axios;