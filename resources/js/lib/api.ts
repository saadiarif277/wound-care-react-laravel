/**
 * API utility functions for making authenticated requests
 */

import axios from './axios-setup';
import { router } from '@inertiajs/react';

// Define a type for the API response
interface ApiResponse<T> {
  data: T;
}

// Re-export the configured axios instance
export const http = axios;

// Generic function to make API requests
async function request<T>(
  method: 'get' | 'post' | 'put' | 'patch' | 'delete',
  url: string,
  data?: any,
  options: RequestInit = {}
): Promise<T> {
  try {
    const response = await http[method]<T>(url, data, options);
    return response.data;
  } catch (error: any) {
    if (error.response?.status === 401) {
      router.visit('/login');
    }
    throw error;
  }
}

// Function to handle file uploads
async function upload<T>(
  url: string,
  file: File,
  options: RequestInit = {}
): Promise<T> {
  const formData = new FormData();
  formData.append('file', file);

  const response = await http.post<T>(url, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    ...options,
  });

  return response.data;
}

const api = {
  get: <T>(url: string, options?: RequestInit) => request<T>('get', url, null, options),
  post: <T>(url:string, data: any, options?: RequestInit) => request<T>('post', url, data, options),
  put: <T>(url:string, data: any, options?: RequestInit) => request<T>('put', url, data, options),
  patch: <T>(url:string, data: any, options?: RequestInit) => request<T>('patch', url, data, options),
  delete: <T>(url:string, options?: RequestInit) => request<T>('delete', url, null, options),
  upload,
};

export default api;
