import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Shield } from 'lucide-react';

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
    npi: string;
    facility: string;
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
    <div className="space-y-1">
      <InfoRow label="Provider Name" value={orderData.provider?.name || 'N/A'} />
      <InfoRow label="Facility Name" value={orderData.provider?.facility || 'N/A'} />
      <InfoRow label="Facility Address" value="123 Medical Center Dr, City, State 12345" />
      <InfoRow label="Organization" value="Medical Group Practice" />
      <InfoRow label="NPI" value={orderData.provider?.npi || 'N/A'} />
    </div>
  </SectionCard>
);

export default ProviderSection;
