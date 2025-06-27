import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { User, Calendar, Phone, Mail, MapPin } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: {
    name: string;
    dob: string;
    gender: string;
    phone: string;
    address: string;
    insurance: {
      primary: string;
      secondary: string;
    };
  };
  product: any;
  forms: any;
  clinical: any;
  provider: any;
  submission: any;
}

interface PatientInsuranceSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const PatientInsuranceSection: React.FC<PatientInsuranceSectionProps> = ({
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
        <InfoRow label="Full Name" value={orderData.patient?.name || 'N/A'} />
        <InfoRow label="Date of Birth" value={orderData.patient?.dob || 'N/A'} icon={Calendar} />
        <InfoRow label="Gender" value={orderData.patient?.gender || 'N/A'} />
        <InfoRow label="Phone" value={orderData.patient?.phone || 'N/A'} icon={Phone} />
        <InfoRow label="Address" value={orderData.patient?.address || 'N/A'} icon={MapPin} />
      </div>
      <div className="space-y-1">
        <InfoRow label="Primary Insurance" value={orderData.patient?.insurance?.primary || 'N/A'} />
        <InfoRow label="Secondary Insurance" value={orderData.patient?.insurance?.secondary || 'N/A'} />
        <div className="flex justify-between items-center py-2">
          <span className="font-medium text-sm">Insurance Card:</span>
          <span className="text-sm text-green-600">Uploaded</span>
        </div>
      </div>
    </div>
  </SectionCard>
);

export default PatientInsuranceSection;
