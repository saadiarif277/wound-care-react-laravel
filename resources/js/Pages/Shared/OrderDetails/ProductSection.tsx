import React from 'react';
import { ChevronDown, ChevronUp, Package, ShoppingCart, Truck } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ProductSectionProps {
  orderData: {
    orderNumber: string;
    createdDate: string;
    createdBy: string;
    patient: any;
    product: {
      name: string;
      code: string;
      quantity: number;
      size: string;
      category: string;
      manufacturer: string;
      manufacturerId?: number;
      selectedProducts?: any[];
      shippingInfo: {
        speed: string;
        instructions?: string;
      };
    };
    forms: {
      ivrStatus: string;
      orderFormStatus: string;
    };
    submission: {
      documents: any[];
    };
    pricing?: {
      totalOrderValue?: number;
      unitPrice?: number;
      yourPrice?: number;
      nationalASP?: number;
      mscPrice?: number;
    };
  };
  isOpen: boolean;
  onToggle: () => void;
  showPricing?: boolean;
  pricingLevel?: 'common' | 'personal' | 'all' | 'none';
}

const ProductSection: React.FC<ProductSectionProps> = ({
  orderData,
  isOpen,
  onToggle,
  showPricing = false,
  pricingLevel = 'none'
}) => {
  const { theme } = useTheme();
  const colors = themes[theme];

  const renderField = (label: string, value: string | number | undefined, className?: string) => {
    return (
      <div className={cn("flex justify-between items-center py-2", className)}>
        <span className="font-medium text-gray-700">{label}:</span>
        <span className="text-gray-900">{value || 'N/A'}</span>
      </div>
    );
  };

  const formatPrice = (price: number | undefined | null): string => {
    if (price === null || price === undefined || isNaN(price)) {
      return 'Contact for pricing';
    }
    return `$${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const renderPricingInfo = () => {
    if (!showPricing || pricingLevel === 'none') return null;

    const pricing = orderData.pricing;

    return (
      <div className="space-y-2 pt-4 border-t border-gray-200">
        <h4 className="font-semibold text-gray-800 flex items-center gap-2">
          <ShoppingCart className="w-4 h-4" />
          Pricing Information
        </h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {pricingLevel === 'common' && (
            <>
              <div className="bg-blue-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-blue-800">National ASP</p>
                <p className="text-lg font-bold text-blue-900">{formatPrice(pricing?.nationalASP)}</p>
              </div>
              <div className="bg-green-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-green-800">MSC Price (40% off ASP)</p>
                <p className="text-lg font-bold text-green-900">{formatPrice(pricing?.mscPrice)}</p>
              </div>
            </>
          )}
          {(pricingLevel === 'personal' || pricingLevel === 'all') && (
            <>
              <div className="bg-purple-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-purple-800">Your Price</p>
                <p className="text-lg font-bold text-purple-900">{formatPrice(pricing?.yourPrice || pricing?.unitPrice)}</p>
              </div>
              <div className="bg-orange-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-orange-800">Total Value</p>
                <p className="text-lg font-bold text-orange-900">{formatPrice(pricing?.totalOrderValue)}</p>
              </div>
            </>
          )}
          {pricingLevel === 'all' && (
            <>
              <div className="bg-blue-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-blue-800">National ASP</p>
                <p className="text-lg font-bold text-blue-900">{formatPrice(pricing?.nationalASP)}</p>
              </div>
              <div className="bg-green-50 p-3 rounded-lg">
                <p className="text-sm font-medium text-green-800">MSC Price (40% off ASP)</p>
                <p className="text-lg font-bold text-green-900">{formatPrice(pricing?.mscPrice)}</p>
              </div>
            </>
          )}
        </div>
      </div>
    );
  };

  return (
    <div className={cn(
      "rounded-lg border transition-all duration-200",
      colors.card,
      colors.border,
      "hover:shadow-lg"
    )}>
      <button
        onClick={onToggle}
        className={cn(
          "w-full p-4 flex items-center justify-between text-left transition-colors",
          colors.hover
        )}
      >
        <div className="flex items-center gap-3">
          <Package className="w-5 h-5 text-blue-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            Product Information
          </h3>
        </div>
        {isOpen ? (
          <ChevronUp className="w-5 h-5 text-gray-400" />
        ) : (
          <ChevronDown className="w-5 h-5 text-gray-400" />
        )}
      </button>

      {isOpen && (
        <div className="p-4 pt-0 space-y-4">
          {/* Product Details */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <Package className="w-4 h-4" />
                Product Details
              </h4>
              {renderField("Product Name", orderData.product?.name)}
              {renderField("Product Code", orderData.product?.code)}
              {renderField("Category", orderData.product?.category)}
              {renderField("Size", orderData.product?.size)}
              {renderField("Quantity", orderData.product?.quantity)}
            </div>

            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <Truck className="w-4 h-4" />
                Manufacturer & Shipping
              </h4>
              {renderField("Manufacturer", orderData.product?.manufacturer)}
              {renderField("Shipping Speed", orderData.product?.shippingInfo?.speed)}
              {orderData.product?.shippingInfo?.instructions && 
                renderField("Special Instructions", orderData.product?.shippingInfo?.instructions)}
            </div>
          </div>

          {/* Pricing Information (conditional) */}
          {renderPricingInfo()}

          {/* Selected Products (if multiple) */}
          {orderData.product?.selectedProducts && orderData.product?.selectedProducts.length > 0 && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800">Selected Products</h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                {orderData.product?.selectedProducts?.map((product, index) => (
                  <div key={index} className="flex justify-between items-center py-1">
                    <span>Product ID: {product?.product_id}</span>
                    <span>Qty: {product?.quantity}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Order Status */}
          <div className="pt-4 border-t border-gray-200">
            <div className="flex justify-between items-center">
              <span className="font-medium text-gray-700">Order Status:</span>
              <span className={cn(
                "px-3 py-1 rounded-full text-sm font-medium",
                orderData.forms?.orderFormStatus === 'completed' 
                  ? 'bg-green-100 text-green-800'
                  : orderData.forms?.orderFormStatus === 'in_progress'
                  ? 'bg-yellow-100 text-yellow-800'
                  : 'bg-gray-100 text-gray-800'
              )}>
                {orderData.forms?.orderFormStatus || 'N/A'}
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductSection; 