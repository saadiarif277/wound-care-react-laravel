
import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData, UserRole } from '../../types/orderTypes';
import { Package } from 'lucide-react';

interface ProductSectionProps {
  orderData: OrderData;
  userRole: UserRole;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const ProductSection: React.FC<ProductSectionProps> = ({
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
        <InfoRow label="Size" value={orderData.product?.sizes?.join(', ') || 'N/A'} />
        <InfoRow label="Quantity" value={orderData.product?.quantity?.toString() || '0'} />
      </div>
      {userRole !== 'OM' && (
        <div className="space-y-1">
          <InfoRow label="ASP Total Price" value={`$${orderData.product?.aspPrice?.toFixed(2) || '0.00'}`} />
          <InfoRow 
            label="Amount to be Billed" 
            value={`$${orderData.product?.discountedPrice?.toFixed(2) || '0.00'}`} 
          />
        </div>
      )}
    </div>
  </SectionCard>
);
