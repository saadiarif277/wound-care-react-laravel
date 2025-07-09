import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { InfoRow } from '@/Pages/QuickRequest/Orders/order/InfoRow';
import { User, Calendar, Phone, Mail, MapPin, CreditCard } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: {
    name: string;
    firstName?: string;
    lastName?: string;
    dob: string;
    gender: string;
    phone: string;
    email?: string;
    address: string;
    memberId?: string;
    isSubscriber?: boolean;
    insurance: {
      primary: string;
      secondary: string;
    };
  };
  insurance?: {
    primary: string;
    secondary: string;
    primaryPlanType?: string;
    secondaryPlanType?: string;
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
}) => {
  // Format patient name from first and last name if available
  const getPatientName = () => {
    if (orderData.patient.firstName && orderData.patient.lastName) {
      return `${orderData.patient.firstName} ${orderData.patient.lastName}`;
    }
    return orderData.patient.name || 'N/A';
  };

  // Format gender for display
  const formatGender = (gender: string) => {
    if (!gender || gender === 'unknown') return 'N/A';
    return gender.charAt(0).toUpperCase() + gender.slice(1);
  };

  return (
    <SectionCard
      title="Patient & Insurance Information"
      icon={User}
      sectionKey="patient"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Patient Information */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Patient Details</h4>
          <InfoRow label="Full Name" value={getPatientName()} />
          <InfoRow label="Date of Birth" value={orderData.patient?.dob || 'N/A'} icon={Calendar} />
          <InfoRow label="Gender" value={formatGender(orderData.patient?.gender || '')} />
          <InfoRow label="Phone" value={orderData.patient?.phone || 'N/A'} icon={Phone} />
          <InfoRow label="Email" value={orderData.patient?.email || 'N/A'} icon={Mail} />
          <InfoRow label="Address" value={orderData.patient?.address || 'N/A'} icon={MapPin} />
          {orderData.patient?.memberId && (
            <InfoRow label="Member ID" value={orderData.patient.memberId} icon={CreditCard} />
          )}
        </div>

        {/* Primary Insurance */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Primary Insurance</h4>
          <InfoRow label="Payer Name" value={orderData.insurance?.primary || orderData.patient?.insurance?.primary || 'N/A'} />
          {orderData.insurance?.primaryPlanType && (
            <InfoRow label="Plan Type" value={orderData.insurance.primaryPlanType} />
          )}
        </div>

        {/* Secondary Insurance */}
        <div className="space-y-1">
          <h4 className="font-medium text-gray-900 mb-3">Secondary Insurance</h4>
          <InfoRow label="Payer Name" value={orderData.insurance?.secondary || orderData.patient?.insurance?.secondary || 'N/A'} />
          {orderData.insurance?.secondaryPlanType && (
            <InfoRow label="Plan Type" value={orderData.insurance.secondaryPlanType} />
          )}
        </div>
      </div>
    </SectionCard>
  );
};

export default PatientInsuranceSection;
