import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Package, Calendar, Truck } from 'lucide-react';
import { formatHumanReadableDate } from '@/utils/dateUtils';

interface OrderData {
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
    shippingInfo: {
      speed: string;
      address: string;
    };
  };
  orderPreferences?: {
    expectedServiceDate?: string;
    shippingSpeed?: string;
    placeOfService?: string;
    deliveryInstructions?: string;
  };
  forms: any;
  clinical: any;
  provider: any;
  submission: any;
  total_amount?: number;
}

interface ProductSectionProps {
  orderData: OrderData;
  userRole: 'Provider' | 'OM' | 'Admin';
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const ProductSection: React.FC<ProductSectionProps> = ({
  orderData,
  userRole,
  isOpen,
  onToggle
}) => {
  const formatCurrency = (amount: number | undefined) => {
    if (amount === undefined || amount === null) return 'N/A';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  return (
    <SectionCard
      title="Product Information"
      icon={Package}
      sectionKey="product"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Product Details */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Product Details</h4>
          <InfoRow label="Product Name" value={orderData.product?.name || 'N/A'} />
          <InfoRow label="Product Code" value={orderData.product?.code || 'N/A'} />
          <InfoRow label="Category" value={orderData.product?.category || 'N/A'} />
          <InfoRow label="Manufacturer" value={orderData.product?.manufacturer || 'N/A'} />
          {orderData.product?.manufacturerId && (
            <InfoRow label="Manufacturer ID" value={orderData.product.manufacturerId.toString()} />
          )}
          <InfoRow label="Quantity" value={orderData.product?.quantity?.toString() || 'N/A'} />
          <InfoRow label="Size" value={orderData.product?.size || 'N/A'} />
        </div>

        {/* Order Details */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Order Details</h4>
          <InfoRow label="Total Order Value" value={formatCurrency(orderData.total_amount)} />
          {orderData.orderPreferences?.expectedServiceDate && (
            <InfoRow label="Expected Service Date" value={formatHumanReadableDate(orderData.orderPreferences.expectedServiceDate)} icon={Calendar} />
          )}
          <InfoRow label="Shipping Speed" value={orderData.orderPreferences?.shippingSpeed || orderData.product?.shippingInfo?.speed || 'Standard'} icon={Truck} />
          {orderData.orderPreferences?.placeOfService && (
            <InfoRow label="Place of Service" value={orderData.orderPreferences.placeOfService} />
          )}
          {orderData.orderPreferences?.deliveryInstructions && (
            <InfoRow label="Delivery Instructions" value={orderData.orderPreferences.deliveryInstructions} />
          )}
          <InfoRow label="Shipping Address" value={orderData.product?.shippingInfo?.address || 'N/A'} />
        </div>
      </div>
    </SectionCard>
  );
};

export default ProductSection;
