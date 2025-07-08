import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { Heart, Calendar, FileText } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: any;
  product: any;
  forms: any;
  clinical: {
    woundType: string;
    woundLocation?: string;
    location: string;
    size: string;
    depth?: string;
    cptCodes: string;
    diagnosisCodes?: string[];
    primaryDiagnosis?: string;
    clinicalNotes?: string;
    placeOfService: string;
    serviceDate?: string;
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
}) => {
  // Format wound type for display
  const formatWoundType = (woundType: string) => {
    if (!woundType) return 'N/A';
    return woundType.split('_').map(word =>
      word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
  };

  // Format wound location for display
  const formatWoundLocation = (location: string) => {
    if (!location) return 'N/A';
    return location.split('_').map(word =>
      word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
  };

  return (
    <SectionCard
      title="Clinical Information"
      icon={Heart}
      sectionKey="clinical"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Wound Information */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Wound Details</h4>
          <InfoRow label="Wound Type" value={formatWoundType(orderData.clinical?.woundType || '')} />
          <InfoRow label="Wound Location" value={formatWoundLocation(orderData.clinical?.woundLocation || orderData.clinical?.location || '')} />
          <InfoRow label="Wound Size" value={orderData.clinical?.size || 'N/A'} />
          {orderData.clinical?.depth && (
            <InfoRow label="Wound Depth" value={orderData.clinical.depth} />
          )}
          <InfoRow label="Place of Service" value={orderData.clinical?.placeOfService || 'N/A'} />
          {orderData.clinical?.serviceDate && (
            <InfoRow label="Expected Service Date" value={orderData.clinical.serviceDate} icon={Calendar} />
          )}
          <InfoRow
            label="Failed Conservative Treatment"
            value={orderData.clinical?.failedConservativeTreatment ? 'Yes' : 'No'}
          />
        </div>

        {/* Codes and Notes */}
        <div className="space-y-3">
          {/* CPT Codes */}
          <div>
            <h4 className="font-medium text-sm mb-2">CPT Codes:</h4>
            <div className="text-sm bg-muted/50 p-2 rounded mb-1">
              <div className="font-medium">{orderData.clinical?.cptCodes || 'N/A'}</div>
            </div>
          </div>

          {/* Diagnosis Codes */}
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

          {/* Clinical Notes */}
          {orderData.clinical?.clinicalNotes && (
            <div>
              <h4 className="font-medium text-sm mb-2">Clinical Notes:</h4>
              <div className="text-sm bg-muted/50 p-2 rounded mb-1">
                <div className="font-medium flex items-start gap-2">
                  <FileText className="w-4 h-4 mt-0.5 flex-shrink-0" />
                  <span>{orderData.clinical.clinicalNotes}</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </SectionCard>
  );
};

export default ClinicalSection;
