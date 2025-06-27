export interface Product {
    id: number;
    name: string;
    sku: string;
    q_code: string;
    hcpcs_code?: string;
    manufacturer: string;
    manufacturer_id?: number;
    category: string;
    description?: string;
    price_per_sq_cm: number;
    national_avg_selling_price?: number;
    msc_price?: number;
    available_sizes?: number[] | string[];
    size_options?: string[] | number[];
    size_pricing?: Record<string, number>;
    size_unit?: 'in' | 'cm';
    image_url?: string;
    commission_rate?: number;
    is_active?: boolean;
    is_featured?: boolean;
    woundreference_url?: string;
    metadata?: Record<string, any>;
    settings?: Record<string, any>;
    created_at?: string;
    updated_at?: string;
    docuseal_template_id?: string;
    signature_required?: boolean;

    // CMS-related fields
    cms_verified_date?: string;
    cms_asp_date?: string;
    cms_national_asp?: number;
    cms_mac_pricing?: Record<string, number>;
    cms_mue_value?: number;
}

interface ProductSize {
  id: number;
  size_label: string;        // "2x2", "4x4"
  area_cm2: number;          // 25.81, 103.23
  width_inches: number;      // 2, 4
  height_inches: number;     // 2, 4
  is_standard: boolean;      // true for common sizes
  display_order: number;     // for UI sorting
}

interface ProductSizeAvailability {
  id: number;
  product_id: number;
  product_size_id: number;
  price: number;
  is_available: boolean;
  created_at: Date;
}

export interface SelectedProduct {
    product_id: number;
    quantity: number;
    size?: string;
    product?: Product;
}
