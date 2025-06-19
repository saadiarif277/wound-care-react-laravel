import React, { useState, useEffect, useMemo } from 'react';
import {
  AlertCircle,
  Info,
  Clock,
  ShieldAlert,
  Package,
  DollarSign,
  CheckCircle,
  XCircle,
  Building,
  Tag,
  User,
  Calendar,
  MapPin,
  AlertTriangle,
  X,
  Plus,
  Minus,
  ShoppingCart,
  Edit3,
  ChevronRight,
  Users,
  FileText,
  Phone,
  Mail
} from 'lucide-react';
import { PricingDisplay } from '@/Components/Pricing/PricingDisplay';
import { getProductSizeLabel } from '@/utils/size-label';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';

interface Product {
  id: number;
  name: string;
  sku: string;
  q_code: string;
  manufacturer: string;
  category: string;
  description: string;
  price_per_sq_cm: number;
  msc_price: number;
  available_sizes: number[];
  image_url?: string;
  commission_rate: number;
}

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: Product;
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
}

interface Props {
  insuranceType: string; // ppo/commercial, medicare, medicaid
  patientState?: string; // for Medicaid rules
  woundSize?: number; // in sq cm
  providerOnboardedProducts: string[]; // Q-codes
  onProductsChange: (products: SelectedProduct[]) => void;
  roleRestrictions: RoleRestrictions;
  last24HourOrders?: Array<{ productCode: string; orderDate: Date }>;
  selectedProducts?: SelectedProduct[];
  className?: string;
}

// Define insurance-based product rules
const INSURANCE_PRODUCT_RULES = {
  'ppo': {
    allowedProducts: ['Q4154'], // BioVance - updated from Q5128
    sizeRestrictions: null,
    message: 'PPO/Commercial insurance covers BioVance for any wound size'
  },
  'commercial': {
    allowedProducts: ['Q4154'], // BioVance - updated from Q5128
    sizeRestrictions: null,
    message: 'PPO/Commercial insurance covers BioVance for any wound size'
  },
  'medicare': {
    '0-250': {
      allowedProducts: ['Q4250', 'Q4290'], // Amnio AMP, Membrane Wrap Hydro - updated from Q5230, Q5231
      message: 'Medicare covers Amnio AMP or Membrane Wrap Hydro for wounds 0-250 sq cm'
    },
    '251-450': {
      allowedProducts: ['Q4290'], // Membrane Wrap Hydro only - updated from Q5231
      message: 'Medicare covers only Membrane Wrap Hydro for wounds 251-450 sq cm'
    },
    '>450': {
      allowedProducts: [],
      message: 'Wounds larger than 450 sq cm require consultation with MSC Admin',
      requiresConsultation: true
    }
  },
  'medicaid': {
    // States that cover Membrane Wrap / Membrane Wrap Hydro
    membraneWrapStates: ['TX', 'FL', 'GA', 'TN', 'NC', 'AL', 'OH', 'MI', 'IN', 'KY', 'MO', 'OK', 'SC', 'LA', 'MS',
                        'WA', 'OR', 'MT', 'SD', 'UT', 'AZ', 'CA', 'CO'],
    // States that cover Restorigen
    restorigenStates: ['TX', 'CA', 'LA', 'MD'],
    // Default products for other states
    defaultProducts: ['Q4271', 'Q4154', 'Q4238'] // Complete FT, BioVance, Derm-maxx - updated from Q5129, Q5128, Q5127
  }
};

// Maximum Units of Eligibility (MUE) limits by product
const MUE_LIMITS: Record<string, number> = {
  'Q4154': 4000, // BioVance
  'Q4271': 4000, // Complete FT
  'Q4238': 4000, // Derm-maxx
  'Q4250': 4000, // Amnio AMP
  'Q4290': 4000, // Membrane Wrap Hydro
  'Q4205': 4000, // Membrane Wrap
  'Q4191': 4000  // Restorigin
};

