import React, { useState, useEffect, useMemo } from 'react';
import {
  Info,
  ShieldAlert,
  Package,
  CheckCircle,
  Building,
  Tag,
  AlertTriangle,
  X,
  Plus,
  Minus,
  ShoppingCart,
  Edit3,
  Users,
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
  manufacturer_id?: number;
  category: string;
  description: string;
  price_per_sq_cm: number;
  msc_price?: number;
  available_sizes?: number[] | string[];  // Support both old and new formats
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

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
  commission_access_level: string;
}

interface Props {
  insuranceType: string; // ppo/commercial, medicare, medicaid
  patientState?: string; // for Medicaid rules
  woundSize: number; // in sq cm
  providerOnboardedProducts: string[]; // Q-codes
  onProductsChange: (products: SelectedProduct[]) => void;
  roleRestrictions: RoleRestrictions;
  last24HourOrders?: Array<{ productCode: string; orderDate: Date }>;
  selectedProducts?: SelectedProduct[];
  className?: string;
}

// TODO: Move to database configuration or API endpoint
// These rules should be managed by MSC admin staff
const INSURANCE_PRODUCT_RULES = {
  'ppo': {
    allowedProducts: ['Q4154'], // BioVance - TODO: Fetch from insurance_product_rules table
    sizeRestrictions: null,
    message: 'Coverage available for BioVance'
  },
  'commercial': {
    allowedProducts: ['Q4154'], // BioVance - TODO: Fetch from insurance_product_rules table
    sizeRestrictions: null,
    message: 'Coverage available for BioVance'
  },
  'medicare': {
    '0-250': {
      allowedProducts: ['Q4250', 'Q4290'], // TODO: Fetch from insurance_product_rules table
      message: 'Medicare covers Amnio AMP or Membrane Wrap Hydro for wounds 0-250 sq cm'
    },
    '251-450': {
      allowedProducts: ['Q4290'], // TODO: Fetch from insurance_product_rules table
      message: 'Medicare covers only Membrane Wrap Hydro for wounds 251-450 sq cm'
    },
    '>450': {
      allowedProducts: [],
      message: 'Wounds larger than 450 sq cm require consultation with MSC Admin',
      requiresConsultation: true
    }
  },
  'medicaid': {
    // TODO: Move state lists to database or configuration
    // States that cover Membrane Wrap / Membrane Wrap Hydro
    membraneWrapStates: ['TX', 'FL', 'GA', 'TN', 'NC', 'AL', 'OH', 'MI', 'IN', 'KY', 'MO', 'OK', 'SC', 'LA', 'MS',
                        'WA', 'OR', 'MT', 'SD', 'UT', 'AZ', 'CA', 'CO'],
    // States that cover Restorigen
    restorigenStates: ['TX', 'CA', 'LA', 'MD'],
    // Default products for other states
    defaultProducts: ['Q4271', 'Q4154', 'Q4238'] // TODO: Fetch from insurance_product_rules table
  }
};

