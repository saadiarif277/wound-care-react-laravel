
import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData } from '../../types/orderTypes';
import { Shield } from 'lucide-react';

interface ProviderSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const ProviderSection: React.FC<ProviderSectionProps> = ({
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
      <InfoRow label="Facility Name" value={orderData.provider?.facilityName || 'N/A'} />
      <InfoRow label="Facility Address" value={orderData.provider?.facilityAddress || 'N/A'} />
      <InfoRow label="Organization" value={orderData.provider?.organization || 'N/A'} />
      <InfoRow label="NPI" value={orderData.provider?.npi || 'N/A'} />
    </div>
  </SectionCard>
);
