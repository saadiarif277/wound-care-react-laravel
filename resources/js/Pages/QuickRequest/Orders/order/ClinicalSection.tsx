import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData } from '../types/orderTypes';
import { Heart, Ruler, Calendar, FileText } from 'lucide-react';

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
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Basic Clinical Information */}
      <div className="space-y-3">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
          <Heart className="w-4 h-4" />
          Wound Information
        </h4>
        <div className="space-y-1">
          <InfoRow label="Wound Type" value={orderData.clinical?.woundType || 'N/A'} />
          <InfoRow label="Wound Size" value={orderData.clinical?.woundSize || 'N/A'} icon={Ruler} />
          <InfoRow label="Facility" value={orderData.clinical?.facilityInfo || 'N/A'} />
        </div>
      </div>

      {/* Procedure Information */}
      <div className="space-y-3">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
          <FileText className="w-4 h-4" />
          Procedure Details
        </h4>
        <div className="space-y-1">
          <InfoRow label="Procedure Info" value={orderData.clinical?.procedureInfo || 'N/A'} />
          <InfoRow label="Prior Applications" value={orderData.clinical?.priorApplications?.toString() || '0'} />
          <InfoRow label="Anticipated Applications" value={orderData.clinical?.anticipatedApplications?.toString() || '0'} />
        </div>
      </div>

      {/* Diagnosis Codes */}
      <div className="space-y-3 lg:col-span-2">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1">Diagnosis Codes</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <h5 className="font-medium text-xs text-gray-600 mb-2">Primary Diagnosis Codes:</h5>
            {(orderData.clinical?.diagnosisCodes || []).length > 0 ? (
              (orderData.clinical?.diagnosisCodes || []).map((code, index) => (
                <div key={index} className="text-sm bg-blue-50 p-2 rounded mb-1 border border-blue-200">
                  <div className="font-medium text-blue-800">{code.code}</div>
                  <div className="text-blue-600 text-xs">{code.description}</div>
                </div>
              ))
            ) : (
              <div className="text-sm text-gray-500 italic">No diagnosis codes</div>
            )}
          </div>

          <div>
            <h5 className="font-medium text-xs text-gray-600 mb-2">ICD-10 Codes:</h5>
            {(orderData.clinical?.icd10Codes || []).length > 0 ? (
              (orderData.clinical?.icd10Codes || []).map((code, index) => (
                <div key={index} className="text-sm bg-green-50 p-2 rounded mb-1 border border-green-200">
                  <div className="font-medium text-green-800">{code.code}</div>
                  <div className="text-green-600 text-xs">{code.description}</div>
                </div>
              ))
            ) : (
              <div className="text-sm text-gray-500 italic">No ICD-10 codes</div>
            )}
          </div>
        </div>
      </div>
    </div>
  </SectionCard>
);