// TODO: Move to product configuration or fetch from products table
// Maximum Units of Eligibility (MUE) limits by product
const MUE_LIMITS: Record<string, number> = {
  'Q4154': 4000, // BioVance - TODO: Get from product.mue_limit field
  'Q4271': 4000, // Complete FT - TODO: Get from product.mue_limit field
  'Q4238': 4000, // Derm-maxx - TODO: Get from product.mue_limit field
  'Q4250': 4000, // Amnio AMP - TODO: Get from product.mue_limit field
  'Q4290': 4000, // Membrane Wrap Hydro - TODO: Get from product.mue_limit field
  'Q4205': 4000, // Membrane Wrap - TODO: Get from product.mue_limit field
  'Q4191': 4000  // Restorigin - TODO: Get from product.mue_limit field
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
        // Debug size data specifically
        const firstProduct = data.products[0];
        console.log('ProductSelectorQuickRequest: First product size data:', {
          available_sizes: firstProduct.available_sizes,
          size_options: firstProduct.size_options,
          size_pricing: firstProduct.size_pricing,
          size_unit: firstProduct.size_unit
        });
      }

      // Temporary fix: Add sample size data to products that don't have any
      const productsWithSizes = (data.products || []).map((product: Product) => {
        const hasNewSizes = product.size_options && product.size_options.length > 0;
        const hasLegacySizes = product.available_sizes && product.available_sizes.length > 0;

        if (!hasNewSizes && !hasLegacySizes) {
          console.log(`Adding sample sizes to ${product.name} (${product.q_code})`);
          // Add common wound care product sizes
          return {
            ...product,
            available_sizes: [4, 9, 16, 25, 36], // Common sizes in cm²
            size_unit: 'cm'
          };
        }

        return product;
      });

      setProducts(productsWithSizes);
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
    const newSelection: SelectedProduct = { product_id: product.id, quantity, size, product };

    // If this is a different product, clear all existing selections
    if (selectedProductsMemo.length > 0 && selectedProductsMemo[0]?.product_id !== product.id) {
      onProductsChange([newSelection]);
    } else {
      // Check if this size already exists
      const existingIndex = selectedProductsMemo.findIndex(item =>
        item.product_id === product.id && item.size === size
      );

      if (existingIndex >= 0) {
        // Update existing size/quantity
        const updated = [...selectedProductsMemo];
        const existingItem = updated[existingIndex];
        if (existingItem) {
          updated[existingIndex] = { ...existingItem, quantity: existingItem.quantity + quantity, product };
          onProductsChange(updated);
        }
      } else {
        // Add new size/quantity
        onProductsChange([...selectedProductsMemo, newSelection]);
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
                      title="Manage Sizes"
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

              {/* Recommended Only Products */}
              {categorizedProducts.recommendedOnly.length > 0 && (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <Info className="w-4 h-4 mr-2 text-blue-500" />
                    Suggested Products (Not Yet Onboarded)
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
                  <p className={`${t.text.secondary} mb-2`}>No product selected yet</p>
                  <p className={`text-xs ${t.text.muted}`}>
                    Select one product type - you can add multiple sizes and quantities
                  </p>
                </div>
              ) : (
                <div className="space-y-3">
                  {/* Product Summary */}
                  {selectedProduct && (
                    <div className={`p-3 ${t.glass.frost} rounded-md mb-4`}>
                      <h5 className={`text-sm font-semibold ${t.text.primary} mb-1`}>
                        {selectedProduct.name}
                      </h5>
                      <div className={`text-xs ${t.text.secondary} space-y-1`}>
                        <p>Q{selectedProduct.q_code} • {selectedProduct.sku}</p>
                        <p>{selectedProduct.manufacturer}</p>
                        <p className="text-blue-600">{selectedProduct.category}</p>
                      </div>
                    </div>
                  )}

                  {/* Size/Quantity Items */}
                  {selectedProductsMemo.map((item) => {
                    const product = item.product || products.find(p => p.id === item.product_id);
                    if (!product) return null;

                    const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
                    let unitPrice = pricePerUnit;

                    if (item.size) {
                      const sizeValue = parseFloat(item.size);
                      if (!isNaN(sizeValue)) {
                        unitPrice = pricePerUnit * sizeValue;
                      }
                    }

                    const totalPrice = unitPrice * item.quantity;

                    return (
                      <div key={`${item.product_id}-${item.size || 'no-size'}`} className={`border ${theme === 'dark' ? 'border-white/10' : 'border-gray-200'} rounded-md p-3`}>
                        <div className="flex items-start justify-between mb-2">
                          <div className="flex-1">
                            {item.size ? (
                              <div>
                                <h5 className={`text-sm font-medium ${t.text.primary}`}>
                                  Size: {getProductSizeLabel(item.size, product.size_unit)}
                                </h5>
                                <p className={`text-xs ${t.text.secondary}`}>
                                  {formatPrice(unitPrice)} per unit
                                </p>
                              </div>
                            ) : (
                              <div>
                                <h5 className={`text-sm font-medium ${t.text.primary}`}>
                                  Standard Size
                                </h5>
                                <p className={`text-xs ${t.text.secondary}`}>
                                  {formatPrice(unitPrice)} per unit
                                </p>
                              </div>
                            )}
                          </div>
                          <button
                            onClick={() => removeProduct(item.product_id, item.size)}
                            className="text-red-500 hover:text-red-700"
                            title="Remove product"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>

                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-2">

                            <button
                              title="Decrease quantity"
                              aria-label="Decrease quantity"
                              onClick={() => updateProductQuantity(item.product_id, item.size, item.quantity - 1)}
                              className={`w-6 h-6 rounded-full border ${theme === 'dark' ? 'border-white/20 hover:bg-white/10' : 'border-gray-300 hover:bg-gray-50'} flex items-center justify-center`}
                            >
                              <Minus className="w-3 h-3" />
                            </button>
                              {item.quantity}

                            <button
                              title="Increase quantity"
                              aria-label="Increase quantity"
                              onClick={() => updateProductQuantity(item.product_id, item.size, item.quantity + 1)}
                              className={`w-6 h-6 rounded-full border ${theme === 'dark' ? 'border-white/20 hover:bg-white/10' : 'border-gray-300 hover:bg-gray-50'} flex items-center justify-center`}
                            >
                              <Plus className="w-3 h-3" />
                            </button>
                          </div>
                          <div className="text-right">
                            <p className={`text-sm font-semibold ${t.text.primary}`}>
                              {formatPrice(totalPrice)}
                            </p>
                            <p className={`text-xs ${t.text.secondary}`}>
                              {item.quantity} × {formatPrice(unitPrice)}
                            </p>
                          </div>
                        </div>
                      </div>
                    );
                  })}

                  <div className={`border-t ${theme === 'dark' ? 'border-white/10' : 'border-gray-200'} pt-3`}>
                    <div className="flex items-center justify-between">
                      <span className={`text-base font-semibold ${t.text.primary}`}>Total:</span>
                      <span className="text-lg font-bold text-blue-600">
                        {formatPrice(calculateTotal())}
                      </span>
                    </div>
                    {!roleRestrictions.can_see_msc_pricing && (
                      <p className="text-xs text-yellow-600 mt-1">
                        * Pricing shown is National ASP only
                      </p>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// Product Card Component
const QuickRequestProductCard: React.FC<{
  product: Product;
  onAdd: (product: Product, quantity: number, size?: string) => void;
  roleRestrictions: RoleRestrictions;
  isDisabled?: boolean;
  canAddMoreSizes?: boolean;
  isSelected?: boolean;
  isRecommended?: boolean;
  isOnboarded?: boolean;
  theme: any;
}> = ({ product, onAdd, roleRestrictions, isDisabled = false, isSelected = false, isRecommended = false, isOnboarded = false, theme: t }) => {
  const [selectedSize, setSelectedSize] = useState<string>('');
  const [quantity, setQuantity] = useState(1);

  // Debug size data for this product
  console.log(`ProductCard for ${product.name} (${product.q_code}):`, {
    available_sizes: product.available_sizes,
    size_options: product.size_options,
    size_pricing: product.size_pricing,
    size_unit: product.size_unit,
    hasNewSizes: product.size_options && product.size_options.length > 0,
    hasLegacySizes: product.available_sizes && product.available_sizes.length > 0
  });

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  const handleAddProduct = () => {
    onAdd(product, quantity, selectedSize || undefined);
    setQuantity(1);
    setSelectedSize('');
  };

  const calculatePrice = () => {
    const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
    if (selectedSize) {
      const sizeValue = parseFloat(selectedSize);
      if (isNaN(sizeValue)) {
        return pricePerUnit * quantity;
      }
      return pricePerUnit * sizeValue * quantity;
    }
    return pricePerUnit * quantity;
  };

  return (
    <div className={`${t.glass.card} rounded-lg p-4 transition-all duration-200 ${
      isSelected ? 'ring-2 ring-blue-500' : ''
    } ${isDisabled ? 'opacity-50' : ''}`}>
      <div className="flex flex-wrap gap-2 mb-2">
        {isSelected && (
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
            Currently Selected
          </span>
        )}
        {isRecommended && (
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
            Suggested
          </span>
        )}
        {isOnboarded && (
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
            Provider Onboarded
          </span>
        )}
        {!isOnboarded && isRecommended && (
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
            Onboarding Required
          </span>
        )}
      </div>

      <div className="flex items-start justify-between mb-3">
        <div className="flex-1">
          <h3 className={`text-sm font-semibold ${t.text.primary} line-clamp-2 mb-1`}>
            {product.name}
          </h3>
          <div className={`flex items-center space-x-2 text-xs ${t.text.secondary} mb-1`}>
            <Tag className="w-3 h-3" />
            <span>Q{product.q_code}</span>
            <span>•</span>
            <span>{product.sku}</span>
          </div>
          <div className={`flex items-center text-xs ${t.text.secondary}`}>
            <Building className="w-3 h-3 mr-1" />
            <span>{product.manufacturer}</span>
          </div>
        </div>
      </div>

      <p className={`text-xs ${t.text.secondary} mb-3 line-clamp-2`}>
        {product.description}
      </p>

      <div className="space-y-2 mb-3">
        <PricingDisplay
          roleRestrictions={roleRestrictions}
          product={{
            nationalAsp: product.price_per_sq_cm,
            mscPrice: product.msc_price,
          }}
          showLabel={true}
          className="text-xs"
        />

        {roleRestrictions.can_view_financials && product.commission_rate && (
          <div className="flex justify-between text-xs">
            <span className={t.text.secondary}>Commission:</span>
            <span className="font-medium text-green-600">
              {product.commission_rate}%
            </span>
          </div>
        )}
      </div>

      {/* Size Selection */}
      {(product.size_options && product.size_options.length > 0) ? (
        <div className="mb-3">
          <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
            Size ({product.size_unit === 'cm' ? 'cm' : 'inches'})
          </label>
          <select
            value={selectedSize}
            onChange={(e) => setSelectedSize(e.target.value)}
            className={`w-full text-xs ${t.input.base} ${t.input.focus}`}
            disabled={isDisabled}
          >
            <option value="">Select size...</option>
            {product.size_options.map(size => {
              const price = product.size_pricing?.[size] || 0;
              const displayPrice = roleRestrictions.can_see_msc_pricing ? (product.msc_price || price) : price;
              return (
                <option key={size} value={size}>
                  {size}{product.size_unit === 'cm' ? ' cm' : '"'} - {formatPrice(displayPrice)}
                </option>
              );
            })}
          </select>
        </div>
      ) : (product.available_sizes && product.available_sizes.length > 0) ? (
        <div className="mb-3">
          <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
            Size (cm²)
          </label>
          <select
            value={selectedSize}
            onChange={(e) => setSelectedSize(e.target.value)}
            className={`w-full text-xs ${t.input.base} ${t.input.focus}`}
            disabled={isDisabled}
          >
            <option value="">Select size...</option>
            {product.available_sizes.map(size => {
              const sizeStr = size.toString();
              const sizeNum = typeof size === 'string' ? parseFloat(size) : size;
              const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
              return (
                <option key={sizeStr} value={sizeStr}>
                  {getProductSizeLabel(sizeStr, product.size_unit)} - {formatPrice(pricePerUnit * sizeNum)}
                </option>
              );
            })}
          </select>
        </div>
      ) : null}

      {/* Quantity Selection */}
      <div className="mb-3">
        <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
          Quantity
        </label>
        <input
          type="number"
          min="1"
          value={quantity}
          onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
          className={`w-full text-xs ${t.input.base} ${t.input.focus}`}
          disabled={isDisabled}
        />
      </div>

      {/* Total Price Display */}
      <div className={`mb-3 p-2 ${t.glass.frost} rounded text-center`}>
        <span className={`text-sm font-semibold ${t.text.primary}`}>
          Total: {formatPrice(calculatePrice())}
        </span>
      </div>

      <button
        onClick={handleAddProduct}
        disabled={(((product.size_options && product.size_options.length > 0) || (product.available_sizes && product.available_sizes.length > 0)) && !selectedSize) || isDisabled}
        className={`w-full text-sm font-medium py-2 px-3 rounded-md transition-all duration-200 ${
          isDisabled
            ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
            : `${t.button.primary.base} ${t.button.primary.hover}`
        }`}
      >
        {isDisabled ? 'Different Product Selected' :
         isSelected ? 'Add Another Size' : 'Select This Product'}
      </button>
    </div>
  );
};

// Consultation Required Card
const ConsultationRequiredCard: React.FC<{
  woundSize: number;
  onContactAdmin: () => void;
  theme: any;
}> = ({ woundSize, onContactAdmin, theme: t }) => {
  return (
    <div className={`${t.glass.card} rounded-lg p-6`}>
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
          <Users className="w-8 h-8 text-yellow-600" />
        </div>
        <h3 className={`text-xl font-semibold ${t.text.primary} mb-2`}>
          Consultation Required
        </h3>
        <p className={`text-sm ${t.text.secondary} mb-4`}>
          Medicare requires special consultation for wounds larger than 450 sq cm.
        </p>
        <div className={`${t.glass.frost} rounded-md p-4 mb-6`}>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className={t.text.secondary}>Current wound size:</span>
              <span className={`font-semibold ${t.text.primary}`}>{woundSize} sq cm</span>
            </div>
            <div className="flex justify-between">
              <span className={t.text.secondary}>Medicare limit:</span>
              <span className={`font-semibold ${t.text.primary}`}>450 sq cm</span>
            </div>
          </div>
        </div>
        <p className={`text-xs ${t.text.tertiary} mb-6`}>
          Please contact MSC Admin for assistance with this order. They will help coordinate the necessary approvals and documentation.
        </p>
        <button
          onClick={onContactAdmin}
          className={`inline-flex items-center px-6 py-3 ${t.button.primary.base} ${t.button.primary.hover} rounded-lg text-sm font-medium`}
        >
          <Phone className="w-4 h-4 mr-2" />
          Contact MSC Admin
        </button>
      </div>
    </div>
  );
};

// Consultation Modal
const ConsultationModal: React.FC<{
  onClose: () => void;
  theme: any;
}> = ({ onClose, theme: t }) => {
  return (
    <div className={`fixed inset-0 z-50 flex items-center justify-center p-4 ${t.modal.backdrop}`}>
      <div className={`${t.modal.container} max-w-md w-full`}>
        <div className={t.modal.header}>
          <div className="flex items-center justify-between">
            <h3 className={`text-lg font-semibold ${t.text.primary}`}>
              Contact MSC Admin
            </h3>

            <button
              type="button"
              title="Close"
              onClick={onClose}
              className={`${t.text.secondary} hover:${t.text.primary}`}
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>
        <div className={t.modal.body}>
          <div className="space-y-4">
            <div className="text-center mb-6">
              <div className="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                <Mail className="w-8 h-8 text-blue-600" />
              </div>
              <p className={`text-sm ${t.text.secondary}`}>
                Please reach out to MSC for assistance with Medicare orders exceeding 450 sq cm.
              </p>
            </div>

            <div className={`${t.glass.frost} rounded-lg p-4 space-y-3`}>
              <div>
                <h4 className={`text-sm font-semibold ${t.text.primary} mb-1`}>
                  Contact Information
                </h4>
                <div className="space-y-2">
                  <div className="flex items-center text-sm">
                    <Users className={`w-4 h-4 mr-2 ${t.text.tertiary}`} />
                    <span className={t.text.secondary}>Ashley (MSC Admin)</span>
                  </div>
                  <div className="flex items-center text-sm">
                    <Mail className={`w-4 h-4 mr-2 ${t.text.tertiary}`} />
                    <a href="mailto:admin@mscwoundcare.com" className="text-blue-600 hover:underline">
                      admin@mscwoundcare.com
                    </a>
                  </div>
                  <div className="flex items-center text-sm">
                    <Phone className={`w-4 h-4 mr-2 ${t.text.tertiary}`} />
                    <span className={t.text.secondary}>Contact MSC Admin</span>
                  </div>
                </div>
              </div>

              <div>
                <h4 className={`text-sm font-semibold ${t.text.primary} mb-1`}>
                  What to Include
                </h4>
                <ul className={`text-xs ${t.text.secondary} space-y-1`}>
                  <li>• Patient information and wound details</li>
                  <li>• Clinical documentation supporting the wound size</li>
                  <li>• Proposed treatment plan</li>
                  <li>• Any previous treatment history</li>
                </ul>
              </div>
            </div>

            <div className={`p-3 ${t.status.info} rounded-md`}>
              <div className="flex items-start">
                <Info className="w-4 h-4 mr-2 flex-shrink-0 mt-0.5" />
                <p className="text-xs">
                  MSC Admin will review your request and coordinate with Medicare to ensure proper authorization and reimbursement.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div className={t.modal.footer}>
          <div className="flex justify-end space-x-3">
            <button
              onClick={onClose}
              className={`px-4 py-2 text-sm font-medium rounded-md ${t.button.ghost.base} ${t.button.ghost.hover}`}
            >
              Close
            </button>
            <a
              href="mailto:admin@mscwoundcare.com?subject=Medicare Consultation Request - Wound >450 sq cm"
              className={`inline-flex items-center px-4 py-2 text-sm font-medium rounded-md ${t.button.primary.base} ${t.button.primary.hover}`}
            >
              <Mail className="w-4 h-4 mr-2" />
              Send Email
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductSelectorQuickRequest;
