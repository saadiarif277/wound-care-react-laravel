import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData } from '../types/orderTypes';
import { User, Calendar, Phone, Mail, MapPin, Shield, CreditCard } from 'lucide-react';

interface PatientInsuranceSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const PatientInsuranceSection: React.FC<PatientInsuranceSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => (
  <SectionCard
    title="Patient & Insurance Information"
    icon={User}
    sectionKey="patient"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {/* Patient Information */}
      <div className="space-y-3">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1">Patient Details</h4>
        <div className="space-y-1">
          <InfoRow label="Full Name" value={orderData.patient?.fullName || 'N/A'} />
          <InfoRow label="Date of Birth" value={orderData.patient?.dateOfBirth || 'N/A'} icon={Calendar} />
          <InfoRow label="Phone" value={orderData.patient?.phone || 'N/A'} icon={Phone} />
          <InfoRow label="Email" value={orderData.patient?.email || 'N/A'} icon={Mail} />
          <InfoRow label="Address" value={orderData.patient?.address || 'N/A'} icon={MapPin} />
        </div>
      </div>

      {/* Primary Insurance */}
      <div className="space-y-3">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
          <Shield className="w-4 h-4" />
          Primary Insurance
        </h4>
        <div className="space-y-1">
          <InfoRow label="Payer Name" value={orderData.patient?.primaryInsurance?.payerName || 'N/A'} />
          <InfoRow label="Plan Type" value={orderData.patient?.primaryInsurance?.planName || 'N/A'} />
          <InfoRow label="Policy Number" value={orderData.patient?.primaryInsurance?.policyNumber || 'N/A'} />
        </div>
        <div className="flex justify-between items-center py-2">
          <span className="font-medium text-sm">Insurance Card:</span>
          <span className={`text-sm px-2 py-1 rounded ${orderData.patient?.insuranceCardUploaded ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
            {orderData.patient?.insuranceCardUploaded ? 'Uploaded' : 'Not Uploaded'}
          </span>
        </div>
      </div>

      {/* Secondary Insurance */}
      <div className="space-y-3">
        <h4 className="font-medium text-sm text-gray-700 border-b pb-1 flex items-center gap-1">
          <CreditCard className="w-4 h-4" />
          Secondary Insurance
        </h4>
        {orderData.patient?.secondaryInsurance ? (
          <div className="space-y-1">
            <InfoRow label="Payer Name" value={orderData.patient.secondaryInsurance.payerName || 'N/A'} />
            <InfoRow label="Plan Type" value={orderData.patient.secondaryInsurance.planName || 'N/A'} />
            <InfoRow label="Policy Number" value={orderData.patient.secondaryInsurance.policyNumber || 'N/A'} />
          </div>
        ) : (
          <div className="text-sm text-gray-500 italic">No secondary insurance</div>
        )}
      </div>
    </div>
  </SectionCard>
);
