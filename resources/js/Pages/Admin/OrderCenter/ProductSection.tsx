import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Package } from 'lucide-react';

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
    shippingInfo: {
      speed: string;
      address: string;
    };
  };
  forms: any;
  clinical: any;
  provider: any;
  submission: any;
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
}) => (
  <SectionCard
    title="Product Information"
    icon={Package}
    sectionKey="product"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="space-y-1">
        <InfoRow label="Product Name" value={orderData.product?.name || 'N/A'} />
        <InfoRow label="Product Code" value={orderData.product?.code || 'N/A'} />
        <InfoRow label="Size" value={orderData.product?.size || 'N/A'} />
        <InfoRow label="Quantity" value={orderData.product?.quantity?.toString() || 'N/A'} />
        <InfoRow label="Category" value={orderData.product?.category || 'N/A'} />
      </div>
      <div className="space-y-1">
        <InfoRow label="Manufacturer" value={orderData.product?.manufacturer || 'N/A'} />
        <InfoRow label="Shipping Speed" value={orderData.product?.shippingInfo?.speed || 'N/A'} />
        <InfoRow label="Shipping Address" value={orderData.product?.shippingInfo?.address || 'N/A'} />
        {userRole !== 'OM' && (
          <InfoRow
            label="Estimated Cost"
            value="$1,200.00"
          />
        )}
      </div>
    </div>
  </SectionCard>
);

export default ProductSection;
