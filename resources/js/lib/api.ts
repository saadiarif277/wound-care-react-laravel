/**
 * API utility functions for making authenticated requests
 */

import axios, { AxiosResponse } from 'axios';

// Types
export interface Role {
    id: number;
    name: string;
    slug: string;
    description: string;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    slug: string;
    description?: string;
    category?: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive' | 'suspended';
    roles: Role[];
    last_login?: string;
    created_at: string;
}

export interface AuditLog {
    id: number;
    action: string;
    user: string;
    target_role?: string;
    target_user?: string;
    changes: string;
    timestamp: string;
    ip_address?: string;
    risk_level: 'low' | 'medium' | 'high' | 'critical';
    requires_review?: boolean;
    reason?: string;
}

export interface ValidationRules {
    rules: {
        [key: string]: {
            required?: boolean;
            max_length?: number;
            unique?: boolean;
            pattern?: string;
            description?: string;
            min_items?: number;
            required_for_updates?: boolean;
        };
    };
    messages: {
        [key: string]: string;
    };
}

export interface ApiResponse<T> {
    data?: T;
    message?: string;
    error?: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

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

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true; // Enable sending cookies with requests

// Add CSRF token if available
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Add request interceptor to handle 401 responses
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Redirect to login page if unauthorized
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// API Error handling
export class ApiError extends Error {
    public status: number;
    public data: any;

