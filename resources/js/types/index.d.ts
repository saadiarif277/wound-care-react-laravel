import { Config } from 'ziggy-js';

export interface User {
  id: number;
  name: string;
  first_name: string;
  last_name: string;
  email: string;
  owner: string;
  photo: string;
  email_verified_at: string | null;
  deleted_at: string | null;
  account: Account;
  created_at: string;
  updated_at: string;
  npi_number?: string;
  current_organization?: {
    id: string;
    name: string;
  };
}

export interface Account {
  id: number;
  name: string;
  created_at: string;
  updated_at: string;
}

export interface Organization {
  id: number;
  name: string;
  email: string;
  phone: string;
  address: string;
  city: string;
  region: string;
  country: string;
  postal_code: string;
  deleted_at: string;
}

export type PaginatedData<T> = {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };

  meta: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;

    links: {
      url: null | string;
      label: string;
      active: boolean;
    }[];
  };
};

export interface PageProps {
  auth: {
    user: User;
  };
  flash: {
    success: string | null;
    error: string | null;
  };
  errors: Record<string, string>;
  ziggy: {
    location: string;
    [key: string]: any;
  };
  [key: string]: any;
}

export interface Facility {
  id: number;
  name: string;
  address?: string;
  city?: string;
  state?: string;
  zip_code?: string;
  full_address?: string; // If available from backend
  npi?: string; // If available from backend
}

export interface WoundType {
  code: string; // e.g., 'diabetic_foot_ulcer'
  display_name: string; // e.g., 'Diabetic Foot Ulcer'
}

export interface Order {
  id: number;
  order_number: string;
  provider_id: number;
  facility_id?: number;
  status: string;
  total_amount?: number;
  paid_amount?: number;
  payment_status?: 'unpaid' | 'partial' | 'paid';
  created_at: string;
  updated_at: string;
  paid_at?: string;
}
