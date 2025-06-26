
import React from 'react';
import { SectionCard } from './SectionCard';
import { InfoRow } from './InfoRow';
import { OrderData } from '../../types/orderTypes';
import { User, Calendar, Phone, Mail, MapPin } from 'lucide-react';

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
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="space-y-1">
        <InfoRow label="Full Name" value={orderData.patient.fullName} />
        <InfoRow label="Date of Birth" value={orderData.patient.dateOfBirth} icon={Calendar} />
        <InfoRow label="Phone" value={orderData.patient.phone} icon={Phone} />
        <InfoRow label="Email" value={orderData.patient.email} icon={Mail} />
        <InfoRow label="Address" value={orderData.patient.address} icon={MapPin} />
      </div>
      <div className="space-y-1">
        <InfoRow label="Primary Insurance" value={orderData.patient.primaryInsurance.payerName} />
        <InfoRow label="Plan" value={orderData.patient.primaryInsurance.planName} />
        <InfoRow label="Policy Number" value={orderData.patient.primaryInsurance.policyNumber} />
        <div className="flex justify-between items-center py-2">
          <span className="font-medium text-sm">Insurance Card:</span>
          <span className="text-sm text-green-600">Uploaded</span>
        </div>
      </div>
    </div>
  </SectionCard>
);