const ProductSelectorQuickRequest: React.FC<Props> = ({
  insuranceType,
  patientState,
  woundSize = 0,
  providerOnboardedProducts,
  onProductsChange,
  roleRestrictions,
  last24HourOrders = [],
  selectedProducts = [],
  className = ''
}) => {
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if context is not available
  }

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [showSizeManager, setShowSizeManager] = useState(false);
  const [consultationModalOpen, setConsultationModalOpen] = useState(false);
  const [warnings, setWarnings] = useState<string[]>([]);

  // Memoize selectedProducts to prevent infinite loops
  const selectedProductsMemo = useMemo(() => selectedProducts, [JSON.stringify(selectedProducts)]);

  // Fetch products on mount
  useEffect(() => {
    fetchProducts();
  }, []);

  // Update selected product when selectedProducts changes
  useEffect(() => {
    if (selectedProductsMemo.length > 0) {
      const firstProduct = selectedProductsMemo[0];
      if (firstProduct?.product_id) {
        const product = firstProduct.product || products.find(p => p.id === firstProduct.product_id);
        setSelectedProduct(product || null);
        setShowSizeManager(!!product);
      }
    } else {
      setSelectedProduct(null);
      setShowSizeManager(false);
    }
  }, [selectedProductsMemo, products]);

  const fetchProducts = async () => {
    try {
      console.log('ProductSelectorQuickRequest: Fetching products from /api/products/search');
      const response = await fetch('/api/products/search');
      const data = await response.json();
      console.log('ProductSelectorQuickRequest: Response received:', data);
      console.log('ProductSelectorQuickRequest: Products count:', data.products?.length || 0);
      if (data.products && data.products.length > 0) {
        console.log('ProductSelectorQuickRequest: First product example:', data.products[0]);
      }
      setProducts(data.products || []);
    } catch (error) {
      console.error('ProductSelectorQuickRequest: Error fetching products:', error);
    } finally {
      setLoading(false);
    }
  };

  // Get allowed products based on insurance rules
  const getAllowedProducts = useMemo(() => {
    let allowedQCodes: string[] = [];
    let message = '';
    let requiresConsultation = false;

    const normalizedInsuranceType = insuranceType.toLowerCase();

    if (normalizedInsuranceType === 'ppo' || normalizedInsuranceType === 'commercial') {
      allowedQCodes = [...INSURANCE_PRODUCT_RULES.ppo.allowedProducts];
      message = INSURANCE_PRODUCT_RULES.ppo.message;
    } else if (normalizedInsuranceType === 'medicare') {
      if (woundSize <= 250) {
        allowedQCodes = [...INSURANCE_PRODUCT_RULES.medicare['0-250'].allowedProducts];
        message = INSURANCE_PRODUCT_RULES.medicare['0-250'].message;
      } else if (woundSize <= 450) {
        allowedQCodes = [...INSURANCE_PRODUCT_RULES.medicare['251-450'].allowedProducts];
        message = INSURANCE_PRODUCT_RULES.medicare['251-450'].message;
      } else {
        allowedQCodes = [...INSURANCE_PRODUCT_RULES.medicare['>450'].allowedProducts];
        message = INSURANCE_PRODUCT_RULES.medicare['>450'].message;
        requiresConsultation = INSURANCE_PRODUCT_RULES.medicare['>450'].requiresConsultation || false;
      }
    } else if (normalizedInsuranceType === 'medicaid' && patientState) {
      const rules = INSURANCE_PRODUCT_RULES.medicaid;
      const upperState = patientState.toUpperCase();

      if (rules.membraneWrapStates.includes(upperState)) {
        allowedQCodes = ['Q4290', 'Q4205']; // Membrane Wrap Hydro, Membrane Wrap - updated from Q5231, Q5232
        message = `Medicaid in ${patientState} covers Membrane Wrap products`;
      } else if (rules.restorigenStates.includes(upperState)) {
        allowedQCodes = ['Q4191']; // Restorigin - updated from Q5233
        message = `Medicaid in ${patientState} covers Restorigen`;
      } else {
        allowedQCodes = [...rules.defaultProducts];
        message = `Medicaid in ${patientState} covers Complete FT, BioVance, or Derm-maxx`;
      }
    }

    return { allowedQCodes, message, requiresConsultation };
  }, [insuranceType, woundSize, patientState]);

  // Memoize the allowed Q-codes separately to prevent infinite loops
  const allowedQCodes = useMemo(() => getAllowedProducts.allowedQCodes, [getAllowedProducts.allowedQCodes]);
  const providerOnboardedProductsMemo = useMemo(() => providerOnboardedProducts, [JSON.stringify(providerOnboardedProducts)]);

  // Memoize last24HourOrders to prevent infinite loops
  const last24HourOrdersMemo = useMemo(() => last24HourOrders, [JSON.stringify(last24HourOrders)]);

  // Filter products based on insurance rules and provider onboarding
  const filteredProducts = useMemo(() => {
    // If no provider onboarded products, show products allowed by insurance
    if (providerOnboardedProductsMemo.length === 0) {
      console.log('No provider onboarded products, showing insurance-allowed products:', allowedQCodes);
      console.log('Available products:', products.map(p => ({ name: p.name, q_code: p.q_code })));

      // If insurance allows specific products, show only those
      if (allowedQCodes.length > 0) {
        const insuranceAllowedProducts = products.filter(product => allowedQCodes.includes(product.q_code));

        // If no products match insurance Q-codes, show all products as fallback
        if (insuranceAllowedProducts.length === 0) {
          console.log('No products found matching insurance Q-codes, showing all products as fallback');
          return products;
        }

        return insuranceAllowedProducts;
      }

      // If no specific insurance restrictions, show all products
      return products;
    }

    return products.filter(product => {
      // Show product if it's either:
      // 1. In the provider's onboarded list OR
      // 2. Allowed by insurance rules
      const isOnboarded = providerOnboardedProductsMemo.includes(product.q_code);
      const isAllowedByInsurance = allowedQCodes.includes(product.q_code);

      return isOnboarded || isAllowedByInsurance;
    });
  }, [products, allowedQCodes, providerOnboardedProductsMemo]);

  // Separate products into categories for display
  const categorizedProducts = useMemo(() => {
    console.log('=== ProductSelectorQuickRequest: Categorizing Products ===');
    console.log('filteredProducts count:', filteredProducts.length);
    console.log('providerOnboardedProductsMemo:', providerOnboardedProductsMemo);
    console.log('allowedQCodes:', allowedQCodes);

    filteredProducts.forEach(p => {
      console.log(`Product ${p.name} (${p.q_code}):`, {
        isOnboarded: providerOnboardedProductsMemo.includes(p.q_code),
        isInsuranceAllowed: allowedQCodes.includes(p.q_code)
      });
    });

    const onboardedAndRecommended = filteredProducts.filter(p =>
      providerOnboardedProductsMemo.includes(p.q_code) && allowedQCodes.includes(p.q_code)
    );

    const onboardedOnly = filteredProducts.filter(p =>
      providerOnboardedProductsMemo.includes(p.q_code) && !allowedQCodes.includes(p.q_code)
    );

    const recommendedOnly = filteredProducts.filter(p =>
      !providerOnboardedProductsMemo.includes(p.q_code) && allowedQCodes.includes(p.q_code)
    );

    // If we're in fallback mode (no provider products and no matching insurance Q-codes),
    // treat all filtered products as "available" products
    const fallbackProducts = filteredProducts.filter(p =>
      !providerOnboardedProductsMemo.includes(p.q_code) && !allowedQCodes.includes(p.q_code)
    );

    console.log('Category counts:', {
      onboardedAndRecommended: onboardedAndRecommended.length,
      onboardedOnly: onboardedOnly.length,
      recommendedOnly: recommendedOnly.length,
      fallbackProducts: fallbackProducts.length
    });

    return {
      onboardedAndRecommended,
      onboardedOnly,
      recommendedOnly,
      fallbackProducts
    };
  }, [filteredProducts, allowedQCodes, providerOnboardedProductsMemo]);

  // Check for warnings (24-hour rule, MUE limits)
  useEffect(() => {
    const newWarnings: string[] = [];

    // Check 24-hour rule for selected products
    selectedProductsMemo.forEach(item => {
      const product = item.product || products.find(p => p.id === item.product_id);
      if (product) {
        const recentOrder = last24HourOrdersMemo.find(order =>
          order.productCode === product.q_code &&
          new Date(order.orderDate).getTime() > Date.now() - 24 * 60 * 60 * 1000
        );

        if (recentOrder) {
          newWarnings.push(`Warning: ${product.name} (Q${product.q_code}) was ordered within the last 24 hours. This may affect reimbursement.`);
        }

        // Check MUE limits
        const totalSize = selectedProductsMemo
          .filter(p => p.product_id === product.id)
          .reduce((sum, p) => {
            const size = p.size ? parseFloat(p.size) : 0;
            return sum + (size * p.quantity);
          }, 0);

        const mueLimit = MUE_LIMITS[product.q_code];
        if (mueLimit && totalSize > mueLimit) {
          newWarnings.push(`Warning: Total size for ${product.name} (${totalSize} sq cm) exceeds Maximum Units of Eligibility (${mueLimit} sq cm). This may require additional documentation.`);
        }
      }
    });

    setWarnings(newWarnings);
  }, [selectedProductsMemo, products, last24HourOrdersMemo]);

  const addProductToSelection = (product: Product, quantity: number = 1, size?: string) => {
    // If this is a different product, clear all existing selections
    if (selectedProductsMemo.length > 0 && selectedProductsMemo[0]?.product_id !== product.id) {
      onProductsChange([{ product_id: product.id, quantity, size, product }]);
    } else {
      // Check if this size already exists
      const existingIndex = selectedProductsMemo.findIndex(item =>
        item.product_id === product.id && item.size === size
      );

      if (existingIndex >= 0) {
        // Update existing size/quantity
        const updated = [...selectedProductsMemo];
        updated[existingIndex] = { ...updated[existingIndex], quantity: updated[existingIndex].quantity + quantity };
        onProductsChange(updated);
      } else {
        // Add new size/quantity
        onProductsChange([...selectedProductsMemo, { product_id: product.id, quantity, size, product }]);
      }
    }
  };

  const updateProductQuantity = (productId: number, size: string | undefined, newQuantity: number) => {
    if (newQuantity <= 0) {
      removeProduct(productId, size);
      return;
    }

    const updated = selectedProductsMemo.map(item =>
      item.product_id === productId && item.size === size
        ? { ...item, quantity: newQuantity }
        : item
    );
    onProductsChange(updated);
  };

  const removeProduct = (productId: number, size: string | undefined) => {
    const updated = selectedProductsMemo.filter(item =>
      !(item.product_id === productId && item.size === size)
    );
    onProductsChange(updated);
  };

  const clearAllProducts = () => {
    onProductsChange([]);
    setSelectedProduct(null);
    setShowSizeManager(false);
  };

  const calculateTotal = () => {
    return selectedProductsMemo.reduce((total, item) => {
      const product = item.product || products.find(p => p.id === item.product_id);
      if (!product) return total;

      const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
      let unitPrice = pricePerUnit;

      if (item.size) {
        const sizeValue = parseFloat(item.size);
        if (!isNaN(sizeValue)) {
          unitPrice = pricePerUnit * sizeValue;
        }
      }

      return total + (unitPrice * item.quantity);
    }, 0);
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center py-12 ${t.glass.card} rounded-lg ${className}`}>
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className={`ml-2 ${t.text.secondary}`}>Loading product catalog...</span>
      </div>
    );
  }

  // Show consultation required modal for Medicare >450 sq cm
  if (getAllowedProducts.requiresConsultation) {
    return (
      <div className={`${className}`}>
        <ConsultationRequiredCard
          woundSize={woundSize}
          onContactAdmin={() => setConsultationModalOpen(true)}
          theme={t}
        />
        {consultationModalOpen && (
          <ConsultationModal
            onClose={() => setConsultationModalOpen(false)}
            theme={t}
          />
        )}
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Insurance Coverage Information */}
      <div className={`${t.glass.card} rounded-lg p-4`}>
        <div className="flex items-start space-x-3">
          <ShieldAlert className="w-5 h-5 text-blue-500 mt-0.5" />
          <div className="flex-1">
            <h3 className={`text-sm font-semibold ${t.text.primary} mb-1`}>
              Insurance Coverage Information
            </h3>
            <p className={`text-sm ${t.text.secondary}`}>
              {getAllowedProducts.message}
            </p>
            {woundSize > 0 && (
              <p className={`text-xs ${t.text.tertiary} mt-1`}>
                Current wound size: {woundSize} sq cm
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Warnings Section */}
      {warnings.length > 0 && (
        <div className="space-y-2">
          {warnings.map((warning, index) => (
            <div key={index} className={`${t.status.warning} rounded-lg p-3 flex items-start space-x-2`}>
              <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
              <p className="text-sm">{warning}</p>
            </div>
          ))}
        </div>
      )}

      {/* Product Selection */}
      {filteredProducts.length === 0 ? (
        <div className={`${t.glass.card} rounded-lg p-8 text-center`}>
          <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
          <h4 className={`text-lg font-semibold ${t.text.primary} mb-2`}>
            No Products Available
          </h4>
          <p className={`text-sm ${t.text.secondary} mb-4`}>
            No products match your insurance coverage and provider onboarding status.
          </p>
          <div className={`${t.glass.frost} rounded-md p-3 text-left`}>
            <h5 className={`text-sm font-medium ${t.text.primary} mb-2`}>Possible reasons:</h5>
            <ul className={`text-xs ${t.text.secondary} space-y-1`}>
              <li>• Your provider is not onboarded with the insurance-approved products</li>
              <li>• The wound size falls outside covered ranges</li>
              <li>• State-specific Medicaid restrictions apply</li>
            </ul>
          </div>
        </div>
      ) : (
        <div className="grid gap-6 grid-cols-1 lg:grid-cols-3">
          {/* Product Grid */}
          <div className="lg:col-span-2">
            {/* Product Selection Notice */}
            {selectedProduct ? (
              <div className={`mb-4 p-4 ${t.status.info} rounded-lg`}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center">
                    <CheckCircle className="w-5 h-5 mr-2" />
                    <div>
                      <h4 className="text-sm font-semibold">
                        Selected: {selectedProduct.name}
                      </h4>
                      <p className="text-xs opacity-80">
                        Q{selectedProduct.q_code} • {selectedProduct.manufacturer}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <button
                      onClick={() => setShowSizeManager(!showSizeManager)}
                      className={`inline-flex items-center px-3 py-1 text-xs font-medium rounded-md ${t.button.secondary.base} ${t.button.secondary.hover}`}
                    >
                      <Edit3 className="w-3 h-3 mr-1" />
                      Manage Sizes
                    </button>
                    <button
                      onClick={clearAllProducts}
                      className={`inline-flex items-center px-3 py-1 text-xs font-medium rounded-md ${t.button.danger.base} ${t.button.danger.hover}`}
                    >
                      <X className="w-3 h-3 mr-1" />
                      Change Product
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <div className={`mb-4 p-3 ${t.status.info} rounded-md flex items-center`}>
                <Info className="w-4 h-4 mr-2 flex-shrink-0" />
                <span className="text-sm">
                  Select one product type. You can then add multiple sizes and quantities for that product.
                </span>
              </div>
            )}

            <div className="space-y-6">
              {/* Onboarded AND Recommended Products */}
              {categorizedProducts.onboardedAndRecommended.length > 0 && (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <CheckCircle className="w-4 h-4 mr-2 text-green-500" />
                    Recommended Products (Provider Onboarded)
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {categorizedProducts.onboardedAndRecommended.map(product => (
                      <QuickRequestProductCard
                        key={product.id}
                        product={product}
                        onAdd={addProductToSelection}
                        roleRestrictions={roleRestrictions}
                        isDisabled={selectedProduct !== null && selectedProduct.id !== product.id}
                        canAddMoreSizes={selectedProduct !== null && selectedProduct.id === product.id}
                        isSelected={selectedProduct?.id === product.id}
                        isRecommended={true}
                        isOnboarded={true}
                        theme={t}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Recommended Only Products */}
              {categorizedProducts.recommendedOnly.length > 0 && (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <Info className="w-4 h-4 mr-2 text-blue-500" />
                    Insurance Recommended Products (Not Yet Onboarded)
                  </h3>
                  <div className={`p-3 mb-3 ${t.status.warning} rounded-md`}>
                    <p className="text-sm">
                      These products are recommended for this patient's insurance but you are not yet onboarded.
                      Contact your MSC representative to get onboarded.
                    </p>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {categorizedProducts.recommendedOnly.map(product => (
                      <QuickRequestProductCard
                        key={product.id}
                        product={product}
                        onAdd={addProductToSelection}
                        roleRestrictions={roleRestrictions}
                        isDisabled={selectedProduct !== null && selectedProduct.id !== product.id}
                        canAddMoreSizes={selectedProduct !== null && selectedProduct.id === product.id}
                        isSelected={selectedProduct?.id === product.id}
                        isRecommended={true}
                        isOnboarded={false}
                        theme={t}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Onboarded Only Products */}
              {categorizedProducts.onboardedOnly.length > 0 && (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <Package className="w-4 h-4 mr-2 text-gray-500" />
                    Other Available Products (Provider Onboarded)
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {categorizedProducts.onboardedOnly.map(product => (
                      <QuickRequestProductCard
                        key={product.id}
                        product={product}
                        onAdd={addProductToSelection}
                        roleRestrictions={roleRestrictions}
                        isDisabled={selectedProduct !== null && selectedProduct.id !== product.id}
                        canAddMoreSizes={selectedProduct !== null && selectedProduct.id === product.id}
                        isSelected={selectedProduct?.id === product.id}
                        isRecommended={false}
                        isOnboarded={true}
                        theme={t}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Fallback Products (when no Q-code matches) */}
              {categorizedProducts.fallbackProducts.length > 0 && (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <Package className="w-4 h-4 mr-2 text-blue-500" />
                    Available Products
                  </h3>
                  <div className={`p-3 mb-3 ${t.status.info} rounded-md`}>
                    <p className="text-sm">
                      Showing all available products. Note: Some products may have different insurance coverage requirements.
                    </p>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {categorizedProducts.fallbackProducts.map(product => (
                      <QuickRequestProductCard
                        key={product.id}
                        product={product}
                        onAdd={addProductToSelection}
                        roleRestrictions={roleRestrictions}
                        isDisabled={selectedProduct !== null && selectedProduct.id !== product.id}
                        canAddMoreSizes={selectedProduct !== null && selectedProduct.id === product.id}
                        isSelected={selectedProduct?.id === product.id}
                        isRecommended={false}
                        isOnboarded={false}
                        theme={t}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* No products available */}
              {filteredProducts.length === 0 && (
                <div className={`text-center py-12 ${t.glass.frost} rounded-lg`}>
                  <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
                  <p className={`${t.text.secondary} mb-2`}>No products available</p>
                  <p className={`text-sm ${t.text.muted}`}>
                    No products match your insurance coverage or provider onboarding status.
                  </p>
                </div>
              )}

              {/* Debug section to show when no categorized products exist */}
              {filteredProducts.length > 0 &&
               categorizedProducts.onboardedAndRecommended.length === 0 &&
               categorizedProducts.recommendedOnly.length === 0 &&
               categorizedProducts.onboardedOnly.length === 0 &&
               categorizedProducts.fallbackProducts.length === 0 && (
                <div className={`text-center py-12 ${t.glass.frost} rounded-lg`}>
                  <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
                  <p className={`${t.text.secondary} mb-2`}>Products loaded but not categorized</p>
                  <p className={`text-sm ${t.text.muted}`}>
                    Found {filteredProducts.length} products but they don't match any category. Check console for debug info.
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Selected Products Cart */}
          <div className="lg:col-span-1">
            <div className={`${t.glass.card} rounded-lg p-4 sticky top-4`}>
              <div className="flex items-center justify-between mb-4">
                <h4 className={`text-lg font-semibold ${t.text.primary} flex items-center`}>
                  <ShoppingCart className="w-5 h-5 mr-2" />
                  {selectedProduct ? 'Product Details' : 'Selected Product'}
                </h4>
                <span className={`text-sm ${t.text.secondary}`}>
                  {selectedProductsMemo.length} {selectedProductsMemo.length === 1 ? 'size/qty' : 'sizes/qtys'}
                </span>
              </div>

              {selectedProductsMemo.length === 0 ? (
                <div className="text-center py-8">
                  <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
                  <p className={`