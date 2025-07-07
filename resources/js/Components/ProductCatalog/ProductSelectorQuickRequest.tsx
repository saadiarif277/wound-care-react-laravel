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
  size_specific_pricing?: Record<string, {
    area_cm2: number;
    size_specific_price?: number;
    price_per_unit?: number;
    effective_price: number;
    display_label: string;
    formatted_size: string;
  }>;
  size_unit?: string;
  mue?: number; // Maximum Units of Eligibility from database
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


// Helper function to parse size string to get dimensions and area
const parseSizeString = (sizeStr: string): { dimensions: string; area: number } => {
  // Handle size strings like "2x3", "2 x 3", "2X3", etc.
  const dimensionMatch = sizeStr.match(/(\d+\.?\d*)\s*[xX×]\s*(\d+\.?\d*)/);

  if (dimensionMatch) {
    const width = parseFloat(dimensionMatch[1] || '0');
    const height = parseFloat(dimensionMatch[2] || '0');
    const area = width * height;
    return {
      dimensions: `${width} x ${height} cm`,
      area: area
    };
  }

  // If it's just a number (area in cm²), calculate square dimensions
  const area = parseFloat(sizeStr);
  if (!isNaN(area) && area > 0) {
    const sqrtSize = Math.sqrt(area);
    const isSquare = sqrtSize === Math.floor(sqrtSize);

    if (isSquare) {
      return {
        dimensions: `${sqrtSize} x ${sqrtSize} cm`,
        area: area
      };
    } else {
      // For non-square sizes, just show the area
      return {
        dimensions: `${area} cm²`,
        area: area
      };
    }
  }

  // Fallback - return as is
  return {
    dimensions: sizeStr,
    area: 0
  };
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
  const [providerMessage, setProviderMessage] = useState<string | null>(null);

  // Memoize selectedProducts to prevent infinite loops
  const selectedProductsMemo = useMemo(() => selectedProducts, [JSON.stringify(selectedProducts)]);

  // Memoize last 24 hour orders
  const last24HourOrdersMemo = useMemo(() => last24HourOrders, [JSON.stringify(last24HourOrders)]);

  // Fetch products only when we have provider onboarded products
  useEffect(() => {
    fetchProducts();
  }, [JSON.stringify(providerOnboardedProducts), insuranceType, patientState, woundSize]);

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

  // Determine if consultation is required based on insurance type and wound size
  const getAllowedProducts = useMemo(() => {
    const requiresConsultation = insuranceType === 'medicare' && woundSize > 450;
    return {
      requiresConsultation,
      allowedQCodes: providerOnboardedProducts
    };
  }, [insuranceType, woundSize, providerOnboardedProducts]);

  const fetchProducts = async () => {
    try {
      setLoading(true);

      // Build query parameters for server-side filtering
      const params = new URLSearchParams();

      // Add provider onboarded products for primary filtering
      const qCodesString = providerOnboardedProducts.filter(code => code && code.trim()).join(',');
      params.append('onboarded_q_codes', qCodesString);

      const response = await fetch(`/api/products/search?${params.toString()}`);
      const data = await response.json();

      // Check if provider has no products
      if (data.provider_has_no_products) {
        setProviderMessage(data.message || 'This provider has not been onboarded to any products yet. Please contact your MSC administrator to request product access.');
        setProducts([]);
      } else {
        setProviderMessage(null);
        setProducts(data.products || []);
      }
    } catch (error) {
      console.error('Error fetching products:', error);
      setProducts([]);
    } finally {
      setLoading(false);
    }
  };

  // Filter products based on allowed Q-codes
  const filteredProducts = useMemo(() => {
    if (!products || products.length === 0) return [];

    // If provider has no onboarded products, return empty array
    if (providerOnboardedProducts.length === 0) return [];

    // Filter products by onboarded Q-codes
    return products.filter(product =>
      providerOnboardedProducts.includes(product.q_code)
    );
  }, [products, providerOnboardedProducts]);

  // Further filter for available products (not restricted by other conditions)
  const availableProducts = useMemo(() => {
    return filteredProducts.filter(() => {
      // Add any additional filtering logic here if needed
      // For now, all filtered products are available
      return true;
    });
  }, [filteredProducts]);

  // Check for warnings
  useEffect(() => {
    const newWarnings: string[] = [];

    // Check for repeated orders in last 24 hours
    selectedProductsMemo.forEach(item => {
      const recent = last24HourOrdersMemo.find(order =>
        order.productCode === item.product?.q_code
      );

      if (recent) {
        newWarnings.push(
          `Warning: ${item.product?.name} (${item.product?.q_code}) was ordered within the last 24 hours. Please verify this is not a duplicate order.`
        );
      }
    });

    // Note: MUE validation is now handled server-side during order submission
    // The actual MUE limits are not exposed to non-admin users for security reasons
    // Warnings about exceeding MUE will be shown when the order is validated

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

  const calculateTotalCm2 = () => {
    return selectedProductsMemo.reduce((totalCm2, item) => {
      if (item.size) {
        const sizeValue = parseFloat(item.size);
        if (!isNaN(sizeValue)) {
          return totalCm2 + (sizeValue * item.quantity);
        }
      }
      return totalCm2;
    }, 0);
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  if (loading) {
    // Show different loading messages based on what we're waiting for
    const loadingMessage = providerOnboardedProducts.length === 0
      ? 'Loading provider onboarded products...'
      : 'Loading product catalog...';

    return (
      <div className={`flex items-center justify-center py-12 ${t.glass.card} rounded-lg ${className}`}>
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className={`ml-2 ${t.text.secondary}`}>{loadingMessage}</span>
      </div>
    );
  }

  // Show message when provider has no onboarded products
  if (providerMessage && !loading) {
    return (
      <div className={`${t.glass.card} rounded-lg p-8 text-center ${className}`}>
        <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
        <h4 className={`text-lg font-semibold ${t.text.primary} mb-2`}>
          No Products Available
        </h4>
        <p className={`text-sm ${t.text.secondary} mb-4`}>
          {providerMessage}
        </p>
        <div className={`${t.glass.frost} rounded-md p-3 text-left`}>
          <h5 className={`text-sm font-medium ${t.text.primary} mb-2`}>What this means:</h5>
          <ul className={`text-xs ${t.text.secondary} space-y-1`}>
            <li>• The provider needs to complete onboarding with manufacturers</li>
            <li>• Contact your account manager to set up product access</li>
            <li>• Onboarding typically includes training and agreement signatures</li>
          </ul>
        </div>
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
              {/* Available Products */}
              {availableProducts.length > 0 ? (
                <div>
                  <h3 className={`text-sm font-semibold ${t.text.primary} mb-3 flex items-center`}>
                    <Package className="w-4 h-4 mr-2 text-blue-500" />
                    Available Products
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {availableProducts.map(product => (
                      <QuickRequestProductCard
                        key={product.id}
                        product={product}
                        onAdd={addProductToSelection}
                        roleRestrictions={roleRestrictions}
                        isDisabled={selectedProduct !== null && selectedProduct.id !== product.id}
                        canAddMoreSizes={selectedProduct !== null && selectedProduct.id === product.id}
                        isSelected={selectedProduct?.id === product.id}
                        theme={t}
                      />
                    ))}
                  </div>
                </div>
              ) : (
                <div className={`text-center py-12 ${t.glass.frost} rounded-lg`}>
                  <Package className={`w-12 h-12 mx-auto mb-3 ${t.text.muted}`} />
                  <p className={`${t.text.secondary} mb-2`}>No products available</p>
                  <p className={`text-sm ${t.text.muted}`}>
                    No products match your insurance coverage or provider onboarding status.
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
                                {(() => {
                                  const sizeInfo = parseSizeString(item.size);
                                  return (
                                    <>
                                      <h5 className={`text-sm font-medium ${t.text.primary}`}>
                                        Size: {sizeInfo.dimensions}
                                      </h5>
                                      <p className={`text-xs ${t.text.secondary}`}>
                                        {sizeInfo.area} cm²{roleRestrictions.can_see_order_totals && ` • ${formatPrice(unitPrice)} per unit`}
                                      </p>
                                    </>
                                  );
                                })()}
                              </div>
                            ) : (
                              <div>
                                <h5 className={`text-sm font-medium ${t.text.primary}`}>
                                  Standard Size
                                </h5>
                                {roleRestrictions.can_see_order_totals && (
                                  <p className={`text-xs ${t.text.secondary}`}>
                                    {formatPrice(unitPrice)} per unit
                                  </p>
                                )}
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
                            <span className={`text-sm font-medium ${t.text.primary} px-2`}>
                              {item.quantity}
                            </span>
                            <button
                              title="Increase quantity"
                              aria-label="Increase quantity"
                              onClick={() => updateProductQuantity(item.product_id, item.size, item.quantity + 1)}
                              className={`w-6 h-6 rounded-full border ${theme === 'dark' ? 'border-white/20 hover:bg-white/10' : 'border-gray-300 hover:bg-gray-50'} flex items-center justify-center`}
                            >
                              <Plus className="w-3 h-3" />
                            </button>
                          </div>
                          {roleRestrictions.can_see_order_totals && (
                            <div className="text-right">
                              <p className={`text-sm font-semibold ${t.text.primary}`}>
                                {formatPrice(totalPrice)}
                              </p>
                              <p className={`text-xs ${t.text.secondary}`}>
                                {item.quantity} × {formatPrice(unitPrice)}
                              </p>
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}

                  <div className={`border-t ${theme === 'dark' ? 'border-white/10' : 'border-gray-200'} pt-3 space-y-2`}>
                    {/* Total Coverage Area */}
                    {calculateTotalCm2() > 0 && (
                      <div className="flex items-center justify-between">
                        <span className={`text-sm font-medium ${t.text.primary}`}>Total Coverage:</span>
                        <span className={`text-sm font-semibold ${t.text.primary}`}>
                          {calculateTotalCm2()} cm²
                        </span>
                      </div>
                    )}

                    {/* Total Price - Only show if user has permission */}
                    {roleRestrictions.can_see_order_totals && (
                      <>
                        <div className="flex items-center justify-between">
                          <span className={`text-base font-semibold ${t.text.primary}`}>Total Price:</span>
                          <span className="text-lg font-bold text-blue-600">
                            {formatPrice(calculateTotal())}
                          </span>
                        </div>
                        {!roleRestrictions.can_see_msc_pricing && (
                          <p className="text-xs text-yellow-600 mt-1">
                            * Pricing shown is National ASP only
                          </p>
                        )}
                      </>
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
  theme: any;
}> = ({ product, onAdd, roleRestrictions, isDisabled = false, isSelected = false, theme: t }) => {
  const [selectedSize, setSelectedSize] = useState<string>('');
  const [quantity, setQuantity] = useState(1);

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
    if (selectedSize) {
      const sizeValue = parseFloat(selectedSize);
      if (isNaN(sizeValue)) {
        return 0;
      }

      // Try to find size-specific pricing data
      if (product.size_specific_pricing) {
        for (const [label, data] of Object.entries(product.size_specific_pricing)) {
          if (data.area_cm2 === sizeValue) {
            return data.effective_price * quantity;
          }
        }
      }

      // Fallback to calculated price if no size-specific data
      const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
      return pricePerUnit * sizeValue * quantity;
    }

    // No size selected, return base price
    const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
    return pricePerUnit * quantity;
  };

  return (
    <div className={`${t.glass.card} rounded-lg p-4 transition-all duration-200 ${
      isSelected ? 'ring-2 ring-blue-500' : ''
    } ${isDisabled ? 'opacity-50' : ''}`}>
      {isSelected && (
        <div className="flex flex-wrap gap-2 mb-2">
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
            Currently Selected
          </span>
        </div>
      )}

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

      {/* Size Selection - MOVED ABOVE QUANTITY */}
      {(product.size_options && product.size_options.length > 0) ? (
        <div className="mb-3">
          <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
            Size
          </label>
          <select
            value={selectedSize}
            onChange={(e) => setSelectedSize(e.target.value)}
            className={`w-full text-xs ${t.input.base} ${t.input.focus}`}
            disabled={isDisabled}
          >
            <option value="">Select size...</option>
            {product.size_options.map(sizeLabel => {
              const sizeData = product.size_specific_pricing?.[sizeLabel];
              const effectivePrice = sizeData?.effective_price || 0;
              return (
                <option key={sizeLabel} value={sizeData?.area_cm2?.toString() || '0'}>
                  {sizeData?.display_label || sizeLabel} - {formatPrice(effectivePrice)}
                </option>
              );
            })}
          </select>
        </div>
      ) : (product.available_sizes && product.available_sizes.length > 0) ? (
        <div className="mb-3">
          <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
            Size
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
              const sizeNum = parseFloat(sizeStr);

              // Try to find size-specific pricing data first
              let sizeData = null;
              let displayLabel = `${sizeNum} cm²`;
              let effectivePrice = 0;

              if (product.size_specific_pricing) {
                for (const [label, data] of Object.entries(product.size_specific_pricing)) {
                  if (data.area_cm2 === sizeNum) {
                    sizeData = data;
                    displayLabel = data.display_label;
                    effectivePrice = data.effective_price;
                    break;
                  }
                }
              }

              // Fallback to calculated price if no size-specific data
              if (!sizeData) {
                const pricePerUnit = roleRestrictions.can_see_msc_pricing ? (product.msc_price || product.price_per_sq_cm) : product.price_per_sq_cm;
                effectivePrice = pricePerUnit * sizeNum;

                // Try to find a label for this size in size_pricing
                if (product.size_pricing) {
                  for (const [label, area] of Object.entries(product.size_pricing)) {
                    if (area === sizeNum) {
                      displayLabel = label;
                      break;
                    }
                  }
                }
              }

              return (
                <option key={sizeStr} value={sizeStr}>
                  {displayLabel} - {formatPrice(effectivePrice)}
                </option>
              );
            })}
          </select>
        </div>
            ) : (
        // No sizes available - show message
        <div className="mb-3">
          <label className={`block text-xs font-medium ${t.text.primary} mb-1`}>
            Size
          </label>
          <div className={`text-xs ${t.text.secondary} p-2 ${t.glass.frost} rounded`}>
            No sizes available for this product. Please contact your administrator.
          </div>
        </div>
      )}

      {/* Quantity Selection - MOVED BELOW SIZE */}
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
      {roleRestrictions.can_see_order_totals && (
        <div className={`mb-3 p-2 ${t.glass.frost} rounded text-center`}>
          <span className={`text-sm font-semibold ${t.text.primary}`}>
            Total: {formatPrice(calculatePrice())}
          </span>
        </div>
      )}

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