    constructor(message: string, status: number, data?: any) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

const handleApiError = (error: any): never => {
    if (error.response) {
        const { status, data } = error.response;
        const message = data?.message || data?.error || 'An error occurred';
        throw new ApiError(message, status, data);
    } else if (error.request) {
        throw new ApiError('Network error - please check your connection', 0);
    } else {
        throw new ApiError(error.message || 'An unexpected error occurred', 0);
    }
};

// Role Management API
export const roleApi = {
    // Get all roles
    async getRoles(): Promise<Role[]> {
        try {
            const response: AxiosResponse<{ roles: Role[] }> = await axios.get('/api/roles');
            return response.data.roles;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get single role
    async getRole(id: number): Promise<Role> {
        try {
            const response: AxiosResponse<{ role: Role }> = await axios.get(`/api/roles/${id}`);
            return response.data.role;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Create role
    async createRole(data: {
        name: string;
        slug: string;
        description: string;
        permissions: number[];
    }): Promise<Role> {
        try {
            const response: AxiosResponse<{ role: Role; message: string }> = await axios.post('/api/roles', data);
            return response.data.role;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Update role
    async updateRole(id: number, data: {
        name: string;
        slug: string;
        description: string;
        permissions: number[];
        reason: string;
    }): Promise<Role> {
        try {
            const response: AxiosResponse<{ role: Role; message: string }> = await axios.put(`/api/roles/${id}`, data);
            return response.data.role;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Delete role
    async deleteRole(id: number, reason: string): Promise<void> {
        try {
            await axios.delete(`/api/roles/${id}`, { data: { reason } });
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get validation rules
    async getValidationRules(): Promise<ValidationRules> {
        try {
            const response: AxiosResponse<ValidationRules> = await axios.get('/api/roles/validation/rules');
            return response.data;
        } catch (error) {
            handleApiError(error);
        }
    },
};

// Access Control API
export const accessControlApi = {
    // Get users with pagination and filters
    async getUsers(params?: {
        search?: string;
        role?: string;
        status?: string;
        page?: number;
    }): Promise<PaginatedResponse<User>> {
        try {
            const response: AxiosResponse<PaginatedResponse<User>> = await axios.get('/api/access-control/users', { params });
            return response.data;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Assign role to user
    async assignRole(data: {
        user_id: number;
        role_id: number;
        reason: string;
    }): Promise<User> {
        try {
            const response: AxiosResponse<{ user: User; message: string }> = await axios.post('/api/access-control/assign-role', data);
            return response.data.user;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Remove role from user
    async removeRole(data: {
        user_id: number;
        role_id: number;
        reason: string;
    }): Promise<User> {
        try {
            const response: AxiosResponse<{ user: User; message: string }> = await axios.post('/api/access-control/remove-role', data);
            return response.data.user;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Toggle user status
    async toggleUserStatus(data: {
        user_id: number;
        status: 'active' | 'inactive' | 'suspended';
        reason: string;
    }): Promise<User> {
        try {
            const response: AxiosResponse<{ user: User; message: string }> = await axios.post('/api/access-control/toggle-status', data);
            return response.data.user;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get access control statistics
    async getStats(): Promise<{
        total_users: number;
        active_users: number;
        inactive_users: number;
        suspended_users: number;
        role_distribution: Array<{ role: string; count: number }>;
        recent_changes: Array<{
            action: string;
            user: string;
            performed_by: string;
            timestamp: string;
            risk_level: string;
        }>;
        security_alerts: number;
    }> {
        try {
            const response = await axios.get('/api/access-control/stats');
            return response.data.stats;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get security monitoring data
    async getSecurityMonitoring(): Promise<{
        high_risk_events: Array<{
            id: number;
            event_type: string;
            performed_by: string;
            target_user: string;
            risk_level: string;
            risk_factors: string[];
            timestamp: string;
            requires_review: boolean;
            reviewed: boolean;
        }>;
        pending_reviews: number;
        failed_logins_24h: number;
    }> {
        try {
            const response = await axios.get('/api/access-control/security-monitoring');
            return response.data.security_monitoring;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Mark audit log as reviewed
    async markAsReviewed(data: {
        audit_log_id: number;
        review_notes?: string;
    }): Promise<void> {
        try {
            await axios.post('/api/access-control/mark-reviewed', data);
        } catch (error) {
            handleApiError(error);
        }
    },
};

// RBAC Management API
export const rbacApi = {
    // Get RBAC overview
    async getOverview(): Promise<{
        total_roles: number;
        total_permissions: number;
        total_users: number;
        role_distribution: Array<{ role: string; count: number; percentage: number }>;
        permission_usage: Array<{ permission: string; usage_count: number; roles: string[] }>;
        recent_activity: Array<{
            action: string;
            user: string;
            target: string;
            timestamp: string;
            risk_level: string;
        }>;
    }> {
        try {
            const response = await axios.get('/api/rbac/overview');
            return response.data;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get role hierarchy
    async getRoleHierarchy(): Promise<Array<{
        id: number;
        name: string;
        level: number;
        permissions_count: number;
        users_count: number;
        risk_level: string;
        can_access_financial: boolean;
        can_access_phi: boolean;
    }>> {
        try {
            const response = await axios.get('/api/rbac/role-hierarchy');
            return response.data.hierarchy;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get permission usage statistics
    async getPermissionUsage(): Promise<Array<{
        permission: string;
        category: string;
        usage_count: number;
        roles: string[];
        risk_level: string;
    }>> {
        try {
            const response = await axios.get('/api/rbac/permission-usage');
            return response.data.permissions;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get security audit logs
    async getSecurityAudit(): Promise<AuditLog[]> {
        try {
            const response: AxiosResponse<{ audit_logs: AuditLog[] }> = await axios.get('/api/rbac/security-audit');
            return response.data.audit_logs;
        } catch (error) {
            handleApiError(error);
        }
    },

    // Get RBAC metrics
    async getMetrics(): Promise<{
        role_growth: Array<{ date: string; count: number }>;
        permission_changes: Array<{ date: string; changes: number }>;
        security_events: Array<{ date: string; events: number; high_risk: number }>;
        user_role_distribution: Array<{ role: string; count: number }>;
    }> {
        try {
            const response = await axios.get('/api/rbac/metrics');
            return response.data;
        } catch (error) {
            handleApiError(error);
        }
    },
};

// Utility functions
export const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export const formatRiskLevel = (level: string): { color: string; label: string } => {
    switch (level) {
        case 'critical':
            return { color: 'red', label: 'Critical' };
        case 'high':
            return { color: 'orange', label: 'High' };
        case 'medium':
            return { color: 'yellow', label: 'Medium' };
        case 'low':
        default:
            return { color: 'green', label: 'Low' };
    }
};

export const generateSlug = (name: string): string => {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
};

export const api = {
    // Orders API
    orders: {
        async getAll(params?: {
            search?: string;
            status?: string;
            date_from?: string;
            date_to?: string;
            sales_rep_id?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/orders${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getAnalytics(params?: {
            date_from?: string;
            date_to?: string;
        }): Promise<any> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/orders/analytics${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async create(orderData: any): Promise<any> {
            const response = await apiPost('/api/orders', orderData);
            return handleApiResponse(response);
        },

        async update(orderId: string, orderData: any): Promise<any> {
            const response = await apiPut(`/api/orders/${orderId}`, orderData);
            return handleApiResponse(response);
        },

        async approve(orderId: string, data: any): Promise<any> {
            const response = await apiPost(`/api/orders/${orderId}/approve`, data);
            return handleApiResponse(response);
        },

        async reject(orderId: string, data: any): Promise<any> {
            const response = await apiPost(`/api/orders/${orderId}/reject`, data);
            return handleApiResponse(response);
        }
    },

    // Eligibility API
    eligibility: {
        async checkEligibility(data: {
            patient: {
                first_name: string;
                last_name: string;
                date_of_birth: string;
                gender: string;
                member_id: string;
            };
            payer: {
                name: string;
                id: string;
                insurance_type: string;
            };
            service_date: string;
            service_codes: Array<{ code: string; type: string }>;
        }): Promise<any> {
            const response = await apiPost('/api/v1/eligibility/check', data);
            return handleApiResponse(response);
        },

        async getHistory(): Promise<any[]> {
            const response = await apiGet('/api/v1/eligibility/history');
            return handleApiResponse(response);
        },

        async getSummary(): Promise<any> {
            const response = await apiGet('/api/v1/eligibility/summary');
            return handleApiResponse(response);
        }
    },

    // Medicare MAC Validation API
    medicareValidation: {
        async quickCheck(data: {
            patient_zip: string;
            service_codes: string[];
            wound_type: string;
            service_date: string;
        }): Promise<any> {
            const response = await apiPost('/api/v1/medicare-validation/quick-check', data);
            return handleApiResponse(response);
        },

        async thoroughValidate(data: any): Promise<any> {
            const response = await apiPost('/api/v1/medicare-validation/thorough-validate', data);
            return handleApiResponse(response);
        },

        async getDashboard(): Promise<any> {
            const response = await apiGet('/api/v1/medicare-validation/dashboard');
            return handleApiResponse(response);
        }
    },

    // Clinical Opportunities API
    clinicalOpportunities: {
        async scanOpportunities(data: {
            clinical_data: any;
            wound_type: string;
            patient_data: any;
            selected_products: any[];
        }): Promise<any> {
            const response = await apiPost('/api/v1/clinical-opportunities/scan', data);
            return handleApiResponse(response);
        },

        async getBySpecialty(specialty: string): Promise<any[]> {
            const response = await apiGet(`/api/v1/clinical-opportunities/by-specialty/${specialty}`);
            return handleApiResponse(response);
        },

        async getAnalyticsSummary(): Promise<any> {
            const response = await apiGet('/api/v1/clinical-opportunities/analytics/summary');
            return handleApiResponse(response);
        }
    },

    // Product Requests API
    productRequests: {
        async getAll(params?: {
            search?: string;
            status?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/product-requests${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async create(requestData: any): Promise<any> {
            const response = await apiPost('/api/product-requests', requestData);
            return handleApiResponse(response);
        },

        async update(requestId: string, requestData: any): Promise<any> {
            const response = await apiPut(`/api/product-requests/${requestId}`, requestData);
            return handleApiResponse(response);
        },

        async getById(requestId: string): Promise<any> {
            const response = await apiGet(`/api/product-requests/${requestId}`);
            return handleApiResponse(response);
        }
    },

    // Products API
    products: {
        async getAll(params?: {
            search?: string;
            category?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/products${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(productId: string): Promise<any> {
            const response = await apiGet(`/api/products/${productId}`);
            return handleApiResponse(response);
        },

        async search(query: string): Promise<any[]> {
            const response = await apiGet(`/api/products/search?q=${encodeURIComponent(query)}`);
            return handleApiResponse(response);
        }
    },

    // Providers API
    providers: {
        async getAll(params?: {
            search?: string;
            facility_id?: string;
            organization_id?: string;
            specialty?: string;
            status?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/providers${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(id: string): Promise<any> {
            const response = await apiGet(`/api/providers/${id}`);
            return handleApiResponse(response);
        },

        async create(data: any): Promise<any> {
            const response = await apiPost('/api/providers', data);
            return handleApiResponse(response);
        },

        async update(id: string, data: any): Promise<any> {
            const response = await apiPut(`/api/providers/${id}`, data);
            return handleApiResponse(response);
        },

        async delete(id: string): Promise<any> {
            const response = await apiDelete(`/api/providers/${id}`);
            return handleApiResponse(response);
        }
    },

    // Facilities API
    facilities: {
        async getAll(params?: {
            search?: string;
            organization_id?: string;
            status?: string;
            type?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/facilities${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(id: string): Promise<any> {
            const response = await apiGet(`/api/facilities/${id}`);
            return handleApiResponse(response);
        },

        async create(data: any): Promise<any> {
            const response = await apiPost('/api/facilities', data);
            return handleApiResponse(response);
        },

        async update(id: string, data: any): Promise<any> {
            const response = await apiPut(`/api/facilities/${id}`, data);
            return handleApiResponse(response);
        },

        async delete(id: string): Promise<any> {
            const response = await apiDelete(`/api/facilities/${id}`);
            return handleApiResponse(response);
        }
    },

    // DocuSeal API
    docuseal: {
        async getSubmissions(params?: {
            status?: string;
            document_type?: string;
            date_from?: string;
            date_to?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/v1/admin/docuseal/submissions${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async generateDocument(orderId: string): Promise<any> {
            const response = await apiPost('/api/v1/admin/docuseal/generate-document', {
                order_id: orderId
            });
            return handleApiResponse(response);
        },

        async getSubmissionStatus(submissionId: string): Promise<any> {
            const response = await apiGet(`/api/v1/admin/docuseal/submissions/${submissionId}/status`);
            return handleApiResponse(response);
        },

        async downloadDocument(submissionId: string): Promise<any> {
            const response = await apiGet(`/api/v1/admin/docuseal/submissions/${submissionId}/download`);
            return response; // This will redirect to document URL
        }
    },

    // Commission API
    commission: {
        async getRecords(params?: {
            status?: string;
            rep_id?: string;
            date_from?: string;
            date_to?: string;
            search?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/commission/records${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getSummary(params?: {
            status?: string;
            rep_id?: string;
            date_from?: string;
            date_to?: string;
        }): Promise<any> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/commission/records/summary${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async approveRecord(recordId: string, data: { notes?: string }): Promise<any> {
            const response = await apiPost(`/api/commission/records/${recordId}/approve`, data);
            return handleApiResponse(response);
        },

        async getRecord(recordId: string): Promise<any> {
            const response = await apiGet(`/api/commission/records/${recordId}`);
            return handleApiResponse(response);
        },

        async getPayouts(params?: {
            status?: string;
            rep_id?: string;
            period_start?: string;
            period_end?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/commission/payouts${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getRules(params?: {
            target_type?: string;
            active?: boolean;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/commission/rules${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async createRule(data: {
            target_type: string;
            target_id: number;
            percentage_rate: number;
            valid_from: string;
            valid_to?: string;
            is_active?: boolean;
            description?: string;
        }): Promise<any> {
            const response = await apiPost('/api/commission/rules', data);
            return handleApiResponse(response);
        }
    },

    // Organizations API
    organizations: {
        async getAll(params?: {
            search?: string;
            status?: string;
            type?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/organizations${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(id: string): Promise<any> {
            const response = await apiGet(`/api/organizations/${id}`);
            return handleApiResponse(response);
        },

        async create(data: any): Promise<any> {
            const response = await apiPost('/api/organizations', data);
            return handleApiResponse(response);
        },

        async update(id: string, data: any): Promise<any> {
            const response = await apiPut(`/api/organizations/${id}`, data);
            return handleApiResponse(response);
        },

        async delete(id: string): Promise<any> {
            const response = await apiDelete(`/api/organizations/${id}`);
            return handleApiResponse(response);
        },

        async getStats(): Promise<any> {
            const response = await apiGet('/api/organizations/stats');
            return handleApiResponse(response);
        }
    },

    // Onboarding API
    onboarding: {
        async getRecords(params?: {
            search?: string;
            status?: string;
            stage?: string;
            assigned_to?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/onboarding/records${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(id: string): Promise<any> {
            const response = await apiGet(`/api/onboarding/records/${id}`);
            return handleApiResponse(response);
        },

        async updateStage(id: string, data: { stage: string; completion_percentage: number; notes?: string }): Promise<any> {
            const response = await apiPut(`/api/onboarding/records/${id}/stage`, data);
            return handleApiResponse(response);
        },

        async updateStatus(id: string, data: { status: string; notes?: string }): Promise<any> {
            const response = await apiPut(`/api/onboarding/records/${id}/status`, data);
            return handleApiResponse(response);
        }
    },

    // Analytics API
    analytics: {
        async getCustomerAnalytics(params?: {
            search?: string;
            date_from?: string;
            date_to?: string;
            organization_id?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/analytics/customers${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getRevenueAnalytics(params?: {
            date_from?: string;
            date_to?: string;
            granularity?: 'daily' | 'weekly' | 'monthly';
        }): Promise<any> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/analytics/revenue${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getPerformanceMetrics(): Promise<any> {
            const response = await apiGet('/api/analytics/performance');
            return handleApiResponse(response);
        }
    },

    // Sales Reps API
    salesReps: {
        async getAll(params?: {
            search?: string;
            status?: string;
            territory?: string;
            manager_id?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/sales-reps${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getById(id: string): Promise<any> {
            const response = await apiGet(`/api/sales-reps/${id}`);
            return handleApiResponse(response);
        },

        async create(data: {
            name: string;
            email: string;
            phone: string;
            territory: string;
            commission_rate: number;
            manager_id?: string;
            status?: string;
        }): Promise<any> {
            const response = await apiPost('/api/sales-reps', data);
            return handleApiResponse(response);
        },

        async update(id: string, data: any): Promise<any> {
            const response = await apiPut(`/api/sales-reps/${id}`, data);
            return handleApiResponse(response);
        },

        async delete(id: string): Promise<any> {
            const response = await apiDelete(`/api/sales-reps/${id}`);
            return handleApiResponse(response);
        },

        async getAnalytics(params?: {
            date_from?: string;
            date_to?: string;
            rep_id?: string;
        }): Promise<any> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/sales-reps/analytics${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async getSubRepApprovals(params?: {
            search?: string;
            status?: string;
            primary_rep_id?: string;
            page?: number;
        }): Promise<PaginatedResponse<any>> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/sales-reps/sub-rep-approvals${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        },

        async approveSubRep(approvalId: string, data: { notes?: string }): Promise<any> {
            const response = await apiPost(`/api/sales-reps/sub-rep-approvals/${approvalId}/approve`, data);
            return handleApiResponse(response);
        },

        async rejectSubRep(approvalId: string, data: { reason: string; notes?: string }): Promise<any> {
            const response = await apiPost(`/api/sales-reps/sub-rep-approvals/${approvalId}/reject`, data);
            return handleApiResponse(response);
        },

        async getTerritories(): Promise<any[]> {
            const response = await apiGet('/api/sales-reps/territories');
            return handleApiResponse(response);
        },

        async getCommissionSummary(repId: string, params?: {
            date_from?: string;
            date_to?: string;
        }): Promise<any> {
            const searchParams = new URLSearchParams();
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                        searchParams.set(key, value.toString());
                    }
                });
            }

            const url = `/api/sales-reps/${repId}/commissions/summary${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
            const response = await apiGet(url);
            return handleApiResponse(response);
        }
    },
};
