import { useState, useEffect } from 'react';
import ProductSelectorQuickRequest from '@/Components/ProductCatalog/ProductSelectorQuickRequest';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';
import { toast } from '@/Components/ui/toast';
import api from '@/lib/api';
import { initializeSanctum } from '@/lib/sanctum';
import { Button } from '@/Components/ui/button';

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
  const [permissionsLoading, setPermissionsLoading] = useState(true);
  const [sanctumInitialized, setSanctumInitialized] = useState(false);

  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Initialize Sanctum before making any API calls
  useEffect(() => {
    const initAuth = async () => {
      try {
        await initializeSanctum();
        setSanctumInitialized(true);
      } catch (error) {
        console.error('Failed to initialize Sanctum:', error);
        // Continue anyway, but API calls might fail
        setSanctumInitialized(true);
      }
    };

    initAuth();
  }, []);

  // Fetch user permissions on mount - but only after Sanctum is initialized
  useEffect(() => {
    if (!sanctumInitialized) return;

    const fetchUserPermissions = async () => {
      try {
        const response = await api.get('/api/quick-request/user-permissions');
        const data = response.data || response;
        
        if (data.success && data.permissions) {
          setUserPermissions(data.permissions);
          
          // Log permissions for debugging (especially for Office Manager role)
          console.log('User permissions loaded:', {
            role: data.permissions.user_role,
            can_see_msc_pricing: data.permissions.can_see_msc_pricing,
            can_see_order_totals: data.permissions.can_see_order_totals,
            pricing_access_level: data.permissions.pricing_access_level
          });
        }
      } catch (error) {
        console.error('Error fetching user permissions:', error);
        // Use default restrictive permissions on error
        setUserPermissions({
          can_view_financials: false,
          can_see_discounts: false,
          can_see_msc_pricing: false,
          can_see_order_totals: false,
          pricing_access_level: 'none',
          commission_access_level: 'none'
        });
      } finally {
        setPermissionsLoading(false);
      }
    };

    fetchUserPermissions();
  }, [sanctumInitialized]);

  // Fetch provider's onboarded products when provider_id changes or if current user is a provider
  useEffect(() => {
    if (!sanctumInitialized) return;

    // Only show products for selected provider
    // providerOnboardedProducts is an array of product codes or IDs

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
          const response = await api.get(`/api/v1/providers/${providerId}/onboarded-products`);
          const data = response.data || response;
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
  }, [sanctumInitialized, formData.provider_id, currentUser?.id, currentUser?.role]);

  const handleProductsChange = async (selectedProducts: SelectedProduct[]) => {
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

    // Create draft episode if products are selected and we don't have an episode_id yet
    if (updatedProducts.length > 0 && !formData.episode_id && sanctumInitialized) {
      try {
        // Get manufacturer name from the first selected product
        const firstProduct = updatedProducts[0]?.product;
        const manufacturerName = firstProduct?.manufacturer || 'Unknown';

        const response = await api.post('/api/v1/quick-request/create-draft-episode', {
          form_data: formData,
          manufacturer_name: manufacturerName
        });

        if (response.data.success && response.data.episode_id) {
          // Update form data with the episode ID
          updateFormData({
            episode_id: response.data.episode_id.toString()
          });
          console.log('✅ Draft episode created:', response.data.episode_id);
        } else {
          console.warn('Failed to create draft episode, will create during final submission');
        }
      } catch (error) {
        console.error('Failed to create draft episode:', error);
        toast.error('Failed to create draft episode. Please try again.');
      }
    }
  };

  // Determine which role restrictions to use
  const roleRestrictions = userPermissions || propRoleRestrictions || {
    can_view_financials: false,
    can_see_discounts: false,
    can_see_msc_pricing: false,
    can_see_order_totals: false,
    pricing_access_level: 'none',
    commission_access_level: 'none'
  };

  return (
    <div className="space-y-6">
      {(loading && formData.provider_id) || permissionsLoading || !sanctumInitialized ? (
        <div className={cn(
          "p-8 text-center rounded-lg",
          theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
        )}>
          <p className={cn(
            "text-sm",
            theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
          )}>
            {!sanctumInitialized ? 'Initializing authentication...' : 
             permissionsLoading ? 'Loading permissions...' : 'Loading provider products...'}
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
