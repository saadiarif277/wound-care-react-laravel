import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Heart } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: any;
  product: any;
  forms: any;
  clinical: {
    woundType: string;
    location: string;
    size: string;
    cptCodes: string;
    placeOfService: string;
    failedConservativeTreatment: boolean;
  };
  provider: any;
  submission: any;
}

interface ClinicalSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const ClinicalSection: React.FC<ClinicalSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => (
  <SectionCard
    title="Clinical Information"
    icon={Heart}
    sectionKey="clinical"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="space-y-1">
        <InfoRow label="Wound Type" value={orderData.clinical?.woundType || 'N/A'} />
        <InfoRow label="Wound Location" value={orderData.clinical?.location || 'N/A'} />
        <InfoRow label="Wound Size" value={orderData.clinical?.size || 'N/A'} />
        <InfoRow label="Place of Service" value={orderData.clinical?.placeOfService || 'N/A'} />
        <InfoRow label="Failed Conservative Treatment" value={orderData.clinical?.failedConservativeTreatment ? 'Yes' : 'No'} />
      </div>
      <div className="space-y-3">
        <div>
          <h4 className="font-medium text-sm mb-2">CPT Codes:</h4>
          <div className="text-sm bg-muted/50 p-2 rounded mb-1">
            <div className="font-medium">{orderData.clinical?.cptCodes || 'N/A'}</div>
            <div className="text-muted-foreground">Type 2 diabetes with foot ulcer</div>
          </div>
        </div>
        <div>
          <h4 className="font-medium text-sm mb-2">Diagnosis Codes:</h4>
          {orderData.clinical?.diagnosisCodes && orderData.clinical.diagnosisCodes.length > 0 ? (
            orderData.clinical.diagnosisCodes.map((code: string, index: number) => (
              <div key={index} className="text-sm bg-muted/50 p-2 rounded mb-1">
                <div className="font-medium">{code}</div>
              </div>
            ))
          ) : (
            <div className="text-sm bg-muted/50 p-2 rounded mb-1">
              <div className="font-medium">{orderData.clinical?.primaryDiagnosis || 'N/A'}</div>
            </div>
          )}
        </div>
      </div>
    </div>
  </SectionCard>
);

export default ClinicalSection;
