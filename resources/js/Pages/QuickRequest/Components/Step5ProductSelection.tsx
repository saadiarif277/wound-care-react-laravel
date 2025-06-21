import { useState, useEffect } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import ProductSelectorQuickRequest from '@/Components/ProductCatalog/ProductSelectorQuickRequest';

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
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
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
    available_sizes?: number[];
    price_per_sq_cm?: number;
    msc_price?: number;
    docuseal_template_id?: string;
    signature_required?: boolean;
  }>;
  providerProducts?: Record<string, string[]>;
  errors: Record<string, string>;
  currentUser?: {
    role?: string;
  };
}

export default function Step5ProductSelection({
  formData,
  updateFormData,
  products,
  providerProducts = {},
  errors,
  currentUser
}: Step5Props) {
  const [providerOnboardedProducts, setProviderOnboardedProducts] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  
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

  // Fetch provider's onboarded products when provider_id changes
  useEffect(() => {
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

  // Get provider onboarded products
  const getProviderOnboardedProducts = () => {
    // Use the fetched provider onboarded products from the API
    return providerOnboardedProducts;
  };

  // Get role restrictions based on user role
  const getRoleRestrictions = () => {
    const userRole = currentUser?.role || 'provider';

    switch (userRole) {
      case 'office-manager':
        return {
          can_view_financials: false,
          can_see_discounts: false,
          can_see_msc_pricing: false,
          can_see_order_totals: false,
          pricing_access_level: 'national_asp_only',
          commission_access_level: 'none'
        };
      case 'provider':
      default:
        return {
          can_view_financials: true,
          can_see_discounts: true,
          can_see_msc_pricing: true,
          can_see_order_totals: true,
          pricing_access_level: 'full',
          commission_access_level: 'full'
        };
    }
  };

  const handleProductsChange = (selectedProducts: SelectedProduct[]) => {
    updateFormData({ selected_products: selectedProducts });
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
          insuranceType={getInsuranceType()}
          patientState={formData.patient_state}
          woundSize={calculateWoundSize()}
          providerOnboardedProducts={getProviderOnboardedProducts()}
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
