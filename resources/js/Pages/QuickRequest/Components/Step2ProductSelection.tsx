import { useState, useEffect } from 'react';
import ProductSelectorQuickRequest from '@/Components/ProductCatalog/ProductSelectorQuickRequest';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { usePage } from '@inertiajs/react';

interface Product {
  id: number;
  name: string;
  sku: string;
  q_code: string;
  manufacturer: string;
  manufacturer_id?: number;
  category: string;
  description: string;
  price_per_sq_cm: number;
  msc_price?: number;
  available_sizes?: number[] | string[];
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
    id?: number;
    role?: string;
    permissions?: string[];
  };
}

interface AuthUser {
  id: number;
  email: string;
  name: string;
  permissions?: string[];
  roles?: Array<{
    slug: string;
    name: string;
  }>;
}

interface PageProps {
  auth?: {
    user?: AuthUser;
  };
  [key: string]: any;
}

export default function Step5ProductSelection({
  formData,
  updateFormData,
  errors,
  currentUser
}: Step5Props) {
  const [providerOnboardedProducts, setProviderOnboardedProducts] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  
  // Get authenticated user from Inertia page props
  const { auth } = usePage<PageProps>().props;
  const authenticatedUser = auth?.user;
  
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Determine which provider ID to use
  const getProviderId = () => {
    // If a specific provider is selected in the form, use that
    if (formData.provider_id) {
      return formData.provider_id;
    }
    
    // Otherwise, use the current authenticated user's ID if they are a provider
    if (authenticatedUser?.id && authenticatedUser.roles?.some(role => role.slug === 'provider')) {
      return authenticatedUser.id;
    }
    
    // Or from currentUser prop
    if (currentUser?.id) {
      return currentUser.id;
    }
    
    return null;
  };

  // Fetch provider's onboarded products
  useEffect(() => {
    const fetchProviderProducts = async () => {
      const providerId = getProviderId();
      
      if (!providerId) {
        console.log('No provider ID available, skipping product fetch');
        setProviderOnboardedProducts([]);
        return;
      }
      
      setLoading(true);
      try {
        console.log(`Fetching onboarded products for provider ${providerId}`);
        const response = await fetch(`/api/v1/providers/${providerId}/onboarded-products`);
        const data = await response.json();
        
        console.log('Provider products response:', data);
        
        if (data.success && data.q_codes) {
          setProviderOnboardedProducts(data.q_codes);
          console.log(`Loaded ${data.q_codes.length} onboarded products:`, data.q_codes);
        } else {
          console.warn('No onboarded products found for provider');
          setProviderOnboardedProducts([]);
        }
      } catch (error) {
        console.error('Error fetching provider products:', error);
        setProviderOnboardedProducts([]);
      } finally {
        setLoading(false);
      }
    };
    
    fetchProviderProducts();
  }, [formData.provider_id, authenticatedUser?.id]);

  // Determine insurance type for product filtering
  const getInsuranceType = () => {
    const insuranceName = formData.primary_insurance_name?.toLowerCase() || '';
    const planType = formData.primary_plan_type?.toLowerCase() || '';

    if (insuranceName.includes('medicare')) {
      return 'medicare';
    } else if (insuranceName.includes('medicaid')) {
      return 'medicaid';
    } else if (planType === 'ppo' || planType === 'commercial') {
      return 'ppo';
    }
    return 'commercial'; // Default to commercial
  };

  // Calculate wound size in sq cm
  const calculateWoundSize = () => {
    const length = parseFloat(formData.wound_size_length || '0');
    const width = parseFloat(formData.wound_size_width || '0');
    return length * width;
  };

  // Get role restrictions based on actual user permissions
  const getRoleRestrictions = () => {
    // Use authenticated user's permissions if available
    const permissions = authenticatedUser?.permissions || currentUser?.permissions || [];
    
    // Check specific permissions
    const hasViewNationalAsp = permissions.includes('view-national-asp');
    const hasViewMscPricing = permissions.includes('view-msc-pricing');
    const hasViewFinancials = permissions.includes('view-financials');
    const hasViewDiscounts = permissions.includes('view-discounts');
    const hasViewOrderTotals = permissions.includes('view-order-totals');
    
    // Determine user's primary role
    const userRoles = authenticatedUser?.roles || [];
    const isOfficeManager = userRoles.some(role => role.slug === 'office-manager');
    const isProvider = userRoles.some(role => role.slug === 'provider');
    const isAdmin = userRoles.some(role => ['msc-admin', 'super-admin'].includes(role.slug));
    
    // Office managers: Only see National ASP
    if (isOfficeManager && !isAdmin) {
      return {
        can_view_financials: false,
        can_see_discounts: false,
        can_see_msc_pricing: false,
        can_see_order_totals: hasViewOrderTotals,
        pricing_access_level: 'national_asp_only',
        commission_access_level: 'none'
      };
    }
    
    // Providers and Admins: See both National ASP and MSC Pricing
    if (isProvider || isAdmin) {
      return {
        can_view_financials: hasViewFinancials || isAdmin,
        can_see_discounts: hasViewDiscounts || isAdmin,
        can_see_msc_pricing: hasViewMscPricing || isAdmin || isProvider,
        can_see_order_totals: hasViewOrderTotals || isAdmin,
        pricing_access_level: 'full',
        commission_access_level: (hasViewFinancials || isAdmin) ? 'full' : 'limited'
      };
    }
    
    // Default restrictions for other roles
    return {
      can_view_financials: hasViewFinancials,
      can_see_discounts: hasViewDiscounts,
      can_see_msc_pricing: hasViewMscPricing,
      can_see_order_totals: hasViewOrderTotals,
      pricing_access_level: hasViewMscPricing ? 'full' : 'national_asp_only',
      commission_access_level: hasViewFinancials ? 'full' : 'none'
    };
  };

  const handleProductsChange = (selectedProducts: SelectedProduct[]) => {
    updateFormData({ selected_products: selectedProducts });
  };

  const providerId = getProviderId();

  return (
    <div className="space-y-6">
      {/* Debug Information (remove in production) */}
      {process.env.NODE_ENV === 'development' && (
        <div className={cn(
          "p-4 rounded-lg text-xs font-mono",
          theme === 'dark' ? 'bg-gray-800/50' : 'bg-gray-100'
        )}>
          <p>Provider ID: {providerId || 'None'}</p>
          <p>Onboarded Products: {providerOnboardedProducts.length} ({providerOnboardedProducts.join(', ') || 'None'})</p>
          <p>Insurance Type: {getInsuranceType()}</p>
          <p>Wound Size: {calculateWoundSize()} sq cm</p>
        </div>
      )}

      {loading && providerId ? (
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
      ) : !providerId ? (
        <div className={cn(
          "p-8 text-center rounded-lg border-2 border-dashed",
          theme === 'dark' ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300'
        )}>
          <p className={cn(
            "text-sm font-medium mb-2",
            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
          )}>
            No Provider Selected
          </p>
          <p className={cn(
            "text-xs",
            theme === 'dark' ? 'text-gray-500' : 'text-gray-500'
          )}>
            Please select a provider in the previous step to view available products.
          </p>
        </div>
      ) : (
        <ProductSelectorQuickRequest
          insuranceType={getInsuranceType()}
          patientState={formData.patient_state}
          woundSize={calculateWoundSize()}
          providerOnboardedProducts={providerOnboardedProducts}
          onProductsChange={handleProductsChange}
          roleRestrictions={getRoleRestrictions()}
          last24HourOrders={formData.last_24_hour_orders}
          selectedProducts={formData.selected_products || []}
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