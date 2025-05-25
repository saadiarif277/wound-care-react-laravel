/**
 * API utility functions for making authenticated requests
 */

/**
 * Get CSRF token from meta tag
 */
function getCsrfToken(): string | null {
  const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
  return metaTag ? metaTag.content : null;
}

/**
 * Default headers for API requests
 */
function getDefaultHeaders(): HeadersInit {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  const csrfToken = getCsrfToken();
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken;
  }

  return headers;
}

/**
 * Make an authenticated API request
 */
export async function apiRequest(url: string, options: RequestInit = {}): Promise<Response> {
  const defaultOptions: RequestInit = {
    headers: getDefaultHeaders(),
    credentials: 'same-origin', // Include cookies for session-based auth
  };

  const mergedOptions: RequestInit = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...options.headers,
    },
  };

  return fetch(url, mergedOptions);
}

/**
 * Make a GET request
 */
export async function apiGet(url: string, options: RequestInit = {}): Promise<Response> {
  return apiRequest(url, { ...options, method: 'GET' });
}

/**
 * Make a POST request
 */
export async function apiPost(url: string, data?: any, options: RequestInit = {}): Promise<Response> {
  const requestOptions: RequestInit = {
    ...options,
    method: 'POST',
  };

  if (data) {
    requestOptions.body = JSON.stringify(data);
  }

  return apiRequest(url, requestOptions);
}

/**
 * Make a PUT request
 */
export async function apiPut(url: string, data?: any, options: RequestInit = {}): Promise<Response> {
  const requestOptions: RequestInit = {
    ...options,
    method: 'PUT',
  };

  if (data) {
    requestOptions.body = JSON.stringify(data);
  }

  return apiRequest(url, requestOptions);
}

/**
 * Make a DELETE request
 */
export async function apiDelete(url: string, options: RequestInit = {}): Promise<Response> {
  return apiRequest(url, { ...options, method: 'DELETE' });
}

/**
 * Handle API response and extract JSON
 */
export async function handleApiResponse<T = any>(response: Response): Promise<T> {
  if (!response.ok) {
    const errorText = await response.text();
    let errorMessage = `HTTP ${response.status}: ${response.statusText}`;

    try {
      const errorJson = JSON.parse(errorText);
      errorMessage = errorJson.message || errorMessage;
    } catch {
      // If not JSON, use the text as is
      errorMessage = errorText || errorMessage;
    }

    throw new Error(errorMessage);
  }

  const contentType = response.headers.get('content-type');
  if (contentType && contentType.includes('application/json')) {
    return response.json();
  }

  return response.text() as any;
}

/**
 * Convenience function for making API calls and handling responses
 */
export async function apiCall<T = any>(url: string, options: RequestInit = {}): Promise<T> {
  const response = await apiRequest(url, options);
  return handleApiResponse<T>(response);
}
