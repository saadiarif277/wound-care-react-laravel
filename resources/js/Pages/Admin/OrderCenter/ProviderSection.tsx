import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Shield, Mail, Phone } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: any;
  product: any;
  forms: any;
  clinical: any;
  provider: {
    name: string;
    npi?: string;
    email?: string;
    facility?: string;
  };
  facility?: {
    name?: string;
    address?: string;
    phone?: string;
  };
  submission: any;
}

interface ProviderSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const ProviderSection: React.FC<ProviderSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => (
  <SectionCard
    title="Provider Information"
    icon={Shield}
    sectionKey="provider"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Provider Information */}
      <div className="space-y-1">
        <h4 className="font-medium text-gray-900 mb-3">Provider Details</h4>
        <InfoRow label="Provider Name" value={orderData.provider?.name || 'N/A'} />
        <InfoRow label="NPI" value={orderData.provider?.npi || 'N/A'} />
        {orderData.provider?.email && (
          <InfoRow label="Email" value={orderData.provider.email} icon={Mail} />
        )}
      </div>

      {/* Facility Information */}
      <div className="space-y-1">
        <h4 className="font-medium text-gray-900 mb-3">Facility Details</h4>
        <InfoRow label="Facility Name" value={orderData.facility?.name || orderData.provider?.facility || 'N/A'} />
        <InfoRow label="Facility Address" value={orderData.facility?.address || 'N/A'} />
        {orderData.facility?.phone && (
          <InfoRow label="Facility Phone" value={orderData.facility.phone} icon={Phone} />
        )}
      </div>
    </div>
  </SectionCard>
);

export default ProviderSection;
