
import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData } from '../../types/orderTypes';
import { Heart } from 'lucide-react';

interface ClinicalSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const ClinicalSection: React.FC<ClinicalSectionProps> = ({
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
        <InfoRow label="Wound Size" value={orderData.clinical?.woundSize || 'N/A'} />
        <InfoRow label="Procedure" value={orderData.clinical?.procedureInfo || 'N/A'} />
        <InfoRow label="Prior Applications" value={orderData.clinical?.priorApplications?.toString() || '0'} />
        <InfoRow label="Anticipated Applications" value={orderData.clinical?.anticipatedApplications?.toString() || '0'} />
        <InfoRow label="Facility" value={orderData.clinical?.facilityInfo || 'N/A'} />
      </div>
      <div className="space-y-3">
        <div>
          <h4 className="font-medium text-sm mb-2">Diagnosis Codes:</h4>
          {(orderData.clinical?.diagnosisCodes || []).map((code, index) => (
            <div key={index} className="text-sm bg-muted/50 p-2 rounded mb-1">
              <div className="font-medium">{code.code}</div>
              <div className="text-muted-foreground">{code.description}</div>
            </div>
          ))}
        </div>
        <div>
          <h4 className="font-medium text-sm mb-2">ICD-10 Codes:</h4>
          {(orderData.clinical?.icd10Codes || []).map((code, index) => (
            <div key={index} className="text-sm bg-muted/50 p-2 rounded mb-1">
              <div className="font-medium">{code.code}</div>
              <div className="text-muted-foreground">{code.description}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  </SectionCard>
);
