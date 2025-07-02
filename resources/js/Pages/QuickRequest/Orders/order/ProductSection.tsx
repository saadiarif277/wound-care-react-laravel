import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData, UserRole } from '../types/orderTypes';
import { Package, DollarSign, Hash } from 'lucide-react';

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
    <div className="space-y-4">
      {/* Product Summary */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="space-y-3">
          <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
            <Package className="w-4 h-4" />
            Product Details
          </h4>
          <div className="space-y-1">
            <InfoRow label="Product Name" value={orderData.product?.name || 'N/A'} />
            <InfoRow label="Size" value={orderData.product?.sizes?.join(', ') || 'N/A'} />
            <InfoRow label="Quantity" value={orderData.product?.quantity?.toString() || '0'} />
          </div>
        </div>

        {userRole !== 'OM' && (
          <div className="space-y-3">
            <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
              <DollarSign className="w-4 h-4" />
              Pricing Information
            </h4>
            <div className="space-y-1">
              <InfoRow label="ASP Total Price" value={`$${orderData.product?.aspPrice?.toFixed(2) || '0.00'}`} />
              <InfoRow
                label="Amount to be Billed"
                value={`$${orderData.product?.discountedPrice?.toFixed(2) || '0.00'}`}
              />
            </div>
          </div>
        )}
      </div>

      {/* Coverage Warnings */}
      {orderData.product?.coverageWarnings && orderData.product.coverageWarnings.length > 0 && (
        <div className="space-y-3">
          <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
            <Hash className="w-4 h-4" />
            Coverage Warnings
          </h4>
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            {orderData.product.coverageWarnings.map((warning, index) => (
              <div key={index} className="text-sm text-yellow-800 mb-1">
                • {warning}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  </SectionCard>
);
