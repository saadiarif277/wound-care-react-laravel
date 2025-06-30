import { useState, useEffect } from 'react';
import ProductSelectorQuickRequest from '@/Components/ProductCatalog/ProductSelectorQuickRequest';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';

interface Product {
  id: number;
  name: string;
  manufacturer: string;
  code?: string;
  manufacturer_id?: number;
  price_per_sq_cm?: number;
  available_sizes?: number[] | string[];
  sku?: string;
  q_code?: string;
  category?: string;
  description?: string;
  msc_price?: number;
  image_url?: string;
  commission_rate?: number;
  docuseal_template_id?: string;
  signature_required?: boolean;
  size_options?: string[];
  size_pricing?: Record<string, number>;
  size_unit?: string;
}

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product: Product;
}

interface FormData {
  provider_id?: number | null;
  selected_products?: SelectedProduct[];
  primary_insurance_name?: string;
  primary_plan_type?: string;
  patient_state?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  last_24_hour_orders?: Array<{productCode: string, orderDate: Date}>;
  [key: string]: any;
}

interface Step5Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  errors: Record<string, string>;
  currentUser?: {
    role?: string;
  };
  roleRestrictions?: {
    can_view_financials: boolean;
    can_see_discounts: boolean;
    can_see_msc_pricing: boolean;
    can_see_order_totals: boolean;
    pricing_access_level: string;
    commission_access_level: string;
  };
}


export default function Step5ProductSelection({
  formData,
  updateFormData,
  errors,
  currentUser,
  roleRestrictions = {
    can_view_financials: true,
    can_see_discounts: true,
    can_see_msc_pricing: true,
    can_see_order_totals: true,
    pricing_access_level: 'full',
    commission_access_level: 'full'
  }
}: Step5Props) {
  const [providerOnboardedProducts, setProviderOnboardedProducts] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Fetch provider's onboarded products when provider_id changes
  useEffect(() => {
    // Only show products for selected provider
    // providerOnboardedProducts is an array of product codes or IDs

    const fetchProviderProducts = async () => {
      if (formData.provider_id) {
        setLoading(true);
        try {
          const response = await fetch(`/api/v1/providers/${formData.provider_id}/onboarded-products`);
          const data = await response.json();
          if (data.success) {
            setProviderOnboardedProducts(data.q_codes || []);
          }
        } catch (error) {
          console.error('Error fetching provider products:', error);
        } finally {
          setLoading(false);
        }
      }
    };
    
    fetchProviderProducts();
  }, [formData.provider_id]);

  const handleProductsChange = (selectedProducts: SelectedProduct[]) => {
    // Store provider-product mapping in formData, format size as '2 x 2'
    updateFormData({
      selected_products: selectedProducts.map((item) => ({
        ...item,
        provider_id: formData.provider_id,
        // Ensure product size is formatted as '2 x 2'
        size: item.size ? String(item.size).replace(/\s*([xX×^])\s*/g, ' x ') : undefined,
      }))
    });
  };

  return (
    <div className="space-y-6">
      {loading && formData.provider_id ? (
        <div className={cn(
          "p-8 text-center rounded-lg",
          theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
        )}>
          <p className={cn(
            "text-sm",
            theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
          )}>
            Loading provider products...
          </p>
        </div>
      ) : (
        <ProductSelectorQuickRequest
          providerOnboardedProducts={providerOnboardedProducts}
          insuranceType={(formData.primary_insurance_name?.toLowerCase().includes('medicare')) ? 'medicare' : (formData.primary_insurance_name?.toLowerCase().includes('medicaid')) ? 'medicaid' : (formData.primary_plan_type?.toLowerCase() === 'ppo' || formData.primary_plan_type?.toLowerCase() === 'commercial') ? 'ppo' : 'commercial'}
          woundSize={parseFloat(formData.wound_size_length || '0') * parseFloat(formData.wound_size_width || '0')}
          patientState={formData.patient_state}
          roleRestrictions={roleRestrictions}
          last24HourOrders={formData.last_24_hour_orders || []}
          selectedProducts={formData.selected_products as any || []}
          onProductsChange={handleProductsChange}
          className=""
        />
      )}

      {/* Validation Errors */}
      {errors.products && (
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark'
            ? 'bg-red-900/20 border-red-800'
            : 'bg-red-50 border-red-200'
        )}>
          <p className={cn(
            "text-sm",
            theme === 'dark' ? 'text-red-400' : 'text-red-600'
          )}>
            {errors.products}
          </p>
        </div>
      )}
    </div>
  );
}
