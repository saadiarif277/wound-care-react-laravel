import { useState, useEffect, useMemo, useCallback } from 'react';
import ProductSelectorQuickRequest from '@/Components/ProductCatalog/ProductSelectorQuickRequest';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';
import { toast } from '@/Components/ui/toast';
import api from '@/lib/api';

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
  episode_id?: string;
  [key: string]: any;
}

interface Step5Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  errors: Record<string, string>;
  currentUser?: {
    id?: number;
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
  roleRestrictions: propRoleRestrictions
}: Step5Props) {
  const [providerOnboardedProducts, setProviderOnboardedProducts] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [userPermissions, setUserPermissions] = useState<{
    can_view_financials: boolean;
    can_see_discounts: boolean;
    can_see_msc_pricing: boolean;
    can_see_order_totals: boolean;
    pricing_access_level: string;
    commission_access_level: string;
  } | null>(null);
  // Removed permissionsLoading state - permissions now computed from props

  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Compute user permissions from currentUser prop (Inertia pattern)
  useEffect(() => {
    const role = currentUser?.role || 'unknown';
    
    // Define permissions based on user role
    const permissions = {
      can_view_financials: ['admin', 'super_admin', 'sales_rep', 'provider'].includes(role),
      can_see_discounts: ['admin', 'super_admin', 'sales_rep'].includes(role),
      can_see_msc_pricing: ['admin', 'super_admin', 'sales_rep', 'provider'].includes(role),
      can_see_order_totals: ['admin', 'super_admin', 'sales_rep', 'provider'].includes(role),
      pricing_access_level: ['admin', 'super_admin'].includes(role) ? 'full' : 
                           ['sales_rep'].includes(role) ? 'limited' : 'none',
      commission_access_level: ['admin', 'super_admin', 'sales_rep'].includes(role) ? 'full' : 'none'
    };
    
    setUserPermissions(permissions);
    
    console.log('✅ User permissions computed from props:', {
      role,
      permissions
    });
  }, [currentUser?.role]);

  // Fetch provider's onboarded products when provider_id changes or if current user is a provider
  useEffect(() => {
    const fetchProviderProducts = async () => {
      // Determine which provider ID to use
      let providerId = formData.provider_id;
      
      // If no provider is explicitly selected and current user is a provider, use their ID
      if (!providerId && currentUser?.role === 'provider' && currentUser?.id) {
        providerId = currentUser.id;
      }
      
      if (providerId) {
        setLoading(true);
        try {
          const response = await api.get<{ success: boolean; q_codes: string[] }>(`/api/v1/providers/${providerId}/onboarded-products`);
          if (response.success) {
            setProviderOnboardedProducts(response.q_codes || []);
          }
        } catch (error: any) {
          console.error('Error fetching provider products:', error);
          // The axios interceptor will handle 401 errors and redirect to login
        } finally {
          setLoading(false);
        }
      }
    };

    fetchProviderProducts();
  }, [formData.provider_id, currentUser?.id, currentUser?.role]);

  // Memoized product change handler (2025 best practice: prevent unnecessary re-renders)
  const handleProductsChange = useCallback(async (selectedProducts: SelectedProduct[]) => {
    // Store provider-product mapping in formData, format size as '2 x 2'
    const updatedProducts = selectedProducts.map((item) => ({
      ...item,
      provider_id: formData.provider_id,
      // Ensure product size is formatted as '2 x 2'
      size: item.size ? String(item.size).replace(/\s*([xX×^])\s*/g, ' x ') : undefined,
    }));

    updateFormData({
      selected_products: updatedProducts
    });

    // Following Inertia.js best practices: No API calls during form interactions
    // The draft episode will be created during IVR generation or final submission when needed
    console.log('✅ Products selected, episode will be created during submission:', updatedProducts.length);
  }, [formData.provider_id, formData.episode_id, updateFormData]);

  // Memoized role restrictions (2025 best practice: computed values)
  const roleRestrictions = useMemo(() => {
    return userPermissions || propRoleRestrictions || {
      can_view_financials: false,
      can_see_discounts: false,
      can_see_msc_pricing: false,
      can_see_order_totals: false,
      pricing_access_level: 'none',
      commission_access_level: 'none'
    };
  }, [userPermissions, propRoleRestrictions]);

  // Memoized insurance type calculation (2025 best practice: avoid recalculation)
  const insuranceType = useMemo(() => {
    const primaryInsurance = formData.primary_insurance_name?.toLowerCase() || '';
    const planType = formData.primary_plan_type?.toLowerCase() || '';
    
    if (primaryInsurance.includes('medicare')) return 'medicare';
    if (primaryInsurance.includes('medicaid')) return 'medicaid';
    if (planType === 'ppo' || planType === 'commercial') return 'ppo';
    return 'commercial';
  }, [formData.primary_insurance_name, formData.primary_plan_type]);

  // Memoized wound size calculation (2025 best practice: avoid recalculation)
  const woundSize = useMemo(() => {
    return parseFloat(formData.wound_size_length || '0') * parseFloat(formData.wound_size_width || '0');
  }, [formData.wound_size_length, formData.wound_size_width]);

  return (
    <div className="space-y-6">
      {(loading && formData.provider_id) ? (
        <div className={cn(
          "p-8 text-center rounded-lg",
          theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
        )}>
          <div className="w-6 h-6 border-2 border-current border-t-transparent rounded-full animate-spin mx-auto mb-2" />
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
          insuranceType={insuranceType}
          woundSize={woundSize}
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
